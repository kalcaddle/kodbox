<?php

/**
 * webdav 服务端
 * 
 * 简易文档: https://tech.yandex.com/disk/doc/dg/reference/put-docpage/
 * 文档:     http://www.webdav.org/specs/rfc2518.html
 */
class webdavServer {
	public $lastError = '';
	public $method = '';
	public function __construct($root,$DAV_PRE_PATH) {
		$this->root = $root;
		$this->initPath($DAV_PRE_PATH);
		$this->start();
	}
	public function initPath($DAV_PRE_PATH){
		$GLOBALS['requestFrom'] = 'webdav';
		$this->method = 'http'.HttpHeader::method();
		$uri  = rtrim($_SERVER['REQUEST_URI'],'/').'/'; //带有后缀的从domain之后部分;
		if(!$this->pathCheck($uri)){//路径长度限制
			$this->lastError = LNG('common.lengthLimit');
			$this->response(array("code"=>404));exit;
		}
		$this->urlBase  = substr($uri,0,strpos($uri,$DAV_PRE_PATH)+1); //$find之前;
		$this->urlBase  = rtrim($this->urlBase,'/').$DAV_PRE_PATH;
		$this->uri   	= $this->pathGet();
		$this->path 	= $this->parsePath($this->uri);
		if(strpos($uri,$DAV_PRE_PATH) === false){
			$this->lastError = LNG('common.noPermission');
			$this->response(array("code"=>404));exit;
		}		
	}
	public function checkUser(){
		$user = HttpAuth::get();
		if($user['user'] == 'admin' && $user['pass'] == '123'){
			return true;
		}
		HttpAuth::error();
	}
	private function pathCheck($path){
		$PATH_LENGTH_MAX = 4096;//路径最长限制;
		return strlen($path) >= $PATH_LENGTH_MAX ? false:true;
	}
	
	public function start(){
		$method = 'http'.HttpHeader::method();
		if(!method_exists($this,$method)){
			return HttpAuth::error();
		}
		if($method == 'httpOPTIONS'){
			return self::response($this->httpOPTIONS());
		}
		$this->checkUser();
		$notCheck = array('httpMKCOL','httpPUT');
		if( !in_array($method,$notCheck) && 
			!$this->pathExists($this->path,true) ){
			$result = array('code' => 404);
		}else{
			$result = $this->$method();
		}
		if(!$result) return;//文件下载;
		self::response($result);
	}
	public function pathGet($dest=false){
		$path = $dest ? $_SERVER['HTTP_DESTINATION'] : $_SERVER['REQUEST_URI'];
		$path = KodIO::clear(rawurldecode($path));
    	if(!strstr($path,KodIO::clear($this->urlBase))) return false;
		return substr($path,strpos($path,$this->urlBase)+ strlen($this->urlBase) );
	}
	
	public function pathExists($path,$allowInRecycle=false){
		return file_exists($path);
	}
	public function pathMkdir($path){
		return mkdir($path,0777,true);
	}
	public function pathInfo($path){
		return path_info($path);
	}
	public function pathList($path){
		return path_list($path);
	}
	// range支持;
	public function pathOut($path){
		echo file_get_contents($path);
	}
	public function pathPut($path,$tempFile=''){
		if(!$tempFile){
			return file_put_contents($path,'');
		}
		return move_path($tempFile,$path);
	}
	public function pathRemove($path){
		if(is_file($path)){
	        return @unlink($this->path);
	    }else{
	        return del_dir($this->path);
	    }
	}
	public function pathMove($path,$dest){
		return move_path($path,$dest);
	}
	public function pathCopy($path,$dest){
		return copy_dir($path,$dest);
	}
    public function parsePath($path){
    	return $path;
	}
	public function parseItem($item,$isInfo){
		$pathAdd = $this->pathGet().'/'.$item['name'];
		$pathAdd = '/'.str_replace('%2F','/',rawurlencode($pathAdd));
		if($isInfo){
			$pathAdd = '/'.str_replace('%2F','/',rawurlencode($this->pathGet()));
		}
		if(!trim($item['modifyTime'])){$item['modifyTime'] = time();}
		if(!trim($item['createTime'])){$item['createTime'] = time();}
		
		$result  = array(
			'href' 			=> KodIO::clear($this->urlBase.$pathAdd),
			'modifyTime' 	=> @gmdate("D, d M Y H:i:s",$item['modifyTime']).' GMT',
			'createTime' 	=> @gmdate("Y-m-d\\TH:i:s\\Z",$item['createTime']),
			'size' 			=> $item['size'] ? $item['size']:0,
		);
		return $result;
	}
	
