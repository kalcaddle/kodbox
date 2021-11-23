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
		include(TEMPLATE.'user/index.html');
	}
	// 进入初始化
	public function init(){
		Hook::trigger('globalRequestBefore');
		Hook::bind('beforeShutdown','user.index.shutdownEvent');
		if( !file_exists(USER_SYSTEM . 'install.lock') ){
			return ActionCall('install.index.check');
		}
		$this->initDB();   		//
		$this->initSession();   //
		$this->initSetting();   // 
		init_check_update();	// 升级检测处理;
		KodIO::initSystemPath();
		
		Action('filter.index')->bind();
		$this->loginCheck();
		Model('Plugin')->init();
		Action('filter.index')->trigger();
	}
	public function shutdownEvent(){
		CacheLock::unlockRuntime();// 清空异常时退出,未解锁的加锁;
	}
	private function initDB(){
		think_config($GLOBALS['config']['databaseDefault']);
		think_config($GLOBALS['config']['database']);
	}
	private function initSession(){
		$systemPassword = Model('SystemOption')->get('systemPassword');
		if(isset($_REQUEST['accessToken'])){
			$pass = substr(md5('kodbox_'.$systemPassword),0,15);
			$sessionSign = Mcrypt::decode($_REQUEST['accessToken'],$pass);
			if(!$sessionSign){
				show_json(LNG('common.loginTokenError'),ERROR_CODE_LOGOUT);
			}
			Session::sign($sessionSign);
		}
		Session::set('kod',1);
		if(!Session::get('kod')){
			show_tips(LNG('explorer.sessionSaveError'));
		}
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
			$upload['ignoreName'] = trim($sysOption['ignoreName']);
			$upload['chunkRetry'] = intval($sysOption['chunkRetry']);
			// $upload['httpSendFile']  = $sysOption['httpSendFile'] == '1'; //前端默认屏蔽;
			
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
		$upload['chunkSize'] = $upload['chunkSize']*1024*1024;
		$upload['chunkSize'] = $upload['chunkSize'] <= 1024*1024*0.1 ? 1024*1024*0.4:$upload['chunkSize'];
		$upload['chunkSize'] = intval($upload['chunkSize']);
	}

	/**
	 * 登录检测;并初始化数据状态
	 * 通过session或kodToken检测登录
	 */
	public function loginCheck() {
		if( is_array(Session::get('kodUser')) ){
			return $this->userDataInit();
		}
		$userID 	= Cookie::get('kodUserID');
		$loginToken = Cookie::get('kodToken');
		if ($userID && $loginToken ) {
			$user = Model('User')->getInfo($userID);
			if ($user && $this->makeLoginToken($user['userID']) == $loginToken ) {
				return $this->loginSuccess($user);
			}
		}
	}
	private function userDataInit() {
		$this->user = Session::get('kodUser');
		if($this->user){
			$findUser = Model('User')->getInfoFull($this->user['userID']);
			// 用户账号hash对比; 账号密码修改自动退出处理;
			if($findUser['userHash'] != $this->user['userHash']){
				Session::destory();
				show_json('user hash error!',ERROR_CODE_LOGOUT);
			}
			Session::set('kodUser',$findUser);
		}
		if (!$this->user) {
			Session::destory();
			show_json('user data error!',ERROR_CODE_LOGOUT);
		} else if ($this->user['status'] == 0) {
			Session::destory();
			show_json(LNG('user.userEnabled'),ERROR_CODE_USER_INVALID);
		} else if ($this->user['roleID'] == '') {
			Session::destory();
			show_json(LNG('user.roleError'),ERROR_CODE_LOGOUT);
		}
		
		$GLOBALS['isRoot'] = 0;
		$role = Model('SystemRole')->listData($this->user['roleID']);
		if($role['administrator'] == '1'){
			$GLOBALS['isRoot'] = 1;
		}
		
		// 计划任务处理; 目录读写所有者为系统;
		if( strtolower(ACTION) == 'user.view.call'){
			define('USER_ID','0');
			define('MY_HOME','');
			define('MY_DESKTOP','');
			return;
		}
				
		define('USER_ID',$this->user['userID']);
		define('MY_HOME',KodIO::make($this->user['sourceInfo']['sourceID']));
		define('MY_DESKTOP',KodIO::make($this->user['sourceInfo']['desktop']));
	}

	public function accessToken(){
		$pass = Model('SystemOption')->get('systemPassword');
		$pass = substr(md5('kodbox_'.$pass),0,15);
		$token = Mcrypt::encode(Session::sign(),$pass,3600*24*30);
		return $token;
	}
	public function accessTokenGet(){
		if(!Session::get('kodUser')){
			show_json('user not login!',ERROR_CODE_LOGOUT);
		}
		show_json($this->accessToken(),true);
	}
	
	// 登录校验并自动跳转 (已登录则直接跳转,未登录则登录成功后跳转)
	public function autoLogin(){
		$link = $this->in['link'];
		if(!$link) return;

		$errorTips = _get($this->in,'msg','');
		$errorTips = $errorTips == '[API LOGIN]' ? '':$errorTips; // 未登录标记,不算做登录错误;
		if(Session::get('kodUser') && !$errorTips){
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
		$user = Model("User")->userLoginCheck($name,$password);
		if(!is_array($user)) {
			$theUser = Hook::trigger("user.index.userInfo",$name, $password);
			if(is_array($theUser)){
				$user = $theUser? $theUser:false;
			}
		}
		if(!is_array($user)) return $user;
		
		$result = Hook::trigger('user.index.loginBefore',$user);
		if($result !== true) return $result;
		Hook::trigger('user.index.loginAfter',$user);
		return $user;
	}
	
	/**
	 * 退出处理
	 */
	public function logout() {
		Session::destory();
		Cookie::remove(SESSION_ID,true);
		Cookie::remove('kodToken');
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
			"name"		=> array("check"=>"require"),
			"password"	=> array('check'=>"require"),
			"salt"		=> array("default"=>false),
		));
		$checkCode = Input::get('checkCode', 'require', '');
		if( need_check_code() && $data['name'] != 'guest'){
			Action('user.setting')->checkImgCode($checkCode);
		}
		if ($data['salt']) {
			$key = substr($data['password'], 0, 5) . "2&$%@(*@(djfhj1923";
			$data['password'] = Mcrypt::decode(substr($data['password'], 5), $key);
		}
		$user = $this->userInfo($data['name'],$data['password']);
		if (!is_array($user)){
			$error = UserModel::errorLang($user);
			$error = $error ? $error:LNG('user.pwdError');
			show_json($error,false);
		}
		if(!$user['status']){
			show_json(LNG('user.userEnabled'), ERROR_CODE_USER_INVALID);
		}
		$this->loginSuccessUpdate($user);
		//自动登录跳转; http://xxx.com/?user/index/loginSubmit&name=guest&password=guest&auto=1
		if($this->in['auto'] == '1'){
			header('Location: '.APP_HOST);exit;
		}
		show_json('ok',true,$this->accessToken());
	}
	private function loginWithToken(){
		if (!isset($this->in['loginToken'])) return false;
		$apiToken = $this->config['settings']['apiLoginToken'];
		$param = explode('|', $this->in['loginToken']);
		if (strlen($apiToken) < 5 ||
			count($param) != 2 ||
			md5(base64_decode($param[0]) . $apiToken) != $param[1]
		) {
			return show_json('API 接口参数错误!', false);
		}
		$name = base64_decode($param[0]);
		$res = Model('User')->where(array('name' => $name))->field('userID')->find();
		if(empty($res['userID'])) {
			return show_json(LNG('user.pwdError'),false);
		}
		$user = Model('User')->getInfo($res['userID']);
		$this->loginSuccessUpdate($user);
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
		Action('user.bind')->bindWithApp($third);
		return show_json('ok',true,$this->accessToken());
	}
	
	/**
	 * 前端（及app）找回密码
	 */
	public function findPassword(){
		return Action('user.setting')->findPassword();
	}
	
	/**
	 * app端请求
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
		Session::set('kodUser', $user);
		Cookie::set('kodUserID', $user['userID']);
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
		// 管理员or未启动维护，返回
		if(_get($GLOBALS,'isRoot') || !Model('SystemOption')->get('maintenance')) return;
		show_tips(LNG('common.maintenanceTips'), '','',LNG('common.tips'));
	}
}
