<?php
/*
 * @author xuruiqi
 * @date 2014-07-17
 * @copyright reetsee.com
 * @desc 爬虫对象的默认类
 *       Crawler objects' default class
 */
abstract class Phpfetcher_Crawler_QQNewsRedis extends Phpfetcher_Crawler_Abstract {
    const MAX_DEPTH = 20;
    const MAX_PAGE_NUM = -1;
    const MODIFY_JOBS_SET = 1;
    const MODIFY_JOBS_DEL = 2;
    const MODIFY_JOBS_ADD = 3;
    const DEFAULT_PAGE_CLASS = 'Phpfetcher_Page_Default';
    const ABSTRACT_PAGE_CLASS = 'Phpfetcher_Page_Abstract';

    const INT_TYPE = 1;
    const STR_TYPE = 2;
    const ARR_TYPE = 3;

    protected static $arrJobFieldTypes = array(
        'start_page' => self::STR_TYPE, 
        'link_rules' => self::ARR_TYPE, 
        'max_depth'  => self::INT_TYPE, 
        'max_pages'  => self::INT_TYPE,
    );

    /*
    protected static $arrJobDefaultFields = array(
        'max_depth' => self::MAX_DEPTH,
        'max_pages' => self::MAX_PAGE_NUM,
    );
     */
    
    protected $_arrFetchJobs = array();//爬虫任务数据
    protected $_arrHash = array();//用hash形式保存爬取过的url
    protected $_arrAdditionalUrls = array();
    protected $_objSchemeTrie = array(); //合法url scheme的字典树
    //protected $_objPage = NULL; //Phpfetcher_Page_Default;
    protected $_redis;
    public function __construct($arrInitParam = array()) {
        if (!isset($arrInitParam['url_schemes'])) {
            $arrInitParam['url_schemes'] = array("http", "https", "ftp");
        }

        $this->_objSchemeTrie = 
                new Phpfetcher_Util_Trie($arrInitParam['url_schemes']);
    }

    public function setRedis( $redis_obj ){
        $this->_redis = $redis_obj;
    }

    /**
     * @author xuruiqi
     * @param
     *      array $arrInput:
     *          array <任务名1> :
     *              string 'start_page',    //爬虫的起始页面
     *              array  'link_rules':   //爬虫跟踪的超链接需要满足的正则表达式，依次检查规则，匹配其中任何一条即可
     *                  string 0,   //正则表达式1
     *                  string 1,   //正则表达式2
     *                  ...
     *                  string n-1, //正则表达式n
     *              int    'max_depth' ,    //爬虫最大的跟踪深度，目前限制最大值不超过20
     *              int    'max_pages' ,    //最多爬取的页面数，默认指定为-1，表示没有限制
     *          array <任务名2> :
     *              ...
     *              ...
     *          ...
     *          array <任务名n-1>:
     *              ...
     *              ...
     *
     * @return
     *      Object $this : returns the instance itself
     * @desc add by what rules the crawler should fetch the pages
     *       if a job has already been in jobs queue, new rules will
     *       cover the old ones.
     */
    public function &addFetchJobs($arrInput = array()) {
        return $this->_modifyFetchJobs($arrInput, self::MODIFY_JOBS_ADD);
    }

    /**
     * @author xuruiqi
     * @param
     *      array $arrInput :
     *          mixed 0 :
     *              任务名
     *          mixed 1 :
     *              任务名
     *          ... ...
     * @return
     *      Object $this : returns the instance itself
     * @desc delete fetch rules according to job names
     */
    public function &delFetchJobs($arrInput = array()) {
        return $this->_modifyFetchJobs($arrInput, self::MODIFY_JOBS_DEL);
    }

    public function getFetchJobByName($job_name) {
        return $this->_arrFetchJobs[$strJobName];
    }

    public function getFetchJobs() {
        return $this->_arrFetchJobs;
    }

    /*
    public function handlePage() {
        //由用户继承本类并实现此方法
    }
     */

    /**
     * @author xuruiqi
     * @param : 
     *      //$intOptType === MODIFY_JOBS_SET|MODIFY_JOBS_ADD,
     *        $arrInput参见addFetchJobs的入参$arrInput
     *      //$intOptType === MODIFY_JOBS_DEL,
     *        $arrInput参见delFetchJobs的入参$arrInput
     *
     * @return
     *      Object $this : returns the instance itself
     * @desc set fetch rules.
     */
    protected function &_modifyFetchJobs($arrInput = array(), $intOptType) {
        $arrInvalidJobs = array();
        if ($intOptType === self::MODIFY_JOBS_SET || $intOptType === self::MODIFY_JOBS_ADD) {
            if ($intOptType === self::MODIFY_JOBS_SET) {
                $this->_arrFetchJobs = array();//如果是设置爬虫任务队列，先将队列清空
            }
            foreach ($arrInput as $job_name => $job_rules) {
                $this->_correctJobParam($job_rules);//规则纠正
                if ($this->_isJobValid($job_rules)) {//判断爬虫任务规则是否合法
                    $this->_arrFetchJobs[$job_name] = $job_rules;
                } else {
                    $arrInvalidJobs[] = $job_name;
                }
            }
        } else if ($intOptType === self::MODIFY_JOBS_DEL) {//当操作为删除时，根据规则名删除规则
            foreach ($arrInput as $job_name) {
                unset($this->_arrFetchJobs[$job_name]);
            }
        } else {
            Phpfetcher_Log::warning("Unknown options for fetch jobs [{$intOptType}]");
        }


        if (!empty($arrInvalidJobs)) {
            Phpfetcher_Log::notice('Invalid jobs:' . implode(',', $arrInvalidJobs));
        }
        return $this;
    }

