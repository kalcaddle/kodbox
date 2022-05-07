<?php

/*
 * @link http://kodcloud.com/
 * @author warlee | e-mail:kodcloud@qq.com
 * @copyright warlee 2014.(Shanghai)Co.,Ltd
 * @license http://kodcloud.com/tools/license/license.txt
 */

class userSetting extends Controller {
	public $user;
	public function __construct() {
		parent::__construct();
		$this->user = Session::get('kodUser');
		$this->model = Model('User');
		$this->imageExt = array('png','jpg','jpeg','gif','webp','bmp','ico');
	}

	/**
	 * 参数设置
	 * 可以同时修改多个：key=a,b,c&value=1,2,3
	 * 防xss 做过滤
	 */
	public function setConfig() {
		$optionKey = array_keys($this->config['settingDefault']);
		$data = Input::getArray(array(
			"key"	=> array("check"=>"in","param"=>$optionKey),
			"value"	=> array("check"=>"require"),
		));
		Model('UserOption')->set($data['key'],$data['value']);
		show_json(LNG('explorer.settingSuccess'));
	}
	public function getConfig(){
	}

	/**
	 * 个人中心-账号设置保存
	 */
	public function setUserInfo() {
		$limit = array('nickName', 'email', 'phone', 'password');
		$data = Input::getArray(array(
			"type"		 => array("check" => "in", "param" => $limit),
			"msgCode"	 => array("default" => null),
		));
		if($data['type'] != 'password') {
			$input = Input::get('input','require');
			$input = trim(rawurldecode($input));
		}
		$input = html2txt($input);

		$userID = $this->user['userID'];
		if(in_array($data['type'], array('email', 'phone'))){
			if($input == $this->user[$data['type']]){
				show_json(LNG('common.' . $data['type']) . LNG('user.binded'), false);
			}
		}
		// 邮件、手机验证码校验
		if (isset($data['msgCode'])) {
			$this->userMsgCheck($input, $data);
		}
		// 昵称校验——更新时校验
		// 密码校验
		if ($data['type'] == 'password') {
			$input = $this->userPwdCheck($data);
		}

		// 更新用户信息
		$res = $this->model->userEdit($userID, array($data['type'] => $input));
		if ($res <= 0) {
			$msg = $this->model->errorLang($res);
			show_json(($msg ? $msg : LNG('explorer.error')), false);
		}
		$userInfo = Model('User')->getInfo($userID);
		Cache::set('kodUser', $userInfo);
		show_json(LNG('explorer.success'), true, $userInfo);
	}

	/**
	 * (图片)验证码校验
	 * @param type $code
	 */
	public function checkImgCode($code){
		$checkCode = Session::get('checkCode');
		Session::remove('checkCode');
		if (!$checkCode || strtolower($checkCode) !== strtolower($code)) {
			show_json(LNG('user.codeError'), false, ERROR_IMG_CODE);
		}
	}

