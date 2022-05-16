<?php

/**
 * webdav client IO;
 * 
 * 支持kodserver支持协议特性(文件属性扩展extendFileInfo,文件列表扩展extendFileList)
 * 
 * //kodserver: 1, 2, 3, sabredav-partialupdate, extended-mkcol, extended-kodbox
 * //sabre    : 1, 3, extended-mkcol, 2, sabredav-partialupdate
 * //patch文件更新: https://sabre.io/dav/http-patch/  curl请求传递contentType会丢失,暂不支持该情况
 */
class PathDriverWebdav extends PathDriverBase {
	public function __construct($config) {
		parent::__construct();
		
		// 挂载插件开关处理;
		$pluginOption = Model("Plugin")->getConfig('webdav');
		if(!$pluginOption || $pluginOption['mountWebdav'] == '0'){$config = array();}

		$this->config = $config;
		$this->dav = new webdavClient($config);// host/user/password/basePath
		
		$this->ioFileOutServer 	= $config['ioFileOutServer'] != '0'; // 下载是否中转
		$this->ioUploadServer 	= $config['ioUploadServer']  != '0'; // 上传是否中转
		$this->davServerKod 	= false;
		$this->uploadChunkSize  = 1024*1024*5; 	// patch分片上传时; 分片大小;
		
		$davSupport = $config['dav'] ? $config['dav']:'';
		$davSupport = explode(',',$davSupport);
		foreach($davSupport as $key => $type){$davSupport[$key] = trim($type);}
		if(in_array('extended-kodbox',$davSupport)){$this->davServerKod = true;}
	}
	public function mkdir($dir,$repeat=REPEAT_SKIP){
		$parent = $dir;$add = array();// 循环创建文件夹;
		while($parent && $parent != '/' && !$this->exist($parent)){
			$name   = get_path_this($parent);
			$parent = get_path_father($parent);
			$add[] = array($parent,$name);
		}
		$add = array_reverse($add);
		for($i=0; $i < count($add); $i++) {
			$path = rtrim($add[$i][0],'/').'/'.$add[$i][1];
			if(!$this->dav->mkdir($path)){break;}
		}
		if(count($add) == 0 || $i == count($add) ){
			return $this->getPathOuter($dir);
		}
		return false;
	}

	private function _copyMove($action,$from,$to,$repeat=REPEAT_REPLACE,$newName=''){
		if(!$this->exist($from)) return false;
		$this->mkdir($this->pathFather($to));
		if(!$newName){
			$newName = get_path_this($from);
			$newName = $this->fileNameAuto($to,$newName,$repeat,false);
		}
		
		$destPath = rtrim($to,'/').'/'.$newName;
		if($action == 'copy'){$result = $this->dav->copy($from,$destPath);}
		if($action == 'move'){$result = $this->dav->move($from,$destPath);}
		return $this->exist($destPath) ? $this->getPathOuter($destPath):false;
	}
	public function moveSameAllow(){}
	public function move($from,$to,$repeat=REPEAT_REPLACE) {
		return $this->_copyMove('move',$from,$to,$repeat);
	}
	public function copy($from,$to,$repeat=REPEAT_REPLACE) {
		return $this->_copyMove('copy',$from,$to,$repeat);
	}
	
	public function copyFile($from,$to){return $this->copy($from,$to);}
	public function moveFile($from,$to){return $this->move($from,$to);}
	public function remove($path,$toRecycle=true){return $this->dav->delete($path);}
	public function rename($from,$newName){
		$to = get_path_father($from);
		return $this->_copyMove('move',$from,$to,REPEAT_SKIP,$newName);
	}
	public function has($path,$count=false,$isFolder=false){
		$info = $this->listPath($path);
		if(!$info) return false;
		$children = array(
			'hasFolder' => count($info['folderList']),
			'hasFile'   => count($info['folderList']),
		);
		if($count){return $children;}
		return $isFolder ? $children['hasFolder'] : $children['hasFile'];
	}
	public function listAll($path) {
		$result = array();
		$this->listAllMake($path,$result);
		return $result;
	}
	
