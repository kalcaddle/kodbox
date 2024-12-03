<?php
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/

/**
 * 加密文件夹处理
 */
class explorerListPassword extends Controller{
	private $model;
	public function __construct(){
		parent::__construct();
		$this->model = Model("Source");
	}
	// 权限检测
	public function authCheck($pathInfo,$action){
		$passInfo = $this->checkAllowPassword($data['current']);
		if(!$passInfo){return;}
		return LNG('explorer.folderPass.tips');
	}
	
	// 追加到根目录;
	public function appendSafe(&$data){
		$passInfo = $this->checkAllowPassword($data['current']);
		if(!$passInfo){return;}
		
		unset($passInfo['folderPassword']);
		$data['fileList']   = array();
		$data['folderList'] = array();
		$data['folderTips'] = LNG('explorer.folderPass.tips').";".htmlentities($passInfo['folderPasswordDesc']);
		$data['pathDesc']   = $data['folderTips'];
		$data['folderPasswordNeed'] = $passInfo;
	}
	
	private function checkAllowPassword($pathInfo){
		if(!$pathInfo || empty($pathInfo['sourceID']) || empty($pathInfo['parentLevel'])){return false;}
		$canEdit  = is_array($pathInfo['auth']) ? Model("Auth")->authCheckEdit($pathInfo['auth']['authValue']):false;
		$userSelf = $pathInfo['targetType'] == 'user' && $pathInfo['targetID'] == KodUser::id();
		if($userSelf || $canEdit || KodUser::isRoot()){return;}
		
		$passInfo = $this->folderPasswordFind($pathInfo);
		if(!$passInfo){return false;}
		
		$sessionKey = "folderPassword_".$passInfo['sourceID'];
		if( $passInfo && !empty($this->in['folderPassword']) && 
			$this->in['folderPassword'] == $passInfo['folderPassword']){
			Session::set($sessionKey,$passInfo['folderPassword']);
			$passInfo = false;// 输入密码正确;
		}
		if( $passInfo && Session::get($sessionKey) == $passInfo['folderPassword']){
			$passInfo = false;// 回话中存在密码,且一致;
		}
		return $passInfo;
	}
	
	
	// 向上查找上层文件夹包含密码设置的文件夹(上级多层包含密码时,仅匹配最近的一层密码设置; )
	private function folderPasswordFind($pathInfo){
		static $_cache = array();

		$keys = array('folderPassword','folderPasswordDesc','folderPasswordTimeTo','folderPasswordUser');
		$parentLevel = $this->model->parentLevelArray($pathInfo['parentLevel']);
		$parentLevel[] = $pathInfo['sourceID'];
		$parentLevel = array_reverse($parentLevel);
		$findHas = false;
		foreach($parentLevel as $sourceID){
			if(empty($_cache[$sourceID])){continue;}
			$findHas = $_cache[$sourceID];break;
		}
		if($findHas || isset($_cache[$pathInfo['sourceID']])){return $findHas;}
		
		$where   = array('sourceID'=>array('in',$parentLevel),'key'=>array('in',$keys));
		$metaArr = Model('io_source_meta')->where($where)->select();
		$metaArr = array_to_keyvalue_group($metaArr,'sourceID');
		foreach($metaArr as $sourceID=>$sourceMeta){
			$meta = array_to_keyvalue($sourceMeta,'key','value');
			if(empty($meta['folderPassword'])){$meta = array();}
			if(!empty($meta['folderPasswordTimeTo']) && $meta['folderPasswordTimeTo'] < time() ){ //已过期处理;
				$meta = array();
			}
			if(!empty($meta['folderPasswordUser'])){
				$item['value'] = Model("User")->getInfoSimpleOuter($meta['folderPasswordUser']);
			}
			if(!empty($meta)){$meta['sourceID'] = $sourceID;}
			$_cache[$sourceID] = $meta;
		}
		
		foreach($parentLevel as $sourceID){
			if(empty($_cache[$sourceID])){continue;}
			$findHas = $_cache[$sourceID];break;
		}
		return $findHas;
	}
	
}
