<?php

/*
 * @link http://kodcloud.com/
 * @author warlee | e-mail:kodcloud@qq.com
 * @copyright warlee 2014.(Shanghai)Co.,Ltd
 * @license http://kodcloud.com/tools/license/license.txt
 */

class userSetting extends Controller {
	private $user;
	private $model;
	public function __construct() {
		parent::__construct();
		$this->user = Session::get('kodUser');
		$this->model = Model('User');
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
		
		// listType,listSort 显示模式,排序方式跟随文件夹配置记录;
		if(isset($this->in['listViewPath']) && $this->in['listViewPath']){
			Action('explorer.listView')->dataSave($this->in);
		}
		show_json(LNG('explorer.settingSuccess'));
	}
	public function getConfig(){
	}

	/**
	 * 个人中心-账号设置保存
	 */
	public function setUserInfo() {
		$allow = array('nickName', 'email', 'phone', 'password');
		$data = Input::getArray(array(
			"type"		=> array("check" => "in", "param" => $allow),
			"msgCode"	=> array("default" => null),
		));
		$input = '';
		if($data['type'] != 'password') {
			$input = Input::get('input','require');
			$input = html2txt(trim(rawurldecode($input)));
		}
		$userID = $this->user['userID'];
		if(in_array($data['type'], array('email', 'phone'))){
			if(!isset($data['msgCode'])){$data['msgCode'] = '000';}
			$this->userMsgCheck($input, $data); // 修改邮箱/手机号,需要验证码校验;
			if($input == $this->user[$data['type']]){
				show_json(LNG('common.' . $data['type']) . LNG('user.binded'), false);
			}
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
		Action('user.index')->refreshUser($userID);
		$userInfo = Model('User')->getInfo($userID);
		show_json(LNG('explorer.success'), true, $userInfo);
	}

	/**
	 * (图片)验证码校验
	 * @param string $code
	 */
	public function checkImgCode($code){
		$checkCode = Session::get('checkCode');
		Session::remove('checkCode');
		if (!$checkCode || strtolower($checkCode) !== strtolower(trim($code))) {
			show_json(LNG('user.codeError'), false, ERROR_IMG_CODE);
		}
	}

	/**
	 * 手机、邮箱验证码存储、验证
	 * @param string $type	email、phone
	 * @param string $code
	 * @param array $data	{type: [source], input: ''}
	 * @param bool $set	首次存储验证码(检测错误次数)
	 * @return void|bool
	 */
	public function checkMsgCode($type, $code, $data = array(), $set = false) {
		$typeList = array('setting', 'regist', 'findpwd', 'deregist');	// 个人设置、注册、找回密码、注销
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
	 * @param array $data
	 * @return void
	 */
	public function checkMsgFreq($data, $set=false){
		$cckey = md5("{$data['type']}_{$data['input']}_{$data['source']}_msgtime");
		$cache = Cache::get($cckey);
		// 保存
		if ($set) {
			$cnt   = intval(_get($cache,'cnt',0));
			$cache = array('time' => time(), 'cnt' => $cnt++);
			return Cache::set($cckey, $cache);
		}
		// 获取
		if (!$cache) return;
		$time = $data['type'] == 'email' ? 60 : 90;
		if(($cache['time'] + $time) > time()) {
			show_json(LNG('user.codeErrorFreq'), false);
		}
		// if($cache['cnt'] >= 10) {
		// 	show_json(sprintf(LNG('user.codeErrorCnt'), $hours), false);
		// }
	}

	/**
	 * (短信、邮箱)验证码校验
	 * @param string $input
	 * @param array $data
	 */
	private function userMsgCheck($input, $data) {
		$type = $data['type'];
		// 判断邮箱、手机号是否已被绑定
		if($this->user[$type] == $input) return;

		$where = array($type=> $input);
		if (Model('User')->userSearch($where, 'name,nickName')) {
			$typeTit = $type . ($type == 'phone' ? 'Number' : '');
			show_json(LNG('common.' . $typeTit) . LNG('common.error'), false);
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
	 * @param array $data
	 * @return string
	 */
	private function userPwdCheck($data) {
		$newpwd = Input::get('newpwd','require');
		$newpwd = KodUser::parsePass($newpwd);
		// 密码为空则不检查原密码
		$info = Model('User')->getInfoSimple($this->user['userID']);
		if(empty($info['password'])) return $newpwd;

		$oldpwd = Input::get('oldpwd','require');
		$oldpwd = KodUser::parsePass($oldpwd);
		if (!$this->model->userPasswordCheck($this->user['userID'], $oldpwd)) {
			show_json(LNG('user.oldPwdError'), false);
		}
		if( !ActionCall('filter.userCheck.password',$newpwd) ){
			return ActionCall('filter.userCheck.passwordTips');
		}
		return $newpwd;
	}

	/**
	 * 用户头像（上传）
	 */
	public function uploadHeadImage(){
		$ext = get_path_ext(Uploader::fileName());
		if(!in_array($ext,array('png','jpg','jpeg','gif','webp','bmp','ico'))){
			show_json("only support image",false);
		}

		$path = KodIO::systemFolder('avataImage');
		$image = 'avata-'.KodUser::id().'.'.$ext;	// jpg
		$pathInfo 	= IO::infoFullSimple($path.'/'.$image);
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
		$userID = KodUser::id();
		$link = str_replace(APP_HOST, './', $link);
		if(!$this->model->userEdit($userID, array("avatar" => $link))) {
			show_json(LNG('explorer.upload.error'), false);
		}
		Action('user.index')->refreshUser($userID);
		$userInfo = Model('User')->getInfo($userID);
		show_json($link, true, $userInfo);
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
			$res = $this->findPwdReset($token);
		}
		show_json($res, true);
	}

	/**
	 * 找回密码 step1：校验验证码，生成一次性的重置token
	 * 注意:先校验验证码再查询用户; 否则"账号未绑定"与"验证码错误"返回不同信息，可枚举出系统内已注册的邮箱/手机号
	 * @return void|string
	 */
	private function findPwdCheck() {
		$data = Input::getArray(array(
			'type'		=> array('check' => 'require'),
			'input'		=> array('check' => 'require'),
			'msgCode'	=> array('check' => 'require')
		));
		$data['type'] = strtolower(trim($data['type']));
		if(!in_array($data['type'], array('phone','email'), true)){
			show_json(LNG('common.illegalRequest'), false);
		}

		// 1. 先校验邮箱/短信验证码; 未绑定/不存在的账号同样落到"验证码错误",不泄露账号是否存在;
		$param = array(
			'type'	=> 'findpwd',
			'input' => $data['input']
		);
		$this->checkMsgCode($data['type'], $data['msgCode'], $param);

		// 2. 验证码通过后再确认账号(未绑定邮箱/手机号的账号收不到验证码,正常不会走到这里)
		$res = Model('User')->userSearch(array($data['type'] => $data['input']), 'userID');
		if (empty($res)) {
			show_json(LNG('user.notBind'), false);
		}

		// 3. 生成一次性token; 有效期10分钟,只允许使用一次;
		$data = array(
			'type'	=> $data['type'],
			'input'	=> $data['input'],
			'userID'=> intval($res['userID']),
			'time'	=> time(),
			'cnt'	=> 0		// 重置密码错误次数
		);
		$pass = 'findpwd_' . md5(implode('_', $data).'_'.rand_string(8));
		Cache::set($pass, $data, 60 * 10);
		return $pass;
	}

	/**
	 * 找回密码 step2：校验一次性token并更新密码
	 * @return void|string
	 */
	private function findPwdReset($token) {
		if (strlen($token) > 64 || strpos($token, 'findpwd_') !== 0) {
			show_json(LNG('common.errorExpiredRequest'), false);
		}
		$password = Input::get('password', 'require');
		$password = KodUser::parsePass($password);
		if(!is_string($password) || $password === ''){
			show_json(LNG('explorer.error'), false);
		}

		// 检测token是否有效
		$cache = Cache::get($token);
		if(!$cache || !is_array($cache)){
			show_json(LNG('common.errorExpiredRequest'), false);
		}
		// 结构&类型校验: 必须是step1写入的结构,避免复用其它业务的缓存内容;
		// 如有必要还应检查：input必须为非空字符串、time为数字等
		$check = true;
		foreach (array('type','input','userID','time','cnt') as $key) {
			if (!isset($cache[$key])) {$check = false; break;}
		}
		if ($check && !in_array($cache['type'], array('phone','email'), true)) {
			$check = false;
		}
		if(!$check){
			Cache::remove($token);
			show_json(LNG('common.illegalRequest'), false);
		}
		// 有效期10分钟(与写缓存时保持一致)，过期即删除
		if(intval($cache['time']) + 60 * 10 < time()){
			Cache::remove($token);
			show_json(LNG('common.expiredRequest'), false);
		}
		// 错误次数过多则作废
		if(intval($cache['cnt']) >= 5){
			Cache::remove($token);
			show_json(LNG('common.expiredRequest'), false);
		}

		// 根据条件查找用户
		$res = Model('User')->userSearch(array($cache['type'] => $cache['input']), 'userID');
		if(empty($res) || intval($res['userID']) != intval($cache['userID'])){
			$this->findPwdResetFail($token, $cache);
			show_json(LNG('common.illegalRequest'), false);
		}
		if (!Action('user.authRole')->authCanUser('user.edit',$res['userID'])) {
			show_json(LNG('explorer.noPermissionAction'),false,1004);
		}
		if( !ActionCall('filter.userCheck.password',$password) ){
			return ActionCall('filter.userCheck.passwordTips');
		}
		// 一次性使用：先失效token再更新密码
		Cache::remove($token);
		if (!$this->model->userEdit($res['userID'], array('password' => $password))) {
			show_json(LNG('explorer.error'), false);
		}
		return LNG('explorer.success');
	}

	// 重置密码token校验失败: 累加错误次数(剩余有效期不变),超限则作废
	private function findPwdResetFail($token, $cache) {
		$cache['cnt'] = intval($cache['cnt']) + 1;
		$timeout = intval($cache['time']) + 60 * 10 - time();
		if($cache['cnt'] >= 5 || $timeout <= 0){
			Cache::remove($token);
			return;
		}
		Cache::set($token, $cache, $timeout);
	}

	// 个人空间使用统计
	public function userChart(){
		$this->in['userID'] = KodUser::id();
		$this->in['groupID'] = '';
		ActionCall('admin.analysis.chart');
	}
	// 个人操作日志
	public function userLog(){
		$type = Input::get('type', null, null);
		$this->in['userID'] = KodUser::id();
		if(!$this->in['userID']){return;}
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
		$res = Model('SystemLog')->deviceList(KodUser::id(),$fromTime);
		show_json($res);
	}
	
	// 当前账号在线设备列表;
	public function userLoginList(){
		$sign = Session::sign();
		$arr  = Action("filter.userLoginState")->userListLoad(KodUser::id());
		$arr[$sign]['isSelf'] = true;
		foreach ($arr as $key => $item) {
			$arr[$key]['address'] = IpLocation::get($item['ip']);
		}
		show_json(array_values($arr));
	}
	// 踢下线某个登录设备;
	public function userLogoutSet(){
		$sign = Input::get('sign', null, null);
		Action("filter.userLoginState")->userLogoutTrigger(KodUser::id(),$sign);
		show_json(LNG('explorer.success'));
	}

	public function taskList(){ActionCall('admin.task.taskList',KodUser::id());}
	public function taskKillAll(){ActionCall('admin.task.taskKillAll',KodUser::id());}
	public function taskAction(){
		$result = ActionCall('admin.task.taskActionRun',false);
		if( !is_array($result['taskInfo'])){show_json(LNG('common.notExists'),false,'taskEmpty');}
		if( $result['taskInfo']['userID'] != KodUser::id()){show_json('User error',false);}
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
