<?php

// 登录认证;
include('../../../app/api/KodSSO.class.php');
KodSSO::check('user:admin');// 必须系统管理员才可用; 'adminer'  'user:admin'
KodSSO::check('adminer'); 	// 系统管理员,可能开启三权分立,则会做检测;
@error_reporting(E_ALL^E_NOTICE^E_WARNING^E_DEPRECATED^E_STRICT);

if(file_exists('/tmp') && !file_exists('/tmp/session')){@mkdir('/tmp/session/',0777);}

// X-Frame-Options 去除不允许ifram限制;
function adminer_object() {
	class AdminerSoftware extends Adminer {
		function headers() {
			header("X-Frame-Options: SameOrigin");
			header("X-XSS-Protection: 0");
			header("Content-Security-Policy:0");
			if (function_exists('header_remove')) {
				@header_remove("X-Frame-Options");
				@header_remove("Content-Security-Policy");
			}
			return false;
		}
		function head() {
			$host = KodSSO::appHost();
			echo '<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover, shrink-to-fit=no" />';
			echo '<script>var kodSdkConfig = {api:"'.$host.'"};</script>';
			echo '<script src="'.$host.'static/app/dist/sdk.js"></script>';
			echo '<script type="text/javascript" src="./adminer.js"></script>';
			return true;
		}
		function permanentLogin($allow=false){
		    return 'aabbccdd';
		}
		function login($login, $password){
			return true;
		}
	}
	return new AdminerSoftware();
}

$debug = KodSSO::cacheGet('adminer_debug');
if (!$debug) ini_set('display_errors', 'off');	// 屏蔽错误信息
include('./adminer.php.txt');
if (!$debug) ini_set('display_errors', 'on');