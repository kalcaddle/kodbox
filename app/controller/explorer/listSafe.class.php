<?php
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/

/**
 * 私密保险箱处理(个人根目录置顶; 状态:未启用,启用-已授权/未授权)
 * 
 * 根目录权限:不支持:复制/剪切/重命名/拖拽; 
 * 子内容权限:不能外链分享/协作分享/收藏/添加标签/发送到桌面快捷方式; 不能通过用户根目录搜索;
 */
class explorerListSafe extends Controller{
	private $model;
	public function __construct(){
		parent::__construct();
		$this->model = Model("Source");
	}
	// 权限检测
	public function authCheck($pathInfo,$action){
		if(!isset($pathInfo['sourceAt']) || $pathInfo['sourceAt'] != 'pathSafeSpace'){return;}

		$spaceInfo = $this->spaceInfo();
		if($spaceInfo['type'] != 'isLogin'){
			if(ACTION == 'explorer.list.path'){show_json($spaceInfo['listEmpty'],true);}
			return LNG('explorer.safe.isNotLogin');
		}
		$notAllowAction = array('share'); // 保险箱内内容不支持的操作: 分享编辑/删除;
		if(in_array($action,$notAllowAction)){return LNG('explorer.pathNotSupport');}
		return false;
	}
	
	// 保险箱内内容不支持的操作;sourceID 路径检测(主动调用)
	public function authCheckAllow($path){
		if(!is_numeric($path) || !$path){return;}
		$pathInfo = $this->model->pathInfo($path);
		if(!$pathInfo || $pathInfo['sourceAt'] != 'pathSafeSpace'){return;}
		show_json(LNG('explorer.pathNotSupport'),false);
	}
	
	// 追加到根目录;
	public function appendSafe(&$data){
		$this->appendSafeChildren($data);
		$this->appendSafeUserRootAdmin($data);
		$pathInfo = $data['current'];
		if(!$pathInfo || intval($this->in['page']) > 1)  return false;
		if(!isset($pathInfo['targetType']) || !isset($pathInfo['targetID']) || !isset($pathInfo['parentID'])){return;}
		if($pathInfo['parentID'] != 0 || $pathInfo['targetType'] != 'user' || $pathInfo['targetID'] != USER_ID) return;
		if(defined('KOD_FROM_WEBDAV')){return;}
		
		$userInfo = Model("User")->getInfoFull(USER_ID);
		if(_get($data,'current.sourceID') == _get($userInfo,'metaInfo.pathSafeFolder')) return;
		if(Model("UserOption")->get('pathSafeSpaceShow') != '1'){return;} // 个人设置已隐藏;
		$spaceInfo = $this->spaceInfo();
		
		// 系统全局关闭,且自己未启用时不显示;
		if(Model("SystemOption")->get('pathSafeSpaceEnable') != '1'){
			if($spaceInfo['type'] == 'isNotOpen'){return;}
			// return;
		}
		$data['folderList'][] = $spaceInfo['folderRoot'];
	}

	// 私密空间子列表处理(未登录提示登录; 已登录子内容追加标记)
	private function appendSafeChildren(&$data){
		$pathInfo = $data['current'];
		
		// 私密保险箱根目录, 追加应用根目录入口;
		if($pathInfo['parentID'] == 0 && _get($pathInfo,'metaInfo.pathSafeFolderUser')){
			$userInfo = Model('User')->getInfoFull($pathInfo['targetID']);
			$userAppRoot = _get($userInfo,'metaInfo.pathAppRoot','');
			if($userAppRoot){
				$data['folderList'][] = array(
					'name'		=> LNG('explorer.appFolder'),
					'path'		=> '{source:'.$userAppRoot.'}/',
					'icon'		=> 'kod-box',//kod-box kod-folder2
					'type'		=> 'folder','pathReadOnly'=>true,
					'pathDesc'	=> LNG('explorer.appFolder'),
					'metaInfo' 	=> array('systemSort'=>3000000000,'systemSortHidden'=>true),
				);
			}
		}
		if(!isset($pathInfo['sourceAt']) || $pathInfo['sourceAt'] != 'pathSafeSpace'){return;}

		$spaceInfo = $this->spaceInfo();
		if($spaceInfo['type'] != 'isLogin'){ // 未登录访问拦截;
			return show_json($spaceInfo['listEmpty'],true);
		}
		foreach ($data['fileList'] as &$item){
			$item['sourceAt'] = 'pathSafeSpace';
		};unset($item);
		foreach ($data['folderList'] as &$item){
			$item['sourceAt'] = 'pathSafeSpace';
		};unset($item);		
	}
	
