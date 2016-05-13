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
    'db_password'   => 'root',
    'db_name'       => 'qqnews',//库名
    'db_pre'        => '',//前缀
);

$db = new Phpfetcher_MySQL_Default( $config );
$curl = curl_init();
class mycrawler extends Phpfetcher_Crawler_Default {
    public function handlePage($page) {
        //获取标题
        $str_title = $page->sel('//title', 0)->plaintext;
		//获取文章内容
        $arr_content = $page->sel('div[@id=Cnt-Main-Article-QQ]/p');
        if( $arr_content ){
			$str_content = '';//*
        	foreach( $arr_content as $content ){
				$str_content .= $content->plaintext;
      	     	$str_content .= "<br />";
			}
			//获取评论info
			//获取文章id
			$str_html = $page->getContent();
			$str_cmt_id_patten = "#cmt_id\s?=\s?\d*;#";
			preg_match( $str_cmt_id_patten, $str_html, $matches );
			$matches[0] = str_replace( "cmt_id = ", "", $matches[0] );
			$matches[0] = str_replace( ";", "", $matches[0] );
			$cmt_id = intval( $matches[0] );
			$error_count = 0;

			if( $cmt_id ){
				$next_cmt_id = 0;
				$comment_url = "http://coral.qq.com/article/$cmt_id/comment";
				//循环获取json评论
				do{
					$temp_url = $comment_url . "?commentid=$next_cmt_id&reqnum=20&callback=mainComment";
echo $temp_url;
					// 设置你需要抓取的URL
					curl_setopt($GLOBALS['curl'], CURLOPT_URL, $comment_url . "?commentid=$next_cmt_id&reqnum=20&callback=mainComment");
					 
					// 设置header
					//curl_setopt($GLOBALS['curl'], CURLOPT_HEADER, 1);
					 
					// 设置cURL 参数，要求结果保存到字符串中还是输出到屏幕上。
					curl_setopt($GLOBALS['curl'], CURLOPT_RETURNTRANSFER, 1);
					 
					// 运行cURL，请求网页
					$str_json = curl_exec($GLOBALS['curl']);
//print_r($str_json);
					$str_json = substr( $str_json, 12, -1 );
					$arr_json = json_decode($str_json, TRUE);

					if( $arr_json && $arr_json['errCode'] == 0 ){					
						//var_dump($arr_json);
						$next_cmt_id = $arr_json['data']['last'];//获取成功即修改下一次的参数
						$get_time = $arr_json['info']['time'];
						$error_count = 0;
						foreach( $arr_json['data']['commentid'] as $comment ){
							echo "cmt_id:$comment[id], user_id:" . $comment['userinfo']['userid'] . PHP_EOL;
							$str_has_sql = "SELECT `id` FROM `comments` WHERE id=$comment[id]";
							$has_this_cmt_handle = $GLOBALS['db']->exe_sql( $str_has_sql );
							$has_this_cmt = mysql_fetch_assoc( $has_this_cmt_handle );
							//print_r($has_this_cmt);
							if( $has_this_cmt ){
								//echo "id: $comment[id],next line is continue" . PHP_EOL;
								continue;
							}
							$user = $comment['userinfo'];
							$weibo = $comment['userinfo']['wbuserinfo'];
							$str_comment_sql = "INSERT INTO `comments`(
													`id`, `rootid`, `targetid`, `parent`, 
													`timeDifference`, `time`, `content`, 
													`title`, `up`, `rep`, `type`, 
													`hotscale`, `checktype`, `checkstatus`, 
													`isdeleted`, `tagself`, `taghost`, 
													`source`, `lat`, `lng`, `locationaddress`, `locationname`, 
													`rank`, `custom`, `extend_at`, 
													`extend_ut`, `orireplynum`, 
													`richtype`, `userid`, `poke`, 
													`abstract`, `thirdid`, `replyuser`, 
													`replyuserid`, `replyhwvip`, `replyhwlevel`, 
													`replyhwannual`, 
													`create_time`, `update_time`, `analysis_time`
												) VALUES (
													'$comment[id]', '$comment[rootid]', '$comment[targetid]', '$comment[parent]', 
													'$comment[timeDifference]', '$comment[time]', '$comment[content]', 
													'$comment[title]', '$comment[up]', '$comment[rep]', '$comment[type]', 
													'$comment[hotscale]', '$comment[checktype]', '$comment[checkstatus]', 
													'$comment[isdeleted]', '$comment[tagself]', '$comment[taghost]', 
													'$comment[source]', '". @$comment['location']['lat']."','" . @$comment['location']['lng'] . "', '" . @$comment['address']['locationaddress'] . " ','" . @$comment['address']['locationname'] . "', 
													'$comment[rank]', '$comment[custom]', '{$comment['extend']['at']}', 
													'{$comment['extend']['ut']}', '$comment[orireplynum]', 
													'$comment[richtype]', '$comment[userid]', '$comment[poke]', 
													'$comment[abstract]', '$comment[thirdid]', '$comment[replyuser]', 
													'$comment[replyuserid]', '$comment[replyhwvip]', '$comment[replyhwlevel]',
													'$comment[replyhwannual]', 
													'$get_time', '$get_time', 0 
												)";
							$res = $GLOBALS['db']->exe_sql( $str_comment_sql );
							if( !$res ){
								echo $str_comment_sql;
							}
							
							$str_has_sql = "SELECT `userid` FROM `users` WHERE userid=$user[userid]";
							$has_this_user_handle = $GLOBALS['db']->exe_sql( $str_has_sql );
							$has_this_user = mysql_fetch_assoc( $has_this_user_handle );
							//print_r( $has_this_user );
							if( $has_this_user ){
								//echo "id $user[userid] continued." . PHP_EOL;
								continue;
							}
							$str_user_sql = "INSERT INTO `users`(
												`userid`, `uidex`, `nick`, `head`, 
												`gender`, `viptype`, `mediaid`, `region`, 
												`thirdlogin`, `hwvip`, `hwlevel`, 
												`hwannual`, `identity`, `wb_name`, 
												`wb_nick`, `wb_url`, `wb_vip`, `wb_ep`, 
												`wb_brife`, `wb_identification`, 
												`wb_intro`, `wb_live_country`, `wb_live_province`, 
												`wb_live_city`, `wb_live_area`, 
												`wb_gender`, `wb_level`, `wb_classify`
											) 
											VALUES 
											(
												'$user[userid]', '$user[uidex]', '$user[nick]', '$user[head]', 
												'$user[gender]', '$user[viptype]', '$user[mediaid]', 
												'$user[region]', '$user[thirdlogin]', '$user[hwvip]', 
												'$user[hwlevel]', '$user[hwannual]', '$user[identity]', 
												'$weibo[name]', '$weibo[nick]', '$weibo[url]', 
												'$weibo[vip]', '$weibo[ep]', '$weibo[brief]', 
												'$weibo[identification]', '$weibo[intro]', '{$weibo['liveaddr']['country']}', 
												'{$weibo['liveaddr']['province']}', '{$weibo['liveaddr']['city']}', '{$weibo['liveaddr']['area']}', 
												'$weibo[gender]', '$weibo[level]', '$weibo[classify]'
											)";
							$res = $GLOBALS['db']->exe_sql( $str_user_sql);
							if( !$res ){
								echo $str_user_sql;
							}
						}
					}
					else{
						$error_count++;
						if( $error_count > 10 ){
							break;
						}
						continue;
					}
				}while( $arr_json['data']['hasnext'] );

				$int_comment = $arr_json['data']['total'];
			}
			//获取新闻类型
			@$str_type = $page->sel('span[@bosszone=ztTopic]/a', 0)->plaintext;
			
