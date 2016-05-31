<?php
$redis = new Redis();
$redis->connect( '127.0.0.1', 6379 );
$mysql_con = mysql_connect( 'localhost:3306', 'root', 'root' );
mysql_select_db( 'qqnews' );
$arr_pid = array();
$key = "U638Z7M6GFeXUydC5oiA6kISjMqhJiqpbDKAuFtC";


//comments_1
//将数据导入redis
$sql = "SELECT `id` FROM `comments_1`";
$source = mysql_query( $sql, $mysql_con );
while( $date = mysql_fetch_assoc( $source ) ){
	$redis->sadd( 'comments:1:id', $date['id'] );
}

mysql_close( $mysql_con );

//die;
//生成子进程

for( $i = 0; $i < 100; $i++ ){
    $pid = pcntl_fork();
    if( $pid == 0 ){
        break;
    }
    else{
        $arr_pid[] = $pid;
    }
}

$curl = curl_init();
$mysql_con = mysql_connect( 'localhost:3306', 'root', 'root' );
mysql_select_db( 'qqnews' );
while(  $redis->scard( 'comments:1:id' ) ){
	$id = $redis->spop( 'comments:1:id' );
	echo 'participle : ' . $id . PHP_EOL;
	//取出评论
	$sql = "SELECT `content` FROM `comments_1` WHERE id=$id";
	$handle = mysql_query( $sql, $GLOBALS['mysql_con'] );
	//var_dump( $GLOBALS['mysql_con'] );
	$res = mysql_fetch_assoc( $handle );
	//获取数据
	$url = "http://api.ltp-cloud.com/analysis/?api_key=$key&text=";
	$url .= urlencode( $res['content'] );
	$url .= "&pattern=all&format=json";
	//echo $url . PHP_EOL;
	curl_setopt( $GLOBALS['curl'], CURLOPT_URL, $url );
	curl_setopt( $GLOBALS['curl'], CURLOPT_RETURNTRANSFER, 1);
	//echo "exec";
	try{
		$json_res = curl_exec( $GLOBALS['curl'] );
		if( $json_res == false ){
			throw new Exception( curl_error( $GLOBALS['curl'] ), curl_errno( $GLOBALS['curl']) );
		}
	}
	catch( Exception $e ){
		echo $e->getCode() . $e->getMessage() . E_USER_ERROR . PHP_EOL;
	}
	//var_dump( $json_res );
	$arr_res = json_decode( $json_res, true );
	//continue;
	if( empty( $arr_res ) ){
		continue;
	}
	$str_res = serialize( $arr_res );
	$str_res = mysql_real_escape_string( $str_res );
	//echo $str_res . PHP_EOL;
	//update 数据库
	$update_sql = "UPDATE `comments_1` SET `participle` = '$str_res' WHERE id=$id";
	//echo $update_sql . PHP_EOL;
	$updaet_handler = mysql_query( $update_sql, $GLOBALS['mysql_con'] );
}
mysql_close( $mysql_con );

if( $pid != 0 ){
    foreach( $arr_pid as $pid ){
            echo "回收：$pid" . PHP_EOL;
            pcntl_waitpid( $pid, $info[ $pid ] );
    }
}

