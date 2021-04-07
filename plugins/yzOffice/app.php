<?php
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/
class yzOfficePlugin extends PluginBase{
	function __construct(){
		parent::__construct();

		//IE8自动切换为普通模式
		if( strpos($_SERVER["HTTP_USER_AGENT"],"MSIE 8.0") ){
			$this->getConfig();
			$this->pluginConfig['preview'] = '0';
		}
	}
	public function regist(){
		$this->hookRegist(array(
			'user.commonJs.insert'	=> 'yzOfficePlugin.echoJs'
		));
	}
	public function echoJs(){
		$this->echoFile('static/main.js');
	}
	public function index(){
		$app = $this->getObj();
		$fileName = $this->fileInfo['name'] . ' - '.LNG('common.copyright.name').LNG('common.copyright.powerBy');
		if(!$app->task['success'] ){
			include($this->pluginPath.'php/template.php');
			return;
		}

		//获取页面
		$step     = count($app->task['steps']) - 1;
		$infoData = $app->task['steps'][$step]['result'];
		if( !is_array($infoData['data']) ){
			$app->clearCache();
			show_tips($infoData['message']);
		}
		$link = $infoData['data'][0];
		$name = md5($link).'.html.temp';
		if($sourceID = IO::fileNameExist($this->cachePath, $name)){
			$content = IO::getContent(KodIO::make($sourceID));
		}else{
			$result = url_request($link,'GET');
			if($result['code'] == 200){
				$title = '<title>永中文档转换服务</title>';
				$content = str_replace($title,'<title>'.$fileName.'</title>',$result['data']);
				$this->pluginCacheFileSet($this->cachePath . $name, $content);
			}else{
				$app->clearCache();
				show_tips($result);
			}
		}
		if(strstr($content,'location.href = ')){
			$app->clearCache();
			show_tips("请求转换异常，请重试！");
		}

		//替换内容
		$config = $this->getConfig();
		if(!$config['cacheFile']){ 
			header("Location: ".$html);
			exit;
		}
		$name  = str_replace(".html",'',get_path_this($link));
		$urlReplaceFrom   = './'.$name.".files";
		$urlReplaceTo     = $this->pluginApi.'getFile&path='.rawurlencode($this->in['path']).
		$urlReplaceTo 	 .= '&file='.rawurlencode($urlReplaceFrom);
		// show_json(array($result,$urlReplaceFrom,$urlReplaceTo),false);
		
		$content = str_replace($urlReplaceFrom,$urlReplaceTo,$content);
		$content = str_replace('"'.$name.'.files','"'.$urlReplaceTo,$content);
		$content = str_replace(array('<!DOCTYPE html>','<html>','<head>','</html>'),'',$content);
		include('php/assign/header.php');
		echo $content;
		include('php/assign/footer.php');
	}

	public function task(){
		$app = $this->getObj();
		$app->runTask();
	}
	public function getFile(){
		$app = $this->getObj();
		$app->getFile($this->in['file']);
	}
	private function getObj(){
		$path = $this->filePath($this->in['path']);
		// if(filesize($path) > 1024*1024*2){
		// 	//show_tips("由于永中官方接口限制,<br/>暂不支持大于2M的文件在线预览！");
		// }
		//文档分享预览; http://yozodoc.com/
		// require_once($this->pluginPath.'php/yzOffice.class.php');
		// return  new yzOffice($this,$path);
		
		//官网用户demo;
		//http://www.yozodcs.com/examples.html     2M上传限制;
		//http://dcs.yozosoft.com/examples.html
		require_once($this->pluginPath.'php/yzOffice.class.php');
		return new yzoffice($this,$path);
	}

	public function restart(){
		$app = $this->getObj();
		$res = $app->clearCache();
		show_json('success');
	}
}

