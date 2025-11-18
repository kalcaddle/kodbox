<?php

class fileThumbPlugin extends PluginBase{
	private $imgExts;
	private $webExts;
	private $ioFileInfo;
	function __construct(){
		parent::__construct();
		$this->imgExts = array('gif','png','bmp','jpe','jpeg','jpg','webp','heic','heif','avif');
		$this->webExts = array('png','jpg','jpeg','bmp','webp','gif','avif');
	}
	public function regist(){
		$this->hookRegist(array(
			'user.commonJs.insert'  	=> 'fileThumbPlugin.echoJs',
			'explorer.list.path.parse'	=> 'fileThumbPlugin.listParse',
			'explorer.list.itemParse'	=> 'fileThumbPlugin.itemParse',
		));
	}
	public function echoJs(){
		$this->echoFile('static/main.js');
	}
	public function onUpdate(){
		AutoTask::restart();sleep(3);
		AutoTask::start();
	}
	
	/**
	 * 优化版,列表统一集中处理; 拉取列表时加入队列处理(同时加入预览大图转换); 1
	 * 总大概性能: 1000图片/s; checkCoverExists==>1000条约300ms;  缩略图处理==>1000条约200ms;Cache::get(); 10000次/s; 
	 */
	public function listParse($data){
		$current = is_array($data['current']) ? $data['current']:array();
		if(!$data || !is_array($data['fileList']) || !count($data['fileList'])){return $data;}
		if(!$this->getConvert() || !$this->getFFmpeg()){return $data;}
		if($current && $current['targetType'] == 'system' && strstr($current['pathDisplay'],'plugin/fileThumb/')){return $data;}

		$config 		= $this->getConfig('fileThumb');
		$supportWeb 	= $this->webExts;
		$supportThumb 	= explode(',',$config['fileThumb'].','.implode(',',$supportWeb));
		$supportView  	= explode(',',$config['fileExt'].','.implode(',',$supportWeb));
		$cachePath 		= false;$timeStart = timeFloat();
		
		// 遍历文件列表,筛选出需要加入缩略图及显示图的内容(支持预览的,加入fileShowView)
		$coverList = array();
		foreach($data['fileList'] as &$file){
			if(!$file['ext'] || $file['size'] <= 100){continue;}
			if(!in_array($file['ext'],$supportThumb)){continue;}
			if(!kodIO::allowCover($file)){continue;}
			if(timeFloat() - $timeStart >= 10){break;} // 内容太多,超出时间则不再处理;
			// if(in_array($file['ext'],$supportWeb) && $file['size'] <= 1024*50){continue;}
			if(!$cachePath){$cachePath = $this->pluginCachePath();}
			
			$fileHash = KodIO::hashPath($file);$path = $file['path'];
			$coverList[$path] = array('fileThumb'=>array('cover'=>"cover_".$fileHash."_250.png",'width'=>250));
			if(in_array($file['ext'],$supportView)){
				$coverList[$path]['fileShowView'] = array('cover'=>"cover_".$fileHash."_1200.png",'width'=>1200);
			}
		};unset($file);
		if(!$cachePath || !$coverList){return $data;};//return $data;

		// 处理需要加入缩略图的文件; 查询数据库已存在的缓存图片;查询redis缓存(已处理,队列中,出错了:不处理)-->未在队列--加入队列;
		$needMake  = 0;$makeIndex = 0; // 待生成缩略图数量,不包含大图;
		$coverList = $this->checkCoverExists($cachePath,$coverList,$needMake);
		$makeCoverNow = $needMake <= 3 ? true:false; // 待生成列表小于5,则缩略图调用立即生成;
		$obj = Action('user.index');
		foreach($data['fileList'] as &$file){
			if(!isset($coverList[$file['path']])){continue;}
			if(timeFloat() - $timeStart >= 10){break;} // 内容太多,超出时间则不再处理;
			
			$path = $file['path'];
			foreach($coverList[$path] as $thumbKey=>$item){
				$coverName = $item['cover'];// 少量没有缩略图的内容,立即生成处理;
				$param 	   = array('path'=>$path,'etag'=>$file['modifyTime'],'width'=>$item['width']);
				$imageSrc  = $obj->apiSignMake('plugin/fileThumb/cover',$param);
				if(isset($item['sourceID']) && $item['sourceID']){$file[$thumbKey] = $imageSrc;continue;}
				if($thumbKey == 'fileShowView'){$file[$thumbKey] = $imageSrc;}// 预览大图,不检测是否有缓存
				if($thumbKey == 'fileThumb' && $makeCoverNow){$file[$thumbKey] = $imageSrc;continue;}
				
				// 多张图片待生成缩略图时,第一张处理为触发调用后台任务队列;
				if($thumbKey == 'fileThumb'){$makeIndex++;}
				if($thumbKey == 'fileThumb' && $makeIndex <= 2){
					$file[$thumbKey] = APP_HOST.'index.php?user/view/call&_t='.rand_string(5);
				}
				$cacheType = Cache::get($coverName);
				if($cacheType == 'no' || $cacheType == 'queue'){continue;}
				
				Cache::set($coverName,'queue',1200);
				$args = array($cachePath,$path,$coverName,$item['width']);
				$desc = '[fileThumb.coverMake]:size='.$file['size'].';cover='.$coverName.';name='.$file['name'].';path='.$file['path'];
				TaskQueue::add('fileThumbPlugin.coverMake',$args,$desc,$coverName);
			}
			// jpg等图片,有该字段时,前端不自动获取图片缩略图,统一通过此插件转换;
			if(in_array($file['ext'],$supportView) && !isset($file['fileThumb'])){$file['fileThumbDisable'] = 1;}
		};unset($file);
		// trace_log([$needMake,$data,$coverList]);
		return $data;
	}
	
