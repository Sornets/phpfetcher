<?
/*$config_tpl = array(
	'db_host' 		=> '',
	'db_port'		=> '3306',
	'db_username' 	=> '',
	'db_password' 	=> '',
	'db_name' 		=> '',//库名
	'db_pre' 		=> '',//前缀
);*/
class Phpfetcher_MySQL_Default{
	private $_con;//连接
	public $_pre = '';
	public $_db_name = '';
	public function __construct( $config ){
		//判断$config数组是否符合规范
		/*foreach( $GLOBALS['config_tpl'] as $key => $value ){
			if(!isset( $config[ $key ] ) ){
				Phpfetcher_Log::warning( "bad \$config" );
			}
		}*/
		//创建数据库连接并保存
		$this->_con = mysql_connect( 
			$config['db_host'] . ':' . $config['db_port'],
			$config['db_username'],
			$config['db_password']
		);
		$this->_pre = $config['db_pre'];
		$this->_db_name = $config['db_name'];
		if(!$_con){
			die("connect mysql failed!");
		}
	}

	public function __destruct(){
		mysql_close($_con);
	}

	public function exe_sql( $str ){
		return mysql_query( $str, $this->$_con );
	} 
}
