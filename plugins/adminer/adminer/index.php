<?php

// 登陆认证;
include('../../../app/api/KodSSO.class.php');
KodSSO::check('adminer');

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
			echo '<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover, shrink-to-fit=no" />';
			echo '<script src="https://libs.baidu.com/jquery/2.0.0/jquery.min.js"></script>';
			echo '<script type="text/javascript" src="./adminer.js"></script>';
			return true;
		}
		function login($login, $password){
			return true;
		}
	}
	return new AdminerSoftware();
}
include('./adminer.php.txt');