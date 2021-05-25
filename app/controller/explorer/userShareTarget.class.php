<?php 
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/

/**
 * 最近分享目标获取;
 * 
 * 最近分享/权限设置出的用户及部门,按次数排序,总共10个,部门在前用户在后;
 * 数据缓存; 新查询数据合并之前缓存数据;(避免取消分享后没有最近使用的情况)
 */
class explorerUserShareTarget extends Controller{
	function __construct(){
		parent::__construct();
	}
	
	public function save(){
		$data = Input::getArray(array(
			"name"		=> array("check"=>"require"),
			"authTo" 	=> array("check"=>"require","default"=>''),
		));

		//编辑保存
		$saveData = $this->dataValue('saveData');
		if($this->in['beforeName']){
			unset($saveData[$this->in['beforeName']]);
		}
		$data['modifyTime'] = time();
		$saveData[$data['name']] = $data;

		// authTo为空则代表删除
		if(!$data['authTo']){
			unset($saveData[$data['name']]);
		}
								
		$this->dataValue('saveData',$saveData);
		$result = $this->get(10);
		show_json($result,true);
	}
	
	public function get($maxNumber){
		$maxNumber = 10; // 最多条数
		$list = $this->targetMake();
		foreach ($list as $targetType => $targetList){
			$list[$targetType] = array();
			foreach ($targetList as $info){
				if($targetType == 'group'){
					$targetInfo = Model("Group")->getInfo($info['id']);
				}else{
					$targetInfo = Model("User")->getInfoSimpleOuter($info['id']);
					if($targetInfo['userID'] == '0' || $targetInfo['userID'] == '-1'){
						$targetInfo = false;
					}
				}
				if(!$targetInfo) continue;
				$list[$targetType][] = $targetInfo;
			}
		}
		
		if( count($list['user']) + count($list['group']) > $maxNumber ){
			$list['user']  = array_slice($list['user'], 0,$maxNumber / 2);
			$list['group'] = array_slice($list['group'],0,$maxNumber / 2);
		}
		
		
		$saveData = $this->dataValue('saveData');
		foreach ($saveData as $key=>$value){
			$value['nodeAddClass'] = 'node-share-item-store';
			$value['icon'] = '<i class="font-icon ri-team-fill"></i>';
			$saveData[$key] = $value;
		}
		$saveData = array_values($saveData);
		$result = array_merge($saveData,$list['group'],$list['user']);
		return $result;
	}
	
	private function dataValue($key,$value=false){
		if($value === false){
			// Model("UserOption")->set($key,null,'shareTarget');
			$theValue = Model("UserOption")->get($key,'shareTarget',1);
			$theValue = is_array($theValue) ? $theValue : array();
		}else{
			Model("UserOption")->set($key,$value,'shareTarget');
		}
		return $theValue;
	}
	
	
	/**
	 * 新查询的数据合并之前数据;
	 * 
	 * 实时中id存在旧数据中不存在,    则设置id的count为实时数据的count
	 * 实时中id存在旧数据中已经存在,  则使用count更大的作为当前id的count;
	 * 旧数据中有,实时中id不存在,     检测对象是否存在,存在则id的count重置为1,不存在则从缓存中移除;
	 */
	private function targetMake(){
		$listNow = $this->targetSelect();$listNowData = $listNow;
		$listBefore = $this->dataValue('cacheData');
		foreach ($listNow as $targetType => $targetList){
			foreach ($targetList as $id=>$info){
				$beforeItem = $listBefore[$targetType][$id];
				if($beforeItem){
					$info['count'] = max($info['count'],$beforeItem['count']);
					$listNow[$targetType][$id] = $info;
				}
			}
		}
		
		// 缓存中有,新查询不存在
		foreach ($listBefore as $targetType => $targetList){
			foreach ($targetList as $id=>$info){
				if(!$listNow[$targetType][$id]){
					if($targetType == 'group'){
						$targetInfo = Model("Group")->getInfo($info['id']);
					}else{
						$targetInfo = Model("User")->getInfoSimpleOuter($info['id']);
					}
					if(!$targetInfo) continue;
					$info['count'] = 1;
					$listNow[$targetType][$id] = $info;
				}
			}
			$listNow[$targetType] = array_sort_by($listNow[$targetType] ,'count',true);
			$listNow[$targetType] = array_to_keyvalue($listNow[$targetType] ,'id');
		}

		if($listNow != $listBefore){
			$this->dataValue('cacheData',$listNow);
		}
		// pr($listNow == $listBefore,$listNowData,$listBefore,$listNow);exit;
		return $listNow;
	}
	
	private function targetSelect(){
		$shareList = Model('Share')->listSimple();
		$shareList = array_filter_by_field($shareList,'isShareTo','1');
		$shareIdList = array_to_keyvalue($shareList,'','shareID');
		if(!$shareIdList) return array('group'=>array(),'user'=>array());
		
		$where 	= array('shareID' => array('in',$shareIdList));
		$toList = Model("share_to")->where($where)->select();
		return array(
			'group' => $this->targetSort($toList,SourceModel::TYPE_GROUP),
			'user' 	=> $this->targetSort($toList,SourceModel::TYPE_USER)
		);
	}
	private function targetSort($shareList,$type){
		$list 	= array_filter_by_field($shareList,'targetType',$type.'');
		$list 	= array_to_keyvalue_group($list,'targetID','targetID');
		foreach ($list as $key => $value) {
			$list[$key] = array('id'=>$key,'count'=>count($value));
		}
		$list = array_sort_by($list,'count',true);
		return array_to_keyvalue($list,'id');
	}
}
