<?php

/*
 * @link http://kodcloud.com/
 * @author warlee | e-mail:kodcloud@qq.com
 * @copyright warlee 2014.(Shanghai)Co.,Ltd
 * @license http://kodcloud.com/tools/license/license.txt
 */

class userRegist extends Controller {
	public function __construct() {
		parent::__construct();
	}
	
	public function checkAllow(){
		$regist = Model("SystemOption")->get("regist");
		if($regist['openRegist'] != '1'){
			show_json("未开启注册,请联系管理员!",false);
		}
	}
	
	/**
	 * 发送验证码——注册、找回密码
	 */
	public function sendMsgCode() {
		$data = Input::getArray(array(
			'type'		=> array('check' => 'require'),
			'input'		=> array('check' => 'require'),
			'source'	=> array('check' => 'require'),
			'checkCode'	=> array('check' => 'require'),
		));
		$typeList = array('setting', 'regist', 'findpwd');	// 个人设置、注册、找回密码
		if(!in_array($data['source'], $typeList)){
			show_json(LNG('common.invalidRequest'), false);
		}
		if (!Input::check($data['input'], $data['type'])) {
			$type = $data['type'] . ($data['type'] == 'phone' ? 'Number' : '');
			show_json(LNG('common.invalid') . LNG('common.' . $type), false);
		}
		if (Session::get('checkCode') != $data['checkCode']) {
			show_json(LNG('user.codeError'), false);
		}

		// 1.1前端注册检测
		if ($data['source'] == 'regist') {
			$this->checkAllow();
			$this->userRegistCheck($data);
		}
		// 1.2找回密码(前端找回、后端重置)检测
		if ($data['source'] == 'findpwd') {
			$this->userFindPwdCheck($data);
		}

		// 2.发送邮件/短信
		$func = $data['type'] == 'email' ? 'sendEmail' : 'sendSms';
		$res = Action('user.bind')->$func($data['input'], "{$data['type']}_{$data['source']}");
		if (!$res['code']) {
			show_json(LNG('user.sendFail') . ': ' . $res['data'], false);
		}

		// 3.存储验证码
		$param = array(
			'type' => $data['source'],
			'input' => $data['input']
		);
		$this->msgCodeExec($data['type'], $res['data'], $param, true);
		show_json(LNG('user.sendSuccess'), true);
	}

	/**
	 * 判断号码、邮箱是否已注册
	 * @param type $data
	 */
	private function userRegistCheck($data) {
		$where = array($data['type'] => $data['input']);
		if (Model('User')->userSearch($where)) {
			show_json(LNG('common.' . $data['type']) . LNG('user.registed'), false);
		}
	}

	/**
	 * 判断账号(及图片验证码-前端)是否有效-找回密码
	 * @param type $data
	 * @return type
	 */
	private function userFindPwdCheck($data) {
		$userID = Input::get('userID', 'require', '0');
		// 前端找回密码
		if ($userID == '0') {
			$where = array($data['type'] => $data['input']);
			if (!Model('User')->userSearch($where)) {
				show_json(LNG('common.' . $data['type']) . LNG('user.notRegist'), false);
			}
			return;
		}
		// 后端重置密码
		$userInfo = Model('User')->getInfoSimple($userID);
		if (empty($userInfo)) {
			show_json(LNG('common.illegalRequest'), false);
		}
		$type = $data['type'] . ($data['type'] == 'phone' ? 'Number' : '');
		if(!$userInfo[$data['type']]) {
			show_json(LNG('common.' . $type) . LNG('user.notBind'), false);
		}
		// 提交的邮箱、手机和用户信息中的不匹配
		if ($userInfo[$data['type']] != $data['input']) {
			show_json(sprintf(LNG('user.inputNotMatch'), LNG('common.' . $type)), false);
		}
	}

