<?php
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/

/**
 * 文件列表通用入口获取
 * 
 * 逻辑参数
 * listTypeSet 		// 指定列表模式; icon,list,split
 * disableSort 		// 是否禁用排序; 0,1
 */
class explorerList extends Controller{
	private $model;
	public function __construct(){
		parent::__construct();
		$this->model = Model("Source");
	}	
	public function path($thePath = false){
		$path     = $thePath ? $thePath : $this->in['path'];
		$path     = $path != '/' ? rtrim($path,'/') : '/';//路径保持统一;
		$path	  = $path == '{io:systemRecycle}' ? IO_PATH_SYSTEM_RECYCLE:$path;
		$path 	  = $this->checkDesktop($path);
		$pathParse= KodIO::parse($path);
		$id 	  = $pathParse['id'];

		$current  = $this->pathCurrent($path);
		$this->checkExist($current,$path);
		switch($pathParse['type']){
			case KodIO::KOD_USER_FAV:			$data = Action('explorer.fav')->get();break;
			case KodIO::KOD_USER_FILE_TAG:		$data = Action('explorer.tag')->listSource($id);break;
			case KodIO::KOD_USER_RECYCLE:		$data = $this->model->listUserRecycle();break;
			case KodIO::KOD_USER_FILE_TYPE:		$data = $this->model->listPathType($id);break;
			case KodIO::KOD_USER_RECENT:		$data = $this->listRecent();break;
			case KodIO::KOD_GROUP_ROOT_SELF:	$data = Action('explorer.listGroup')->groupSelf($pathParse);break;
			case KodIO::KOD_USER_SHARE:			$data = Action('explorer.userShare')->myShare('to');break;
			case KodIO::KOD_USER_SHARE_LINK:	$data = Action('explorer.userShare')->myShare('link');break;
			
			case KodIO::KOD_USER_SHARE_TO_ME:	$data = Action('explorer.userShare')->shareToMe($id);break;
			case KodIO::KOD_SHARE_ITEM:			$data = Action('explorer.userShare')->sharePathList($pathParse);break;
			case KodIO::KOD_SEARCH:				$data = Action('explorer.listSearch')->listSearch($pathParse);break;
			case KodIO::KOD_BLOCK:				$data = $this->blockChildren($id);break;
			case KodIO::KOD_SHARE_LINK:
			case KodIO::KOD_SOURCE:
			case KodIO::KOD_IO:
			default:$data = IO::listPath($path);break;
		}
		$this->parseData($data,$path,$pathParse,$current);
		$data = Hook::filter('explorer.list.path.parse',$data);

		if($thePath) return $data;
		show_json($data);
	}
	public function parseData(&$data,$path,$pathParse){
		$this->parseAuth($data,$path,$pathParse);
		$this->pageParse($data);
		$this->parseDataHidden($data,$pathParse);
		
		//回收站追加物理/io回收站;
		Action('explorer.recycleDriver')->appendList($data,$pathParse); 
		Action('explorer.listGroup')->appendChildren($data);
		
		$this->pathListParse($data);
		$this->pageReset($data);
	}	
	
	// 桌面文件夹自动检测;不存在处理;
	private function checkDesktop($path){
		if(!defined('MY_DESKTOP')) return $path;
		if(trim($path,'/') !== trim(MY_DESKTOP,'/')) return $path;
		if(IO::info($path)) return MY_DESKTOP;//存在则不处理;
		
		$desktopName = LNG('explorer.toolbar.desktop');
		$model  = Model("Source");
		$find   = IO::fileNameExist(MY_HOME,$desktopName);
		$rootID = KodIO::sourceID(MY_HOME);
		if(!$find){
			$find = $model->mkdir($rootID,$desktopName);
		}
		$model->metaSet($find,'desktop','1');
		$model->metaSet($rootID,'desktopSource',$find);
		Model('User')->cacheFunctionClear('getInfo',USER_ID);
		return KodIO::make($find);
	}
	