	// 批量查询缓存图片是否存在;
	private function checkCoverExists($cachePath,$coverList,&$needMake){
		if(!$cachePath || !count($coverList)){return $coverList;}
		$sourceID = kodIO::sourceID($cachePath);
		$coverArr = array();
		foreach($coverList as $items){
			foreach($items as $item){$coverArr[] = $item['cover'];}
		}
		$where = array('parentID'=>$sourceID,'name'=>array('in',$coverArr));
		$lists = Model("Source")->field('sourceID,name')->where($where)->select();
		$lists = array_to_keyvalue($lists,'name','sourceID');
		foreach($coverList as $i=>$items){
			foreach($items as $k=>$item){
				if(isset($lists[$item['cover']])){$coverList[$i][$k]['sourceID'] = $lists[$item['cover']];}
			}
			if(!isset($coverList[$i]['fileThumb']['sourceID'])){$needMake++;}
		}
		return $coverList;
	}

	// 单个文件属性处理;
	public function itemParse($file){
		if(strtolower(ACT) != 'pathinfo' || $file['type'] == 'folder'){return $file;}
		$data = $this->listParse(array('folderList'=>array(),'fileList'=>array($file)));
		return $data['fileList'][0];
	}

	/**
	 * 缩略图预览
	 * @return void
	 */
	public function cover(){
		$path  = $this->filePath($this->in['path'],false);
		$width = intval($this->in['width']);
		$width = ($width && $width > 1000) ? 1200:250;
		
		$file = IO::info($path);
		$fileHash  = KodIO::hashPath($file);
		$coverName = "cover_".$fileHash."_{$width}.png";
		$result = $this->coverMake($this->cachePath,$file['path'],"cover_".$fileHash."_{$width}.png",$width);
		if($width == 1200){$this->coverMake($this->cachePath,$file['path'],"cover_".$fileHash."_250.png",250);}
		$sourceID = IO::fileNameExist($this->cachePath,$coverName);
		// pr(IO::Info(kodIO::make($sourceID)));exit;
		if($sourceID){IO::fileOut(kodIO::make($sourceID));exit;}
		// 1200预览输出原图; 生成失败,浏览器支持预览的直接输出;
		if(in_array($file['ext'], $this->webExts)) {
			IO::fileOut($path);exit;
		}
		echo $result;
	}

