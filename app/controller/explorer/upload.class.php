<?php
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/

class explorerUpload extends Controller{
	public function __construct(){
		parent::__construct();
		$this->model = Model("Source");
	}
	public function pathAllowReplace($path){
		$notAllow = array('\\', ':', '*', '?', '"', '<', '>', '|',"\r","\n");//不允许字符
		return str_replace($notAllow,'_',$path);
	}
	
	
	// 通过上传内容获得上传临时文件;(插件;文件编辑保存)
	public function fileUploadTemp(){
		$this->in["chunkSize"] = '0';
		$this->in["size"] = '0';
		
		$uploader  = new Uploader();
		$localFile = $uploader->upload();
		$uploader->statusSet(false);
		return $localFile;
	}
	
	/**
	 * 上传,三个阶段
	 * checkMd5:上传前;秒传处理、前端上传处理
	 * uploadLinkSuccess: 前端上传完成处理;
	 * 其他: 正常通过后端上传上传到后端;
	 */
	public function fileUpload(){
		$this->authorizeCheck();
		$uploader = new Uploader();
		$savePath = $this->in['path'];
		if ( $this->in['fullPath'] ) {//带文件夹的上传
			$fullPath = KodIO::clear($this->in['fullPath']);
			$fullPath = $this->pathAllowReplace($fullPath);
			$fullPath = get_path_father($fullPath);
			$savePath = IO::mkdir(rtrim($savePath,'/').'/'.$fullPath);
		}
		$uploader->fileName = $this->pathAllowReplace($uploader->fileName);
		$savePath = rtrim($savePath,'/').'/'.$uploader->fileName;
		$repeat = Model('UserOption')->get('fileRepeat');
		$repeat = isset($this->in['repeatType']) ? $this->in['repeatType'] : $repeat;
		
		// 文件保存; 必须已经先存在;
		if($this->in['fileSave'] == '1'){
			$repeat = REPEAT_REPLACE;
			$info   = IO::info($this->in['path']);
			if(!$info){
				show_json(LNG("common.pathNotExists"),false);
			}
			$parent = IO::pathFather($info['path']);
			if(!$parent){show_json(LNG("common.pathNotExists"),false);}
			$savePath = rtrim($parent,'/').'/'.$info['name'];// 重新构造路径 父目录+文件名;
		}
		
		// 第三方存储上传完成
		if( isset($this->in['uploadLinkSuccess']) ){
			$this->fileUploadByClient($savePath,$repeat);
		}
		if( isset($this->in['checkType']) ){
			$this->fileUploadCheckExist($uploader,$savePath,$repeat);
		}
		
		// 通过服务器上传;
		$localFile = $uploader->upload();
		$path = IO::upload($savePath,$localFile,true,$repeat);//本地到本地则用move的方式;
		$uploader->clearData();//清空上传临时文件;
		// pr($localFile,$path,$savePath,$uploader,$this->in);exit;
		if($path){
			show_json(LNG("explorer.upload.success"),true,$this->uploadInfo($path));
		}else{
			show_json(LNG("explorer.upload.error"),false);
		}
	}
	private function uploadInfo($path){
		$info = IO::info($path);
		// 记录文件本身最后修改时间;
		if($info && $this->in['modifyTime']){
			IO::setModifyTime($path,substr($this->in['modifyTime'],0,10));
		}
		
		if($this->in['fileInfo'] != '1') return $path;
		$info = array_field_key($info,array("ext",'name','createTime','size','path','pathDisplay'));
		$info['downloadPath'] = Action('explorer.share')->link($path);
		return $info;
	}
	
	
	// 第三方上传获取凭证
	private function authorizeCheck(){
		if( !isset($this->in['authorize']) ) return;
		$inPath = $this->in['path'];
		if(substr(IO::getType($inPath), 0, 2) == 'db'){
			$path = KodIO::defaultIO().$inPath;
		}else{
			$pathBase = substr($inPath, 0, stripos($inPath, '/'));
			$path = (!$pathBase ? $inPath : $pathBase) . '/' . $inPath;
		}
		$paramMore = $this->getParamMore();
		$result = IO::multiUploadAuthData($path, $paramMore);
		show_json($result, true);
	}

	// 获取paramMore，兼容json和数组
	private function getParamMore(){
		if(!isset($this->in['paramMore'])) return array();
		if(is_array($this->in['paramMore'])) return $this->in['paramMore'];
		if($paramMore = json_decode($this->in['paramMore'], true)) return $paramMore;
		return array();
	}

	//秒传及断点续传处理
	private function fileUploadCheckExist($uploader,$savePath,$repeat){
		$size = $this->in['size'];
		$isSource   = false;
		$hashSimple = isset($this->in['checkHashSimple']) ? $this->in['checkHashSimple']:false;
		$hashMd5    = isset($this->in['checkHashMd5']) ? $this->in['checkHashMd5']:false;
		if(substr(IO::getType($savePath), 0, 2) == 'db' && $hashSimple ){
			$isSource = true;
			$file = Model("File")->findByHash($hashSimple,$hashMd5);
		}else{
			$file = array('hashSimple' => null, 'hashMd5' => null);	// 非绑定数据库存储不检查秒传
		}
		
		$default  = KodIO::defaultDriver();
		$infoData = array(
			"checkChunkArray"	=> $uploader->checkChunk(),
			"checkFileHash"		=> array(
				"hashSimple"=>$file['hashSimple'],
				"hashMd5"	=>$file['hashMd5']
			),
			"uploadLinkInfo"	=> IO::uploadLink($savePath, $size),//前端上传信息获取;
			"uploadToKod"		=> $isSource,
			"kodDriverType"		=> $default['driver'],
		);
		$linkInfo = &$infoData['uploadLinkInfo'];
		if(isset($linkInfo['host'])){
		    $linkInfo['host'] = str_replace("http://",'//',$linkInfo['host']);
		}
		
		if( $this->in['checkType'] == 'matchMd5' && 
			!empty($this->in['checkHashMd5']) && 
			!empty($file['hashMd5']) && 
			$this->in['checkHashMd5'] == $file['hashMd5']
		){
			$path = IO::uploadFileByID($savePath,$file['fileID'],$repeat);
			$uploader->clearData();//清空上传临时文件;
			show_json(LNG('explorer.upload.secPassSuccess'),true,$this->uploadInfo($path));
		}else{
			show_json(LNG('explorer.success'),true,$infoData);
		}
	}

