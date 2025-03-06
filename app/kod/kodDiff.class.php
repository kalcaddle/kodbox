<?php

class kodDiff{
	// 生成差异对象
    public static function diffMake($objFrom,$objTo,$objLike){
        if(self::isArray($objLike)){
            return self::diffArray($objFrom,$objTo,$objLike[0]);
        }elseif(self::isObject($objLike)){
            return self::diffObject($objFrom,$objTo,$objLike);
        }
        return false;
    }
    // 应用差异对象
    public static function diffApply($objFrom,$diff,$objLike){
        if(!$diff){return $objFrom;}
        if(self::isArray($objLike)){
            return self::applyArray($objFrom,$diff,$objLike[0]);
        }elseif(self::isObject($objLike)){
            return self::applyObject($objFrom,$diff,$objLike);
        }
        return $objFrom;
    }
	
	
	
    // 对象差异比较
    private static function diffObject($objFrom,$objTo,$objStruct){
        $diff = array();
        $objStruct = self::isObject($objStruct) ? $objStruct : false;
        if(self::isEqual($objFrom,$objTo)){return false;}
        self::compareObj($objFrom,$objTo,true,$diff,$objStruct);
        self::compareObj($objTo,$objFrom,false,$diff,$objStruct);
        return $diff;
    }
	private static function compareObj($obj1,$obj2,$isFromTo, &$diff,$objStruct){
		$obj1 = is_array($obj1) ? $obj1 : array();
		$obj2 = is_array($obj2) ? $obj2 : array();
		foreach($obj1 as $k=>$v){
			$objStructSub = _get($objStruct,$k,false);
			$subFrom = $isFromTo ? $v:_get($obj2,$k,array()); $subTo = $isFromTo ? _get($obj2,$k,array()):$v;
			
			// 都为空情况处理; $objFrom包含key且为空,同时$objTo不包含key时处理清空key;  $objTo包含key且为空,同时$objFrom不包含key时保留;
			if(!$subFrom && !$subTo && is_array($subFrom) && is_array($subTo)){
				if(!$isFromTo && !isset($obj2[$k])){$diff[$k] = array('type'=>'diff','val'=>array());}
				if($isFromTo  && !isset($obj2[$k])){$diff[$k] = array('type'=>'diff','val'=>array(),'_clearAll'=>true);}
				continue;
			}
			if(self::isEqual($subFrom,$subTo)){continue;}
			if(self::isArray($objStructSub)){
				$diffNow  = self::diffArray($subFrom,$subTo,$objStructSub[0]);
				if($diffNow){$diff[$k] = array('type'=>'diffArr','val'=>$diffNow);}
				if($isFromTo && $diffNow && !isset($obj2[$k])){$diff[$k]['_clearAll'] = true;}
			}elseif(self::isObject($objStructSub) || self::allowMergeObj($subFrom,$subTo)){
				$diffNow  = self::diffObject($subFrom,$subTo,$objStructSub);
				if($diffNow){$diff[$k] = array('type'=>'diff','val'=>$diffNow);}
				if($isFromTo && $diffNow && !isset($obj2[$k])){$diff[$k]['_clearAll'] = true;}
			}else{
				if(!isset($obj2[$k])){
					$diff[$k] = $isFromTo ? array('type'=>'remove') : array('type'=>'edit', 'val'=>$v);
					continue;
				}
				$diff[$k] = array('type'=>'edit', 'val'=>$v);
			}
		}		
	}
	
