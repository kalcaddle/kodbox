<?php
class adminerPlugin extends PluginBase{
	function __construct(){
		parent::__construct();
	}
	public function regist(){
		$this->hookRegist(array(
			'user.view.options.after' => 'adminerPlugin.addMenu',
		));
	}

	public function addMenu($options){
		$config = $this->getConfig();
		$menu = array(
			'name'		=> 'Adminer',
			'icon'		=> $this->appIcon(),
			'url'		=> $this->pluginApi,
			'target'	=> '_blank',
			'subMenu'	=> $config['menuSubMenu'],
			'use'		=> '1'
		);
		return ActionCall('admin.setting.addMenu',$options,$menu);
	}
	
	public function index(){
		header('Location: '.$this->pluginHost.'adminer/');
	}
}