	/**
	 * 最近文档；
	 * 仅限自己的文档；不分页；不支持排序；  最新修改时间 or 最新修改 or 最新打开 max top 100;
	 * 
	 * 最新自己创建的文件(上传or拷贝)
	 * 最新修改的，自己创建的文件	
	 * 最新打开的自己的文件 		
	 * 
	 * 资源去重；整体按时间排序【创建or上传  修改  打开】
	 */
	private function listRecent(){
		$list = array();
		$this->listRecentWith('createTime',$list);		//最近上传or创建
		$this->listRecentWith('modifyTime',$list);		//最后修改
		$this->listRecentWith('viewTime',$list);		//最后打开
		
		//合并重复出现的类型；
		foreach ($list as &$value) {
			$value['recentType'] = 'createTime';
			$value['recentTime'] = $value['createTime'];
			if($value['modifyTime'] > $value['recentTime']){
				$value['recentType'] = 'modifyTime';
				$value['recentTime'] = $value['modifyTime'];
			}
			if($value['viewTime'] > $value['recentTime']){
				$value['recentType'] = 'viewTime';
				$value['recentTime'] = $value['viewTime'];
			}
		}
		
		$list = array_sort_by($list,'recentTime',true);
		$listRecent = array_to_keyvalue($list,'sourceID');
		$result = array();
		if(!empty($listRecent)){
			$where  = array( 'sourceID'=>array('in',array_keys($listRecent)) );
			$result = $this->model->listSource($where);
		}
		$fileList = array_to_keyvalue($result['fileList'],'sourceID');
		// pr($fileList,$listRecent);exit;
		
		//保持排序，合并数据
		foreach ($fileList as $sourceID => &$value) {
			$item  = $listRecent[$sourceID];
			if(!$item){
				unset($fileList[$sourceID]);
				continue;
			}
			$value = array_merge($value,$item);
		}
		$result['fileList'] = array_values($fileList);
		$result['disableSort'] = 1;
		$result['listTypeSet'] = 'list';
		// unset($result['pageInfo']);
		return $result;
	}
	private function listRecentWith($timeType,&$result){
		$where = array(
			'targetType'	=> SourceModel::TYPE_USER,
			'targetID'		=> USER_ID,
			'isFolder'		=> 0,
			'isDelete'		=> 0,
			'createTime'	=> array('>',time() - 3600*24*60),//2个月内;
			'size'			=> array('>',0),
		);

		$maxNum = 50;	//最多150项
		$field  = 'sourceID,name,createTime,modifyTime,viewTime';
		$list   = $this->model->field($field)->where($where)
					->limit($maxNum)->order($timeType.' desc')->select();
		$list   = array_to_keyvalue($list,'sourceID');
		$result = array_merge($result,$list);
		$result = array_to_keyvalue($result,'sourceID');
	}

	public function pageParse(&$data){
		if(isset($data['pageInfo'])) return;
		$in = $this->in;
		$pageNumMax = 5000;
		$pageNum = isset($in['pageNum'])?$in['pageNum']: $pageNumMax;
		if($pageNum === -1){ // 不限分页情况; webdav列表处理;
			unset($in['pageNum']);
			$pageNumMax = 1000000;
			$pageNum = $pageNumMax;
		}
				
		$fileCount  = count($data['fileList']);
		$folderCount= count($data['folderList']);
		$totalNum	= $fileCount + $folderCount;
		$pageNum 	= intval($pageNum);
		$pageNum	= $pageNum <= 5 ? 5 : ($pageNum >= $pageNumMax ? $pageNumMax : $pageNum);
		$pageTotal	= ceil( $totalNum / $pageNum);
		$page		= intval( isset($in['page'])?$in['page']:1);
		$page		= $page <= 1 ? 1  : ($page >= $pageTotal ? $pageTotal : $page);
		$data['pageInfo'] = array(
			'totalNum'	=> $totalNum,
			'pageNum'	=> $pageNum,
			'page'		=> $page,
			'pageTotal'	=> $pageTotal,
		);
		if($pageTotal <= 1) return;

		$sort = $this->_parseOrder();
		$isDesc = $sort['desc'] == 'desc';
		$data['fileList'] 	= array_sort_by($data['fileList'],$sort['key'],$isDesc);
		$data['folderList'] = array_sort_by($data['folderList'],$sort['key'],$isDesc);
		
		$start = ($page-1) * $pageNum;
		$end   = $start + $pageNum;
		if( $end <= $folderCount){ // 文件夹范围内;
			$data['folderList'] = array_slice($data['folderList'],$start,$pageNum);
			$data['fileList'] 	= array();
		}else if($start >= $folderCount){ // 文件范围内;
			$data['folderList'] = array();
			$data['fileList'] 	= array_slice($data['fileList'],$start-$folderCount,$pageNum);
		}else{ // 各自占一部分;
			$folderNeed  = $folderCount - $start;
			$data['folderList'] = array_slice($data['folderList'],$start,$folderNeed);
			$data['fileList'] 	= array_slice($data['fileList'],0,$pageNum-($folderNeed) );
		}
	}
	private function pageReset(&$data){
		// 合并额外current数据;
		if(isset($data['currentFieldAdd'])){
			$data['current'] = array_merge($data['current'],$data['currentFieldAdd']);
			unset($data['currentFieldAdd']);
		}
		
		if(!isset($data['pageInfo'])) return;
		$group = isset($data['groupList']) ? count($data['groupList']) : 0;
		$total = count($data['fileList']) + count($data['folderList']) + $group;
		$pageInfo = $data['pageInfo'];
		if(	$pageInfo['page'] == 1 && $pageInfo['pageTotal'] == 1){
			$data['pageInfo']['totalNum'] = $total;
		}

		// 某一页因为权限全部过滤掉内容, 则加大每页获取条数;
		if(	$total == 0 && $pageInfo['totalNum'] != 0 && $pageInfo['pageTotal'] > 1 ){
			$this->in['pageNum'] = $pageInfo['pageNum'] * 2;
			if($this->in['pageNum'] < 500){
				$this->in['pageNum'] = 500;
			}
			$newData = $this->path($this->in['path']);
			show_json($newData);
		}
	}
	
