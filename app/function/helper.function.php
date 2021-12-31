<?php

if(!function_exists('gzinflate')){
    show_tips("不支持gzinflate ,<br/>请安装php-zlib 扩展后再试");exit;
}

//扩展名权限判断 有权限则返回1 不是true
function checkExt($file){
	if(_get($GLOBALS,'isRoot')) return 1;
	if(strstr($file,'<') || strstr($file,'>') || $file=='') {
		return 0;
	}
	
	//'php|phtml|phtm|pwml|asp|aspx|ascx|jsp|pl|htaccess|shtml|shtm'
	$notAllow = strtolower($GLOBALS['auth']['extNotAllow']);
	$extArr = explode('|',$notAllow);
	if(in_array('asp',$extArr)){
		$extArr = array_merge($extArr,array('aspx','ascx','pwml'));
	}
	if(in_array('php',$extArr)){
		$extArr = array_merge($extArr,array('phtml','phtm','htaccess','pwml'));
	}
	if(in_array('htm',$extArr) || in_array('html',$extArr)){
		$extArr = array_merge($extArr,array('html','shtml','shtm','html'));
	}
	foreach ($extArr as $current) {
		if ($current !== '' && stristr($file,'.'.$current)){//含有扩展名
			return 0;
		}
	}
	return 1;
}
function checkExtSafe($file){
	if($file == '.htaccess' || $file == '.user.ini') return false;
	if(strstr($file,'<') || strstr($file,'>') || $file=='') return false;
	$disable  = 'php|phtml|phtm|pwml|asp|aspx|ascx|jsp|pl|html|htm|svg|shtml|shtm';
	$extArr = explode('|',$disable);
	foreach ($extArr as $ext) {
		if ($ext && stristr($file,'.'.$ext)) return false;
	}
	return true;
}

function linkHref($src,$dev=false){
	if($dev){echo STATIC_PATH.$src.'?v='.time(); return;}
	$static  = STATIC_PATH;
	$version = GLOBAL_DEBUG ? time() : KOD_VERSION.'.'.KOD_VERSION_BUILD;//debug;
	echo $static . $src . '?v='.$version;
}
function urlApi($api='',$param=''){
	$host = appHostGet(); $and = '';
	if($param === null ) return $host.$api;
	$and = !strstr($host,'?') ? '?' : '&';
	return $host.$api.$and.$param;
}
function appHostGet(){
	static $host = false;
	if($host) return $host;

	$appHost = rtrim(APP_HOST,'/').'/';
	$resultFile  = DATA_PATH .'system/rewrite.lock';
	if(file_exists($resultFile)){
		$split = @file_get_contents($resultFile);
	}else{
		//避免部分apache url该情况301跳转;
		$split    = 'index.php?';// 默认:'index.php?'; 省略入口:'?'; 伪静态:''; 指定:'i';
		$checkUrl = $appHost.'app/function/?sitemap/urlType&v=1';
		// 安装调用时，扩展可能还未安装
		if(ini_get('allow_url_fopen')) {
			$opts = array('http'=>array('method'=>'GET','timeout'=>0.5));
			$context = stream_context_create($opts);
			$data = file_get_contents($checkUrl, false, $context);
		}else if(function_exists('curl_init')) {
			$data = url_request($checkUrl,0,0,0,0,0,0.5);
			$data = !empty($data['data']) ? $data['data'] : '';
		}
		if(trim($data) == '[ok]') {
			$split = '?';
		}
		@file_put_contents($resultFile,$split);
	}
	return $appHost.$split;
}

//-----解压缩跨平台编码转换；自动识别编码-----
//压缩前，文件名处理；
//ACT=zip——压缩到当前
//ACT=zipDownload---打包下载[判断浏览器&UA——得到地区自动转换为目标编码]；
function zip_pre_name($fileName,$toCharset=false){
	Hook::trigger('zip.nameParse',$fileName);
	if(get_path_this($fileName) == '.DS_Store') return '';//过滤文件
	if (!function_exists('iconv')){
		return $fileName;
	}
	$charset = $GLOBALS['config']['systemCharset'];
	if($toCharset == false){//默认从客户端和浏览器自动识别
		$toCharset = 'utf-8';
		$clientLanugage = I18n::defaultLang();
		$langType = I18n::getType();
		if( client_is_windows() && (
			$clientLanugage =='zh-CN' || 
			$clientLanugage =='zh-TW' || 
			$langType =='zh-CN' ||
			$langType =='zh-TW' )
		){
			$toCharset = "gbk";//压缩或者打包下载压缩时文件名采用的编码
		}
	}

	//write_log("zip:".$charset.';'.$toCharset.';'.$fileName,'zip');
	$result = iconv_to($fileName,$charset,$toCharset);
	if(!$result){
		$result = $fileName;
	}
	return $result;
}