	/**
	 * 检查服务
	 * linux 注意修改获取bin文件的权限问题;
	 * @return void
	 */
	public function check(){
		$this->checkImgnry();	// imaginary服务检查
		Cache::remove('fileThumb.getFFmpeg');
		Cache::remove('fileThumb.getConvert');
		if(isset($_GET['action']) && $_GET['action'] == 'stopAll'){
		    // 清除所有任务;
            @include_once($this->pluginPath.'lib/VideoResize.class.php');
    		@include_once($this->pluginPath.'lib/TaskConvert.class.php');
    		$video = new videoResize();
    		$video->stopAll();
    		$video->log("Success !");
    		return;
		}
		if(isset($_GET['check'])){
			$convert = $this->getConvert();
            $ffmpeg  = $this->getFFmpegFind();
            $ffmpegSupport = $ffmpeg ? $this->ffmpegSupportCheck($ffmpeg) : false;
            $result  = $convert && $ffmpeg && $ffmpegSupport;
            if($ffmpeg && !$this->ffmpegSupportCheck($ffmpeg)){$result = false;}
            $message = $result ? 'ok;': LNG('fileThumb.check.faild').'<br/>';
			if(!$result){
				$error = '';
				$check = array('shell_exec','proc_open','proc_close','exec');
				foreach ($check as $method) {
					if(!function_exists($method)){$error .= $method .' ';}
				}
				if($error){$message = $message.'['.trim($error).'] is disabled(please allow it)<br/>';}
			}
			
            if(!$convert){
                $message .= "$convert convert ".LNG('fileThumb.check.error').";<br/>";
            }
            if(!$ffmpeg){
                $message .= "$ffmpeg ffmpeg".LNG('fileThumb.check.error').";<br/>";
            }
            if($ffmpeg && !$ffmpegSupport){
                $message .= 'ffmpeg not support muxer:image2 or libx264; please install again!';
            }
            show_json($message,$result);
		}
		include($this->pluginPath.'static/check.html');
	}

	// 标清视频;
	public function videoSmall(){
		if(!$this->getFFmpeg()) return;
		@include_once($this->pluginPath.'lib/VideoResize.class.php');
		@include_once($this->pluginPath.'lib/TaskConvert.class.php');
		$video = new videoResize();
		$video->start($this);
	}
	// 视频预览图
	public function videoPreview(){
		if(!$this->getFFmpeg()) return;
		@include_once($this->pluginPath.'lib/VideoResize.class.php');
		$video = new videoResize();
		$video->videoPreview($this);
	}