    // 数组差异比较
    private static function diffArray($arrFrom,$arrTo,$objStruct){
		$arrFrom = self::isArray($arrFrom) ? $arrFrom : array();
        $arrTo   = self::isArray($arrTo) ? $arrTo : array();
		if(self::isEqual($arrFrom,$arrTo)){return false;} // 相等则忽略;
        if(!self::isObject($objStruct) || (empty($arrFrom) && empty($arrTo))){return false;}
		
		$idKey = _get($objStruct,'_idKey_','id');
        $diff  = array('add'=>array(), 'remove'=>array(), 'edit'=>array(), 'sort'=>array('isChange'=>false, 'idArr'=>array()));
		$arrFromMap = array();$arrFromSort = array();$arrToMap = array();$arrToSort = array();$lastID = '';
		foreach($arrFrom as $v){
			if(!is_array($v)){continue;}
			$id = isset($v[$idKey]) ? $v[$idKey].'' : '';
			if($id === ''){continue;}
			$arrFromMap[$id] = $v;$arrFromSort[] = $id;
		}
		
		// 创建 arrTo 的映射和排序数组, 并处理新增和变更的项
		foreach($arrTo as $v){
			if(!is_array($v)){continue;}
			$id = isset($v[$idKey]) ? $v[$idKey].'' : '';
			if($id === '' || !isset($arrFromMap[$id])){// id 为空,或来源不存在时为新增项
				$diff['add'][] = array('beforeID' => $lastID,'val' => $v);
				if($id === ''){continue;}
			}
			if(isset($arrFromMap[$id])){
				$diffNow = self::diffObject($arrFromMap[$id], $v, $objStruct);
				if($diffNow){$diff['edit'][$id] = $diffNow;}
			}
			$arrToMap[$id] = $v;$arrToSort[] = $id;$lastID = $id;
		}
		foreach($arrFromMap as $id => $v){
			if(!isset($arrToMap[$id])){$diff['remove'][] = $id;}
		}
		// 检查排序是否有变化
		if($arrFromSort !== $arrToSort){
			$diff['sort'] = array('isChange' => true,'idArr' => $arrToSort);
		}
		return $diff;
    }
	
    // 应用对象差异
    private static function applyObject($obj,$diff,$objStruct){
        $newObj = $obj;
        foreach($diff as $key=>$change){
            switch ($change['type']){
                case 'edit':$newObj[$key] = $change['val'];break;
                case 'remove':unset($newObj[$key]);break;
                case 'diff':
                    $newObj[$key] = self::applyObject(_get($newObj,$key,array()),$change['val'],_get($objStruct,$key,array()));
                    if(isset($change['_clearAll']) && empty($newObj[$key])){unset($newObj[$key]);}
                    break;
                case 'diffArr':
                    $newObj[$key] = self::applyArray(_get($newObj,$key,array()),$change['val'],_get($objStruct,$key.'.0',array()));
					if(isset($change['_clearAll']) && empty($newObj[$key])){unset($newObj[$key]);}
                    break;
				default:break;
            }
        }
        return $newObj;
    }
    // 应用数组差异
    private static function applyArray($arr,$diff,$objStruct){
        $newArr = $arr;$arrMap = array();$arrSort = array();
        $arrResultID = array();$arrResult = array();
        $idKey = self::isObject($objStruct) ? _get($objStruct,'_idKey_','id'):'';
        if(!$diff){return $newArr;}

        foreach($newArr as $i=>$item){
            if(!is_array($item)){continue;}
            $id = isset($item[$idKey]) ? $item[$idKey].'' : '';
            if(!$id){continue;}
            if(in_array($id,$diff['remove'])){
                $newArr[$i] = false;
                continue;
            }
            if(isset($diff['edit'][$id])){
                $newArr[$i] = self::applyObject($newArr[$i],$diff['edit'][$id],$objStruct);
            }
            $arrMap[$id] = $newArr[$i];
            $arrSort[] = $id;
        }

        foreach($diff['add'] as $addItem){
            if(!self::isObject($addItem['val'])){continue;}
            $id = isset($addItem['val'][$idKey]) ? $addItem['val'][$idKey].'' : '';
            if($id && !isset($arrMap[$id])){
                $arrMap[$id] = $addItem['val'];
            }
        }
		
        $hasPushedID = array();
        $arrSortID = $diff['sort']['isChange'] ? $diff['sort']['idArr'] : $arrSort;
        foreach($arrSortID as $id){
            if(isset($arrMap[$id])){
                $arrResultID[] = $id;
                $hasPushedID[$id] = true;
            }
        }
        foreach($arrMap as $id=>$v){
            if(!isset($hasPushedID[$id])){$arrResultID[] = $id;}
        }

        $hasAdd = array();
        self::pushAdd('',$diff,$arrResult,$hasAdd,$idKey);
        foreach($arrResultID as $id){
            if(isset($arrMap[$id]) && !isset($hasAdd[$id])){
                $arrResult[] = $arrMap[$id];
            }
            self::pushAdd($id,$diff,$arrResult,$hasAdd,$idKey);
        }
		
		$autoIDType = _get($objStruct,'_autoID_');  
		if($idKey && $autoIDType){
			self::arrayAutoID($arrResult,$idKey,$autoIDType);
		}
        return $arrResult;
    }
	
