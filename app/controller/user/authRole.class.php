<?php
/*
 * @link http://kodcloud.com/
 * @author warlee | e-mail:kodcloud@qq.com
 * @copyright warlee 2014.(Shanghai)Co.,Ltd
 * @license http://kodcloud.com/tools/license/license.txt
 */

/**
 * 用户角色，全局权限拦截；
 */
class userAuthRole extends Controller {
	protected static $authRole;
	function __construct() {
		parent::__construct();
	}
	public function authCanDownload(){
		return $this->authCan('explorer.download');
	}
	public function isRoot(){return isset($GLOBALS['isRoot'])?!!$GLOBALS['isRoot']:false;} 
	public function authCanSearch(){return $this->authCan('explorer.search');}
	public function authCanRead(){return $this->authCan('explorer.view');}
	public function authCanEdit(){return $this->authCan('explorer.edit');}
	public function authCan($action){
		if($this->isRoot()) return true;
		$userRole = $this->userRoleAuth();
		$action   = strtolower($action);
		if($userRole['roleList'][$action] == 1){
			return true;
		}
		return false;
	}

	// 未登录：允许的 控制器方法;
	// 已登录：不允许的 控制器&方法；
	// 其他的可以通过内部Action进行方法调用转发；
	public function autoCheck(){
		$theMod 	= strtolower(MOD);
		$theST 		= strtolower(ST);
		$theAction 	= strtolower(ACTION);
		$authNotNeedLogin = $this->config['authNotNeedLogin'];
		foreach ($authNotNeedLogin as &$val) {
			$val = strtolower($val);
		};unset($val);
		if(in_array($theAction,$authNotNeedLogin)) return;
		foreach ($authNotNeedLogin as $value) {
			$item = explode('.',$value); //MOD,ST,ACT
			if( count($item) == 2 && 
				$item[0] === $theMod && $item[1] === '*'){
				return;
			}
			if( count($item) == 3 && 
				$item[0] === $theMod && $item[1] === $theST  &&$item[2] === '*'){
				return;
			}
		}
		// 排除不需要登录的方法；其他的都需要登录
		$user = Session::get("kodUser");
		if(!$user){
			show_json(LNG('user.loginFirst'),ERROR_CODE_LOGOUT);
		}
		//系统管理员不受权限限制
		if($this->isRoot()) return true;
		
		$userRole = $this->userRoleAuth();
		$allowAction = $userRole['allowAction'];
		// pr($allowAction[$theAction],$theAction,$user,$userRole);exit;
		if(!$allowAction[$theAction]){ //不存在该方法或
			show_json(LNG('explorer.noPermissionAction'),false,1004);
		}
	}
	
	// 用户权限解析处理;处理成最终动作
	public function userRoleAuth($roleID=false){
		if(!$roleID){
			$user = Session::get('kodUser');
			if(!$user || !$user['roleID']) return false;
			$roleID = $user['roleID'];
		}
		if(!self::$authRole){self::$authRole = array();}
		if(isset(self::$authRole[$roleID])){
			return self::$authRole[$roleID];
		}
		
		$authAllowAction 	= $this->config['authAllowAction'];
		$roleAction 		= $this->config['authRoleAction'];		
		$roleInfo = Model('SystemRole')->listData($roleID);		
		$userRoleAllow  = $this->authCheckAlias($roleInfo['auth']);
		$authRoleList 	= array();
		$allowAction 	= array();
		foreach ($roleAction as $role => $modelActions) {
			$enable = intval(in_array($role,$userRoleAllow));
			$authRoleList[$role] = $enable;
			if(!$modelActions || !is_array($modelActions)) continue;
			$actionArray = array();
			foreach ($modelActions as $controller => $stActions) {
				if(!$stActions) continue;
				$stActions = explode(',',trim($stActions,','));
				foreach ($stActions as $action) {
					$actionArray[] = $controller.'.'.$action;
				}
			}
			foreach ($actionArray as $action) {
				$action = strtolower($action);//统一转为小写
				if(!isset($allowAction[$action])){
					$allowAction[$action] = $enable;
				}else{
					/**
					 * false可以覆盖true;true不能覆盖false;
					 * 'explorer.download'	=> array('explorer.index'=>'zipDownload...')
					 * 'explorer.zip'		=> array('explorer.index'=>'zipDownload...')
					*/
					if($allowAction[$action]){
						$allowAction[$action] = $enable;
					}
				}
			}
		}
		
		//不需要检测的动作白名单; 优先级最高;
		foreach ($authAllowAction as $action) { 
			$allowAction[strtolower($action)] = 1;
		}
		$result = array(
			'info'			=> $roleInfo,
			'allowAction'	=> $allowAction,
			'roleList'		=> $authRoleList
		);
		self::$authRole[$roleID] = $result;
		return $result;
	}
	
	// 处理权限前置依赖;
	private function authCheckAlias($auth){
		$authList = explode(',',trim($auth,','));
		$alias = array(
			'explorer.add'			=> 'explorer.view',
			'explorer.download'		=> 'explorer.view',
			'explorer.share'		=> 'explorer.view,explorer.upload,explorer.add,explorer.download',
			// 'explorer.upload'		=> 'explorer.add',
			'explorer.edit'			=> 'explorer.add,explorer.view,explorer.upload',
			'explorer.remove'		=> 'explorer.edit',
			'explorer.move'			=> 'explorer.edit',
			'explorer.unzip'		=> 'explorer.edit',
			'explorer.zip'			=> 'explorer.edit',
			'explorer.serverDownload'	=> 'explorer.edit',
			
			'admin.role.edit'		=> 'admin.role.list',
			'admin.job.edit'		=> 'admin.job.list',
			'admin.member.userEdit'	=> 'admin.member.list',
			'admin.member.groupEdit'=> 'admin.member.list',
			'admin.auth.edit'		=> 'admin.auth.list',
			'admin.plugin.edit'		=> 'admin.plugin.list',
			'admin.storage.edit'	=> 'admin.storage.list',
			'admin.autoTask.edit'	=> 'admin.autoTask.list',
			
			// 'a'=>'a','c'=>'d','d'=>'c', // 循环依赖处理;
			// 'x1'=>'x2,x3','x3'=>'x1,x2',
		);
		foreach ($alias as $theKey => $aliasAction){
			$alias[$theKey] = explode(',', $aliasAction);
		}
		$aliasAll = array();//key以来的所有上层key;
		foreach ($alias as $theKey => $aliasAction){
			$aliasAll[$theKey] = $this->authCheckAliasParent($theKey,$alias);
		}

		$userRoleAllow = array();
		for ($i=0; $i < count($authList); $i++){
			$authAction = $authList[$i];
			if(!isset($aliasAll[$authAction])){
				$userRoleAllow[] = $authList[$i];continue;
			}
			// 所有依赖都在当前权限中,才算拥有该权限;
			$needAuth = $aliasAll[$authAction];$allHave  = true;
			for ($j=0; $j < count($needAuth); $j++) { 
				if(!in_array($needAuth[$j],$authList)) {$allHave = false;break;}
			}
			if($allHave){$userRoleAllow[] = $authList[$i];}
		}
		return $userRoleAllow;
	}
	private function authCheckAliasParent($theKey,&$alias,&$result=array()){
		$parents = $alias[$theKey];
		if(!$parents) return false;
		for ($i=0; $i < count($parents); $i++) {
			if(isset($result[$parents[$i]])) continue;
			$result[$parents[$i]] = true;
			$this->authCheckAliasParent($parents[$i],$alias,$result);
		}
		return array_keys($result);
	}
}
