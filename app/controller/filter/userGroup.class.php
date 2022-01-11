<?php 
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/

/**
 * 用户部门查询,搜索处理
 */
class filterUserGroup extends Controller{
	function __construct(){
		parent::__construct();
	}

	public function check(){
		if(_get($GLOBALS,'isRoot')) return true;
		$this->checkUser();
		$this->checkGroup();
		$this->checkRole();
	}
	
	// 用户列表获取,
	private function checkUser(){
		$paramMap = array(
			'admin.member.get'			=> array('group'=>'groupID','read'=>'allow','error'=>'list'),
			'admin.member.search'		=> array('group'=>'parentGroup','read'=>'allow','error'=>'list'),
			'admin.member.getbyid'		=> array('user'=>'id','read'=>'allow','error'=>'error'),
			
			'admin.member.add'			=> array('groupArray'=>'groupInfo','error'=>'error','userRole'=>'roleID'),
			'admin.member.addgroup'		=> array('groupArray'=>'groupInfo','user'=>'userID','error'=>'error'),
			'admin.member.removegroup'	=> array('group'=>'groupID','user'=>'userID','error'=>'error'),
			'admin.member.switchgroup'	=> array('group'=>'to','user'=>'userID','error'=>'error'),
			
			'admin.member.edit'			=> array('groupArray'=>'groupInfo','user'=>'userID','error'=>'error','userRole'=>'roleID'),
			'admin.member.status'		=> array('user'=>'userID','error'=>'error'),
			'admin.member.remove'		=> array('user'=>'userID','error'=>'error'),
		);
		$this->checkItem($paramMap);
	}
	
	private function checkGroup(){
		$paramMap = array(
			'admin.group.get' 		=> array('group'=>'parentID','read'=>'allow','error'=>'list'),
			'admin.group.search' 	=> array('group'=>'parentGroup','read'=>'allow','error'=>'list'),
			'admin.group.getbyid' 	=> array('group'=>'id','read'=>'allow','error'=>'error'),

			'admin.group.add' 		=> array('group'=>'parentID','error'=>'error'),
			'admin.group.edit' 		=> array('group'=>'groupID','error'=>'error'),
			'admin.group.status' 	=> array('group'=>'groupID','error'=>'error'),
			'admin.group.remove' 	=> array('group'=>'groupID','error'=>'error'),
			'admin.group.sort' 		=> array('group'=>'groupID','error'=>'error'),
			
			// 部门公共标签: 获取,修改
			'explorer.taggroup.get' => array('group'=>'groupID','read'=>'allow','error'=>'error'),
			'explorer.taggroup.set' => array('group'=>'groupID','error'=>'error'),
		);
		$this->checkItem($paramMap);
	}
	private function checkRole(){
		$paramMap = array(
			'admin.role.add' 		=> array('roleAuth'=>'auth'),
			'admin.role.edit' 		=> array('roleAuth'=>'auth'),
			'admin.role.remove' 	=> array('userRole'=>'id'),
		);
		$this->checkItem($paramMap);
	}
		
