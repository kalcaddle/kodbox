<?php
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/
//官网用户demo
//https://www.yozodcs.com/page/example.html
class yzOffice{
	public $plugin;
	public $filePath;
	public $task;
	public $taskFile;
	public $api;

	public $cachePath;	// 缓存文件目录
	public $cacheTask;	// 任务信息缓存名称
	public function __construct($plugin,$filePath){
		$this->plugin = $plugin;
		$this->filePath = $filePath;

		$this->api = array(
			'upload'	=> "https://www.yozodcs.com/fcscloud/file/upload",
			'convert'	=> "https://www.yozodcs.com/fcscloud/composite/convert",
		);

		$this->cachePath = $this->plugin->cachePath;
		$this->cacheTask = md5($this->cachePath . $this->filePath);

		// 任务信息，如有缓存直接读取；否则读任务文件内容，存入缓存
		$this->taskFile = $this->cachePath.'info.json';
		if($this->task = Cache::get($this->cacheTask)) return;
		if($info = IO::infoFull($this->taskFile)){
			$this->taskFile = $info['path'];
			$taskHas = json_decode(IO::getContent($this->taskFile),true);
			$this->task = $taskHas;
			return Cache::set($this->cacheTask, $taskHas);
		}
		return $this->task = false;
	}
	public function runTask(){
		$task = array(
			'currentStep'	=> 0,
			'success'     	=> 0,
			'taskUuid'		=> md5($this->filePath.rand_string(20)),
			'hideData'		=> array(),
			'steps'	=> array(
				array('name'=>'upload','process'=>'uploadProcess','status'=>0,'result'=>''),
				array('name'=>'convert','process'=>'convert','status'=>0,'result'=>''),
			)
		);
		if(is_array($this->task)){
			$task = &$this->task;
		}else{
			$this->task = &$task;
		}

		$item  = &$task['steps'][$task['currentStep']];
		// write_log(array('永中任务----', $item));
		if($item['status'] == 0){
			$item['status'] = 1;
			if(!$item['process'] || 
				$item['name'] == $item['process']){ //单步没有定时检测；相等则自我查询进度；0=>2之间跳转
				$item['status'] = 0;
			}
			$this->saveData();
			$function = $item['name'];
			$result = $this->$function();
			if(isset($result['data'])){
				$item['result'] = $result['data'];
				$item['status'] = 2;
				$task['currentStep'] += 1;

				//最后一步完成
				if( $item['status'] == 2 &&  $task['currentStep'] > count($task['steps'])-1 ){
					$task['success'] = 1;
				}
				if($task['currentStep'] >= count($task['steps'])-1 ){
					$task['currentStep'] = count($task['steps'])-1;
				}
				$this->saveData();
			}else{
				$error = LNG('explorer.error');
				if(is_array($result) && $result['code'] == 100){
					$error = LNG('explorer.upload.error');
				}else if(is_array($result) && is_string($result['data']) ){
					$error = $result['data'];
				}
				show_json($error,false,$result);
			}
		}else if($item['status'] == 1){
			$function = $item['process'];
			if($function){
				$item['result'] = $this->$function();
				if($item['name'] == 'upload' && !$item['result']){
					show_json($item['result'],false);
				}
				$this->saveData();
			}
		}
		unset($task['hideData']);
		show_json($task);
	}
	public function saveData(){
		Cache::set($this->cacheTask, $this->task);
		if($this->taskSuccess($this->task)){
			$data = json_encode_force($this->task);
			return $this->plugin->pluginCacheFileSet($this->taskFile, $data);
		}
	}
	// 是否转换成功
	public function taskSuccess($taskHas){
		if(!is_array($taskHas)) return false;
		$lastStep = end($taskHas['steps']);
		return $lastStep['status'] == 2 ? $taskHas : false;
	}

	//非高清预览【返回上传后直接转换过的文件】
	public function upload(){
		ignore_timeout();
		// 上传文件至office服务器，先下载至本地，地址入缓存（进度和重启需要）
		// $path = IO::tempLocalFile($this->filePath);
		$path = $this->plugin->pluginLocalFile($this->filePath);
		$post = array(
			"file"			=> "@".$path,
		);
		$task = new TaskHttp($this->task['taskUuid'],'plugin.yzOffice.upload',filesize($path));
		$result = url_request($this->api['upload'],'POST',$post,false,false,true,3600);
		// write_log(array('永中上传----', $this->api['upload'], $result, $path));
		return is_array($result) && $result['data'] ? $result : false;
	}
	public function convert($tempFile=false){
		$headers = array("Content-Type: application/x-www-form-urlencoded; charset=UTF-8");
		$tempFile = $tempFile?$tempFile:$this->task['steps'][0]['result']['data']['data'];
		$postArr = array(
			'srcRelativePath'	=> $tempFile,
			'convertType'		=> 61,
			'isDccAsync'		=> 1,
			'isCopy'			=> 1,
			// 'time'				=> 1800
			'isShowTitle'		=> 0,	// 显示文件名
			'isDelSrc'			=> 1,	// 删除源文件
		);
		$post = http_build_query($postArr);//post默认用array发送;content-type为x-www-form-urlencoded时用key=1&key=2的形式
		$result = url_request($this->api['convert'],'POST',$post,$headers,false,true,3600);
		// write_log(array('永中转换----', $this->api['convert'], $result, $postArr));
		if(is_array($result) && is_array($result['data'])){
			return $result;
		}
		return false;
	}

	public function clearCache(){
		Cache::remove($this->cacheTask);
		Task::kill($this->task['taskUuid']);
		IO::remove($this->cachePath, false);
	}

	public function uploadProcess(){
		return Task::get($this->task['taskUuid']);
	}
	public function getFile($file){
		ignore_timeout();
		$ext = unzip_filter_ext(get_path_ext($file));
		$cacheFile = $this->cachePath.md5($file.'file').'.'.$ext;
		if($info = IO::infoFull($cacheFile)){
			return IO::fileOut($info['path']);
		}
		$step     = count($this->task['steps']) - 1;
		$infoData = $this->task['steps'][$step]['result'];
		$link = $infoData['data'][0];
		$linkFile = get_path_father($link) . str_replace('./','',$file);
		$result = url_request($linkFile,'GET',false);
		if($result['code'] == 200){
			$cacheFile = $this->plugin->pluginCacheFileSet($cacheFile, $result['data']);
			IO::fileOut($cacheFile);
		}
	}
}