	/**
	 * 注册
	 */
	public function regist() {
		$this->checkAllow();
		$data = Input::getArray(array(
			'type'		 => array('check' => 'require'),
			'input'		 => array('check' => 'require'),
			'name'	 	 => array('default' => null),
			'nickName'	 => array('default' => null),
			'password'	 => array('check' => 'require'),
			'checkCode'	 => array('check' => 'require'),	// 图片验证码
			'msgCode' 	 => array('check' => 'require'),	// 消息验证码
		));
		foreach ($data as $k => $val) {
			$data[$k] = rawurldecode($val);
		}
		if(empty($data['name'])) $data['name'] = $data['input'];	// 兼容app注册
		// 图片验证码校验
		if (Session::get('checkCode') != $data['checkCode']) {
			show_json(LNG('user.codeError'), false);
		}
		// 邮箱/手机号校验
		if (!Input::check($data['input'], $data['type'])) {
			$type = $data['type'] . ($data['type'] == 'phone' ? 'Number' : '');
			show_json(LNG('common.invalid') . LNG('common.' . $type), false);
		}
		// 消息验证码校验
		if(!$msgCode = Input::get('msgCode')){
			show_json(LNG('user.inputVerifyCode'), false);
		}
		$param = array(
			'type' => 'regist',
			'input' => $data['input']
		);
		$this->msgCodeExec($data['type'], $msgCode, $param);

		// 密码校验
		$salt = Input::get('salt',null, 0);
		$password = $salt == 1 ? Action('user.setting')->decodePwd($data['password']) : $data['password'];
		$data['password'] = rawurldecode($password);
		if( !ActionCall('filter.userCheck.password',$data['password']) ){
			return ActionCall('filter.userCheck.passwordTips');
		}
		$this->addUser($data);
	}
	
	

	/**
	 * 新增/注册用户
	 * @param type $param
	 * @return type
	 */
	public function addUser($param) {
		$name = $param['name'];
		$nickName = trim($param['nickName']);
		$nickName = $nickName ? $nickName : '';

		$bindRegist = true;	// 绑定注册
		if (isset($param['type']) && isset($param['input'])) {
			$bindRegist = false;
			if (Model('User')->userSearch(array($param['type'] => $param['input'])) ) {
				return show_json(LNG('common.' . $param['type']) . LNG('user.registed'), false);
			}
		}
		// 3.1用户基础信息保存
		$regist = Model("SystemOption")->get("regist");
		$this->in = array(
			'name'		 => $name,
			'nickName'	 => $nickName,
			'password'	 => $param['password'],
			'roleID'	 => $regist['roleID'],
			'email'		 => '',
			'phone'		 => '',
			'sizeMax'	 => floatval($regist['sizeMax']), //M
			'status'	 => $regist['checkRegist'] == 1 ? 0 : 1, //0禁用；1启用 等待审核可以改为-1
			'groupInfo'  => $regist['groupInfo']
		);
		!$bindRegist && $this->in[$param['type']] = $param['input'];

        if(!defined('USER_ID')) define('USER_ID', 0);
		$res = ActionCallHook('admin.member.add');
		// 绑定注册，直接返回新增结果
		if ($bindRegist) return $res;	// show_json(true, true, userID)
		if(!$res['code']) {
			$msg = $res['data'] ? $res['data'] : LNG('explorer.error');
			show_json($msg, false);
		}

		Session::remove('checkCode');
		$code = true;
		$msg = LNG('user.registSuccess');
		if(!$this->in['status']){
			$code = ERROR_CODE_USER_INVALID;
			$msg .= LNG('user.waitCheck');
		}
		show_json($msg, $code);
	}

	/**
	 * 手机、邮箱验证码存储、验证
	 * @param type $type
	 * @param type $code
	 * @param type $data	{type: [source], input: ''}
	 * @param type $set	首次存储验证码(检测错误次数)
	 * @return type
	 */
	public function msgCodeExec($type, $code, $data = array(), $set = false) {
		$typeList = array('setting', 'regist', 'findpwd');	// 个人设置、注册、找回密码
		if(!in_array($data['type'], $typeList)){
			show_json(LNG('common.invalid') . LNG('explorer.file.action'), false);
		}
		$name = md5("{$data['type']}_{$type}_{$data['input']}_msgcode");
		// 1. 存储
		if ($set) {
			$sess = array(
				'code'	 => $code,
				'cnt'	 => 0,
				'time'	 => time()
			);
			return Session::set($name, $sess);
		}
		// 2. 验证
		$type = $type == 'phone' ? 'sms' : $type;
		if (!$sess = Session::get($name)) {
			$msg = LNG('common.invalid') . LNG('common.' . $type) . LNG('user.code');
			show_json($msg, false);
		}
		// 超过20分钟
		if ($sess['time'] + 60 * 20 < time()) {
			Session::remove($name);
			show_json(LNG('common.' . $type) . LNG('user.codeExpired'), false);
		}
		// 错误次数超过10次——错误次数过多，锁定一段时间
		if ($sess['cnt'] > 10) {
			Session::remove($name);
			show_json(LNG('common.' . $type) . LNG('user.codeErrorTooMany'), false);
		}
		if (strtolower($sess['code']) != strtolower($code)) {
			$sess['cnt'] ++;
			Session::set($name, $sess);
			show_json(LNG('common.' . $type) . LNG('user.codeError'), false);
		}
		Session::remove($name);
	}
}
