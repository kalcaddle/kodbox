<?php

/**
 * 共享账号登录;支持限定账户,部门,权限组;
 * 
 * 1. 引入代码调用;(会引入整套kod的库; 侵入性; 函数名重名用命名空间处理)
 * include('../../../config/config.php');
 * Action('user.sso')->check('adminer');
 * 
 * 2. 通用CAS模式单点登陆; (可跨站点跨服务器,不同服务之间调用); 可用其他语言实现类似逻辑;
 * include('../../../app/api/KodSSO.class.php');
 * KodSSO::check('adminer'); // 不同站需要传入kod站点的名称; 
 * 
 * 
 * 流程:
 * 1. 有cookie kodTokenApi; 请求kod的认证接口; 返回[ok] 则继续;
 * 2. 没有cookie kodTokenApi则跳转到kod登陆界面; kod登陆成功则带上kodToken跳转到该应用url; 再次验证kodToken成功则完成;
 */ 
class KodSSO{
	public static function check($appName,$host=""){
		if(!$host){$host = self::appHost();}
		$key = 'kodTokenApi';
		$token = isset($_COOKIE[$key]) ? $_COOKIE[$key] : '';
		$token = isset($_GET[$key]) ? $_GET[$key] : $token;
		if($token && self::checkToken($appName,$host,$token)){
			if(isset($_GET[$key])){ // 首次登陆成功跳转回来;
				$path = str_replace(self::host(),'',self::appHost());
				setcookie($key,$token, time()+3600*5,'/'.trim($path,'/'),false,false,true);

				// 跳转到之前url; 去除url带入的token;
				$linkBefore = self::urlRemoveKey(self::thisUrl(),$key);
				header('Location: '.$linkBefore);exit;
			}
			return;
		}

		$link = rawurlencode(self::thisUrl());
		$url  = $host.'?user/sso/apiLogin&appName='.$appName.'&callbackUrl='.$link;
		header('Location: '.$url);exit;
	}
	public static function checkToken($appName,$host,$token){
		if(!$token) return false;
		$timeStart = microtime(true);
		$uri = 'user/sso/apiCheckToken&accessToken='.$token.'&appName='.$appName;
		$res = '';
		$phpBin = self::phpBin();
		if($phpBin && function_exists('shell_exec')){
			$BASIC_PATH = str_replace('\\','/',dirname(dirname(dirname(__FILE__)))).'/';
			$command = $phpBin.' '.$BASIC_PATH.'index.php '.escapeshellarg($uri);
			$res = shell_exec($command);
		}else{
		    echo "shell_exec is disabled; please open it";exit;
		}
		if(!$res || substr(trim($res),0,1) != '[' ){ // 避免命令行调用返回错误的问题; 
			$context = stream_context_create(array(
				'http'	=> array('timeout' => 2,'method'=>"GET"),
				"ssl" 	=> array("verify_peer"=>false,"verify_peer_name"=>false)
			));
			$res = file_get_contents($host.'?'.$uri,false,$context);
		}
		// var_dump(microtime(true) - $timeStart,$res);exit;
		if(trim($res) === '[ok]') return true;
		if(!strstr($res,'[error]:')){echo $res;exit;}
		return false;
	}


	// 获取当前php执行目录; 
	private static function phpBin(){
		if(defined('PHP_BINARY') && @file_exists(PHP_BINARY)){
			$php = str_replace('-fpm','',PHP_BINARY);
			if(file_exists($php)) return $php;
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
			if(file_exists($path.$binFile)) return $path.$binFile;
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
	public static function appHost(){
		$BASIC_PATH = str_replace('\\','/',dirname(dirname(dirname(__FILE__)))).'/';
		$WEB_ROOT 	= self::webrootPath($BASIC_PATH);
		return self::host().str_replace($WEB_ROOT,'',$BASIC_PATH); //程序根目录
	}
	//解决部分主机不兼容问题
	public static function webrootPath($basicPath){
		$index = self::pathClear($basicPath.'index.php');
		$uri   = self::pathClear($_SERVER["DOCUMENT_URI"]);
		// 兼容 index.php/explorer/list/path; 路径模式;
		if($uri){//DOCUMENT_URI存在的情况;
			$uriPath = substr($uri,0,strpos($uri,'/index.php'));
			$uri = $uriPath.'/index.php';
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
		if($_SERVER['SCRIPT_FILENAME'] && $_SERVER["DOCUMENT_URI"]){
			$index = self::pathClear($_SERVER['SCRIPT_FILENAME']);
			$uri   = self::pathClear($_SERVER["DOCUMENT_URI"]);		
			// 兼容 index.php/test/todo 情况;
			if( strstr($uri,'.php/')){
				$uri = substr($uri,0,strpos($uri,'.php/')).'.php';
			}		
			if( substr($index,- strlen($uri) ) == $uri){
				$path = substr($index,0,strlen($index)-strlen($uri));
				return rtrim($path,'/').'/';
			}
		}
		return $_SERVER['DOCUMENT_ROOT'];
	}
	public static function host(){
		$protocol = "http://";
		if( (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
			(isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') ||
			$_SERVER['SERVER_PORT'] === 443 
			){
			$protocol = 'https://';
		}
		
		$url_host = $_SERVER['SERVER_NAME'].($_SERVER['SERVER_PORT']=='80' ? '' : ':'.$_SERVER['SERVER_PORT']);
		$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $url_host;
		$host = isset($_SERVER['HTTP_X_FORWARDED_HOST']) ? $_SERVER['HTTP_X_FORWARDED_HOST'] : $host;//proxy
		return rtrim($protocol.$host,'/').'/';
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