	private function _parseOrder(){
		$defaultField = Model('UserOption')->get('listSortField');
		$defaultSort  = Model('UserOption')->get('listSortOrder');
		$sortTypeArr  = array('up'=>'asc','down'=>'desc');
		$sortFieldArr = array(
			'name'			=> 'name',
			'size'			=> 'size',
			'type'			=> 'ext',
			'ext'			=> 'fileType',
			'createTime'	=> 'createTime',
			'modifyTime'	=> 'modifyTime'
		);
		$sortField    = Input::get("sortField",'in',$defaultField,array_keys($sortFieldArr));
		$sortType	  = Input::get("sortType", 'in',$defaultSort,array_keys($sortTypeArr));
		if( !in_array($sortField,array_keys($sortFieldArr)) ){
			$sortField = 'name';
		}
		if( !in_array($sortType,array_keys($sortTypeArr)) ){
			$sortField = 'up';
		}
		return array('key'=>$sortFieldArr[$sortField],'desc'=>$sortTypeArr[$sortType]);
	}
	
	/**
	 * 检查目录是否存在;
	 */
	private function checkExist($current,$path){
		if(trim($path,'/') == '{source:0}') return;
		if(!$current || $current['exists'] === false){
			show_json(LNG('common.pathNotExists'),false);
		}
		if(isset($current['isDelete']) && $current['isDelete'] == '1'){
			show_json(LNG("explorer.pathInRecycle"),false);
		}
	}

	public function pathCurrent($path,$loadInfo = true){
		$pathParse = KodIO::parse($path);
		$driver    = IO::init($path);
		if($pathParse['isTruePath']){
			$current = array('type'=>'folder','path'=>$path);
			if(!$driver) {$current['exists'] = false;}
			if($driver && $loadInfo){
				$current = IO::info($path);
				$current = Model('SourceAuth')->authOwnerApply($current);
			}
			if($driver && !$loadInfo && $driver->getType() == 'local'){
				$currentInfo = IO::info($path);
				if($currentInfo){$current=$currentInfo;}
				if(!$currentInfo){$current['exists'] = false;}
			}
			return $current;
		}

		$current = $this->ioInfo($pathParse['type']);
		if($pathParse['type'] == KodIO::KOD_BLOCK){
			$list = $this->blockItems();
			$current = $list[$pathParse['id']];
			$current['name'] = _get($current,'name','root');
			$current['icon'] = 'block-'.$pathParse['id'];
		}else if($pathParse['type'] == KodIO::KOD_USER_FILE_TYPE){
			$list = $this->blockFileType();
			$current = $list[$pathParse['id']];
			$current['name'] = LNG('common.fileType').' - '.$current['name'];
		}else if($pathParse['type'] == KodIO::KOD_USER_FILE_TAG){
			$list = Action('explorer.tag')->tagList();
			$current = $list[$pathParse['id']];
			$current['name'] = LNG('common.tag').' - '.$current['name'];
		}
		$current['type'] = 'folder';
		$current['path'] = $path;
		return $current;
	}
	
