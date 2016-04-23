<?
$config_tpl = array(
	'db_host' 		=> '',
	'db_port'		=> '3306',
	'db_username' 	=> '',
	'db_password' 	=> '',
	'db_name' 		=> '',//库名
	'db_pre' 		=> '',//前缀
);
class MySQL{
	private $_con;//连接
	public $_pre = '';
	public $_db_name = '';
	private function __construct( $config ){
		//判断$config数组是否符合规范
		foreach( $config_tpl as $key => $value ){
			if(!isset( $config[ $key ] ) ){
				Phpfetcher_Log::warning( "bad \$config" );
			}
		}
		//创建数据库连接并保存
		$this->_con = mysql_connect( 
			$config['db_host'] . ':' . $config['port'],
			$config['username'],
			$config['password']
		);
		$this->_pre = $config['db_pre'];
		$this->_db_name = $config['db_name'];
		if(!$_con){
			die("connect mysql failed!");
		}
	}

	private function __destruct(){
		mysql_close($_con);
	}

	public function exe_sql( $str ){
		return mysql_query( $str, $this->$_con );
	} 
}