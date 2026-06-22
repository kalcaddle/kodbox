<?php 

/**
 * 用户相关信息处理
 */
class KodUser{
	private static  $_shareGuestID = 4200000000;	// 游客用户ID; 默认认为用户数不超过42亿
	private static  $_userID = -1; 					// -1=>使用USER_ID; 否则id= $_userID; 管理员--切换用户身份;获取配置等操作;
	public static function init($id=-1){
		if(defined('USER_ID')){return;}
		
		self::$_userID = $id === -1 ? self::$_shareGuestID : intval($id);
		define('USER_ID',self::$_userID);
	}
	public static function set($id=-1){
		if($id === 'clear'){self::$_userID = -1;return;}					// 清除用户ID;
		if($id === 'guest'){self::$_userID = self::$_shareGuestID;return;} 	// 指定用户为游客;
		if($id !== -1){self::$_userID = intval($id);return;} 				// 指定用户ID;
	}
	
	public static function id($id=-1){
		if(self::$_userID !== -1){return self::$_userID;}
		return defined('USER_ID') ? USER_ID:0;
	}
	public static function isGuest($id=-1){
		$userID = $id === -1 ? self::id() : $id;
		return $userID == self::$_shareGuestID;
	}
	
	public static function isLogin(){
		$user = Session::get('kodUser');
		return (is_array($user) && isset($user['userID']) && $user['userID']) ? 1 : 0;
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
		$pass = trim($pass);
		if (!$pass) return $pass;
		// $pass = rawurldecode($pass);	// 后台接受的参数已经过解码，有特殊字符时再次解码会导致错误
		if (_get($GLOBALS, 'in.salt', 0) != '1' && $salt != '1') return $pass;
		$key  = substr($pass,0,5)."2&$%@(*@(djfhj1923";
		$pass = Mcrypt::decode(substr($pass,5),$key);
		return trim($pass);
	}

}