	public function pathListParse(&$data){
		$timeNow = timeFloat();
		$timeMax = 1.5;
		$infoFull= true;
		$data['current'] = $this->pathInfoParse($data['current'],$data['current']);
		foreach ($data as $type =>$list) {
			if(!in_array($type,array('fileList','folderList','groupList'))) continue;
			foreach ($list as $key=>$item){
				if(timeFloat() - $timeNow >= $timeMax){$infoFull = false;}
				$data[$type][$key] = $this->pathInfoParse($item,$data['current'],$infoFull);
			}
		}
	}
	
	public function pathInfoParse($pathInfo,$current=false,$infoFull=true){
		if(!$pathInfo) return false;
		if(defined('USER_ID')){
			$pathInfo = Action('explorer.fav')->favAppendItem($pathInfo);
			$pathInfo = Action('explorer.userShare')->shareAppendItem($pathInfo);
			$pathInfo = Action('explorer.tagGroup')->tagAppendItem($pathInfo);
		}
		if($infoFull){
			$pathInfo = Action('explorer.listDriver')->parsePathIO($pathInfo,$current);
			$pathInfo = Action('explorer.listDriver')->parsePathChildren($pathInfo,$current);
			if($pathInfo['type'] == 'file'){
				$pathInfo = $this->pathParseOexe($pathInfo);
				$pathInfo = $this->pathInfoMore($pathInfo);
			}
		}else{
			if($pathInfo['type'] == 'folder'){
				$pathInfo['hasFolder'] = true;
				$pathInfo['hasFile']   = true;
			}
		}		
		$pathInfo['pathDisplay'] = _get($pathInfo,'pathDisplay',$pathInfo['path']);

		// 下载权限处理;
		$pathParse = KodIO::parse($pathInfo['path']);
		$pathInfo['canDownload'] = $pathParse['isTruePath'];
		if(isset($pathInfo['auth'])){
			$pathInfo['canDownload'] = Model('Auth')->authCheckDownload($pathInfo['auth']['authValue']);
		}
		
		// 写入权限;
		if($pathParse['isTruePath']){
			if(isset($pathInfo['auth'])){
				$pathInfo['canWrite'] = Model('Auth')->authCheckEdit($pathInfo['auth']['authValue']);
			}
			$lockUser = _get($pathInfo,'metaInfo.systemLock');
			if($lockUser && $lockUser != USER_ID){
				$pathInfo['isWriteable'] = false;
			}
		}
		if($pathInfo['type'] == 'file' && !$pathInfo['ext']){
			$pathInfo['ext'] = strtolower($pathInfo['name']);
		}
		
		// 没有下载权限,不显示fileInfo信息;
		if(!$pathInfo['canDownload']){
			unset($pathInfo['fileInfo']);
			unset($pathInfo['hashMd5']);
		}
		if(isset($pathInfo['fileID'])){
			unset($pathInfo['fileID']);
		}
		if(isset($pathInfo['fileInfo']['path'])){
			unset($pathInfo['fileInfo']['path']);
		}
		$pathInfo = Hook::filter('explorer.list.itemParse',$pathInfo);
		// $this->pathDesc($pathInfo,$pathParse);
		return $pathInfo;
	}

	/**
	 * 递归处理数据；自动加入打开等信息
	 * 如果是纯数组: 处理成 {folderList:[],fileList:[],thisPath:xxx,current:''}
	 */
	private function parseAuth(&$data,$path,$pathParse){
		if( !isset($data['folderList']) || 
			!is_array($data['folderList'])
		) { //处理成统一格式
			$listTemp = isset($data['fileList']) ? $data['fileList'] : $data;
			$data = array(
				"folderList" 	=> $listTemp ? $listTemp : array(),
				'fileList'		=> array()
			);
		}
		$path = rtrim($path,'/').'/';
		$data['current']  = $this->pathCurrent($path);
		$data['thisPath'] = $path;
		$data['targetSpace'] = $this->targetSpace($data['current']);
		foreach ($data['folderList'] as &$item) {
			if( isset($item['children']) ){
				$item['isParent'] = true;
				$pathParseParent = KodIO::parse($item['path']);
				$this->parseAuth($item['children'],$item['path'],$pathParseParent);
			}
			$item['type'] = isset($item['type']) ? $item['type'] : 'folder';
		}
		if($pathParse['type'] == KodIO::KOD_SHARE_LINK) return;

		$data['fileList']   = $this->dataFilterAuth($data['fileList']);
		$data['folderList'] = $this->dataFilterAuth($data['folderList']);
	}
	
