<?php 

/**
 * 用户相关信息处理
 */
class KodUser{
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
}