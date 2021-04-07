<?php

class pdfjsPlugin extends PluginBase{
	function __construct(){
		parent::__construct();
	}
	public function regist(){
		$this->hookRegist(array(
			'user.commonJs.insert' => 'pdfjsPlugin.echoJs',
		));
	}
	public function echoJs(){
		$this->echoFile('static/app/main.js');
	}
	
	/**
	 * pdf: pdfjs,http://mozilla.github.io/pdf.js/getting_started/ 
	 * ofd: https://gitee.com/Trisia/ofdrw;  支持移动端,手势缩放; 文本选择复制等暂支持不完善;
	 */
	public function index(){
		$path = $this->in['path'];
		$fileUrl  = $this->filePathLink($path).'&name=/'.rawurlencode($this->in['name']);
		$fileName = $this->in['name'].' - '.LNG('common.copyright.name').' - '.LNG('common.copyright.powerBy');
		$canDownload = Action('explorer.auth')->fileCan($path,'download');
		// pr($canDownload,$fileUrl);exit;
		if( in_array($this->in['ext'],array('pdf','djvu','ofd')) ){
			include($this->pluginPath.'/php/'.$this->in['ext'].'.php');
		}
	}
}