	// 显示隐藏文件处理; 默认不显示隐藏文件;
	private function parseDataHidden(&$data,$pathParse){
		if(defined('USER_ID') && Model('UserOption')->get('displayHideFile') == '1') return;
		$pathHidden = Model('SystemOption')->get('pathHidden');
		$pathHidden = explode(',',$pathHidden);
		$hideNumber = 0;

		if($pathParse['type'] == KodIO::KOD_USER_SHARE_TO_ME) return;
		foreach ($data as $type =>$list) {
			if(!in_array($type,array('fileList','folderList'))) continue;
			$result = array();
			foreach ($list as $item){
				if(substr($item['name'],0,1) == '.') continue;
				if(in_array($item['name'],$pathHidden)) continue;
				$result[] = $item;
			}
			$data[$type] = $result;
			$hideNumber  += count($list) - count($result);
		}
		// 总文件数; 只减去当前页;暂不处理多页情况;
		// if(is_array($data['pageInfo']) && $hideNumber > 0){
		// 	$data['pageInfo']['totalNum'] -= $hideNumber;
		// }
	}

	
	// 用户或部门空间尺寸;
	public function targetSpace($current){
		if(!_get($current,'targetID')) return false;
		if(	isset($current['auth']) &&
			$current['auth']['authValue'] == -1 ){
			return false;
		}
		if(!$current || !isset($current['targetType'])){
			$current = array("targetType"=>'user','targetID'=>USER_ID);//用户空间;
		}
		return Action('explorer.auth')->space($current['targetType'],$current['targetID']);
	}
	
	private function dataFilterAuth($list){
		if(_get($GLOBALS,'isRoot') && $this->config["ADMIN_ALLOW_SOURCE"]) return $list;
		foreach ($list as $key => $item) {
			if( isset($item['targetType']) &&
				$item['targetType'] == 'user' &&
				$item['targetID'] == USER_ID ){
				continue;
			}
			// if(!isset($item['auth'])) continue;
			if( isset($item['targetType']) && 
				(!$item['auth'] || $item['auth']['authValue'] == 0 ) // 不包含-1,构建通路;
			){
				unset($list[$key]);
			}
		}
		return array_values($list);
	}

	// 文件详细信息处理;
	public function pathInfoMore($pathInfo){
		//return $pathInfo;
		$infoKey  = 'fileInfoMore';
		$cacheKey = 'fileInfo.'.md5($pathInfo['path'].'@'.$pathInfo['size'].$pathInfo['modifyTime']);

		// 没有图片尺寸情况,再次计算获取;[更新]
		$isImage  = in_array($pathInfo['ext'],array('jpg','jpeg','png','ico','bmp'));
		if($isImage && !isset($pathInfo[$infoKey]['sizeWidth'])){
			unset($pathInfo[$infoKey]);Cache::remove($cacheKey);//不使用缓存;
		}
				
		if(isset($pathInfo[$infoKey])){
		}else if(isset($pathInfo['sourceID'])){
			$fileID = _get($pathInfo,'fileInfo.fileID');
			GetInfo::infoAdd($pathInfo);
			if($fileID && is_array(_get($pathInfo,$infoKey) )){
				$value = json_encode($pathInfo[$infoKey]);
				Model("File")->metaSet($fileID,$infoKey,$value);
			}
		}else{ // 本地存储, io存储;
			$infoMore = Cache::get($cacheKey);
			if(is_array($infoMore)){
				$pathInfo[$infoKey] = $infoMore;
			}else{
				GetInfo::infoAdd($pathInfo);
				$infoAdd = is_array($pathInfo[$infoKey]) ? $pathInfo[$infoKey]:array();
				Cache::set($cacheKey,$infoAdd,3600*24*20);
			}
		}
		// 文件封面;
		if(isset($pathInfo[$infoKey]) && isset($pathInfo[$infoKey]['fileThumb']) ){
			$fileThumb = $pathInfo[$infoKey]['fileThumb'];
			unset($pathInfo[$infoKey]['fileThumb']);
			$pathInfo['fileThumb'] = Action('explorer.share')->linkFile($fileThumb);
		}
		return $pathInfo;
	}
	
