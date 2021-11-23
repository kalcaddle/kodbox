<?php

/**
 * office文件预览整合
 */
class officeViewerPlugin extends PluginBase{
	function __construct(){
		parent::__construct();
	}
	public function regist(){
		$this->hookRegist(array(
			'user.commonJs.insert'	=> 'officeViewerPlugin.echoJs'
		));
	}
	public function echoJs(){
		$this->echoFile('static/app/main.js');
	}

	public function onSetConfig($config) {
		$type = $config['openType'];
		$config['fileExt'] = $config[$type . 'FileExt'];
		if($type != 'js') return $config;
		// js方式，取合集
		$ext = array();
		foreach(array('js', 'lb', 'ol', 'gg', 'yz') as $k) {
			$ext[] = $config[$k . 'FileExt'];
		}
		$ext = explode(',', implode(',', $ext));
		$ext = implode(',', array_unique($ext));
		$config['fileExt'] = $ext;
		return $config;
	}

	// 入口
	public function index(){
		$action = array(
			'js' => 'jsOffice',
			'lb' => 'libreOffice',
			'ol' => 'officeLive',
			'gg' => 'googleDocs',
			'yz' => 'yzOffice',
		);
		$config = $this->getConfig();
		$type = isset($config['openType']) ? $config['openType'] : '';
		if(!$type || !isset($action[$type])) {
			show_tips(LNG('officeViewer.main.invalidType'));
		}
		// 单一方式，(js表示全部)
		if($type != 'js') {
			return $this->fileOut($action[$type]);
		};
		// 全部，各方式依次执行
		// 1.js解析
		$app = $action['js'];
		if($this->allowExt('js') && $config['jsOpen'] == '1') {
			return $this->fileOut($app);
		}
		// 2.libreOffice
		$app = $action['lb'];
		if($this->allowExt('lb') && $this->action($app)->getSoffice()){
			return $this->fileOut($app);
		}
		// 3.officelive，文件需为域名地址
		if($this->allowExt('ol') && $this->isNetwork()) {
			return $this->fileOut($action['ol']);
		}
		// 4.googledocs，文件为外网地址，可为ip
		if($this->allowExt('gg') && $this->isNetwork(false)) {
			// 需在前端访问google，这里无法检测
			return $this->fileOut($action['gg']);
		}
		// 5.永中
		if($this->allowExt('yz')) {
			return $this->fileOut($action['yz']);
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
		return in_array($ext, $extAll);
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
	 * 前端解析模板
	 * @param [type] $app
	 * @return void
	 */
	public function showJsOffice($app){
		$path   = $this->in['path'];
		$assign = array(
			"fileUrl"	=>'','savePath'	=>'','canWrite'	=>false,
			'fileName'	=> $this->in['name'],
			'fileApp'	=> $app, 'fileExt' => $this->in['ext']
		);
		if($path){
			if(substr($path,0,4) == 'http'){
				$assign['fileUrl'] = $path;
			}else{
				$assign['fileUrl']  = $this->filePathLink($path);
				if(ActionCall('explorer.auth.fileCanWrite',$path)){
					$assign['savePath'] = $path;
					$assign['canWrite'] = true;
				}
			}
			$assign['fileUrl'] .= "&name=/".$assign['fileName'];
		}
		$this->assign($assign);
		$this->display($this->pluginPath.'static/jsoffice/template.html');
	}

	/**
	 * libreOffice服务检测模板
	 * @return void
	 */
	public function checkLibreOffice(){
		include($this->pluginPath.'static/libreoffice/check.html');
	}
}

