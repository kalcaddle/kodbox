<?php

class HttpHeader{
	public static $_headers = array(
        'Host'                => 'HTTP_HOST',
        'User-Agent'          => 'HTTP_USER_AGENT',
        'Content-Type'        => 'HTTP_CONTENT_TYPE',
        'Content-Length'      => 'HTTP_CONTENT_LENGTH',
        'Depth'               => 'HTTP_DEPTH',
        'Expect'              => 'HTTP_EXPECT',
        'If-None-Match'       => 'HTTP_IF_NONE_MATCH',
        'If-Match'            => 'HTTP_IF_MATCH',
        'If-Range'            => 'HTTP_IF_RANGE',
        'Last-Modified'       => 'HTTP_LAST_MODIFIED',
        'If-Modified-Since'   => 'HTTP_IF_MODIFIED_SINCE',
        'If-Unmodified-Since' => 'HTTP_IF_UNMODIFIED_SINCE',
        'Range'               => 'HTTP_RANGE',
        'Timeout'             => 'HTTP_TIMEOUT',
        'If'                  => 'HTTP_IF',
        'Lock-Token'          => 'HTTP_LOCK_TOKEN',
        'Overwrite'           => 'HTTP_OVERWRITE',
        'Destination'         => 'HTTP_DESTINATION',
        'Request-Id'          => 'REQUEST_ID',
        'Request-Body-File'   => 'REQUEST_BODY_FILE',
        'Redirect-Status'     => 'REDIRECT_STATUS',
    );

	public static function init(){
		static $init = false;
		if($init) return;
		foreach ($_SERVER as $key=>$val){
			$key = strtoupper($key);
			if(!array_key_exists($key,$_SERVER)) continue;
			$_SERVER[$key]= $val;
		}
		foreach (self::$_headers as $key=>$keyRequest){
			if(!array_key_exists($key,$_SERVER)) continue;
			$_SERVER[$key]= $_SERVER[$keyRequest];
			$_SERVER[strtoupper($key)]= $_SERVER[$keyRequest];
		}
	}
	
	
	public static function get($key){
		self::init();
		return $_SERVER[$key] ? $_SERVER[$key] : $_SERVER['HTTP_'.strtoupper($key)];
	}
	
	
	public static function method(){
		return strtoupper(self::get('REQUEST_METHOD'));
	}
	public static function length(){
		$result = self::get('X-Expected-Entity-Length');
		if (!$result) {
			$result = self::get('Content-Length');
		}
		return $result;
	}
	public static function range(){
		$range = self::get('Range');
        if (!$range) return false;
        if (!preg_match('/^bytes=([0-9]*)-([0-9]*)$/i', $range, $matches)) return false;
        if ($matches[1] === '' && $matches[2] === '') return false;
        
        return array(
            $matches[1] !== '' ? $matches[1] : null,
            $matches[2] !== '' ? $matches[2] : null,
        );
	}
	
    public static $statusCode = array(
    	/**
    	https://www.cnblogs.com/chengkanghua/p/11314230.html
    	
    	1xx 临时响应;用于指定客户端应相应的某些动作
		2xx 成功;用于表示请求成功
		3xx 重定向;表示要完成请求，需要进一步操作
		4xx 请求错误; 表示请求可能出错，妨碍了服务器的处理
		5xx 服务器错误;服务器处理请求时内部错误
		*/
		'100' => 'Continue',
		'101' => 'Switching Protocol',
		'102' => 'Processing',
		'103' => 'Early Hints',
		
        '200' => 'OK',
        '201' => 'Created',
        '202' => 'Accepted',
        '203' => 'Non-Authoritative Information',
        '204' => 'No Content',
        '205' => 'Reset Content',
        '206' => 'Partial Content',
        '207' => 'Multi-Status',
        
        '300' => 'Multiple Choices',
        '301' => 'Moved Permanently',
        '302' => 'Found',
        '303' => 'See Other',
        '304' => 'Not Modified',
        '305' => 'Use Proxy',
        '307' => 'Temporary Redirect',
        '308' => 'Permanent Redirect',
        
        '400' => 'Bad Request',
        '401' => 'Unauthorized',
        '402' => 'Payment Required',
        '403' => 'Forbidden',
        '404' => 'Not Found',
        '405' => 'Method Not Allowed',
        '406' => 'Not Acceptable',
        '407' => 'Proxy Authentication Required',
        '408' => 'Request Timeout',
        '409' => 'Conflict',
        '410' => 'Gone',
        '411' => 'Length Required',
        '412' => 'Precondition Failed',
        '413' => 'Request Entity Too Large',
        '414' => 'Request URI Too Large',
        '415' => 'Unsupported Media Type',
        '416' => 'Requested Range Not Satisfiable',
        '417' => 'Expectation Failed',
        '422' => 'Unprocessable Entity',
        '423' => 'Locked',
        '424' => 'Failed Dependency',
        '425' => 'Unordered Collection',
        '426' => 'Upgrade Required',
        '428' => 'Precondition Required',
        '429' => 'Too Many Requests',
        '431' => 'Request Header Fields Too Large',
		'444' => 'No Response',
		'450' => 'Blocked by Windows Parental Controls',
		'451' => 'Unavailable For Legal Reasons',
		'494' => 'Request Header Too Large',
        
        
        '500' => 'Internal Server Error',
        '501' => 'Not Implemented',
        '502' => 'Bad Gateway',
        '503' => 'Service Unavailable',
        '504' => 'Gateway Timeout',
        '505' => 'HTTP Version not supported',
        '507' => 'Insufficient Storage',
    );
    public static function code($code){
    	$code   = $code.'';
    	$result = self::$statusCode[$code];
    	$result = $result ? "HTTP/1.1 $code ".$result : '';
        return $result;
    }
}