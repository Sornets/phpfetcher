<?php
//下面两行使得这个项目被下载下来后本文件能直接运行
$demo_include_path = dirname(__FILE__) . '/../';
set_include_path(get_include_path() . PATH_SEPARATOR . $demo_include_path);

require_once('phpfetcher.php');
require_once('lib.php');
define( CMT_TABLE, "comments_");
define( USER_TABLE, "users_");
define( TABLE_LIMIT, 1000000 - 1 );

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
$redis = new Redis();
$redis->connect( '127.0.0.1', 6379 );
$error_count = 0;
//不断从 news:id:needCrawlComment ZSET中取新闻id

while( $redis->scard( 'news:id:needCrawlComment' ) ){
	$cmt_id = $redis->spop( 'news:id:needCrawlComment' );
	if( empty( $cmt_id ) ){
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
				//判断当前评论是否保存过
				$user = $comment['userinfo'];
				$weibo = $comment['userinfo']['wbuserinfo'];
				
				if( !isCmtExist( $comment['id'] ) ){
					$res = insertCmtInfo( $comment );
					if( $res ){//插入数据成功
						$redis->ZADD( 'comment:id:crawled', time(), $comment['id'] );
					}
				}

				if( !isUserExist( $user['userid'] ) ){
					$res = insertUserInfo( $user );
					if( $res ){//插入数据成功
						$redis->ZADD( 'user:id:crawled', time(), $user['userid'] );
					}
				}
			}
		}
		else{
			$error_count++;
			if( $error_count > 3 ){
				insertError( 'errCode != 0', "cmt_id = $cmt_id" );
				break;//break do while()//获取下篇新闻的评论
			}
			continue;
		}
	}while( $arr_json['data']['hasnext'] );
	//一条新闻爬取完成
	$redis->ZADD( 'news:id:crawledComment', time(), $cmt_id );

}//while( $redis->scard( 'news:id:needCrawlComment' ) ){

function isCmtExist( $comment_id ){
	$res = $GLOBALS['redis']->ZSCORE( 'comment:id:crawled', $comment_id );
	return boolval( $res );
}

function isUserExist( $user_id ){
	$res = $GLOBALS['redis']->ZSCORE( 'user:id:crawled', $user_id );
	return boolval( $res );
}

/*
 * 将$comment插入到表中
 * 1：将comment:totalNum自增，并获取结果
 * 2：将1中获取的值作为评论的主键id，
 * 3：根据主键id选择要插入的表名
 * 4：插入到数据库中
 */
function insertCmtInfo( $comment ){
	$comment_id = $GLOBALS['redis']->INCR( 'comment:totalNum' );
	$table_name = getTableName( CMT_TABLE, $comment_id );
	@$str_comment_sql = "INSERT INTO `$table_name`(
		`id`, `real_id`, `rootid`, `targetid`, `parent`, 
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
		'$comment_id', '$comment[id]', '$comment[rootid]', '$comment[targetid]', '$comment[parent]', 
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
	$res = boolval( $res );
	if( !$res ){
		insertError( $str_comment_sql, 'comment_insert_fail' );
	}
	return $res; 
}

/*
 * 将$user插入到表中
 * 1：将user:totalNum自增，并获取结果
 * 2：将1中获取的值作为用户信息的主键id，
 * 3：根据主键id选择要插入的表名
 * 4：插入到数据库中
 */
function insertUserInfo( $user ){
	$weibo = $user['wbuserinfo'];
	$user_id = $GLOBALS['redis']->INCR( 'user:totalNum' );
	$table_name = getTableName( USER_TABLE, $user_id );
	@$str_user_sql = "INSERT INTO `$table_name`(
		`id`, `userid`, `uidex`, `nick`, `head`, 
		`gender`, `viptype`, `mediaid`, `region`, 
		`thirdlogin`, `hwvip`, `hwlevel`, 
		`hwannual`, `identity`, `wb_name`, 
		`wb_nick`, `wb_url`, `wb_vip`, `wb_ep`, 
		`wb_brife`, `wb_identification`, 
		`wb_intro`, `wb_live_country`, `wb_live_province`, 
		`wb_live_city`, `wb_live_area`, 
		`wb_gender`, `wb_level`, `wb_classify`
	) VALUES (
		'$user_id', '$user[userid]', '$user[uidex]', '$user[nick]', '$user[head]', 
		'$user[gender]', '$user[viptype]', '$user[mediaid]', 
		'$user[region]', '$user[thirdlogin]', '$user[hwvip]', 
		'$user[hwlevel]', '$user[hwannual]', '$user[identity]', 
		'$weibo[name]', '$weibo[nick]', '$weibo[url]', 
		'$weibo[vip]', '$weibo[ep]', '$weibo[brief]', 
		'$weibo[identification]', '$weibo[intro]', '{$weibo['liveaddr']['country']}', 
		'{$weibo['liveaddr']['province']}', '{$weibo['liveaddr']['city']}', '{$weibo['liveaddr']['area']}', 
		'$weibo[gender]', '$weibo[level]', '$weibo[classify]'
	)";
	$res = $GLOBALS['db']->exe_sql( $str_user_sql );
	$res = boolval( $res );
	if( !$res ){
		insertError( $str_user_sql, 'user_insert_fail' );
	}
	return $res; 
}

function getTableName( $table_pre, $id ){
	$table_index = intval( $id / TABLE_LIMIT ) + 1;
	return $table_pre . $table_index;
}

function insertError( $err_content, $err_type ){
	$err_content = mysql_real_escape_string( $err_content );
	$err_sql = "INSERT INTO `fail`(`err_type`, `content`) VALUES ('$err_type', '$err_content')";
}
