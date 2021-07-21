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
		foreach(array('js', 'ol', 'yz') as $k) {
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
		if($this->_isNetwork()) {
			$this->fileOutOffice($action['ol']);
		}
		$this->fileOutOffice($action['yz']);
	}

	// 用不同的方式预览office文件
	public function fileOutOffice($app){
		Action($this->pluginName . "Plugin.{$app}.index")->index();
	}
	private function _isNetwork(){
		return Action($this->pluginName . 'Plugin.officeLive.index')->isNetwork();
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

}

