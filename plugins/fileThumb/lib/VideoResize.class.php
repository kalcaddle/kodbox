<?php

/**
 * 视频转码处理
 * 
 * 转码条件: 视频文件格式,已解析到播放时长,大于20M,非系统文件
 * 任务管理: 转码后进入后台执行,旁路定时获取转码进度,更新到任务; 任务可手动结束,结束后同时结束转码进程;
 * 转码配置: 开启转码,最大并发进程,最小文件大小;(任务列表)
 * 并发管理: 进行中则返回进度; 添加时检测允许中的任务,大于最大限制则直接返回信息; 
 * 播放处理: 开启了转码时,播放时请求该文件标清视频(已完成返回标清视频链接;首次触发转码;进行中则获取进度;任务繁忙情况);
 * 
 * test: http://127.0.0.1/kod/kodbox/?plugin/fileThumb/videoSmall&path={source:3801668}
 * 视频压制ffmpeg参数: https://www.bilibili.com/read/cv5804639/
 * ffmpeg参数说明: https://github.com/fujiawei-dev/ffmpeg-generator/blob/master/docs/vfilters.md
 * 部分服务器处理视频转码绿色条纹花屏问题(兼容处理)
 * 		图片缩放指定转码算法 -sws_flags, 默认为bilinear, ok=accurate_rnd,neighbor,bitexact(neighbor会有锯齿)
 * 		https://www.sohu.com/a/551562728_121066035 
 * 		https://www.cnblogs.com/acloud/archive/2011/10/29/sws_scale.html
 * 		https://github.com/hemanthm/ffmpeg-soc/blob/376d5c5f13/libswscale/options.c
 * 		https://blog.csdn.net/Randy009/article/details/51523331
 */
class videoResize {
	const STATUS_SUCCESS	= 2;	//成功;
	const STATUS_IGNORE		= 3;	//忽略转换,大小格式等不符合;
	const STATUS_ERROR		= 4;	//转换失败,ffmepg不存在等原因;
	const STATUS_RUNNING	= 5;	//转换进行中;
	const STATUS_LIMIT		= 6;	//任务数达到上限,忽略该任务.

