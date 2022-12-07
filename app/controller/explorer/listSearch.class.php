<?php
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/
class explorerListSearch extends Controller{
	public function __construct(){
		parent::__construct();
		$this->model = Model("Source");
	}
	
	// {search}/key=val@key2=value2;
	public function listSearch($pathInfo){
		if( !Action('user.authRole')->authCanSearch() ){
			show_json(LNG('explorer.noPermissionAction'),false);
		}
		return $this->listSearchPath($pathInfo);
	}
	public function listSearchPath($pathInfo){
		$param = array();$paramIn=array();$sourceInfo= array();
		$this->parseParam($pathInfo,$param,$paramIn,$sourceInfo);
		if(	isset($param['option']) &&
			in_array('mutil',$param['option']) && 
			is_array($param['wordsMutil']) ){
			$list = $this->searchMutil($param);
		}else{
			$list = $this->searchData($param);
		}
		
		// 搜索内容,强制列表显示;
		if(in_array('content',$param['option'])){
			$list['listTypeSet'] = 'list';
		}
		$list['searchParam']  = $paramIn;
		$list['searchParent'] = $sourceInfo;
		if($param['parentPath']){
			$list['searchParent'] = IO::info($param['parentPath']);
		}
		$this->searchResultCount($list);
		return $list;
	}

	private function searchMutil($param){
		$param['words'] = '';
		$list = false;
		if(count($param['wordsMutil']) > 100){
			show_json(LNG('common.numberLimit').'(100)',false);
		}
		foreach($param['wordsMutil'] as $word){
			$param['words'] = $word;
			$find = $this->searchData($param);
			if(!$list) {$list = $find;continue;}
			
			$list['fileList']   = array_merge($list['fileList'],$find['fileList']);
			$list['folderList'] = array_merge($list['folderList'],$find['folderList']);
		}
		// 合并相同的结果;
		$list['fileList']   = array_values(array_to_keyvalue($list['fileList'],'path'));
		$list['folderList'] = array_values(array_to_keyvalue($list['folderList'],'path'));
		
		$list['pageInfo']['totalNum']  = count($list['fileList']) + count($list['folderList']);
		$list['pageInfo']['pageTotal'] = 1;
		$list['disableSort'] = 1;
		return $list;
	}
	
	public function searchData($param){
		$path  = $param['parentPath'];
		$parse = KodIO::parse($path);$parseBefore = $parse;
		$io = IO::init($path);
		if($io->pathParse['truePath']){ // [内部协作分享+外链分享]-物理路径及io路径;
			$path  = $io->pathParse['truePath'];
			$parse = KodIO::parse($path);
		}
		if($parse['type'] == KodIO::KOD_SHARE_LINK && !$io->pathParse['truePath']){ // 外链分享-物理路径及io路径;
			$param['parentID'] = $io->path;
		}
		$result = Hook::trigger('explorer.listSearch.searchDataBefore',$param);//调整$param;
		if(is_array($result)){$param = $result;}

		//本地路径搜索;
		$searchContent = $param['words'] && in_array('content',$param['option']);
		$listData = array('fileList' => array(),'folderList'=>array());
		$fromIO   = $path && ( in_array($parse['type'],array('',false,KodIO::KOD_IO)) || $searchContent);
		if(is_array($param['fileID'])){$fromIO =  false;}
		if($fromIO){
			unset($param['parentID']);
			$listData = $this->searchIO($path,$param);
		}else{
			if(!$param['parentID']) return $listData;
			$listData = $this->model->listSearch($param);
		}

		$result   = Hook::trigger('explorer.listSearch.searchDataAfter',$param,$listData); // 调整结果
		if(is_array($result)){$listData = $result;}
		// pr($fromIO,$path,$param,$parse,$listData);exit;
		
		$listData = $this->listDataParseShareItem($listData,$parseBefore);
		$listData = $this->listDataParseShareLink($listData,$parseBefore);
		return $listData;
	}
	
	// 内部协作分享搜索
	public function listDataParseShareItem($listData,$parseSearch){
		if($parseSearch['type'] != KodIO::KOD_SHARE_ITEM) return $listData;
		$userShare = Action('explorer.userShare');
		$shareInfo = Model("Share")->getInfo($parseSearch['id']);
		foreach ($listData as $key => $keyList) {
			if($key != 'folderList' && $key != 'fileList' ) continue;
			$keyListNew = array();
			foreach ($keyList as $source){
				$source = $userShare->_shareItemeParse($source,$shareInfo);
				if($source){$keyListNew[] = $source;}
			};
			$listData[$key] = $keyListNew;
		}
		return $listData;
	}
	// 外链分享搜索
	public function listDataParseShareLink($listData,$parseSearch){
		if($parseSearch['type'] != KodIO::KOD_SHARE_LINK) return $listData;
		foreach ($listData as $key => $keyList) {
			if($key != 'folderList' && $key != 'fileList' ) continue;
			$keyListNew = array();
			foreach ($keyList as $source){
				$source = Action('explorer.share')->shareItemInfo($source);
				if($source){$keyListNew[] = $source;}
			};
			$listData[$key] = $keyListNew;
		}
		return $listData;
	}
	
