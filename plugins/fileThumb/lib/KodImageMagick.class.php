<?php 

/**
 * 图片处理类-ImageMagick命令行
 */
class KodImageMagick {

	private $plugin;
    private $command;
    public function __construct($plugin) {
        $this->plugin = $plugin;
    }

    // 格式是否支持
    public function isSupport($ext) {
        return true;
    }

    // 获取命令
    public function getCommand($type='convert') {
        return $this->plugin->getCommand($type);
    }

    /**
     * 图片生成缩略图
     * @param [type] $file
     * @param [type] $cacheFile
     * @param [type] $maxSize
     * @param [type] $ext
     * @return void
     */
	// imagemagick  -density 100 //耗时间,暂时去除
	// convert -density 900 banner.psd -colorspace RGB -resample 300 -trim -thumbnail 200x200 test.jpg
	// convert -colorspace rgb simple.pdf[0] -density 100 -sample 200x200 sample.jpg
    public function createThumb($file, $cacheFile, $maxSize, $ext) {
        if (!file_exists($file) || !$this->isSupport($ext)) return false;
        $command = $this->getCommand();
        if (!$command) return false;

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
			case 'bmp':$param = "-resize {$size}";break;
			case 'jpe':
			case 'jpg':
			case 'jpeg':
			case 'heic':$param = "-resize {$size} -auto-orient";break;
			
			default:
				$dng = 'dng,cr2,erf,raf,kdc,dcr,mrw,nrw,nef,orf,pef,x3f,srf,arw,sr2';
				$dng = $dng.',3fr,crw,dcm,fff,iiq,mdc,mef,mos,plt,ppm,raw,rw2,srw,tst';
				if(in_array($ext,explode(',',$dng))){
					$param = "-resize {$size}";
					//$file = 'rgb:'.$file.'[0]';
				}
				break;
		}
		// 移除元数据、最低压缩级别、禁止过滤，8位深度——可能执行快一点，内存占用没有区别
		if ($ext != 'gif') {
			$param .= ' -strip -define png:compression-level=1 -define png:filter=0 -depth 8';
		}
		$param = $this->plugin->memLimitParam('convert', $param);	// 加上内存限制

        $tempPath = $this->getTmpPath($cacheFile, $tempName);
		$this->setLctype($file,$tempPath);

		$script = $command.' '.$param.' '.escapeShell($file).' '.escapeShell($tempPath).' 2>&1';
		$script = "export MAGICK_THREAD_LIMIT=2; {$script}";	// 限制进程数
		$out = shell_exec($script);

		if(!file_exists($tempPath)) return $this->log('image thumb error:'.$out.';cmd='.$script);
		move_path($tempPath,$cacheFile);
		return true;
    }

    /**
     * 使用ffmpeg生成视频封面
     * @param [type] $file
     * @param [type] $cacheFile
     * @param [type] $videoThumbTime
     * @return void
     */
    public function createThumbVideo($file,$cacheFile,$videoThumbTime){
        $command = $this->getCommand('ffmpeg');
        if (!$command) return false;
        $tempPath = $this->getTmpPath($cacheFile, rand_string(15).'.jpg');

		$maxWidth = 800;
		$timeAt   = $videoThumbTime ? ' -ss 00:00:03' : '';	// 截取时间点，前置（ffmpeg -ss 00:00:03）可直接从第3秒开始处理，提升效率
		$this->setLctype($file,$tempPath);
		$script   = $this->plugin->memLimitParam('ffmpeg', $command) . $timeAt . ' -i '.escapeShell($file).' -y -f image2 -vframes 1 '.escapeShell($tempPath).' 2>&1';
		// $script = "/usr/bin/time -v ".$script;  // Maximum resident set size
		$out = shell_exec($script);
		if(!file_exists($tempPath)) {
			$this->log('video thumb error,'.$out.';cmd='.$script);
            return false;
		}

		move_path($tempPath,$cacheFile);
		$cm = new ImageThumb($cacheFile,'file');
		$cm->prorate($cacheFile,$maxWidth,$maxWidth);
        return true;
	}

    /**
     * 获取图片信息，类getimagesize格式
     */
    public function getImgSize($file, $ext = '') {
        if (!file_exists($file)) return false;

        // $res = shell_exec("identify -format '%wx%hx%[channels]x%[depth]' ".escapeshellarg($file));
        $res = shell_exec("identify -format '%w %h' -ping ".escapeshellarg($file));
        if (!$res) return false;

        list($width, $height) = explode(' ', trim($res));
        return array(
            intval($width),    // width
            intval($height),   // height
            'channels' => 3,
            'bits'     => 8,
        );
    }

    // 设置地区字符集，避免中文被过滤
	private function setLctype($path,$path2=''){
		if (stripos(setlocale(LC_CTYPE, 0), 'utf-8') !== false) return;
		if (Input::check($path,'hasChinese') || ($path2 && Input::check($path2,'hasChinese'))) {
			setlocale(LC_CTYPE, "en_US.UTF-8");
		}
	}

    // 重置临时文件位置：linux下$cacheFile不可写问题，先生成到/tmp下;再移动出来
    private function getTmpPath($tempFile, $tempName) {
        $tempPath = $tempFile;
        if($GLOBALS['config']['systemOS'] == 'linux' && is_writable('/tmp/')){
			mk_dir('/tmp/fileThumb');
			if(is_writable('/tmp/fileThumb')){ // 可能有不可写的情况;
				$tempPath = '/tmp/fileThumb/'.$tempName;
			}
		}
        return $tempPath;
    }

    // 记录日志
    public function log($msg) {
        $this->plugin->log($msg);
    }
    
}