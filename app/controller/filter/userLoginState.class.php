<?php

/**
 * 同一账号限制同时登录数;0=不限制;
 * guest/admin不限制  不限制=最多50个登录设备;
 * 
 * 对外方法:
 * Action("filter.userLoginState")->userListLoad(); // 当前账号在线设备列表;
 * Action("filter.userLoginState")->userLogoutTrigger($userID,$sid);// 主动踢下线某个设备;
 */
class filterUserLoginState extends Controller {
	function __construct() {
		parent::__construct();
	}
	public function bind(){
		Hook::bind('user.index.loginBefore',array($this,'checkLimit'));
		Hook::bind('user.index.logoutBefore',array($this,'logoutBefore'));
	}

	// 同时登录限制处理; (只在登录时检测在线的session)
	// 暂不支持通过accessToken的共享session方式登录(app扫描登录, 外部accessToken打开文件或网页); 
	// 		有一点点门槛;同时一个点退出其他所有设备都会退出;
	public function checkLimit($user){
		$limitMax = 500;
		$limit = $GLOBALS['config']['settings']['userLoginLimit'];
		if($limit == 0) {$limit = $limitMax;};
		
		// 权限检测;guest/admin不限制 [guest按没有写入权限及新建权限判断]
		$role 		= Model('SystemRole')->listData($user['roleID']);
		$roleAuth 	= explode(',',trim($role['auth'],','));
		$isRoot  	= $role['administrator'] == '1';
		$isGuest 	= !in_array('explorer.add',$roleAuth) && !in_array('explorer.upload',$roleAuth);
		if($isRoot || $isGuest){$limit = $limitMax;}
		
		$sid = Session::sign();
		$loginList = $this->userListLoad($user['userID']);
		unset($loginList[$sid]);
		
		// 已经有序, 保留数组后$limit - 1项; 前面的退出处理(更早登录的);
		$indexFrom = count($loginList) - ($limit - 1);
		$indexFrom = $indexFrom <= 0 ? 0 : $indexFrom;
		$loginListNew = array();$index = 0;
		foreach($loginList as $item){
			if($index >= $indexFrom){
				$loginListNew[$item['sid']] = $item;
				$index++;continue;
			}
			$this->userLogoutSession($item['sid']);
			$index++;
		}
		$loginListNew[$sid] = array(
			'time' 	=> timeFloat(),
			'sid'	=> $sid,
			'ip' 	=> get_client_ip(),
			'ua'	=> $_SERVER['HTTP_USER_AGENT'].';',
			'device'=> Action("filter.userCheck")->getDevice()
		);
		if(isset($this->in['HTTP_X_PLATFORM'])){
			$loginListNew[$sid]['HTTP_X_PLATFORM'] = $this->in['HTTP_X_PLATFORM'];
		}
		$this->userListSet($user['userID'],$loginListNew);
	}
	
	public function logoutBefore($user){
		if(!is_array($user)) return;
		$this->userLogoutTrigger($user['userID'],Session::sign());
	}
	
	// 踢出登录用户(根据sessionID)
	public function userLogoutTrigger($userID,$sid){
		$loginList = $this->userListLoad($userID);
		if(!is_array($loginList[$sid])) return;

		$this->userLogoutSession($sid);
		unset($loginList[$sid]);
		$this->userListSet($userID,$loginList);
	}
	private function userLogoutSession($sid){
		$session = Session::getBySign($sid);
		if(!is_array($session['kodUser'])) return;
		$session['kodUser'] = false;
		$session['kodUserLogoutTrigger'] = true;
		Session::setBySign($sid,$session);
	}
	
	// 获取当前用户在线列表; 自动清理不在线的设备;
	public function userListLoad($userID){
		$key = 'userLoginList_'.$userID;
		$loginList = Cache::get($key);
		$loginList = is_array($loginList) ? $loginList : array();
		if(count($loginList) == 0) return $loginList;
		
		$loginListNew = array();
		foreach($loginList as $loginInfo){
			$session = Session::getBySign($loginInfo['sid']);
			if(!$session || !is_array($session['kodUser'])) continue;
			$loginListNew[$loginInfo['sid']] = $loginInfo;
		}
		$loginListNew = array_sort_by($loginListNew,'time');
		$this->userListSet($userID,$loginListNew);
		return $loginListNew;
	}
	private function userListSet($userID,$loginList){
		$key = 'userLoginList_'.$userID;
		Cache::set($key,$loginList,3600*24*30);
		// write_log($key.';count='.count($loginList).';'.ACTION);
	}
}