	/**
	 * 追加应用内容信息;
	 */
	private function pathParseOexe($pathInfo){
		$maxSize = 1024*1024*1;
		if($pathInfo['ext'] != 'oexe' || $pathInfo['size'] > $maxSize) return $pathInfo;

		$content = IO::getContent($pathInfo['path']);
		$pathInfo['oexeContent'] = json_decode($content,true);
		if( $pathInfo['oexeContent']['type'] == 'path' && 
			isset($pathInfo['oexeContent']['value']) ){
			$linkPath = $pathInfo['oexeContent']['value'];
			$parse = KodIO::parse($pathInfo['path']);
			if($parse['type'] == KodIO::KOD_SHARE_LINK) return $pathInfo;
			
			if(Action('explorer.auth')->fileCan($linkPath,'show')){
				if(substr($linkPath,0,4) == '{io:'){ //io路径不处理;
					$infoTarget = array('path'=>$linkPath);
					$infoTarget = Action('explorer.listDriver')->parsePathIO($infoTarget);
				}else{
					$infoTarget = IO::info($linkPath);
				}
				$pathInfo['oexeSourceInfo'] = $infoTarget;
			}
		}
		return $pathInfo;
	}
	/**
	 * 根数据块
	 */
	private function blockRoot(){
		$list = $this->blockItems();
		if(!$this->pathEnable('fileType')){unset($list['fileType']);}
		if(!_get($GLOBALS,'isRoot') || !$this->pathEnable('driver')){unset($list['driver']);}
		if(!$this->pathEnable('fileTag')){unset($list['fileTag']);}
		$result = array();
		foreach ($list as $type => $item) {
			$block = array_merge($item,array(
				"path"		=> '{block:'.$type.'}/',
				"icon"		=> 'block-'.$type,
				"isParent"	=> true,
				"children"	=> $this->blockChildren($type),
			));
			if($block['children'] === false) continue;
			$result[] = $block;
		}
		return $result;
	}
	private function blockItems(){
		$list = array(
			'files'		=>	array('name'=>LNG('common.position'),'open'=>true),
			'tools'		=>	array('name'=>LNG('common.tools'),'open'=>true,'children'=>true),
			'fileType'	=>	array('name'=>LNG('common.fileType'),'open'=>false,'children'=>true,'pathDesc'=> LNG('explorer.pathDesc.fileType')),
			'fileTag'	=>	array('name'=>LNG('common.tag'),'open'=>false,'children'=>true,'pathDesc'=> LNG('explorer.pathDesc.tag')),
			'driver'	=>	array('name'=>LNG('common.mount').' (admin)','open'=>false,'pathDesc'=> LNG('explorer.pathDesc.mount')),
		);
		return $list;
	}
	
	/**
	 * 数据块数据获取
	 */
	private function blockChildren($type){
		$result = array();
		switch($type){
			case 'root':		$result = $this->blockRoot();break; //根
			case 'files': 		$result = $this->blockFiles();break;
			case 'tools': 		$result = $this->blockTools();break;
			case 'fileType': 	$result = $this->blockFileType();break;
			case 'fileTag': 	$result = Action('explorer.tag')->tagList();break;
			case 'driver': 		$result = Action("explorer.listDriver")->get();break;
		}
		return array_values($result);
	}
	
