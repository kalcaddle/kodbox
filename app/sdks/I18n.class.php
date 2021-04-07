<?php

function LNG($key){
	static $isInit = false;
	if (func_num_args() == 1) {
        return I18n::get($key);
	} else {
		$args = func_get_args();
		array_shift($args);
        return vsprintf(I18n::get($key), $args);
	}
}

class I18n{
	private static $loaded = false;
	private static $lang   = NULL;
	public  static $langType = NULL;
	public static function load(){}
	public static function defaultLang(){
		if(isset($GLOBALS['config']['settings']['language'])){
			return $GLOBALS['config']['settings']['language'];
		}
		$langDefault = 'zh-CN';//zh-CN en;
		$lang  = $langDefault;
		$arr   = $GLOBALS['config']['settingAll']['language'];
		$langs = array();
		foreach ($arr as $key => $value) {
			$langs[$key] = $key;
		}
		$langs['zh'] = 'zh-CN';	//增加大小写对应关系
		$langs['zh-tw'] = 'zh-TW';

		$acceptLanguage = array();
		if(!isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])){
			$httpLang = $langDefault;
		}else{
			$httpLang = str_replace("_","-",strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE']));
		}
		preg_match_all('~([-a-z]+)(;q=([0-9.]+))?~',$httpLang,$matches,PREG_SET_ORDER);
		foreach ($matches as $match) {
			$acceptLanguage[$match[1]] = (isset($match[3]) ? $match[3] : 1);
		}
		arsort($acceptLanguage);
		foreach ($acceptLanguage as $key => $q) {
			if (isset($langs[$key])) {
				$lang = $langs[$key];break;
			}
			$key = preg_replace('~-.*~','', $key);
			if (!isset($acceptLanguage[$key]) && isset($langs[$key])) {
				$lang = $langs[$key];break;
			}
		}
		return $lang;
	}

	public static function getAll(){
		self::init();
		return self::$lang;
	}
	public static function getType(){
		self::init();
		return self::$langType;
	}

	public static function init(){
		if(isset($GLOBALS['in']['language'])){
			return self::setLanguage($GLOBALS['in']['language']);
		}
    	if(self::$loaded !== false) return;
	    $cookieLang = 'kodUserLanguage';
		if (isset($_COOKIE[$cookieLang])) {
			$lang = $_COOKIE[$cookieLang];
		}else{
			$lang = self::defaultLang();
		}
		//兼容旧版本
		if($lang == 'zh_CN') $lang = 'zh-CN';
		if($lang == 'zh_TW') $lang = 'zh-TW';

		if(isset($GLOBALS['config']['settings']['language'])){
			$lang = $GLOBALS['config']['settings']['language'];
		}
		self::setLanguage($lang);
	}

	private static function setLanguage($lang){
		if(!preg_match('/^[0-9a-zA-z_\-]+$/', $lang)){
			$lang = 'zh-CN';
		}
		$langFile = LANGUAGE_PATH.$lang.'/index.php';
		if(!file_exists($langFile)){//allow remove some I18n folder
			$lang = 'zh-CN';
			$langFile = LANGUAGE_PATH.$lang.'/index.php';
		}

		self::$langType = $lang;
		self::$lang = include($langFile);
		self::$loaded = true;
		$GLOBALS['L'] = &self::$lang;
	}

	public static function get($key){
		self::init();
		if(!isset(self::$lang[$key])) return $key;
		if (func_num_args() == 1) {
	        return self::$lang[$key];
		} else {
			$args = func_get_args();
			array_shift($args);
	        return vsprintf(self::$lang[$key], $args);
		}
	}

	/**
	 * 添加多语言;
	 * @param [type] $args [description]
	 */
	public static function set($array){
		self::init();
		if(!is_array($array)) return;
		foreach ($array as $key => $value) {
			self::$lang[$key] = $value;
		}
	}
}