	// 系统管理员: 管理用户根目录--显示私密文件夹处理(默认关闭)
	private function appendSafeUserRootAdmin(&$data){
		if(!$this->config["ADMIN_ALLOW_USER_SAFE"]){return;}
		$pathInfo = $data['current'];
		if(!KodUser::isRoot() || !$pathInfo || !$pathInfo['sourceID']){return;}
		if($pathInfo['parentID'] != 0 || $pathInfo['targetType'] != 'user') return;
		if($pathInfo['targetID'] == USER_ID) return;//自己的根目录;
		$userInfo = $userInfo = Model("User")->getInfoFull($pathInfo['targetID']);
		$userSafeSpace = _get($userInfo,'metaInfo.pathSafeFolder');
		if(!$userSafeSpace){return;}
		
		$infoChange = array(
			'name'		=> LNG('explorer.safe.name'),
			'icon'		=> 'user-folder-safe',
			'metaInfo' 	=> array('systemSort'=>3000000000,'systemSortHidden'=>true),
			'sourceAt'	=> 'pathSafeSpace',
			'ownerUser'	=> '',
		);
		$sourceInfo = Model("Source")->pathInfo($userSafeSpace);
		if(!$sourceInfo){return;}

		// 该用户保险箱根目录;
		if($userSafeSpace == $pathInfo['sourceID']){
			$userRootName = LNG('common.user').'['.$userInfo['name'].']';
			$userRoot = KodIO::make(_get($userInfo,'sourceInfo.sourceID'));
			$infoChange['pathAddress'] = array(
				array("name"=> $userRootName,"path"=>$userRoot),
				array("name"=> LNG('explorer.safe.name'),"path"=>$sourceInfo['path']),
			);
			$data['current'] = array_merge($data['current'],$infoChange);
			return;
		}
		// 该用户保险箱子目录;
		$data['folderList'][] = array_merge($sourceInfo,$infoChange);
	}
	
	
	// 状态及数据处理; isNotOpen/isNotLogin/isLogin;
	private function spaceInfo(){
		static $result = false;
		if(is_array($result)) return $result;
		KodUser::checkLogin();
		
		$uesrID     = Session::get('kodUser.userID');
		$userInfo   = Model("User")->getInfoFull($uesrID);
		$safeFolder = _get($userInfo,'metaInfo.pathSafeFolder','');
		$isLogin 	= Session::get('pathSafe-userIsLogin') == '1' ? true : false;
		$type = !$safeFolder ? 'isNotOpen' : ($isLogin ? 'isLogin' : 'isNotLogin');
		$message = array(
			'isNotOpen' 	=> LNG('explorer.safe.isNotOpen'),
			'isNotLogin' 	=> LNG('explorer.safe.isNotLogin'),
			'isLogin' 		=> LNG('explorer.safe.isLogin'),
		);
		$result = array('type'=>$type,'folder'=>$safeFolder,'message'=>$message[$type]);
		$result['folderRoot'] = array(
			'name'	=> LNG('explorer.safe.name'),
			'path'	=> '{block:safe}/',
			'type'	=> 'folder',
			'pathDesc'	=> LNG('explorer.safe.desc'),
			'pathSafe'	=> $type,'pathReadOnly'=>true,
			'metaInfo' 	=> array('systemSort'=>3000000000,'systemSortHidden'=>true),
		);
		if($type != 'isLogin'){
			$result['folderRoot']['hasFile'] = false;
			$result['folderRoot']['hasFolder'] = false;
		}
		
		$result['listEmpty']  = array(
			'current' 		=> $result['folderRoot'],
			'folderList' 	=> array(),
			'fileList' 		=> array(),
			'pathSafe'		=> $type,
			'pathSafeMsg'	=> $result['message'],
			'thisPath'		=> $result['folderRoot']['path'],
		);
		//trace_log($result);
		return $result;
	}
	
	// 私密保险箱根目录; 包含功能入口(未开启-提示开启; 登录)
	public function listRoot(){
		$spaceInfo = $this->spaceInfo();
		if($spaceInfo['type'] != 'isLogin'){ // 未登录访问拦截;
			return show_json($spaceInfo['listEmpty'],true);
		}

		// 进入目录;
		$folder = '{source:'.$spaceInfo['folder'].'}/';
		$listData = Action("explorer.list")->path($folder);
		return show_json($listData);
	}