	private function groupRoot(){
		$groupArray = Action('filter.userGroup')->userGroupRoot();
	    if (empty($groupArray[0]) || count($groupArray) != 1) return false;
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
		
		if(!$this->pathEnable('myFav')){unset($list['fav']);}
		if(!$this->pathEnable('my')){unset($list['my']);}
		if(!$this->pathEnable('rootGroup') || !$groupInfo){unset($list['rootGroup']);}
		if(!$this->pathEnable('myGroup')){unset($list['myGroup']);}
		
		// 没有所在部门时不显示;
		if(isset($list['myGroup'])){
			$selfGroup 	= Session::get("kodUser.groupInfo");
			$groupArray = array_to_keyvalue($selfGroup,'','groupID');//自己所在的组
			$group 		= array_remove_value($groupArray,$groupInfo['groupID']);
			if(!$group){unset($list['myGroup']);}
		}

		$result = array();
		foreach ($list as $pathItem){
			$item = $this->pathCurrent($pathItem['path']);
			if(!$item) continue;			
			$item['isParent'] = true;
			if($item['open']){ //首次打开：默认展开的路径，自动加载字内容
				$item['children'] = $this->path($item['path']);
			}			
			$result[] = array_merge($item,$pathItem);
		}
		return $result;
	}
	
	private function pathEnable($type){
		$model  = Model('SystemOption');
		$option = $model->get();
		if( !isset($option['treeOpen']) ) return true;
		
		// 单独添加driver情况;更新后处理;  单独加入文件类型开关,则根据flag标记;自动处理;
		// my,myFav,myGroup,rootGroup,recentDoc,fileType,fileTag,driver
		$checkType = array(
			'treeOpenMy' 		=> 'my',
			'treeOpenMyGroup' 	=> 'myGroup',
			'treeOpenFileType' 	=> 'fileType',
			'treeOpenFileTag' 	=> 'fileTag',
			'treeOpenRecentDoc' => 'recentDoc',
			
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
	 * 文件类型列表
	 */
	private function blockFileType(){
		$docType = KodIO::fileTypeList();
		$list	 = array();
		foreach ($docType as $key => $value) {
			$list[$key] = array(
				"name"		=> $value['name'],
				"path"		=> KodIO::makeFileTypePath($key),
				'ext'		=> $value['ext'],
				'extType'	=> $key,
				'icon' 		=> 'userFileType-'.$key,
			);
		}
		return $list;
	}
	
	/**
	 * 工具
	 */
	private function blockTools(){
		$list = $this->ioInfo(array(
			KodIO::KOD_USER_RECENT,
			KodIO::KOD_USER_SHARE,
			KodIO::KOD_USER_SHARE_LINK,
			KodIO::KOD_USER_RECYCLE,
		));
		if(!$this->pathEnable('recentDoc')){
			unset($list[KodIO::KOD_USER_RECENT]);
		}
		if(Model('UserOption')->get('recycleOpen') == '0'){
			unset($list[KodIO::KOD_USER_RECYCLE]);
		}
		return array_values($list);
	}

	private function ioInfo($pick){
		$list = array(
			KodIO::KOD_USER_FAV			=> array('name'=>LNG('explorer.toolbar.fav'),'pathDesc'=>LNG('explorer.pathDesc.fav')),
			KodIO::KOD_GROUP_ROOT_SELF	=> array('name'=>LNG('explorer.toolbar.myGroup'),'pathDesc'=>LNG('explorer.pathDesc.myGroup')),
			KodIO::KOD_USER_RECENT		=> array('name'=>LNG('explorer.toolbar.recentDoc'),'pathDesc'=>LNG('explorer.pathDesc.recentDoc')),
			KodIO::KOD_USER_SHARE		=> array('name'=>LNG('explorer.toolbar.shareTo'),'pathDesc'=>LNG('explorer.pathDesc.shareTo')),
			KodIO::KOD_USER_SHARE_LINK	=> array('name'=>LNG('explorer.toolbar.shareLink'),'pathDesc'=>LNG('explorer.pathDesc.shareLink')),
			KodIO::KOD_USER_SHARE_TO_ME	=> array('name'=>LNG('explorer.toolbar.shareToMe')),
			KodIO::KOD_USER_RECYCLE		=> array('name'=>LNG('explorer.toolbar.recycle'),'pathDesc'=>LNG('explorer.pathDesc.recycle')),
			KodIO::KOD_SEARCH			=> array('name'=>LNG('common.search')),
		);
		$result = array();
		foreach ($list as $key => $item){
			$result[$key] = array(
				"name"		=> $item['name'], 
				"path"		=> $key.'/',
				"icon"		=> trim(trim($key,'{'),'}'),
				"pathDesc"	=> $item['pathDesc'],
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