	// 本地文件:local,io-local, {{kod-local}}全量生成;
	// 远端:(ftp,oss等对象存储)
	public function coverMake($cachePath,$path,$coverName,$size){
		$cckey = md5('fileThumb.conver.'.$path.$coverName.$size);
		if (Cache::get($cckey)) return;
		Cache::set($cckey, 1, 60);	// 延迟1分钟，避免重复执行（对未生成的图片预览大图时，会执行3次250x250）
		if(IO::fileNameExist($cachePath,$coverName)){return 'exists;';}
		if(!is_dir(TEMP_FILES)){mk_dir(TEMP_FILES);}

		// 清除pathInfo缓存，避免队列任务中历史版本影响
		$parse = KodIO::parse($path);
		if ($parse['type'] == KodIO::KOD_SOURCE && $parse['id']) {
			Model('Source')->sourceCacheClear($parse['id']);
		}

		$info = IO::info($path);$ext = $info['ext'];
		if ($ext == 'gif' && $size != 250) return;	// gif大图不转换，预览输出原图
		$thumbFile = TEMP_FILES . $coverName;		
		$localFile = $this->localFile($path);
		// TODO 可能不应先下载到本地，而应该先判断是否需要生成
		$movie = '3gp,avi,mp4,m4v,mov,mpg,mpeg,mpe,mts,m2ts,wmv,ogv,webm,vob,flv,f4v,mkv,rmvb,rm';
		$isVideo = in_array($ext,explode(',',$movie));
		// 过短的视频封面图,不指定时间;
		$videoThumbTime = true;
		if( $isVideo && is_array($info['fileInfoMore']) && 
			isset($info['fileInfoMore']['playtime']) &&
			floatval($info['fileInfoMore']['playtime']) <= 3 ){
			$videoThumbTime = false;
		}
		if($isVideo){
			// 不是本地文件; 切片后获取:mp4,mov,mpg,webm,f4v,ogv,avi,mkv,wmv;(部分失败)
			if(!$localFile){
				$localTemp = $thumbFile.'.'.$ext;
				$localFile = $localTemp;
				file_put_contents($localTemp,IO::fileSubstr($path,0,1024*600));	// TODO 太小时不一定能生成
			}
			$this->thumbVideo($localFile,$thumbFile,$videoThumbTime);
		} else {
			// 检查文件大小
			if (!$this->thumbSizeLimit($localFile,'size',$info['size'])) {
				return 'covert error! file size > limit;';
			}
			// 下载文件到本地
			if(!$localFile){
				// 支持imaginary的s3系文件使用url生成缩略图，省略中转下载
				$localFile = $this->localFile2Url($path,$ext);
				if (!$localFile) {
					$localFile = $localTemp = $this->pluginLocalFile($path);
				}
			}
			if($ext == 'ttf'){
				$this->thumbFont($localFile,$thumbFile,$size);
			}else{
				$this->thumbImage($localFile,$thumbFile,$size,$ext);
			}
		}
		if($localTemp){@unlink($localTemp);}
		if(@file_exists($thumbFile) && @is_file($thumbFile) && filesize($thumbFile) > 0){
			Cache::remove($coverName);
			$destFile = IO::move($thumbFile,$cachePath);
			$pathInfo = KodIO::parse($destFile);
			if($pathInfo['type'] == KodIO::KOD_SOURCE){
				Model('Source')->setDesc($pathInfo['id'],$path);
				//write_log(get_caller_info(),'test');
			}
			return $destFile;
		}
		Cache::set($coverName,'no',600);
		del_file($thumbFile);
		$this->log('cover=makeError:'.$localFile.';temp='.$thumbFile);
		return 'convert error! localFile='.get_path_this($localFile);
	}

	// 获取文件 hash
	public function localFile($path){
		$io = IO::init($path);
		$pathParse = KodIO::parse($path);
		if(!$pathParse['type']) return $path;
		if(is_array($io->pathParse) && isset($io->pathParse['truePath'])){ //协作分享处理;
			if(file_exists($io->pathParse['truePath'])) return $io->pathParse['truePath'];
			return false;
		}
		
		$fileInfo = IO::info($path);
		if($fileInfo['fileID']){
			$tempInfo 	= Model('File')->fileInfo($fileInfo['fileID']);
			$fileInfo 	= IO::info($tempInfo['path']);
			$pathParse 	= KodIO::parse($tempInfo['path']);
		}
		$parent = array('path'=>'{userFav}/');
		$fileInfo = Action('explorer.listDriver')->parsePathIO($fileInfo,$parent);
		$this->ioFileInfo = array(
			'path'		=> $path, 
			'ioDriver'	=> strtolower($fileInfo['ioDriver'])
		);
		if($fileInfo['ioDriver'] == 'Local' && $fileInfo['ioBasePath']){
			$base = rtrim($fileInfo['ioBasePath'],'/');
			if(substr($base,0,2) == './') {
				$base = substr_replace($base, BASIC_PATH, 0, 2);
			}
			return $base . '/' . ltrim($pathParse['param'], '/');
		}
		return false;
	}
	// s3系对象存储，通过imaginary用文件url生成缩略图
	private function localFile2Url($path,$ext){
		if (request_url_safe($path)) return false;
		if (!in_array($this->ioFileInfo['ioDriver'], array('s3','eds','eos','minio','oos'))) return false;
		// 是否支持imaginary
		$api = $this->getImgnryApi($ext);
		if (!$api) return false;
		return Action('explorer.share')->link($path);
	}
	