	public function parseItemXml($itemFile,$isInfo){
		$item = $this->parseItem($itemFile,$isInfo);
		if ($itemFile['type'] == 'folder') {//getetag
			$xmlAdd = "<D:resourcetype><D:collection/></D:resourcetype>";
			$xmlAdd.= "<D:getcontenttype>httpd/unix-directory</D:getcontenttype>";
			$item['href'] = rtrim($item['href'],'/').'/';
			if(isset($_SERVER['HTTP_DATE']) && isset($_SERVER['HTTP_DEPTH'])){
				// goodsync同步处理;HTTP_DATE/HTTP_DEPTH; 首次列表展开失败问题处理;
				$result['modifyTime'] = $_SERVER['HTTP_DATE'];
			}
		}else{
			$ext    = $itemFile['ext'] ? $itemFile['ext']:get_path_ext($itemFile['name']);
			$mime   = get_file_mime($ext);
			$xmlAdd = '<D:resourcetype/>';
			$xmlAdd.= "<D:getcontenttype>{$mime}</D:getcontenttype>";
		}
		
		$infoMore = array();
		$picker = array(
			'hasFile','hasFolder','fileInfo','fileInfoMore','oexeContent','parentID','isTruePath',
			'isReadable','isWriteable','sourceRoot','icon','iconClassName','children',
		);
		foreach ($picker as $key){
			if(array_key_exists($key,$itemFile)){$infoMore[$key] = $itemFile[$key];}
		}
		if($itemFile['type'] == 'file'){
			$param = array('path'=>$itemFile['path']);
			$infoMore['fileOutLink'] = Action('user.index')->apiSignMake('explorer/index/fileOut',$param);
		}
		if($infoMore){
			$xmlAdd.= "<D:extendFileInfo>".base64_encode(json_encode($infoMore))."</D:extendFileInfo>";
		}
		
		return "
		<D:response>
			<D:href>{$item['href']}</D:href>
			<D:propstat>
				<D:prop>
					<D:getlastmodified>{$item['modifyTime']}</D:getlastmodified>
					<D:creationdate>{$item['createTime']}</D:creationdate>
					<D:getcontentlength>{$item['size']}</D:getcontentlength>
					{$xmlAdd}
				</D:prop>
				<D:status>HTTP/1.1 200 OK</D:status>
			</D:propstat>
		</D:response>";
	}
	public function pathListMerge($listData){
		if(!$listData) return $listData;
		$keyList = array('fileList','folderList','groupList');
		$list    = array();
		foreach ($listData as $key=>$typeList){
			if(!in_array($key,$keyList) || !is_array($typeList)) continue;
			$list = array_merge($list,$typeList);
		}
		//去除名称中的/分隔; 兼容存储挂载
		foreach ($list as &$item) {
			$item['name'] = str_replace('/','@',$item['name']);
		}
		return $list;
	}

