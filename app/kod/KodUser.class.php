<?php 

/**
 * 用户相关信息处理
 */
class KodUser{
	public static function id($setUserID=-1){
		static $_userID = -1; // 管理员--切换用户身份;获取配置等操作;
		if($setUserID === 'clear'){$_userID = -1;return;}
		if($setUserID !== -1){$_userID = intval($setUserID);return;}
		if($_userID   !== -1){return $_userID;}
		
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
		$pass = trim($pass);
		if (!$pass) return $pass;
		// $pass = rawurldecode($pass);	// 后台接受的参数已经过解码，有特殊字符时再次解码会导致错误
		if (_get($GLOBALS, 'in.salt', 0) != '1' && $salt != '1') return $pass;
		$key  = substr($pass,0,5)."2&$%@(*@(djfhj1923";
		$pass = Mcrypt::decode(substr($pass,5),$key);
		return trim($pass);
	}

}