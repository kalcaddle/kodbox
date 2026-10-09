<?php

// 登录认证;
include('../../../app/api/KodSSO.class.php');
KodSSO::check('user:admin');// 必须系统管理员才可用; 'adminer'  'user:admin'
KodSSO::check('adminer'); 	// 系统管理员,可能开启三权分立,则会做检测;
@error_reporting(E_ALL^E_NOTICE^E_WARNING^E_DEPRECATED^E_STRICT);
$sessionPath = '/tmp/session/';
@ini_set('session.save_handler', 'files');
if(file_exists('/tmp') && !file_exists($sessionPath)){@mkdir($sessionPath,0777);}
if(file_exists($sessionPath) && is_writable($sessionPath)){session_save_path($sessionPath);}


/**
 * 5.51 https://github.com/vrana/adminer/releases
 * 修改1: `error_reporting` (去除调用);
 * 
 * 扩展: 
 * headers:  X-Frame-Options 去除不允许ifram限制; 404替换,数据库名不存在时,避免header-404被nginx拦截;多语言异常时处理;
 * head:     增加自定义js加载,增加meta viewport;
 * editInput: varchar字段值 input=> textarea;
 * tableName:  显示表名时,增加表注释显示;
 * selectVal:  表数据结果字段值展示, 时间戳显示优化;
 */
function adminer_object() {
	class AdminerSoftware extends Adminer\Adminer {
		function headers() {
			header("X-Frame-Options: SameOrigin");
			header("X-XSS-Protection: 0");
			header("Content-Security-Policy:0");
			if (function_exists('header_remove')) {
				@header_remove("X-Frame-Options");
				@header_remove("Content-Security-Policy");
				@header_remove("HTTP/1.1 404 Not Found"); // 数据库名不存在时,避免header-404被nginx拦截;
			}
			header("HTTP/1.1 200 OK");
			
			// 中文等多语言异常情况;部分php7.1环境解码异常情况; // debug;
			if(0 || !is_array($_SESSION['translations']) || count($_SESSION['translations']) < 10){
				$langDefault = Adminer\decompress_string(Adminer\get_compressed("en"));
				Adminer\Lang::$translations = explode("\n", $langDefault);
				// $langStr = Adminer\decompress_string(Adminer\get_compressed('zh'),$langDefault);
				// var_dump($langStr,Adminer\Lang::$translations,$_SESSION);exit;
			}
			return false;
		}
		function head($Mb = null){
			$host = KodSSO::appHost();
			echo '<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover, shrink-to-fit=no" />';
			echo '<script>var kodSdkConfig = {api:"'.$host.'"};</script>';
			echo '<script src="'.$host.'static/app/dist/sdk.js"></script>';
			echo '<script type="text/javascript" src="./adminer.js"></script>';
			return true;
		}
		// 编辑条数据,varchar字段值 input=> textarea;
		function editInput($table,array $field,$attrs,$value){
			if (preg_match('~char~', $field["type"])) {
				return "<textarea cols='40' rows='2'$attrs>" . Adminer\h($value) . '</textarea>';
			}
		}
		
		function tableName(array $tableStatus){
			return $tableStatus["Name"];
			$desc = $tableStatus["Comment"];
			if($desc && strtolower($desc) != strtolower($tableStatus["Name"])){$desc = '<i class="table-desc">'.$desc.'</i>'; }
			return $tableStatus["Name"].$desc;
		}
		// 表数据结果字段值展示, 时间戳显示优化;// 2001 ~ 2065;
		function selectVal($val,$link,array $field,$original){
			$isTime = strlen($val.'') == 10 && is_numeric($val) && (substr($val.'',0,1) == '1' || substr($val.'',0,1) == '2');
			$fieldLikeTime = stristr($field['field'],'time') || stristr($field['field'],'date') || stristr($field['field'],'last');
			// var_dump($val, $link, $field, $original);exit;
			if($isTime && !$fieldLikeTime && $field['field'] == 'value'){$fieldLikeTime = true;}
			if($isTime && $fieldLikeTime){
				return '<div class="field-value-show field-time">'.$val.'</div>'.'<div class="field-value-desc field-time">'.date('Y-m-d H:i:s', $val).'</div>';
			}
			return parent::selectVal($val,$link,$field,$original);
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

ini_set('display_errors','on');
include('./adminer.php.txt');