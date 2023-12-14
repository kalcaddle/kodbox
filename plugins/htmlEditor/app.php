<?php

class htmlEditorPlugin extends PluginBase{
	function __construct(){
		parent::__construct();
	}
	public function regist(){
		$this->hookRegist(array(
			'user.commonJs.insert' => 'htmlEditorPlugin.echoJs',
		));
	}
}