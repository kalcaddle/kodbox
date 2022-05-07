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
		$config = $this->getConfig();
		// 插件配置中该参数是在后台保存时添加，如果不存在，从后台原数据中获取
		if (!isset($config['loginWith'])) {
			$regist = Model('SystemOption')->get('regist');
			if (!isset($regist['loginWith'])) {
				$loginWith = array('qq', 'weixin');	// 全新安装不含此参数，则赋默认值
			} else {
				$loginWith = _get($regist,'loginWith',array());
			}
			$this->setConfig(array('loginWith' => implode(',', $loginWith)));
		} else {
			$loginWith = explode(',', _get($config, 'loginWith', ''));
		}
		$options['system']['options']['loginConfig']['loginWith'] = array_filter($loginWith);
		return $options;
	}

	/**
	 * 后台第三方登录设置
	 * @return void
	 */
	public function adminSetBefore(){
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
	public function bind() {
		$action = Input::get('method', 'require');
		return $this->action()->$action();
	}
	/**
	 * 兼容app相关调用
	 * @param array $data
	 * @return void
	 */
	public function bindHook($data=array()){
		$act = strtolower(ACT);
		$actions = array(
			'bind' 			=> 'bind',
			'unbind' 		=> 'unbind',
			'bindmetainfo'	=> 'bindInfo',
		);
		if (isset($actions[$act])) {
			$this->in['method'] = $actions[$act];
			return $this->bind();
		}
		// app绑定第三方账号
		if (isset($this->in['third']) && !empty($data)) {
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
}