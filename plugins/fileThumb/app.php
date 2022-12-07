<?php

class fileThumbPlugin extends PluginBase{
	function __construct(){
		parent::__construct();
	}
	public function regist(){
		$this->hookRegist(array(
			'user.commonJs.insert'  	=> 'fileThumbPlugin.echoJs',
			'explorer.list.itemParse'	=> 'fileThumbPlugin.itemParse',
		));
	}
	public function echoJs(){
		$this->echoFile('static/main.js');
	}
	public function itemParse($pathInfo){
		static $supportThumb = false;
		static $supportView  = false;
		static $supportBin   = false;
		if(!$supportThumb){
			$config 		= $this->getConfig('fileThumb');
			$supportThumb 	= explode(',',$config['fileThumb']);
			$supportView  	= explode(',',$config['fileExt']);
			$supportBin 	= $this->getConvert() && $this->getFFmpeg();
		}
		if($supportBin != 'ok') return;
		if($pathInfo['type'] == 'folder') return;
		if(isset($pathInfo['fileThumb']) || $pathInfo['size'] < 100) return;
		if(!in_array($pathInfo['ext'],$supportThumb)) return;

		$param  = '&path='.rawurlencode($pathInfo['path']);
		$param .= '&etag='.$pathInfo['size'].'_'.$pathInfo['modifyTime'];
		$pathInfo['fileThumb'] = APP_HOST.'?plugin/fileThumb/cover'.$param.'&size=250';
		if(in_array($pathInfo['ext'],$supportView)){
			$pathInfo['fileShowView'] = APP_HOST.'?plugin/fileThumb/cover'.$param.'&size=1200';
		}
		return $pathInfo;
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
	public function cover(){
		$path = $this->filePath($this->in['path']);
		$size = $this->getSize();
		$info = IO::info($path);
		$ext  = $info['ext'];
		
		// io缩略图已存在，直接输出
		$fileHash  = KodIO::hashPath($info);
		$coverName = "cover_{$ext}_{$fileHash}_{$size}.jpg";
		$thumbFile = TEMP_FILES . $coverName;
		if($sourceID = IO::fileNameExist($this->cachePath, $coverName)){
			return IO::fileOut(KodIO::make($sourceID));
		}
		if(Cache::get('fileCover_'.$fileHash) == 'error'){return;}; //是否有封面处理;
		
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

		// del_file($thumbFile);
		if( $isVideo ){
			// 不是本地文件; 切片后获取:mp4,mov,mpg,webm,f4v,ogv,avi,mkv,wmv;(部分失败)
			if(!$localFile){
				$localTemp = $thumbFile.'.'.$ext;
				$localFile = $localTemp;
				file_put_contents($localTemp,IO::fileSubstr($path,0,1024*600));
			}
			$this->thumbVideo($localFile,$thumbFile,$videoThumbTime);
		} else {
			if(!$localFile){
				// $localTemp = $localFile，可能会转换失败，为避免重新下载，不删除临时文件
				$localFile = $this->pluginLocalFile($path);	// 下载到本地文件
			}
			if($ext == 'ttf'){
				$this->thumbFont($localFile,$thumbFile,$size);
			}else{
				$this->thumbImage($localFile,$thumbFile,$size,$ext);
			}
		}

		// pr(file_exists($thumbFile),$localFile,$thumbFile);exit;
		if($localTemp){ @unlink($localTemp); }
		if(@file_exists($thumbFile)){
			$cachePath  = IO::move($thumbFile,$this->cachePath);
			return IO::fileOutServer($cachePath);
		}
		Cache::set('fileCover_'.$fileHash,'error',60);
		del_file($thumbFile);
	}
	
	private function getSize(){
		$size = intval($this->in['size']);		
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
		$script   = $command.' -i "'.$file.'" -y -f image2 '.$timeAt.' -vframes 1 '.$tempPath;
		shell_exec($script);
		if(!file_exists($tempPath)) return;

		move_path($tempPath,$cacheFile);
		$cm = new ImageThumb($cacheFile,'file');
		$cm->prorate($cacheFile,$maxWidth,$maxWidth);
	}

	// imagemagick  -density 100 //耗时间,暂时去除
	// convert -density 900 banner.psd -colorspace RGB -resample 300 -trim -thumbnail 200x200 test.jpg
	// convert -colorspace rgb simple.pdf[0] -density 100 -sample 200x200 sample.jpg
	private function thumbImage($file,$cacheFile,$maxSize,$ext){
		$this->thumbImageCreate($file,$cacheFile,$maxSize,$ext);
		$isResize = explode(',','gif,png,bmp,jpe,jpeg,jpg,heic');
		if(in_array($ext,$isResize)) return;

		ImageThumb::createThumb($cacheFile,$cacheFile,$maxSize,$maxSize);
	}
	
	public function thumbImageCreate($file,$cacheFile,$maxSize,$ext){
		$command = $this->getConvert();
		if(!$command){
			echo "ImageMagick ".LNG("fileThumb.check.notFound");
			return false;
		}
		$param = "-auto-orient -alpha off -quality 90 -size {$maxSize}x{$maxSize}";
		switch ($ext){
			case 'eps':
			case 'psb':
			case 'psd':
			case 'ps'://ps,ai,pdf; ==> window:缺少组件;mac:命令行可以执行，但php执行有问题
			case 'ai':
			case 'pdf':$file.= '[0]';break;
			
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
			case 'tif':$file.= '[0]';$param = " -flatten ";break;
			case 'gif':
			case 'png':
			case 'bmp':
			case 'jpe':
			case 'jpeg':
			case 'jpg':$param   = "-resize {$maxSize}x{$maxSize}";break;
			case 'heic':;$param = "-resize {$maxSize}x{$maxSize}";break;
			
			default:
				$dng = 'dng,cr2,erf,raf,kdc,dcr,mrw,nrw,nef,orf,pef,x3f,srf,arw,sr2';
				$dng = $dng.',3fr,crw,dcm,fff,iiq,mdc,mef,mos,plt,ppm,raw,rw2,srw,tst';
				if(in_array($ext,explode(',',$dng))){
					$param = "-resize {$maxSize}x{$maxSize}";
					$file = 'rgb:'.$file.'[0]';
				}
				break;
		}

		//linux下$cacheFile不可写问题，先生成到/tmp下;再复制出来
		$tempPath = $cacheFile;
		if($GLOBALS['config']['systemOS'] == 'linux' && is_writable('/tmp/')){
			mk_dir('/tmp/fileThumb');
			$tempPath = '/tmp/fileThumb/'.rand_string(15).'.jpg';
		}

		$script = $command.' '.$param.' "'.$file.'" '.$tempPath;
		shell_exec($script);
		// pr($script,file_exists($tempPath));exit;
		if(!file_exists($tempPath)) return;

		move_path($tempPath,$cacheFile);
		return true;
	}
	
	
	public function getFFmpeg(){
		return $this->getCall('fileThumb.getFFmpeg',60,array($this,'getFFmpegNow'));
	}
	public function getConvert(){
		return $this->getCall('fileThumb.getConvert',60,array($this,'getConvertNow'));
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
        if(strstr($out,'--disable-muxer=image2')){return false;}
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
		if($result){
			return $result;
		}
		$findMore = array(
			'/imagemagick/convert',
			'/imagemagick/bin/convert',
			'/ImageMagick/convert.exe',
			'/ImageMagick-7.0.7-Q8/convert.exe',
		);
		foreach ($findMore as $value) {
			$result = $this->guessBinPath($value,$check);
			if($result){
				return $result;
			}
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
		$result = shell_exec($bin.' --help');
		return stripos($result,$check) > 0 ? true : false;
	}
}