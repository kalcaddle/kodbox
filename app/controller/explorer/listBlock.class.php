<?php
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/

/**
 * 文件列表通用入口获取
 */
class explorerListBlock extends Controller{
	private $model;
	public function __construct(){
		parent::__construct();
		$this->model = Model("Source");
	}
	
	/**
	 * 数据块数据获取
	 */
	public function blockChildren($type){
		$result = array();
		switch($type){
			case 'root':		$result = $this->blockRoot();break; //根
			case 'files': 		$result = $this->blockFiles();break;
			case 'tools': 		$result = $this->blockTools();break;
			case 'safe': 		$result = Action('explorer.listSafe')->listRoot();break;
			case 'fileType': 	$result = Action('explorer.listFileType')->block();break;
			case 'fileTag': 	$result = Action('explorer.tag')->tagList();break;
			case 'driver': 		$result = Action("explorer.listDriver")->get();break;
		}
		if(!is_array($result)) return array();
		if(isset($result['folderList'])) return $result;
		return array_values($result);
	}
	
	/**
	 * 根数据块
	 */
	private function blockRoot(){
		$list = $this->blockItems();
		if(!$this->pathEnable('fileType')){unset($list['fileType']);}
		if(!KodUser::isRoot() || !$this->pathEnable('driver')){unset($list['driver']);}
		if(!$this->pathEnable('fileTag')){unset($list['fileTag']);}
		$result = array();
		foreach ($list as $type => $item) {
			$block = array_merge($item,array(
				"path"		=> '{block:'.$type.'}/',
				"isParent"	=> true,
			));
			if($block['open'] || $block['children']){
				$block['children'] = $this->blockChildren($type);
				if($block['children'] === false) continue;
			} // 必须有children,没有children的去除(兼容Android <= 2.15)
			$result[] = $block;
		}
		return $result;
	}
	public function blockItems(){
		$list = array(
			'files'		=> array('name'=>LNG('common.position'),'open'=>true), 
			'tools'		=> array('name'=>LNG('common.tools'),'open'=>true),
			'fileType'	=> array('name'=>LNG('common.fileType'),'open'=>false,'children'=>true,'pathDesc'=> LNG('explorer.pathDesc.fileType')),
			'fileTag'	=> array('name'=>LNG('explorer.userTag.title'),'open'=>false,'children'=>true,'pathDesc'=> LNG('explorer.pathDesc.tag')),
			'driver'	=> array('name'=>LNG('common.mount').' (admin)','open'=>false,'pathDesc'=> LNG('explorer.pathDesc.mount')),
		);
		return $list;
	}
	
	private function groupRoot(){
		$groupArray = Action('filter.userGroup')->userGroupRootShow();
	    if (!$groupArray || empty($groupArray[0])) return false;
	    return Model('Group')->getInfo($groupArray[0]);
	}
	
	/**
	 * 文件位置
	 * 收藏夹、我的网盘、公共网盘、我所在的部门
	 */
	private function blockFiles(){
		$groupInfo 	= $this->groupRoot();
		$list = array(
			"fav"		=> array("path"	=> KodIO::KOD_USER_FAV,'pathDesc'=>LNG('explorer.pathDesc.fav')),
			"my"		=> array(
				'name' 			=> LNG('explorer.toolbar.rootPath'),//我的网盘
				'sourceRoot' 	=> 'userSelf',//文档根目录标记；前端icon识别时用：用户，部门
				"path"			=> KodIO::make(Session::get('kodUser.sourceInfo.sourceID')),
				'open'			=> true,
				'pathDesc'		=> LNG('explorer.pathDesc.home')
			),
			"rootGroup"	=> array(
				'name' 			=> $groupInfo['name'],
				'sourceRoot' 	=> 'groupPublic',
				"path"			=> KodIO::make($groupInfo['sourceInfo']['sourceID']),
				'pathDesc'		=> LNG('explorer.pathDesc.groupRoot')
			),
			"myGroup"	=> array("path"	=> KodIO::KOD_GROUP_ROOT_SELF,'pathDesc'=>LNG('explorer.pathDesc.myGroup')),
			'shareToMe'	=> array("path"	=> KodIO::KOD_USER_SHARE_TO_ME),
		);
		$option = Model('SystemOption')->get();
		if(!$this->pathEnable('myFav')){unset($list['fav']);}
		if(!$this->pathEnable('my')){unset($list['my']);}
		if(!$this->pathEnable('rootGroup') || !$groupInfo || !$groupInfo['sourceInfo']){unset($list['rootGroup']);}
		if(!$this->pathEnable('myGroup')){unset($list['myGroup']);}
		if($option['groupSpaceLimit'] == '1' && $option['groupSpaceLimitLevel'] <= 1){
			unset($list['myGroup']);
		}
		
		
		// 根部门没有权限,且没有子内容时不显示;
		if(isset($list['rootGroup'])){
			$rootChildren = Action('explorer.list')->path($list['rootGroup']['path']);
			$hasAuth = _get($rootChildren,'current.auth.authValue');
			if(!$rootChildren['pageInfo']['totalNum'] && $hasAuth <= 0){
				unset($list['rootGroup']);
			}
		}
		
		// 没有所在部门时不显示;
		if(isset($list['myGroup'])){
			$selfGroup 	= Session::get("kodUser.groupInfo");
			$groupArray = array_to_keyvalue($selfGroup,'','groupID');//自己所在的组
			$group 		= array_remove_value($groupArray,$groupInfo['groupID']);
			if(!$group && !isset($list['rootGroup'])){unset($list['myGroup']);}
			if(!$selfGroup){unset($list['myGroup']);}
		}

		$explorer = Action('explorer.list');
		$result = array();
		foreach ($list as $pathItem){
			$item = $explorer->pathCurrent($pathItem['path']);
			if(!$item) continue;			
			$item['isParent'] = true;
			if($item['open']){ //首次打开：默认展开的路径，自动加载字内容
				$item['children'] = $explorer->path($item['path']);
			}			
			$result[] = array_merge($item,$pathItem);
		}
		return $result;
	}
	
