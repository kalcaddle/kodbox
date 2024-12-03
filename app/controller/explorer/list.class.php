<?php
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/

/**
 * 文件列表通用入口获取
 * 基本数据: current/folderList/fileList/pageInfo;
 * 
 * 其他参数
 * listTypeSet 		// 指定列表模式; icon,list,split
 * listTypePhoto	// 强制显示图片模式
 * disableSort 		// 是否禁用排序; 0,1
 * pageSizeArray	// 自定义分页数量选择;
 * folderTips		// 目录警告提示信息;
 * groupShow 		// 分组依据;
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
			case KodIO::KOD_USER_FILE_TYPE:		$data = Action('explorer.listFileType')->get($id,$pathParse);break;
			case KodIO::KOD_USER_RECENT:		$data = Action('explorer.listRecent')->listData();break;
			case KodIO::KOD_GROUP_ROOT_SELF:	$data = Action('explorer.listGroup')->groupSelf($pathParse);break;
			case KodIO::KOD_USER_SHARE:			$data = Action('explorer.userShare')->myShare('to');break;
			case KodIO::KOD_USER_SHARE_LINK:	$data = Action('explorer.userShare')->myShare('link');break;
			
			case KodIO::KOD_USER_SHARE_TO_ME:	$data = Action('explorer.userShare')->shareToMe($id);break;
			case KodIO::KOD_SHARE_ITEM:			$data = Action('explorer.userShare')->sharePathList($pathParse);break;
			case KodIO::KOD_SEARCH:				$data = Action('explorer.listSearch')->listSearch($pathParse);break;
			case KodIO::KOD_BLOCK:				$data = Action('explorer.listBlock')->blockChildren($id);break;
			case KodIO::KOD_SHARE_LINK:
			case KodIO::KOD_SOURCE:
			case KodIO::KOD_IO:
			default:$data = IO::listPath($path);break;
		}
		$this->parseData($data,$path,$pathParse,$current);
		Action('explorer.listView')->listDataSet($data);

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
		Action('explorer.listSafe')->appendSafe($data);
		Action('explorer.listPassword')->appendSafe($data);
		
		$this->pathListParse($data);// 1000 => 50ms; all-image=200ms;
		$this->pageReset($data);
		$this->addHistoryCount($data,$pathParse);
	}
	
	// 拉取所有(用于文件夹同步,对比等情况)
	// IO::listAll($this->in['path']);// path:默认为真实路径;包含sourceInfo时sourceInfo.path为真实路径;
	public function listAll(){
		$page		= isset($this->in['page']) ? intval($this->in['page']):1;
		$pageNum 	= isset($this->in['pageNum']) ? intval($this->in['pageNum']):2000;
		$page = $page <= 1 ? 1:$page;$pageNum = $pageNum <= 1000 ? 1000:$pageNum;		

		$list = IO::listAllSimple($this->in['path'],0);// path:包含上层文件夹名的路径;filePath:真实路径;
		$data = array_page_split($list,$page,$pageNum);
		show_json($data);
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
	
	public function pageParse(&$data){
		if(isset($data['pageInfo'])) return;
		$in = $this->in;
		$pageNumMax = 50000;
		$pageNum = isset($in['pageNum']) ? $in['pageNum'] : $pageNumMax;
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
		$data['fileList'] 	= KodSort::arraySort($data['fileList'],$sort['key'],$isDesc,'name');
		$data['folderList'] = KodSort::arraySort($data['folderList'],$sort['key'],$isDesc,'name');
		
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
		if(is_array($data['currentFieldAdd'])){
			$data['current'] = is_array($data['current']) ? $data['current'] : array();
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
	
	// 文件历史版本数量追加;
	private function addHistoryCount(&$data,$pathParse){
		if($pathParse['type'] == KodIO::KOD_SHARE_LINK) return;
		$sourceArr = array();$pathArr = array();
		foreach ($data['fileList'] as $file){
			if($file['sourceID']){
				// 修改时间小于等于创建时间时无历史版本; 忽略判断, 上传文件保留最后修改时间;
				// if($file['modifyTime'] <= $file['createTime']) continue; 
				$sourceArr[]  = $file['sourceID'];
			}
			if(!$file['sourceID'] && $file['isWriteable']){$pathArr[] = $file['path'];}
		}

		$countSource = $sourceArr ? Model("SourceHistory")->historyCount($sourceArr):array();
		$countLocal  = $pathArr   ? IOHistory::historyCount($pathArr):array();
		// if(!$countSource && !$countLocal) return;
		foreach ($data['fileList'] as $key=>$file){
			$data['fileList'][$key]['historyCount'] = 0; //默认设置为0
			if($file['sourceID'] && $countSource[$file['sourceID']]){
				$data['fileList'][$key]['historyCount'] = intval($countSource[$file['sourceID']]);
			}
			if($countLocal[$file['path']]){
				$data['fileList'][$key]['historyCount'] = intval($countLocal[$file['path']]);
			}
		}
	}
	public function fileInfoAddHistory($pathInfo){
		if(!$pathInfo || $pathInfo['type'] != 'file') return;
		
		$pathParse = KodIO::parse($pathInfo['path']);
		$data = array('fileList'=>array($pathInfo));
		$this->addHistoryCount($data,$pathParse);
		return $data['fileList'][0];
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
		$fromDav = _get($GLOBALS,'requestFrom') == 'webdav';
		if(isset($current['isDelete']) && $current['isDelete'] == '1' && !$fromDav){
			show_json(LNG("explorer.pathInRecycle"),false);
		}
	}

	public function pathCurrent($path,$loadInfo = true){
		$pathParse = KodIO::parse($path);
		try{// 收藏或访问的io路径不存在情况报错优化;
			$driver = IO::init($path); 
		}catch(Exception $e){
			$current = array('type'=>'folder','path'=>$path,'exists'=>false);
			return $current;
		}
		
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

		$listBlock = Action('explorer.listBlock');
		$current = $listBlock->ioInfo($pathParse['type']);
		if($pathParse['type'] == KodIO::KOD_BLOCK){
			$list = $listBlock->blockItems();
			$current = $list[$pathParse['id']];
			$current['name'] = _get($current,'name','root');
			$current['icon'] = 'block-'.$pathParse['id'];
		}else if($pathParse['type'] == KodIO::KOD_USER_FILE_TYPE){
			$list = Action('explorer.listFileType')->block();
			$current = $list[$pathParse['id']];
			$current['name'] = LNG('common.fileType').' - '.$current['name'];
		}else if($pathParse['type'] == KodIO::KOD_USER_FILE_TAG){
			$list = Action('explorer.tag')->tagList();
			$current = $list[$pathParse['id']];
			$current['name'] = LNG('explorer.userTag.title').' - '.$current['name'];
		}
		$current['type'] = 'folder';
		$current['path'] = $path;
		return $current;
	}
	
	public function pathListParse(&$data){
		$timeNow = timeFloat();
		$timeMax = 2.5;
		$infoFull= true;
		$data['current'] = $this->pathInfoParse($data['current'],$data['current']);
		foreach ($data as $type =>&$list) {
			if(!in_array($type,array('fileList','folderList','groupList'))) continue;
			foreach ($list as $key=>&$item){
				if(timeFloat() - $timeNow >= $timeMax){$infoFull = false;}
				$data[$type][$key] = $this->pathInfoParse($item,$data['current'],$infoFull);
			};unset($item);
		};unset($list);
		$data = Hook::filter('explorer.list.path.parse',$data);
	}
	
	public function pathInfoParse($pathInfo,$current=false,$infoFull=true){
		if(!$pathInfo) return false;
		static $showMd5 		= false; // 大量文件夹文件内容时,频繁调用性能优化;
		static $explorerFav 	= false;
		static $explorerTag 	= false;
		static $explorerShare 	= false;
		static $explorerTagGroup= false;
		static $explorerDriver 	= false;
		static $modelAuth 		= false;
		if(!$explorerFav){
			$explorerFav 		= Action('explorer.fav');
			$explorerTag 		= Action('explorer.tag');
			$explorerShare 		= Action('explorer.userShare');
			$explorerTagGroup 	= Action('explorer.tagGroup');
			$explorerDriver 	= Action('explorer.listDriver');
			$explorerDriver 	= Action('explorer.listDriver');
			$modelAuth 			= Model('Auth');
			$showMd5 			= Model('SystemOption')->get('showFileMd5') != '0';
		}

		if(USER_ID){
			$explorerFav->favAppendItem($pathInfo);
			$explorerTag->tagAppendItem($pathInfo);
			$explorerShare->shareAppendItem($pathInfo);
			$explorerTagGroup->tagAppendItem($pathInfo);
		}
		if($infoFull){
			if( substr($pathInfo['path'],0,4) == '{io:'){
				$explorerDriver->parsePathIO($pathInfo,$current);
			}
			if($pathInfo['type'] == 'folder' && !isset($pathInfo['hasFolder']) ){
				$explorerDriver->parsePathChildren($pathInfo,$current);
			}
			if($pathInfo['type'] == 'file' && !$pathInfo['_infoSimple']){
				$this->pathParseOexe($pathInfo);
				$this->pathInfoMore($pathInfo);
			}
		}else{
			if($pathInfo['type'] == 'folder'){
				$pathInfo['hasFolder'] = true;
				$pathInfo['hasFile']   = true;
			}
		}
		if(!$pathInfo['pathDisplay']){$pathInfo['pathDisplay'] = $pathInfo['path'];}

		// 下载权限处理;
		if(!array_key_exists('isTruePath',$pathInfo)){
			$pathInfo['isTruePath'] = KodIO::isTruePath($pathInfo['path']);
		}
		$pathInfo['canDownload'] = $pathInfo['isTruePath'];
		if(isset($pathInfo['auth'])){
			$pathInfo['canDownload'] = $modelAuth->authCheckDownload($pathInfo['auth']['authValue']);
		}

		// 写入权限;
		if($pathInfo['isTruePath']){
			if(isset($pathInfo['auth'])){
				$pathInfo['canWrite'] = $modelAuth->authCheckEdit($pathInfo['auth']['authValue']);
			}
			if(is_array($pathInfo['metaInfo']) && 
				isset($pathInfo['metaInfo']['systemLock']) && 
				$pathInfo['metaInfo']['systemLock'] != USER_ID ){
				$pathInfo['isWriteable'] = false;
			}
		}
		if($pathInfo['type'] == 'file' && !$pathInfo['ext']){
			$pathInfo['ext'] = strtolower($pathInfo['name']);
		}
		$pathInfo = $this->pathInfoCover($pathInfo);
		
		// 没有下载权限,不显示fileInfo信息;
		if(!$pathInfo['canDownload'] || !$showMd5){
			if(isset($pathInfo['fileInfo'])){unset($pathInfo['fileInfo']);}
			if(isset($pathInfo['hashMd5'])){unset($pathInfo['hashMd5']);}
		}
		if(isset($pathInfo['fileID'])){unset($pathInfo['fileID']);}
		if(isset($pathInfo['fileInfo']['path'])){unset($pathInfo['fileInfo']['path']);}
		return $pathInfo;
	}
	
	public function pathInfoCover($pathInfo){
		// 文件文件夹封面; 自适应当前url;
		if(is_array($pathInfo['metaInfo']) && $pathInfo['metaInfo']['user_sourceCover']){
			$pathInfo['fileThumbCover'] = '1';
			$pathInfo['fileThumb'] = Action('user.view')->parseUrl($pathInfo['metaInfo']['user_sourceCover']);
		}
		if($pathInfo['type'] == 'file'){ // 仅针对文件; 追加缩略图等业务;
			$pathInfo = Hook::filter('explorer.list.itemParse',$pathInfo);
		}
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
		if(!is_array($data['fileList'])){$data['fileList'] = array();}
		if(!is_array($data['folderList'])){$data['folderList'] = array();}
		$path = rtrim($path,'/').'/';
		if(!isset($data['current']) || !$data['current']){
			$data['current']  = $this->pathCurrent($path);
		}
		$data['thisPath'] = $path;
		if(is_array($data['current']) && $data['current']['path']){
			$data['thisPath'] = rtrim($data['current']['path'],'/').'/';
		}
		if(!$data['targetSpace']){
			$data['targetSpace'] = $this->targetSpace($data['current']);
		}
		foreach ($data['folderList'] as &$item) {
			if( isset($item['children']) ){
				$item['isParent'] = true;
				$pathParseParent = KodIO::parse($item['path']);
				$this->parseAuth($item['children'],$item['path'],$pathParseParent);
			}
			$item['type'] = isset($item['type']) ? $item['type'] : 'folder';
		};unset($item);
		if($pathParse['type'] == KodIO::KOD_SHARE_LINK) return;

		$data['fileList']   = $this->dataFilterAuth($data['fileList']);
		$data['folderList'] = $this->dataFilterAuth($data['folderList']);
		
		// 列表处理;
		switch($pathParse['type']){
			case KodIO::KOD_USER_FAV:
			case KodIO::KOD_USER_RECENT:
			case KodIO::KOD_GROUP_ROOT_SELF:
			case KodIO::KOD_BLOCK:
				$data['disableSort'] = 1;// 禁用客户端排序;
				// $data['listTypeSet'] = 'list'; //强制显示模式;
				break;
			default:break;
		}
	}
	
	// 显示隐藏文件处理; 默认不显示隐藏文件;
	private function parseDataHidden(&$data,$pathParse){
		if(Model('UserOption')->get('displayHideFile') == '1') return;
		$pathHidden = Model('SystemOption')->get('pathHidden');
		$pathHidden = explode(',',$pathHidden);
		$hideNumber = 0;

		if($pathParse['type'] == KodIO::KOD_USER_SHARE_TO_ME) return;
		foreach ($data as $type =>$list) {
			if(!in_array($type,array('fileList','folderList'))) continue;
			$result = array();
			foreach ($list as $item){
				$firstChar = substr($item['name'],0,1);
				if($firstChar == '.' || $firstChar == '~') continue;
				if(in_array($item['name'],$pathHidden)) continue;
				$result[] = $item;
			}
			$data[$type] = $result;
			$hideNumber  += count($list) - count($result);
		}
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
		if($list && Action('explorer.auth')->allowRootSourceInfo($list[0])) return $list;
		$shareLinkPre = '{shareItemLink';
		foreach ($list as $key => $item) {
			if( substr($item['path'],0,strlen($shareLinkPre)) == $shareLinkPre) continue;
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
		if(!is_array($list)) return array();
		return array_values($list);
	}

	// 文件详细信息处理;
	public function pathInfoMore(&$pathInfo){
		if(!GetInfo::support($pathInfo['ext'])) return $pathInfo;
		if($pathInfo['targetType'] == 'system') return $pathInfo;
		
		$infoKey  = 'fileInfoMore';
		$cacheKey = md5($pathInfo['path'].'@'.$pathInfo['size'].$pathInfo['modifyTime']);
		$fileID   = _get($pathInfo,'fileID');
		$fileID   = _get($pathInfo,'fileInfo.fileID',$fileID);
		if(!isset($pathInfo['sourceID'])){
			$infoMore = Cache::get($cacheKey);
			if(is_array($infoMore)){$pathInfo[$infoKey] = $infoMore;}
		}
		
		// 没有图片尺寸情况,再次计算获取;[更新]
		$isImage  = in_array($pathInfo['ext'],array('jpg','jpeg','png','ico','bmp'));
		if($isImage && !isset($pathInfo[$infoKey]['sizeWidth'])){
			unset($pathInfo[$infoKey]);
			if(!isset($pathInfo['sourceID'])){Cache::remove($cacheKey);} //不使用缓存;
		}
		
		$debug = KodUser::isRoot() && $this->in['debug'] == '1'; // 调试模式,直接立即获取;
		if($debug){
			unset($pathInfo[$infoKey]);Cache::remove($cacheKey);//debug
			if($fileID){Model("File")->metaSet($fileID,$infoKey,null);};
			$infoMore = $this->pathInfoMoreParse($pathInfo['path'],$cacheKey,$fileID);
			if(is_array($infoMore)){$pathInfo[$infoKey] = $infoMore;}
		}
		
		// 异步延迟获取;
		$fileHash = $fileID ? $fileID : $cacheKey;
		if(!isset($pathInfo[$infoKey]) || $pathInfo[$infoKey]['etag'] != $fileHash){
			$args = array($pathInfo['path'],$cacheKey,$fileID);// 异步任务处理;
			$desc = '[pathInfoMore]'.$pathInfo['name'];
			$key  ='pathInfoMoreParse-'.($fileID ? $fileID : $cacheKey);
			TaskQueue::add('explorer.list.pathInfoMoreParse',$args,$desc,$key);
		}
		
		// md5 未计算情况处理;(队列加入失败,执行退出或文件不存在等情况补充)
		if($fileID && is_array($pathInfo['fileInfo']) && !$pathInfo['fileInfo']['hashMd5']){
			$fileInfo = Model("File")->fileInfo($fileID);
			$args = array($fileID,$fileInfo);
			$desc = '[fileMd5]'.$fileID.';path='.$pathInfo['path'];
			TaskQueue::add('FileModel.fileMd5Set',$args,$desc,'fileMd5Set'.$fileID);
		}
		
		// 文件封面;
		if(isset($pathInfo[$infoKey]) && isset($pathInfo[$infoKey]['fileThumb']) ){
			$fileThumb = $pathInfo[$infoKey]['fileThumb'];
			if(!IO::exist($fileThumb)){ // 不存在检测处理;
				unset($pathInfo[$infoKey]);Cache::remove($cacheKey);
				if($fileID){Model("File")->metaSet($fileID,$infoKey,null);};
				return $pathInfo;
			}
			unset($pathInfo[$infoKey]['fileThumb']);
			$pathInfo['fileThumb'] = Action('explorer.share')->linkFile($fileThumb);
		}
		return $pathInfo;
	}
	
	// 解析文件详情;
	public function pathInfoMoreParse($file,$cacheKey,$fileID=false){
		$infoKey  = 'fileInfoMore';
		$infoFull = IO::info($file);
		unset($infoFull[$infoKey]);
		GetInfo::infoAdd($infoFull);
		$infoMore = isset($infoFull[$infoKey]) ? $infoFull[$infoKey]:false;
		
		if(!$infoMore) return;
		$infoMore['etag'] = $fileID ? $fileID : $cacheKey;
		if($fileID){
			Model("File")->metaSet($fileID,$infoKey,json_encode($infoMore));
		}else{
			Cache::set($cacheKey,$infoMore,3600*24*30);
		}
		return $infoMore;
	}
	
	// 获取文件内容, 存储在对象存储时不存在处理; 避免报错
	private function pathGetContent($pathInfo){
		if(!$pathInfo || !$pathInfo['path']){return "";}
		$filePath = $pathInfo['path'];
		if(isset($pathInfo['fileID'])){
			$fileInfo = Model("File")->fileInfo($pathInfo['fileID']);
			if(!$fileInfo || !$fileInfo['path']){return "";}
			$filePath = $fileInfo['path'];
		}
		if(!IO::exist($filePath)){return "";}
		return IO::getContent($filePath);
	}
		
	/**
	 * 追加应用内容信息;
	 */
	private function pathParseOexe(&$pathInfo){
		$maxSize = 1024*1024*1;
		if($pathInfo['ext'] != 'oexe' || $pathInfo['size'] > $maxSize) return $pathInfo;
		if($pathInfo['size'] == 0) return $pathInfo;
		if(!isset($pathInfo['oexeContent'])){
			// 文件读取缓存处理; 默认缓存7天;
			$pathHash = KodIO::hashPath($pathInfo);
			$content  = Cache::get($pathHash);
			if(!$content){
				$content = $this->pathGetContent($pathInfo);
				Cache::set($pathHash,$content,3600*24*7);
			}
			if(!is_string($content) || !$content) return $pathInfo;
			$pathInfo['oexeContent'] = json_decode($content,true);
		}
				
		if( $pathInfo['oexeContent']['type'] == 'path' && 
			isset($pathInfo['oexeContent']['value']) ){
			$linkPath = $pathInfo['oexeContent']['value'];
			$parse = KodIO::parse($pathInfo['path']);
			$parsePath = KodIO::parse($linkPath);
			if($parse['type'] == KodIO::KOD_SHARE_LINK) return $pathInfo;
			if(!$parsePath['isTruePath']){return $pathInfo;}
			
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
}
