<?php

/**
 * 共享账号登录;支持限定账户,部门,权限组;
 */
class userSso extends Controller{
	public function __construct(){
		parent::__construct();
	}

	// sdk模式; 引入代码调用;
	public function check($appName){
		$urlInfo = parse_url(this_url());
		$GLOBALS['API_SSO_KEY']  = 'kodTokenApi-'.substr(md5($urlInfo['path']),0,5);
		$GLOBALS['API_SSO_PATH'] = $this->thisPathUrl();
		if(isset($this->in['kodTokenApi'])){
			$_REQUEST['accessToken'] = $this->in['kodTokenApi'];
		}
		
		$app = new Application();
		$app->setDefault('user.index.index');
		$result = $this->checkAuth($appName);
		$theUrl = $this->urlRemoveKey(this_url(),'kodTokenApi');
		if($result === true){
			if(isset($this->in['kodTokenApi'])){// 登录成功处理;	
				header('Location:'.$theUrl);exit;
			}
			return $this->userInfo();
		}
		$login = 'index.php?user/index/autoLogin&link='.rawurlencode($theUrl).'&callbackToken=1&msg='.$result;
		header('Location:'.APP_HOST.$login);exit;
	}
	private function userInfo(){
		$userInfo = Session::get('kodUser');
		if(!$userInfo) return false;
		
		$keys = explode(',','userID,name,email,phone,nickName,avatar,sex,avatar');
		$user = array_field_key($userInfo,$keys);
		$user['accessToken'] = Action('user.index')->accessToken();
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
			$authValue = $appName;
		}else{
			$plugin = Model("Plugin")->loadList($appName);
			if (!$plugin || $plugin['status'] == 0){
				return $appName.' '.LNG('admin.plugin.disabled');
			}
			$authValue = $plugin['config']['pluginAuth'];
		}
		if(!Action('user.AuthPlugin')->checkAuthValue($authValue)){
			return LNG('user.loginNoPermission');
		}
		return true;
	}

	// 第三方通过url调用请求;
	public function apiCheckToken(){
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