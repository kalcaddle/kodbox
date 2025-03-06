<?php

/*
 * @link http://kodcloud.com/
 * @author warlee | e-mail:kodcloud@qq.com
 * @copyright warlee 2014.(Shanghai)Co.,Ltd
 * @license http://kodcloud.com/tools/license/license.txt
 */

class userIndex extends Controller {
	private $user;  //用户相关信息
	function __construct() {
		parent::__construct();
	}
	public function index(){
		@ob_end_clean();
		include(TEMPLATE.'user/index.html');
	}
	// 进入初始化; total=10ms左右;
	public function init(){
		Hook::trigger('globalRequestBefore');
		Hook::bind('beforeShutdown','user.index.shutdownEvent');
		if( !file_exists(USER_SYSTEM . 'install.lock') ){
			return ActionCall('install.index.check');
		}
		if( !file_exists(BASIC_PATH . 'config/setting_user.php') || empty($GLOBALS['config']['database'])){
			show_tips(LNG('explorer.sysSetUserError'));
		}
		$this->initDB();   		//
		Action('filter.index')->bindBefore();
		$this->initSession();   //
		$this->initSetting();   // 
		init_check_update();	// 升级检测处理;
		KodIO::initSystemPath();
		
		Action('filter.index')->bind();
		$this->loginCheck();
		Model('Plugin')->init();
		Action('filter.index')->trigger();
		if(!defined("USER_ID")){
			$userID = Session::get("kodUser.userID");$userID = 0;
			define("USER_ID",$userID ? $userID:0);
		}
	}
	public function shutdownEvent(){
		$GLOBALS['requestShutdownGlobal'] = true;// 请求结束后状态标记;
		TaskQueue::addSubmit();		// 结束后有任务时批量加入
		TaskRun::autoRun();			// 定期执行及延期任务处理;
		CacheLock::unlockRuntime(); // 清空异常时退出,未解锁的加锁;
	}
	private function initDB(){
		think_config($GLOBALS['config']['databaseDefault']);
		think_config($GLOBALS['config']['database']);
	}
	private function initSession(){
		$this->apiSignCheck();
		// 入口不处理cookie,兼容服务器启用了全GET缓存情况(输出前一次用户登录的cookie,导致账号登录异常)
		$action= strtolower(ACTION);
		if( $action == 'user.index.index' || $action == 'user.view.call'){
			Cookie::disable(true);
		}
		
		$systemPass = Model('SystemOption')->get('systemPassword');
		if(isset($_REQUEST['accessToken'])){
			$token = $_REQUEST['accessToken'];
			if(!$token || strlen($token) > 500){show_json('token error!',false);}

			$pass = substr(md5('kodbox_'.$systemPass),0,15);
			$sessionSign = Mcrypt::decode($token,$pass);
			if(!$sessionSign){show_json(LNG('common.loginTokenError'),ERROR_CODE_LOGOUT);}
			if($action == 'user.index.index'){Cookie::disable(false);} // 带token的url跳转入口页面允许cookie输出;
			Session::sign($sessionSign);
		}
		if(!$GLOBALS['disableSession'] && !Session::get('kod')){
			Session::set('kod',1);
			if(!Session::get('kod')){show_tips(LNG('explorer.sessionSaveError'));}
		}
		// 注意: Session设置sessionid的cookie;两个请求时间过于相近,可能导致删除cookie失败的问题;(又有sessionid请求覆盖)
		// 设置csrf防护;
		if(!Cookie::get('CSRF_TOKEN')){Cookie::set('CSRF_TOKEN',rand_string(16));}
	}
	