	// 缩略图：字体
	private function thumbFont($fontFile,$cacheFile,$maxSize){
		$textMore = '
		可道云在线云盘
		汉体书写信息技术标准相容
		档案下载使用界面简单
		支援服务升级资讯专业制作
		创意空间快速无线上网
		AaBbCc 0123456789ＡａＢｂＣｃ
		 ㈠㈡㈢㈣㈤㈥㈦㈧㈨㈩';
		$textMore = str_replace("\t",' ',$textMore);
		$text = "字体ABC";$size = 200;
		if($maxSize >= 500){
			$text = $textMore;
			$size = 800;
		}
		$im = imagecreatetruecolor($size,$size);
		imagefill($im,0,0,imagecolorallocate($im,255,255,255));
		$color = imagecolorallocate($im,10,10,10);
		imagefttext($im,32,0,10,100,$color,$fontFile,$text);
		imagejpeg($im,$cacheFile);//生成图片     
		imagedestroy($im);
	}

	// 缩略图：视频
	private function thumbVideo($file,$cacheFile,$videoThumbTime){
		$api = $this->getImgmgkApi('ffmpeg');
		if ($api) {
			$res = $api->createThumbVideo($file,$cacheFile,$videoThumbTime);
			if ($res) return true;
		}
		// 生成失败，尝试调用OSS直链生成
		$this->thumbVideoByLink($cacheFile);
	}
	// 对象存储通过链接获取缩略图——当前仅支持OSS
	// https://help.aliyun.com/zh/oss/user-guide/video-snapshots?
	private function thumbVideoByLink($cacheFile) {
		$driver = _get($this->ioFileInfo, 'ioDriver', '');
		if ($driver != 'oss') return false;
		$path = $this->ioFileInfo['path'];
		switch ($driver) {
			case 'oss':
				// 费用：截帧数*(0.1/1k)/1000
				$options = array('x-oss-process' => 'video/snapshot,t_10000,f_jpg,w_250,m_fast');
				$link = IO::link($path, $options);
				break;
		}
		if (!$link) return;

		// 写入文件——视频缩略图收费，写入文件，避免反复调用
		$content = curl_get_contents($link);
		if (!$content) return;
		file_put_contents($cacheFile, $content);
		return @file_exists($cacheFile) ? true : false;
	}

	// 缩略图：图片
	private function thumbImage($file,$cacheFile,$maxSize,$ext){
		// 1.使用imaginary接口
		$api = $this->getImgnryApi($ext);
		if ($api) return $api->createThumb($file,$cacheFile,$maxSize,$ext);
		// 检查图片大小，提前拦截——imagick执行过程中不受PHP内存限制，也拦截
		$isImg = false;
		if (in_array($ext, $this->imgExts)) {
			$isImg = true;
			if (!$this->thumbSizeLimit($file)) return;
		}
		// 2.使用imagick扩展
		$api = $this->getImgickApi($ext);
		if ($api) return $api->createThumb($file,$cacheFile,$maxSize,$ext);
		// 3.使用ImageMagick
		$api = $this->getImgmgkApi();
		if ($api) $api->createThumb($file,$cacheFile,$maxSize,$ext);
		if ($isImg) return;
		// 生成的封面图，再用gd生成缩略图
		ImageThumb::createThumb($cacheFile,$cacheFile,$maxSize,$maxSize);
	}

