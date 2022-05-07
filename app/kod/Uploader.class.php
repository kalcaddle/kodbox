<?php
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/

class Uploader{
	public $fileName;
	public $uploadFile;
	public $tempFile;

	public function __construct(){
		global $in;
		$this->in = &$in;
		if (!empty($_FILES)) {
			$files  = $_FILES['file'];
			$this->uploadFile = $files["tmp_name"];
			if( !$this->uploadFile && $files['error']> 0){
				show_json($this->errorInfo($files['error']),false);
			}
		}else if (isset($in["name"])) {
			$this->uploadFile = isset($in['base64Upload'])? 'base64':"php://input";
		}

		$this->fileName = self::fileName();
		$this->statusData = false;
		$this->checkSize();
		$this->tempPathInit();
	}

	/**
	 * 文件上传处理。大文件支持分片上传
	 * post上传：base64Upload=1;file=base64str;name=filename
	 * 
	 * chunk,chunks; 选填; 没有或chunks小于等于1则认为不分片;
	 * 分片则 size 必须传入
	 */
	public function upload(){
		$chunk  = isset($this->in["chunk"]) ? intval($this->in["chunk"]) : 0;
		$chunks = isset($this->in["chunks"]) ? intval($this->in["chunks"]) : 1;
		$dest   = $this->tempFile.'.part'.$chunk;
		$size   = isset($this->in["size"]) ? intval($this->in["size"]) : 0;
		$chunkSize  = isset($this->in["chunkSize"]) ? intval($this->in["chunkSize"]) : 0;
		if(	$chunks > 1 && $chunkSize <= 0 ){
			show_json('chunkSize error!',false);
		}
		
		if($chunkSize > $size){
		    $chunks = 1;
		}
		if ($chunks <= 1) {// 没有分片;
			// 清除秒传or断点续传请求checkChunk写入的数据;
			$this->tempFile = $this->tempFile.rand_string(5);
			$this->statusSet(false);
			return $this->moveUploadedFile($this->tempFile);
		}
		
		//分片上传必须携带文件大小,及分片切分大小;
		CacheLock::lock($this->tempFile,30);
		$data = $this->statusGet();
		$this->initFileTemp();
		$chunkFile = $this->moveUploadedFile($dest);
		if($size > 0 && filesize($chunkFile) == 0){
			$this->showJson('0 Byte upload',false);
		}
		if(!$chunkFile) $this->showJson(LNG('explorer.moveError'),false);
		
		$offset = $chunk * $chunkSize;
		if(!$outFp = @fopen($this->tempFile, "r+")){
			$this->showJson('fopen file error:'.$this->tempFile,false);
		}
		fseek_64($outFp,$offset);
		$success = $this->writeTo($chunkFile,$outFp,$this->tempFile);
		$hash    = IO::hashSimple($chunkFile);
		$size    = IO::size($chunkFile);
		@unlink($chunkFile);
		if(!$success){
			$this->showJson('chunk_error:'.$chunk,false);
		}
		
		//分片成功:
		$data['chunkTotal'] = $chunks;
		$data['chunkArray']['chunk'.$chunk] = array(
			'offset'	=> $offset,
			'index' 	=> $chunk,
			'size'		=> $size,
			'hashSimple'=> $hash,
		);
		$this->statusSet($data);
		if ( count($data['chunkArray']) != $data['chunkTotal'] ){
			$this->showJson('chunk_success_'.$chunk,true);
		}
		// 所有分片完成,检测分片hash值一致性;
		ignore_timeout();
		if( !$this->checkChunkHash($data) ){
			$this->showJson("hash error!",false);
		}
		$this->statusSet(false);//上传成功,清空相关配置;
		CacheLock::unlock($this->tempFile);
		return $this->tempFile;
	}
	
	private function checkSize(){
		if(phpBuild64() || $this->in['size'] < PHP_INT_MAX) return;
		show_json(LNG('explorer.uploadSizeError'),false);
	}
	private function showJson($data,$code){
		CacheLock::unlock($this->tempFile);
		show_json($data,$code);
	}
	
	public function clearData(){
		$this->statusSet(false);
		if(file_exists($this->tempFile)){
			@unlink($this->tempFile);return;
		}
	}
	
	// 临时文件;
	private function tempPathInit(){
		$tempPath = TEMP_FILES;
		@mk_dir($tempPath);
		touch($tempPath.'index.html');
		$tempName = isset($this->in['checkHashSimple']) ? $this->in['checkHashSimple']:false;
		if(strlen($tempName) < 30){ //32+大小;
			$tempName = md5(USER_ID.$this->in['path'].$this->fileName.$this->in['size']);
		}
		$this->tempFile = $tempPath."upload_".md5($tempName.$this->in['chunkSize']);
	}
	// 兼容move_uploaded_file 和 流的方式上传
	private function moveUploadedFile($dest){
		$fromPath = $this->uploadFile;
		if($fromPath == "base64"){
			@file_put_contents($dest,base64_decode($_REQUEST['base64str']));
		}else if($fromPath == "php://input"){
			$out = @fopen($dest, "wb");
			$this->writeTo($fromPath,$out,$dest);
		}else{
			if(!move_uploaded_file($fromPath,$dest)){
				return false;
			}
		}
		return $dest;
	}

