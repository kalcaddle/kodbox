<?php
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/

define('GLOBAL_DEBUG',0);//0 or 1
@set_time_limit(3600);//60min pathInfoMuti,search,upload,download...
@ini_set("max_execution_time",3600);//3600
@ini_set('request_terminate_timeout', 3600);
@ini_set('memory_limit','500M');//
@ini_set('session.cache_expire',1800);

@ini_set('date.timezone', 'Asia/Shanghai');
@date_default_timezone_set('Asia/Shanghai');
// @date_default_timezone_set(@date_default_timezone_get());
// $f="/Library/WebServer/Documents/localhost/kod/doc/tools/xhprof/load.php";if(file_exists($f)){include($f);}

if(GLOBAL_DEBUG){
	// php8 array的key未定义从notice更改为了warning;
	@ini_set("display_errors","on");
	@error_reporting(E_ALL^E_NOTICE^E_WARNING^E_DEPRECATED^E_STRICT);
	define("STATIC_DEV",0);
}else{
	@ini_set("display_errors","on");//on off;
	@error_reporting(E_ALL^E_NOTICE^E_WARNING^E_DEPRECATED^E_STRICT);//0
	define("STATIC_DEV",0);
}

//header('HTTP/1.1 200 Ok');//兼容部分lightHttp服务器环境; php5.1以下会输出异常；暂屏蔽
header("Content-type: text/html; charset=utf-8");
define('BASIC_PATH',str_replace('\\','/',dirname(dirname(__FILE__))).'/');
define('LIB_DIR',       BASIC_PATH .'app/');     	//系统库目录
define('PLUGIN_DIR',    BASIC_PATH .'plugins/');	//插件目录
define('CONTROLLER_DIR',LIB_DIR .'controller/'); 	//控制器目录
define('MODEL_DIR',     LIB_DIR .'model/');  		//模型目录
define('TEMPLATE',      LIB_DIR .'template/');   	//模版文件路径
define('FUNCTION_DIR',	LIB_DIR .'function/');		//函数库目录
define('CLASS_DIR',		LIB_DIR .'kod/');			//工具类目录
define('CORER_DIR',		LIB_DIR .'core/');			//核心目录
define('SDK_DIR',		LIB_DIR .'sdks/');			//
define('DEFAULT_PERRMISSIONS',0755);	//新建文件、解压文件默认权限，777 部分虚拟主机限制了777
define('MEMORY_LIMIT_ON', function_exists('memory_get_usage'));
define("TIME",time());
define("TIME_FLOAT",microtime(true));
include('const.php');
$__SERVER = $_SERVER;

if(file_exists(BASIC_PATH.'config/define.php')){
	include(BASIC_PATH.'config/define.php');
}
if(!defined('DATA_PATH')){
	define('DATA_PATH',BASIC_PATH .'data/');       //用户数据目录
}
define('USER_SYSTEM',   DATA_PATH .'system/');      //用户数据存储目录
define('TEMP_PATH',     DATA_PATH .'temp/');        //临时目录
define('TEMP_FILES',    TEMP_PATH .'files/');       //临时缓存文件
define('LOG_PATH',      TEMP_PATH .'log/');         //日志
define('DATA_THUMB',    TEMP_PATH .'thumb/');       //缩略图生成存放
define('LANGUAGE_PATH', BASIC_PATH .'config/i18n/');//多语言目录
define('KOD_SITE_ID',substr(md5(BASIC_PATH),0,5));
define('SESSION_ID','KOD_SESSION_ID');
define('REQUEST_METHOD',strtoupper($_SERVER['REQUEST_METHOD']));

include(FUNCTION_DIR.'common.function.php');
include(FUNCTION_DIR.'web.function.php');
include(FUNCTION_DIR.'file.function.php');
include(FUNCTION_DIR.'helper.function.php');
include(FUNCTION_DIR.'think.function.php');
include(BASIC_PATH.'config/version.php');

check_enviroment();
include(LIB_DIR.'autoload.php');

$config['jsonpAllow']	= true;
$config['appCharset']	= 'utf-8';//该程序整体统一编码
$config['checkCharset'] = 'ASCII,UTF-8,GB2312,GBK,BIG5,UTF-16,UCS-2,'.
		'Unicode,EUC-KR,EUC-JP,SHIFT-JIS,EUCJP-WIN,SJIS-WIN,JIS,LATIN1';//文件打开自动检测编码
$config['checkCharsetDefault'] = '';//if set,not check;
//when edit a file ;check charset and auto converto utf-8;
if (strtoupper(substr(PHP_OS, 0,3)) === 'WIN') {
	$config['systemOS']='windows';
	$config['systemCharset']='gbk';// EUC-JP/Shift-JIS/BIG5  //user set your server system charset
	if(version_compare(phpversion(), '7.1.0', '>=')){//7.1 has auto apply the charset
		$config['systemCharset']='utf-8';
	}
} else {
	$config['systemOS']='linux';
	$config['systemCharset']='utf-8';
}

if(!defined('HOST')){		define('HOST',rtrim(get_host(),'/').'/');}
if(!defined('WEB_ROOT')){	define('WEB_ROOT',webroot_path(BASIC_PATH) );}
if(!defined('APP_HOST')){	define('APP_HOST',HOST.str_replace(WEB_ROOT,'',BASIC_PATH));} //程序根目录

include(BASIC_PATH.'config/setting.php');
init_common();
$config['autorun'] = array(
	'user.index.init',
	'user.index.maintenance',
	'user.authRole.autoCheck',
	'user.authPlugin.autoCheck',
	'explorer.auth.autoCheck',
	'admin.autoRun.index'
);