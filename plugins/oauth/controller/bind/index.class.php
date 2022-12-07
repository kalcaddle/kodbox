<?php 
/**
 * 第三方账号绑定
 */
class oauthBindIndex extends Controller {

	const BIND_META_INFO = 'Info';
	const BIND_META_UNIONID = 'Unionid';
	public function __construct() {
		parent::__construct();
		$this->pluginName = 'oauthPlugin';
		$this->typeList = array(
			'qq'		=> array('type' => 'qq', 'title' => 'QQ'),
			'weixin'	=> array('type' => 'wx', 'title' => LNG('common.wechat')),
			'github'	=> array('type' => 'gh', 'title' => 'GitHub'),
			'google'	=> array('type' => 'gg', 'title' => 'Google'),
			'facebook'	=> array('type' => 'fb', 'title' => 'Facebook'),
		);
		$this->checkAuth();
    }

	// 检查是否有用户编辑权限
	private function checkAuth(){
		if(!Session::get('kodUser')) return;
		$check = array(
			'bind',
			'bindApi',
			'unbind',
			'oauth',
			'bindWithApp',
		);
		$action = Input::get('method', 'require');
		$action = strtolower($action);
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
		if (!$data && is_string($input['data'])) {
			$msg = LNG('common.invalidParam');
			if (isset($this->in['info']) && $this->in['info'] == '40003') {
				Model('SystemOption')->set('systemSecret', '');
				$msg = 'sign_error';
			}
			return $this->bindHtml($type, $data, false, array('bind', $msg));
		}
		if (empty($data['unionid'])) {
			$msg = isset($data['data']) && is_string($data['data']) ? $data['data'] : LNG('explorer.dataError');
			return $this->bindHtml($type, $data, false, array('bind', $msg));
		}
		return $this->bindDisplay($type, $data);
	}

