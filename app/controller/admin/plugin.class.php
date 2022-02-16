<?php
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*
*
* 插件管理：页面；列表；
*/

class adminPlugin extends Controller{
	private $model;
	function __construct() {
		parent::__construct();
		$this->model = Model('Plugin');
	}

	public function appList(){
		$list  = $this->model->viewList();
		show_json($list);
	}
	public function changeStatus(){
		if( !isset($this->in['app']) || 
			!isset($this->in['status'])){
			show_json(LNG('explorer.dataNotFull'),false);
		}
		$app 	= $this->in['app'];
		$status = $this->in['status']?1:0;

		//同步用户的插件，只允许开启一个；其他的开启时自动提示;
		Hook::trigger("pluginApp.changeStatus",$app,$status);
		$eventAsync = 'pluginApp.changeStatus.userAsync';
		if($status){
			$appConfig = $this->model->loadList($app);
			if( is_array($appConfig['regiest']) && 
				isset($appConfig['regiest'][$eventAsync]) ){
				Hook::trigger($eventAsync,$app);
			}
			//启用插件则检测配置文件，必填字段是否为空；为空则调用配置
			$config	 = $this->model->getConfig($app);
			$package = $this->model->getPackageJson($app);
			$needConfig = false;
			foreach($package['configItem'] as $key=>$item) {
				if( (isset($item['require']) && $item['require']) &&
					(!isset($item['value']) || $item['value'] === '' || $item['value'] === null) &&
					(!isset($config[$key])  || $config[$key] == "")
				){
					$needConfig = true;
					break;
				}
			}
			if($needConfig){
				show_json('needConfig',false);
			}
		}
		$this->model->changeStatus($app,$status);
		ActionCall($app.'Plugin.onChangeStatus',$status,$app);
		$this->appList();
	}

	public function getConfig(){
		$app = Input::get('app');
		$data = $this->model->getConfig($app);
		$package  = $this->model->getPackageJson($app);
		$formData = $package['configItem'];
		$userSelect = array("type"=>"mutil","user"=>"mutil","group"=>"mutil","role"=>"mutil");

		foreach ($formData as $key=>&$item) {
			if(!isset($item['type']) || $item['type'] == 'html' || $item['type'] == 'button') continue;
			if(isset($data[$key])){
				$item['value'] = $data[$key];
			}
			//用户选择,默认值处理;
			if( is_array($item) && $item['type'] == 'userSelect' && !isset($item['info']) ){
				$item['info'] = $userSelect;
			}
		};unset($item);
		$result = ActionCall($app.'Plugin.onGetConfig',$formData);
		if(is_array($result)){
			$formData = $result;
		}
		show_json($formData);
	}

	public function setConfig(){
		if( !$this->in['app'] || 
			!$this->in['value']){ 
			show_json(LNG('explorer.dataNotFull'),false);
		}
		$json = $this->in['value'];
		$app  = $this->in['app'];
		if($json == 'reset'){
			//重置为默认配置
			$this->model->setConfig($app,false);
			$json = $this->model->getConfigDefault($app);
		}else{
			if(!is_array($json) && !$json = json_decode($json, true)){
				show_json($json,false);
			}
		}
		$this->model->changeStatus($app,1);
		$result = ActionCall($app.'Plugin.onSetConfig',$json);
		if(is_array($result)){
			$json = $result;
		}
				
		$this->model->setConfig($app,$json);
		show_json(LNG('explorer.success'));
	}

	// download=>fileSize=>unzip=>remove
	public function install(){
		$app = Input::get('app','key');
		$appPath = PLUGIN_DIR.$app.'.zip';
		$appPathTemp = $appPath.'.downloading';
		switch($this->in['step']){
			case 'check':
				$info = $this->pluginInfo($app);
				if(!is_array($info)){
					show_json(false,false);
				}
				echo json_encode($info);
				break;
			case 'download':
				$info = $this->pluginInfo($app);
				if(!$info || !$info['code']){
					show_json(LNG('explorer.error'),false);
				}
				$result = Downloader::start($info['data'],$appPath);
				show_json($result['data'],!!$result['code'],$app);
				break;
			case 'fileSize':
				if(file_exists($appPath)){
					show_json(filesize($appPath));
				}
				if(file_exists($appPathTemp)){
					show_json(filesize($appPathTemp));
				}
				show_json(0,false);
				break;
			case 'unzip':
				$GLOBALS['isRoot'] = 1;
				if(!file_exists($appPath)){
					show_json(LNG('explorer.error'),false);
				}
				$result = KodArchive::extract($appPath,PLUGIN_DIR.$app.'/');
				del_file($appPathTemp);
				del_file($appPath);
				show_json($result['data'],!!$result['code']);
				break;
			case 'remove':
				del_file($appPathTemp);
				del_file($appPath);
				show_json(LNG('explorer.success'));
				break;
			case 'update':
				show_json(Hook::apply($app.'Plugin.update'));
				break;
			default:break;
		}
	}
	private function pluginInfo($app){
		$api = $this->config['settings']['kodApiServer'].'pluginV5/install';
		$param = array(
			"app"			=> $app,
			"version"		=> KOD_VERSION,
			"versionHash"	=> Model('SystemOption')->get('versionHash'),
			"code"			=> Model('SystemOption')->get('versionUser'),
			"deviceUUID"	=> Model('SystemOption')->get('deviceUUID'),
			"systemOS"		=> $this->config['systemOS'],
			"phpVersion"	=> PHP_VERSION,
			"channel"		=> INSTALL_CHANNEL,	
			"lang"			=> I18n::getType()
		);
		$info   = url_request($api,'POST',$param);
		$result = false;
		if($info && $info['data']){
			$result = json_decode($info['data'],true);
		}
		return $result;
	}

	public function unInstall(){
		$app = Input::get('app','key');
		if( !$this->in['app']){
			show_json(LNG('explorer.dataNotFull'),false);
		}
		if(substr($app,0,3) == 'oem'){
			show_json("专属定制插件不支持卸载,不需要您可以禁用!",false);
		}		
		ActionCall($app.'Plugin.onUninstall',$app);
		$this->model->unInstall($app);
		del_dir(PLUGIN_DIR.$app);
		$this->appList();
	}
}