	private function initSetting(){
		if(!defined('STATIC_PATH')){
			define('STATIC_PATH',$GLOBALS['config']['settings']['staticPath']);
		}
		$sysOption = Model('SystemOption')->get();
		$upload = &$GLOBALS['config']['settings']['upload'];
		if(isset($sysOption['chunkSize'])){ //没有设置则使用默认;
			$upload['chunkSize']  = floatval($sysOption['chunkSize']);
			
			// 老版本升级,没有值情况处理;
			if(isset($sysOption['ignoreName'])){$upload['ignoreName'] = trim($sysOption['ignoreName']);}
			if(isset($sysOption['chunkRetry'])){$upload['chunkRetry'] = intval($sysOption['chunkRetry']);}
			if(isset($sysOption['threads'])){$upload['threads'] = floatval($sysOption['threads']);}
			if($upload['threads'] <= 0){$upload['threads'] = 1;}
			
			// 上传限制扩展名,限制单文件大小;
			$role = Action('user.authRole')->userRoleAuth();
			if($role && $role['info']){
				$roleInfo = $role['info'];
				// if(isset($roleInfo['ignoreExt'])){
				// 	$upload['ignoreExt']  = $roleInfo['ignoreExt'];
				// }
				if(isset($roleInfo['ignoreFileSize'])){
					$upload['ignoreFileSize']  = $roleInfo['ignoreFileSize'];
				}
			}
			if($sysOption['downloadSpeedOpen']){//限速大小;
				$upload['downloadSpeed'] = floatval($sysOption['downloadSpeed']);
			}
		}
		
		// 文件历史版本数量限制; 小于等于1则关闭,大于500则不限制;
		if(isset($sysOption['fileHistoryMax'])){
			$GLOBALS['config']['settings']['fileHistoryMax'] = intval($sysOption['fileHistoryMax']);
		}
		$upload['chunkSize'] = $upload['chunkSize']*1024*1024;
		$upload['chunkSize'] = $upload['chunkSize'] <= 1024*1024*0.1 ? 1024*1024*0.4:$upload['chunkSize'];
		$upload['chunkSize'] = intval($upload['chunkSize']);
	}

	/**
	 * 登录检测;并初始化数据状态
	 * 通过session或kodToken检测登录
	 */
	public function loginCheck() {
		if( KodUser::isLogin() ){
			return $this->userDataInit();
		}
		if(Session::get('kodUserLogoutTrigger')){return;} //主动设置退出,不自动登录;
		$userID 	= Cookie::get('kodUserID');
		$loginToken = Cookie::get('kodToken');
		if ($userID && $loginToken ) {
			$user = Model('User')->getInfoFull($userID);
			if ($user && $user['status'] != '0' && $this->makeLoginToken($user['userID']) == $loginToken ) {
				return $this->loginSuccess($user);
			}
		}
	}
	
	private function logoutError($msg,$code){
		Session::destory();
		Cookie::remove('kodToken');
		show_json($msg,$code);
	}
	private function userDataInit() {
		$this->user = Session::get('kodUser');
		if($this->user){
			$findUser = Model('User')->getInfoFull($this->user['userID']);
			// 用户账号hash对比; 账号密码修改自动退出处理;
			if($findUser['userHash'] != $this->user['userHash']){
				$this->logoutError(LNG('common.loginTokenError').'(hash)',ERROR_CODE_LOGOUT);
			}
			
			//优化,避免频繁写入session(file缓存时容易造成并发锁); 变化时更新;或者超过10分钟写入一次;
			$_lastTime = $this->user['_lastTime'];unset($this->user['_lastTime']);
			if($this->user != $findUser || time() - $_lastTime > 600){
			    $findUser['_lastTime'] = time();
			    Session::set('kodUser',$findUser);
			}
			$this->user = $findUser;
		}
		if(!$this->user) {
			$this->logoutError('user data error!',ERROR_CODE_LOGOUT);
		}else if($this->user['status'] == 0) {
			$this->logoutError(LNG('user.userEnabled'),ERROR_CODE_USER_INVALID);
		}else if($this->user['roleID'] == '') {
			$this->logoutError(LNG('user.roleError'),ERROR_CODE_LOGOUT);
		}
		
		$GLOBALS['isRoot'] = 0;
		$role = Model('SystemRole')->listData($this->user['roleID']);
		if($role['administrator'] == '1'){
			$GLOBALS['isRoot'] = 1;
		}
		
		// 计划任务处理; 目录读写所有者为系统;
		if( strtolower(ACTION) == 'user.view.call'){
			define('USER_ID',0);
			define('MY_HOME','');
			define('MY_DESKTOP','');
			return;
		}
				
		define('USER_ID',$this->user['userID']);
		define('MY_HOME',KodIO::make($this->user['sourceInfo']['sourceID']));
		define('MY_DESKTOP',KodIO::make($this->user['sourceInfo']['desktop']));
	}

