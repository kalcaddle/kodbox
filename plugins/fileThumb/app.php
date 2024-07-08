<?php

class fileThumbPlugin extends PluginBase{
	function __construct(){
		parent::__construct();
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
		$supportWeb 	= explode(',','png,jpg,jpeg,bmp,webp,gif');
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
			if(in_array($file['ext'],$supportWeb) && $file['size'] <= 1024*50){continue;}
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
		echo $result;
	}

	//linux 注意修改获取bin文件的权限问题;
	public function check(){
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
	
	public function videoPreview(){
		if(!$this->getFFmpeg()) return;
		@include_once($this->pluginPath.'lib/VideoResize.class.php');
		$video = new videoResize();
		$video->videoPreview($this);
	}

	// 本地文件:local,io-local, {{kod-local}}全量生成;
	// 远端:(ftp,oss等对象存储)
	public function coverMake($cachePath,$path,$coverName,$size){
		if(IO::fileNameExist($cachePath,$coverName)){return 'exists;';}
		if(!is_dir(TEMP_FILES)){mk_dir(TEMP_FILES);}
		
		$info = IO::info($path);$ext = $info['ext'];
		$thumbFile = TEMP_FILES . $coverName;		
		$localFile = $this->localFile($path);
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
				file_put_contents($localTemp,IO::fileSubstr($path,0,1024*600));
			}
			$this->thumbVideo($localFile,$thumbFile,$videoThumbTime);
		} else {
			if(!$localFile){$localFile = $localTemp = $this->pluginLocalFile($path);}
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
		$this->log('cover=makeError:'.$localFile.';temp='.$thumbFile,'fileThumb');
		return 'convert error! localFile='.get_path_this($localFile);
	}
	private function getSize($size){
		$sizeAllow = array(250,1200,3000);
		for ($i=0;$i<count($sizeAllow);$i++){
			if($i == 0 && $size <= $sizeAllow[$i]){
				$size = $sizeAllow[$i];break;
			}else if($size > $sizeAllow[$i - 1] && $size <= $sizeAllow[$i]){
				$size = $sizeAllow[$i];break;
			}else if($i == count($sizeAllow) - 1 && $size > $sizeAllow[$i]){
				$size = $sizeAllow[$i];break;
			}
		}
		return $size;
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

	private function thumbVideo($file,$cacheFile,$videoThumbTime){
		$command = $this->getFFmpeg();
		if(!$command){
			echo "Ffmpeg ".LNG("fileThumb.check.notFound");
			return false;
		}
		$tempPath = $cacheFile;
		if($GLOBALS['config']['systemOS'] == 'linux' && is_writable('/tmp/')){
			mk_dir('/tmp/fileThumb');
			$tempPath = '/tmp/fileThumb/'.rand_string(15).'.jpg';
		}

		$maxWidth = 800;
		$timeAt   = $videoThumbTime ? '-ss 00:00:03' : '';
		$this->setLctype($file,$tempPath);
		$script   = $command.' -i '.escapeShell($file).' -y -f image2 '.$timeAt.' -vframes 1 '.escapeShell($tempPath).' 2>&1';
		$out = shell_exec($script);
		if(!file_exists($tempPath)) {
			if ($this->thumbVideoByLink($cacheFile)) return;
			return $this->log('video thumb error,'.$out.';cmd='.$script);
		}

		move_path($tempPath,$cacheFile);
		$cm = new ImageThumb($cacheFile,'file');
		$cm->prorate($cacheFile,$maxWidth,$maxWidth);
	}
	// 对象存储通过链接获取缩略图——当前仅支持OSS
	// https://help.aliyun.com/zh/oss/user-guide/video-snapshots?
	private function thumbVideoByLink($cacheFile) {
		if (!isset($this->ioFileInfo['ioDriver']) || $this->ioFileInfo['ioDriver'] != 'oss') return;
		// 获取缩略图链接
		$options = array('x-oss-process' => 'video/snapshot,t_10000,f_jpg,w_250,m_fast');
		$link = IO::link($this->ioFileInfo['path'], $options);
		if (!$link) return;
		// 写入文件——视频缩略图收费，写入文件，避免反复调用（截帧数*(0.1/1k)/1000）
		$content = curl_get_contents($link);
		if (!$content) return;
		file_put_contents($cacheFile, $content);
		return @file_exists($cacheFile) ? true : false;
	}

	// imagemagick  -density 100 //耗时间,暂时去除
	// convert -density 900 banner.psd -colorspace RGB -resample 300 -trim -thumbnail 200x200 test.jpg
	// convert -colorspace rgb simple.pdf[0] -density 100 -sample 200x200 sample.jpg
	private function thumbImage($file,$cacheFile,$maxSize,$ext){
		$this->thumbImageCreate($file,$cacheFile,$maxSize,$ext);
		$isResize = explode(',','gif,png,bmp,jpe,jpeg,jpg,webp,heic');
		if(in_array($ext,$isResize)) return;
		ImageThumb::createThumb($cacheFile,$cacheFile,$maxSize,$maxSize);
	}
	
	public function thumbImageCreate($file,$cacheFile,$maxSize,$ext){
		$command = $this->getConvert();
		if(!$command){
			echo "ImageMagick ".LNG("fileThumb.check.notFound");
			return false;
		}
		$size  = $maxSize.'x'.$maxSize;
		$param = "-auto-orient -alpha off -quality 90 -size ".$size;
		$tempName = rand_string(15).'.png';
		switch ($ext){
			case 'eps':
			case 'psb':
			case 'psd':
			case 'ps'://ps,ai,pdf; ==> window:缺少组件;mac:命令行可以执行，但php执行有问题
			case 'ai':$file.= '[0]';break;
			case 'pdf':$file.= '[0]';
				$param = "-auto-orient -alpha remove -alpha off -quality 90 -size ".$size." -background white";
				break; // pdf 生成缩略图透明背景变为黑色问题处理;
			
			/**
			 * 生成doc/docx封面; or转图片;
			 * 
			 * mac: 使用liboffice=>soffice 关联convert的delegate;
			 * centos : unoconv (yum install unoconv); 
			 * 		转doc/docx/ppt/pptx/xls/xlsx/odt/odf为pdf; 再用convert提取某页为图片;
			 * 		实现office预览方案之一;(中文字体copy)
			 * 		unoconv -f pdf /data/from.docx /data/toxx.pdf;
			 * 		// https://github.com/ScoutsGidsenVL/Pydio/blob/master/plugins/editor.imagick/class.IMagickPreviewer.php
			 */
			case 'ppt':
			case 'pptx':
			case 'doc':
			case 'docx':$file.= '[0]';break;

			// https://legacy.imagemagick.org/Usage/thumbnails/
			case 'tif':$file.= '[0]';$param .= " -flatten ";break;
			//case 'gif':$file.= '[0]';break;
			case 'gif':
				$param = "-thumbnail ".$size;
				$tempName = rand_string(15).'.gif';
				break;
			
			case 'webp':
			case 'png':
			case 'bmp':$param = "-resize ".$size;break;
			case 'jpe':
			case 'jpg':
			case 'jpeg':
			case 'heic':$param = "-auto-orient -resize ".$size;break;
			
			default:
				$dng = 'dng,cr2,erf,raf,kdc,dcr,mrw,nrw,nef,orf,pef,x3f,srf,arw,sr2';
				$dng = $dng.',3fr,crw,dcm,fff,iiq,mdc,mef,mos,plt,ppm,raw,rw2,srw,tst';
				if(in_array($ext,explode(',',$dng))){
					$param = "-resize ".$size;
					//$file = 'rgb:'.$file.'[0]';
				}
				break;
		}

		//linux下$cacheFile不可写问题，先生成到/tmp下;再移动出来
		$tempPath = $cacheFile;
		if($GLOBALS['config']['systemOS'] == 'linux' && is_writable('/tmp/')){
			mk_dir('/tmp/fileThumb');
			if(is_writable('/tmp/fileThumb')){ // 可能有不可写的情况;
				$tempPath = '/tmp/fileThumb/'.$tempName;
			}
		}
		$this->setLctype($file,$tempPath);
		$script = $command.' '.$param.' '.escapeShell($file).' '.escapeShell($tempPath).' 2>&1';
		$out = shell_exec($script);
		if(!file_exists($tempPath)) return $this->log('image thumb error:'.$out.';cmd='.$script);
		move_path($tempPath,$cacheFile);
		return true;
	}

	// 设置地区字符集，避免中文被过滤
	private function setLctype($path,$path2=''){
		if (stripos(setlocale(LC_CTYPE, 0), 'utf-8') !== false) return;
		if (Input::check($path,'hasChinese') || ($path2 && Input::check($path2,'hasChinese'))) {
			setlocale(LC_CTYPE, "en_US.UTF-8");
		}
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
}