	/**
	 * 前端上传,完成后记录并处理;
	 * 
	 * $key是完整路径，type为DB（即为默认io）时，$savePath={source:x}/$key，
	 * 获取默认io判断：{io:n}/$key
	 * 否则，$savePath={io:x}/$key，直接判断
	 */
	private function fileUploadByClient($savePath,$repeat){
		$paramMore  = $this->getParamMore();
		$remotePath = $this->parsePath(KodIO::defaultDriver(),$this->in['key']);

		// 耗时操作;
		if(!IO::exist($remotePath)){
			show_json(LNG("explorer.upload.error"), false);
		}
		$path = IO::addFileByRemote($savePath, $remotePath,$paramMore,$this->in['name'],$repeat);
		show_json(LNG("explorer.upload.success"),true,$this->uploadInfo($path));
	}
	private function parsePath($driver,$path){
		$bucket		= isset($driver['config']['bucket']) ? $driver['config']['bucket'].'/':'';
		$pathBase	= trim($driver['config']['basePath'], '/');
	    $pathPre	= $bucket.$pathBase;
	    if(substr($path,0,strlen($pathPre)) == $pathPre){
	        $path = substr($path,strlen($pathPre));
	    }else if(!empty($pathBase) && substr($path,0,strlen($pathBase)) == $pathBase){
			$path = substr($path,strlen($pathBase));
		}
	    $remotePath = '{io:'.$driver['id'].'}/'.trim($path, '/');
	    return $remotePath;
	}
	
	// 远程下载
	public function serverDownload() {
		if(!$this->in['uuid']){
			$this->in['uuid'] = md5($this->in['url']);
		}
		$uuid = 'download_'.$this->in['uuid'];
		$this->serverDownloadCheck($uuid);
		$url 	= $this->in['url'];
		$savePath = rtrim($this->in['path'],'/').'/';
		$header = url_header($url);
		if (!$header){
			show_json(LNG('download_error_exists'),false);
		}

		$filename = _get($this->in,'name',$header['name']);
		$filename = unzip_filter_ext($filename);
		$tempFile = TEMP_FILES.md5($uuid);
		mk_dir(TEMP_FILES);
		Session::set($uuid,array(
			'supportRange'	=> $header['supportRange'],
			'length'		=> $header['length'],
			'path'			=> $tempFile,
			'name'			=> $filename,
		));
		$this->serverDownloadHashCheck($url,$header,$savePath,$filename,$uuid);
		$result = Downloader::start($url,$tempFile);
		if($result['code']){
			$outPath = IO::copy($tempFile,$savePath,REPEAT_RENAME);
			$fileName= IO::fileNameAuto($savePath,$filename,REPEAT_RENAME);
			$outPath = IO::rename($outPath,$fileName);
			show_json(LNG('explorer.downloaded'),true,IO::info($outPath));
		}else{
			show_json($result['data'],false);
		}
	}

	/**
	 * 远程下载秒传处理;
	 * 小于10M的文件不处理;
	 */
	private function serverDownloadHashCheck($url,$header,$savePath,$filename,$uuid){
		if($header['length'] < 10 * 1024*1024) return false;
		$driver = new PathDriverUrl();
		$fileHash = $driver->hashSimple($url,$header); // 50个请求;8s左右;
		$file = Model("File")->findByHash($fileHash);
		if(!$fileHash || !$file) return;
		
		$tempFile = $file['path'];
		Session::remove($uuid);
		$outPath = IO::copy($tempFile,$savePath,REPEAT_RENAME);
		$fileName= IO::fileNameAuto($savePath,$filename,REPEAT_RENAME);
		$outPath = IO::rename($outPath,$fileName);
		show_json(LNG('explorer.upload.secPassSuccess'),true,IO::info($outPath));
	}
	
	
	private function serverDownloadCheck($uuid){
		$data = Session::get($uuid);
		if ($this->in['type'] == 'percent') {
			if (!$data) show_json('uuid error',false);
			$result = array(
				'supportRange' => $data['supportRange'],
				'uuid'      => $this->in['uuid'],
				'length'    => intval($data['length']),
				'name'		=> $data['name'],
				'size'      => intval(@filesize($data['path'].'.downloading')),
				'time'      => mtime()
			);
			show_json($result);
		}else if($this->in['type'] == 'remove'){//取消下载;文件被删掉则自动停止
			if($data){
				IO::remove($data['path'].'.downloading');
				IO::remove($data['path'].'.download.cfg');
				Session::remove($uuid);
			}
			show_json('');
		}
	}
}