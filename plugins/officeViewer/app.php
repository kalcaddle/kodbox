<?php

/**
 * office文件预览整合
 */
class officeViewerPlugin extends PluginBase{
	function __construct(){
		parent::__construct();
		$this->appList = array(
			'wb' => 'webOffice',
			'lb' => 'libreOffice',
			'ol' => 'officeLive',
			'yz' => 'yzOffice',
		);
	}
	public function regist(){
		$this->hookRegist(array(
			'user.commonJs.insert'	=> 'officeViewerPlugin.echoJs'
		));
	}
	public function echoJs(){
		$this->echoFile('static/app/main.js');
	}

	// 重置支持的文件格式
	public function onChangeStatus($status){
		if($status != '1') return;
		$config = $this->getConfig();
		if ($config['openType'] != 'js') return;

		$update = array(
			'openType' => 'wb',
			'fileExt' => 'doc,docx,xls,xlsx,ppt,pptx,csv',
		);
		$this->setConfig($update);
	}
	public function onSetConfig($config) {
		$openType = $config['openType'];
		if ($openType == 'js') {	// 兼容旧版
			$openType = 'wb';
			$config['openType'] = $openType;
		}
		$ext = 'doc,docx,xls,xlsx,ppt,pptx,csv';
		if (isset($config[$openType . 'FileExt'])) {
			$ext = $config[$openType . 'FileExt'];
		}
		$config['fileExt'] = $ext;
		return $config;
	}

	// 入口
	public function index(){
		$config = $this->getConfig();
		$openType = isset($config['openType']) ? $config['openType'] : '';
		if ($openType == 'js') $openType = 'wb';	// 兼容旧版
		$types = $openType == 'wb' ? array_keys($this->appList) : array($openType);
		// 按顺序依次调用
		foreach ($this->appList as $type => $app) {
			if (!in_array($type, $types) || !$this->allowExt($type)) continue;
			if ($type == 'lb' && !$this->action($app)->getSoffice()) continue;
			if ($type == 'ol' && !$this->isNetwork()) continue;	// officelive，文件需为域名地址
			return $this->fileOut($app);
		}
		show_tips(LNG('officeViewer.main.invalidType'));
	}

	// 用不同的方式预览office文件
	public function fileOut($app){
		return $this->action($app)->index();
	}
	public function action($app){
		return Action($this->pluginName . "Plugin.{$app}.index");
	}

	// 获取各应用的配置参数
	public function _appConfig($type){
		$config = $this->getConfig();
		$data = array();
		foreach($config as $key => $value) {
			if(strpos($key, $type) === 0) {
				$k = lcfirst(substr($key, strlen($type)));
				$data[$k] = $value;
			}
		}
		return $data;
	}
	// 各打开方式支持的文件格式
	public function allowExt($type){
		$ext = $this->in['ext'];
		$config = $this->getConfig();
		$extAll = explode(',', $config[$type.'FileExt']);
		if (!in_array($ext, $extAll)) return false;
		// 某些文件可能只是命名为旧格式，根据前2个字符(PK)区分
		if ($type == 'wb' && in_array($ext, array('doc', 'ppt'))) {
			$path = $this->in['path'];
			$prfx = IO::fileSubstr($path, 0, 2);
			return strtolower($prfx) == 'pk' ? true : false;
		}
		return true;
	}
	// 各打开方式错误提示
	public function showTips($msg, $title){
		// $title = '<img src="'.$this->pluginHost.'static/images/icon.png" style="width:22px;margin-right:8px;vertical-align:text-top;">'
		$title = '<span style="font-size:20px;">'.LNG('officeViewer.meta.name')
				.'</span><span style="font-size:14px;margin-left:5px;"> - '.$title.'</span>';
		// $msg = '<div style="text-align:center;font-size:14px;">'
		// 		.'<img src="'.$this->pluginHost.'static/images/error.png" style="width:24px;margin-top:10px;">'
		// 		.'<p style="margin-top:10px;">'.$msg.'</p>'
		// 		.'</div>';
		show_tips($msg, '', '', $title);
	}

	/**
	 * 可通过互联网访问
	 * @param boolean $domain	要求是域名
	 * @return void
	 */
	public function isNetwork($domain = true){
		$key = md5($this->pluginName . '_kodbox_server_network_' . intval($domain));
		$check = Cache::get($key);
		if($check !== false) return (boolean) $check;
		$time = ($check ? 30 : 3) * 3600 * 24;	// 可访问保存30天，否则3天

		// 1. 判断是否为域名
		$host = get_url_domain(APP_HOST);
		if($host == 'localhost') return false;
		if($domain && !is_domain($host)) return false;

		// 2. 判断外网能否访问
		$data = array('type' => 'network', 'url' => APP_HOST);
		$url = $GLOBALS['config']['settings']['kodApiServer'] . 'plugin/platform/';
		$res = url_request($url, 'POST', $data, false, false, false, 3);
		if(!$res || $res['code'] != 200 || !isset($res['data'])) {
			Cache::set($key, 0, $time);
			return false;
		}
		$res = json_decode($res['data'], true);

		$check = (boolean) $res['code'];
		Cache::set($key, (int) $check, $time);
		return $check;
	}

	/**
	 * 加载模板文件
	 * @param [type] $assign
	 * @param [type] $template	'static/weboffice/template.html'
	 * @return void
	 */
	public function displayTpl($assign, $template){
		$this->assign($assign);
		$this->display($this->pluginPath.$template);
	}

	/**
	 * 引入模板文件
	 * @param [type] $template
	 * @return void
	 */
	public function includeTpl($template){
		include($this->pluginPath.$template);
	}
}

