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
		$db = $this->config['database'];// 文件文件夹名emoji是否支持处理;
		if(!isset($db['DB_CHARSET']) || $db['DB_CHARSET'] != 'utf8mb4'){
			$path = preg_replace_callback('/./u',function($match){return strlen($match[0]) >= 4 ? '-':$match[0];},$path);
		}
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
		if(!IO::exist($savePath)) show_json(LNG('explorer.upload.errorPath'),false);
		if( $this->in['fullPath']){//带文件夹的上传
			$fullPath = KodIO::clear($this->in['fullPath']);
			$fullPath = $this->pathAllowReplace($fullPath);
			$fullPath = get_path_father($fullPath);
			$savePath = IO::mkdir(rtrim($savePath,'/').'/'.$fullPath);
		}
		$repeat = Model('UserOption')->get('fileRepeat');
		$repeat = isset($this->in['fileRepeat']) ? $this->in['fileRepeat'] : $repeat;
		$repeat = isset($this->in['repeatType']) ? $this->in['repeatType'] : $repeat; // 向下兼容pc客户端
		
		// 上传前同名文件处理(默认覆盖; [覆盖,重命名,跳过])
		$uploader->fileName = $this->pathAllowReplace($uploader->fileName);
		if($repeat == REPEAT_RENAME){
			$uploader->fileName = IO::fileNameAuto($savePath,$uploader->fileName,$repeat);
			if(!$uploader->fileName){show_json('skiped',true);}
		}
		$savePath = rtrim($savePath,'/').'/'.$uploader->fileName;
		
		// 文件保存; 必须已经先存在;
		if($this->in['fileSave'] == '1'){
			$repeat = REPEAT_REPLACE;
			$info   = IO::info($this->in['path']);
			if(!$info){
				show_json(LNG("common.pathNotExists"),false);
			}
			
			$this->in['name'] = $info['name'];
			$uploader->fileName = $this->pathAllowReplace($info['name']);
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
			show_json(IO::getLastError(LNG("explorer.upload.error")),false);
		}
	}
	private function uploadInfo($path){
		$info = IO::info($path);
		// 记录文件本身最后修改时间;
		if($info && $this->in['modifyTime']){
			$modifyTime = abs(intval(substr($this->in['modifyTime'],0,10)));
			if($modifyTime > 1000 && $modifyTime < time()){
				IO::setModifyTime($path,$modifyTime);
			}
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
			$ioFileDriver = KodIO::ioFileDriverGet($inPath);
			$path = '{io:'.$ioFileDriver['id'].'}/'.$inPath;
		}else{
			$pathBase = substr($inPath, 0, stripos($inPath, '/'));
			$path = (!$pathBase ? $inPath : $pathBase) . '/' . $inPath;
		}
		$paramMore = $this->getParamMore();
		$result = IO::uploadMultiAuth($path, $paramMore);
		show_json($result, true);
	}

	// 获取paramMore，兼容json和数组
	private function getParamMore(){
		if(!isset($this->in['paramMore'])) return array();
		$paramMore = $this->in['paramMore'];
		if (!is_array($paramMore)) {
			$paramMore = json_decode($paramMore, true);
			if (!$paramMore) return array();
		}
		// 兼容旧版 ext:?partNumber=1&uploadId=xxx
		if (!empty($paramMore['ext'])) {
			$query = parse_url_query($paramMore['ext']);
			if (is_array($query)) $paramMore = array_merge($paramMore, $query);	// partNumber, uploadId
			// unset($paramMore['ext']);
		}
		return $paramMore;
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
		
		if(!$file['hashMd5']){$file['hashSimple'] = null;}
		$checkChunkArray = array();
		if($hashSimple){$checkChunkArray = $uploader->checkChunk();} // 断点续传保持处理;
		
		$ioFileDriver = KodIO::ioFileDriverGet($savePath);
		$infoData = array(
			"checkChunkArray"	=> $checkChunkArray,
			"checkFileHash"		=> array(
				"hashSimple"=>$file['hashSimple'],
				"hashMd5"	=>$file['hashMd5']
			),
			"uploadLinkInfo"	=> IO::uploadLink($savePath, $size),//前端上传信息获取;
			"uploadToKod"		=> $isSource,
			"uploadChunkSize"	=> $this->config['settings']['upload']['chunkSize'],
			"kodDriverType"		=> $ioFileDriver['driver'],
		);
		$linkInfo = &$infoData['uploadLinkInfo'];
		// trace_log(['fileUploadCheckExist',$savePath,$linkInfo,$ioFileDriver['name'].':'.$ioFileDriver['name']]);
		if(isset($linkInfo['host'])){ // 前端上传时,自适应处理(避免http,https混合时浏览器拦截问题; )
		    $linkInfo['host'] = str_replace("http://",'//',$linkInfo['host']);
			// $linkInfo['host'] = str_replace("https://",'//',$linkInfo['host']);	// 存储只限https访问时去掉会有异常
		}
		$this->checkAllowUploadWeb($infoData);
		
		// 保留参数部分; kod挂载kod的webdav前端上传;
		if($this->in['addUploadParam']){$infoData['addUploadParam'] = $this->in['addUploadParam'];} // server;
		if($linkInfo['webdavUploadTo']){$infoData = $linkInfo;}	// webdav client 首次检测中转访问;
		
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
	
	// 检测, 是否允许前端对象存储直传(腾讯cos+Android浏览器form分片上传时,)
	private function checkAllowUploadWeb(&$infoData){
		if(!$infoData['uploadLinkInfo']){return;}
		// if(stristr($_SERVER['HTTP_USER_AGENT'],'android')){$infoData['uploadLinkInfo'] = false;}
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
		$remotePath = $this->parsePath(KodIO::ioFileDriverGet($savePath),$this->in['key']);

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
		return;// 暂时关闭该特性;
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