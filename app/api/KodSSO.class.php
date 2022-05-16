<?php
/**
 * 共享账号登录;支持限定账户,部门,权限组;
 * 
 * 1. 引入代码调用;(会引入整套kod的库; 侵入性; 函数名重名用命名空间处理)
 * include('./config/config.php');
 * $user = Action('user.sso')->check('adminer');
 * 
 * 2. 通用CAS模式单点登录; (可跨站点跨服务器,不同服务之间调用); 可用其他语言实现类似逻辑;
 * include('./app/api/KodSSO.class.php');
 * $user = KodSSO::check('user:admin',$host=''); // 不同站需要传入kod站点的名称; 不传默认认为和当前kod在同一站点下;
 * 
 * 权限检测支持: 
 * 	1. 指定插件名, 权限同该插件配置用户权限
 * 	2. 指定用户: 空字符串或user:all 所有登录用户; user:admin系统管理员
 * 	3. 指定权限详情: {"user":"1,3","group":"1","role":"1,2"}; 同插件权限设置指定:用户,部门,角色
 * 
 * 流程:
 * 1. 有cookie kodTokenApi; 请求kod的认证接口; 返回[ok] 则继续;
 * 2. 没有cookie kodTokenApi则跳转到kod登录界面; kod登录成功则带上kodToken跳转到该应用url; 再次验证kodToken成功则完成;
 */

@ini_set("display_errors","on");
@error_reporting(E_ALL^E_NOTICE^E_WARNING^E_DEPRECATED^E_STRICT);
class KodSSO{
	public static function check($appName="",$host=""){
		if(!$host){$host = self::appHost();}
		$urlInfo 	= parse_url(self::thisUrl());
		$key 		= 'kodTokenApi';
		$keyCookie 	= 'kodTokenApi-'.substr(md5($urlInfo['path']),0,5);
		$token 		= isset($_COOKIE[$keyCookie]) ? $_COOKIE[$keyCookie] : '';
		$token 		= isset($_GET[$key]) ? $_GET[$key] : $token;
		$userInfo 	= self::checkToken($appName,$host,$token);
		if( $token && $userInfo){
			if(isset($_GET[$key])){ // 首次登录成功跳转回来;
				setcookie($keyCookie,$token, time()+3600*5,self::thisPathUrl(),false,false,true);
				// 跳转到之前url; 去除url带入的token;
				$linkBefore = self::urlRemoveKey(self::thisUrl(),$key);
				header('Location: '.$linkBefore);exit;
			}
			return $userInfo;
		}

		$link = rawurlencode(self::thisUrl());
		$url  = $host.'?user/sso/apiLogin&appName='.$appName.'&callbackUrl='.$link;
		header('Location: '.$url);exit;
	}
	private static function checkToken($appName,$host,$token){
		if(!$token) return false;
		$timeStart = microtime(true);
		$uri = 'user/sso/apiCheckToken&accessToken='.$token.'&appName='.$appName;
		$res = '';
		$phpBin = self::phpBin();
		if($phpBin && function_exists('shell_exec')){
			$BASIC_PATH = str_replace('\\','/',dirname(dirname(dirname(__FILE__)))).'/';
			$command = $phpBin.' '.$BASIC_PATH.'index.php '.escapeshellarg($uri);
			$res = shell_exec($command);
		}
		if(!$res || substr(trim($res),0,1) != '[' ){ // 避免命令行调用返回错误的问题; 
			$context = stream_context_create(array(
				'http'	=> array('timeout' => 2,'method'=>"GET"),
				"ssl" 	=> array("verify_peer"=>false,"verify_peer_name"=>false)
			));
			$res = file_get_contents($host.'?'.$uri,false,$context);
		}
		// var_dump(microtime(true) - $timeStart,$host.'?'.$uri,$res);exit;
		$userInfo = @json_decode($res,true);
		if( $userInfo && is_array($userInfo) ){
			if(isset($userInfo['code']) && $userInfo['code'] == '10001') return false;
			return $userInfo;
		}
		if(!strstr($res,'[error]:')){echo $res;exit;}
		return false;
	}


	// 获取当前php执行目录; 
	private static function phpBin(){
		if(defined('PHP_BINARY') && @file_exists(PHP_BINARY)){
			$php = str_replace('-fpm','',PHP_BINARY);
			if(@file_exists($php)) return $php;
		}
		if(!defined('PHP_BINDIR')) return false; // PHP_BINDIR,PHP_BINARY
		$includePath = get_include_path();// php_ini_loaded_file();//php.ini path;
		$includePath = substr($includePath,strpos($includePath,'/'));
	
		$isWindow 	= strtoupper(substr(PHP_OS, 0,3)) === 'WIN';
		$binFile	= $isWindow ? 'php.exe':'php';
		$checkPath 	= array(
			PHP_BINDIR.'/',
			dirname(dirname($includePath)).'/bin/',
			dirname(dirname(dirname($includePath))).'/bin/',
		);
		foreach ($checkPath as $path) {
			if(@file_exists($path.$binFile)) return $path.$binFile;
		}
		return 'php';
    }