	// 获取convert/ffmpeg命令
	public function getCommand($type = 'convert') {
		if ($type == 'convert') {
			$command = $this->getConvert();
		} else {
			$command = $this->getFFmpeg();
		}
		if ($command) return $command;
		echo ucfirst($type).' '.LNG("fileThumb.check.notFound");
		return false;
	}
	public function getFFmpeg(){
		return $this->getCall('fileThumb.getFFmpeg',600,array($this,'getFFmpegNow'));
	}
	public function getConvert(){
		return $this->getCall('fileThumb.getConvert',600,array($this,'getConvertNow'));
	}
	// Cache::getCall
	private function getCall($key,$timeout,$call,$args = array()){
		$result = Cache::get($key);
		if($result || $result === '') return $result;
		
		$result = call_user_func_array($call,$args);
		$result = $result ? $result : '';
		Cache::set($key,$result,$timeout);
		return $result;
	}
	
	public function ffmpegSupportCheck($ffmpeg){
        $out = shell_exec($ffmpeg.' -v 2>&1');
        if(strstr($out,'--disable-muxer=image2')){
			$this->log('ffmpeg support error. '.$out);
			return false;
		}
        return true;
    }
    public function getFFmpegNow(){
        $ffmpeg = $this->getFFmpegFind();
        if(!$ffmpeg) return false;
        return $this->ffmpegSupportCheck($ffmpeg) ? $ffmpeg:false;
	}
	public function getFFmpegFind(){
		$check  = 'options';
		$config = $this->getConfig();
		if( $this->checkBin($config['ffmpegBin'],$check) ){
			return $config['ffmpegBin'];
		}
		$result = $this->guessBinPath('ffmpeg',$check);
		if($result){return $result;}
		$findMore = array(
			'/imagemagick/ffmpeg',
			'/imagemagick/bin/ffmpeg',
			'/ImageMagick/ffmpeg.exe',
			'/ImageMagick-7.0.7-Q8/ffmpeg.exe',
		);
		foreach ($findMore as $value) {
			$result = $this->guessBinPath($value,$check);
			if($result){return $result;}
		}
		return false;
	}
	public function getConvertNow(){
		$check  = 'options';
		$config = $this->getConfig();
		if( $this->checkBin($config['imagickBin'],$check) ){
			return $config['imagickBin'];
		}
		$result = $this->guessBinPath('convert',$check);
		if($result){return $result;}
		$findMore = array(
			'/imagemagick/convert',
			'/imagemagick/bin/convert',
			'/ImageMagick/convert.exe',
			'/ImageMagick-7.0.7-Q8/convert.exe',
		);
		foreach ($findMore as $value) {
			$result = $this->guessBinPath($value,$check);
			if($result){return $result;}
		}
		return false;
	}

	/**
	 * 查找可执行文件命令
	 * https://github.com/taligentx/ee204/blob/master/admin/SCRIPT_server_tools_pathguess.php
	 * @param  [type] $bin   命令文件
	 * @param  [type] $check 找到文件后执行,结果中匹配的字符串
	 * @return [type]        可执行命令路径
	 */
	private function guessBinPath($bin,$check){
		$array = array(
			"/bin/",
			"/usr/local/bin/",
			"/usr/bin/",
			"/usr/sbin/",
			"/usr/local/",
			"/local/bin/",
			"C:/Program Files/",
			"C:/Program Files (x86)/",
			"C:/",
		);
		$findArray = array();
		foreach ($array as $value) {
			if(file_exists($value.$bin)){
				$file = $value.$bin;
				if(strstr($file,' ')){
					$file = '"'.$file.'"';
				}
				$findArray[] = $file;
			}
		}
		if(!strstr($bin,'/')){
			$findArray[] = $bin;
		}
		if(count($findArray) > 0){
			foreach ($findArray as $file) {
				if( $this->checkBin($file,$check) ){
					//var_dump($file,$findArray,$check,shell_exec($file.' --help'));exit;
					return $file;
				}
			}
		}
		return false;
	}
	private function checkBin($bin,$check){
		if(!function_exists('shell_exec')) {
			$this->log('shell_exec function is disabled.');
			return false;
		}
		$result = shell_exec($bin.' --help');
		if (stripos($result,$check) > 0) return true;
		
		$out = shell_exec($bin.' --help 2>&1');
		$this->log('imagick env error:'.$out.';cmd='.$bin.' --help 2>&1');
		return false;
	}

