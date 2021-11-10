<?php

/*
 * @link http://kodcloud.com/
 * @author warlee | e-mail:kodcloud@qq.com
 * @copyright warlee 2014.(Shanghai)Co.,Ltd
 * @license http://kodcloud.com/tools/license/license.txt
 */

class userBind extends Controller {
	const BIND_META_INFO = 'Info';
	const BIND_META_UNIONID = 'Unionid';
	private $addUser;
	private $withApp;
	public $typeList = array();

	public function __construct() {
		parent::__construct();
		$this->typeList = array(
			'qq' => 'QQ',
			'github' => 'GitHub',
			'weixin' => LNG('common.wechat')
		);
		$this->addUser = $this->withApp = false;
		$this->checkAuth();
	}
	
	private function checkAuth(){
		if(!Session::get('kodUser')) return;
		$check = array(
			'user.bind.bind',
			'user.bind.bindApi',
			'user.bind.unbind',
			'user.bind.oauth',
			'user.bind.bindWithApp',
		);
		$action = strtolower(ACTION);
		foreach ($check as &$theAction){
			$theAction = strtolower($theAction);
		}
		if(!in_array($action,$check)) return;
		if(!Action('user.authRole')->authCan('user.edit')){
			show_json(LNG('explorer.noPermissionAction'),false);
		}
	}

	/**
	 * 第三方验证
	 * data string {type;openid;unionid;nickName;sex;avatar}
	 * type string qq|weixin|github
	 */
	public function bindApi() {
		// api固定参数:type、sign、kodid、timestamp、data
		$input = Input::getArray(array(
			'type'		 => array('check' => 'require'),
			'kodid'		 => array('check' => 'require'),
			'timestamp'	 => array('check' => 'require'),
			'data'		 => array('check' => 'require')
		));
		$type = $input['type'];
		if (!isset($this->typeList[$type])) {
			$this->bindHtml($type, array(), false, array('bind', LNG('common.invalidRequest')));
		}
		// 验证签名
		$sign = Input::get('sign','require');
		$_sign = $this->makeSign($input['kodid'], $input);
		if ($sign !== $_sign) {
			$this->bindHtml($type, array(), false, array('bind', LNG('user.signError')));
		}

		// 解析data参数
		$data = json_decode(@base64_decode($input['data']), true);
		// $data = unserialize_safe(@base64_decode($input['data']));
		if (!$data && is_string($input['data'])) {
			Model('SystemOption')->set('systemSecret', '');
			return $this->bindHtml($type, $data, false, array('bind', 'sign_error'));
		}
		return $this->bindDisplay($type, $data);
	}

	/**
	 * 第三方绑定返回信息
	 * @param type $type	qq|github|weixin
	 * @param type $data
	 */
	public function bindDisplay($type, $data) {
		$unionid = $data['unionid'];
		$client = Input::get('client','require',1); // 前后端
		$data['client'] = $client;

		// 判断是否已绑定
		if ($bind = $this->isBind($type, $unionid, $client)) {
			// 前端:已绑定,直接跳转登录
			// 后端:已绑定(别的账号),提示绑定失败
			if ($client) {
				$data['bind'] = true;
				if(is_array($bind) && $bind[0]){
					$success = true;
					$msg = array('login');	// 已绑定且已开启，直接登录
				}else{
					$msg = array('invalid');
					$success = false;
				}
			} else {
				$data['bind'] = false; // 可不传
				$msg = array('bind', 'bind_others', $bind);
				$success = false;	// $bind=true，说明已绑定其他账号——update:bind=name
			}
			return $this->bindHtml($type, $data, $success, $msg);
		}

		// 未绑定,前、后端处理
		$function = $client ? 'bindFront' : 'bindBack';
		return $this->$function($type, $data);
	}

	/**
	 * app端后台绑定
	 */
	public function bind(){
		$data = Input::getArray(array(
			'type'		=> array('check' => 'in', 'param' => array_keys($this->typeList)),
			'openid' 	=> array('check' => 'require'),
			'unionid' 	=> array('check' => 'require'),
			'nickName' 	=> array('check' => 'require'),
			'sex' 		=> array('check' => 'require', 'default' => 1),
			'avatar' 	=> array('check' => 'require', 'default' => ''),
		));
		$this->in['client'] = 0;
		$res = ActionCallHook('user.bind.bindDisplay', $data['type'], $data);
		$msg = $res['data'];
		if(isset($msg['msg'])){
			$msg = explode(';', $msg['msg']);
			$msg = isset($msg[1]) ? $msg[1] : $msg[0];
		}
		// 操作失败
		if(!$res['code']) show_json($msg, false);
		// success/bind同时为false；bind为true——更新用户信息失败
		if(!$res['data']['success']){	// bind
			show_json($msg, false);
		}
		$this->bindToServer($data);
		show_json(LNG('common.bindSuccess'));
	}