	private function parseParam($pathInfo,&$param,&$paramIn,&$sourceInfo){
		$paramCheck = array(
			'parentPath'=> 'require',
			'words'		=> 'require',
			'option' 	=> 'require',
			'wordsMutil'=> 'require',
			"sizeFrom"	=> 'float',
			"sizeTo"	=> 'float',
			"timeFrom"	=> 'date',
			"timeTo"	=> 'date',
			'fileType'	=> 'require',//folder|ext;
			"user"		=> 'require',
		);
		$pathInfo['param'] = trim($pathInfo['param'],'/');
		$paramIn = $this->parseSearch($pathInfo['param']);
		$param   = array();
		foreach ($paramCheck as $key => $checkType) {
			if( !isset($paramIn[$key]) ) continue;
			if( !Input::check($paramIn[$key],$checkType) ) continue;
			$param[$key] = $paramIn[$key];
			if($checkType == 'date'){
				$param[$key] = strtotime($paramIn[$key]);
			}
			//文件处理
			if($checkType == 'fileType' && $paramIn[$key] != 'folder'){
				$param[$key] = explode(',',$paramIn[$key]);
			}
			if($key == 'option'){
				$param[$key] = array_filter(explode(',',$paramIn[$key]));
			}
			if($key == 'wordsMutil'){
				$param[$key] = array_filter(explode("\n",$paramIn[$key]));
				$param[$key] = array_values($param[$key]);
			}
		}
		
		if(isset($param['sizeFrom'])) $param['sizeFrom'] = intval($param['sizeFrom']);
		if(isset($param['sizeTo'])) $param['sizeTo'] = intval($param['sizeTo']);
		if(isset($param['timeFrom'])) $param['timeFrom'] = intval($param['timeFrom']);
		if(isset($param['timeTo'])) $param['timeTo'] = intval($param['timeTo']);
		if(!is_array($param['option'])){$param['option'] = array();}
		
		$searchPath     	= $param['parentPath'] ? $param['parentPath'] : MY_HOME;
		$param['words'] 	= trim($param['words'], '/');
		$param['parentID']  = $this->searchPathSource($searchPath);
	}
	
	public function searchPathSource($path){
		if(!$path) return false;
		$path  = trim($path,'/');
		$parse = KodIO::parse($path);
		Action('explorer.auth')->canView($path); //权限检测;
		if($parse['type'] == KodIO::KOD_SHARE_ITEM){
			$shareID  	= $parse['id'];
			$sourceID 	= trim($parse['param'],'/');
			$sourceInfo = Action("explorer.userShare")->sharePathInfo($shareID,$sourceID);
			if(!$sourceInfo){
				show_json(LNG('explorer.noPermissionAction'),false);
			}
		}else if($parse['type'] == KodIO::KOD_SOURCE){
			$sourceInfo = IO::info($path);
		}
		return $sourceInfo ? $sourceInfo['sourceID'] : false;
	}
	
	/**
	 * 本地路径,IO路径搜索;
	 */
	public function searchIO($path,$param){
		$list = IO::listAll($path);
		$fileType = isset($param['fileType']) ? $param['fileType']:'';// folder|allFile|''|ext1,ext2
		$onlyFolder = $fileType == 'folder';  // 仅文件夹
		$onlyFile   = $fileType == 'allFile'; // 仅文件
		$allowExt   = false;
		$searchContent = $param['words'] && in_array('content',$param['option']);
		if($fileType && $fileType != 'folder' && $fileType != 'allFile'){
			$allowExt   = explode(',',strtolower($fileType));
			$onlyFile 	= true;
		}

		$isLocalFile = file_exists($path);
		$result = array('folderList'=> array(),'fileList'=> array());
		$matchMax = 20000; $findNum = 0;
		foreach($list as $item){
			check_abort_echo();
			$isFolder = $item['folder'];
			if($onlyFolder && !$isFolder) continue;
			if($onlyFile && $isFolder) continue;
			if($searchContent && $isFolder) continue;
		
			$name = get_path_this($item['path']);
			$ext  = get_path_ext($name);
			if(!$isFolder && $allowExt && !in_array($ext,$allowExt)) continue;
			
			//搜索文件名;
			if (isset($param['words']) && $param['words'] &&
				!in_array('content',$param['option']) &&
				!$this->matchWords($name,$param['words']) ){
				continue;
			}
			
			if(isset($item['sourceInfo'])){ //IO文件;
				$info = $item['sourceInfo'];
				if(isset($item['filePath'])){
					$info['filePath'] = $item['filePath'];
				}
			}else{
				$info = IO::info($item['path']);
			}
			if(!$info) continue;
			if( $isFolder && ($param['sizeFrom'] || $param['sizeTo'])  ) continue;
			$theTime = isset($info['modifyTime']) ? intval($info['modifyTime']):0;//modifyTime createTime;
			if( $theTime && isset($param['timeFrom']) &&
				$theTime < $param['timeFrom'] ){
				continue;
			}
			if( $theTime && isset($param['timeTo']) &&
				$theTime > $param['timeTo'] ){
				continue;
			}
			if( isset($param['sizeFrom']) && !$isFolder && 
				intval($info['size']) < $param['sizeFrom'] ){
				continue;
			}
			if( isset($param['sizeTo']) && !$isFolder && 
				intval($info['size']) > $param['sizeTo'] ){
				continue;
			}
			if( isset($param['user']) && 
				( (is_array($info['modifyUser']) && $param['user'] != $info['modifyUser']['userID']) ||
				  (is_array($info['createUser']) && $param['user'] != $info['createUser']['userID']) ) ){
				continue;
			}
						
			if (isset($param['words']) && $param['words'] &&
				in_array('content',$param['option']) ){
				if(!$this->searchFile($info,$param['words'],$isLocalFile)){
					continue; // 内容匹配; 
				}
			}
			unset($info['filePath']);
			$findNum ++;
			if($findNum > $matchMax) break;
			if($isFolder){
				$result['folderList'][] = $info;
			}else{
				$result['fileList'][] = $info;
			}
		}
		// pr($path,$param,$list,$result,$allowExt);exit;
		return $result;
	}
	