//解压缩文件名检测
function unzip_filter_ext($name){
	$add = '.txt';
	if( checkExt($name) &&
		!stristr($name,'user.ini') &&
		!stristr($name,'.htaccess')
	){//允许
		return $name;
	}
	return $name.$add;
}
//解压到kod，文件名处理;识别编码并转换到当前系统编码
function unzip_pre_name($fileName){
	Hook::trigger('unzip.nameParse',$fileName);
	$fileName = str_replace(array('../','..\\',''),'',$fileName);
	if (!function_exists('iconv')){
		return unzip_filter_ext($fileName);
	}
	if(isset($GLOBALS['unzipFileCharsetGet'])){
		$charset = $GLOBALS['unzipFileCharsetGet'];
	}else{
		$charset = get_charset($fileName);
	}
	$toCharset = $GLOBALS['config']['systemCharset'];
	$result = iconv_to($fileName,$charset,$toCharset);
	if(!$result){
		$result = $fileName;
	}
	$result = unzip_filter_ext($result);
	//echo $charset.'==>'.$toCharset.':'.$result.'==='.$fileName.'<br/>';
	return $result;
}

// 获取压缩文件内编码
// $GLOBALS['unzipFileCharsetGet']
function unzip_charset_get($list){
	if(!is_array($list) || count($list) == 0) return 'utf-8';
	$charsetArr = array();
	for ($i=0; $i < count($list); $i++) { 
		$charset = get_charset($list[$i]['filename']);
		if(!isset($charsetArr[$charset])){
			$charsetArr[$charset] = 1;
		}else{
			$charsetArr[$charset] += 1;
		}
	}
	arsort($charsetArr);
	$keys = array_keys($charsetArr);

	if(in_array('gbk',$keys)){//含有gbk,则认为是gbk
		$keys[0] = 'gbk';
	}
	$GLOBALS['unzipFileCharsetGet'] = $keys[0];
	return $keys[0];
}

function charset_check(&$str,$check,$tempCharset='utf-8'){
	if ($str === '' || !function_exists("mb_convert_encoding")){
		return false;
	}
	$testStr1 = @mb_convert_encoding($str,$tempCharset,$check);
	$testStr2 = @mb_convert_encoding($testStr1,$check,$tempCharset);
	if($str == $testStr2){
		return true;
	}
	return false;
}

//https://segmentfault.com/a/1190000003020776
//http://blog.sina.com.cn/s/blog_b97feef301019571.html
function get_charset(&$str) {
	if($GLOBALS['config']['checkCharsetDefault']){//直接指定编码
		return $GLOBALS['config']['checkCharsetDefault'];
	}
	if ($str === '' || !function_exists("mb_detect_encoding")){
		return 'utf-8';
	}
	$bom_arr = array(
		'utf-8'		=> chr(0xEF) . chr(0xBB) .chr(0xBF),
		'utf-16le' 	=> chr(0xFF) . chr(0xFE),
		'utf-16be' 	=> chr(0xFE) . chr(0xFF),
		'utf-32le' 	=> chr(0xFF) . chr(0xFE) . chr(0x00) . chr(0x00),
		'utf-32be' 	=> chr(0x00) . chr(0x00) . chr(0xFE) . chr(0xFF),
	);
	foreach ($bom_arr as $key => $value) {
		if (substr($str,0,strlen($value)) === $value ){
			return $key;
		}
	}

	//前面检测成功则，自动忽略后面
	$charset=strtolower(@mb_detect_encoding($str,$GLOBALS['config']['checkCharset']));
	$charsetGet = $charset;
	if ($charset == 'cp936'){
		// 有交叉，部分文件无法识别
		if(charset_check($str,'ISO-8859-4') && !charset_check($str,'gbk') && !charset_check($str,'big5')){
			$charset = 'ISO-8859-4';
		}elseif(charset_check($str,'gbk') && !charset_check($str,'big5')){
			$charset = 'gbk';
		}else if(charset_check($str,'big5')){
			$charset = 'big5';
		}
	}else if ($charset == 'euc-cn'){
		$charset = 'gbk';
	}else if ($charset == 'ascii'){
		$charset = 'utf-8';
	}
	if ($charset == 'iso-8859-1'){
		//检测详细编码;value为用什么编码检测；为空则用utf-8
		$check = array(
			'utf-8'       => $charset,
			'utf-16'      => 'gbk',
			'iso-8859-1'  => 'utf-8',
		);
		foreach($check as $key => $val){
			if(charset_check($str,$key,$val)){
				if($val == ''){
					$val = 'utf-8';
				}
				$charset = $key;
				break;
			}
		}
	}else if ($charset == 'sjis-win'){
		if(charset_check($str,'iso-8859-1')){$charset = 'iso-8859-1';}
	}
	// pr(charset_check($str,'utf-8'),charset_check($str,'iso-8859-1'),$charset,$charsetGet);exit;
	return $charset;
}