	private static function urlRemoveKey($url,$key){
		$parse = parse_url($url);
		parse_str($parse['query'],$get);
		unset($get[$key]);
		$query = http_build_query($get);
		$query = $query ? '?'.$query : '';
		$port  = (isset($parse['port']) && $parse['port'] != '80' ) ? ':'.$parse['port']:'';
		return $parse['scheme'].'://'.$parse['host'].$port.$parse['path'].$query;
	}
	public static function thisUrl(){
		return rtrim(self::host(),'/').'/'.ltrim($_SERVER['REQUEST_URI'],'/');
	}
	public static function thisPathUrl(){
		$uriInfo = parse_url(self::thisUrl());
		$uriPath = dirname($uriInfo['path']);
		if(substr($uriPath,-1) == '/'){$uriPath = $uriInfo['path'];}
		return '/'.trim($uriPath,'/');
	}
	public static function appHost(){
		$BASIC_PATH = str_replace('\\','/',dirname(dirname(dirname(__FILE__)))).'/';
		$WEB_ROOT 	= self::webrootPath($BASIC_PATH);
		$WEB_URI    = str_replace($WEB_ROOT,'',$BASIC_PATH);

		// 有软连接情况处理;
		if(substr($WEB_ROOT,0,strlen($BASIC_PATH)) != $WEB_ROOT || 
			substr($BASIC_PATH,0,strlen($WEB_ROOT)) != $WEB_ROOT){
			$WEB_URI = '';
			$DOCUMENT_URI = isset($_SERVER["DOCUMENT_URI"]) ? $_SERVER["DOCUMENT_URI"]:'';
			$pose = strpos($DOCUMENT_URI,'/plugins/');
			if($pose >= 0){
				$WEB_URI = substr($DOCUMENT_URI,1,$pose);
			}
		}
		return self::host().$WEB_URI; //程序根目录
	}
	//解决部分主机不兼容问题
	public static function webrootPath($basicPath){
		$DOCUMENT_URI = isset($_SERVER["DOCUMENT_URI"]) ? $_SERVER["DOCUMENT_URI"]:'';
		$SCRIPT_FILENAME = isset($_SERVER["SCRIPT_FILENAME"]) ? $_SERVER["SCRIPT_FILENAME"]:'';		
		$index = self::pathClear($basicPath.'index.php');
		$uri   = self::pathClear($DOCUMENT_URI);
		// 兼容 index.php/explorer/list/path; 路径模式;
		if($uri){//DOCUMENT_URI存在的情况; test.php ...统一化;
			$uri = dirname($uri).'/index.php';
		}
		if( substr($index,- strlen($uri) ) == $uri){
			$path = substr($index,0,strlen($index)-strlen($uri));
			return rtrim($path,'/').'/';
		}
		$uri = self::pathClear($_SERVER["SCRIPT_NAME"]);
		if( substr($index,- strlen($uri) ) == $uri){
			$path = substr($index,0,strlen($index)-strlen($uri));
			return rtrim($path,'/').'/';
		}
		
		// 子目录sso调用情况兼容;
		if($SCRIPT_FILENAME && $DOCUMENT_URI){
			$index = self::pathClear($SCRIPT_FILENAME);
			$uri   = self::pathClear($DOCUMENT_URI);		
			// 兼容 index.php/test/todo 情况;
			if( strstr($uri,'.php/')){
				$uri = substr($uri,0,strpos($uri,'.php/')).'.php';
			}		
			if( substr($index,- strlen($uri) ) == $uri){
				$path = substr($index,0,strlen($index)-strlen($uri));
				return rtrim($path,'/').'/';
			}
		}
		return str_replace('\\','/',$_SERVER['DOCUMENT_ROOT']);
	}
	public static function host(){
		$httpType = 'http';
		if( (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ||
			(isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') ||
			$_SERVER['SERVER_PORT'] === 443 
		){
			$httpType = 'https';
		}

		$host = $_SERVER['SERVER_NAME'].($_SERVER['SERVER_PORT']=='80' ? '' : ':'.$_SERVER['SERVER_PORT']);
		$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $host;
		if(isset($_SERVER['HTTP_X_FORWARDED_HOST'])){//proxy
			$hosts = explode(',', $_SERVER['HTTP_X_FORWARDED_HOST']);
			$host  = trim($hosts[0]);
		}else if(isset($_SERVER['HTTP_X_FORWARDED_SERVER'])){
			$host  = $_SERVER['HTTP_X_FORWARDED_SERVER'];
		}
		return $httpType.'://'.trim($host,'/').'/';
	}
	public static function pathClear($path){
		$path = str_replace('\\','/',trim($path));
		$path = preg_replace('/\/+/', '/', $path);
		if (strstr($path,'../')) {
			$path = preg_replace('/\/\.+\//', '/', $path);
		}
		return $path;
	}
}