	public function accessToken(){
		$systemPass = Model('SystemOption')->get('systemPassword');
		$pass = substr(md5('kodbox_'.$systemPass),0,15);
		return Mcrypt::encode(Session::sign(),$pass,3600*24*30);
	}
	public function accessTokenGet(){
		if(!KodUser::isLogin()){show_json('user not login!',ERROR_CODE_LOGOUT);}
		show_json($this->accessToken(),true);
	}	
	public function accessTokenCheck($token){
		if(!$token || strlen($token) > 500) return false;

		$systemPass = Model('SystemOption')->get('systemPassword');
		$pass = substr(md5('kodbox_'.$systemPass),0,15);
		$sessionSign = Mcrypt::decode($token,$pass);
		if(!$sessionSign || $sessionSign != Session::sign()) return false;
		return true;
	}
		
	// 登录校验并自动跳转 (已登录则直接跳转,未登录则登录成功后跳转)
	public function autoLogin(){
		$link = $this->in['link'];
		if(!$link) return;

		$errorTips = _get($this->in,'msg','');
		$errorTips = $errorTips == '[API LOGIN]' ? '':$errorTips; // 未登录标记,不算做登录错误;
		if(KodUser::isLogin() && !$errorTips){
			$param = 'kodTokenApi='.$this->accessToken();
			if($this->in['callbackToken'] == '1'){
				$link .= strstr($link,'?') ? '&'.$param:'?'.$param;
			}
			header('Location:'.$link);exit;
		}
		
		$param  = '#user/login&link='.rawurlencode($link);
		$param .= isset($this->in['msg']) ? "&msg=".$this->in['msg']:'';
		$param .= isset($this->in['callbackToken']) ? '&callbackToken=1':'';
		header('Location:'.APP_HOST.$param);exit;
	}

	/**
	 * 根据用户名密码获取用户信息
	 * @param [type] $name
	 * @param [type] $password
	 */
	public function userInfo($name, $password){
		$user = Model("User")->userLoginCheck($name,$password,true);
		if(!is_array($user)) {
			$userHook = Hook::trigger("user.index.userInfo",$name, $password);
			if(is_array($userHook)) return $userHook;// 第三方登陆不做检测处理;
		}
		return Hook::trigger('user.index.loginSubmitBefore',$name,$user);
	}
	
	/**
	 * 退出处理
	 */
	public function logout() {
		$user = Session::get('kodUser');
		if(!is_array($user) || !$user['userID']){show_json('ok');}
		Hook::trigger('user.index.logoutBefore',$user);
		
		$lastLogin = time() - $GLOBALS['config']['cache']['sessionTime'] - 10;
		Model('User')->userEdit($user['userID'],array("lastLogin"=>$lastLogin)); 
		Session::destory();
		Cookie::remove(SESSION_ID,true);
		Cookie::remove('kodToken');
		Action('user.sso')->logout(); // 单点登录同步跟随退出;清理缓存;
		show_json('ok');
	}

	/**
	 * 登录数据提交处理；登录跳转：
	 */
	public function loginSubmit() {
		$res = $this->loginWithToken();
		if($res || $res !== false) return $res;
		$res = $this->loginWithThird();	// app第三方账号登录
		if($res || $res !== false) return $res;
		$data = Input::getArray(array(
			"name"		=> array("check"=>"require",'lengthMax'=>500),
			"password"	=> array('check'=>"require",'lengthMax'=>500),
		));
		$checkCode = Input::get('checkCode', 'require', '');
		if( need_check_code() && $data['name'] != 'guest'){
			Action('user.setting')->checkImgCode($checkCode);
		}
		$data['password'] = KodUser::parsePass($data['password']);

		$user = $this->userInfo($data['name'],$data['password']);
		if (!is_array($user)){
			show_json($this->loginErrMsg($user),false);
		}
		if(!$user['status']){
			show_json(LNG('user.userEnabled'), ERROR_CODE_USER_INVALID);
		}
		$this->loginSuccessUpdate($user);
		//自动登录跳转; http://xxx.com/?user/index/loginSubmit&name=guest&password=guest&auto=1
		$this->loginAuto();
		show_json('ok',true,$this->accessToken());
	}
	private function loginWithToken(){
		if (!isset($this->in['loginToken'])) return false;
		$apiToken = $this->config['settings']['apiLoginToken'];
		$param = explode('|', $this->in['loginToken']);
		if (strlen($apiToken) < 5 ||
			count($param) != 2 || strlen($this->in['loginToken']) > 500 ||
			md5(base64_decode($param[0]) . $apiToken) != $param[1]
		) {
			return show_json('API 接口参数错误!', false);
		}
		$name = base64_decode($param[0]);
		$res = Model('User')->where(array('name' => $name))->field('userID')->find();
		if(empty($res['userID'])) {
			return show_json(LNG('user.pwdError'),false);
		}
		$user = Model('User')->getInfoFull($res['userID']);
		$this->loginSuccessUpdate($user);
		$this->loginAuto();
		return show_json('ok',true,$this->accessToken());
	}
	