	// 调试模式
	public function log($log, $cmd = ''){
		// $config = $this->getConfig();
		//if(!$config['debug']) return;
		write_log($log,'fileThumb');
	}

	// 根据文件大小检查是否支持生成缩略图
	private function thumbSizeLimit($file, $type='pixel', $size=0) {
		// 1.按文件大小限制——主要是Imagick/ImageMagick，imaginary可以在容器限制，暂时统一拦截
		if ($type == 'size') {
			$config = $this->getConfig();
			$sizeLimit = intval(_get($config, 'thumbSizeLimit', 50));
			if ($size < 1024*1024*$sizeLimit) {
				return true;
			}
			$this->log('cover=makeError:'.$file.'; file size too large: '.$size);
			return false;
		}
		// 2.按像素计算大小限制
		$memNeed = $this->sysMemoryNeed($file);
		if(!$memNeed){return true;}
		
		$memFree = $this->sysMemoryFree();
		if(!$memFree || ($memNeed < $memFree)){return true;}
		$this->log('cover=makeError:'.$file.'; need memory too large: '.$size);
		return false;
	}
	// 系统可用内存——不一定能获取到
	public function sysMemoryFree() {
		$server = new ServerInfo();
		$memUsage = $server->memUsage();
		return intval($memUsage['total'] - $memUsage['used']);
	}
	// （命令）内存限制参数
	public function memLimitParam($type = 'ffmpeg', $param = '') {
		$memBase = 128 * 1024 * 1024; // 128M，最小内存限制——实际可用内存可能更小，暂不处理
		$memFree = $this->sysMemoryFree();
		$memFree = max($memBase, intval($memFree * 0.5));
		if ($type != 'ffmpeg') {	// convert
			$mapLimit = $memFree * 2;
			$dskLimit = $memFree * 4;
			$memLimit = $this->sizeFormat($memFree);
			$mapLimit = $this->sizeFormat($mapLimit);
			$dskLimit = $this->sizeFormat($dskLimit);
			// 限制内存后可能失败——某些步骤可能需要连续内存块‌（如解码、色彩空间转换），内存过小无法完成初始化
			// return " -define resource:limit=true -limit memory {$memLimit} -limit map {$mapLimit} -limit disk {$dskLimit} {$param}";
			// return " -limit memory {$memLimit} -limit map {$mapLimit} -limit disk {$dskLimit} {$param}";
			if(!is_dir(TEMP_FILES)){mk_dir(TEMP_FILES);}
            $path = TEMP_FILES . 'imagemagick'; mk_dir($path);
			$cmd  = " -limit memory {$memLimit} -limit map {$mapLimit} -limit disk {$dskLimit} ";
			$cmd .= "-define registry:temporary-path={$path} ";	// 指定临时目录
			return $cmd . $param;
		}
		// $memLimit = intval($memFree / 1024);	// KB
		// return "ulimit -v {$memLimit}; {$param} -threads 2 ";	// $param=>ffmpeg
		$memLimit = $memFree;	// max_alloc 支持无后缀(bytes)和有后缀(m/g)
		return "{$param} -max_alloc {$memLimit} -threads 2 ";	// $param=>ffmpeg;
	}
	private function sizeFormat($size, $type = 'convert') {
		// $temp = explode(' ',size_format($size));
		// $size = floor($temp[0]) . $temp[1];
		$temp = explode(' ',size_format($size, 1));
		$size = $temp[0] . $temp[1];	// 用floor相差较大（比如1.5GB），用小数在某些系统下可能存在解析差异
		if ($type == 'convert') return $size;
		return str_replace('B', '', $size);
	}
	// ImageMagick生成缩略图所需内存（基本所需，实际要求更多）
	public function sysMemoryNeed($image) {
		// 获取图像信息
		$imageInfo = $this->getImgSize($image);
		if (!$imageInfo) {return false;}

		// 获取基本参数
		$width		= $imageInfo[0];
		$height		= $imageInfo[1];
		$channels	= _get($imageInfo, 'channels', 4); // 默认4通道
		$bits		= _get($imageInfo, 'bits', 8); // 默认8位
		// 基础内存需求（字节）- 原始图像内存
		$memory		= $width * $height * $channels * ($bits / 8);
		// ImageMagick通常需要至少3倍的原始图像内存（原图+工作内存+输出）
		// return $memory * 3;
		return $memory;	// convert添加了映射内存和磁盘，所以这里只返回原图所需，用于粗略过滤
	}
	// 获取图片信息
	public function getImgSize($image) {
		if (!file_exists($image)) return false;
		// 使用内置函数获取
		if ($imageInfo = getimagesize($image)) return $imageInfo;
		// 通过外部服务获取
		$ext = get_path_ext($image);
		$apiMethods = array(
			array('method' => 'getImgnryApi', 'param' => $ext),
			array('method' => 'getImgickApi', 'param' => $ext),
			array('method' => 'getImgmgkApi', 'param' => 'convert')
		);
		foreach ($apiMethods as $apiMethod) {
			$func = $apiMethod['method'];
			$api = $this->$func($apiMethod['param']);
			if ($api && $imageInfo = $api->getImgSize($image)) {
				return $imageInfo;
			}
		}
		return false;
	}