    /**
     * @author xuruiqi
     * @param : 参见addFetchJobs的入参$arrInput
     *
     * @return
     *      Object $this : returns the instance itself
     * @desc set fetch jobs.
     */
    public function &setFetchJobs($arrInput = array()) {
        return $this->_modifyFetchJobs($arrInput, self::MODIFY_JOBS_SET);
    }

    /**
     * @author xuruiqi
     * @param
     *      array $arrInput : //运行设定
     *          string 'page_class_name' : //指定要使用的Page类型，必须是
     *                                     //Phpfetcher_Page_Abstract的
     *                                     //子类
     *          [array 'page_conf'] : //Page调用setConf时的输入参数，可选
     * @return
     *      obj $this
     * @desc
     */
    public function &run($arrInput = array()) {
        if ( !$this->_redis ){
            Phpfetcher_Log::warning("Redis Error.");
            return false;
        }
        //检测任务队列是否为空
        if (empty($this->_arrFetchJobs)) {
            Phpfetcher_Log::warning("No fetch jobs.");
            return $this;
        }

        //构建Page对象
        $objPage = NULL;
        $strPageClassName = self::DEFAULT_PAGE_CLASS;//使用默认的页面类的名字
        if (!empty($arrInput['page_class_name'])) {
            $strPageClassName = strval($arrInput['page_class_name']);
        }
        try {
            //判断自定义Page类是否存在
            if (!class_exists($strPageClassName, TRUE)) {
                throw new Exception("[$strPageClassName] class not exists!");
            }

            $objPage = new $strPageClassName;
            //判断自定义的Page类是否继承自Phpfetcher_Page_Abstract
            if (!($objPage instanceof Phpfetcher_Page_Abstract)) {
                throw new Exception("[$strPageClassName] is not an instance of " . self::ABSTRACT_PAGE_CLASS);
            }
        } catch (Exception $e) {
            Phpfetcher_Log::fatal($e->getMessage());
            return $this;//?
        }

        //引入配置信息
        $arrPageConf = empty($arrInput['page_conf']) ? array() : $arrInput['page_conf'];
        //初始化Page对象
        $objPage->init();
        if (!empty($arrPageConf)) {
            if(isset($arrPageConf['url'])) {
                unset($arrPageConf['url']);
            }
            $objPage->setConf($arrPageConf);//对page对象进行配置
        }

        //遍历任务队列
        foreach ($this->_arrFetchJobs as $job_name => $job_rules) {
            //检查job_rules填写是否符合规范
            if (!($this->_isJobValid($job_rules))) {
                Phpfetcher_Log::warning("Job rules invalid [" . serialize($job_rules) . "]");
                continue;
            }

            $intDepth   = 0;//深度
            $intPageNum = 0;//爬取的页面数量
            $this->_redis->sadd( 'need:crawl:links', $job_rules['start_page'] );

            //当 need:crawl:links 列表中有数据的时候，进行循环
            while ( $this->_redis->scard( 'need:crawl:links' ) > 0 
                && ($job_rules['max_depth'] === -1 || $intDepth < $job_rules['max_depth']) //深度不溢出
                && ($job_rules['max_pages'] === -1 || $intPageNum < $job_rules['max_pages'])) {//页码不溢出

                //从 need:crawl:links 中取一条数据
                $url = $this->_redis->spop( 'need:crawl:links' );

                //两个条件都不符合
                if ( $job_rules['max_pages'] !== -1 && $intPageNum > $job_rules['max_pages'] ) {
                    //如果页数超标，结束
                    break;
                }
                $objPage->setUrl($url);//设置页面url
                $objPage->read();//读取页面内容

                //获取所有的超链接
                $arrLinks  = $objPage->getHyperLinks();
        		if( empty( $arrLinks ) ){
        			continue;
        		}
                //解析当前URL的各个组成部分，以应对超链接中存在站内链接
                //的情况，如"/entry"等形式的URL
                $strCurUrl = $objPage->getUrl();
                $arrUrlComponents = parse_url($strCurUrl);//对当前页面url的解析
                
                //处理当前页面中的连接
                //遍历用户自定义的link_rules
                foreach ($job_rules['link_rules'] as $link_rule) {
                    foreach ($arrLinks as $link) {//遍历当前页面中的链接
                        //寻找符合rule的链接，并且链接没有被爬过。
                        if (preg_match($link_rule, $link) === 1
                                && !$this->getHash($link)) {
                            //拼出实际的URL
                            $real_link = $link;

                            //不使用strpos，防止扫描整个字符串
                            //这里只需要扫描前6个字符即可
                            $colon_pos = false;//冒号的位置

                            for ($i = 0; $i <= 5; ++$i) {
                                if ($link[$i] == ':') {
                                    $colon_pos = $i;
                                    break;
                                }
                            }
                            //判断是否为站内地址
                            if ($colon_pos === false
                                    || !$this->_objSchemeTrie->has(
                                        substr($link, 0, $colon_pos))) {
                                //将站内地址转换为完整地址
                                $real_link = $arrUrlComponents['scheme']
                                        . "://"
                                        . $arrUrlComponents['host']
                                        . (isset($arrUrlComponents['port'])
                                            && strlen($arrUrlComponents['port']) != 0 ?
                                                ":{$arrUrlComponents['port']}" :
                                                "")
                                        . ($link[0] == '/' ?
                                            $link : "/$link");
                            }
                            //判断 crawled:links 集合中是否已有
                            if( self::isGoodUrl( $real_link ) && empty( $this->_redis->sismember( 'crawled:links', $real_link ) ) ){
                                //没有
                                $this->_redis->sadd( 'need:crawl:links', $real_link );
                            }
                        }
                    }
                }//处理网页内url foreach ($job_rules['link_rules'] as $link_rule)

                //由用户实现handlePage函数
                $objPage->setExtraInfo(array('job_name' => $job_name ));
                $this->handlePage($objPage);

                $this->saveUrl( 'crawled:links', $strCurUrl );//记录相对地址的hash值
                $intPageNum += 1;
            }
        }
        return $this;
    }

