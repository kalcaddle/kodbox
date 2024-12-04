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
		if(!$this->checkAuthNeed($pathInfo)){return;} // 拥有编辑以上权限则忽略;
		$passInfo = $this->checkAllowPassword($pathInfo);
		if($passInfo){// 当前文件,或上层需要密码;
			if($action == 'show'){return;}
			return LNG('explorer.folderPass.tips');
		}
		
		// 查询子目录是否包含需要设置密码的文件夹;
		if($pathInfo['type'] == 'folder' && $action == 'download'){
			$where = array(
				"source.parentLevel"=> array("like",$pathInfo['parentLevel'].$pathInfo['sourceID'].',%'),
				"source.isDelete"	=> 0,
				"meta.key"			=> 'folderPassword',
			);
			$field = "source.sourceID,meta.value";
			$join  = "LEFT JOIN io_source_meta meta on source.sourceID = meta.sourceID";
			$list  = $this->model->alias("source")->field($field)->where($where)->join($join)->select();
			$sourceMeta = $this->folderPasswordMeta(array_to_keyvalue($list,'','sourceID'));
			$needPassword = array();
			foreach($sourceMeta as $sourceID => $passInfo){
				$sessionKey = "folderPassword_".$passInfo['sourceID'];
				if( $passInfo && Session::get($sessionKey) != $passInfo['folderPassword']){
					$needPassword[] = $passInfo;
				}
			}
			// trace_log([$passInfo,$pathInfo,$action,$list,$needPassword]);
			$msgCount = ' ['.count($needPassword).' '.LNG('common.items').']';
			if($needPassword){return LNG('explorer.folderPass.tipsHas').$msgCount;}
		}
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
	
	private function checkAuthNeed($pathInfo){
		if(!$pathInfo || empty($pathInfo['sourceID']) || empty($pathInfo['parentLevel'])){return false;}
		$canEdit  = is_array($pathInfo['auth']) ? Model("Auth")->authCheckEdit($pathInfo['auth']['authValue']):false;
		$userSelf = $pathInfo['targetType'] == 'user' && $pathInfo['targetID'] == KodUser::id();
		if($userSelf || $canEdit || KodUser::isRoot()){return false;}
		return true;
	}
	private function checkAllowPassword($pathInfo){
		if(!$this->checkAuthNeed($pathInfo)){return false;}
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
		
		$sourceMeta = $this->folderPasswordMeta($parentLevel);
		foreach ($sourceMeta as $sourceID => $meta) {
			$_cache[$sourceID] = $meta;
		}
		foreach($parentLevel as $sourceID){
			if(empty($_cache[$sourceID])){continue;}
			$findHas = $_cache[$sourceID];break;
		}
		return $findHas;
	}
	
	private function folderPasswordMeta($sourceArr){
		$sourceMeta = array();
		if(!$sourceArr){return $sourceMeta;}
		$keys 	 = array('folderPassword','folderPasswordDesc','folderPasswordTimeTo','folderPasswordUser');
		$where   = array('sourceID'=>array('in',$sourceArr),'key'=>array('in',$keys));
		$metaArr = Model('io_source_meta')->where($where)->select();
		$metaArr = array_to_keyvalue_group($metaArr,'sourceID');
		foreach($metaArr as $sourceID=>$theMeta){
			$meta = array_to_keyvalue($theMeta,'key','value');
			if(empty($meta['folderPassword'])){$meta = array();}
			if(!empty($meta['folderPasswordTimeTo']) && $meta['folderPasswordTimeTo'] < time() ){ //已过期处理;
				$meta = array();
			}
			if(!empty($meta['folderPasswordUser'])){
				$item['value'] = Model("User")->getInfoSimpleOuter($meta['folderPasswordUser']);
			}
			if(!empty($meta)){$meta['sourceID'] = $sourceID;}
			$sourceMeta[$sourceID] = $meta;
		}
		return $sourceMeta;
	}
	
}
