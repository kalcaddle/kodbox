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
		return self::parseAddress($address['region']);
	}
	
	public static function parseAddress($address){
		static $addressReplace;
		$replaceFrom = array('中国|','0|','|','省 ');
		$replaceTo   = array('','',' ','省');
		$lang = I18n::getType();
		if( strstr($lang,'zh') ){
			$address = str_replace($replaceFrom,$replaceTo,$address);
			$address = str_replace('内网IP 内网IP','内网IP',$address);
			return $address;
		}

		if(!$addressReplace){
			$addressMap  = require(dirname(__FILE__).'/Ip2Region/'.'Address.lang.php');
			$replaceFrom = array_keys($addressMap);$replaceTo = array();
			usort($replaceFrom,'strLengthSort');$replaceFrom = array_reverse($replaceFrom);
			foreach($replaceFrom as $key){
				$replaceTo[] = $addressMap[$key];
			}
			$addressReplace['from'] = $replaceFrom;
			$addressReplace['to'] = $replaceTo;
		}
		$replaceFrom = $addressReplace['from'];
		$replaceTo = $addressReplace['to'];
		$address = str_replace(array('0|','|0','|'),array(',',',',','),$address);
		$address = str_replace(',,',',',$address);
		$address = trim($address,'0');$address = trim($address,',');
		$address = str_replace($replaceFrom,$replaceTo,$address);
		$address = str_replace('Local IP,Local IP','Local IP',$address);
		return $address;
	}
}

function strLengthSort($a,$b){
    $la = strlen( $a); $lb = strlen( $b);
    if( $la == $lb) return strcmp( $a,$b);
    return $la - $lb;
}