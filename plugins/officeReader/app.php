<?php

/**
 * office文件预览整合
 */
class officeReaderPlugin extends PluginBase{
	function __construct(){
		parent::__construct();
	}
	public function regist(){
		$this->hookRegist(array(
			'user.commonJs.insert'	=> 'officeReaderPlugin.echoJs'
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
		foreach(array('js', 'ol', 'gg', 'yz') as $k) {
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
			'js' => 'officeJs',
			'ol' => 'officeLive',
			'gg' => 'googleDocs',
			'yz' => 'yzOffice',
		);
		$config = $this->getConfig();
		$type = isset($config['openType']) ? $config['openType'] : '';
		if(!$type || !isset($action[$type])) {
			show_tips(LNG('officeReader.main.invalidType'));
		}
		if($type != 'js') {
			return $this->fileOutOffice($action[$type]);
		};
		// 非js方式（自动），依次执行一遍
		if($config['jsOpen'] == '1') {
			$this->fileOutOffice($action['js']);
		}
		// officelive，文件需为域名地址
		if($this->isNetwork()) {
			$this->fileOutOffice($action['ol']);
		}
		// googledocs，文件为外网地址，可为ip
		if($this->isNetwork(false)) {
			// google.com可能连不上，但不好检查
			$this->fileOutOffice($action['gg']);
		}
		$this->fileOutOffice($action['yz']);
	}

	// 用不同的方式预览office文件
	public function fileOutOffice($app){
		Action($this->pluginName . "Plugin.{$app}.index")->index();
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
		$data = array(
			'type'	=> 'network',
			'url'	=> APP_HOST
		);
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

	public function showJsTpl($app){
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
		$this->display($this->pluginPath.'static/office/template.html');
		exit;
	}

}