	/**
	 * 通过app端绑定
	 */
	public function bindWithApp($data){
		$this->withApp = true;
		if(empty($data['openid'])) show_json(LNG('common.invalidParam') . ':openid', false);
		if(empty($data['unionid'])) show_json(LNG('common.invalidParam') . ':unionid', false);
		$res = ActionCallHook('user.bind.bindDisplay', $data['type'], $data);
		$msg = $res['data'];	// bindHtml前就已经报错时，只打印了第一个错误信息
		if(isset($msg['msg'])){
			$msg = explode(';', $msg['msg']);
			$msg = isset($msg[1]) ? $msg[1] : $msg[0];
		}
		// 操作失败
		if(!$res['code']) show_json($msg, false);
		// 绑定成功但结果失败-未启用
		if(!$res['data']['success']){
			$code = $res['data']['bind'] ? ERROR_CODE_USER_INVALID : false;
			show_json($msg, $code);
		}
		// 未注册用户，直接返回登录
		if(!$this->addUser) return true;
		$this->bindToServer($data);
		return true;
	}
	// app端绑定信息写入api服务器
	private function bindToServer($data){
		// 写入api服务器
		$param = array(
			'type'		=> $data['type'],
			'nickName'	=> $data['name'],
			'avatar'	=> $data['avatar'],
			'sex'		=> isset($data['sex']) ? $data['sex'] : 1,
			'openid'	=> $data['openid'],
			'unionid'	=> $data['unionid'],
		);
		$this->apiRequest('bind', $param);	// TODO 这里不管成功与否，登录信息已存储
	}

	/**
	 * 第三方绑定返回信息-前端
	 * @param type $type
	 * @param type $data
	 */
	private function bindFront($type, $data) {
		$data['bind'] = false;
		// 1.判断是否开放了注册
		$regist = Model('SystemOption')->get('regist');
		if(!(int) $regist['openRegist']){
			return $this->bindHtml($type, $data, false, array('login'));
		}
		// 2. 自动注册
		$regist = $this->bindRegist($type, $data);
		if(!$regist['code']){
			return $this->bindHtml($type, $data, false, array('bind', $regist['data']));
		}
		$data['bind'] = true;
		// 自动登录
		$userID = $regist['data'];
		$user = Model("User")->getInfo($userID);
		if($user['status']) {
			Action('user.index')->loginSuccessUpdate($user);
		}
		if($this->withApp) {	// bindHtml会直接打印，故在此return
			return array(
				'code' => true,
				'data' => array('success' => true)
			);
		}
		return $this->bindHtml($type, $data, true, array('connect'));
	}

	/**
	 * 绑定（自动）注册
	 */
	private function bindRegist($type, $data){
		$typeList = array(
			'qq' 	 => 'qq',
			'weixin' => 'wx',
			'github' => 'gh',
		);
		// 判断昵称是否重复
		$data['nickName'] = $this->nickNameAuto($data['nickName']);
		// 1.写入用户主信息
		$param = array(
			'name'		=> $typeList[$type] . substr(guid(), 0, 10),
			'nickName'	=> $data['nickName'],
			'password'	=> 'K0d' . rand_string(5),
			'sex'		=> isset($data['sex']) ? $data['sex'] : 1
		);
		$res = Action("user.regist")->addUser($param);
		if (!$res['code']) return $res;
		// 2.更新账户名
		$data['userID'] = $res['info'];
		$update = array(
			'userID'	=> $data['userID'],
			'name'		=> strtoupper($typeList[$type]) . '1' . str_pad($data['userID'], 8, 0, STR_PAD_LEFT),
			'avatar'	=> $data['avatar'],
		);
		Model('User')->userEdit($update['userID'], $update);
		// 3.密码置为空
		Model('User')->metaSet($data['userID'],'passwordSalt','');
		Model('User')->where(array('userID' => $data['userID']))->save(array('password' => ''));
		// 4.写入用户meta信息
		if (!$this->bindSave($data, $data['userID'])) return array('code' => false, 'data' => LNG('user.bindUpdateError'));
		$this->addUser = true;
		return array('code' => true, 'data' => $data['userID']);
	}
	// 获取昵称
	private function nickNameAuto($nickName){
		$where = array('nickName' => array('like', $nickName.'%'));
		$cnt = Model('User')->where($where)->count();
		if(!$cnt) return $nickName;
		return $nickName . str_pad(($cnt + 1), 2, '0', STR_PAD_LEFT);
	}