	public function httpPROPFIND(){
		$listFile = $this->pathList($this->path);
		$list = $this->pathListMerge($listFile);
		$pathInfo = $listFile['current'];
		if(!is_array($list) || $pathInfo['exists'] === false ){//不存在;
			return array("code" => 404,"body" => $this->errorBody('ObjectNotFound','not exist'));
		}
		if(isset($listFile['folderList'])){
			$pathInfo['type'] = 'folder';
		}		
		//只显示属性;
		$isInfo = $pathInfo['type'] == 'file' || HttpHeader::get('Depth') == '0';
		// kodbox webdav挂载获取文件夹属性;
		if( $pathInfo['type'] == 'folder' && 
			HttpHeader::get('X_DAV_ACTION') == 'infoChildren'){
			$pathInfo = IO::infoWithChildren($pathInfo['path']);
		}
		if($isInfo){
			$list = array($pathInfo);
		}else{
			$pathInfo['name'] = '';
			$list = array_merge(array($pathInfo),$list);
		}
		$out = '';
		foreach ($list as $itemFile){
			$out .= $this->parseItemXml($itemFile,$isInfo);
		}
		// write_log([$this->pathGet(),$this->path,$pathInfo],'webdav');
		$code = 207;//207 => 200;
		if(strstr($this->uri,'.xbel')){$code = 200;} // 兼容floccus

		// 扩展kod内容;
		$infoMore = array();
		$picker   = array('groupShow','pageInfo','targetSpace','listTypePhoto','listTypeSet','pageSizeArray');
		foreach ($picker as $key){
			if(array_key_exists($key,$listFile)){$infoMore[$key] = $listFile[$key];}
		}
		$infoMoreData = $infoMore ? base64_encode(json_encode($infoMore)):'';
		return array(
			"code" => $code,
			"headers" => array('X-extendFileList: '.$infoMoreData),
			"body" => "<D:multistatus xmlns:D=\"DAV:\">\n{$out}\n</D:multistatus>"
		);
	}
		
	public function httpHEAD() {
		$info = $this->pathInfo($this->path);
        if(!$info || $info['type'] == 'folder'){
        	return array(
                'code' => 200,
                'headers' => array(
                    'Content-Type: text/html; charset=utf8',
                )
            );
        }

		return array(
			'code'=> 200,
			'headers' => array(
				'Vary: Range',
                'Accept-Ranges: bytes',
				'Content-length: '.$info['size'],
                'Content-type: '.get_file_mime($info['ext']),
                'Last-Modified: '.gmdate("D, d M Y H:i:s ", $info['mtime'])."GMT",
                'Cache-Control: max-age=86400,must-revalidate',
                // 'ETag: "'.md5($info['mtime'].$info['path']).'"',
            )
		);
	}
	public function httpOPTIONS() {
		return array(
            'code'	  => 200,
            'headers' => array(
                'DAV: 1, 2, 3, extended-kodbox',
                'MS-Author-Via: DAV',
                'Allow: OPTIONS, PROPFIND, PROPPATCH, MKCOL, GET, PUT, DELETE, COPY, MOVE, LOCK, UNLOCK, HEAD',
				'Accept-Ranges: bytes',
                'Content-Length: 0',
            )
        );
	}
	public function httpPROPPATCH(){
		$out = '
		<D:response>
			<D:href>'.$_SERVER['REQUEST_URI'].'</D:href>
			<D:propstat>
				<D:prop>
					<m:Win32LastAccessTime xmlns:m="urn:schemas-microsoft-com:" />
					<m:Win32CreationTime xmlns:m="urn:schemas-microsoft-com:" />
					<m:Win32LastModifiedTime xmlns:m="urn:schemas-microsoft-com:" />
					<m:Win32FileAttributes xmlns:m="urn:schemas-microsoft-com:" />
				</D:prop>
				<D:status>HTTP/1.1 200 OK</D:status>
			</D:propstat>
		</D:response>';
		return array(
			"code" => 207,
			"body" => "<D:multistatus xmlns:D=\"DAV:\">\n{$out}\n</D:multistatus>"
		);
	}
	
	public function httpGET() {
		$this->pathOut($this->path);
	}
	// 分片支持; X-Expected-Entity-Length
	public function httpPUT() {
		$tempFile = $this->uploadFile();
		if($tempFile){
			$code = 204; 
		}else{
		    $tempFile = '';
			$code = 201;
		}
		$result = $this->pathPut($this->path,$tempFile);
		if($result == false){$code = 404;}
		return array("code"=>$code);
	}
	
