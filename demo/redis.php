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
/*$sql = "SELECT `real_id` FROM `news`";
$id_source = mysql_query( $sql, $mysql_con );
while( $id = mysql_fetch_assoc( $id_source ) ){
	$redis->rpush( 'need:crawled:news:ids', $id['real_id'] );
}*/
$source = mysql_query( $sql, $mysql_con );
$sql = "SELECT `real_id`, `news_url` FROM `news`";
while( $date = mysql_fetch_assoc( $source ) ){
	$redis->hset( 'news:id:links', $date['real_id'], $date['news_url'] );
}

