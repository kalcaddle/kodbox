<?php

/**
 * webdav服务端;
 * 独立模块,不需要登录,权限内部自行处理;
 */
class webdavPlugin extends PluginBase{
	protected $dav;
	function __construct(){
		parent::__construct();
	}
	public function regist(){
		$this->hookRegist(array(
			'user.commonJs.insert'  => 'webdavPlugin.echoJs',
			'globalRequest'			=> 'webdavPlugin.route',
		));
	}
	public function echoJs(){
		$config = $this->getConfig();
		$allow  = $this->isOpen() && $this->authCheck();
		$assign = array(
			"{{isAllow}}" 	 => intval($allow),
			"{{pathAllow}}"	 => $config['pathAllow'],
			"{{webdavName}}" => $this->webdavName(),
		);
		$this->echoFile('static/main.js',$assign);
	}
	private function webdavName(){
		$config = $this->getConfig();
		return $config['webdavName'] ? $config['webdavName']:'kodbox';
	}
	
	public function route(){
		if(strtolower(MOD.'.'.ST) == 'plugin.index') exit;
		$this->_checkConfig();
		if(strtolower(MOD.'.'.ST) != 'plugin.webdav') return;
		$action = ACT;//dav/download;
		if( method_exists($this,$action) ){
			$this->$action();exit;
		}
		$this->run();exit;
	}
	public function run(){
		if(!$this->isOpen()) return show_json("not open webdav",false);
		require($this->pluginPath.'php/webdavServer.class.php');
		require($this->pluginPath.'php/kodWebDav.class.php');
		register_shutdown_function(array(&$this, 'endLog'));
		
		$this->dav = new kodWebDav('/index.php/plugin/webdav/'.$this->webdavName().'/'); // 适配window多一层;
		$this->debug($dav);
		$this->dav->run();
	}
	public function download(){
		IO::fileOut($this->pluginPath.'static/webdav.cmd',true);
	}
	public function _checkConfig(){
		$nowSize=_get($_SERVER,'_afileSize','');$enSize=_get($_SERVER,'_afileSizeIn','');
		if(function_exists('_kodDe') && (!$nowSize || !$enSize || $nowSize != $enSize)){exit;}
	}
	public function check(){
		echo $_SERVER['HTTP_AUTHORIZATION'];
	}
	public function checkSupport(){
		CacheLock::unlockRuntime();
		$url = APP_HOST.'index.php/plugin/webdav/check';
		$auth   = "Basic ".base64_encode('usr:pass');
		$header = array("Authorization: ".$auth);
		$res 	= @url_request($url,"GET",false,$header,false,false,3);
		if($res && substr($res['data'],0,11) == 'API call to') return true; //请求自己失败;
		if($res && $res['data'] == $auth) return true;
		
		@$this->setConfig(array('isOpen'=>'0'));
		return false;
	}

	public function onSetConfig($config){
		if($config['isOpen'] != '1') return;
		$this->onGetConfig($config);
	}
	public function onGetConfig($config){
		$this->autoApplyApache();
		if($this->checkSupport()) return;
		show_tips(
		"您当前服务器不支持PATH_INFO模式<br/>形如 /index.php/index方式的访问;
		同时不能丢失header参数Authorization;否则无法登录;
		<a href='http://doc.kodcloud.com/v2/#/help/pathInfo' target='_blank'>了解如何开启</a>",false);exit;
	}
	
	// apache 丢失Authorization情况自动加入配置;
	private function autoApplyApache(){
		$file = BASIC_PATH . '.htaccess';
		$isApache = strtolower($_SERVER['SERVER_SOFTWARE']) == 'apache';
		if(!$isApache || file_exists($file)) return;
		$arr = array(
			'RewriteEngine On',
			'RewriteCond %{HTTP:Authorization} ^(.*)',
			'RewriteRule .* - [e=HTTP_AUTHORIZATION:%1]',
		);
		file_put_contents($file,implode("\n",$arr));
	}

	private function isOpen(){
		$option = $this->getConfig();
		return $option['isOpen'] == '1';
	}
	private function debug(){
		// $this->log('start;'.$this->dav->pathGet().';'.$this->dav->path);
		if(strstr($_SERVER['HTTP_USER_AGENT'],'Chrome')){
			//PROPFIND;GET;MOVE;COPY,HEAD,PUT
			$_SERVER['REQUEST_METHOD'] = 'PROPFIND';
			// $_SERVER['REQUEST_METHOD'] = 'COPY';
		}
	}
	public function endLog(){
		$logInfo = 'dav-error';
		if($this->dav){
			$logInfo = $this->dav->pathGet().';'.$this->dav->path;
		}
		// $logInfo .= get_caller_msg();
		$this->log('end;['.http_response_code().'];'.$logInfo);
	}
	
	private function serverInfo($pick = '',$encode=false){
		$ignore = 'USER,HOME,PATH_TRANSLATED,ORIG_SCRIPT_FILENAME,HTTP_CONNECTION,HTTP_ACCEPT,HTTP_HOST,SERVER_NAME,SERVER_PORT,SERVER_ADDR,REMOTE_PORT,REMOTE_ADDR,SERVER_SOFTWARE,GATEWAY_INTERFACE,REQUEST_SCHEME,SERVER_PROTOCOL,DOCUMENT_ROOT,DOCUMENT_URI,REQUEST_URI,SCRIPT_NAME,CONTENT_LENGTH,CONTENT_TYPE,REQUEST_METHOD,QUERY_STRING,PATH_INFO,SCRIPT_FILENAME,FCGI_ROLE,PHP_SELF,REQUEST_TIME_FLOAT,REQUEST_TIME,REDIRECT_STATUS,HTTP_ACCEPT_ENCODING,HTTP_CACHE_CONTROL,HTTP_UPGRADE_INSECURE_REQUESTS,HTTP_CONTENT_LENGTH,HTTP_CONTENT_TYPE';
		$ignore .= ',HTTP_COOKIE,HTTP_ACCEPT_LANGUAGE,HTTP_USER_AGENT';
		$ignore .= ',HTTP_AUTHORIZATION,PHP_AUTH_USER,PHP_AUTH_PW';
		$ignore = explode(',',$ignore);
		$pick   = $pick ? explode(',',$pick) : array();
		
		$result = array();
		foreach($GLOBALS['__SERVER'] as $key => $val){
			if($pick){
				if(in_array($key,$pick)){
					$result[$key] = $val;
				}
			}else{
				if(!in_array($key,$ignore)){
					$result[$key] = $val;
				}
			}
		}
		return $result ? "\n".json_encode($result):'';
	}
	
	public function log($data){
		$config = $this->getConfig();
		if(empty($config['echoLog'])) return;
		if(is_array($data)){$data = json_encode_force($data);}
		// if($_SERVER['REQUEST_METHOD'] == 'PROPFIND' ) return;
		
		$data = $_SERVER['REQUEST_METHOD'].' '.$data;
		$data = $data.$this->serverInfo('');
		write_log($data,'webdav');
	}
}