	/**
	 * 第三方绑定返回信息-后端
	 * @param type $type
	 * @param type $data
	 */
	private function bindBack($type, $data) {
		$data['bind'] = true;
		// 绑定信息存储
		$userID = Session::get("kodUser.userID");
		if (!$ret = $this->bindSave($data, $userID, true)) {
			return $this->bindHtml($type, $data, false, array('bind', 'update_error'));
		}
		return $this->bindHtml($type, $data, true, array('bind'));
	}

	/**
	 * TODO api返回操作结果信息
	 * @param type $type	// qq|github|weixin
	 * @param type $succ	//
	 * @param type $act	// connect|bind|login
	 * @param type $msg		// sign_error|update_error|bind_others
	 * @return type
	 */
	private function bindInfo($type, $success, $msgData = array()) {
		$act = $msgData[0];
		$msg = isset($msgData[1]) ? $msgData[1] : '';
		if ($success) {
			return LNG('common.congrats') . $this->typeList[$type] . LNG('common.' . $act . 'Success');
		}
		$errTit = LNG('common.sorry') . $this->typeList[$type];
		if ($act == 'login') {
			return $errTit . LNG('common.loginError') . ';'.$this->typeList[$type] . LNG('user.thirdBindFirst');
		}
		// 2.2 尚未启用
		if ($act == 'invalid') {
			return $errTit . LNG('common.loginError') . ';' . LNG('user.userEnabled');
		}
		// 2.3 其他失败
		$errList = array(
			'sign_error'	 => LNG('user.bindSignError'),
			'update_error'	 => LNG('user.bindUpdateError'),
			'bind_others'	 => $this->typeList[$type] . LNG('user.bindOthers') . "[{$msgData[2]}]"
		);
		return $errTit . LNG('common.bindError') .';' . (isset($errList[$msg]) ? $errList[$msg] : $msg);
	}

	/**
	 * 
	 * @param type $type
	 * @param type $data
	 * @param type $success
	 * @param type $msgData
	 */
	private function bindHtml($type, $data, $success, $msgData) {
		$return = array(
			'type'		 => $type, // 绑定类型
			'typeTit'	 => $this->typeList[$type], // 绑定类型名称
			'success'	 => (int) $success, // 绑定结果
			'bind'		 => isset($data['bind']) ? $data['bind'] : false, // 是否已绑定
			'client'	 => (int) $data['client'], // 前后端
			'name'		 => isset($data['nickName']) ? $data['nickName'] : '',
			'avatar'	 => isset($data['avatar']) ? $data['avatar'] : '', // 头像
			'imgUrl'	 => './static/images/file_icon/icon_others/error.png', // 结果标识(头像orX)
			'title'		 => LNG('explorer.error'), // 结果标题
			'msg'		 => $this->bindInfo($type, $success, $msgData), // 结果说明
		);
		if ($success) {
			$return['title'] = LNG('explorer.success');
			$return['imgUrl'] = $data['avatar'];
		}
		return show_json($return);
	}

	/**
	 * 发送信息(验证码)-短信、邮件	当前只有个人设置绑定使用,暂时只记为绑定
	 */
	public function sendMsg() {
		$data = Input::getArray(array(
			'type'	 => array('check' => 'in', 'param' => array('email', 'phone')),
			'input'	 => array('check' => 'require'),
		));
		$type = $data['type'];

		// 检查图片验证码
		$checkCode = Input::get('checkCode', 'require', '');
		Action('user.setting')->checkImgCode($checkCode);

		// 1.1 判断邮箱是否已绑定-自己
		$userInfo = Session::get("kodUser");
		if ($userInfo[$data['type']] == $data['input']) {
			show_json(LNG('common.' . $type) . LNG('user.binded'), false);
		}
		// 1.2 判断邮箱是否已绑定-他人
		if ($res = Model('User')->userSearch(array($type => $data['input']), 'name,nickName')) {
			$typeTit = $type . ($type == 'phone' ? 'Number' : '');
			show_json(LNG('common.' . $typeTit) . LNG('common.error'), false);
			// $name = $res['nickName'] ? $res['nickName'] : $res['name'];
			// show_json(LNG('common.' . $type) . LNG('user.bindOthers') . "[{$name}]", false);
		}

		$data['source'] = 'bind';
		Action('user.setting')->checkMsgFreq($data);	// 消息发送频率检查
		// 2 发送邮件/短信
		$func = $type == 'email' ? 'sendEmail' : 'sendSms';
		$res = $this->$func($data['input'], $type.'_'.$data['source']);
		if (!$res['code']) {
			show_json(LNG('user.sendFail') . ': ' . $res['data'], false);
		}

		// 3. 存储验证码
		$param = array(
			'type' => 'setting',
			'input' => $data['input']
		);
		Action("user.setting")->checkMsgCode($type, $res['data'], $param, true);
		show_json(LNG('user.sendSuccess'), true);
	}