	public function canRead($path) {return $this->exist($path);}
	public function canWrite($path) {return $this->exist($path);}
	public function getContent($file){return $this->fileSubstr($file, 0, -1);}
	public function fileSubstr($file, $start, $length){
		$start 		= $start ? $start : 0;
		$end   		= $start + $length - 1;
		$range 		= $length > 0 ? 'bytes='.$start.'-'.$end: '';

		$tempFile 	= $this->tempFile();
		$result 	= $this->dav->get($file,$tempFile,$range);
		$content    = $result ? file_get_contents($tempFile):false;	
		$this->tempFileRemve($tempFile);
		return $content;
	}
	
	public function mkfile($path,$content='',$repeat = REPEAT_RENAME){
		$tempFile = $this->tempFile('',$content);
		$result   = $this->upload($path,$tempFile,false,$repeat);
		$this->tempFileRemve($tempFile);
		
		// io 添加时检测; 根目录新建空文件则放过
		if(trim($path,'/') == 'index.html' && !$content){return $this->getPathOuter('/index.html');}
		return $result;
	}
	public function setContent($file, $content = ''){
		return $this->mkfile($file,$content,REPEAT_REPLACE) ? true : false;
	}	
	public function upload($destPath,$localPath,$moveFile=false,$repeat=REPEAT_REPLACE){
		if($this->davServerKod && filesize($localPath) >= $this->uploadChunkSize ){
			return $this->uploadChunk($destPath,$localPath,$moveFile,$repeat);
		}
		$savePath = $this->pathFather($destPath);$this->mkdir($savePath);
		$saveName = $this->fileNameAuto($savePath,$this->pathThis($destPath),$repeat,false);
		$destPath = rtrim($savePath,'/').'/'.$saveName;
		$result   = $this->dav->put($destPath,$localPath);
		// write_log([$destPath,$localPath,$result],'dav');
		return $result['status'] ? $this->getPathOuter($destPath) : false;
	}
	
	// 如若是kod,前端直传;
	public function uploadLink($destPath,$size=0){
		$pose   = strpos($this->config['host'],'/index.php/plugin/webdav/');
		$server = $pose ? substr($pose,0,$pose+1):'';
		if($this->ioUploadServer) return;
		if(!$this->davServerKod || !$server || $size <= 1024*10) return;
		
		$in   = $GLOBALS['in'];
		$args = array(
			'size'				=> $size,
			'uploadWeb'			=>'1',
			'fullPath'			=> _get($in,'fullPath',''),
			'checkType'   		=> _get($in,'checkType',''),// 必须是'checkHash',
			'checkHashSimple'	=> _get($in,'checkHashSimple',''),
		);
		if($args['checkType'] != 'checkHash') return;
		
		$result = $this->uploadSend($destPath,'',$args);
		if(!$result['status'] || !is_array($result['data']['info'])){
			show_json(IO::getLastError(),false);
		}
		$uploadInfo = $result['data']['info'];
		$uploadInfo['webdavUploadTo'] = $uploadInfo['addUploadParam'];
		unset($uploadInfo['addUploadParam']);
		// write_log($uploadInfo,'upload');
		return $uploadInfo;
	}
	
	private function uploadChunk($destPath,$localPath,$moveFile=false,$repeat=REPEAT_REPLACE){
		$savePath = $this->pathFather($destPath);$this->mkdir($savePath);
		$saveName = $this->fileNameAuto($savePath,$this->pathThis($destPath),$repeat,false);
		$destPath = rtrim($savePath,'/').'/'.$saveName;
		
		// 秒传检测处理;
		$checkResult = $this->uploadHashCheck($destPath,$localPath);
		if($checkResult == false){
			$checkResult = $this->uploadWithChunk($destPath,$localPath);
		}
		return $checkResult['code'] ? $this->getPathOuter($destPath) : false;
	}
	
