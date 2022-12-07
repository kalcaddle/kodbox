<?php
/*
 * @link http://kodcloud.com/
 * @author warlee | e-mail:kodcloud@qq.com
 * @copyright warlee 2014.(Shanghai)Co.,Ltd
 * @license http://kodcloud.com/tools/license/license.txt
 */

/**
 * 检测某人对某文档操作权限
 */
class explorerAuthUser extends Controller {
	function __construct() {
		parent::__construct();
	}

	/**
	 * 检测文档权限，是否支持$action动作
	 * path: 支持物理路径/io路径/source路径; (不支持其他路径)
	 * action: view,download,upload,edit,remove,share,comment,event,root
	 */
	public function can($path,$action,$userID){
		$userInfo = Model('User')->getInfo($userID);
		if($userInfo['status'] == '0' || !$userInfo['roleID']) return false;//用户已禁用;

		$roleInfo = Action('user.authRole')->userRoleAuth($userInfo['roleID']);
		$isRoot = $roleInfo && ($roleInfo['info']['administrator'] == 1);
		if(!$roleInfo) return false;
		if(!$isRoot && !$this->userRoleCheck($roleInfo,$action)) return false;// 没有对应权限;
		
		
		$parse  = KodIO::parse($path);$ioType = $parse['type'];
		// 物理路径 io路径拦截；只有管理员且开启了访问才能做相关操作;
		if( $ioType == KodIO::KOD_IO || $ioType == false ){
			if($isRoot && $this->config["ADMIN_ALLOW_IO"]) return true;
			return false;
		}
		
		if($isRoot && $this->config["ADMIN_ALLOW_SOURCE"]) return true;
		$pathInfo = Model('Source')->pathInfo($parse['id']);
		$targetType = $pathInfo['targetType'];
		if(!$pathInfo || $pathInfo['isDelete'] == '1') return false;//不存在,不判断文档权限;
		if( $targetType != 'user' && $targetType != 'group' ) return false;// 不是个人或部门文档
		if( $targetType == 'user' && $pathInfo['targetID'] != $userID ) return false; //个人文档但不是自己的文档

		//部门文档：权限拦截；会自动匹配权限；我在的部门会有对应权限
		if($targetType == 'group'){
			$selfAuth  = $this->makeUserAuth($userID,$parse['id']);
			if(!$selfAuth || !Model("Auth")->authCheckAction($selfAuth['authValue'],$action)) return false;
		}
		return true;
	}
	
	public function canShare($shareInfo){
		if(!$shareInfo) return false;
		// 兼容早期版本,该字段为空的情况;
		if(!$shareInfo['sourcePath'] && $shareInfo['sourceID'] != '0'){
			$shareInfo['sourcePath'] = KodIO::make($shareInfo['sourceID']);
		}
		
		// 系统分享;则不检测;
		$isSystemSource 	= '/systemPath/systemSource/';
		$pathDisplay 		= _get($shareInfo,'sourceInfo.pathDisplay');
		$isSystem 			= _get($shareInfo,'sourceInfo.targetType') == 'system';
		if(substr($pathDisplay,0,strlen($isSystemSource)) == $isSystemSource && $isSystem) return true;
		return $this->can($shareInfo['sourcePath'],'share',$shareInfo['userID']);
	}
	
	public function makeUserAuth($userID,$sourceID){
		$pathInfo  = Model('Source')->pathInfo($sourceID);
		$authList  = Model("SourceAuth")->getSourceList(array($sourceID),false,$userID);
		if( $authList && isset($authList[$sourceID])) return $authList[$sourceID];
		return Action('explorer.listGroup')->pathGroupAuthMake($pathInfo['targetID'],$userID);
	}
	
	private function userRoleCheck($roleInfo,$action){
		$actionMap = array(
			'view'		=> 'explorer.view',
			'download'	=> 'explorer.download',
			'upload'	=> 'explorer.upload',
			'edit' 		=> 'explorer.edit',
			'remove'	=> 'explorer.remove',
			'share'		=> 'explorer.share',
			'comment'	=> 'explorer.edit',
			'event'		=> 'explorer.edit',
			'root'		=> 'explorer.edit',
		);
		if(!isset($actionMap[$action])) return false;
		$theKey = $actionMap[$action];
		if(!$roleInfo || !isset($roleInfo['roleList'][$theKey])){
			return false;
		}
		return $roleInfo['roleList'][$theKey] == 1;
	}
}