	private function checkItem($actions){
		$action = strtolower(ACTION);
		if(!isset($actions[$action])) return;
		if(!Session::get("kodUser")){show_json(LNG('user.loginFirst'),ERROR_CODE_LOGOUT);}
		$check = $actions[$action];
		if($check['read'] == 'allow'){return $this->checkItemRead($check,$action);}

		$allow = true;
		if($allow && $check['group']){
			$groupID = $this->in[$check['group']];
			$allow   = $this->allowChangeGroup($groupID);
			$adminGroup = $this->userAdminGroup();
			// 自己为管理员的根部门: 禁用编辑与删除;
			$disableAction = array('admin.group.edit','admin.group.remove');
			if(in_array($action,$disableAction) && in_array($groupID,$adminGroup)){$allow = false;}
		}
		if($allow && $check['user']){ $allow = $this->allowChangeUser($this->in[$check['user']]);}
		if($allow && $check['userRole']){$allow = $this->allowChangeUserRole($this->in[$check['userRole']]);}
		if($allow && $check['roleAuth']){$allow = $this->roleActionAllow($this->in[$check['roleAuth']]);}
		if($allow && $check['groupArray']){$allow = $this->allowChangeGroupArray($check);}
		if($allow) return true;
		$this->checkError($check);
	}
	
	
	private function checkItemRead($check,$action){
		$groupList = Session::get("kodUser.groupInfo");
		if(count($groupList) == 0){ // 不在任何部门则不支持用户及部门查询;
			return $this->checkError(array('error' =>'error'));
		}

		// app 接口请求认为是前端请求
		if( strstr($_SERVER['HTTP_USER_AGENT'],'kodCloud-System:iOS') || 
			isset($this->in['HTTP_X_PLATFORM']) ){
			$this->in['requestFrom'] = 'user';
		}
		
		// 来自用户请求,false则来自后台管理员请求;
		$fromUser   = $this->in['requestFrom'] == 'user';
		$groupArray = $fromUser ? $this->userGroupRoot() : $this->userAdminGroup();
		if($fromUser && $action == 'admin.member.search') return $this->checkUserSearch();
		
		// 后端请求 fromAdmin;
		$groupID = $this->in[$check['group']];
		$userID  = $this->in[$check['user']];
		if($action == 'admin.group.getbyid' && !$this->allowViewGroup($groupArray,$groupID)){
			$this->checkError($check);
		}
		if($action == 'admin.member.getbyid' && !$this->allowViewUser($groupArray,$userID)){
			$this->checkError($check);
		}
		if(!$check['group']) return;
		if(!$groupID || $groupID == 'root'){
			$groupID = $groupArray ? $groupArray[0]:false;
			$GLOBALS['in'][$check['group']] = $groupID;
			if($action == 'admin.group.get'){
				$items = Model('Group')->listByID($groupArray);
				show_json(array('list'=>$items,'pageInfo'=>array()),true);
			}
		}
		
		// 当前部门和所有部门根部门相等则不显示 "对外授权"
		if( $fromUser && $groupID && 
			strstr($this->in['rootParam'],'appendRootGroup') && 
			count($groupArray) == 0 && $groupArray[0] == $groupID
		){
			$this->in['rootParam'] = str_replace('appendRootGroup','',$this->in['rootParam']);
		}
		
		// pr($groupID,$groupArray,$this->allowViewGroup($groupArray,$groupID));exit;
		if($this->allowViewGroup($groupArray,$groupID)) return;
		$this->checkError(array('error' =>'error'));
	}
	private function checkUserSearch(){
		$words = Input::get('words','require');
		$where = array(
			'name' 		=> $words,
			'nickName' 	=> $words,
			'email' 	=> $words,
			'phone' 	=> $words,
			'_logic' 	=> 'or',
		);
		$user = Model("User")->where($where)->find();
		if($user){
			$list = Model('User')->userListInfo(array($user['userID']));
			$list = array_values($list);
			$page['totalNum'] = 1;
		}
		return show_json(array('list'=>$list,'pageInfo'=>$page),true);
	}
	
	private function checkError($check){
		if(!$check['error'] || $check['error'] == 'error'){
			show_json(LNG('explorer.noPermissionAction'),false);
		}
		$pageInfo = array("page"=>1,"totalNum"=>0,"pageTotal"=>0);
		show_json(array('list'=>array(),'pageInfo'=>$pageInfo),true);
	}
	
