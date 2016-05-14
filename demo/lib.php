<?php
function mysql_real_escape_arr( &$arr ){
	if( is_array( $arr ) ){
		foreach( $arr as $key => &$val ){
			if( !is_array( $val ) ){
				$val = mysql_real_escape_string( $val );
			}
			else{
				mysql_real_escape_arr( $val );
			}
		}
	}
	else if( is_string( $arr ) ){
		$arr = mysql_real_escape_string( $arr );
	}
	else{
		return false;
	}
}