	// 更新登录时间
	public function loginSuccessUpdate($user){
		$this->loginSuccess($user);
		Model('User')->userEdit($user['userID'],array("lastLogin"=>time()));
		ActionCall('admin.log.loginLog');	// 登录日志
	}

	/**
	 * （app）第三方登录
	 */
	private function loginWithThird(){
		if (!isset($this->in['third'])) return false;
		$third = Input::get('third');
		if(empty($third)) return false;
		$third = is_array($third) ? $third : json_decode($third, true);

		// 判断执行结果
		if(isset($third['avatar'])) $third['avatar'] = rawurldecode($third['avatar']);
		Hook::trigger('user.bind.withApp', $third);
		$this->loginAuto();
		return show_json('ok',true,$this->accessToken());
	}

	// 登录后自动跳转
	private function loginAuto() {
		if($this->in['auto'] != '1') return;
		header('Location: '.APP_HOST);exit;
	}
	
	// 刷新用户信息;
	public function refreshUser($userID){
		Model('User')->clearCache($userID);
		$user = Model('User')->getInfoFull($userID);
		Session::set('kodUser', $user);	
	}
	
	//前端（及app）找回密码
	public function findPassword(){
		return Action('user.setting')->findPassword();
	}
	
	/**
	 * app端请求——弃用？
	 */
	private function findPwdWidthApp(){
		// api，直接填写手机/邮箱验证码、密码进行修改
		$data = Input::getArray(array(
			'type'		 => array('check' => 'in','default'=>'','param'=>array('phone','email')),
			'input'		 => array('check' => 'require'),
			'code'		 => array('check' => 'require'),
			'password'	 => array('check' => 'require'),
		));
		$param = array(
			'type' => 'regist',
			'input' => $data['input']
		);
		Action('user.setting')->checkMsgCode($data['type'], $data['code'], $param);
		$user = Model('User')->where(array($data['type'] => $data['input']))->find();
		if (empty($user)) {
			show_json(LNG('user.notBind'), false);
		}
		if (!Model('User')->userEdit($user['userID'], array('password' => $data['password']))) {
			show_json(LNG('explorer.error'), false);
		}
		show_json(LNG('explorer.success'));
	}
	
	public function loginSuccess($user) {
		// 登录管控
		$result = Hook::trigger('user.index.loginBefore',$user);
		if($result !== true) {
			show_tips($this->loginErrMsg($result), APP_HOST);exit;
		}
		Hook::trigger('user.index.loginAfter',$user);

		Session::set('kodUser', $user);
		Cookie::set('kodUserID', $user['userID']);
		Cookie::set('kodTokenUpdate','1');//更新token通知;//自动登录处理;
		$kodToken = Cookie::get('kodToken');
		if($kodToken){//已存在则延期
			Cookie::setSafe('kodToken',$kodToken);
		}
		if (!empty($this->in['rememberPassword'])) {
			$kodToken = $this->makeLoginToken($user['userID']);
			Cookie::setSafe('kodToken',$kodToken);
		}
		$this->userDataInit($user);
		Hook::trigger("user.loginSuccess",$user);
	}
	// 登录时模糊提示消息
	private function loginErrMsg($code){
		if (in_array($code, array('-1','-2'))) {
			return LNG('user.pwdError');
		}
		$error = UserModel::errorLang($code);
		return $error ? $error : LNG('user.pwdError');
	}
	