	/**
	 * 发送(验证码)邮件
	 * @param [type] $input
	 * @param [type] $action
	 * @return void
	 */
	public function sendEmail($input, $action) {
		$systemName = Model('SystemOption')->get('systemName');
		$data = array(
			'type'		=> 'email',
			'input'		=> $input,
			'action'	=> $action,
			'config'	=> array(
				'address'	=> $input,
				'subject'	=> "[{$systemName}]" . LNG('user.emailVerify'),
				'content'	=> array(
					'type' => 'code', 
					'data' => array('code' => rand_string(6))
				),
				'signature'	=> $systemName,
			)
		);
		return Action('user.msg')->send($data);
	}

	/**
	 * 发送(验证码)短信
	 * @param [type] $input
	 * @param [type] $action
	 * @return void
	 */
	public function sendSms($input, $action) {
		$data = array(
			'type'		=> 'sms',
			'input'		=> $input,
			'action'	=> $action
		);
		return Action('user.msg')->send($data);
	}

	/**
	 * 请求Kodapi服务器
	 * @param type $type
	 * @param type $data
	 * @return type
	 */
	public function apiRequest($type, $data = array()) {
		$kodid = md5(BASIC_PATH . Model('SystemOption')->get('systemPassword'));
		$post = array(
			'type'		 => $type,
			'kodid'		 => $kodid,
			'timestamp'	 => time(),
			'data'		 => is_array($data) ? json_encode($data) : $data
		);
		$post['sign'] = $this->makeSign($kodid, $post);
		$url = $this->config['settings']['kodApiServer'] . 'plugin/platform/';
		$response = url_request($url, 'GET', $post);
		if ($response['status']) {
			return json_decode($response['data'], true);
		}
		// Network error. Please check whether the server can access the external network.
		return array('code' => false, 'data' => 'network error.');
	}

	/**
	 * kodapi请求参数签名
	 * @param type $kodid
	 * @param type $post
	 * @return type
	 */
	private function makeSign($kodid, $post) {
		// 获取secret
		if (!$secret = Model('SystemOption')->get('systemSecret')) {
			// 本地没有,先去kodapi请求获取secret(此处请求secret以Kodid代替)
			if ($post['type'] != 'secret') {
				$secret = $this->apiSecret();
			} else {
				$secret = $kodid;
			}
		}
		ksort($post);
		$tmp = array();
		$post = stripslashes_deep($post);
		foreach ($post as $key => $value) {
			$tmp[] = $key . '=' . $value;
		}
		$md5 = md5(sha1(implode('&', $tmp) . $secret));
		return strtoupper($md5); //生成签名
	}

	/**
	 * 向api请求secret
	 * @return type
	 */
	private function apiSecret() {
		$res = $this->apiRequest('secret');
		if (!$res['code']) {
			$msg = 'Api secret error' . (!empty($res['data']) ? ': ' . $res['data'] : '');
			show_json($msg, false);
		}
		Model('SystemOption')->set('systemSecret', $res['data']);
		return $res['data'];
	}

	/**
	 * 请求kodapi url参数处理——前端第三方登录、后端绑定
	 */
	public function oauth() {
		$data = Input::getArray(array(
			'type'	 	=> array('check'   => 'require'),
			'action' 	=> array('check'   => 'require'),
			'state'		=> array('default' => 'open'),
			'client' 	=> array('default' => 1),
		));
		if (!isset($this->typeList[$data['type']])) {
			show_json(LNG('common.invalidParam'), false);
		}

		$client = isset($data['client']) ? "&client={$data['client']}" : "";
		$link = Input::get('link');
		$link = !$link ? APP_HOST . '#user/bindInfo' : $link;
		$post = array(
			"type"		 => $data['type'],
			'kodid'		 => md5(BASIC_PATH . Model('SystemOption')->get('systemPassword')),
			'timestamp'	 => time(),
			"data"		 => json_encode(array(
				'action' => $data['type'] . '_' . $data['action'],
				'link'	 => $link . $client,
				'isJson' => 1,	// 返回数据不使用序列化，此参数为兼容旧版
			))
		);
		$post['sign'] = $this->makeSign($post['kodid'], $post);

		// 获取微信appid
		$appId = ($data['type'] == 'weixin') ? $this->appid($data['state']) : '';
		show_json(http_build_query($post), true, $appId);
	}

