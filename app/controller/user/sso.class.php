<?php

/**
 * 共享账号登录;支持限定账户,部门,权限组;
 */
class userSso extends Controller{
	public function __construct(){
		if(!defined('MOD')){
			define('MOD','user');
			define('ST','sso');
			define('ACT','index');
			define('ACTION',MOD.'.'.ST.'.'.ACT);
		}
		parent::__construct();
	}
	public function index(){
	}

	// sdk模式; 引入代码调用;
	public function check($appName){
		$urlInfo = parse_url(this_url());
		$GLOBALS['API_SSO_KEY']  = 'kodTokenApi-'.substr(md5($urlInfo['path']),0,5);
		$GLOBALS['API_SSO_PATH'] = $this->thisPathUrl();
		$apiToken 	 = isset($this->in['kodTokenApi']) ? $this->in['kodTokenApi'] : '';
		$sessionSign = $apiToken ? Cache::get($apiToken) : '';
		if($sessionSign){Session::sign($sessionSign);}
		
		$app = new Application();
		$app->setDefault('user.index.index');
		$result = $this->checkAuth($appName);
		$theUrl = $this->urlRemoveKey(this_url(),'kodTokenApi');
		if($result === true){
			if($apiToken){header('Location:'.$theUrl);exit;} // 登录成功处理;
			return $this->userInfo();
		}
		$location = APP_HOST.'index.php?user/index/autoLogin&link='.rawurlencode($theUrl).'&callbackToken=1&msg='.$result;
		header('Location:'.$location);exit;
	}
	private function userInfo(){
		$userInfo = Session::get('kodUser');
		if(!$userInfo) return false;
		
		$keys = explode(',','userID,name,email,phone,nickName,avatar,sex,avatar');
		$user = array_field_key($userInfo,$keys);
		// $user['accessToken'] = Action('user.index')->accessToken(); // 不再输出token,避免被攻击引导泄露;
		return $user;
	}
	private function thisPathUrl(){
		$uriInfo = parse_url(this_url());
		$uriPath = dirname($uriInfo['path']);
		if(substr($uriPath,-1) == '/'){$uriPath = $uriInfo['path'];}
		return '/'.trim($uriPath,'/');
	}
	
	private function checkAuth($appName){
		Action('user.index')->init();
		if(!Session::get('kodUser.userID')) return '[API LOGIN]';

		// user:所有登录用户, root:系统管理员用户; 其他指定用户json:指定用户处理;
		if(!$appName || $appName == 'user:all'){$appName = '{"user":"all"}';}
		if($appName == 'user:admin'){$appName = '{"user":"admin"}';}
		if(substr($appName,0,1) == '{'){
			//支持直接传入权限设定对象;{"user":"1,3","group":"1","role":"1,2"}
			$allow = Action('user.AuthPlugin')->checkAuthValue($appName);
		}else{
			$allow = Action('user.AuthPlugin')->checkAuth($appName);
		}
		if(!$allow){return LNG('user.loginNoPermission');}
		return true;
	}

	// 第三方通过url调用请求; 校验kodTokenApi,成功后返回用户基本信息;
	public function apiCheckToken(){
		$apiToken = isset($this->in['kodTokenApi']) ? $this->in['kodTokenApi'] : '';
		$sessionSign = $apiToken ? Cache::get($apiToken):'';
		if(!$sessionSign){echo "[error]:[API LOGIN]";return;}

		// 运行期内指定当前session会话;
		Cookie::disable(true);
		Session::setBySign($sessionSign,Session::getBySign($sessionSign));
		$result  = $this->checkAuth($_GET['appName']);
		$content = "[error]:".$result;
		if($result === true){
			ob_get_clean();
			$content = json_encode($this->userInfo());
		}
		echo $content;
	}
	// -> login&apiLogin => 第三方app&token=accessToken;
	public function apiLogin(){
		$link = isset($_GET['callbackUrl']) ? $_GET['callbackUrl']:'';
		$result = $this->checkAuth($_GET['appName']);
		if($result !== true){
			$link = APP_HOST.'#user/login&link='.rawurlencode($link).'&callbackToken=1&msg='.$result;
			header('Location:'.$link);exit;
		}
		$apiToken 	= Action('user.index')->apiTokenMake();// 允许泄露,仅用于第三方应用回调校验;
		$link 		= $this->urlRemoveKey($link,'kodTokenApi');
		$link 		= $link.(strstr($link,'?') ? '&':'?').'kodTokenApi='.$apiToken;
		header('Location:'.$link);exit;
	}
	
	// 清除sso 登录cache缓存;
	public function logout(){
		$ssoKey 	= 'KOD_SSO_CACHE_KEY';
		$cachePath 	= BASIC_PATH.'data/temp/_cache/';
		$keys 		= isset($_COOKIE[$ssoKey]) ? $_COOKIE[$ssoKey] : '';
		if(!$keys){return;}
		
		$keyArr = explode(',',rawurldecode($keys));
		foreach ($keyArr as $key){
			$key = str_replace(array("/",'\\','?'),"_",$key);
        	$cacheFile = $cachePath."cache_api_".$key.'.php';
			if($key && @file_exists($cacheFile)){
				@unlink($cacheFile);
			}
		}
		Cookie::remove($ssoKey,true);
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