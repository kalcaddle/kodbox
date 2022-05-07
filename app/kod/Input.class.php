<?php 

class Input{
	/**
	 * 检测并获取传入参数;
	 * 
	 * key为字段名; value: 
	 * 		default: 为空时默认值;default 为null时 不返回该key
	 * 		aliasKey:将传入的字段映射为对应别名；
	 * 		msg:   错误时提示内容
	 * 		check: 检测类型; 含有该项并不为空时依照条件进行检测  in/json  param
	 */
	public static function getArray($array){
		global $in;
		$result = array();
		$error  = array();
		$msgCommon	= LNG("common.invalidParam");
		foreach ($array as $key => $value) {
			$msg 	 = _get($value,'msg', $msgCommon.': '.$key ); // 错误提示
			$itemKey = $key;
			if(isset($value['aliasKey']) && $value['aliasKey']){
				$itemKey = $value['aliasKey'];
			}
			//!isset($in[$key])  替换
			if( !array_key_exists($key,$in) ){				
				//设置了默认值则使用默认值;为null时 不返回该key
				if( array_key_exists("default",$value) ){
					if( !is_null($value["default"]) ){
						$result[$itemKey] = $value["default"];
					}
				}else if(isset($value['check'])){//只要设置了check,则该值不为空
					$error[] = $msg;
				}
				continue;
			}
			
			// json 单独处理
			if( isset($value['check']) && $value['check'] == 'json' ){
				$decode = json_decode($in[$key],true);
				if( is_array($decode) ){
					$result[$itemKey] = $decode;
				}else{
					if( array_key_exists("default",$value) ){
						if( !is_null($value["default"]) ){
							$result[$itemKey] = $value["default"];
						}				
					}else{
						$error[] = $msg;
					}
				}
				continue;
			}

			$param = _get($value,'param');
			if( isset($value['check']) && !self::check($in[$key],$value['check'],$param) ){
				if( array_key_exists("default",$value) ){
					if( !is_null($value["default"]) ){
						$result[$itemKey] = $value["default"];
					}				
				}else{
					$error[] = $msg;
				}
				continue;
			}
			$result[$itemKey] = $in[$key];
		}
		if(count($error) > 0 ){
			show_json(implode(";\n",$error),false);
		}
		return $result;
	}
	
	public static function reg($type='require'){
		static $checkReg = array(
            'require'	=> ".+",
			'number' 	=> "\d+",
			'integer'   => "\d+",
			'hex'		=> "[0-9A-Fa-f]+",
			'int'	 	=> "[-\+]?\d+",
			'bool'	 	=> "0|1",
			'float' 	=> "[-\+]?\d+(\.\d+)?",
			'english' 	=> "[A-Za-z ]+",
			'chinese'	=> "[\x{4e00}-\x{9fa5}]+",					//全中文
			'hasChinese'=> "/([\x{4e00}-\x{9fa5}]+)/u",				//含有中文
			
			'email' 	=> "\w+([\.\-]\w+)*\@\w+([\.\-]\w+)*\.\w+",	//邮箱
			'phone' 	=> "1[3-9]\d{9}",							//手机号 11位国内手机;
			'telphone'	=> "(\(\d{3,4}\)|\d{3,4}-|\s)?\d{7,14}",	//固定电话,
			'url' 		=> "(http|ftp|https):\/\/[\w\-_]+(\.[\w\-_]+)+([\w\-\.,@?^=%&:\/~\+#]*[\w\-\@?^=%&\/~\+#])?",	//互联网网址
            'urlFull' 	=> "[a-zA-z]+:\/\/[^\s]*",					//广义网址
			'ip'		=> "(\d{1,3}\.){3}(\d{1,3})",				//ip地址
			'zip' 		=> "[1-9]\d{5}(?!\d)",						//邮编
			'idCard'	=> "(\d{15})|(\d{17}(\d|X|x))",				//身份证 15位或18位身份证,18位末尾可能为x或X
			'color'		=> "#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})",		//16进制颜色 #fff,#fafefe,#fff;
			
			'time'		=> "([0-1]\d|2[0-4]):[0-5]\d",				//时间,	02:23,15:59,24
			'date'		=> "\d{4}[-\/]?(0[1-9]|1[0-2])[-\/]?([0-2]\d|3[0-1])",//年月日,  20150102 2015/01/02 2015-01-02;
			'dateTime'	=> "\d{4}[-\/]?(0[1-9]|1[0-2])[-\/]?([0-2]\d|3[0-1])\s+([0-1]\d|2[0-4]):[0-5]\d",
							//年-月-日时分秒,	2015-01-02 
			
			'password'	=> "(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,20}",	//强密码，必须包含数字、大小写字符；不能包含特殊字符；8-20位
			'key'		=> "[A-Za-z0-9_\-\.]+",		//数据库字段或表 [A-Za-z0-9_\.]+
			'keyFull'	=> "[A-Za-z0-9_\-\.\s,]+",	//数据库字段或表，支持多key；加入空格逗号
		);
		//多国手机匹配,https://github.com/chriso/validator.js/blob/master/src/lib/isMobilePhone.js
		if(!$type){
			return $checkReg;
		}
		return $checkReg[$type];
	}

	/**
	 * 检查某个数据是否符合某个规则
	 * $check : 正则名称或者正则表达式;
	 */
	public static function check($value,$check,$param = null){
		//其他规则
		switch($check){
			case 'in':		return in_array($value,$param);break;
			case 'bigger':	return floatval($value)>$param;break;
			case 'smaller':	return floatval($value)<$param;break;
			case 'length':	return strlen($value)>=$param[0] && strlen($value)<=$param[1];break;
			case 'length':	
				if(is_array($param)){
					return strlen($value)>=$param[0] && strlen($value)<=$param[1];break;
				}else{
					return strlen($value)==$param;break;
				}
			case 'between':	return floatval($value)>=$param[0] && floatval($value)<=$param[1];break;
		}
		
		// 检查是否有内置的正则表达式
		$reg   = self::reg(false);
		$check = isset($reg[$check]) ? $reg[$check] : $check;
		
		//含有/ 则认为是完整的正则表达式，不自动追加
		if(substr($check,0,1) != '/'){
			$check = '/^'.$check.'$/su';//匹配多行
		}
        return preg_match($check,$value) === 1;
	}

	/**
	 * 获取某个参数值
	 */
	public static function get($key,$check=null,$default=null,$param=null){
		$item = array();
		if(!is_null($default)) $item['default'] = $default;
		if(!is_null($param))   $item['param'] = $param;
		if(!is_null($check))   $item['check'] = $check;
		$data = Input::getArray(array($key => $item));
		return $data[$key];
	}
}

