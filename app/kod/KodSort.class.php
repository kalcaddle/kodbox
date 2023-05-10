<?php 

/**
 * 自然排序相关处理;
 * 
 * 1.数字从小到大(小数位处理,版本号或ip则填充)
 * 2.字母大小写无关
 * 3.中文拼音排序(多音字根据上下文词语自动选择)
 * 4.中文数字排序;(数字>字母>中文数字>中文)
 */
class KodSort{
	/**
	 * 数组自然排序
	 * 按$field进行排序(若$field为空则字符串数组排序),$fieldDefault作为field相等时额外判断字段
	 * 
	 * arraySort([["name":"f2","status":1],...],'status',false,'name'); // 按数组中字段排序;
	 * arraySort(["f2","f10",'f13'],false,true); // 直接数组排序;
	 */
	public static function arraySort($records,$field='',$orderDesc=false,$fieldMore=false,$useLocal=false){
		if(!is_array($records) || !$records) return array();
		if($fieldMore && $fieldMore == $field){$fieldMore = false;}
		if(count($records) == 1) return $records;
		$order   = $orderDesc ? SORT_DESC : SORT_ASC;
		$flag 	 = SORT_STRING;
		$sortBy1 = array();$sortBy2 = array();
		
		// $useLocal = true;
		// 超过一定数量采用自带自然排序;
		if(count($records) >= 100000 || $useLocal){
			return array_sort_by($records,$field,$orderDesc,$fieldMore);
		}
				
		foreach ($records as $item){
			if(!$field){$sortBy1[] = self::makeStr($item); continue;}
			if($field){
				$val = isset($item[$field]) ? $item[$field] :_get($item,$field,'');
				$sortBy1[]  = self::makeStr($val);
			}
			if($fieldMore){
				$val = isset($item[$fieldMore]) ? $item[$fieldMore] :_get($item,$fieldMore,'');
				$sortBy2[] = self::makeStr($val);
			}
		}
		// trace_log(['222',$field,$order,$fieldMore,$sortBy1,$sortBy2]);
		if($fieldMore){ array_multisort($sortBy1,$order,$flag,$sortBy2,$order,$flag,$records);}
		if(!$fieldMore){array_multisort($sortBy1,$order,$flag,$records);}
		return $records;
	}
	public static function strSort($a,$b){
		$strA = self::makeStr($a);
		$strB = self::makeStr($b);
		return $strA > $strB ? 1 : ($strA == $strB ? 0 :-1);
	}
	
	// 转为可自然排序的字符串
	public static function makeStr($str){
		if($str === '' || $str === null ) return '';
		if(strlen($str) > 128) return $str;
		$strResult = '';$padLength = 15;$start = 0;

		if(preg_match_all("/\d+/",$str,$matchRes)){
			$numberPadArr = self::numberPadArr($str);
			$padPoseMin   = $numberPadArr ? $numberPadArr[0][0]:false;
			$padPoseMax   = $numberPadArr ? $numberPadArr[count($numberPadArr) - 1][1]:false;
		}else{
			$matchRes = array(array());$strResult = $str;
		}
		foreach($matchRes[0] as $find){
			$pose  = strpos($str,$find,$start);
			$end   = $pose + strlen($find);
			$findPad    = str_pad($find,$padLength,"0",STR_PAD_LEFT);
			$strNow     = $strResult.substr($str,$start,$pose - $start);
			$strResult  = $strNow.$findPad;
			// pr("start=$start;find=($find);pose=$pose; pre=(".substr($str,$pose-2,2).")");

			// 前面不是小数,则填充;  前面是小数:
			$start = $end;
			if($pose < 2 || !preg_match("/\d\./",substr($str,$pose-2,2)) ){continue;}
			if(!$numberPadArr){$strResult = $strNow.$find;continue;}
			if($end < $padPoseMin || $pose > $padPoseMax){$strResult = $strNow.$find;continue;}
			
			$needPad = false;
			foreach ($numberPadArr as $range){
				if($pose >= $range[0] && $end <= $range[1]){$needPad = true;break;}
			}
			if(!$needPad){$strResult = $strNow.$find;}
		};
		if($start > 0){$strResult .= substr($str,$start);}
		$match = preg_match("/[\x{4e00}-\x{9fa5}]/u",$strResult,$matchRes);
		if(!$match) return strtolower($strResult);
		
		$regChineseNum = "/(零|一|二|三|四|五|六|七|八|九|十|百|千|万|壹|贰|叁|肆|伍|陆|柒|捌|玖|拾|佰|仟|万|亿)+/";
		$strResult = preg_replace_callback($regChineseNum,'self::chineseNumberMake',$strResult);
		$strResult = Pinyin::get($strResult,'','','~');
		return strtolower($strResult);
	}
	public static function chineseNumberMake($match){
		static $oneChar = array('零'=>1,'拾'=>1,'百'=>1,'千'=>1,'万'=>1,'亿'=>1,'佰'=>1,'仟'=>1);
		$find = $match[0];
		if($oneChar[$find]) return $find;
		$number = self::chineseToNumber($find);
		if($number === -1) return $find;
		return '~'.str_pad($number,15,"0",STR_PAD_LEFT);
	}
	