	/**
	 * 手机、邮箱验证码存储、验证
	 * @param type $type	email、phone
	 * @param type $code
	 * @param type $data	{type: [source], input: ''}
	 * @param type $set	首次存储验证码(检测错误次数)
	 * @return type
	 */
	public function checkMsgCode($type, $code, $data = array(), $set = false) {
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
		if (($sess['time'] + 60 * 20) < time()) {
			Session::remove($name);
			show_json(LNG('common.' . $type) . LNG('user.codeExpired'), false);
		}
		// 错误次数过多，锁定一段时间——没有锁定，重新获取
		if ($sess['cnt'] >= 10) {
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

	/**
	 * 消息发送频率检查
	 * [type/input/source]
	 * @param [type] $data
	 * @return void
	 */
	public function checkMsgFreq($data){
		$name = md5("{$data['type']}_{$data['input']}_{$data['source']}_msgtime");
		$hours = 12;	// 缓存12小时
		if(!$cache = Cache::get($name)) {
			$cache = array('time' => time(), 'cnt' => 1);
			return Cache::set($name, $cache, 3600*$hours);
		}
		$tt = $data['type'] == 'email' ? 60 : 90;
		if(($cache['time'] + $tt) > time()) {
			show_json(LNG('user.codeErrorFreq'), false);
		}
		if($cache['cnt'] >= 10) {
			show_json(sprintf(LNG('user.codeErrorCnt'), $hours), false);
		}
		$cache['cnt']++;
		$cache['time'] = time();
		Cache::set($name, $cache, 3600*$hours);
	}

	/**
	 * (短信、邮箱)验证码校验
	 * @param type $input
	 * @param type $data
	 */
	private function userMsgCheck($input, $data) {
		$type = $data['type'];
		// 判断邮箱、手机号是否已被绑定
		if($this->user[$type] == $input) return;

		$where = array($type=> $input);
		if ($res = Model('User')->userSearch($where, 'name,nickName')) {
			$typeTit = $type . ($type == 'phone' ? 'Number' : '');
			show_json(LNG('common.' . $typeTit) . LNG('common.error'), false);
			// $name = $res['nickName'] ? $res['nickName'] : $res['name'];
			// show_json(LNG('common.' . $type) . LNG('user.bindOthers') . "[{$name}]", false);
		}
		// 判断邮箱、短信验证码
		$param = array(
			'type' => 'setting',
			'input' => $input
		);
		$this->checkMsgCode($type, $data['msgCode'], $param);
	}

	/**
	 * 修改密码检测
	 * @param type $data
	 * @return type
	 */
	private function userPwdCheck($data) {
		$newpwd = Input::get('newpwd','require');
		$salt = Input::get('salt',null, 0);
		// 密码为空则不检查原密码
		$info = Model('User')->getInfoSimple($this->user['userID']);
		if(isset($info['password']) && empty($info['password'])) {
			return !$salt ? $newpwd : $this->decodePwd($newpwd);
		} 
		$oldpwd = Input::get('oldpwd','require');
		if ($salt == 1) {
			$oldpwd = $this->decodePwd($oldpwd);
			$newpwd = $this->decodePwd($newpwd);
		}
		if (!$this->model->userPasswordCheck($this->user['userID'], $oldpwd)) {
			show_json(LNG('user.oldPwdError'), false);
		}
		if( !ActionCall('filter.userCheck.password',$newpwd) ){
			return ActionCall('filter.userCheck.passwordTips');
		}
		return $newpwd;
	}

	/**
	 * 解析密码
	 */
	public function decodePwd($password) {
		$pwd = rawurldecode($password);
		$key = substr($pwd, 0, 5) . "2&$%@(*@(djfhj1923";
		return Mcrypt::decode(substr($pwd, 5), $key);
	}

	/**
	 * 用户头像（上传）
	 */
	public function uploadHeadImage(){
		$ext = get_path_ext(Uploader::fileName());
		if(!in_array($ext,$this->imageExt)){
			show_json("only support image",false);
		}

		$path = KodIO::systemFolder('avataImage');
		$image = 'avata-'.USER_ID.'.jpg';
		$pathInfo 	= IO::infoFull($path.'/'.$image);
		if($pathInfo){
			IO::remove($pathInfo['path'], false);
		}
		
		// pr($imagePath,$path,IO::infoFull($imagePath));exit;
		$this->in['fullPath'] = '';
		$this->in['name'] = $image;
		$this->in['path'] = $path;
		Action('explorer.upload')->fileUpload();
	}
	/**
	 * 用户头像（设置）
	 */
	public function setHeadImage() {
		$link = Input::get('link', 'require');
		if(strpos($link, APP_HOST) !== 0) {
			show_json(LNG('common.illegalRequest'), false);
		}
		$userID = USER_ID;
		$link = str_replace(APP_HOST, './', $link);
		if(!$this->model->userEdit($userID, array("avatar" => $link))) {
			show_json(LNG('explorer.upload.error'), false);
		}
		$user = $this->model->getInfo($userID);
		Session::set('kodUser', $user);
		show_json($link, true, $user);
	}

	/**
	 * 重置密码
	 */
	public function changePassword() {
		if (empty($this->user['email']) && empty($this->user['phone'])) {
			show_json('请先绑定邮箱或手机号!', false);
		}
		show_json('', true);
	}

	/**
	 * 找回密码
	 */
	public function findPassword() {
		$token = Input::get('token', null, null);
		if(!$token){
			$res = $this->findPwdCheck();
		}else{
			$res = $this->findPwdReset();
		}
		show_json($res, true);
	}

	/**
	 * 找回密码 step1:根据账号检测并获取用户信息
	 * @return type
	 */
	private function findPwdCheck() {
		$data = Input::getArray(array(
			'type'			=> array('check' => 'in','default'=>'','param'=>array('phone','email')),
			'input'			=> array('check' => 'require'),
			'msgCode'		=> array('check' => 'require')
		));
		// 是否绑定
		$res = Model('User')->userSearch(array($data['type'] => $data['input']), 'userID');
		if (empty($res)) {
			show_json(LNG('user.notBind'), false);
		}
		$param = array(
			'type' => 'findpwd',
			'input' => $data['input']
		);
		$this->checkMsgCode($data['type'], $data['msgCode'], $param);

		$data = array(
			'type' => $data['type'],
			'input' => $data['input'],
			'userID' => $res['userID'],
			'time' => time()
		);
		$pass = md5('findpwd_' . implode('_', $data));
		Cache::set($pass, $data, 60 * 20);	// 有效期20分钟
		return $pass;
	}

	/**
	 * 找回密码 step1:更新密码
	 * @return type
	 */
	private function findPwdReset() {
		$data = Input::getArray(array(
			'token'	 	=> array('check' => 'require'),
			'password'	=> array('check' => 'require'),
			'salt'		=> array('default' => null)
		));
		// 检测token是否有效
		$cache = Cache::get($data['token']);
		if(!$cache) show_json(LNG('common.errorExpiredRequest'), false);
		if(!isset($cache['type']) || !isset($cache['input']) || !isset($cache['userID']) || !isset($cache['time'])){
			show_json(LNG('common.illegalRequest'), false);
		}
		if($cache['time'] < time() - 60 * 10){
			show_json(LNG('common.expiredRequest'), false);
		}
		$res = Model('User')->userSearch(array($cache['type'] => $cache['input']), 'userID');
		if(empty($res) || $res['userID'] != $cache['userID']){
			show_json(LNG('common.illegalRequest'), false);
		}
		if (isset($data['salt'])) {
			$data['password'] = $this->decodePwd($data['password']);
		}
		if( !ActionCall('filter.userCheck.password',$data['password']) ){
			return ActionCall('filter.userCheck.passwordTips');
		}
		Cache::remove($data['token']);		
		if (!$this->model->userEdit($res['userID'], array('password' => $data['password']))) {
			show_json(LNG('explorer.error'), false);
		}
		return LNG('explorer.success');
	}

	// 个人空间使用统计
	public function userChart(){
		ActionCall('admin.analysis.chart');
	}
	// 个人操作日志
	public function userLog(){
		$type = Input::get('type', null, null);
		if(!$type){
			return ActionCall('admin.log.userLog');
		}
		if($type == 'user.index.loginSubmit'){
			return ActionCall('admin.log.userLogLogin');
		}
	}
	// 个人登录设备
	public function userDevice(){
		$fromTime = time() - 3600 * 24 * 30 * 3;//最近3个月;
		$res = Model('SystemLog')->deviceList(USER_ID,$fromTime);
		show_json($res);
	}
	

	public function taskList(){ActionCall('admin.task.taskList',USER_ID);}
	public function taskKillAll(){ActionCall('admin.task.taskKillAll',USER_ID);}
	public function taskAction(){
		$result = ActionCall('admin.task.taskActionRun',false);
		if( !is_array($result['taskInfo'])){show_json(LNG('common.notExists'),false,'taskEmpty');}
		if( $result['taskInfo']['userID'] != USER_ID){show_json('User error',false);}
		show_json($result['result'],true);
	}
	public function notice(){
		$data	= Input::getArray(array(
			'id'		=> array('default' => false),
			'action'	=> array('check' => 'in','param' => array('get','edit','remove')),
		));
		$action = 'admin.notice.notice' . ucfirst($data['action']);
		ActionCall($action, $data['id']);
	}
}
