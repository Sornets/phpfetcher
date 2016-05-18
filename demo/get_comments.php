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
    'db_password'   => 'sxcxs0819',
    'db_name'       => 'qqnews',//库名
    'db_pre'        => '',//前缀
);

$db = new Phpfetcher_MySQL_Default( $config );
$curl = curl_init();
$redis = new Redis();
$redis->connect( '127.0.0.1', 6379 );
$min_date = 20120101;

//不断从 need:crawled:news:ids 列表中取新闻id
while( $cmt_id = $redis->spop( 'need:crawled:news:ids' ) ){
	$news_url = $redis->hget( 'news:id:links', $cmt_id );
	$news_data = getDateFromUrl( $news_url );

	if( !could_crawl( $news_url) ){
		//进行下篇文章
		continue;
	}
	$next_cmt_id = 0;
	$comment_url = "http://coral.qq.com/article/$cmt_id/comment";
	//循环获取json评论
	do{
		// 设置你需要抓取的URL
		curl_setopt($GLOBALS['curl'], CURLOPT_URL, $comment_url . "?commentid=$next_cmt_id&reqnum=20&callback=mainComment");
		
		// 设置cURL 参数，要求结果保存到字符串中还是输出到屏幕上。
		curl_setopt($GLOBALS['curl'], CURLOPT_RETURNTRANSFER, 1);
		 
		// 运行cURL，请求网页
		$str_json = curl_exec($GLOBALS['curl']);
		$str_json = substr( $str_json, 12, -1 );
		$arr_json = json_decode($str_json, TRUE);
		mysql_real_escape_arr( $arr_json );
		if( $arr_json && $arr_json['errCode'] == 0 ){
			$error_count = 0;

			$next_cmt_id = $arr_json['data']['last'];//获取成功即修改下一次的参数
			$get_time = $arr_json['info']['time'];
			foreach( $arr_json['data']['commentid'] as $comment ){
				//判断当前评论时候保存过
				$user = $comment['userinfo'];
				$weibo = $comment['userinfo']['wbuserinfo'];
				
				//暂时先不考虑两次爬取间隔太短的情况
				if( !isCmtExist( $comment['id'] ) ){
					$res = insertCmtInfo( $comment );
					if( $res ){//插入数据成功
						$redis->hset( 'crawled:comments', $comment['id'], time() );
					}
				}
				//已存在评论，跳过对当前评论的操作

				if( !isUserExist( $user['id'] ) ){
					$res = insertUserInfo( $user );
					if( $res ){//插入数据成功
						$redis->hset( 'crawled:users', $user['userid'], time() );
					}
				}
			}
		}
		else{
			$error_count++;
			if( $error_count > 2 ){
				$news_data = getDateFromUrl( $news_url );
				$redis->sadd( 'can:notuse:news:dates', $news_data );
				if( $news_data > $min_date ){
					$min_date = $news_data + 1;
				}
				break;//break do while()//获取下篇新闻的评论
			}
			continue;
		}
	}while( $arr_json['data']['hasnext'] );

	//将爬取过评论的文章id保存在 crawled:news:ids   哈希表中，field 为 id，value 为 timestamp
	$redis->hset( 'crawled:news:ids', $cmt_id ,time() );
	//将爬取过的新闻date加入的redis can:use:news:dates 中
	if( $news_data < $min_date ){
		$redis->sadd( 'can:use:news:dates', $news_data );
		$min_date = $news_date;
	}

}

function isCmtExist( $comment_id ){
	return $redis->hexists( 'crawled:comments', $comment_id );
}

function isUserExist( $user_id ){
	return $redis->hexists( 'crawled:users', $user_id );
}
function getDateFromUrl( $url ){
	$pattern = "#/a/\d+/#";
	$matchs = array();
	preg_match( $pattern, $url, $matchs );
	if( isset( $matchs[0] ) ){
		return $news_data = intval( substr( $matchs, 3, -1 ) );
	}
	return false;
}

function could_crawl( $news_url ){
	if( $date = getDateFromUrl( $news_url ) ){
		return $date >= $GLOBALS['min_date'];
	}
	return false;
}

function isCmtNeedInsert( $update_time){
	if( empty( $update_time ) ){
		return true;
	}
	else{
		return false;
	}
}

function isCmtNeedUpdate( $comment ){
	$cmt_time = $comment['time'];
	$cur_time = time();
	if( $cur_time - $cmt_time < 3600 * 24 * 7 ){
		//一周前的评论不更新
		return false;
	}
}

function updateCmtInfo(){}

function insertCmtInfo( $comment ){
	@$str_comment_sql = "INSERT INTO `comments`(
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
	return $GLOBALS['db']->exe_sql( $str_comment_sql );
}

function updateUserInfo(){}

function insertUserInfo( $user ){
	$weibo = $user['wbuserinfo'];
	@$str_user_sql = "INSERT INTO `users`(
		`userid`, `uidex`, `nick`, `head`, 
		`gender`, `viptype`, `mediaid`, `region`, 
		`thirdlogin`, `hwvip`, `hwlevel`, 
		`hwannual`, `identity`, `wb_name`, 
		`wb_nick`, `wb_url`, `wb_vip`, `wb_ep`, 
		`wb_brife`, `wb_identification`, 
		`wb_intro`, `wb_live_country`, `wb_live_province`, 
		`wb_live_city`, `wb_live_area`, 
		`wb_gender`, `wb_level`, `wb_classify`
	) VALUES (
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

}

//$redis->hset( 'crawled:comments', $comment['id'], time() );
//insert cmt
/*$res = insertCmtInfo( $comment );
if( !$res ){
	$error_sql = "INSERT INTO `fail`(`content`) VALUES ('" . mysql_real_escape_string($sql) . "')";
	$GLOBALS['db']->exe_sql( $error_sql );
	echo $str_comment_sql;
}*/

//$redis->hset( 'crawled:users', $user['userid'], time() );

/*$str_has_sql = "SELECT `userid` FROM `users` WHERE userid=$user[userid]";
$has_this_user_handle = $GLOBALS['db']->exe_sql( $str_has_sql );
$has_this_user = mysql_fetch_assoc( $has_this_user_handle );
if( $has_this_user ){
	continue;
}*/

//$str_user_sql = mysql_real_escape_string( $str_user_sql );
/*$res = $GLOBALS['db']->exe_sql( $str_user_sql);
if( !$res ){
	echo $str_user_sql;
}*/