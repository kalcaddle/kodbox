<?php

class webodfPlugin extends PluginBase{
	function __construct(){
		parent::__construct();
	}
	public function regist(){
		$this->hookRegist(array(
			'user.commonJs.insert' => 'webodfPlugin.echoJs',
		));
	}
	public function echoJs(){
		$this->echoFile('static/main.js');
	}
	public function index(){
		$fileUrl  = $this->filePathLink($this->in['path']);
		$fileName = $this->in['name'].' - '.LNG('common.copyright.name').LNG('common.copyright.powerBy');
		include($this->pluginPath.'/php/template.php');
	}
}