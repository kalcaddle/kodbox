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
		foreach($param['wordsMutil'] as $word){
			$param['words'] = $word;
			$find = $this->searchData($param);
			if(!$list) {
				$list = $find;
			}else{
				$list['fileList']   = array_merge($list['fileList'],$find['fileList']);
				$list['folderList'] = array_merge($list['folderList'],$find['folderList']);
			}
		}
		// 合并相同的结果;
		$list['fileList']   = array_values(array_to_keyvalue($list['fileList'],'path'));
		$list['folderList'] = array_values(array_to_keyvalue($list['folderList'],'path'));
		
		$list['pageInfo']['totalNum']  = count($list['fileList']) + count($list['folderList']);
		$list['pageInfo']['pageTotal'] = 1;
		$list['disableSort'] = 1;
		return $list;
	}
	
	private function searchData($param){
		$path  = $param['parentPath'];
		$parse = KodIO::parse($path);
		$searchContent = $param['words'] && in_array('content',$param['option']);
		
		//本地路径搜索;
		$searchIO = array('',KodIO::KOD_IO,KodIO::KOD_SHARE_ITEM,KodIO::KOD_SHARE_LINK);
		if($path && (in_array($parse['type'],$searchIO) || $searchContent) ){
			unset($param['parentID']);
			return $this->searchIO($path,$param);
		}
		return $this->model->listSearch($param);
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
			}
		}
		
		if(isset($param['sizeFrom'])) $param['sizeFrom'] = intval($param['sizeFrom']);
		if(isset($param['sizeTo'])) $param['sizeTo'] = intval($param['sizeTo']);
		if(isset($param['timeFrom'])) $param['timeFrom'] = intval($param['timeFrom']);
		if(isset($param['timeTo'])) $param['timeTo'] = intval($param['timeTo']);
		if(!is_array($param['option'])){$param['option'] = array();}
		
		$param['words'] = trim($param['words'], '/');
		$path = $param['parentPath'];
		$parse = KodIO::parse($path);
		if(!$path || $parse['type'] == ''){ //本地路径
			$user = Session::get('kodUser');
			$param['parentID'] = $user['sourceInfo']['sourceID'];
			return;
		}
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
		$param['parentID'] = $sourceInfo['sourceID'];
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

		$result = array('folderList'=> array(),'fileList'=> array());
		$matchMax = 1000; $findNum = 0;
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
				if(!$this->searchFile($info,$param['words'])){
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
	
	private function searchFile(&$file,$search){
		if(!is_text_file($file['ext'])) return false;
		if($file['size'] <= 1) return false;
		if($file['size'] >= 1024*1024*10) return false;
		
		if(isset($file['filePath'])){
			$content = IO::getContent($file['filePath']);
		}else{
			$content = IO::getContent($file['path']);
		}
		
		$isCase  = false;
		$find = content_search($content,$search,$isCase);
		unset($content);
		if(!$find) return false;
		$file['searchTextFile'] = $find;
		// $file['searchContentMatch'] = mb_substr($find[0]['str'],0,250).'...'; //单行数据展示
		return true;
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