	public function start($plugin){
		$path = $plugin->filePath($GLOBALS['in']['path']);
		$fileInfo = IO::info($path);
		$fileInfo = Action('explorer.list')->pathInfoMore($fileInfo);
		$fileInfo['taskID'] = 'video-convert-'.KodIO::hashPath($fileInfo);
		$status  = $this->run($path,$fileInfo,$plugin);
		if($status === true) return;
		
		$taskID = $fileInfo['taskID'];
		$result = array('status'=>$status,'taskRun'=>Cache::get('fileThumb-videoResizeCount'));
		switch ($status) {
			case self::STATUS_SUCCESS:
				$findSource = IO::fileNameExist($plugin->cachePath,$taskID.".mp4");
				$sourcePath = KodIO::make($findSource);
				$result['msg']  = LNG('fileThumb.video.STATUS_SUCCESS');
				$result['data'] = $plugin->pluginApi.'videoSmall&play=1&path='.rawurlencode($path);
				$result['data'] = Action('explorer.share')->link($sourcePath);
				$result['size'] = array(
					'before'	=> size_format($fileInfo['size']),
					'now'		=> size_format(IO::size($sourcePath)),
				);
				// if($_GET['play'] == '1'){IO::fileOut($sourcePath);exit;}
				break;
			case self::STATUS_IGNORE:$result['msg'] = LNG('fileThumb.video.STATUS_IGNORE');break;
			case self::STATUS_ERROR:$result['msg'] = $this->convertError($taskID);break;
			case self::STATUS_RUNNING:
				$result['msg']  = LNG('fileThumb.video.STATUS_RUNNING');
				$result['data'] = Task::get($taskID);
				$errorCheck = 'Task_error_'.$taskID;
				if(!$result['data']){
				    $lastError = Cache::get($errorCheck);
				    if(!$lastError){Cache::set($errorCheck,time(),60);}
				    if($lastError && time() - $lastError > 10){
				        $this->convertClear($taskID);
				        Cache::remove($errorCheck);
				    }
				}else{
				    Cache::remove($errorCheck);
				}
				break;
			case self::STATUS_LIMIT:$result['msg'] = LNG('fileThumb.video.STATUS_LIMIT');break;
			default:break;
		}
		show_json($result);
	}
	public function run($path,$fileInfo,$plugin){
		// io缩略图已存在，直接输出
		$cachePath = $plugin->cachePath;
		$taskID    = $fileInfo['taskID'];
		$tempFileName = $taskID.".mp4";
		$config 	= $plugin->getConfig('fileThumb');
		$isVideo   	= in_array($fileInfo['ext'],explode(',',$config['videoConvertType']));
		$fileSizeMax = floatval($config['videoConvertLimitTo']); //GB; 为0则不限制
		$fileSizeMin = floatval($config['videoConvertLimit']); //GB; 为0则不限制
		if(IO::fileNameExist($cachePath, $tempFileName)){return self::STATUS_SUCCESS;}
		
		$pathDisplay = isset($fileInfo['pathDisplay']) ? $fileInfo['pathDisplay'] : $fileInfo['path'];
		if( !$isVideo || 
		    !is_array($fileInfo['fileInfoMore']) ||
			!isset($fileInfo['fileInfoMore']['playtime']) ||
			strstr($pathDisplay,'/systemPath/systemTemp/plugin/fileThumb/') ||
			strstr($pathDisplay,'/tmp/fileThumb') ||
			strstr($pathDisplay,TEMP_FILES) ||
			($fileSizeMax > 0.01 && $fileInfo['size'] > 1024 * 1024 * 1024 * $fileSizeMax) ||
			($fileSizeMin > 0.01 &  $fileInfo['size'] < 1024 * 1024 * $fileSizeMin) ){
			return self::STATUS_IGNORE;
		}
		
		$command = $plugin->getFFmpeg();
		if(!$command || !function_exists('proc_open') || !function_exists('shell_exec')){
			$this->convertError($taskID,LNG('fileThumb.video.STATUS_ERROR').'(3501)');
			return self::STATUS_ERROR;//Ffmpeg 软件未找到，请安装后再试
		}
		if(!$this->convertSupport($command)){
			$this->convertError($taskID,'ffmpeg not support libx264; please repeat install'.'(3502)');
			return self::STATUS_ERROR;//Ffmpeg 转码解码器不支持;
		}
		
		// 过短的视频封面图,不指定时间;
		$localFile = $plugin->localFile($path);
		if(Cache::get($taskID) == 'error'){return self::STATUS_ERROR;}; //是否已经转码
		if( !$localFile ){
			Cache::set($taskID,'error',60);
			$this->convertError($taskID,'localFile move error!'.'(3503)');
			return self::STATUS_IGNORE;
		}
		
		$tempPath = TEMP_FILES.$tempFileName;
		if($GLOBALS['config']['systemOS'] == 'linux' && is_writable('/tmp/')){
			mk_dir('/tmp/fileThumb/');
			$tempPath = '/tmp/fileThumb/'.$tempFileName;
		}

		if(Cache::get($taskID) == 'running') return self::STATUS_RUNNING;
		$runTaskMax   = intval($config['videoConvertTask']);
		$runTaskCount = intval(Cache::get('fileThumb-videoResizeCount'));
		if($runTaskCount >= $runTaskMax) return self::STATUS_LIMIT;
		if($_GET['noOutput'] == '1'){http_close();} // 默认输出;
		$this->convertAdd($taskID);
		
		// https://www.ruanyifeng.com/blog/2020/01/ffmpeg.html
		ignore_timeout();
		$quality 	= 'scale=-2:480 -b:v 1000k -maxrate 1000k -sws_flags accurate_rnd';//480:-2 -2:480 限制码率; 缩放图片处理
		$timeStart 	= time();// 画质/速度: medium/ultrafast/fast
		$logFile 	= $tempPath.'.log';@unlink($logFile);//-vf scale=480:-2 -b:v 1024k -maxrate 1000k -threads 2
		$args 		= '-c:a copy -preset medium -vf '.$quality.' -strict -2 -c:v libx264 1>'.$logFile.' 2>&1';
		$script 	= $command.' -y -i "'.$localFile.'" '.$args.' "'.$tempPath.'"';
		
		// 后台运行
		if($GLOBALS['config']['systemOS'] == 'windows'){
			$script = 'start /B "" '.$script;
		}else{
			$script = $script.' &';
		}
		$this->log('[command] '.$script."\n");
		//@shell_exec($script);@shell_exec("powershell.exe Start-Process -WindowStyle hidden '".$command."' ");
        proc_close(proc_open($script,array(array('pipe','r'),array('pipe','w'),array('pipe','w')),$pipes));
		$this->progress($tempPath,$fileInfo,$cachePath,$timeStart);
		return true;
	}
	
