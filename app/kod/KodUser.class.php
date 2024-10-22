<?php 

/**
 * 用户相关信息处理
 */
class KodUser{
	public static function id(){
		return defined('USER_ID') ? USER_ID:0;
	}
	public static function isLogin(){
		$user = Session::get('kodUser');
		return (is_array($user) && isset($user['userID'])) ? 1 : 0;
	}
	public static function checkLogin(){
		$user = Session::get('kodUser');
		$code = (is_array($user) && isset($user['userID'])) ? 1 : 0;
		if(!$code){show_json(LNG('user.loginFirst'),false);}
		return $user;
	}
	
	public static function isRoot(){
		return (isset($GLOBALS['isRoot']) && $GLOBALS['isRoot'] == 1) ? 1:0;
	}
	public static function checkRoot(){
		$code = (isset($GLOBALS['isRoot']) && $GLOBALS['isRoot'] == 1) ? 1:0;
		if(!$code){show_json(LNG('explorer.noPermissionAction'),false);}
	}

	/**
	 * 解析加盐密码
	 * @param [type] $pass
	 * @param integer $salt
	 * @return void
	 */
	public static function parsePass($pass, $salt=0){
		if (!$pass) return $pass;
		$pass = rawurldecode($pass);
		if (_get($GLOBALS, 'in.salt', 0) != '1' && $salt != '1') return $pass;
		$key = substr($pass,0,5)."2&$%@(*@(djfhj1923";
		return Mcrypt::decode(substr($pass,5),$key);
	}

}