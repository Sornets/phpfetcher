<?php
/*
 * @author xuruiqi
 * @date   2014.06.28
 * @copyright reetsee.com
 * @desc Default Page class
 */
class Phpfetcher_Page_Default extends Phpfetcher_Page_Abstract {

    protected static $_arrField2CurlOpt = array(
        /* bool */
        'include_header' => CURLOPT_HEADER,//启用时会将头文件的信息作为数据流输出。
        'exclude_body'   => CURLOPT_NOBODY,//TRUE 时将不输出 BODY 部分。同时 Mehtod 变成了 HEAD。修改为 FALSE 时不会变成 GET。
        'is_post'        => CURLOPT_POST,//
        'is_verbose'     => CURLOPT_VERBOSE,
        'return_transfer'=> CURLOPT_RETURNTRANSFER,//true:将服务器返回的内容以字符串形式返回，false:直接输出

        /* int */
        'buffer_size'       => CURLOPT_BUFFERSIZE,
        'connect_timeout'   => CURLOPT_CONNECTTIMEOUT,//等待时间(s)
        'connect_timeout_ms' => CURLOPT_CONNECTTIMEOUT_MS,//等待时间(ms)
        'dns_cache_timeout' => CURLOPT_DNS_CACHE_TIMEOUT,
        'max_redirs'        => CURLOPT_MAXREDIRS,//最大重定向次数
        'port'              => CURLOPT_PORT,
        'timeout'           => CURLOPT_TIMEOUT,//curl函数执行的秒数(s)
        'timeout_ms'        => CURLOPT_TIMEOUT_MS,//curl函数执行的秒数(ms)

        /* string */
        'cookie'            => CURLOPT_COOKIE,
        'cookie_file'       => CURLOPT_COOKIEFILE,
        'cookie_jar'        => CURLOPT_COOKIEJAR,
        'post_fields'       => CURLOPT_POSTFIELDS,
        'url'               => CURLOPT_URL, // 需要获取的 URL 地址，也可以在curl_init() 初始化会话的时候。
        'user_agent'        => CURLOPT_USERAGENT,// 在HTTP请求中包含一个"User-Agent: "头的字符串。
        'user_pwd'          => CURLOPT_USERPWD,

        /* array */
        'http_header'       => CURLOPT_HTTPHEADER,

        /* stream resource */
        'file'              => CURLOPT_FILE,

        /* function or a Closure */
        'write_function'    => CURLOPT_WRITEFUNCTION,
    );

    //page 对象的默认配置
    protected $_arrDefaultConf = array(
            'connect_timeout' => 10,
            'max_redirs'      => 10,
            'return_transfer' => 1,   //need this
            'timeout'         => 15,
            'url'             => NULL,
            'user_agent'      => 'firefox'
    );

    protected $_arrConf    = array();//page对象的配置
    protected $_arrExtraInfo = array();
    protected $_bolCloseCurlHandle = FALSE;
    protected $_curlHandle = NULL;//curl句柄
    protected $_dom        = NULL;
    //protected $_xml      = NULL;

    public function __construct() {
    }
    public function __destruct() {
        if ($this->_bolCloseCurlHandle) {
            curl_close($this->_curlHandle);
        }
    }

    public static function formatRes($data, $errcode, $errmsg = NULL) {
        if ($errmsg === NULL) {
            $errmsg = Phpfetcher_Error::getErrmsg($errcode);
        }
        return array('errcode' => $errcode, 'errmsg' => $errmsg, 'res' => $data);
    }

    /**
     * @author xuruiqi
     * @desc get configurations.
     */
    public function getConf() {
        return $this->_arrConf;
    }

    /**
     * @author xuruiqi
     * @param $key: specified field
     * @return
     *      bool  : false when field doesn't exist
     *      mixed : otherwise
     * @desc get a specified configuration.
     */
    public function getConfField($key) {
        if (isset($this->_arrConf[$key])) {
            return self::formatRes($this->_arrConf[$key], Phpfetcher_Error::ERR_SUCCESS);
        } else {
            return self::formatRes(NULL, Phpfetcher_Error::ERR_FIELD_NOT_SET);
        }
    }

    public function getContent() {
        return $this->_strContent;
    }

    public function getExtraInfo($arrInput) {
        $arrOutput = array();
        foreach ($arrInput as $req_key) {
            $arrOutput[$req_key] = $this->_arrExtraInfo[$req_key];
        }
        return $arrOutput;
    }