/**
 * 服务器相关环境
 * 检测环境是否支持升级版本
 */
function serverInfo(){
	$lib = array(
		"sqlit3"=>intval( class_exists('SQLite3') ),
		"sqlit" =>intval( extension_loaded('sqlite') ),
		"curl"	=>intval( function_exists('curl_init') ),
		"pdo"	=>intval( class_exists('PDO') ),
		"mysqli"=>intval( extension_loaded('mysqli') ),
		"mysql"	=>intval( extension_loaded('mysql') ),
	);
	$libStr = "";
	foreach($lib as $key=>$val){
		$libStr .= $key.'='.$val.';';
	}
	$system = explode(" ", php_uname());
	$env = array(
		"sys"   => strtolower($system[0]),
		"php"	=> floatval(PHP_VERSION),
		"server"=> $_SERVER['SERVER_SOFTWARE'],
		"lib"	=> $libStr,
		"bit" 	=> PHP_INT_SIZE,
		"info"	=> php_uname().';php='.PHP_VERSION,
	);
	$result = str_replace("\/","@",json_encode($env));
	return $result;
}


/**
 * 获取可以上传的最大值
 * return * byte
 */
function get_post_max(){
	$upload = ini_get('upload_max_filesize');
	$upload = $upload==''?ini_get('upload_max_size'):$upload;
	$post = ini_get('post_max_size');
	$upload = floatval($upload)*1024*1024;
	$post = floatval($post)*1024*1024;
	$theMax = $upload<$post?$upload:$post;
	return $theMax;
}

function phpBuild64(){
	if(PHP_INT_SIZE === 8) return true;//部分版本,64位会返回4;
	ob_clean();
	ob_start();
	var_dump(12345678900);
	$res = ob_get_clean();
	if(strstr($res,'float')) return false;
	return true;
}


function check_list_dir(){
	$url  = APP_HOST.'app/controller/explorer/';
	@ini_set('default_socket_timeout',1);
	$context = stream_context_create(array('http'=>array('method'=>"GET",'timeout'=>1)));
	$str = @file_get_contents($url,false,$context);
	return stripos($str,"share.class.php") === false;//not find=>true; find=> false;
}

function check_enviroment(){
	I18n::load();
	checkPhp();
	check_version_cache();
}

//提前判断版本是否一致；
function check_version_cache(){
	//检查是否更新失效
	$content = file_get_contents(BASIC_PATH.'config/version.php');
	$result  = match_text($content,"'KOD_VERSION','(.*)'");
	if($result != KOD_VERSION){
		$ver = KOD_VERSION.'==>'.$result;
		show_tips(LNG('common.env.phpCacheOpenTips')."<br/>".$ver);
	}
}
function checkPhp(){
	$version = phpversion();
	if(floatval($version) <  5.3){
		$msg = "<b>php版本不支持:</b> php >= 5.3;<br/><b>当前版本:</b> php version(".$version.');';
		show_tips($msg);exit;
	}
}

function init_cli(){
	if(!stristr(php_sapi_name(),'cli')) return;
	$params = $GLOBALS['argv'][1];
	$paramArr = explode('&',$params);
	foreach ($paramArr as $item) {
		$item = trim($item);
		if(!$item) continue;
		$arr = explode('=',$item);
		$_GET[$arr[0]] = $arr[1] ? rawurldecode($arr[1]):'';
		$_REQUEST[$arr[0]] = $_GET[$arr[0]];
	}
}
// 不允许双引号
function escapeShell($param){
	return escapeshellarg($param);
	//$param = escapeshellarg($param);
	$os = strtoupper(substr(PHP_OS, 0,3));
	if ( $os != 'WIN' && $os != 'DAR') {//linux
		$param = str_replace('!','\!',$param);
	}
	$param = rtrim($param,"\\");
	return '"'.str_replace(array('"',"\0",'`'),'_',$param).'"';
}


function init_common(){
	init_cli();
	$GLOBALS['in'] = parse_incoming();
	$_SERVER['KOD_VERSION'] 	= KOD_VERSION;
	$_SERVER['INSTALL_CHANNEL'] = INSTALL_CHANNEL;
}
function init_check_update(){
	$updateFile = LIB_DIR.'update.php';
	if(!file_exists($updateFile)) return;	
	//覆盖安装文件删除不了重定向问题优化
	$errorTips = LNG('common.env.pathPermissionError') .BASIC_PATH.'</pre>';
	if(!is_writable($updateFile) ) show_tips($errorTips,false);
	include($updateFile);
	del_file($updateFile);
}