	/**
	 * 上传处理
	 * 
	 * 1. 上传请求 simpleHash; [checkType=checkHash, path,name,checkHashSimple]
	 * 2. 如果simpleHash匹配到simpleHash则秒传请求; [checkType=matchMd5, path,name,checkHashMd5]
	 * 
	 * 正常分片上传 [path,name,size,chunkSize,chunks,chunk]; 
	 * data.info字段是否为非空字符串（目标文件路径），来确认文件秒传是否成功
	 */
	private function uploadHashCheck($destPath,$localPath){
		// 检测hashSimple
		$hashSimple = IO::hashSimple($localPath);
		$args = array('checkType'=>'checkHash','checkHashSimple'=>$hashSimple);
		$result = $this->uploadSend($destPath,'',$args);

		if(!is_array($result['data']) || !array_key_exists('code',$result['data'])) return false;
		if(!$result['data']['code']) return $result['data'];//结束;
		$hashInfo = _get($result['data'],'info.checkFileHash');
		if(!is_array($hashInfo) || !$hashInfo['hashMd5']) return false;
		if($hashInfo['uploadChunkSize']){$this->uploadChunkSize = $hashInfo['uploadChunkSize'];}
		$fileMd5 = IO::hashMd5($localPath);
		if($hashInfo['hashMd5'] != $fileMd5) return false;
		
		// 秒传检测
		$args = array('checkType'=>'matchMd5','checkHashMd5'=>$fileMd5,'checkHashSimple'=>$hashSimple);
		$result = $this->uploadSend($destPath,'',$args);
		if(!is_array($result['data'])) return array('code'=>false,'data'=>$result['error']);
		return $result['data'];
	}
	private function uploadWithChunk($destPath,$localPath){
		$totalSize 	= @filesize($localPath);
		$chunkSize 	= $this->uploadChunkSize;
		$chunkCount	= ceil($totalSize / $chunkSize);$chunkIndex = 0;
		while($chunkIndex < $chunkCount){
			$content  = IO::fileSubstr($localPath,$chunkIndex * $chunkSize,$chunkSize);
			$tempFile = $this->tempFile('',$content);

			$args = array('size'=>$totalSize,'chunkSize'=>$chunkSize,'chunks'=>$chunkCount,'chunk'=>$chunkIndex);
			$result = $this->uploadSend($destPath,$tempFile,$args);$chunkIndex++;
			if(!is_array($result['data'])) return array('code'=>false,'data'=>$result['error']);
			if(!$result['data']['code']) return $result['data'];//有失败情况,则直接结束;
		}
		return $result;
	}
	private function uploadSend($destPath,$tempFile,$args){
		$this->dav->setHeader('X-DAV-UPLOAD','kodbox');
		$this->dav->setHeader('X-DAV-ARGS',base64_encode(json_encode($args)));
		return $this->dav->put($destPath,$tempFile);
	}

	
	public function download($file, $destFile){
		$result = $this->dav->get($file,$destFile);
		return $result ? $destFile : false;
	}
	public function listPath($path,$simple=false){
		$data = $this->dav->propfind($path);
		if(!$data['status'] || !$data['data'] || !$data['data']['response']) return false;

		$list = $data['data']['response'];
		$current = isset($list[0]) ? $list[0]:$list;
		$current = $this->_pathInfoParse($current);
		$result  = array('fileList'=>array(),'folderList'=>array(),'current'=>$current);
		if(!isset($list[0])) return $result;
		
		foreach($list as $index => $val){
			if($index == 0 ) continue;
			$item = $this->_pathInfoParse($val);
			$type = $item['type'] == 'file' ? 'fileList':'folderList';
			$result[$type][] = $item;
			
			$key = trim($item['path'],'/');
			$this->infoCache[$key] = $item;
		}
		// 追加信息处理;extendFileList
		if(isset($data['data']['extendFileList'])){
			$arr  = json_decode(base64_decode($data['data']['extendFileList']),true);
			$result = array_merge($result,$arr ? $arr:array());
		}
		// pr($data,$result);exit;
		return $result;
	}
	

