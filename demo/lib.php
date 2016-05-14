<?php
function mysql_real_escape_arr( &$arr ){
	foreach( $arr as $key => &$val ){
		if( !is_array( $val ) ){
			$val = mysql_real_escape_string( $val );
		}
		else{
			escape_arr( $val );
		}
	}
}