	/**
	 * 启动转换后计算进度, 并存储在全局任务中;
	 * 定时检测转换进度: 300ms一次, 结束条件: 转换完成or输出日志文件5s没有更新;
	 */
	private function progress($tempPath,$fileInfo,$cachePath,$timeStart){
		$logFile = $tempPath.'.log';// 5s没有更新则结束;
		usleep(300*1000);// 运行后,等待一段时间再读取信息;
		
		$tempName = get_path_this($tempPath);
		$pid  = $this->processFind($tempName);
		$data = $this->progressGet($logFile);
		$args = array($tempPath,$fileInfo,$cachePath,$timeStart,$pid);
		$task = new TaskConvert($fileInfo['taskID'],'videoResize',$data['total'],LNG("fileThumb.video.title"));
		$task->task['currentTitle'] = size_format($fileInfo['size']).'; '.$fileInfo['name'];
		$task->onKillCall = array(array($this,'convertFinished'),$args);
		$task->onKillSet(array($this,'convertFinished'),$args);
		
		$this->log('[start] '.$task->task['currentTitle'].'; pid='.$pid);
		while(true){
			$data = $this->progressGet($logFile);clearstatcache();
			if($data['total'] && $data['finished'] == $data['total']){break;}
			if(time() - @filemtime($logFile) >= 5){break;}
			if(!$this->processFind($tempName)){break;} //进程已不存在; 转码报错或者意外终止或者其他情况的进程终止;

			$task->task['taskFinished'] = round($data['finished'],3);
			CacheLock::lock("video-process-update");
			$task->update(0);
			CacheLock::unlock("video-process-update");
			$this->log(sprintf('%.1f',$data['finished'] * 100 / $data['total']).'%',true);
			Cache::set($fileInfo['taskID'],'running',60);
			usleep(300*1000);//300ms;
		}
		$task->end();$task->onKill();
	}
	public function log($log,$clear=false,$show=true){
	    if($show){echoLog($log,$clear);}
		if(!$clear){write_log($log,'videoConvert');}
	}
	
	private function progressGet($logFile){
		$result  = array('finished'=>0,'total'=>0);
		$content = @file_get_contents($logFile);
		preg_match("/Duration: (.*?), start: (.*?), bitrate:/", $content, $match);
		if(is_array($match)){
			$total = explode(':', $match[1]);
			$result['total'] = intval($total[0]) * 3600 + intval($total[1]) * 60 + floatval($total[2]); // 转换为秒
		}

		preg_match_all("/frame=(.*?) time=(.*?) bitrate/", $content, $match);
		if(is_array($match) && count($match) == 3 && $match[2]){
			$total = explode(':',$match[2][count($match[2]) - 1]);
			$result['finished'] = intval($total[0]) * 3600 + intval($total[1]) * 60 + floatval($total[2]); // 转换为秒
		}
		
		if(!$result['total']){$result['total'] = 1;}
		if(strstr($content,'video:')){$result['finished'] = $result['total'];}
		return $result;
	}	
	