	// ------------------------------------------- 外部服务 -------------------------------------------

	// imagry环境检查
	public function checkImgnry(){
		$type = $this->in['type'];
		if ($type != 'imgnry') return;
		if(isset($_GET['check'])){
			$rest = $this->getThumbApi('imgnry')->status();
			$code = $rest ? 1 : 0;
			$this->setConfig(array('imgnryStatus' => $code));
			$msg = LNG('fileThumb.check.svc'.($code ? 'Ok' : 'Err'));
			show_json($msg, boolval($code));
		}
		include($this->pluginPath.'static/check.html');
		exit;
	}

	// 获取缩略图服务
	public function getThumbApi($type='imgnry') {
		$typeArr = array(
			'imgnry' => 'Imaginary',
			'imgick' => 'Imagick',
			'imgmgk' => 'ImageMagick',
		);
		static $api = array();
		if (!isset($api[$type])) {
			$class = 'Kod'.$typeArr[$type];
			@include_once($this->pluginPath."lib/{$class}.class.php");
			if (!class_exists($class)) {return false;}
			$api[$type] = new $class($this);
		}
		return $api[$type];
	}

	// 获取Imaginry对象
	public function getImgnryApi($ext) {
		// 检查服务是否启用
		static $imgnryStatus = null;
		if (is_null($imgnryStatus)) {
			$config = $this->getConfig();
			$open = intval($config['imgnryOpen']);
			$stat = intval($config['imgnryStatus']);
			$imgnryStatus = $open && $stat ? true : false;
		}
		if (!$imgnryStatus) return false;
		// 判断是否支持
		$api = $this->getThumbApi('imgnry');
		return (!$api || !$api->isSupport($ext)) ? false : $api;
	}
	// 获取Imagick扩展对象
	public function getImgickApi($ext) {
		if (!class_exists('Imagick')) return false;
		$api = $this->getThumbApi('imgick');
		return (!$api || !$api->isSupport($ext)) ? false : $api;
	}
	// 获取ImageMagick对象
	public function getImgmgkApi($type='convert') {
		if (!$this->getCommand($type)) return false;
		return $this->getThumbApi('imgmgk');
	}
}