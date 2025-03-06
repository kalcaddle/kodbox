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
class userAuthPlugin extends Controller{
	protected static $authRole;
	function __construct(){
		parent::__construct();
	}

	/**
	 * 插件执行路由检测：
	 * 
	 * plugin/epubReader/index&a=1
	 * plugin/epubReader/&a=1 ==>ignore index;
	 */
	public function autoCheck(){
		$theMod = strtolower(MOD);
		if ($theMod != 'plugin') return;
		if ($this->checkAuth(ST)) return;
		$msg = $this->lastError ? $this->lastError : LNG('explorer.noPermissionAction');
		if($_SERVER['REQUEST_METHOD'] == 'GET'){ // 插件处理;
			show_tips($msg.'; '.ST);
		}
		show_json($msg, false, 2001);
	}

	/**
	 * 插件权限检测
	 * 1. 有该插件,且已开启;
	 * 2. 登录检测；不需要登录的直接返回；
	 * 3. 权限检测
	 */
	public function checkAuth($appName){
		$plugin = Model("Plugin")->loadList($appName);
		if (!$plugin)  return true;//不存在插件,转发接口
		if ($plugin['status'] == 0)  {
			$this->lastError = LNG('admin.plugin.closedError');
			return false;
		}
		
		$config = $plugin['config'];
		if (isset($config['pluginAuthOpen']) && $config['pluginAuthOpen']) return true;
		if (KodUser::isRoot()){
			if($GLOBALS['config']["ADMIN_ALLOW_ALL_ACTION"] || !$GLOBALS['config']["ADMIN_AUTH_LIMIT_PLUGINS"]) return true;
			$disablePlugin = explode(',',strtolower($GLOBALS['config']["ADMIN_AUTH_LIMIT_PLUGINS"]));
			return in_array(strtolower($appName),$disablePlugin) ? false : true;//系统管理员,开启三权分立时,限制插件处理;
		}
		$auth = isset($config['pluginAuth']) ? $config['pluginAuth'] : null;
		if(!$auth) return false;
		return $this->checkAuthValue($auth);
	}

	/**
	 * 检测用户是否在用户选择数据中
	 * @param  [type] $info 组合数据  "{"all":"0","user":"2,4","group":"10,15","role":"4,3"}"
	 * @return [type]       [description]
	 */
	public function checkAuthValue($auth,$user=false){
		if( is_string($auth) ){
			$auth = @json_decode($auth, true);
		}
		if (isset($auth['all']) && $auth['all'] == '1') return true; // 全部则无需登录也可以访问;
		if (!$user){$user = Session::get('kodUser');}
		if (!$auth || !$user || !is_array($auth)) return false;
		
		// all:代表任意登录用户; root:代表系统管理员;
		if ($auth['user'] == 'all')  return true;
		if ($auth['user'] == 'admin' && KodUser::isRoot()) return true;
		if ($auth['role'] === '1' && KodUser::isRoot()) return true;
		
		$groups  = array_to_keyvalue($user['groupInfo'],'','groupID');
		$auth['user']  = $auth['user']  ? explode(',',$auth['user']) :  array();
		$auth['group'] = $auth['group'] ? explode(',',$auth['group']) : array();
		$auth['role']  = $auth['role']  ? explode(',',$auth['role']) :  array();
		
		//所在目标用户、角色、部门进行检测
		if( in_array($user['userID'], $auth['user']) ) return true;
		if( in_array($user['roleID'], $auth['role']) ) return true;
		foreach ($groups as $id) {
			if (in_array($id, $auth['group'])) return true;
		}
		return false;
	}
}
