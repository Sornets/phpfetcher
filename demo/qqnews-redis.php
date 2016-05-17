<?php
//下面两行使得这个项目被下载下来后本文件能直接运行
$demo_include_path = dirname(__FILE__) . '/../';
set_include_path(get_include_path() . PATH_SEPARATOR . $demo_include_path);

require_once('phpfetcher.php');
require_once('lib.php');
//mysql info
$config = array(
    'db_host'       => 'localhost',
    'db_port'       => '3306',
    'db_username'   => 'root',
    'db_password'   => 'root',
    'db_name'       => 'qqnews',//库名
    'db_pre'        => '',//前缀
);

$db = new Phpfetcher_MySQL_Default( $config );
$curl = curl_init();
class mycrawler extends Phpfetcher_Crawler_QQNewsRedis{
    public function handlePage($page) {
    	echo "crawling : " . $page->getUrl() . PHP_EOL;
		
		$need_log = false;
    	$error_type = "";
		$int_comment = 0;
    	
    	$news_url = $page->getUrl();
        //echo $news_url . PHP_EOL;
		//获取标题
        $str_title = $page->sel('//title', 0)->plaintext;
		//获取文章内容
        echo "title : " . $str_title . PHP_EOL;
		$arr_content = $page->sel('div[@id=Cnt-Main-Article-QQ]/p');
        //if( $arr_content ){
		$str_content = '';//*
    	foreach( $arr_content as $content ){
			$str_content .= $content->plaintext;
  	     	$str_content .= "<br />";
		}

		//获取文章id
		$str_html = $page->getContent();
		$str_cmt_id_patten = "#cmt_id\s?=\s?\d*;#";
		$match_count = preg_match( $str_cmt_id_patten, $str_html, $matches );
		if( empty( $match_count ) ){//cmt_id 获取失败
			//aid: "1400425094",
			$str_aid_patten = "#aid:\s?\"\s?\d*\",#";
			$match_count = preg_match( $str_aid_patten, $str_html, $matches );

			if( empty( $match_count ) ){//aid 获取失败
				$need_log = true;
				$error_type .= "Match cmt_id & aid failed.";
				$cmt_id = 0;
			}
			else{//aid 获取成功
				$matches[0] = str_replace( "aid: \"", "", $matches[0] );
				$matches[0] = str_replace( "\",", "", $matches[0] );
				$cmt_id = intval( $matches[0] );
			}
		}
		else{//获取成功
			$matches[0] = str_replace( "cmt_id = ", "", $matches[0] );
			$matches[0] = str_replace( ";", "", $matches[0] );
			$cmt_id = intval( $matches[0] );
		}

		if( $cmt_id > 0 ){
			//将cmt_id保存到redis中
			$this->_redis->sadd( 'need:crawled:news:ids', $cmt_id );
		}
		else{
			$need_log = true;
			$error_type .= "Get news id failed.";
			$error_sql = "INSERT INTO `fail`( `err_type`, `content`) VALUES ( '$error_type', " . $news_url . "')";
			$GLOBALS['db']->exe_sql( $error_sql );
		}

		//获取新闻类型
		@$str_type = $page->sel('span[@bosszone=ztTopic]/a', 0)->plaintext;
		
		//获取来源
		$obj_refer = $page->sel('span[@bosszone=jgname]/a', 0);
		if( empty( $obj_refer) ){
			//获取失败
			$obj_refer = $page->sel('span[@class=where]/a', 0);
			if( empty( $obj_refer) ){
				//两次获取都失败了
				$need_log = true;
				$error_type .= "Get refer info failed.";
			}
		}
		@$str_refer = $obj_refer->plaintext;
		//获取来源域名
		$arr_url = parse_url( @$obj_refer->href );
		$str_refer_url = @$arr_url['scheme'] . "://" . @$arr_url['host'];

		//获取发布时间
		$time = $page->sel('//span[@class=article-time]', 0)->plaintext;
		if( empty( $time ) ){
			//for http://news.qq.com/a/20120425/000744.htm
			$time = $page->sel('//span[@class=pubTime]', 0)->plaintext;
			if( !empty( $time ) ){
				$time = str_replace( '年', '-', $time );
				$time = str_replace( '月', '-', $time );
				$time = str_replace( '日', ' ', $time );
			}
			else{
				$need_log = true;
				$error_type .= "Get public time failed.";
			}
		}

		$time = strtotime( $time );
					//保存信息
		$db_name = $GLOBALS['db']->_db_name;
		$db_pre = $GLOBALS['db']->_pre;
		echo "now try to save news." . PHP_EOL;
		$sql = "INSERT INTO `news` ( `real_id`, `news_url`, `title`, `comment_num`, `content`, `refer`, `refer_url`, `news_type`, `time` ) VALUES ( '$cmt_id', '$news_url', '$str_title', '$int_comment', '$str_content', '$str_refer', '$str_refer_url', '$str_type', '$time' )";
		//echo $sql . PHP_EOL;
		if( !$GLOBALS['db']->exe_sql( $sql ) ){
			//检查数据库中是否已经存在当前新闻
			$str_has_sql = "SELECT id FROM `news` WHERE real_id='$cmt_id'";
			$has_this_news_handle = $GLOBALS['db']->exe_sql( $str_has_sql );
			if( isset( $has_this_news_handle ) && $has_this_news_handle !== false ){
				//var_dump( $has_this_news_handle);
				$has_this_news = mysql_fetch_assoc( $has_this_news_handle );
			}
			if( isset( $has_this_news ) ){
				return;
			}
			else{
				$need_log = true;
				$error_type .= "Insert failed.";
			}
		}

		if( $need_log ){
			$error_sql = "INSERT INTO `fail`( `err_type`, `content`) VALUES ( '$error_type', '$sql')";
			$GLOBALS['db']->exe_sql( $error_sql );
		}
    }
}

$crawler = new mycrawler();
$arrJobs = array(
    //任务的名字随便起，这里把名字叫qqnews
    //the key is the name of a job, here names it qqnews
    'qqnews' => array( 
        //'start_page' => 'http://news.qq.com/', //起始网页
        'start_page' => 'http://news.qq.com/', 
        'link_rules' => array(
            /*
             * 所有在这里列出的正则规则，只要能匹配到超链接，那么那条爬虫就会爬到那条超链接
             */
            '#news\.qq\.com/a/\d+/\d+\.htm$#',
        ),
        //爬虫从开始页面算起，最多爬取的深度，设置为1表示只爬取起始页面
	//'max_depth' => 100,
	'max_pages' => 10, 
    ) ,   
);

$redis = new Redis();
$redis->connect( '127.0.0.1', 6379 );
$crawler->setFetchJobs($arrJobs);
$crawler->setRedis( $redis );//设置爬虫的redis信息
$crawler->run();