			//获取来源
			$obj_refer = $page->sel('span[@bosszone=jgname]/a', 0);
			if( $obj_refer ){
			$str_refer = $obj_refer->plaintext;
			//获取来源域名
			$arr_url = parse_url( $obj_refer->href );
			$str_refer_url = $arr_url['scheme'] . "://" . $arr_url['host'];
			}
			else{
				$str_refer = "腾讯新闻";
				$str_refer_url = "http://news.qq.com";
			}
			//获取发布时间
			@$time = $page->sel('//span[@class=article-time]', 0)->plaintext;
			
			$time = strtotime( $time );
						//保存信息
			$db_name = $GLOBALS['db']->_db_name;
			$db_pre = $GLOBALS['db']->_pre;
			//mysql 转码
            $str_content = mysql_real_escape_string( $str_content );
            $str_title = mysql_real_escape_string( $str_title );
            $str_refer = mysql_real_escape_string( $str_refer );
            $str_refer_url = mysql_real_escape_string( $str_refer_url );
            $str_type = mysql_real_escape_string( $str_type );

			$sql = "INSERT INTO `news` (`title`, `comment_num`, `content`, `refer`, `refer_url`, `news_type`, `time` ) VALUES ( '$str_title', $int_comment, '$str_content', '$str_refer', '$str_refer_url', '$str_type', $time )";
			//echo 'sql:'. PHP_EOL . $sql .PHP_EOL . 'sql end';
			if( !$GLOBALS['db']->exe_sql( $sql ) ){
				$check_sql = "SELECT id FROM `news` WHERE title='$str_title'";
				if( !$GLOBALS['db']->exe_sql( $check_sql ) ){
					Phpfetcher_Log::warning("insert into mysql failed!");
					//mysql_select_db( 'fail', $GLOBALS['db']->_con );	
					$error_sql = "INSERT INTO `fail`( `content` ) VALUES ( '". mysql_real_escape_string($sql)  . "')";
					$GLOBALS['db']->exe_sql( $error_sql );
					//mysql_select_db( $GLOBALS['db']->_db_name, $GLOBALS['db']->_con );
					echo $str_title;
				}
			}
		}
    }
}

$crawler = new mycrawler();
$arrJobs = array(
    //任务的名字随便起，这里把名字叫qqnews
    //the key is the name of a job, here names it qqnews
    'qqnews' => array( 
        //'start_page' => 'http://news.qq.com/', //起始网页
        'start_page' => 'http://news.qq.com/a/20160421/053219.htm', 
        'link_rules' => array(
            /*
             * 所有在这里列出的正则规则，只要能匹配到超链接，那么那条爬虫就会爬到那条超链接
             * Regex rules are listed here, the crawler will follow any hyperlinks once the regex matches
             */
            '#news\.qq\.com/a/\d+/\d+\.htm$#',
        ),
        //爬虫从开始页面算起，最多爬取的深度，设置为1表示只爬取起始页面
        //Crawler's max following depth, 1 stands for only crawl the start page
        'max_depth' => 2,
	'max_pages' => 3, 
        
    ) ,   
);

//$crawler->setFetchJobs($arrJobs)->run(); //这一行的效果和下面两行的效果一样
$crawler->setFetchJobs($arrJobs);
$crawler->run();
