<?php

class HttpAuth {
    public static function get() {
        $user     = '';
        $password = '';
        //Apache服务器
        if (isset($_SERVER['PHP_AUTH_USER'])) {
            $user = $_SERVER['PHP_AUTH_USER'];
			$password = $_SERVER['PHP_AUTH_PW'];
        } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            //其他服务器如 Nginx  Authorization
			$httpAuth = $_SERVER['HTTP_AUTHORIZATION'];
            if (strpos(strtolower($httpAuth), 'basic') === 0) {
                $auth = explode(':', base64_decode(substr($httpAuth, 6)));
                $user = isset($auth[0])?$auth[0]:'';
                $password = isset($auth[1])?$auth[1]:0;
			}
        }
        return array('user'=>$user, 'pass'=>$password);
	}
	
	public static function error() {
		//pr_trace();exit;
		header('WWW-Authenticate: Basic realm="kodcloud"');
		header('HTTP/1.0 401 Unauthorized');
		header('Pragma: no-cache');
		header('Cache-Control: no-cache');
		header('Content-Length: 0');
		exit;
	}
	
	public static function make($user,$pass){
		return "Authorization: Basic " . base64_encode($user.':'.$pass);
	}
}