	private function searchFile(&$file,$search,$isLocalFile){
		if($file['size'] <= 3) return false;
		$filePath = isset($file['filePath']) ? $file['filePath'] : $file['path'];
		if(is_text_file($file['ext']) && $isLocalFile ){
			if($file['size'] >= 1024*1024*10) return false;
			$content = IO::getContent($filePath);
		}else{
			$content = Hook::trigger('explorer.listSearch.fileContentText',$file);
			if(!$content && is_text_file($file['ext'])){
				if($file['size'] >= 1024*1024*10) return false;
				$content = IO::getContent($filePath);
			}
		}
		if(!$content) return false;
		if(!is_text_file($file['ext'])){//单行数据展示
			$find = $this->contentSearchMatch($content,$search);
			if($find){$file['searchContentMatch'] = $find;}
			return $find ? true : false;
		}
		$find = content_search($content,$search,false,505);
		if($find){$file['searchTextFile'] = $find;}
		return $find ? true : false;
	}
	
	// $content中匹配到$word的内容截取; 最大长度默认300; 前一行+
	public function contentSearchMatch(&$content,$word,$maxContent = 300){
		if(!$content || !$word) return false;
		$pose  = stripos($content,$word);
		if($pose === false) return false;
		$start = intval($pose) <= 0 ? 0 : $pose;
		$stopChar = array("\n",' ',',','.','!','.','!',';');
		for($i = $start; $i >= 0; $i--){
			$start = $i;
			if($pose - $i > $maxContent * 0.2 - strlen($word)){break;}
			if(in_array($content[$i],$stopChar)) break;
		}
		$start  = $start <= 20 ? 0 : $start;
		$length = strlen($word) + $maxContent;
		$str 	= utf8Repair(substr($content,$start,$length));
		if(strlen($content) > $start + $length + 10){$str .= '...';}
		$str = str_replace(array("\n\n",'&nbsp;','&quot;'),array("\n",' ','"'),$str);
		
		// pr($word,$pose,$start,$length,$str,$content);exit;
		return $str;
	}
	
	// 多个搜索词并列搜索; "且"语法,返回同时包含的内容;
	private function matchWords($name,$search){
		$wordsArr  = explode(' ',trim($search));
		$result    = true;
		if( strlen($search) > 2 &&
			(substr($search,0,1) == '"' && substr($search,-1)  == '"') ||
			(substr($search,0,1) == "'" && substr($search,-1)  == "'")
		){
			$search = substr($search,1,-1);
			$wordsArr = array($search);
		}
		
		foreach($wordsArr as $searchWord){
			$searchWord = trim($searchWord);
			if(!$searchWord) continue;
			if(stripos($name,$searchWord) === false){
				$result = false;
				break;
			}
		}
		return $result;
	}
	
	private function searchResultCount(&$list){
		if(!$list['searchParam']['words']) return;
		$list['searchCount'] = 0;
		foreach ($list['fileList'] as $item) {
			if(!is_array($item['searchTextFile'])) continue;
			$list['searchCount'] += count($item['searchTextFile']);
		}
	}
	
	public function parseSearch($param){
		if(!$param) return array();
		$param  = ltrim($param,'/');
		$all    = explode('@',$param);
		$result = array();
		foreach ($all as $item) {
			if(!$item || !strstr($item,'=')) continue;
			$keyv = explode('=',$item);
			if(count($keyv) != 2 || !$keyv[0] || !$keyv[1]) continue;
			$value = trim(rawurldecode($keyv[1]));
			if(strlen($value) > 0 ){
				$result[$keyv[0]] = $value;
			}
		}
		return $result;
	}
}