	/**
	 * 第三方绑定返回信息
	 * @param type $type	qq|github|weixin|google|facebook
	 * @param type $data
	 */
	public function bindDisplay($type, $data) {
		$this->updateAvatar($data);
		$unionid = $data['unionid'];
		$client = Input::get('client','require',1); // 前后端
		$data['client'] = $client;

		// 判断是否已绑定
		$bind = $this->isBind($type, $unionid, $client);
		if (!$bind) {
			// 未绑定，前、后端分别处理
			$function = $client ? 'bindFront' : 'bindBack';
			return $this->$function($type, $data);
		}
		// 已绑定，前端：直接跳转登录；后端：已绑定(别的账号)，提示绑定失败
		if ($client) {
			$data['bind'] = true;
			if(is_array($bind) && $bind[0]){
				$success = true;
				$msg = array('login');		// 已绑定且已启用，直接登录
			}else{
				$msg = array('invalid');	// 未启用
				$success = false;
			}
		} else {
			$data['bind'] = false; // 可不传
			$msg = array('bind', 'bind_others', $bind);
			$success = false;	// $bind=true，说明已绑定其他账号——update:bind=name
		}
		return $this->bindHtml($type, $data, $success, $msg);
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
		// 后端，要判断是否已经绑定了其他账号
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
	 * 
	 * @param type $type
	 * @param type $data
	 * @param type $success
	 * @param type $msg
	 */
	private function bindHtml($type, $data, $success, $msg) {
		$return = array(
			'type'		 => $type, // 绑定类型
			'success'	 => (int) $success, // 绑定结果
			'bind'		 => isset($data['bind']) ? $data['bind'] : false, // 是否已绑定
			'client'	 => (int) $data['client'], // 前后端
			'name'		 => isset($data['nickName']) ? $data['nickName'] : '',
			'avatar'	 => isset($data['avatar']) ? $data['avatar'] : '', // 头像
			'title'		 => $success ? LNG('explorer.success') : LNG('explorer.error'), // 结果标题
			'msg'		 => $this->_bindInfo($type, $success, $msg), // 结果说明
		);
		$this->updateAvatar($return);
		if ($return['bind']) Hook::trigger('user.bind.log', 'bind', $return);
		return show_json($return);
	}

	/**
	 * api返回操作结果信息
	 * @param type $type	// qq|github|weixin
	 * @param type $succ	//
	 * @param type $msg		// sign_error|update_error|bind_others
	 * @return type
	 */
	private function _bindInfo($type, $success, $msg = array()) {
		$action = $msg[0];	// connect|bind|login
		$title	= $this->typeList[$type]['title'];
		if ($success) {
			return LNG('common.congrats') . $title . LNG('common.' . $action . 'Success');
		}
		$errTitle = LNG('common.sorry') . $title;
		if ($action == 'login') {
			return $errTitle . LNG('common.loginError') . ';'.$title . LNG('user.thirdBindFirst');
		}
		// 2.2 尚未启用
		if ($action == 'invalid') {
			return $errTitle . LNG('common.loginError') . ';' . LNG('user.userEnabled');
		}
		// 2.3 其他失败
		$errList = array(
			'sign_error'	 => LNG('user.bindSignError'),
			'update_error'	 => LNG('user.bindUpdateError'),
			'bind_others'	 => $title . LNG('user.bindOthers') . (isset($msg[2]) ? "[{$msg[2]}]" : '')
		);
		$msgKey = isset($msg[1]) ? $msg[1] : '';
		return $errTitle . LNG('common.bindError') .';' . (isset($errList[$msgKey]) ? $errList[$msgKey] : $msgKey);
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
		// 3.自动登录
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
		$data['bind'] = true;
		return $this->bindHtml($type, $data, true, array('connect'));
	}

	/**
	 * 绑定（自动）注册
	 */
	private function bindRegist($type, $data){
		$prev = $this->typeList[$type]['type'];

		// 判断昵称是否重复
		$data['nickName'] = $this->nickNameAuto($data['nickName']);

		// 1.写入用户主信息
		$param = array(
			'name'		=> $prev . substr(guid(), 0, 10),
			'nickName'	=> $data['nickName'],
			'password'	=> 'K0d' . rand_string(5),
			'sex'		=> isset($data['sex']) ? $data['sex'] : 1,
			'avatar'	=> $data['avatar'],
		);
		if (isset($data['email'])) $param['email'] = $data['email'];
		$res = Action("user.regist")->addUser($param);
		if (!$res['code']) return $res;
		$userID = $res['info'];

		// 2.更新账户名、密码置为空
		$update = array('name' => strtoupper($prev) . '1' . str_pad($userID, 8, 0, STR_PAD_LEFT));
		Model('User')->where(array("userID"=>$userID))->save(array('password'=>''));// 密码置空
		Model('User')->userEdit($userID, $update);
		Model('User')->metaSet($userID, 'passwordSalt','');

		// 3.写入用户meta信息
		if (!$this->bindSave($data, $userID)) {
			return array('code' => false, 'data' => LNG('user.bindUpdateError'));
		}
		$this->addUser = true;
		return array('code' => true, 'data' => $userID);
	}
	// 获取昵称
	private function nickNameAuto($nickName){
		if (!$nickName) return '';
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
		$user = Session::get("kodUser");
		if (!$ret = $this->bindSave($data, $user['userID'])) {
			$data['bind'] = false;
			return $this->bindHtml($type, $data, false, array('bind', 'update_error'));
		}
		$update = array("avatar" => $data['avatar']);
		if (isset($data['email']) && empty($user['email'])) {
			$update['email'] = $data['email'];
		}
		Model("User")->userEdit($user['userID'], $update);
		return $this->bindHtml($type, $data, true, array('bind'));
	}

	/**
	 * 第三方信息绑定保存
	 */
	private function bindSave($data, $userID) {
		if(!$userID) show_json(LNG('oauth.main.notLogin'),false);
		// 更新meta信息
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
		// $link = !$link ? APP_HOST . '#user/bindInfo' : $link;
		$link = !$link ? APP_HOST . '?plugin/oauth/callback' : $link;
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

		$appId = '';
		// 微信授权分公众号（微信内）和开放平台，对应appid，需要分别获取
		if ($data['type'] == 'weixin') {
			$data = array('type' => 'weixin', 'state' => $data['state']);
			$res  = $this->apiRequest('appid', $data);
			if (!$res['code']) {
				show_json(LNG('user.bindWxConfigError').'.'.$res['data'], false);
			}
			$appId = $res['data'];
		}
		show_json(http_build_query($post), true, $appId);
	}

	/**
	 * app端后台绑定
	 */
	public function bind(){
		$data = Input::getArray(array(
			'type'		=> array('check' => 'in', 'param' => array_keys($this->typeList)),
			'openid' 	=> array('check' => 'require'),
			'unionid' 	=> array('check' => 'require'),
			'nickName' 	=> array('check' => 'require', 'default' => ''),
			'sex' 		=> array('check' => 'require', 'default' => 1),
			'avatar' 	=> array('check' => 'require', 'default' => ''),
			'email' 	=> array('default' => null),
		));
		$this->in['client'] = 0;
		$res = ActionCallHook($this->pluginName.'.bind.index.bindDisplay', $data['type'], $data);
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
		Hook::trigger('user.bind.log', 'bind', $data);
		show_json(LNG('common.bindSuccess'));
	}

	/**
	 * app前端绑定
	 */
	public function bindWithApp($data){
		$this->withApp = true;
		if(empty($data['openid'])) {
			show_json(LNG('common.invalidParam') . ':openid', false);
		}
		if(empty($data['unionid'])) {
			show_json(LNG('common.invalidParam') . ':unionid', false);
		}
		$res = ActionCallHook($this->pluginName.'.bind.index.bindDisplay', $data['type'], $data);
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
			if ($code) Hook::trigger('user.bind.log', 'bind', $data);
			show_json($msg, $code);
		}
		Hook::trigger('user.bind.log', 'bind', $data);
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
			'avatar'	=> isset($data['avatar']) ? $data['avatar'] : '',
			'sex'		=> isset($data['sex']) ? $data['sex'] : 1,
			'openid'	=> $data['openid'],
			'unionid'	=> $data['unionid'],
		);
		$this->updateAvatar($param);
		$this->apiRequest('bind', $param);	// 这里不管成功与否，登录信息已存储
	}

	// 头像地址替换
	private function updateAvatar(&$data){
		if (empty($data['avatar'])) return;
		if (substr($data['avatar'],0,7) == 'http://') {
			$data['avatar'] = 'https://'.substr($data['avatar'], 7);
		}
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
		Hook::trigger('user.bind.log', 'unbind', array('type' => $type));
		show_json(LNG('explorer.success'), true);
	}
	// 更新userInfo缓存
	private function updateUserInfo($id) {
		Model('User')->cacheFunctionClear('getInfo',$id);
		Session::set('kodUser', Model('User')->getInfo($id));
	}

	/**
	 * 请求Kodapi服务器
	 * @param type $type
	 * @param type $data
	 * @return type
	 */
	private function apiRequest($type, $data = array()) {
		return Action('user.bind')->apiRequest($type, $data);
	}
	/**
	 * kodapi请求参数签名
	 * @param type $kodid
	 * @param type $post
	 * @return type
	 */
	private function makeSign($kodid, $post) {
		return Action('user.bind')->makeSign($kodid, $post);
	}

	/**
	 * 用户绑定信息
	 */
	public function bindInfo(){
		$userInfo = Session::get('kodUser');
		$metaInfo = $userInfo['metaInfo'];
		$bindInfo = array();
		foreach ($this->typeList as $type => $value) {
			$bindInfo[$type.'Bind'] = isset($metaInfo[$type . 'Unionid']) ? 1 : 0;
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