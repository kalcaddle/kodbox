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
	
	public function checkAllow($regist=true){
		if (!isset($this->regOpen)) {
			$regist = Model("SystemOption")->get("regist");
			$this->regOpen = $regist['openRegist'] == '1';
		}
		if ($this->regOpen) return true;
		$msg = $regist ? LNG('user.registNotAllow') : LNG('user.deregistNotAllow');
		show_json($msg,false);
	}
	
	/**
	 * 发送验证码——注册、找回密码
	 */
	public function sendMsgCode() {
		$data = Input::getArray(array(
			'type'		=> array('check' => 'in', 'param' => array('email', 'phone')),
			'input'		=> array('check' => 'require'),
			'source'	=> array('check' => 'require'),
			// 'checkCode'	=> array('check' => 'require'),
		));
		// 非后端调用（前端请求）需要图形验证码
		if (!$GLOBALS['BACKEND_CALL_SENDMSGCODE']) {
			$data['checkCode'] = Input::get('checkCode', 'require');
		}
		$type	= $data['type'];
		$input	= $data['input'];
		$source	= $data['source'];

		// 个人设置、注册、找回密码、注销
		if(!in_array($source, array('setting', 'regist', 'findpwd', 'deregist'))){
			show_json(LNG('common.invalidRequest'), false);
		}
		if (!Input::check($input, $type)) {
			$text = $type . ($type == 'phone' ? 'Number' : '');
			show_json(LNG('common.invalid') . LNG('common.' . $text), false);
		}
		// 图形验证码
		if (!$GLOBALS['BACKEND_CALL_SENDMSGCODE']) {
			Action('user.setting')->checkImgCode($data['checkCode']);
		} else {
			unset($GLOBALS['BACKEND_CALL_SENDMSGCODE']);
		}

		// 1.1前端注册检测
		if ($source == 'regist') {
			$this->checkAllow();
			$this->userRegistCheck($data);
		}
		// 1.2找回密码(前端找回、后端重置)检测
		if ($source == 'findpwd') {
			$this->userFindPwdCheck($data);
		}

		// 2.发送邮件/短信
		Action('user.setting')->checkMsgFreq($data);	// 消息发送频率检查
		if ($type == 'email') {
			$res = Action('user.bind')->sendEmail($input, $type.'_'.$source);
		} else {
			$res = Action('user.bind')->sendSms($input, $type.'_'.$source);
		}
		if (!$res['code']) {
			show_json(LNG('user.sendFail') . ': ' . $res['data'], false);
		}
		Action('user.setting')->checkMsgFreq($data, true);

		// 3.存储验证码
		$param = array(
			'type'	=> $source,
			'input' => $input
		);
		Action('user.setting')->checkMsgCode($type, $res['data'], $param, true);
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
		$text = $data['type'] . ($data['type'] == 'phone' ? 'Number' : '');
		// 前端找回密码
		if ($userID == '0') {
			$where = array($data['type'] => $data['input']);
			if (!Model('User')->userSearch($where)) {
				show_json(LNG('common.' . $text) . LNG('common.error'), false);
				// show_json(LNG('common.' . $data['type']) . LNG('user.notRegist'), false);
			}
			return;
		}
		// 后端重置密码
		$userInfo = Model('User')->getInfoSimple($userID);
		if (empty($userInfo)) {
			show_json(LNG('common.illegalRequest'), false);
		}
		if(!$userInfo[$data['type']]) {
			show_json(LNG('common.' . $text) . LNG('common.error'), false);
			// show_json(LNG('common.' . $text) . LNG('user.notBind'), false);
		}
		// 提交的邮箱、手机和用户信息中的不匹配
		if ($userInfo[$data['type']] != $data['input']) {
			show_json(sprintf(LNG('user.inputNotMatch'), LNG('common.' . $text)), false);
		}
	}

	/**
	 * 注册
	 */
	public function regist() {
		$this->checkAllow();
		$data = Input::getArray(array(
			'type'		 => array('check' => 'in', 'param' => array('email', 'phone')),
			'input'		 => array('check' => 'require'),
			'name'	 	 => array('default' => null),
			'nickName'	 => array('default' => null),
			'password'	 => array('check' => 'require'),
			'msgCode' 	 => array('check' => 'require'),	// 消息验证码
		));
		foreach ($data as $k => $val) {
			$data[$k] = rawurldecode($val);
		}
		if(empty($data['name'])) $data['name'] = $data['input'];	// 兼容app注册

		// 邮箱/手机号校验
		if (!Input::check($data['input'], $data['type'])) {
			$text = $data['type'] . ($data['type'] == 'phone' ? 'Number' : '');
			show_json(LNG('common.invalid') . LNG('common.' . $text), false);
		}
		// 消息验证码校验
		if(!$msgCode = Input::get('msgCode')){
			show_json(LNG('user.inputVerifyCode'), false);
		}
		$param = array(
			'type' => 'regist',
			'input' => $data['input']
		);
		Action('user.setting')->checkMsgCode($data['type'], $msgCode, $param);

		// 密码校验
		$data['password'] = KodUser::parsePass($data['password']);
		if( !ActionCall('filter.userCheck.password',$data['password']) ){
			return ActionCall('filter.userCheck.passwordTips');
		}
		$this->addUser($data);
	}
	
	

	/**
	 * 新增/注册用户
	 * @param type $data
	 * @return type
	 */
	public function addUser($data) {
		$this->checkAllow();
		$name = $data['name'];
		$nickName = trim($data['nickName']);
		$nickName = $nickName ? $nickName : '';

		$bindRegist = true;	// 绑定注册
		if (isset($data['type']) && isset($data['input'])) {
			$bindRegist = false;
			if (Model('User')->userSearch(array($data['type'] => $data['input'])) ) {
				$text = $data['type'] . ($data['type'] == 'phone' ? 'Number' : '');
				return show_json(LNG('common.' . $text) . LNG('common.error'), false);
				// return show_json(LNG('common.' . $data['type']) . LNG('user.registed'), false);
			}
		}
		// 3.1用户基础信息保存
		$regist = Model("SystemOption")->get("regist");
		$this->in = array(
			'name'		 => $name,
			'nickName'	 => $nickName,
			'password'	 => $data['password'],
			'roleID'	 => $regist['roleID'],
			'email'		 => isset($data['email']) ? $data['email'] : '',
			'phone'		 => isset($data['phone']) ? $data['phone'] : '',
			'avatar'	 => isset($data['avatar']) ? $data['avatar'] : '',
			'sex'	 	 => isset($data['sex']) ? $data['sex'] : 1,
			'sizeMax'	 => floatval($regist['sizeMax']), //M
			'status'	 => $regist['checkRegist'] == 1 ? 0 : 1, //0禁用；1启用 等待审核可以改为-1
			'groupInfo'  => $regist['groupInfo']
		);
		!$bindRegist && $this->in[$data['type']] = $data['input'];

		$res = ActionCallHook('admin.member.add');
		// 绑定注册，直接返回新增结果
		if ($bindRegist) return $res;	// show_json(true, true, userID)
		if(!$res['code']) {
			$msg = $res['data'] ? $res['data'] : LNG('explorer.error');
			show_json($msg, false);
		}

		$code = true;
		$msg = LNG('user.registSuccess');
		if(!$this->in['status']){
			$code = ERROR_CODE_USER_INVALID;
			$msg .= LNG('user.waitCheck');
		}
		show_json($msg, $code);
	}

	/**
	 * 注销
	 * 1.确认注销——发送验证码
	 * 2.输入验证码，提交注销
	 * @return void
	 */
	public function deregist(){
		KodUser::checkLogin();
		$this->checkAllow(false);	// 未开启注册

		// 1.验证用户资格
		$userInfo = Session::get("kodUser");
		if (!$userInfo) {show_json(LNG('oauth.main.notLogin'), false);}
		if ($userInfo['userID'] == '1') {
			show_json('系统管理员不支持此操作！', false);
		}
		if (!$userInfo['email']) {
			show_json('请先绑定邮箱，用于验证码获取！', false);
		}

		// 2.注销用户
		// 2.1 发送验证码
		if (!isset($this->in['code'])) {
			$this->in = array(
				'type'		=> 'email',
				'input'		=> $userInfo['email'],
				'source'	=> 'deregist',
			);
			$GLOBALS['BACKEND_CALL_SENDMSGCODE'] = true;
			ActionCallResult("user.regist.sendMsgCode",function(&$res){
				if ($res['code']) {
					$res['data'] = LNG('explorer.safe.sendMailTips') . ', ' . LNG('explorer.safe.sendMailGet');
				}
				return $res;
			});
		}

		// 2.2 验证验证码，注销用户
		$data = array(
			'type'	=> 'email',
			'input'	=> $userInfo['email'],
		);
		// 消息验证码校验
		if(!$code = Input::get('code')){
			show_json(LNG('user.inputVerifyCode'), false);
		}
		$param = array(
			'type'	=> 'deregist',
			'input' => $data['input']
		);
		Action('user.setting')->checkMsgCode($data['type'], $code, $param);

		// 删除用户
		$this->in['userID'] = $userInfo['userID'];
		Action("admin.member")->remove();
	}

}