// 获取当前php执行目录; 
function phpBinCommand(){
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

//登录是否需要验证码
function need_check_code(){
	if( !Model('SystemOption')->get('needCheckCode') || 
		!function_exists('imagecreatefromjpeg')||
		!function_exists('imagecreatefromgif')||
		!function_exists('imagecreatefrompng')||
		!function_exists('imagecolorallocate')
		){
		return false;
	}else{
		return true;
	}
}

function hash_encode($str) {
	return str_replace(array('+','/','='),array('_a','_b','_c'),base64_encode($str));
}
function hash_decode($str) {
	return base64_decode(str_replace(array('_a','_b','_c'),array('+','/','='),$str));
}

// 目录hash;
function hash_path($path,$addExt=false){
	$password = Model('SystemOption')->get('systemPassword');
	if(!$password){
		$password = 'kodcloud';
	}

	$pre = substr(md5($path.$password),0,8);
	$result = $pre.md5($path);
	if(file_exists($path)){
		$result = $pre.md5($path.filemtime($path));
		if(filesize($path) < 50*1024*1024){
			$fileMd5 = @md5_file($path);
			if($fileMd5){
				$result = $fileMd5;
			}
		}
	}
	if($addExt){
		$result = $result.'.'.get_path_ext($path);
	}
	return $result;
}

// 解析config; 获取数据库类型; mssql,mysql,sqlite;
function getDatabaseType(){
	$database = $GLOBALS['config']['database'];
	$dbType   = strtolower($database['DB_TYPE']);
	if($dbType == 'pdo'){
		$dsnParse = explode(':',$database['DB_DSN']);
		$dbType   = $dsnParse[0];
	}
	$dbType = $dbType == 'mysqli'?  'mysql':$dbType;
	$dbType = $dbType == 'sqlite3'? 'sqlite':$dbType;
	return $dbType;
}

//兼容未安装Ctype扩展服务器;
if(!function_exists('ctype_digit')){
	// https://github.com/ntulip/piwik/blob/master/libs/upgradephp/upgrade.php
	function ctypeCheck($text,$type){
		$ctypePreg = array(
			'ctype_alnum' 	=> "/^[A-Za-z0-9\300-\377]+$/",	// 是否全部为字母和(或)数字字符
			'ctype_alpha' 	=> "/^[a-zA-Z\300-\377]+$/",	// 是否全为小写或全为大写;
			'ctype_digit' 	=> "/^[\d]+$/",					// 检测字符串中的字符是否都是数字，负数和小数会检测不通过,
			'ctype_xdigit' 	=> "/^[a-fA-F0-9]+$/",			// 是否为16进制字符传
			'ctype_cntrl'	=> "/^[\x00-\x1f\x7f]+$/",		// 是否全为控制字符
			'ctype_space' 	=> "/^[\s]+$/",					// 是否全为不可见字符
			'ctype_upper' 	=> "/^[A-Z\300-\337]+$/",		// 是否全为小写字母
			'ctype_lower' 	=> "/^[a-z\340-\377]+$/",		// 是否全为小写字母
			
			'ctype_graph'	=> "/^[\041-\176\241-\377]+$/",	// 
			'ctype_punct'	=> "/^[^0-9A-Za-z\000-\040\177-\240\300-\377]+$/",
		);
		if(!is_string($text) || $text == '') return false;
		return preg_match($ctypePreg[$type],$text,$match);
	}
	function ctype_alnum($text){return ctypeCheck($text,'ctype_alnum');}
	function ctype_alpha($text){return ctypeCheck($text,'ctype_alpha');}
	function ctype_digit($text){return ctypeCheck($text,'ctype_digit');}
	function ctype_xdigit($text){return ctypeCheck($text,'ctype_xdigit');}
	function ctype_cntrl($text){return ctypeCheck($text,'ctype_cntrl');}
	function ctype_space($text){return ctypeCheck($text,'ctype_space');}
	function ctype_upper($text){return ctypeCheck($text,'ctype_upper');}
	function ctype_lower($text){return ctypeCheck($text,'ctype_lower');}

	function ctype_graph($text){return ctypeCheck($text,'ctype_graph');}
	function ctype_punct($text){return ctypeCheck($text,'ctype_punct');}
	function ctype_print($text){return ctype_punct($text) && ctype_graph($text);}
}

// 拆分sql语句
function sqlSplit($sql){
	$num = 0;
	$result = array();
	$sql = str_replace("\r", "\n", $sql);
	$splitArray = explode(";\n", trim($sql."\n"));
	unset($sql);
	foreach($splitArray as $query){
		$result[$num] = '';
		$queryArr = explode("\n", trim($query));
		$queryArr = array_filter($queryArr);
		foreach($queryArr as $query){
			$firstChar = substr($query, 0, 1);
			if($firstChar != '#' && $firstChar != '-'){
				$result[$num] .= $query;
			}
		}
		$num++;
	}
	return $result;
}
