<?php

class oauthPlugin extends PluginBase{
	function __construct(){
		parent::__construct();
	}
	public function regist(){
		$this->hookRegist(array(
			'user.commonJs.insert'			=> 'oauthPlugin.echoJs',
			'user.view.options.after'		=> 'oauthPlugin.updateOption',
			'admin.setting.set.before'		=> 'oauthPlugin.adminSetBefore',

			'user.bind.withApp'				=> 'oauthPlugin.bindHook',	// app登录绑定
			'user.bind.bind.before'			=> 'oauthPlugin.bindHook',
			'user.bind.unbind.before'		=> 'oauthPlugin.bindHook',
			'user.bind.bindmetainfo.before'	=> 'oauthPlugin.bindHook',

			'user.bind.log'					=> 'oauthPlugin.logAdd',
			'admin.log.typelist.after'		=> 'oauthPlugin.logType',
			'admin.log.get.after'			=> 'oauthPlugin.logList',
		));
	}
	public function echoJs(){
		$this->echoFile('static/main.js');
	}

	/**
	 * 第三方登录项重置
	 * @param [type] $options
	 * @return void
	 */
	public function updateOption($options) {
		// 只有（渠道）定制插件存在该值（安装时自动保存配置，一般默认为空），其他的从插件配置中获取
		if (defined('INSTALL_CHANNEL') && INSTALL_CHANNEL) {
			$regist	= Model('SystemOption')->get('regist');
			$loginWith = _get($regist, 'loginWith', '');
		} else {
			$config = $this->getConfig();
			if (isset($config['loginWith'])) {
				$loginWith = _get($config, 'loginWith', '');
			} else {
				$loginWith = 'qq,weixin';	// 默认
			}
		}
		$loginWith = explode(',', $loginWith);
		$loginWith = array_filter($loginWith);
		// $options['system']['options']['loginWith'] = $loginWith;	// admin/set/get均已删除
		$options['system']['options']['loginConfig']['loginWith'] = $loginWith;
		return $options;
	}

	/**
	 * 后台第三方登录设置
	 * @return void
	 */
	public function adminSetBefore(){
		if(!Action('user.authRole')->authCan('admin.setting.set')){
			show_json(LNG('explorer.noPermissionAction'),false);
		}
		$data = json_decode($this->in['data'], true);
		$loginWith = $data['loginWith'];
		unset($data['loginWith']);
		$this->in['data'] = json_encode($data);
		// 登录项保存至插件配置
		$this->setConfig(array('loginWith' => $loginWith));
	}

	public function action($act = 'bind'){
		return Action($this->pluginName . 'Plugin.'.$act.'.index');
	}
	/**
	 * 绑定相关操作请求
	 * @return void
	 */
	public function bind($web=true) {
		$this->checkRequest('POST');
		$method = Input::get('method', 'require');	// oauth/bind/unbind/bindInfo/bindApi
		$action = $this->action();
		if (!method_exists($action, $method) || ($web && $method == 'bind')) {
			show_json(LNG('common.invalidParam').'[method]', false);
		}
		if ($method == 'bindInfo') {
			$type = '';
		} else {
			$type = Input::get('type','require');
		}
		return $action->$method($type);
	}
	/**
	 * 兼容app相关调用
	 * @param array $data
	 * @return void
	 */
	public function bindHook($data){
		$this->checkRequest();
		$act = strtolower(ACT);
		$actions = array(
			'bind' 			=> 'bind',
			'unbind' 		=> 'unbind',
			'bindmetainfo'	=> 'bindInfo',
		);
		if (isset($actions[$act])) {
			$this->in['method'] = $actions[$act];
			return $this->bind(false);
		}
		// app绑定第三方账号
		if (isset($this->in['third']) && !empty($data)) {
			$this->in['method'] = 'bindWithApp';
			return $this->action()->bindWithApp($data);
		}
		show_json(LNG('common.illegalRequest'), false);
	}
	/**
	 * 认证回调地址
	 * @return void
	 */
	public function callback(){
		$this->display($this->pluginPath.'/static/oauth/index.html');
	}

	// 日志相关
	public function logAdd($action, $data = array()) {
		return $this->action('log')->logAdd($action, $data);
	}
	public function logType($data = array()){
		return $this->action('log')->logType($data);
	}
	public function logList($data = array()){
		return $this->action('log')->logList($data);
	}

	// 限定post请求
	private function checkRequest($type='POST') {
		if ($_SERVER['REQUEST_METHOD'] != 'POST') {
			show_json(LNG('common.illegalRequest'), false);
		}
		if ($type == 'POST') return;
		$device = Action('filter.userCheck')->getDevice();
		if (!$device || $device['type'] != 'app') {
			show_json(LNG('common.illegalRequest'), false);
		}
	}
}