	public function convertFinished($tempPath,$fileInfo,$cachePath,$timeStart,$pid){
		$logFile 	= $tempPath.'.log';
		$data 		= $this->progressGet($logFile);
		$output 	= @file_get_contents($logFile);
		@unlink($logFile);
		$this->processKill($pid);
		$this->convertClear($fileInfo['taskID']);
		
		$runError  = true;
		$errorTips = 'Run error!';
		if( preg_match("/(Error .*)/",$output,$match) || 
			preg_match("/(Unknown encoder .*)/",$output,$match) ||
			preg_match("/(Invalid data found .*)/",$output,$match) ||
			preg_match("/(No device available .*)/",$output,$match)
		){
			$errorTips = '[ffmpeg error] '.$match[0].';<br/>see log[data/temp/log/videoconvert/xx.log]';
		}
		if( preg_match("/frame=\s+(\d+)/",$output,$match)){
			$errorTips = 'Stoped!';
			$runError  = false;
		}
		$logEnd  = get_caller_msg();
		$logTime = 'time='.(time() - $timeStart);	
		if($data['total'] && intval($data['total']) == intval($data['finished']) && file_exists($tempPath)){
			$runError = true;
			$destPath = IO::move($tempPath,$cachePath);
			$checkStr = IO::fileSubstr($destPath,0,10);
			if($checkStr && strlen($checkStr) == 10){
				$this->log('[end] '.$fileInfo['name'].'; finished Success; '.$logTime.$logEnd);
				return;
			}
			IO::remove($destPath,false);
			$this->log('[end] '.$fileInfo['name'].'; move error; '.$logTime.$logEnd);
			$errorTips = 'Move temp file error!';
		}
		
		@unlink($tempPath);
		Cache::set($fileInfo['taskID'],'error',5);
		$this->convertError($fileInfo['taskID'],$errorTips);
		$logAdd = $runError ? "\n".trim($output) : '';
		$this->log('[end] '.$fileInfo['name'].';'.$errorTips.'; '.$logTime.$logAdd.$logEnd);
		$this->log('[end] '.$output);
	}
	
	public function convertAdd($taskID){
		$runTaskCount = intval(Cache::get('fileThumb-videoResizeCount'));
		$taskList     = Cache::get('fileThumb-videoResizeList');
		$taskList	  = is_array($taskList) ? $taskList : array();
		if(!$runTaskCount){$this->stopAll();$taskList = array();}
		
		$taskList[$taskID] = '1';
		Cache::set('fileThumb-videoResizeList',$taskList,3600);
		Cache::set('fileThumb-videoResizeCount',($runTaskCount + 1),3600);
		Cache::set($taskID,'running',600);
	}
	public function convertClear($taskID){
		$runTaskCount = intval(Cache::get('fileThumb-videoResizeCount'));
		$runTaskCount = $runTaskCount <= 1 ? 0 : ($runTaskCount - 1);

		$taskList     = Cache::get('fileThumb-videoResizeList');
		$taskList	  = is_array($taskList) ? $taskList : array();
		if($taskList[$taskID]){unset($taskList[$taskID]);}
		
		Cache::set('fileThumb-videoResizeList',$taskList,3600);		
		Cache::set('fileThumb-videoResizeCount',$runTaskCount,3600);
		Cache::remove($taskID);
		if(!$runTaskCount){$this->stopAll();}
	}
	public function stopAll(){
		$taskList = Cache::get('fileThumb-videoResizeList');
		$taskList = is_array($taskList) ? $taskList : array();
		foreach ($taskList as $taskID=>$key){
			Task::kill($taskID);
			Cache::remove($taskID);
			$this->convertError($taskID,-1);
		}
		Cache::remove('fileThumb-videoResizeList');
		Cache::remove('fileThumb-videoResizeCount');
		
		$count = 0;
		while($count <= 20){
			$pid = $this->processFind('ffmpeg');
			if($pid){$this->processKill($pid);}
			if(!$pid){break;}
			$count ++;
		}
	}
	private function convertError($taskID,$content=""){
		$key = 'fileThumb-videoResizeError-'.$taskID;
		if($content === -1){return Cache::remove($key);}
		if(!$content){return Cache::get($key);}
		Cache::set($key,$content,600);
	}
	private function convertSupport($ffmpeg){
		$out = shell_exec($ffmpeg.' -v 2>&1');
		if(!strstr($out,'--enable-libx264')){return false;}
		return true;
	}
	
