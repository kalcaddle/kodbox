<?php
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/

class explorerFileView extends Controller{
	public function __construct(){
		parent::__construct();
		$this->model = Model("Source");
	}
	public function index(){
		$file = $this->in['path'];	
		$fileInfo = IO::info($file);
		$app = $this->getAppUser($fileInfo['ext']); 
		$fileUri = rawurlencode($file);

		if(!$app){
			return header('Location: '.APP_HOST.'#fileView&path='.$fileUri);
		}
		$object = Action($app.'Plugin');
		$existMethod = method_exists($object,'index');	
		if(!$existMethod){
			return header('Location: '.APP_HOST.'#fileView&path='.$fileUri);
		}		
		
		$link = urlApi('plugin/'.$app,'path='.$fileUri);
		$link .= '&ext='.rawurlencode($fileInfo['ext']).'&name='.rawurlencode($fileInfo['name']);
		header('Location: '.$link);
	}
	
	// 根据当前用户打开方式;
	private function getAppUser($ext){
		$list = $this->getAppSupport($ext,true);
		if(!$list) return false;
		$listName   = array_to_keyvalue($list,'name');
		$defaultSet = Model('UserOption')->get('kodAppDefault');
		$defaultSet = json_decode(Model('UserOption')->get('kodAppDefault'),true);

		$app = $list[0]['name'];
		if( isset($defaultSet[$ext]) && 
			isset($listName[$defaultSet[$ext]]) ){
			$app = $listName[$defaultSet[$ext]]['name'];
		}
		return $app;
	}

	// 打开方式获取并排序;
	private function getAppSupport($ext,$checkUserAuth = true){
		$modelPlugin = Model("Plugin");
		$list = $modelPlugin->loadList();
		$listAllow = array();
		foreach ($list as $app => $item) {
			if( !isset($item['config']['fileExt']) ) continue;
			$extArr = explode(',',$item['config']['fileExt']);
			if( !in_array($ext,$extArr) ) continue;
			
			if( $modelPlugin->appAllow($app,$item,$checkUserAuth) ){
				$item['fileOpenSort'] = intval($item['config']['fileSort']);
				$listAllow[] = $item;
			}
		}
		$listAllow = array_sort_by($listAllow,'fileOpenSort',true);
		return $listAllow;
	}
}
