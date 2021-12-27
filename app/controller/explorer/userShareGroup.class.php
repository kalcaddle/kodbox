<?php 
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt

按组织架构对与我协助内容进行归类
1. 子部门: 该部门子部门,下级部门有分享或下级成员有分享时显示
2. 部门成员的个人空间分享; 包含个人空间文件,其他物理路径,外部部门空间分享;
3. 该部门空间协作分享: 该部门空间内容;
----
4. 外部协作分享: 不在自己组织架构下的外部用户的协作分享;
*/

class explorerUserShareGroup extends Controller{
	private $model;
	private $userGroupRoot;
	private $shareListData;
	function __construct(){
		parent::__construct();
		$this->model  = Model('Share');
	}
	public function get($id){
		$this->userGroupRoot  = Action('filter.userGroup')->userGroupRoot();
		$this->shareListData  = $this->shareListDataMake();
		if(!$id || $id == 'group'){
			return $this->listRoot();
		}

		$userPre  = 'group-u';//group-u101-23; userID-parentGroup
		$groupPre = 'group-g';//group-g101;
		if(substr($id,0,strlen($groupPre)) == $groupPre){
			$groupID = substr($id,strlen($groupPre));
			return $this->listByGroup($groupID);
		}
		if(substr($id,0,strlen($userPre)) == $userPre){
			$userGroup = explode('-',substr($id,strlen($userPre)));
			return $this->listByUser($userGroup[0],$userGroup[1]);
		}
	}
	
	private function listRoot(){
		$rootCount = count($this->userGroupRoot);
		if($rootCount == 0) return false;
		$outUserList  = $this->listUserOuter();
		if($rootCount == 1){
			return $this->listRootSingle($this->userGroupRoot[0],$outUserList);
		}

		$childrenGroup = Model("Group")->listByID($this->userGroupRoot);
		$childrenGroup = array_sort_by($childrenGroup,'sort',false);
		$groupList = array();
		foreach($childrenGroup as $groupInfo){
			$childList   = $this->listByGroup($groupInfo['groupID']);
			if(!$childList || (!$childList['groupList'] && !$childList['folderList'])){continue;}
			$groupList[] = $this->makeItemGroup($groupInfo);
		}
		if(!$groupList) return false;
		if(count($groupList) == 1){
			return $this->listRootSingle($groupList[0]['groupID'],$outUserList);
		}
		
		$result = $this->listByGroupType(array('groupList'=>$groupList));
		$result['groupList'] = array_merge($result['groupList'],$outUserList);
		return $result;
	}
	private function listRootSingle($groupID,$outUserList){
		$result = $this->listByGroup($groupID);
		$desc   = '['.LNG('admin.setting.shareToMeGroup').'],'.LNG('explorer.pathDesc.shareToMeGroup');
		if($result){$result['currentFieldAdd'] = array("pathDesc"=>$desc);}
		// 合并外部分享信息数据;
		
		if(!$result['groupList']){$result['groupList'] = array();}
		$result['groupList'] = array_merge($result['groupList'],$outUserList);
		return $result;
	}
	
	// 当前部门有分享内容的子部门, 当前部门分享内容, 当前部门有分享内容的用户;
	private function listByGroup($groupID){
		$groupID = intval($groupID);
		if(!$groupID) return false;
		$groupInfoCurrent = Model("Group")->getInfo($groupID);
		if(!$groupInfoCurrent) return false;

		$listData   = $this->shareListData;
		$groupArray = array_keys($listData['group']);
		$userArray  = array_keys($listData['user']);		
		$goupList = Model("Group")->listByID($groupArray);
		$userList = Model("User")->listByID($userArray);
		
		$childrenUser  = array();
		$childrenGroup = array();
		foreach($goupList as $groupInfo){
			$groupLevel = $groupInfo['parentLevel'].$groupInfo['groupID'].',';//层级
			$childrenID = $this->groupChildrenMake($groupID,$groupLevel);
			if($childrenID){$childrenGroup[] = $childrenID;}
		}
		foreach ($userList as $userInfo){
			foreach ($userInfo['groupInfo'] as $groupInfo){
				$groupLevel = $groupInfo['parentLevel'].$groupInfo['groupID'].',';//层级
				$childrenID = $this->groupChildrenMake($groupID,$groupLevel);
				if($childrenID){$childrenGroup[] = $childrenID;}
				if($groupInfo['groupID'] == $groupID){$childrenUser[] = $userInfo;}
			}
		}
		$childrenGroup = Model("Group")->listByID(array_unique($childrenGroup));
		$childrenGroup = array_sort_by($childrenGroup,'sort',false);
		
		$groupList = array();
		foreach($childrenGroup as $groupInfo){$groupList[] = $this->makeItemGroup($groupInfo);}
		foreach($childrenUser  as $userInfo){$groupList[] = $this->makeItemUser($userInfo,$groupInfoCurrent);}

		$result = array('groupList'=>$groupList,'folderList'=>$listData['group'][$groupID]);
		$result['currentFieldAdd'] = $this->makeItemGroup($groupInfoCurrent);
		$result = $this->listByGroupType($result);
		return $result;
	}
	