	// 匹配计算需要填充的片段进行缓存; 版本号或ip则填充; 否则检测小数;
	public static function numberPadArr($str){
		$arr = array();$start = 0; // [[2,5],[20,24],...]
		$regMatch = "/\d+(\.\d+){2,}/i";
		$match = preg_match_all($regMatch,$str,$matchRes);
		if(!$match || !$matchRes[0]) return $arr;
		
		foreach($matchRes[0] as $find){
			$pose  = stripos($str,$find,$start);
			$end   = $pose + strlen($find);
			$arr[] = array($pose,$end,$find);$start = $end;
		}
		return $arr;
	}
	public static function split($str){
		$arr = @preg_split("//u",$str,-1,PREG_SPLIT_NO_EMPTY);
		return is_array($arr) ? $arr : array();
	}
	
	// 阿拉伯数字转中文数字; 不支持小数和正负数, 最长16位(一亿亿-1);
	public static function numberToChinese($str){
		$str    = preg_replace("/[+-]|(\.\d*)/",'',$str.'');
		$result = array('@');$unitNo = 0;$resultStr='';$len = strlen($str);
		$unit   = array('十','百','千','万','十','百','千','亿','十','百','千');
		$numChar= array('零','一','二','三','四','五','六','七','八', '九');
		if($str !== '0'){$str = ltrim($str,'0');}
		if($str == '' || $len > 16) return '';
		if($len == 1){return $numChar[intval($str)] ? $numChar[intval($str)]:'';}
		
		if($len >= 9){
			$halfLeft  = substr($str,0,-8);
			$halfRight = ltrim(substr($str,-8),'0');
			$halfAdd   = '亿';
			if($halfRight && $halfLeft[strlen($halfLeft) - 1] === '0'){$halfAdd = '亿零';}
			if($halfRight && strlen($halfRight) < 8){$halfAdd = '亿零';}
			$resultStr = self::numberToChinese($halfLeft) .$halfAdd. self::numberToChinese($halfRight);
			$resultStr = preg_replace("/亿(一)*亿/","亿",$resultStr);
			return $resultStr;
		}
				
		for ($i = $len - 1;;$i--){
			array_unshift($result,$numChar[$str[$i]]);
			if ($i <= 0) {break;}
			array_unshift($result,$unit[$unitNo]);;$unitNo++;
		}
		$resultStr = implode('',$result);
		$resultStr = preg_replace("/(零(千|百|十)){1,3}/","零",$resultStr);
		$resultStr = preg_replace("/(零){2,}/","零",$resultStr);
		$resultStr = preg_replace("/零(万|亿)/","$1零",$resultStr);
		$resultStr = preg_replace("/(零){2,}/","零",$resultStr);
		$resultStr = preg_replace("/亿万/","亿",$resultStr);
		$resultStr = preg_replace("/^一十/","十",$resultStr);
		$resultStr = preg_replace("/(零)*@/","",$resultStr);
		return $resultStr;
	}
	
	public static $chineseToNumberMap = array(
		"零"=>0,
		"一"=>1,"壹"=>1,
		"二"=>2,"贰"=>2,"两"=>2,
		"三"=>3,"叁"=>3,
		"四"=>4,"肆"=>4,
		"五"=>5,"伍"=>5,
		"六"=>6,"陆"=>6,
		"七"=>7,"柒"=>7,
		"八"=>8,"捌"=>8,
		"九"=>9,"玖"=>9,
		"十"=>10,"拾"=>10,
		"百"=>100,"佰"=>100,
		"千"=>1000,"仟"=>1000,		
		"万"=>10000,"十万"=>100000,"百万"=>1000000,"千万"=>10000000,"亿"=>100000000
	);
	
	// 中文数字转阿拉伯数字; 不支持小数和正负数, 最长16位(一亿亿-1);
	public static function chineseToNumber($str){
		$str = $str.'';if($str != '零'){$str = ltrim($str,'零');}
		$strArr = self::split($str);$len = count($strArr);
		$summary = 0; $map = &self::$chineseToNumberMap;
        if ($len == 0) return -1;
        if ($len == 1) return ($map[$str] <= 10) ? $map[$str] : -1;
        if ($map[$strArr[0]] == 10) {
			$str    = "一".$str;
			$strArr = self::split($str);$len = count($strArr);
		}
        if ($len >= 3 && $map[$strArr[$len-1]] < 10) {
            $lastSecondNum = $map[$strArr[$len-2]];
            if ($lastSecondNum == 100 || $lastSecondNum == 1000 || 
				$lastSecondNum == 10000 || $lastSecondNum == 100000000) {
                foreach ($map as $key) {
                    if ($map[$key] == $lastSecondNum / 10) {
						$str    = $str.$key;
						$strArr = self::split($str);$len = count($strArr);
						break;
                    }
                }
            }
        }
		if(strpos($str,'亿亿') > 0) return -1;
        $splited = explode("亿",$str);
        if ($splited && count($splited) == 2) {
            $rest = $splited[1] == "" ? 0 : self::chineseToNumber($splited[1]);
            return $summary + self::chineseToNumber($splited[0]) * 100000000 + $rest;
        }
		$splited = explode("万",$str);
        if ($splited && count($splited) == 2) {
            $rest = $splited[1] == "" ? 0 : self::chineseToNumber($splited[1]);
            return $summary + self::chineseToNumber($splited[0]) * 10000 + $rest;
        }
        $i = 0;
        while ($i < $len) {
            $firstCharNum = $map[$strArr[$i]];$secondCharNum = $map[$strArr[$i + 1]];
            if ($secondCharNum > 9){$summary += $firstCharNum * $secondCharNum;}
            $i++;
            if ($i == $len){$summary += $firstCharNum <= 9 ? $firstCharNum : 0;}
        }
        return $summary;
	}
}