	private function writeTo($from,$outFp,$outName){
		$lockKey = 'Uploader.writeTo'.$outName;
		$isLock  = CacheLock::lock($lockKey,1); //不用文件锁;解决NFS不支持文件写入锁的问题;
		$in  = @fopen($from,"rb");
		if(!$in || !$outFp || !$isLock){
			CacheLock::unlock($lockKey);
			return false;
		}
		while (!feof($in)) {
			fwrite($outFp, fread($in, 1024*200));
		}
		fclose($in);fclose($outFp);
		CacheLock::unlock($lockKey);
		return true;
	}

	private function statusGet(){
		if( is_array($this->statusData)) return $this->statusData;
		$file = $this->tempFile.'.cfg';
		$content = false;
		if(file_exists($file)){
			$content = @file_get_contents($file);
		}
		if($content){
			$this->statusData = json_decode($content,true);
		}
		if(!$this->statusData){
			$defaultData = array(
				'name'		=> $this->fileName,
				'chunkTotal'=> 0,
				'chunkArray'=> array(), // chunk2=>{offset:xxx,index:2,hashSimple:xxx}
			);
			$this->statusSet($defaultData);
		}
		return $this->statusData;
	}	
	public function statusSet($data){
		$file = $this->tempFile.'.cfg';
		if(!$data){
			if(file_exists($file)){
				@unlink($file);
			}
			return true;
		}
		$this->statusData = $data;
		return file_put_contents($file,json_encode($data));
	}
	
	// 生成占位文件;
	private function initFileTemp(){
		if( file_exists($this->tempFile) ) return;
		if(!$fp = fopen($this->tempFile,'wb+')){
			$this->showJson('fopen file error:'.$this->tempFile,false);
		}
		fseek_64($fp,$this->in['size']-1,SEEK_SET);
		fwrite($fp,'0');
		fclose($fp);
	}
		
	//文件分块检测是否已上传，已上传则忽略；断点续传
	public function checkChunk(){
		$result = array();
		CacheLock::lock($this->tempFile);
		$data  = $this->statusGet();
		CacheLock::unlock($this->tempFile);
		foreach ($data['chunkArray'] as $item) {
			$hash = $item['hashSimple'];
			if($hash){
				$result['part_'.$item['index']] = $hash;
			}
		}
		return $result;
	}
	
	// 所有分片完成,检测simpleHash 及md5;
	private function checkChunkHash($data){
		if( count($data['chunkArray']) != $data['chunkTotal'] ){
			return false;
		}
		// html5 前端计算了md5,则对比md5;
		$fileHash = _get($this->in,'checkHashSimple');
		$fileMd5  = _get($this->in,'checkHashMd5');
		if( strlen($fileMd5) == 32 &&
			IO::hashSimple($this->tempFile) == $fileHash &&
			IO::hashMd5($this->tempFile) == $fileMd5 ){
			return true;
		}

		if(!$fp = fopen($this->tempFile,'r')) return false;
		$success = true;
		foreach ($data['chunkArray'] as $item) {
			fseek_64($fp,$item['offset']);
			$chunkHash = PathDriverStream::hash($fp,$item['size']);
			if($item['hashSimple'] != $chunkHash){
				$success = false;break;
			}
		}
		fclose($fp);
		return $success;
	}
	
	//拍照上传等情况兼容处理;
	public static function fileName(){
		global $in;
		$fileName = isset($in['name']) ? $in['name'] :'';
		if (!empty($_FILES)) {
			$fileName = $fileName ? $fileName : $_FILES['file']["name"];
		}
		if(!is_wap()) return KodIO::clear($fileName);
		
		//ios 上传没有文件名问题处理
		$time = strtotime($in['lastModifiedDate']);
		$time = $time ? $time : time();
		$beforeName = strtolower($fileName);
		if($beforeName == "image.jpg" || $beforeName == "image.jpeg"){
			$fileName =  date('Ymd',$time).'_'.$in['size'].'.jpg';
		}else if($beforeName == "capturedvideo.mov"){
			$fileName =  date('Ymd',$time).'_'.$in['size'].'.mov';
		}
		return KodIO::clear($fileName);
	}
	
	private function errorInfo($error){
		$status = array(
			'UPLOAD_ERR_OK',        //0 没有错误发生，文件上传成功。
			'UPLOAD_ERR_INI_SIZE',  //1 上传的文件超过了php.ini 中 upload_max_filesize 选项限制的值。
			'UPLOAD_ERR_FORM_SIZE', //2 上传文件的大小超过了 HTML 表单中 MAX_FILE_SIZE 选项指定的值。
			'UPLOAD_ERR_PARTIAL',   //3 文件只有部分被上传。
			'UPLOAD_ERR_NO_FILE',   //4 没有文件被上传。
			'UPLOAD_UNKNOW',		//5 未定义
			'UPLOAD_ERR_NO_TMP_DIR',//6 找不到临时文件夹。php 4.3.10 和 php 5.0.3 引进。
			'UPLOAD_ERR_CANT_WRITE',//7 文件写入失败。php 5.1.0 引进。
		);
		return $error.':'.$status[$error];
	}
}