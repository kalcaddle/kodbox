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
	public function iframe(){
		// header('Content-Security-Policy: sandbox allow-scripts');// allow-same-origin 强制跨域; 导致资源无法缓存;
		header('Cross-Origin-Opener-Policy: same-origin');
		header('Cross-Origin-Embedder-Policy: sandbox require-corp');
		echo file_get_contents($this->pluginPath.'static/iframe-proxy.html');
	}
}