	// 多个部门信息检测;
	public function allowChangeGroupArray($check){
		$authSet = json_decode($this->in[$check['groupArray']],true);
		$authSet = is_array($authSet) ? $authSet : array();
		$userInfo  = $this->in[$check['user']] ? Model('User')->getInfo($this->in[$check['user']]):array();
		$groupAuth = array_to_keyvalue($userInfo['groupInfo'],'groupID','auth');$allow = true;
		
		foreach ($authSet as $groupID => $auth){
			if($userInfo && $groupAuth[$groupID]['id'] == $auth) continue;//其他部门权限,不允许修改;
			if(!$this->allowChangeGroup($groupID)){$allow = false;break;}
		}
		if(!$userInfo || !$groupAuth) return $allow;

		// 其他部门: 不允许删除,不允许修改;
		foreach ($groupAuth as $groupID => $authInfo){
			if($this->allowChangeGroup($groupID)) continue;
			if(!$authSet[$groupID]){$allow = false;break;}
		}
		foreach ($authSet as $groupID => $auth){
			if($this->allowChangeGroup($groupID)) continue;
			if($groupAuth[$groupID]['id'] != $auth){$allow = false;break;}
		}
		return $allow;
	}
	
	// 当前用户是否有操作该部门的权限;
	public function allowChangeGroup($groupID){return $this->allowViewGroup($this->userAdminGroup(),$groupID);}
	public function allowChangeUser($userID){return $this->allowViewUser($this->userAdminGroup(),$userID);}
	public function allowViewUser($selfGroup,$userID){
		if(!$selfGroup || count($selfGroup) == 0) return false;
		$userInfo  = Model('User')->getInfo($userID);
		$groupList = $userInfo ? $userInfo['groupInfo']:array();
		foreach ($groupList as $group){
			$groupInfo  = Model('Group')->getInfo($group['groupID']);
			$parents = Model('Group')->parentLevelArray($groupInfo['parentLevel']);$parents[] = $group['groupID'];
			foreach ($parents as $groupID){
				if(in_array($groupID.'',$selfGroup)) return true;
			}
		}
		return false;
	}
	public function allowViewGroup($selfGroup,$groupID){
		if(!$selfGroup || count($selfGroup) == 0) return false;
		$groupInfo  = Model('Group')->getInfo($groupID);
		$parents = Model('Group')->parentLevelArray($groupInfo['parentLevel']);$parents[] = $groupID;
		foreach ($parents as $groupID){
			if(in_array($groupID.'',$selfGroup)) return true;
		}
		return false;
	}

	// 权限修改删除范围处理: 只能操作权限包含内容小于等于自己权限包含内容的类型;  设置用户权限也以此为标准;
	public function allowChangeUserRole($roleID){
		if(_get($GLOBALS,'isRoot')) return true;
		$authInfo = Model('SystemRole')->listData($roleID);
		return $this->roleActionAllow($authInfo['auth']);
	}
	private function roleActionAllow($actions){
		$userInfo 		= Session::get("kodUser");
		$authInfo 		= Model('SystemRole')->listData($userInfo['roleID']);
		$actions   		= $actions ? explode(',',$actions) : array('--');
		$selfActions  = isset($authInfo['auth']) ? explode(',',$authInfo['auth']):array('-error-');
		foreach ($actions as $action){
			if($action && !in_array($action,$selfActions)) return false;
		}//$selfActions 包含$actions;
		return true;
	}
	
	// 自己所在为管理员的部门;
	public function userAdminGroup(){
		static $groupArray = null;
		if($groupArray !== null) return $groupArray;
		
		$groupList 	= Session::get("kodUser.groupInfo");$groupArray = array();
		foreach ($groupList as $group){
			if(!AuthModel::authCheckRoot($group['auth']['auth'])) continue;
			$groupArray[] = $group['groupID'].'';
		}
		$groupArray = Model('Group')->groupMerge($groupArray);
		return $groupArray;
	}
	
	// 自己可见的部门; 所在的部门向上回溯;
	public function userGroupRoot(){
		static $groupArray = null;
		if($groupArray !== null) return $groupArray;

		$groupCompany = $GLOBALS['config']['settings']['groupCompany'];
		$groupList 	= Session::get("kodUser.groupInfo");$groupArray = array();
		foreach ($groupList as $group){
			$groupRoot = Model('Group')->groupShowRoot($group['groupID'],$groupCompany);
			$groupArray = array_merge($groupArray,$groupRoot);
		}
		return Model('Group')->groupMerge($groupArray);
	}
}