	// 兼容move_uploaded_file 和 流的方式上传
	private function uploadFile(){
		@mk_dir(TEMP_FILES);
		$dest 	= TEMP_FILES.'upload_dav_'.rand_string(32);
		$outFp 	= @fopen($dest, "wb");
		$in  	= @fopen("php://input","rb");
		if(!$in || !$outFp){
			@unlink($dest);return false;
		} 	
		while (!feof($in)) {
			fwrite($outFp, fread($in, 1024*200));
		}
		fclose($in);fclose($outFp);
		if(@filesize($dest) > 0) return $dest;
		@unlink($dest);return false;
	}

	
	/**
	 * 新建文件夹
	 */
	public function httpMKCOL() {
		if ($this->pathExists($this->path)) {
            return array('code' => 409);
        }
        $res  = $this->pathMkdir($this->path);
        return array('code' => $res?201:403);
	}

	public function httpMOVE() {
		$dest    = $this->parsePath($this->pathGet(true));
		if (isset($_SERVER["HTTP_OVERWRITE"])) {
            $options["overwrite"] = $_SERVER["HTTP_OVERWRITE"] == "T";
        }
		$res 	= $this->pathMove($this->path,$dest);
		return array('code' => $res?201:404);
	}
	public function httpCOPY() {
		$dest   = $this->parsePath($this->pathGet(true));
		$res 	= $this->pathCopy($this->path,$dest);
		return array('code' => $res?201:404);
	}
	public function httpDELETE() {
		$res = $this->pathRemove($this->path);
        return array('code' => $res?200:503);
	}
	public function httpLOCK() {
		$token = md5($this->path);
		$lockInfo = '<D:prop xmlns:d="DAV:">
			<D:lockdiscovery>
				<D:activelock>
					<D:locktype><d:write/></D:locktype>
					<D:lockscope><d:exclusive/></D:lockscope>
					<D:depth>infinity</D:depth>
					<D:owner>'.$this->xmlGet('lockinfo/owner/href').'</D:owner>
					<D:timeout>Infinite</D:timeout>
					<D:locktoken><D:href>opaquelocktoken:'.$token.'</D:href></D:locktoken>
				</D:activelock>
			</D:lockdiscovery>
		</D:prop>';
		return array(
            'code' => 200,
            'headers' => array(
				'Lock-Token: '.$token,
				'Connection: keep-alive',
			),
			'body' => $lockInfo,
        );
	}
	public function httpUNLOCK() {
		return array('code' => 204);
	}

	public function xmlGet($key){
		static $xml = false;
		if(!$xml){
			// 禁用xml实体,避免xxe攻击; php8以上已废弃
			if(PHP_VERSION_ID < 80000) {libxml_disable_entity_loader(true);}
			$body = file_get_contents('php://input');
			if(!$body) return '';
			$xml = new DOMDocument();
			$xml->loadXML($body);
		}
		
        $tag = array_shift(explode('/', $key));
		$objData = $xml->getElementsByTagNameNS('DAV:', $tag);
		if($objData) return $objData[0]->nodeValue;
		return '';
	}
	
	public function getLastError(){return $this->lastError;}
	public function errorBody($title='',$desc=''){
		if(!$desc){$desc = $this->getLastError();}
		return 
		'<D:error xmlns:D="DAV:" xmlns:S="http://kodcloud.com">
			<S:exception>'.htmlentities($title).'</S:exception>
			<S:message>'.htmlentities($desc).'</S:message>
		</D:error>';
	}
	
	/**
    * 输出应答信息
    * @param array $data [header:array,code:int,body:string]
    */
    public function response($data) {
        $headers   = is_array($data['headers']) ? $data['headers'] :array();
		$headers[] = HttpHeader::code($data['code']);
		$headers[] = 'Pragma: no-cache';
		$headers[] = 'Cache-Control: no-cache';
		$headers[] = 'X-DAV-BY: kodbox';
        foreach ($headers as $header) {
            header($header);
        }
		
		if($data['code'] >= 400 && !$data['body']){
			$data['body'] = $this->errorBody();
		}
		// write_log(array($_SERVER['REQUEST_URI'],$header,$GLOBALS['_SERVER']),'webdav');
        if (is_string($data['body'])) {
        	header('Content-Type: text/xml; charset=UTF-8');
        	echo '<?xml version="1.0" encoding="utf-8"?>'."\n".$data['body'];
        }
	}
}