	// 通过命令行及参数查找到进程pid; 兼容Linux,window,mac
	// http://blog.kail.xyz/post/2018-03-28/other/windows-find-kill.html
	public function processFind($search){
		$cmd = "ps -ef | grep '".$search."' | grep -v grep | awk '{print $2}'";
		if($GLOBALS['config']['systemOS'] != 'windows'){return trim(@shell_exec($cmd));}
		
		// windows 获取pid;
		$cmd = 'WMIC process where "Commandline like \'%%%'.$search.'%%%\'" get Caption,Processid,Commandline';
		$res = trim(@shell_exec($cmd));
		$resArr = explode("\n",trim($res));
		if(!$resArr || count($resArr) <= 3) return '';

		$lineFind = $resArr[count($resArr) - 3];// 最后两个一个为wmic,和cmd;
		$res = preg_match("/.*\s+(\d+)\s*$/",$lineFind,$match);
		if($res && is_array($match) && $match[1]){return $match[1];}
		return '';
	}
	
	// 通过pid结束进程;
	public function processKill($pid){
		if(!$pid) return;
		if($GLOBALS['config']['systemOS'] != 'windows'){return @shell_exec('kill -9 '.$pid);}
		@shell_exec('taskkill /F /PID '.$pid);
	}
	
	

	// ================================== 生成视频预览图 ==================================
	// 生成视频预览图; 平均截取300张图(小于30s的视频不截取)
	public function videoPreview($plugin){
		$pickCount = 300;// 总共截取图片数(平均时间内截取)
		$path = $plugin->filePath($GLOBALS['in']['path']);
		$fileInfo = IO::info($path);
		$fileInfo = Action('explorer.list')->pathInfoMore($fileInfo);
		$tempFileName = 'preview-'.KodIO::hashPath($fileInfo).'.jpg';
		$findSource = IO::fileNameExist($plugin->cachePath,$tempFileName);
		if($findSource){return IO::fileOut(KodIO::make($findSource));}

		$command = $plugin->getFFmpeg();
		if(!$command){return show_json('command error',false);}
		$localFile = $plugin->localFile($path);
		if(!$localFile ){return show_json('not local file',false);}
		$videoInfo = $this->parseVideoInfo($command,$localFile);
		$this->storeVideoMetadata($fileInfo,$videoInfo);
		if(!$videoInfo || $videoInfo['playtime'] <= 30){return show_json('time too short!',false);}
		
		// $pickCount = $totalTime; 每秒生成一张图片; 可能很大
		// 宽列数固定10幅图, 行数为总截取图除一行列数; https://blog.51cto.com/u_15639793/5297432
		if(Cache::get($tempFileName)) return show_json('running');
		Cache::set($tempFileName,'running',600);
		
		ignore_timeout();
		$tempPath = TEMP_FILES.$tempFileName;
		$fps = $pickCount / $videoInfo['playtime'];
		$sizeW = 150;
		$tile  = '10x'.ceil($pickCount / 10).''; 
		$scale = 'scale='.$sizeW.':-2'; //,pad='.$sizeW.':'.$sizeH.':-1:-1 -q:v 1~5 ;质量从最好到最差;
		$args  = '-sws_flags accurate_rnd -q:v 4 -an'; //更多参数; 设置图片缩放算法(不设置缩小时可能产生绿色条纹花屏);
		$cmd = 'ffmpeg -y -i "'.$localFile.'" -vf "fps='.$fps.','.$scale.',tile='.$tile.'" '.$args.' "'.$tempPath.'"';
		$this->log('[videoPreview start] '.$fileInfo['name'].';size='.size_format($fileInfo['size']),0,0);$timeStart = timeFloat();
		$this->log('[videoPreview run] '.$cmd,0,0);
		@shell_exec($cmd);//pr($cmd);exit;
		Cache::remove($tempFileName);
		
		$success = file_exists($tempPath) && filesize($tempPath) > 100;
		$msg     = $success ? 'success' : 'error';
		$this->log('[videoPreview end] '.$fileInfo['name'].';time='.(timeFloat() - $timeStart).'s;'.$msg,0,0);
		if($success){
			$destPath = IO::move($tempPath,$plugin->cachePath);
			if($destPath){return IO::fileOut($destPath);}
		}
		@unlink($tempPath);
		show_json('run error',false);
	}
	