	public function pathEnable($type){
		$model  = Model('SystemOption');
		$option = $model->get();
		if( !isset($option['treeOpen']) ) return true;
		
		// 单独添加driver情况;更新后处理;  单独加入文件类型开关,则根据flag标记;自动处理;
		// my,myFav,myGroup,rootGroup,recentDoc,fileType,fileTag,userPhoto,driver
		$checkType = array(
			'treeOpenMy' 		=> 'my',
			'treeOpenMyGroup' 	=> 'myGroup',
			'treeOpenFileType' 	=> 'fileType',
			'treeOpenFileTag' 	=> 'fileTag',
			'treeOpenRecentDoc' => 'recentDoc',
			
			'treeOpenPhoto' 	=> 'userPhoto',
			'treeOpenDriver' 	=> 'driver',
			'treeOpenFav'		=> 'myFav',
			'treeOpenRootGroup'	=> 'rootGroup',
		);
		foreach ($checkType as $keyType=>$key){
			if(isset($GLOBALS['TREE_OPTION_IGNORE']) && $GLOBALS['TREE_OPTION_IGNORE'] == '1') break;
			if( $option[$keyType] !='ok'){
				$model->set($keyType,'ok');
				$model->set('treeOpen',$option['treeOpen'].','.$key);
				$result = true;
				$option = $model->get();
			}
		}
		if($result) return true;
		
		$allow = explode(',',$option['treeOpen']);
		return in_array($type,$allow);
	}
		
	/**
	 * 工具
	 */
	private function blockTools(){
		$list = $this->ioInfo(array(
			KodIO::KOD_USER_RECENT,
			KodIO::KOD_USER_SHARE,
			KodIO::KOD_USER_SHARE_LINK,'userPhoto',
			KodIO::KOD_USER_RECYCLE,
		));
		if(!$this->pathEnable('recentDoc')){
			unset($list[KodIO::KOD_USER_RECENT]);
		}
		if(!$this->pathEnable('userPhoto')){
			unset($list['userPhoto']);
		}
		if(Model('UserOption')->get('recycleOpen') == '0'){
			unset($list[KodIO::KOD_USER_RECYCLE]);
		}
		if(Model('SystemOption')->get('shareLinkAllow') == '0'){
			unset($list[KodIO::KOD_USER_SHARE_LINK]);
		}
		if(!is_array($list)) return array();
		return array_values($list);
	}

	public function ioInfo($pick){
		$list = array(
			array(KodIO::KOD_USER_FAV,'explorer.toolbar.fav','explorer.pathDesc.fav'),
			array(KodIO::KOD_GROUP_ROOT_SELF,'explorer.toolbar.myGroup','explorer.pathDesc.myGroup'),
			array(KodIO::KOD_USER_RECENT,'explorer.toolbar.recentDoc','explorer.pathDesc.recentDoc'),
			array(KodIO::KOD_USER_SHARE,'explorer.toolbar.shareTo','explorer.pathDesc.shareTo'),
			array(KodIO::KOD_USER_SHARE_LINK,'explorer.toolbar.shareLink','explorer.pathDesc.shareLink'),
			array(KodIO::KOD_USER_SHARE_TO_ME,'explorer.toolbar.shareToMe',''),
			array(KodIO::KOD_USER_RECYCLE,'explorer.toolbar.recycle','explorer.pathDesc.recycle'),
			array(KodIO::KOD_SEARCH,'common.search',''),
			array('userPhoto',LNG('explorer.toolbar.photo'),LNG('explorer.photo.desc'),'{userFileType:photo}/'),
		);
		$result = array();
		foreach ($list as $item){
			$thePath = isset($item[3]) ? $item[3]:$item[0];
			$result[$item[0]] = array(
				"name"		=> LNG($item[1]),
				"path"		=> $thePath.'/',
				"pathDesc"	=> $item[2] ? LNG($item[2]) : '',
			);
		}
		if(is_string($pick)){
			return $result[$pick];
		}else if(is_array($pick)){
			$pickArr = array();
			foreach ($pick as $value) {
				$pickArr[$value] = $result[$value];
			}
			return $pickArr;
		}		
		return $result;	
	}
}