	public function fileOut($path, $download = false, $downFilename = false, $etag=''){
		if($this->fileOutKod($path)) return;
		$this->fileOutServer($path, $download, $downFilename, $etag);
	}
	public function fileOutServer($path, $download = false, $downFilename = false, $etag=''){
		parent::fileOut($path, $download, $downFilename, $etag);
	}
	public function fileOutImage($path,$width=250){
		if($this->fileOutKod($path)) return;
		parent::fileOutImage($path,$width);
	}
	public function fileOutImageServer($path,$width=250){
		parent::fileOutImage($path,$width);
	}
	private function fileOutKod($path){
		if(!$this->davServerKod) return false;
		if($this->isFileOutServer()) return false;
		$data = $this->info($path);
		if(!$data || !$data['fileOutLink']) return false;
		
		$link  = $data['fileOutLink'];
		$param = parse_url_query(this_url());
		$disableKey = array('accessToken','path');
		foreach($param as $key=>$val){
			if(!$val || in_array($key,$disableKey)) continue;
			$link .= '&'.$key.'='.$val;
		}
		$this->fileOutLink($link);
	}
	
	// 缓存处理;
	public static $infoCache = array();
	public static $listCache = array();
	private function _pathInfo($path,$cacheInfo=false){
		if(!$this->pathCheck($path)) return false;
		$key = trim($path,'/');
		if($cacheInfo){$this->infoCache[$key] = $cacheInfo;return;}
		if(isset($this->infoCache[$key])) return $this->infoCache[$key];
		
		$data = $this->listPath($path);
		$pathInfo = $data ? $data['current']:false;
		if($pathInfo){$this->infoCache[$key] = $pathInfo;}
		return $pathInfo;
	}
	// 文件属性; name/path/type/size/createTime/modifyTime/
	private function _pathInfoParse($item){
		$path 	= $this->dav->uriToPath($item['href']);
		$info   = array('name'=>get_path_this($path),'path'=>$path,'type'=>'folder');
		$prop   = _get($item,'propstat.prop',array());
		if(is_array($item['propstat']) && is_array($item['propstat'][0])){
			$prop  = $item['propstat'][0]['prop'];
		}
		if(isset($prop['getlastmodified'])){
			$info['modifyTime'] = strtotime($prop['getlastmodified']);
		}
		if(isset($prop['creationdate'])){
			$info['createTime'] = strtotime($prop['creationdate']);
		}
		if(isset($prop['getcontentlength'])){
			$info['size'] = $prop['getcontentlength'];
		}
		$info['type'] = $prop['resourcetype'] == '' ? 'file':'folder';
		$info['name'] = $info['name'] ? $info['name']:'/';
		$info['path'] = $this->getPathOuter($info['path']);
		$info['_infoSimple'] = true;
		if($info['type'] == 'file'){
			$info['ext'] = get_path_ext($info['name']);
		}
		if(isset($prop['extendFileInfo'])){
			$arr  = json_decode(base64_decode($prop['extendFileInfo']),true);
			$info = array_merge($info,$arr ? $arr:array());
		}
		return $info;
	}

	public function fileInfo($path,$simple=false,$fileInfo = array()) {
		return $this->_pathInfo($path);
	}
	public function folderInfo($path,$simple=false,$itemInfo=array()) {
		return $this->_pathInfo($path);
	}
	public function size($path){
		$info = $this->_pathInfo($path);
		return $info ? $info['size'] : 0;
	}
	public function info($path){
		return $this->_pathInfo($path);
	}
	public function infoWithChildren($path){
		$this->dav->setHeader('X-DAV-ACTION','infoChildren');
		$info = $this->_pathInfo($path);
		if($this->davServerKod) return $info;
		if(is_array($info) && is_array($info['children'])) return $info;
				
		return parent::infoWithChildren($path);
	}
	
	public function exist($path){
		$info = $this->_pathInfo($path);
		return $info ? true : false;
	}
	public function isFile($path){
		$info = $this->_pathInfo($path);
		return ($info && $info['type'] == 'file') ? true : false;
	}
	public function isFolder($path){
		$info = $this->_pathInfo($path);
		return ($info && $info['type'] == 'folder') ? true : false;
	}
	private function pathCheck($path){
		$PATH_LENGTH_MAX = 4096;//路径最长限制;
		return strlen($path) >= $PATH_LENGTH_MAX ? false:true;
	}
}