	private function listByGroupType($result){
		if(!$result['folderList']){$result['folderList'] = array();}
		if(!$result['fileList']){$result['fileList'] = array();}
		$result['groupShow'] = array(
			array(
				'type' 	=> 'childGroup',
				'title' => LNG("explorer.pathGroup.shareGroup"),
				'desc' 	=> LNG("explorer.pathGroup.shareGroupDesc"),
				"filter"=> array('groupID'=>'')
			),
			array(
				'type'	=> 'childUser',
				'title'	=> LNG("explorer.pathGroup.shareUser"),
				'desc'	=> LNG("explorer.pathGroup.shareUserDesc"),
				"filter"=> array('userID'=>'','shareFrom'=>'!=outer')
			),
			array(
				'type'	=> 'childUserOuter',
				'title'	=> LNG('explorer.pathGroup.shareUserOuter'),
				"desc"	=> LNG("explorer.pathGroup.shareUserOuterDesc"),
				"filter"=> array('shareFrom'=>'outer')
			),
			array(
				'type'	=> 'childContent',
				'title'	=> LNG("explorer.pathGroup.shareContent"),
				"filter"=> array('shareID'=>'')
			),
		);
		if(count($result['groupList']) == 0){unset($result['groupShow']);}
		// filter 支持多个key-value; 全匹配才算匹配; 
		// value为*则检测是否有该key; 为字符串则检测相等; value为数组则代表可选值集合
		// show_json([$groupID,$groupList,$result]);exit;
		return $result;
	}
	
	// $groupID是否为$parentLevel的上层部门; 如果是则返回$groupID下一层部门id;
	private function groupChildrenMake($groupID,$checkLevel){
		$level = explode(',',trim($checkLevel,','));
		$level = array_remove_value($level,'0');
		$index = array_search($groupID,$level);
		if($index === false || $index == count($level) - 1) return false;
		return $level[$index + 1];
	}
	public function groupAllowShow($groupID,$parentLevel=false){
		$allow  = false;
		if(in_array($groupID,$this->userGroupRoot)) return true;
		if(!$parentLevel){
			$groupInfo = Model('Group')->getInfo($groupID);
			$parentLevel = $groupInfo['parentLevel'].','.$groupInfo['groupID'];
		}
		foreach($this->userGroupRoot as $group){
			if($this->groupChildrenMake($group,$parentLevel)){$allow = true;break;}
		}
		return $allow;
	}
	
	private function makeItemGroup($groupInfo){
		$result = array(
			"groupID"		=> $groupInfo['groupID'],
			"name" 			=> $groupInfo["name"],
			"type" 			=> "folder",
			"path" 			=> "{shareToMe:group-g".$groupInfo['groupID']."}/",
			"icon"			=> "root-groupPath",
			"pathDesc"		=> LNG('explorer.pathDesc.shareToMeGroupGroup'),
		);
		$parentGroup = Model("Group")->getInfo($groupInfo['parentID']);
		return $this->makeAddress($result,$parentGroup);
	}
	private function makeItemUser($userInfo,$parentGroup=false){
		$defaultThumb = STATIC_PATH.'images/common/default-avata.png';
		$defaultThumb = 'root-user-avatar';// $userInfo["avatar"] = false;
		$parentGroup  = $parentGroup ? $parentGroup:array('groupID'=>'0','parentLevel'=>'');
		$result = array(
			"userID"		=> $userInfo['userID'],
			"name" 			=> _get($userInfo,"nickName",$userInfo["name"]),
			"type" 			=> "folder",
			"path" 			=> "{shareToMe:group-u".$userInfo['userID']."-".$parentGroup['groupID']."}/",
			"icon"			=> $userInfo["avatar"] ? $userInfo["avatar"]:$defaultThumb,//fileThumb,icon
			"iconClassName"	=> 'user-avatar',	
		);
		$result['pathDescAdd'][] = array(
			"title"		=> LNG("common.desc"),
			"content"	=> LNG('explorer.pathGroup.shareUserDesc')
		);
		$userInfo = Model('User')->getInfo($userInfo['userID']);
		$groupArr = array_to_keyvalue($userInfo['groupInfo'],'','groupName');
		if($groupArr){
			$result['pathDescAdd'][] = array("title"=>LNG("admin.member.group"),"content"=>implode(',',$groupArr));
		}
		return $this->makeAddress($result,$parentGroup);
	}
	
