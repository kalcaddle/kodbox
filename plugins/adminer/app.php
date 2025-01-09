<?php
class adminerPlugin extends PluginBase{
	function __construct(){
		parent::__construct();
	}
	public function regist(){
		$this->hookRegist(array('user.commonJs.insert'=> 'adminerPlugin.echoJs'));
	}
	public function echoJs(){
		if(_get($GLOBALS,'isRoot') != 1) return;
		$this->debugSet(0);
		$this->echoFile('static/main.js');
	}
	public function debugSet($state){
		$config = $this->getConfig();
		$debug  = intval(_get($config,'debug', $state));
		include_once('./app/api/KodSSO.class.php');
		KodSSO::cacheSet('adminer_debug', $debug);
	}
}