	// 获取应用appid
	private function appid($state){
		$res = $this->apiRequest('appid', array('type' => 'weixin', 'state' => $state));
		if(!$res['code']) {
			$msg = LNG('user.bindWxConfigError') . '.' . $res['data'];
			show_json($msg, false);
		}
		return $res['data'];
	}

	/**
	 * 第三方账号解绑-后端
	 */
	public function unbind() {
		$type = Input::get('type','require', '');
		if(!isset($this->typeList[$type])){
			show_json(LNG('user.bindTypeError'), false);
		}
		$info = Session::get('kodUser');
		if($this->isEmptyPwd($info['userID'])) show_json(LNG('user.unbindWarning'), false);

		Model('User')->startTrans();
		$del = Model('User')->metaSet($info['userID'], $type . self::BIND_META_INFO, null);
		$del = Model('User')->metaSet($info['userID'], $type . self::BIND_META_UNIONID, null);
		Model('User')->commit();

		if ($del === false) {
			show_json(LNG('explorer.error'), false);
		}
		$this->updateUserInfo($info['userID']);
		show_json(LNG('explorer.success'), true);
	}

	/**
	 * 根据unionid判断对应账号是否已绑定
	 * @param type $key
	 * @param type $unionid
	 * @param type $client
	 * @return boolean
	 */
	private function isBind($key, $unionid, $client = 1) {
		// 根据metadata.unionid获取用户信息
		$user = Model('User')->getInfoByMeta($key . self::BIND_META_UNIONID, $unionid);
		if (empty($user)) return false;
		// 后端,要判断该微信/QQ是否已经绑定了其他账号
		// 通过绑定信息获取到的用户，不是当前登录用户，说明已绑定其他账号
		if (!$client) {
			if($user['userID'] != Session::get("kodUser.userID")) {
				return $user['nickName'] ? $user['nickName'] : $user['name'];
			}
			return false;
		}
		// 前端,用户存在,则直接登录
		if($user['status']){
			Action('user.index')->loginSuccessUpdate($user);
		}
		return array($user['status']);	// true
	}

	/**
	 * 第三方信息绑定保存
	 */
	private function bindSave($data, $userID, $back=false) {
		// 更新头像、meta信息
		if(!$userID) show_json('not login!',false);
		if($back) {
			Model("User")->userEdit($userID, array("avatar" => $data['avatar']));
		}
		$metadata = array(
			$data['type'] . self::BIND_META_UNIONID	 => $data['unionid'],
			$data['type'] . self::BIND_META_INFO	 => json_encode($data)
		);
		$ret = Model('User')->metaSet($userID, $metadata);
		if ($ret && !$data['client']) {
			$this->updateUserInfo($userID);	// 后端绑定，更新用户信息
		}
		return $ret;
	}

	// 更新userInfo缓存
	private function updateUserInfo($id) {
		Model('User')->cacheFunctionClear('getInfo',$id);
		Session::set('kodUser', Model('User')->getInfo($id));
	}

	/**
	 * 用户是否绑定
	 */
	public function bindMetaInfo(){
		$userInfo = Session::get('kodUser');
		$metaInfo = $userInfo['metaInfo'];
		$bindInfo = array();
		$bindList = array('weixinUnionid', 'qqUnionid', 'githubUnionid');
		foreach($bindList as $bind){
			$type = str_replace('Unionid', '', $bind);
			$bindInfo[$type . 'Bind'] = isset($metaInfo[$bind]) ? 1 : 0;
		}
		// 密码是否为空
		$data = array('bind' => $bindInfo, 'emptyPwd' => 0);
		if(array_sum($bindInfo)){
			$data['emptyPwd'] = (int) $this->isEmptyPwd($userInfo['userID']);
		}
		show_json($data);
	}
	private function isEmptyPwd($userID){
		$info = Model('User')->getInfoSimple($userID);
		return empty($info['password']);
	}
}