	private function makeAddress($itemInfo,$parentGroup){
		$address = array(array("name"=> LNG('explorer.toolbar.shareToMe'),"path"=>'{shareToMe}'));
		// 从$this->userGroupRoot的某一个,开始到$groupInfo所在部门
		$level = $parentGroup ? $parentGroup['parentLevel'].$parentGroup['groupID'].',':'';
		$level = explode(',',trim($level,','));
		$level = array_remove_value($level,'0');
		
		$fromAdd = count($this->userGroupRoot) == 1 ? 1: 0;//只有一个根部门,则忽略根部门;
		$index   = false;
		if($level){
			foreach($this->userGroupRoot as $groupID){
				$index = array_search($groupID,$level);
				if($index !== false){break;}
			}
		}
		if($index !== false){
			$nameArray = explode('/',trim($parentGroup['groupPath']));
			for ($i=$index+$fromAdd; $i < count($level); $i++) { 
				$address[] = array("name"=> $nameArray[$i],"path"=>"{shareToMe:group-g".$level[$i]."}/");
			}
		}
		$address[] = array("name"=> $itemInfo["name"],"path"=>$itemInfo['path']);
		$itemInfo['pathAddress'] = $address;
		return $itemInfo;
	}

	private function listByUser($userID,$parentGroup){
		$userShareList = $this->shareListData['user'][$userID];
		if(!$userShareList) return false;
		
		$userInfo = Model('User')->getInfoSimpleOuter($userID);
		$result = Action('explorer.userShare')->shareToMeListMake($userShareList);
		$groupInfo = Model('Group')->getInfo($parentGroup);
		$result['currentFieldAdd'] = $this->makeItemUser($userInfo,$groupInfo);
		// unset($result['currentFieldAdd']['icon']);
		return $result;
	}
	
	// 对内容归类整理: 所属用户,所属部门,
	private function shareListDataMake(){
		$shareList = $this->model->listToMe(5000);
		$sourceArray = array_to_keyvalue($shareList['list'],'','sourceID');
		$sourceArray = array_unique($sourceArray);
		if($sourceArray){
			$where = array('sourceID' => array('in',$sourceArray),'isDelete' => 0,);
			$sourceList  = Model('Source')->listSource($where);
			$sourceArray = array_merge($sourceList['folderList'],$sourceList['fileList']);
			$sourceArray = array_to_keyvalue($sourceArray,'sourceID');
		}
		$userList = array();$groupList = array();
		foreach ($shareList['list'] as $shareItem){
			$timeout = intval(_get($shareItem,'options.shareToTimeout',0));
			if($timeout > 0 && $timeout < time()) continue;// 过期内容;
			if($shareItem['sourceID'] == '0'){// 物理路径,io路径;
				// $info = IO::info($shareItem['sourcePath']);
				$info = $shareItem;//性能优化, 拉取用户内容时才考虑;
			}else{
				$info = $sourceArray[$shareItem['sourceID']];
			}

			if(!$info) continue;
			$groupNotAllowShare = false;
			if($info['targetType'] == 'group' && !$this->groupAllowShow($info['targetID'])){
				$groupNotAllowShare = true;
			}
			if($groupNotAllowShare || $shareItem['sourceID'] == '0' || $info['targetType'] == 'user'){// 物理路径,io路径;
				$userID = $shareItem['userID'];
				if(!isset($userList[$userID])){$userList[$userID] = array();}
				$userList[$userID][] = $shareItem;//性能优化;
			}else{
				$info = Action('explorer.userShare')->_shareItemeParse($info,$shareItem);
				$groupID = $info['targetID'];
				if(!isset($groupList[$groupID])){$groupList[$groupID] = array();}
				$groupList[$groupID][] = $info;
			}
		}
		return array('user'=>$userList,'group'=>$groupList);
	}
	
	// 筛选出外部分享内容; 部门空间内容-部门不在该自己所在组织架构内; 个人空间分享--个人不在自己所在组织架构内;
	// 按人进行归类;
	private function listUserOuter(){
		$userList = array();
		foreach($this->shareListData['user'] as $userID=>$userShare){
			$userInfo  = Model('User')->getInfo($userID);
			$userAllow = false;
			foreach ($userInfo['groupInfo'] as $groupInfo){
				$groupLevel = $groupInfo['parentLevel'].$groupInfo['groupID'].',';//层级
				if($this->groupAllowShow($groupInfo['groupID'],$groupLevel)){$userAllow = true;break;}
			}
			if($userAllow) continue;
			
			$userItem   = $this->makeItemUser($userInfo);
			$userItem['shareFrom'] = 'outer';
			$userList[] = $userItem;
		}
		return $userList;
	}
}