	/**
	 * 以当前用户身份临时授权访问接口请求构造
	 * 
	 * 安全性: 不泄露 accessToken; 
	 * 只能访问特定接口特定参数
	 * 一定时间内访问有效
	 * 
	 * 可以按应用授权;维护{appKey:appSecret,...};
	 * 注:完全以当前身份访问, 权限以当前用户为准;
	 */
	public function apiSignMake($action,$args,$timeout=false,$appKey='',$uriIndex=false){
		$appSecret = $this->appKeySecret($appKey);
		if(!$appSecret || !USER_ID){ // 外链分享等情况
			return APP_HOST.'index.php?'.$action.'&'.http_build_query($args);
		}
		
		$userID  = Session::get('kodUser.userID');
		$timeout = $timeout ? $timeout : 3600*24*30; // 咱不使用, 默认一直有效;
		$param   = '';
		$keyList = array(strtolower($action));
		$signArr = array(strtolower($action),$appSecret);
		foreach($args as $key=>$val){
			$keyList[] = strtolower($key);
			$signArr[] = strtolower($key).'='.base64_encode($val);
			$param .= $key.'='.rawurlencode($val).'&';
		}
		$signToken = md5(implode(';',$signArr));//解密时获取
		$acionKey  = hash_encode(implode(';',$keyList));
		$actionToken = Mcrypt::encode(USER_ID,$signToken,0,md5($appSecret)); // 避免url变化,无法缓存问题;
		$param .= 'actionToken='.$actionToken.'&actionKey='.$acionKey;
		// 包含index.php; (跨域时浏览器请求options, 避免被nginx拦截提前处理)
		if($uriIndex){return APP_HOST.'index.php?'.$action.'&'.$param;}
		return urlApi($action,$param);
	}

	// 解析处理;
	public function apiSignCheck(){
		if(isset($_REQUEST['accessToken']) && $_REQUEST['accessToken']){return;} // token优先;
		$actionToken = _get($this->in, 'actionToken', '');
		$actionKey	 = _get($this->in, 'actionKey', '');
		$appKey   	 = _get($this->in, 'appKey', '');
		$appSecret	 = $this->appKeySecret($appKey);
		if(!$actionToken || !$actionKey || !$appSecret) return;
		if(strlen($actionToken) > 500) return;
		
		$action  = str_replace('.','/',ACTION);
		$keyList = explode(';',hash_decode($actionKey));
		$signArr = array(strtolower($action),$appSecret);
		if(strtolower($action) != $signArr[0]) return;
		for ($i = 1; $i < count($keyList); $i++) {
			$key = $keyList[$i];
			$signArr[] = strtolower($key).'='.base64_encode($this->in[$key]);
		}
		$signToken = md5(implode(';',$signArr));//同上加密计算
		$userID    = Mcrypt::decode($actionToken,$signToken);
		
		allowCROS();Cookie::disable(true);
		// 当前为自己则默认使用当前session(是否登录保险箱等session共享;)
		if($userID && Session::get('kodUser.userID') == $userID){return;} 
		$userInfo  = $userID ? Model('User')->getInfoFull($userID):false;
		if(!is_array($userInfo)) {show_json(LNG("explorer.systemError").'.[apiSignCheck]',false);};

		// api临时访问接口; 不处理cookie; 不影响已登录用户session; 允许跨域
		Session::$sessionSign = guid();
		Session::set('kodUser', $userInfo);
		unset($_REQUEST['accessToken']);
	}
	// 维护多个应用
	public function appKeySecret($appKey=''){
		if(!$appKey) return md5(Model('SystemOption')->get('systemPassword'));
		$appList = Model('SystemOption')->get('appKeySecret');
		if(!$appList || !is_array($appList[$appKey])) return '';
		return $appList[$appKey]['appSecret'];
	}
	

	//登录token
	private function makeLoginToken($userID) {
		$pass = Model('SystemOption')->get('systemPassword');
		$user = Model('User')->getInfo($userID);
		if(!$user) return false;
		return md5($user['password'] . $pass . $userID);
	}

	// 系统维护中
	public function maintenance($update=false,$value=0){
		// Model('SystemOption')->set('maintenance',0);exit;
		if($update) return Model('SystemOption')->set('maintenance', $value);
		// 有配置参数则不处理
		if ($GLOBALS['config']['settings']['systemMaintenance'] === 0) return;
		// 管理员or未启动维护，返回
		if(KodUser::isRoot() || !Model('SystemOption')->get('maintenance')) return;
		show_tips(LNG('common.maintenanceTips'), '','',LNG('common.tips'));
	}
}
