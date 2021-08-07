<?php 
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/

class explorerApi extends Controller{
	function __construct(){
		parent::__construct();
	}

	/**
	 * 通用文件预览方案
	 * image,media,cad,office,webodf,pdf,epub,swf,text
	 * 跨域:epub,pdf,odf,[text];  
	 * @return [type] [description]
	 */
	public function view(){
		if(!isset($this->in['path'])){
			show_tips(LNG('explorer.share.errorParam'));
		}
		$this->checkAccessToken();
		$this->setIdentify();
	}
	private function setIdentify(){
		if(!Session::get('accessPlugin')){
			Session::set('accessPlugin', 'ok');
		}
	}
	public function checkAccessToken(){
		$config = Model('Plugin')->getConfig('fileView');
		if(!$config['apiKey']) return;

		$timeTo = isset($this->in['timeTo'])?intval($this->in['timeTo']):'';
		$token = md5($this->in['path'].$timeTo.$config['apiKey']);
		if($token != $this->in['token']){
			show_tips('token ' . LNG('common.error'));
		}
		if($timeTo != '' && $timeTo <= time()){
			show_tips('token ' . LNG('common.expired'));
		}
	}
}