	// 私密空间功能入口(复用列表接口,不做权限拦截)
	public function action(){
		$spaceInfo = $this->spaceInfo();
		$type = $spaceInfo['type'];
		if($this->in['type'] != 'open' && $type == 'isNotOpen'){
			return show_json($spaceInfo['listEmpty'],true);
		}
		
		$userInfo = Model("User")->getInfoFull(USER_ID);
		$userID   = $userInfo['userID'];
		switch ($this->in['type']){
			case 'open':
				if(Model("SystemOption")->get('pathSafeSpaceEnable') != '1'){ // 未开启私密保险箱功能;
					show_json(LNG('explorer.safe.isNotOpen').' [admin]',false);
				}
				if($type != 'isNotOpen'){show_json(LNG('explorer.safe.doOpenOpend'),false);}
				if(!$userInfo['email']){show_json(LNG('explorer.safe.doOpenTips'),false);}
				
				$password = Input::get('password','require');
				Model("Source")->userPathSafeAdd($userID);
				Model("User")->metaSet($userID,'pathSafePassword',md5($password));
				Action('user.index')->refreshUser($userID);
				show_json(LNG('explorer.safe.doOpenSuccess'),true);
				break;
			case 'login':
				if($type == 'isLogin'){return show_json(LNG('explorer.success'),true);}
				$password = Input::get('password','require');
				if(md5($password) != _get($userInfo,'metaInfo.pathSafePassword')){
					show_json(LNG('ERROR_USER_PASSWORD_ERROR'),false);
				}
				Session::set('pathSafe-userIsLogin','1');
				show_json(LNG('explorer.safe.doLoginOk'),true);
				break;
			case 'logout':
				if($type != 'isLogin'){show_json(LNG('user.loginFirst'),false);}
				Session::remove('pathSafe-userIsLogin');
				show_json(LNG('explorer.success'),true);
				break;
			case 'close': // 关闭私密保险箱; 移动到根目录,清空密码;
				if($type != 'isLogin'){return show_json($spaceInfo['listEmpty'],true);}
				$userRoot   = $userInfo['sourceInfo']['sourceID'];
				$safeFolder = _get($userInfo,'metaInfo.pathSafeFolder','');
				
				Model("Source")->where( array("sourceID"=> $safeFolder) )->data(array('fileType'=>''))->save();
				Model("Source")->move($safeFolder,$userRoot,REPEAT_RENAME_FOLDER,LNG('explorer.safe.name').'-copy');
				Model("Source")->metaSet($safeFolder,'pathSafeFolderUser',null);
				Model("User")->metaSet($userID,'pathSafeFolder',null);
				Model("User")->metaSet($userID,'pathSafePassword',null);
				Session::remove('pathSafe-userIsLogin');
				Action('user.index')->refreshUser($userID);
				show_json(LNG('explorer.success'),true);
				break;
			case 'resetPassword':
				$data = Input::getArray(array(
					'passwordOld'	=> array('check'=>'require'),
					'password'		=> array('check'=>'require'),
				));
				$password = Input::getArray('password','require');
				if(md5($data['passwordOld']) != _get($userInfo,'metaInfo.pathSafePassword')){
					show_json(LNG('user.oldPwdError'),false);
				}
				Model("User")->metaSet($userID,'pathSafePassword',md5($data['password']));
				Session::remove('pathSafe-userIsLogin');
				Action('user.index')->refreshUser($userID);
				show_json(LNG('explorer.safe.passwordChanged'),true);
				break;
			case 'findPasswordSendCode':
				$cacheKey   = 'pathSafe-findPasswordCheckCode_'.$userID;
				$checkInfo  = Cache::get($cacheKey);
				if(is_array($checkInfo) && time() - $checkInfo['time'] < 60){ //一分钟内只发送一次;
					$message = LNG('explorer.safe.sendMailTips').clear_html($userInfo['email'])."<br/>".LNG('explorer.safe.sendMailGet');
					show_json($message."<br/>".LNG('explorer.safe.doCheckLimit'),true);
				}
				$checkInfo  = array('code'=> rand_string(6,1),'time'=>time(),'use'=>0);
				Cache::set($cacheKey,$checkInfo,3600);
				$this->sendCheckCode($checkInfo['code']);
				show_json(LNG('explorer.success'),true);
				break;
			case 'findPasswordReset':
				$cacheKey   = 'pathSafe-findPasswordCheckCode_'.$userID;
				$checkInfo  = Cache::get($cacheKey);
				if(!is_array($checkInfo) || time() - $checkInfo['time'] > 60*60){ // 1小时过期
					Session::remove('pathSafe-findPasswordCheckCode');
					show_json(LNG('user.codeExpired'),false);
				}
				$data = Input::getArray(array(
					'checkCode'	=> array('check'=>'require'),
					'password'	=> array('check'=>'require'),
				));
				if($data['checkCode'] != $checkInfo['code']){
					$checkInfo['use'] += 1;
					if($checkInfo['use'] > 5){
						Cache::remove($cacheKey);
						show_json(LNG('user.codeErrorTooMany'),false);
					}
					Cache::set($cacheKey,$checkInfo);
					show_json(LNG('user.codeError'),false);
				}
				Model("User")->metaSet($userID,'pathSafePassword',md5($data['password']));
				Action('user.index')->refreshUser($userID);
				Cache::remove($cacheKey);
				show_json(LNG('explorer.safe.passwordChanged'),true);
				break;
			default:break;
		}
		return false;
	}
	
	// 找回密码发送验证码;
	private function sendCheckCode($code){
		$userInfo = Session::get("kodUser");
		$title  = ' '.LNG('explorer.safe.sendMailTitle');
		$email  = $userInfo['email'];
		$result = Action("user.bind")->sendEmail($email,'pathSafe-findPassword',$title,$code);
		if(!$result['code']){show_json($result['data'],false);}
		show_json(LNG('explorer.safe.sendMailTips').clear_html($email)."<br/>".LNG('explorer.safe.sendMailGet'),true);
	}
}
