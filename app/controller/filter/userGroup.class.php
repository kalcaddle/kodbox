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
		if(KodUser::isRoot()){
			if($this->config["ADMIN_ALLOW_ALL_ACTION"]){return;}
			//三权分立,限制系统管理员设置用户角色及所在部门权限; 还原设置数据;
			return $this->userAuthEditCheck();
		}
		$this->checkUser();
		$this->checkGroup();
		$this->checkRole();
	}
	
	// 用户列表获取,
	private function checkUser(){
		$paramMap = array(
			'admin.member.get'			=> array('group'=>'groupID','read'=>'allow','error'=>'list'),
			'admin.member.search'		=> array('group'=>'parentGroup','read'=>'allow','error'=>'list'),
			'admin.member.getbyid'		=> array('user'=>'id','read'=>'allow','error'=>'listSimple'),
			
			'admin.member.add'			=> array('groupArray'=>'groupInfo','userRole'=>'roleID'),
			'admin.member.addgroup'		=> array('groupArray'=>'groupInfo','user'=>'userID'),
			'admin.member.removegroup'	=> array('group'=>'groupID','user'=>'userID'),
			'admin.member.switchgroup'	=> array('group'=>'to','user'=>'userID'),
					
			'admin.member.edit'			=> array('groupArray'=>'groupInfo','user'=>'userID','userRole'=>'roleID'),
			'admin.member.status'		=> array('user'=>'userID'),
			'admin.member.remove'		=> array('user'=>'userID'),
		);
		$this->checkItem($paramMap);
	}
	
	private function checkGroup(){
		$paramMap = array(
			'admin.group.get' 		=> array('group'=>'parentID','read'=>'allow','error'=>'list'),
			'admin.group.search' 	=> array('group'=>'parentGroup','read'=>'allow','error'=>'list'),
			'admin.group.getbyid' 	=> array('group'=>'id','read'=>'allow','error'=>'listSimple'),

			'admin.group.add' 		=> array('group'=>'parentID'),
			'admin.group.edit' 		=> array('group'=>'groupID'),
			'admin.group.status' 	=> array('group'=>'groupID'),
			'admin.group.remove' 	=> array('group'=>'groupID'),
			'admin.group.sort' 		=> array('group'=>'groupID'),
			'admin.group.switchgroup' => array('group'=>'from','group'=>'to'),
			
			// 部门公共标签: 获取,修改;
			'explorer.taggroup.set' => array('group'=>'groupID'),
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
		
		if($check['read'] == 'allow'){			
			// 部门管理员,从后台搜索部门; 仅限在有权限的部门中搜索;
			$isFromAdmin = isset($this->in['requestFromType']) && $this->in['requestFromType'] == 'admin';
			$adminGroup = $this->userGroupAdmin();
			$isSearch    = in_array($action,array('admin.member.search','admin.group.search'));
			if( $isSearch && $isFromAdmin && !isset($this->in[$check['group']])){
				$this->in[$check['group']] = implode(',',$adminGroup);
			}
			return $this->checkItemRead($check,$action);
		}

		$allow = true;
		if($allow && $check['group']){
			$groupID = $this->in[$check['group']];
			$allow   = $this->allowChangeGroup($groupID);
			$adminGroup = $this->userGroupAdmin();
			// 自己为管理员的根部门: 禁用编辑与删除;
			$disableAction = array('admin.group.edit','admin.group.remove');
			if(in_array($action,$disableAction) && in_array($groupID,$adminGroup)){$allow = false;}
		}
		
		$err = $allow ? -1:0;
		if($allow){$allow = $this->userAuthEditCheck();$err=$allow?$err:1;}
		if($allow && $check['user']){ $allow = $this->allowChangeUser($this->in[$check['user']]);$err=$allow?$err:2;}
		if($allow && $check['userRole']){$allow = $this->allowChangeUserRole($this->in[$check['userRole']]);$err=$allow?$err:3;}
		if($allow && $check['roleAuth'] && isset($this->in['roleID'])){$allow = $this->roleActionAllow($this->in[$check['roleAuth']]);$err=$allow?$err:4;}
		if($allow && $check['groupArray']){$allow = $this->allowChangeGroupArray($check);$err=$allow?$err:5;}
		// trace_log([$err,$allow,$check,$this->in,'GET:',$_REQUEST]);
		if($allow) return true;
		$this->checkError($check);
	}
	
	private function checkItemRead($check,$action){
		$groupList = Session::get("kodUser.groupInfo");
		if(!$groupList || count($groupList) == 0){ // 不在任何部门则不支持用户及部门查询;
			return $this->checkError($check);
		}
		$groupArray = $this->userGroupRootShow();
		$groupID = $this->in[$check['group']];
		$userID  = $this->in[$check['user']];
		if($groupID && ($groupID == 'root' || $groupID == 'rootOuter')){$groupID = '';}		
		if($action == 'admin.group.getbyid'){
			$allowID = $this->allowViewGroup($groupArray,$groupID,true);
			if(!$allowID){$this->checkError($check);}
			$this->in[$check['group']] = $allowID;// 过滤出有权限的部分;
			return;
		}else if($action == 'admin.member.getbyid'){
			$allowID = $this->allowViewUser($groupArray,$userID,true);
			if(!$allowID){$this->checkError($check);}
			$this->in[$check['user']] = $allowID;// 过滤出有权限的部分;
			return;
		}
		
		if(!$check['group']) return;
		if(!$groupID || $this->allowViewGroup($groupArray,$groupID)) return;
		$this->checkError($check);
	}
	
	private function checkError($check){
		if(!$check || !$check['error']){show_json(LNG('explorer.noPermissionAction'),false);}

		$result = array('list'=>array(),'pageInfo'=>array("page"=>1,"totalNum"=>0,"pageTotal"=>0));
		if($check['error'] == 'listSimple'){$result = array();}
		show_json($result,true,'empty');
	}
	
	// 用户编辑,用户角色设置权限处理;  三权分立系统管理员; 
	private function userAuthEditCheck(){
		$action = strtolower(ACTION);
		$allowUserAuth 	= Action('user.authRole')->authCan('admin.member.userAuth');
		$allowUserEdit 	= Action('user.authRole')->authCan('admin.member.userEdit');
		if(KodUser::isRoot()){
			$allowUserEdit = true;
			$allowUserAuth = $this->config["ADMIN_ALLOW_ALL_ACTION"] == 1 ? true:false;
		}
		if($allowUserEdit && $allowUserAuth){return true;}
		if(!$allowUserEdit && !$allowUserAuth){return true;}
		
		$userCheckActions = array(
			'admin.member.add'			=> array('groupArray'=>'groupInfo','userRole'=>'roleID'),
			//'admin.member.addgroup'		=> array('groupArray'=>'groupInfo'), // 单独检测;
			'admin.member.removegroup'	=> array(),
			'admin.member.switchgroup'	=> array(),
			'admin.member.edit'			=> array('groupArray'=>'groupInfo','userRole'=>'roleID'),
			'admin.member.status'		=> array(),
			'admin.member.remove'		=> array(),
		);
		
		if(!is_array($userCheckActions[$action])){return true;}
		$userCheck = $userCheckActions[$action];
		$groupInfoKey = isset($userCheck['groupArray']) ? $userCheck['groupArray'] : '';

		// 不允许编辑用户,仅允许设置用户权限(安全保密员角色); 只能设置用户角色和用户所在部门权限,不能设置用户所在部门
		if(!$allowUserEdit && $allowUserAuth){
			if($action != 'admin.member.edit'){return false;} 	// 只允许调用edit;其他方法禁用			
			$keepKey  = array('userID','groupInfo','roleID','HTTP_X_PLATFORM','CSRF_TOKEN','URLrouter','URLremote');
			foreach ($this->in as $key=>$v) {
				if(!in_array($key,$keepKey)){unset($this->in[$key]);unset($_REQUEST[$key]);}
			}
			return $this->userAuthEditKeepGroupInfo($groupInfoKey,$this->in['userID'],'onlyAuth');
		}
		
		// 允许编辑用户,不允许设置用户权限(用户管理员; 系统管理员-启用三权分立时)
		// 可以编辑,删除,添加用户; 不能设置用户角色(添加用户:最小普通用户角色),能设置用户所在部门,不能设置用户所在部门权限(最小权限-不可见);
		if($allowUserEdit && !$allowUserAuth){
			// 用户角色权限默认移除;
			if(isset($this->in['roleID']) ){unset($this->in['roleID']);}
			if($action == 'admin.member.add'){
				$this->in['roleID'] = Model('SystemRole')->findRoleDefault();
			}
			return $this->userAuthEditKeepGroupInfo($groupInfoKey,$this->in['userID'],'onlyEdit');
		}
	}
	
	private function userAuthEditKeepGroupInfo($groupInfoKey,$userID,$type='onlyEdit'){
		if(!$groupInfoKey || !isset($this->in[$groupInfoKey])){return true;}
		$authSet 	= json_decode($this->in[$groupInfoKey],true);
		$authSet 	= is_array($authSet) ? $authSet : array();$authSetOld = $authSet;
		$userInfo 	= $userID ? Model('User')->getInfo($userID):array();
		$groupAuth 	= array_to_keyvalue($userInfo['groupInfo'],'groupID','auth');
		$defaultAuth= Model('Auth')->findAuthMinDefault();

		if($type == 'onlyAuth'){
			// 只能修改在某部门的权限(设置部门不在已有部门中-移除; 移除已有部门kv-保留);
			foreach ($authSet as $groupID => $auth){
				if(!isset($groupAuth[$groupID])){unset($authSet[$groupID]);}
			}
			foreach ($groupAuth as $groupID => $auth){
				if(!isset($authSet[$groupID])){$authSet[$groupID] = $auth['id'];}
			}
		}else if($type == 'onlyEdit'){
			$isGroupAppend = isset($this->in['groupInfoAppend']) && $this->in['groupInfoAppend'] == '1';
			if(strtolower(ACTION) == 'admin.member.addgroup'){$isGroupAppend = true;}

			// 只能修改用户所在部门, 不能修改所在部门权限,允许移除所在部门(新添加部门使用默认最小权限)
			foreach ($authSet as $groupID => $auth){
				$authSet[$groupID] = isset($groupAuth[$groupID]) ? $groupAuth[$groupID]['id']:$defaultAuth;
			}
			// 仅添加时,用户所在部门不在设置范围内则自动加入;
			foreach ($groupAuth as $groupID => $auth){
				if($isGroupAppend && !isset($authSet[$groupID])){$authSet[$groupID] = $auth['id'];}
			}
		}
		$this->in[$groupInfoKey] = json_encode($authSet);
		return true;
	}
	
	
	// 多个部门信息检测; 修改所在部门及权限时检测(不允许删除/添加用户在自己非部门管理员的部门)
	public function allowChangeGroupArray($check){
		$groupInfoKey = $check['groupArray'];
		if(!$groupInfoKey || !isset($this->in[$groupInfoKey])){return true;} // 没有传入groupInfo 代表不修改, 对应不检测;
		
		$authSet = json_decode($this->in[$groupInfoKey],true);
		$authSet = is_array($authSet) ? $authSet : array();
		$userInfo  = $this->in[$check['user']] ? Model('User')->getInfo($this->in[$check['user']]):array();
		$groupAuth = array_to_keyvalue($userInfo['groupInfo'],'groupID','auth');$allow = true;
		
		foreach ($authSet as $groupID => $auth){
			if($userInfo && $groupAuth[$groupID]['id'] == $auth) continue;//其他部门权限,不允许修改;
			if(!$this->allowChangeGroup($groupID)){$allow = false;break;}
		}
		if(!$userInfo || !$groupAuth) return $allow;
		
		// 追加用户所在部门;// 该值启用时,已存在所在部门权限继续保持,仅追加新的;
		$isGroupAppend = isset($this->in['groupInfoAppend']) && $this->in['groupInfoAppend'] == '1';
		if(strtolower(ACTION) == 'admin.member.addgroup'){$isGroupAppend = true;}

		// 其他自己无权限部门: 不允许删除,不允许修改;
		foreach ($groupAuth as $groupID => $authInfo){
			if($isGroupAppend && !$authSet[$groupID]){$authSet[$groupID] = $authInfo['id'];}
			if($this->allowChangeGroup($groupID)) continue;
			// if(!$authSet[$groupID]){$allow = false;break;} // 部门自己没有管理权限,报错;
			if(!$authSet[$groupID]){$authSet[$groupID] = $authInfo['id'];} // 去除部门自己没有管理权限,则默认自动加上;
		}
		foreach ($authSet as $groupID => $auth){
			if($this->allowChangeGroup($groupID)) continue;
			if($groupAuth[$groupID]['id'] != $auth){$allow = false;break;}
		}
		$this->in[$groupInfoKey] = json_encode($authSet);
		return $allow;
	}
	
	// 当前用户是否有操作该部门的权限;
	public function allowChangeGroup($groupID){return $this->allowViewGroup($this->userGroupAdmin(),$groupID);}
	public function allowChangeUser($userID){return $this->allowViewUser($this->userGroupAdmin(),$userID);}
	
	// 检测自己是否有权限获取指定用户信息;$returnAllow为true,则返回有权限访问的部分用户id;
	public function allowViewUser($selfGroup,$users,$returnAllow=false){
		if(!$selfGroup || count($selfGroup) == 0) return false;
		$allowAll   = true;$allowHas = array();
		$valueArray = explode(',',trim($users.'',','));//默认多个,逗号分隔;全部都有权限才通过
		foreach ($valueArray as $theID){
			$userInfo  = Model('User')->getInfo($theID);
			$groupList = $userInfo ? $userInfo['groupInfo']:array();
			$groups    = implode(',',array_to_keyvalue($groupList,'','groupID'));
			if($this->allowViewGroup($selfGroup,$groups,true)){
				$allowHas[] = $theID;
			}else{
				$allowAll = false;
				if(!$returnAllow){return $allowAll;}
			}
		}
		return $returnAllow ? implode(',',$allowHas) : $allowAll;
	}
	
	// 检测自己是否有权限获取指定部门信息;$returnAllow为true,则返回有权限访问的部分部门id;
	public function allowViewGroup($selfGroup,$groups,$returnAllow=false){
		if(!$selfGroup || count($selfGroup) == 0) return false;
		$allowAll   = true;$allowHas = array();
		$valueArray = explode(',',trim($groups.'',','));//默认多个,逗号分隔; 全部都有权限才通过
		foreach ($valueArray as $theID){
			if(Model('Group')->parentInGroup($theID,$selfGroup)){
				$allowHas[] = $theID;
			}else{
				$allowAll = false;
				if(!$returnAllow){return $allowAll;}
			}
		}
		return $returnAllow ? implode(',',$allowHas) : $allowAll;
	}

	// 权限修改删除范围处理: 只能操作权限包含内容小于等于自己权限包含内容的类型;  设置用户权限也以此为标准;
	public function allowChangeUserRole($roleID){
		if(KodUser::isRoot() || !$roleID) return true;
		$authInfo = Model('SystemRole')->listData($roleID);
		if($authInfo && $authInfo['administrator'] == 1) return false; // 系统管理员不允许非系统管理员获取,设置
		if(!$this->config["ADMIN_ALLOW_ALL_ACTION"]){return true;} // 启用了三权分立,安全保密员允许获取,或设置用户的角色;
		
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
	public function userGroupAdmin(){
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
	// 自己所在部门()
	public function userGroupAt(){
		static $groupArray = null;
		if($groupArray !== null) return $groupArray;

		$groupList 	= Session::get("kodUser.groupInfo");
		$groupArray = Model('Group')->groupMerge(array_to_keyvalue($groupList,'','groupID'));
		return $groupArray;
	}
	
	public function  userGroupRoot(){
		return $this->userGroupRootShow();
	}
	// 自己可见的部门; 所在的部门向上回溯;
	public function userGroupRootShow(){
		static $groupArray = null;
		if($groupArray !== null) return $groupArray;

		$groupCompany = $GLOBALS['config']['settings']['groupCompany'];
		$groupList 	= Session::get("kodUser.groupInfo");$groupArray = array();
		foreach ($groupList as $group){
			$groupRoot = Model('Group')->groupShowRoot($group['groupID'],$groupCompany);
			$groupArray = array_merge($groupArray,$groupRoot);
		}
		$groupArray = Model('Group')->groupMerge($groupArray);
		return $groupArray;
	}
}