    protected function _correctJobParam(&$job_rules) {
        /*
        foreach (self::$arrJobDefaultFields as $field => $value) {
            if (!isset($job_rules[$field]) || ($job_rules['']))
        }
         */
        if (!isset($job_rules['max_depth']) || (self::MAX_DEPTH !== -1 && self::MAX_DEPTH < $job_rules['max_depth'])) {
            $job_rules['max_depth'] = self::MAX_DEPTH;
        }

        if (!isset($job_rules['max_pages']) || (self::MAX_PAGE_NUM !== -1 && self::MAX_PAGE_NUM < $job_rules['max_pages'])) {
            $job_rules['max_pages'] = self::MAX_PAGE_NUM;
        }
    }

    /**
     * @author xuruiqi
     * @desc check if a rule is valid
     */
    protected function _isJobValid($arrRule) {
        foreach (self::$arrJobFieldTypes as $field => $type) {
            if (!isset($arrRule[$field]) || ($type === self::ARR_TYPE && !is_array($arrRule[$field]))) {
                //当四种规则缺少一个或者对值不应当是数组的键赋值数组
                return FALSE;
            }
        }
        return TRUE;
    }

    protected static function _swap(&$a, &$b) {
        $tmp = $a;
        $a = $b;
        $b = $tmp;
    }

    public function getHash($strRawKey) {
        $strRawKey = strval($strRawKey);
        $strKey = md5($strRawKey);
        if (isset($this->_arrHash[$strKey])) {
            return $this->_arrHash[$strKey];
        }
        return NULL;
    }

    public function saveUrl($set_name, $value) {
        $value = strval($value);
        $this->_redis->sadd( $set_name, $value );
    }

    public function setHashIfNotExist($strRawKey, $value) {
        $strRawKey = strval($strRawKey);
        $strKey = md5($strRawKey);

        $bolExist = true;
        if (!isset($this->_arrHash[$strKey])) {
            $this->_arrHash[$strKey] = $value;
            $bolExist = false;
        }

        return $bolExist;
    }

    public function clearHash() {
        $this->_arrHash = array();
    }

    public function addAdditionalUrls($url) {
        if (!is_array($url)) {
            $url = array($url);
        }

        $intAddedNum = 0;
        foreach ($url as $strUrl) {
            $strUrl = strval($strUrl);

            if ($this->setHashIfNotExist($strUrl, true) === false) {
                $this->_arrAdditionalUrls[] = $strUrl;
                ++$intAddedNum;
            }
        }

        return $intAddedNum;
    }

    public static function isGoodUrl( $url ){
        $date = self::getDateFromUrl( $url );
        //echo $date . PHP_EOL;
	if( $date >= 20150101 ){
		//echo "TRUE";
            return true;
        }
        return false;
    }

    public static function getDateFromUrl( $url ){
        $pattern = "#/a/\d+/#";
        $matchs = array();
        preg_match( $pattern, $url, $matchs );
        if( isset( $matchs[0] ) ){
            return $news_data = intval( substr( $matchs[0], 3, -1 ) );
        }
        return false;
    }
};
?>
