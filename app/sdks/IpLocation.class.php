<?php 

/**
 * ip地址库转换
 * 数据更新:https://gitee.com/lionsoul/ip2region
 */
class IpLocation {
	public static function get($ip){
		static $obj;
		if(!$obj){
			$path = dirname(__FILE__).'/Ip2Region/';
			require $path.'Ip2Region.php';
			$obj = new Ip2Region($path.'Ip2Region.db');
		}
		$address = $obj->memorySearch($ip);// memorySearch/btreeSearch
		$replaceFrom = array('中国|','0|','|','省 ');
		$replaceTo   = array('','',' ','省');
		$address = str_replace($replaceFrom,$replaceTo,$address['region']);
		return $address;
	}
}