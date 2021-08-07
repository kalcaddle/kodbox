<?php

class officeLivePlugin extends PluginBase{
	function __construct(){
		parent::__construct();
	}
	public function regist(){
		$this->hookRegist(array(
			'user.commonJs.insert'	=> 'officeLivePlugin.echoJs'
		));
	}
	public function echoJs(){
		$this->echoFile('static/main.js');
	}
	public function index(){
		$fileUrl = $this->filePathLinkOut($this->in['path']);
		$config = $this->getConfig();
		header('Location:'.$config['apiServer'].rawurlencode($fileUrl));
	}
}