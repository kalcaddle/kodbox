<?php

// source 路径自动解析补全处理; {source:xx1}/aa/bb/c;  ==> 转为{source:xx2}
class explorerAutoPathParse extends Controller {
	function __construct() {
		parent::__construct();
	}

	// 入口统一处理;
	public function parseAuto(){
		$theAction 	= strtolower(ACTION);
		$checkArr   = array(
			'explorer.index.pathinfo' 		=> array('path','[dataArr]'),
			'explorer.index.pathrename'		=> array('path'),
			'explorer.index.pathdelete'		=> array('[dataArr]'),
			'explorer.index.pathcopy'		=> array('[dataArr]'),
			'explorer.index.pathcute'		=> array('[dataArr]'),
			'explorer.index.pathcopyto'		=> array('path','[dataArr]'),
			'explorer.index.pathcuteto' 	=> array('path','[dataArr]'),
			'explorer.index.mkdir' 			=> array('path'),
			'explorer.index.mkfile' 		=> array('path'),
			'explorer.index.filesave' 		=> array('path'),
			'explorer.index.setmeta' 		=> array('path'),
			'explorer.index.setdesc' 		=> array('path'),
			'explorer.index.setauth' 		=> array('path'),
			'explorer.index.fileout' 		=> array('path'),
			'explorer.index.filedownload' 	=> array('path'),
			'explorer.index.zipdownload' 	=> array('path'),
			'explorer.index.filedownloadremove' => array('path'),
			'explorer.index.fileoutby' 		=> array('path'),
			'explorer.index.unzip' 			=> array('path','pathTo'),
			'explorer.index.unziplist' 		=> array('path'),
			'explorer.index.pathlog' 		=> array('path'),
			'explorer.index.filethumb' 		=> array('path'),
			'explorer.list.path'	 		=> array('path'),
			'explorer.list.listall'	 		=> array('path'),
			'explorer.usershare.add'	 	=> array('path'),
			'explorer.upload.fileupload'	=> array('path'),
			'explorer.upload.serverdownload'=> array('path'),
			'explorer.editor.filesave'		=> array('path'),
		);
		if(!$checkArr[$theAction]){return;}
		
		$allowNotMatchAction = array(
			'explorer.index.mkdir' 		=> 1,
			'explorer.index.mkfile' 	=> 1,
			'explorer.index.unzip' 		=> 1,
  		);
		$allowNotMatch  = isset($allowNotMatchAction[$theAction]) ? true : false;
		foreach($checkArr[$theAction] as $key){
			if(substr($key,0,1) == '['){
				$this->parseArr(substr($key,1,-1),$allowNotMatch);
			}else{
				$this->parseKey($key,$allowNotMatch);
			}
		}
		// pr($this->in,$_REQUEST);exit;
	}
	
	public function parseKey($key,$allowNotMatch=false){
		if(!$this->in[$key]){return;}
		$this->in[$key] = $this->parsePath($this->in[$key],$allowNotMatch);
	}
	public function parseArr($key,$allowNotMatch=false){
		$data = json_decode($this->in[$key],true);
		if(!is_array($data)){return;}
		foreach($data as $k=>$v){
			if(!is_array($v) || !isset($v['path'])){continue;}
			$data[$k]['path'] = $this->parsePath($v['path'],$allowNotMatch);
		}
		$this->in[$key] = json_encode($data);
	}
	
	public function parsePath($path,$allowNotMatch=false){
		if(!$path){return $path;}
		if(substr($path,0,8) == '{search}'){
			return $this->parseSourceSearch($path);
		}
		
		if(substr($path,0,8) != '{source:'){return $path;}
		$path = KodIO::clear($path);
		$info = preg_match("/^(\{source:(\d+)\})(\/?.*)$/",$path,$match);
		if(!$match || !trim($match[3],'/')){return $path;}
		
		$pathArr  = explode('/',trim($match[3],'/'));
		$sourceID = $match[2];
		foreach($pathArr as $i=>$name){
			$pathInfo = $this->parseSource($sourceID,$name);
			if(!$pathInfo){ // 不存在时, 当前文档为文件时; 直接返回;  允许文件路径后续追加无效内容;
				$itemInfo = Model("Source")->field("sourceID,name,isFolder")->where(array('sourceID'=>$sourceID))->find();
				if($itemInfo && $itemInfo['isFolder'] == '0'){$pathInfo = $itemInfo;}
			}
			if($pathInfo && $pathInfo['isFolder'] == '0'){// 如果是文件则直接返回;忽略后续内容;
				$sourceID = $pathInfo['sourceID'];break;
			}
			
			if($pathInfo){$sourceID = $pathInfo['sourceID'];continue;}
			if(!$allowNotMatch){return show_json(LNG('common.pathNotExists'),false);}
			return '{source:'.$sourceID.'}/'.implode('/',array_slice($pathArr,$i));
		}
		return '{source:'.$sourceID.'}/';
	}
	private function parseSourceSearch($path){
		if(substr($path,0,8) != '{search}'){return $path;}
		if(!strstr($path,'parentPath=')){return $path;}
		$all    = explode('@',ltrim(substr($path,8),'/'));
		$result = array();
		foreach ($all as $item) {
			$keyv = explode('=',$item ? $item : '');
			if(count($keyv) == 2 || $keyv[0] == 'parentPath'){
				$value = trim(rawurldecode($keyv[1]));
				$value = $this->parsePath($value);
				$item = $keyv[0].'='.rawurlencode($value);
			}
			$result[] = $item;
		}
		return '{search}/'.implode('@',$result);
	}
	
	private function parseSource($sourceID,$name){
		static $cache = array();
		$cacheKey = $sourceID.'-'.$name;
		if(!trim($name)){return $sourceID;}
		if(array_key_exists($cacheKey,$cache)){
			return $cache[$cacheKey];
		}
		$where    = array('parentID'=>$sourceID,'name'=>$name);
		$pathInfo = Model("Source")->field("sourceID,name,isFolder")->where($where)->find();
		$cache[$cacheKey] = $pathInfo ? $pathInfo : 0;
		return $cache[$cacheKey];
	}
}