	private static function pushAdd($beforeID,$diff,&$arrResult,&$hasAdd,$idKey){
		foreach($diff['add'] as $addItem){
			if(!$addItem || !self::isObject($addItem['val'])){continue;}
			if($addItem['beforeID'] != $beforeID){continue;}
			$id = isset($addItem['val'][$idKey]) ? $addItem['val'][$idKey].'' : '';
			if($id && isset($hasAdd[$id])){continue;}
			$arrResult[] = $addItem['val'];$hasAdd[$id] = true;
		}
	}
	
	public static function isEqual($a, $b){
        if(gettype($a) !== gettype($b)){return false;}
        if(is_array($a)){
            if(count($a) !== count($b)){return false;}
            foreach($a as $k => $v){
                if(!array_key_exists($k, $b) || !self::isEqual($v, $b[$k])) return false;
            }
            return true;
        }
        return $a === $b;
    }
    private static function isObject($v){
		return is_array($v) && (!isset($v[0]) && count($v) > 0);
    }
    private static function isArray($v){
		return is_array($v) && (isset($v[0]) || count($v) == 0);
    }
    private static function allowMergeObj($a,$b){
        return self::isObject($a) || self::isObject($b);
    }
	
	
	// 构造id; 区分数组中唯一id;
	public static function makeID($idArr,$type='string'){
		if($type != 'string'){
			$maxID = 1;
			foreach($idArr as $id){
				$maxID = max($maxID,intval($id));
			}
			return ($maxID + 1).'';
		}
		
		$loop = 1;
		while($loop++ <= 500){
			$uid = strtolower(substr(md5(rand_string(30).time()),0,6));
			if(!$idArr || !in_array($uid,$idArr)){return $uid;}
		}
		return rand_string(6);
	}
	public static function arrayAutoID(&$arr,$idKey = 'id',$type='number'){
		$idArr = array_to_keyvalue($arr,'',$idKey);
		foreach($arr as $i=>$v){
			if($arr[$i][$idKey]){continue;}
			$id = self::makeID($idArr,$type);$idArr[] = $id;
			$arr[$i][$idKey] = $id;
		}
	}
}

/*
示例使用
$a = array(
    'user' => array('userID' => '1', 'name' => 'admin', 'x' => '1'),'a' => array(2, 3, 4),'pose2' => array('x' => 2, 'y' => 3),'pose3' => array(),
	'menu1' => array(),
    'menu2' => array(
        array('id' => 1, 'name' => 'a1'),
        array('id' => 2, 'name' => 'a2', 'op' => array('a' => 1, 'b' => 2))
    ),
    'menu3' => array(
        array('id' => 1, 'name' => 'a1'),
        array('id' => 2, 'name' => 'a2', 'op' => array('a' => 1, 'b' => array('x' => 1, 'y' => 2)))
    )
);
$b = array(
    'user' => array('userID' => '2', 'name' => 'admin', 'y' => '2'),'a' => array(3, 5),'pose2' => array(),'pose4' => array(),
    'menu1' => array(
        array('id' => 2, 'name' => 'a2'),
        array('id' => 1, 'name' => 'a1')
    ),
    'menu2' => array(
        array('id' => 2, 'name' => 'a26', 'x' => 3),
        array('id' => 1, 'name' => 'a1')
    ),
    'menu3' => array(
        array('name' => 'a1', 'id' => 1),
        array('id' => 13, 'name' => 'a12'),
        array('id' => 2, 'name' => 'a2', 'op' => array('a' => 1, 'b' => array()))
    )
);
$like = array(
    'menu1' => array(array('_idKey_' => 'id')),
    'menu2' => array(array('_idKey_' => 'id')),
    'menu3' => array(array('_idKey_' => 'id'))
);
// 计算差异
$diff   = kodDiff::diffMake($a, $b, $like);
$diffTo = kodDiff::diffApply($a, $like, $diff);
pr(kodDiff::isEqual($b, $diffTo),$a,$b,$diffTo,$diff);
**/