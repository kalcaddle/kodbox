<?php

/**
 * 共享账号登录;支持限定账户,部门,权限组;
 */
class userSso extends Controller{
	public function __construct(){
		parent::__construct();
	}

	// 引入代码调用;
	public function check($appName){
		$result = $this->checkAuth($appName);
		if($result === true) return array(1,Action('user.index')->accessToken());
		$login = APP_HOST.'#user/login&link='.rawurlencode(this_url()).'&msg='.$result;
		header('Location:'.$login);exit;
	}
	private function checkAuth($appName){
		Action('user.index')->init();
		if(!Session::get('kodUser.userID')) return '[API LOGIN]';
		$plugin = Model("Plugin")->loadList($appName);
		if (!$plugin || $plugin['status'] == 0){
			return $appName.' '.LNG('admin.plugin.disabled');
		}
		$authValue = $plugin['config']['pluginAuth'];
		if(!Action('user.AuthPlugin')->checkAuthValue($authValue)){
			return LNG('user.loginNoPermission');
		}
		return true;
	}


	// 第三方通过url调用请求;
	public function apiCheckToken(){
		$result = $this->checkAuth($_GET['appName']);
		$result = $result === true ? '[ok]' : "[error]:".$result;

		if($result == '[ok]'){ob_get_clean();}
		// var_dump($this->in,$_GET,Session::get());
		echo $result;
	}
	// -> login&apiLogin => 第三方app&token=accessToken;
	public function apiLogin(){
		$result = $this->checkAuth($_GET['appName']);
		$callbackUrl = $_GET['callbackUrl'];
		if($result === true){
			$token = Action('user.index')->accessToken();
			$callbackUrl = $this->urlRemoveKey($callbackUrl,'kodTokenApi');
			if(strstr($callbackUrl,'?')){
				$callbackUrl = $callbackUrl.'&kodTokenApi='.$token;
			}else{
				$callbackUrl = $callbackUrl.'?kodTokenApi='.$token;
			}
			// pr($callbackUrl,$token);exit;
			header('Location:'.$callbackUrl);exit;
		}
		
		$link = APP_HOST.'#user/login&link='.rawurlencode($callbackUrl).'&callbackToken=1&msg='.$result;
		header('Location:'.$link);exit;
	}
	
	private function urlRemoveKey($url,$key){
		$parse = parse_url($url);
		parse_str($parse['query'],$get);
		unset($get[$key]);
		$query = http_build_query($get);
		$query = $query ? '?'.$query : '';
		$port  = (isset($parse['port']) && $parse['port'] != '80' ) ? ':'.$parse['port']:'';
		return $parse['scheme'].'://'.$parse['host'].$port.$parse['path'].$query;
	}
}