    public function getHyperLinks() {
        if ($this->_dom === NULL) {
            //Phpfetcher_Log::warning('$this->_dom is NULL!');
            echo $this->getUrl() . ' page dom is NULL!' . PHP_EOL;
	    return NULL;
        }

	$arrLinks = array();
        $res = $this->sel('//a');
        for ($i = 0; $i < count($res); ++$i) {
            $arrLinks[] = $res[$i]->href;
        }
        /*
        foreach ($res as $node) {
            $arrLinks[] = $node->href;
        }
         */
        return $arrLinks;
    }

    /**
     * @author xuruiqi
     * @param
     * @return
     *      string : current page's url
     * @desc get this page's URL.
     */
    public function getUrl() {
        $arrRet = $this->getConfField('url');
        return strval($arrRet['res']);
    }

    /**
     * @author xuruiqi
     * @param
     *      array $conf : configurations
     *      bool  $clear_default : whether to clear default options not set in $conf
     * @return
     * @desc initialize this instance with specified or default configuration
     */
    public function init($curl_handle = NULL, $conf = array()) {
        $this->_curlHandle = $curl_handle;
        if (empty($this->_curlHandle)) {
            $this->_curlHandle = curl_init();//初始化curl handle
            $this->_bolCloseCurlHandle = TRUE;
        }
        $this->_arrConf = $this->_arrDefaultConf;//_arrConf初始化

        $this->setConf($conf, TRUE);//用$conf参数对page对象进行配置

        return $this;
    }

    /**
     * @author xuruiqi
     * @param
     *      array $ids : elements' ids
     * @return
     *      array : array of DOMElement, with keys equal each of ids
     *      NULL  : if $this->_dom equals NULL
     * @desc select spcified elements with their ids.
     */
    /*
    public function mselId($ids) {
        if ($this->_dom === NULL) {
            Phpfetcher_Log::warning('$this->_dom is NULL!');
            return NULL;
        }

        $arrOutput = array();
        foreach ($ids as $id) {
            $arrOutput[$id] = $this->selId($id);
        }
        return $arrOutput;
    }
     */

    /**
     * @author xuruiqi
     * @param
     *      array $tags : elements' tags
     * @return
     *      array : array of DOMNodeList, with keys equal each of tags 
     *      NULL  : if $this->_dom equals NULL
     * @desc select spcified elements with their tags
     */
    /*
    public function mselTagName($tags) {
        if ($this->_dom === NULL) {
            Phpfetcher_Log::warning('$this->_dom is NULL!');
            return NULL;
        }

        $arrOutput = array();
        foreach ($tags as $tag) {
            $arrOutput[$tag] = $this->selId($tag);
        }
        return $arrOutput;
    }
     */
    

    /**
     * @author xuruiqi
     * @param
     *      array $conf : configurations
     *      bool  $clear_previous_conf : if TRUE, then before set $conf, reset current configuration to its default value
     * @return
     *      array : previous conf
     * @desc set configurations.
     */
    public function setConf($conf = array(), $clear_previous_conf = FALSE) {
        $previous_conf = $this->_arrConf;
        if ($clear_previous_conf === TRUE) {
            $this->_arrConf = $this->_arrDefaultConf;
        }
        /*foreach ($conf as $k => $v) {
            $this->_arrConf[$k] = $v;
        }*/
        $this->_arrConf += $conf;

        $bolRes = TRUE;

        if ($clear_previous_conf === TRUE) {
            $bolRes = $this->_setConf($this->_arrConf);
        } else {
            $bolRes = $this->_setConf($conf);
        }

        if ($bolRes != TRUE) {
            $this->_arrConf = $previous_conf;
            $this->_setConf($this->_arrConf);
            return $bolRes;
        }

        return $previous_conf;
    }

    protected function _setConf($conf = array()) {
        $arrCurlOpts = array();
        foreach ($conf as $k => $v) {
            if (isset(self::$_arrField2CurlOpt[$k])) {
                $arrCurlOpts[self::$_arrField2CurlOpt[$k]] = $v;
            } else {
                //currently only curl options can be set
                $arrCurlOpts[$k] = $v;
            }
        }
        return curl_setopt_array($this->_curlHandle, $arrCurlOpts);
    }

    public function setExtraInfo($arrInput) {
        foreach ($arrInput as $key => $val) {
            $this->_arrExtraInfo[$key] = $val;
        }
    }