	// 更新完善文件Meta信息;
	private function storeVideoMetadata($fileInfo,$videoInfo){
		$infoMore = _get($fileInfo,'fileInfoMore',array());
		$cacheKey = 'fileInfo.'.md5($fileInfo['path'].'@'.$fileInfo['size'].$fileInfo['modifyTime']);
		$fileID   = _get($fileInfo,'fileInfo.fileID',_get($fileInfo,'fileID'));

		$audio    = $videoInfo['audio'];
		$infoMore['audio'] = is_array($infoMore['audio']) ? array_merge($audio,$infoMore['audio']) : $audio;
		$infoMore = array_merge($videoInfo,$infoMore);
		$infoMore['etag']  = $fileID ? $fileID : $cacheKey;
		if($fileID){
			Model("File")->metaSet($fileID,'fileInfoMore',json_encode($infoMore));
		}else{
			Cache::set($cacheKey,$infoMore,3600*24*30);
		}
	}

	// 解析视频文件信息;
	private function parseVideoInfo($command,$video){
		$result  = shell_exec($command.' -i "'.$video.'" 2>&1');
		$info    = array('playtime'=>0,'createTime'=>'','audio'=>array());
		if(preg_match("/Duration:\s*([0-9\.\:]+),/", $result, $match)) {
			$total = explode(':', $match[1]);
			$info['playtime'] = intval($total[0]) * 3600 + intval($total[1]) * 60 + floatval($total[2]); // 转换为秒
		}
		if(preg_match("/Video: (.*?), (.*?), (\d+)x(\d+)/", $result,$match)) {
			$info['dataformat'] = $match[1];
			$info['sizeWidth']  = $match[3];
			$info['sizeHeight'] = $match[4];
		}
		if(preg_match("/Audio: (.*?), (\d+) Hz, (.*?), /", $result,$match)) {
			$info['audio']['dataformat'] = $match[1];
			$info['audio']['rate']  = $match[2];
			$info['audio']['channelmode']  = $match[3];
		}
		if(preg_match("/creation_time\s*:\s*(.*)/", $result,$match)){
			$info['createTime'] = date("Y-m-d H:i:s",strtotime($match[1]));
		}
		if(preg_match("/encoder\s*:\s*(.*)/", $result,$match)){
			$info['software'] = $match[1];
		}
		return $info;
	}
	
	
	private function findBinPath($bin,$check){
		$isWin = $GLOBALS['config']['systemOS'] == 'windows';
		$path  = false;
		if ($isWin) {
			exec('where '.escapeshellarg($bin),$output);
			foreach ($output as $line) {
				if (stripos($line,$check) !== false) {
					$path = escapeshellarg($line).'/'.$bin.'.exe';
					break;
				}
			}
		} else {
			exec('which '.$bin,$output,$status);
			if ($status == 0) {
				$path = $bin;
				if($output[0]){
					$path = $output[0].'/'.$bin;
				}
			}
		}
		return $path;
	}
}