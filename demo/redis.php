<?php
$redis = new Redis();
$redis->connect( '127.0.0.1', 6379 );
$mysql_con = mysql_connect( 'localhost:3306', 'root', 'root' );
mysql_select_db( 'qqnews' );
/*$sql = "SELECT `news_url` FROM `news`";
$urls_source = mysql_query( $sql, $mysql_con );
while( $url = mysql_fetch_assoc( $urls_source ) ){
	$redis->sadd( 'crawled:links', $url['news_url'] );
}
*/
$sql = "SELECT `real_id` FROM `news`";
$id_source = mysql_query( $sql, $mysql_con );
$count = 0;
while( $id = mysql_fetch_assoc( $id_source ) ){
	if( empty( $id['real_id'] ) ){
		continue;
	}
	$redis->sadd( 'news:id:needCrawlComment', $id['real_id'] );
}

/*
$sql = "SELECT `id`, `update_time` FROM	`comments`";
$source = mysql_query( $sql, $mysql_con );
while( $date = mysql_fetch_assoc( $source ) ){
	$redis->hset( 'crawled:comments', $date['id'], $date['update_time'] );
}

$source = mysql_query( $sql, $mysql_con );
$sql = "SELECT `userid` FROM `users`";
while( $date = mysql_fetch_assoc( $source ) ){
	$redis->hset( 'crawled:users', $date['user_id'], 0 );
}
*/
//$source = mysql_query( $sql, $mysql_con );
/*$sql = "SELECT `real_id`, `news_url` FROM `news`";
$source = mysql_query( $sql, $mysql_con );
while( $date = mysql_fetch_assoc( $source ) ){
	$redis->hset( 'news:id:links', $date['real_id'], $date['news_url'] );
}*/
/*
$sql = "SELECT `real_id`, `news_url` FROM `news`";
$source = mysql_query( $sql, $mysql_con );
while( $date = mysql_fetch_assoc( $source ) ){
	$url = $date['news_url'];       
	$time = getDateFromUrl( $url );
	$time = intval( $time );
	if( $time < 20150101 ){
		$del_sql = "DELETE FROM `news` WHERE real_id=$date[real_id]";
		mysql_query( $del_sql, $mysql_con ); 
	}
}

//var_dump( intval( getDateFromUrl( "http://news.qq.com/a/20141204/014251.htm" ) ));
function getDateFromUrl( $url ){
	$pattern = "#/a/\d+/#";
	$matchs = array();
	preg_match( $pattern, $url, $matchs );
	if( isset( $matchs[0] ) ){
		return $news_data = intval( substr( $matchs[0], 3, -1 ) );
	}
	return false;
}
*/