    /**
     * @author xuruiqi
     * @param
     *      string $id : specifed element id
     * @return
     *      object : DOMElement or NULL is not found
     *      NULL   : if $this->_dom equals NULL
     * @desc select a spcified element via its id.
     */
    public function selId($id) {
        if ($this->_dom === NULL) {
            Phpfetcher_Log::warning('$this->_dom is NULL!');
            return NULL;
        }

        return $this->_dom->getElementById($id);
    }

    /**
     * @author xuruiqi
     * @param
     *      string $tag : specifed elements' tag name 
     * @return
     *      object : a traversable DOMNodeList object containing all the matched elements
     *      NULL   : if $this->_dom equals NULL
     * @desc select spcified elements via its tag name.
     */
    public function selTagName($tag) {
        if ($this->_dom === NULL) {
            Phpfetcher_Log::warning('$this->_dom is NULL!');
            return NULL;
        }

        return $this->_dom->getElementsByTagName($tag);
    }

    public function setConfField($field, $value) {
        $this->_arrConf[$field] = $value;
        return $this->_setConfField($field, $value);
    }

    protected function _setConfField($field, $value) {
        if (isset(self::$_arrField2CurlOpt[$field])) {
            return curl_setopt($this->_curlHandle, self::$_arrField2CurlOpt[$field], $value);
        } else {
            //currently only curl options can be set
            return curl_setopt($this->_curlHandle, $field, $value);
        }
    }

    /**
     * @author xuruiqi
     * @param
     *      string $url : the URL
     * @return
     *      string : previous URL
     * @desc set this page's URL.
     */
    public function setUrl($url) {
        $previous_url = $this->_arrConf['url'];
        $this->setConfField('url', $url);
        return $previous_url;
    }

    /**
     * @author xuruiqi
     * @param
     * @return
     *      string : return page's content
     *      bool   : if failed return FALSE
     * @desc get page's content, and save it into member variable <_strContent>
     */
    public function read() {
        $this->_strContent = curl_exec($this->_curlHandle);
        if ($this->_strContent != FALSE) {
            $matches = array();
            preg_match('#charset="?([a-zA-Z0-9-\._]+)"?#', $this->_strContent, $matches);
            if (!empty($matches[1])) {
                //Phpfetcher_Log::notice("Convert content from {$matches[1]} to UTF-8\n");
                $this->_strContent = mb_convert_encoding($this->_strContent, 'UTF-8', $matches[1]);
            }

            /*
            $this->_dom = new DOMDocument(); //DOMDocument's compatibility is bad
            if (@$this->_dom->loadHTML($this->_strContent) == FALSE) {
                Phpfetcher_Log::warning('Failed to call $this->_dom->loadHTML');
                $this->_dom      = NULL;
                $this->_domxpath = NULL;
            } else {
                $this->_domxpath = new DOMXPath($this->_dom);
            }
             */

            $this->_dom = new Phpfetcher_Dom_SimpleHtmlDom();
            if (@$this->_dom->loadHTML($this->_strContent) == FALSE) {
                Phpfetcher_Log::warning('Failed to call $this->_dom->loadHTML');
                $this->_dom      = NULL;
            } 
        }
        else{
            //fix 404 bug
            $this->_strContent = "";
        }
        return $this->_strContent;
    }

    /**
     * @author xuruiqi
     * @param
     *      string $strPath : xpath's path
     *      [DOMNode $contextnode : The optional contextnode can be specified for doing relative XPath queries. By default, the queries are relative to the root element.]
     *
     * @return
     *      DOMNodelist : DOMNodelist object
     *      NULL  : if $this->_dom equals NULL
     *      false : if error occurs
     * @desc select corresponding content use xpath
     */
    public function sel($strPath, $intIndex = NULL, $contextnode = NULL) {
        if ($this->_dom === NULL) {
            Phpfetcher_Log::warning('$this->_dom is NULL!');
            return NULL;
        }

        if ($contextnode !== NULL) {
            //$res = $this->_domxpath->query($strPath, $contextnode);
            Phpfetcher_Log::warning('param contextnode is no use because of this function\'s inability');
            $res = $this->_dom->sel($strPath, $intIndex);
        } else {
            //$res = $this->_domxpath->query($strPath);
            $res = $this->_dom->sel($strPath, $intIndex);
        }

        return $res;
    }
}
?>
