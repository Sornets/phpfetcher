<?php
//下面两行使得这个项目被下载下来后本文件能直接运行
$demo_include_path = dirname(__FILE__) . '/../';
set_include_path(get_include_path() . PATH_SEPARATOR . $demo_include_path);

require_once('phpfetcher.php');

//mysql info
$config = array(
    'db_host'       => 'localhost',
    'db_port'       => '3306',
    'db_username'   => 'root',
    'db_password'   => 'sxcxs0819',
    'db_name'       => 'qqnews',//库名
    'db_pre'        => '',//前缀
);

$db = new Phpfetcher_MySQL_Default( $config );
$curl = curl_init();
class mycrawler extends Phpfetcher_Crawler_Default {
    public function handlePage($page) {
        //获取标题
        $str_title = $page->sel('//title', 0)->plaintext;
        if(	PATH_SEPARATOR == ':' ){
			echo $str_title;
		}
		else{
			echo iconv("UTF-8", "GBK", $str_title);
		}
		//获取文章内容
        $arr_content = $page->sel('div[@id=Cnt-Main-Article-QQ]/p');
        if( $arr_content ){
			$str_content = '';//*
        	foreach( $arr_content as $content ){
				$str_content .= $content->plaintext;
      	     	$str_content .= "<br />";
			}
			//echo "文章段落数：" . count( $arr_content );
			//获取评论数
			$obj_comment = $page->sel('//a[@id=cmtNum]', 0);
			$news_id = intval( str_replace( "http://coral.qq.com/", "", $obj_comment->href ) );
			$comment_url = "http://coral.qq.com/article/$news_id/commentnum";
			// 设置你需要抓取的URL
			curl_setopt($GLOBALS['curl'], CURLOPT_URL, $comment_url);
			 
			// 设置header
			curl_setopt($GLOBALS['curl'], CURLOPT_HEADER, 1);
			 
			// 设置cURL 参数，要求结果保存到字符串中还是输出到屏幕上。
			curl_setopt($GLOBALS['curl'], CURLOPT_RETURNTRANSFER, 1);
			 
			// 运行cURL，请求网页
			$str_json = curl_exec($GLOBALS['curl']);
			$arr_json = json_decode($str_json, TRUE);
			$int_comment = intval( $arr_json['data']['commentnum'] );
			$int_orgcomment = intval( $arr_json['data']['orgcommentnum'] );
			
			//获取新闻类型
			$str_type = $page->sel('span[@bosszone=ztTopic]/a', 0)->plaintext;
			//获取来源
			$obj_refer = $page->sel('span[@bosszone=jgname]/a', 0);
			$str_refer = $obj_refer->plaintext;
			//获取来源域名
			$arr_url = parse_url( $obj_refer->href );
			$str_refer_url = $arr_url['scheme'] . "://" . $arr_url['host'];

			//保存信息
			$db_name = $GLOBALS['db']->_db_name;
			$db_pre = $GLOBALS['db']->_pre;
			$sql = "INSERT INTO `$db_name`.`news` VALUES ( '$str_title', $int_comment, 
			'$str_content', '$str_refer', '$str_refer_url', '$str_type' )";
			/*if( @$GLOBALS['db']->exe_sql() ){
				Phpfetcher_Log::warning("insert into mysql failed!");
			}*/
		}
    }
}

$crawler = new mycrawler();
$arrJobs = array(
    //任务的名字随便起，这里把名字叫qqnews
    //the key is the name of a job, here names it qqnews
    'qqnews' => array( 
        'start_page' => 'http://news.qq.com/', //起始网页
        'link_rules' => array(
            /*
             * 所有在这里列出的正则规则，只要能匹配到超链接，那么那条爬虫就会爬到那条超链接
             * Regex rules are listed here, the crawler will follow any hyperlinks once the regex matches
             */
            '#news\.qq\.com/a/\d+/\d+\.htm$#',
        ),
        //爬虫从开始页面算起，最多爬取的深度，设置为1表示只爬取起始页面
        //Crawler's max following depth, 1 stands for only crawl the start page
        'max_depth' => 10, 
        
    ) ,   
);

//$crawler->setFetchJobs($arrJobs)->run(); //这一行的效果和下面两行的效果一样
$crawler->setFetchJobs($arrJobs);
$crawler->run();
