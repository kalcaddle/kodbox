<?php

/**
 * 存储导入：已存文件数据扫描写入数据库
 * OSS：sqlite+file：100w文件耗时6.5-7小时；mysql+redis：100w文件耗时3.5-4小时
 */

// TODO 待更新为列表分段写入缓存
class storeImportPlugin extends PluginBase{
	function __construct(){
		parent::__construct();
	}
	public function regist(){
		$this->hookRegist(array(
			'user.commonJs.insert'			=> 'storeImportPlugin.echoJs',
			'admin.storage.import.before'	=> 'storeImportPlugin.cliImport',
		));
	}
	public function echoJs(){
		$this->echoFile('static/main.js');
	}

	public function api($path){
		$parse	= KodIO::parse($path);
		$store = Model('Storage')->driverInfo($parse['id']);
		include_once($this->pluginPath.'lib/Driver.class.php');
		return new impDriver($store);
	}

	// 数据导入
	public function start(){
		KodUser::checkRoot();
		$pathFrom = Input::get('pathFrom','require');
		$pathTo	  = Input::get('pathTo','require');
		$pathFrom = trim($pathFrom, '/');
        $pathTo	  = trim($pathTo, '/');
		$taskId	  = $this->_taskId();

		// 0.任务进度
		$this->getProcess($pathFrom, $pathTo);

		// 1.1 检查原始目录
		$parse = KodIO::parse($pathFrom);
		if ($parse['type'] != KodIO::KOD_IO) {
			$this->showJson('原始数据目录错误，必须为存储目录：{io:x}', false);
		}
		$store = Model('Storage')->driverInfo($parse['id']);
		if (!$store) {
			$this->showJson('原始数据所在存储无效！', false);
		}
		$type = strtolower($store['driver']);
		$ioList = array(
			'sg' => array('local','oss','qiniu','uss'),	// ftp性能较差，暂不考虑支持
			's3' => array('s3','bos','cos','eds','eos','jos','minio','obs','oos','moss','nos')
		);
		if (!in_array($type, $ioList['sg']) && !in_array($type, $ioList['s3'])) {
			$this->showJson('不支持此存储类型导入：'.$store['driver'], false);
		}
		if (is_array($store['config'])) {	// 兼容旧版
			$store['config'] = json_encode($store['config']);
		}
		$check = Model('Storage')->checkConfig($store, true);
		if ($check !== true) {
			$this->showJson('原始数据所在存储无法连接。'.$check, false);
		}
		if ($type != 'local') $this->in['hash'] = 0;

		// 1.2 检查目标目录
		$parse = KodIO::parse($pathTo);
		if ($parse['type'] != KodIO::KOD_SOURCE) {
			$this->showJson('网盘存放目录错误，必须为网盘系统目录：个人空间或部门及其子目录', false);
		}

		// 2. 开始导入
		$this->doImport($pathFrom, $pathTo);

		$data = Cache::get($taskId);
		$this->showJson($data, true, 1);
	}

	// 导入进度
	private function getProcess($pathFrom, $pathTo){
		$taskId = $this->_taskId();
		$task = Task::get($taskId);	// TODO task存在时为false，不存在为null，待确认
		if (!isset($this->in['process'])) {
			if ($task) $this->showJson('任务执行中，请勿重复操作！', false);
			Cache::remove($taskId);
			return;
		}
		if ($this->in['kill']) {
			if (!$task) $task = array('taskPercent' => 1);
			$task['desc'] = '任务手动终止！';
			$task['status'] = 'kill';
			Cache::set($taskId, $task);	// 避免process继续请求
			Task::kill($taskId);
			$this->showJson('Task killed.');
		}
		$info  = 0;
		$cache = Cache::get($taskId);
		if ($cache) {
			$task = $cache; $info = 1;
			Cache::remove($taskId);
		}
		$this->showJson($task, true, $info);
	}

	/**
	 * 存储导入
	 * @param [type] $pathFrom	{io:2}/oldpath	{io:2}=>/var/usr/data
	 * @param [type] $pathTo	{source:1}
	 * @return void
	 */
	public function doImport($pathFrom, $pathTo){
		ignore_timeout();
		Hook::bind('show_json',array($this,'showErrorCheck'));
		$parseFrom = KodIO::parse($pathFrom);
		$parseTo = KodIO::parse($pathTo);
		$this->ioType = $parseFrom['id'];	// 原存储io(id)
		// $checkHash = intval($this->in['hash']);

		// 0. 开始输出提示
		$text = '存储导入数据，开始：from=>'.$pathFrom.'; to=>'.$pathTo;
		$this->webEcho($text);
		$this->cliEcho($pathFrom, $text, 0);

		// 1. 开始任务
		$taskId = $this->_taskId();
		$task	= new Task($taskId, 'storeImport', 0, '存储数据导入');
		$info = $this->pathTotalInfo($pathFrom);
		$task->task['taskTotal'] = $info['total'];

		// 1.1 初始化：io_file缓存、日志文件
		$this->ioFileCache($pathFrom, 'init');	// 原目录io_file记录——缓存初始化
		$this->wrt256Log($pathFrom, $pathTo, 0);	// 路径长度超出，记录日志

		// 1.2 获取原目录文件列表
		$GLOBALS['STORE_IMPORT_FILE_CNT'] = 0;	// 指定目录下文件总数——动态变化；使用缓存的问题：数据落地不及时、保持key一致比较麻烦
		// 原存储driver ——$this->api()获取的driver无法随意调用父类方法，原因未知
		$driver = IO::init($pathFrom);
		$list = $this->api($pathFrom)->listAll($pathFrom);

		// TODO 中途中断不退出
		$GLOBALS['SHOW_JSON_NOT_EXIT'] = 1;

		// 2. 开始导入
		$fid  = 0;
		$this->pathIdx = $idx = 0;
		$rest = array('success'=>0, 'error'=>0);
		$sModel = Model('Source'); $fModel = Model('File');
        foreach ($list as $item) {
			$name  = get_path_this($item['path']);
			// $task->task['taskTotal'] = $GLOBALS['STORE_IMPORT_FILE_CNT'];
			$task->task['currentTitle'] = $name;
			$task->update(1);
			$this->pathIdx++;
			$this->cliEcho($pathFrom);

			// 每10000条刷新一下大小
			$idx++;
			if ($idx > 10000) {
				$idx = 0;
				$sModel->folderSizeResetChildren($parseTo['id']);
			}
			// TODO 可以考虑按完整层级存文件夹id，在此目录下新增文件/夹
			// $ccKey = $taskId.'_'.md5($item['path']);
			// if (Cache::get($ccKey)) continue;

			// 获取io_source.path、io_file.path
			$path  = $item['path'];						// 原数据绝对路径：/var/usr/data/oldpath/home/1.txt
            $fPath = $driver->getPathOuter($path);		// io_file.path：{io:2}/oldpath/home/1.txt
            $tPath = substr($fPath, strlen($pathFrom));	// 临时路径：/home/1.txt
            $sPath = $pathTo.'/'.trim($tPath,'/');		// io_source.path：{source:1}/home/1.txt；对目标目录而言，一级子目录为原数据指定目录下的一级子目录，即home

            // 1. 文件夹创建
            if ($item['folder'] == 1) {
                $res = IO::mkdir($sPath);
				$this->writeLog($res, $path, $rest, 'folder');
				// if ($res) Cache::set($ccKey, 1);
                continue;
            }

            // 2. 文件创建——添加io_source/io_file记录
			// 2.0 path过长，拦截
			if (mb_strlen($fPath) > 256) {
				$this->wrt256Log($pathFrom, $pathTo, $fPath);
				$this->writeLog(false, 'path length > 256.', $rest);
				// Cache::set($ccKey, 1);
				continue;
			}
            // 2.1 IO::mkfile($path)创建文件，得到sourceID——多一条io_file空文件记录
			// 判断io_file是否存在，不存在，执行完整流程；存在，检查io_source——存在且fileID相等，跳过；不存在，新建source记录并增加file引用数
			$find = $this->ioFileCache($fPath);	// [fileID=xx,linkCount=>1]
			if ($find) {
				$info = IO::infoFullSimple($sPath);
				if ($info && $info['fileID'] == $find['fileID']) {
					$this->writeLog(true, $path, $rest);
					// if ($res) Cache::set($ccKey, 1);
					continue;
				}
			}
			// 新建io_source记录
			$res = $sPath = IO::mkfile($sPath);
			if (!$this->writeLog($res, $path, $rest)) continue;
			if (!$fid) {	// 获取空文件fileID ——IO::mkfile持续引用此文件，完成后需重置引用数
				$info = IO::infoSimple($res);
				$fid  = _get($info, 'fileID', 0);
			}
			$parse = KodIO::parse($res);
            $sourceID = $parse['id'];

            // 2.2 io_file新增/更新，获得fileID
			$size = $item['size'];
			if ($find) {
				$data = array(
                    "size"      => $size,
                    "linkCount"	=> intval($find['linkCount']) + 1,
                );
                $res = $fModel->where(array("fileID"=>$find['fileID']) )->save($data);
                $fileID = $res ? $find['fileID'] : 0;
			} else {
				$data = array(
					"size" 		=> $size,
					"linkCount"	=> 1,
					"name"		=> $name,
					"ioType"	=> $this->ioType,
					"path" 		=> $fPath,
					"hashSimple"=> '',	// 置空，避免缩略图异常（KodIO::hashPath）
					"hashMd5" 	=> 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'	// 占位，避免触发追加md5任务
				);
				// if ($checkHash) {
				// 	$data['hashSimple'] = $driver->hashSimple($path);
				// 	$data['hashMd5'] = $driver->hashMd5($path);
				// }
				$fileID = $fModel->add($data);
			}
			if (!$fileID) {
				$this->writeLog($fileID, $path, $rest);
				IO::remove($sPath, false);	// io_file添加失败，删除该文件
				$rest['success']--;
				continue;
			}

            // 2.3 更新io_source.fileID/size
			$update = array('fileID'=>$fileID,'size'=>$size,'modifyTime'=>$item['modifyTime']);
            $res = $sModel->where(array("sourceID"=>$sourceID))->save($update);
			if (!$res) {
				$this->writeLog($fileID, $path, $rest);
				IO::remove($sPath, false);	// io_source更新失败，删除该文件
				$rest['success']--;
				continue;
			}
			// Cache::set($ccKey, 1);
        }
		$GLOBALS['SHOW_JSON_NOT_EXIT_DONE'] = 0;
		$GLOBALS['SHOW_JSON_NOT_EXIT'] = 0;

		// 3. 更新空文件记录引用数
		if ($fid) {
			$find	= $fModel->find($fid);
			$cnt	= (int) $sModel->where(array('fileID'=>$fid))->count();
			$update	= array('linkCount'	=> ($cnt ? $cnt : 1));
			$fModel->where(array('fileID'=>$fid))->save($update);
		}
		// 4. 更新目标目录大小
		$info = $sModel->pathInfo($parseTo['id'],true);
		$sModel->folderSizeResetChildren($info['sourceID']);
		if ($info['parentID']) {
			$sModel->folderSizeReset($info['parentID']);
		}

		// 5. 结束任务
		$task->task['taskPercent'] = 1;	// 强制设为1
		$task->task['desc'] = '成功:'.$rest['success'].',失败:'.$rest['error'];
		$msg = '总记录:'.$task->task['taskTotal'] .','. $task->task['desc'];
		Cache::set($taskId, $task->task);
		$task->end();

		// 5.1 日志文件保存、io缓存清除
		$this->wrt256Log($pathFrom, $pathTo, 1);
		$this->ioFileCache($pathFrom, 'clear');
		
		// 5.2 提示输出：页面、cli
		$text = '存储导入数据，完成：from=>'.$pathFrom.'; to=>'.$pathTo.'。'.$msg;
		$this->webEcho($text);
		$this->cliEcho($pathFrom, $msg, 1);

		return $rest;
	}

	// 获取指定目录信息=>size、total——含文件夹
	private function pathTotalInfo($path) {
		if (isset($this->totalInfo)) return $this->totalInfo;
		$info = IO::infoWithChildren($path);
		$size = _get($info, 'size', 0);
		$fileNum	= _get($info, 'children.fileNum');
		$folderNum	= _get($info, 'children.folderNum');
		$this->pathTotal = intval($fileNum) + intval($folderNum);
		$this->totalInfo = array(
			'size'  => _get($info, 'size', 0),
			'total' => $this->pathTotal
		);
		return $this->totalInfo;
	}

	/**
	 * io_file数据缓存
	 * @param [type] $path	{io:1}、{io:1}/a/b/c/1.txt
	 * @param string $act	init/set/clear
	 * @return void
	 */
	private function ioFileCache($path='', $act='get'){
		$baseKey = 'io_file_'.$this->_taskId();	// io_file_xxx
		// 清除缓存
		if ($act == 'clear') {
			$list = Cache::get($baseKey);
			if (!$list) return false;
			foreach ($list as $cckey) {
				Cache::remove($cckey);
			}
			Cache::remove($baseKey);
			return;
		}
		// 根据path获取缓存
		if ($act == 'get') {
			$list = Cache::get($baseKey);
			if (!$list) return false;
			$md5 = md5($path);
			foreach ($list as $cckey) {
				$cache = Cache::get($cckey);
				if (isset($cache[$md5])) return $cache[$md5];
			}
			return false;
		}
		// 初始化缓存
		if ($act != 'init' || !$path) return;
		$kcache = array();	// 分页键名数组缓存
		$model = Model('File');
		$page  = 1; $pageNum = 5000;
		$where = array('ioType' => $this->ioType, 'path' => array('like', $path.'/%'));	// {io:x}
		$list  = $model->where($where)->selectPage($pageNum,$page);
		while($list && $page <= $list['pageInfo']['pageTotal']){
			$cache = array();
			foreach ($list['list'] as $item) {
				$md5 = md5($item['path']);
				$cache[$md5] = array('fileID'=>$item['fileID'],'linkCount'=>$item['linkCount']);
			}
			if ($cache) {
				$cckey = $baseKey.'_p'.$page;	// io_file_xxx_p1
				Cache::set($cckey, $cache);
				$kcache[] = $cckey;
			}
			$page++;$list = $model->where($where)->selectPage($pageNum,$page);
		}
		if ($kcache) Cache::set($baseKey, $kcache);
	}

	// 路径长度超出256，写入日志
	private function wrt256Log($pathFrom, $pathTo, $path='') {
		// 1.初始化任务key
		if ($path === 0) {
			$this->logName = $this->_taskId().'-'.date('His');	// xxx-172027
			return;
		}
		// 2.写入日志
		if ($path !== 1) {
			$temp = explode('-', $this->logName);
			write_log('('.$temp[1].') '.$path, $this->pluginName, 'log-'.$this->logName);
			return;
		}
		// 3.日志文件移动到目标目录
		$log = false;
		$logPath = IO::mkdir($pathTo.'/导入失败日志-长度超256字符');

		$path = LOG_PATH.strtolower($this->pluginName);
		$list = IO::listPath($path,true);
		foreach ($list['fileList'] as $item) {
			if (!strstr($item['name'], $this->logName)) continue;
			$log = true;
			write_log('move error log to dest: '.$item['path'].'=>'.$logPath, $this->pluginName);
			IO::move($item['path'], $logPath);
		}
		if (!$log) IO::remove($logPath);
	}
	// 页面输出，记录日志
	private function webEcho($msg) {
		if (!isset($this->echoLog)) {
			$this->echoLog = intval($this->in['echoLog']);
		}
		if ($this->echoLog) echoLog($msg);
		write_log($msg, $this->pluginName);
	}
	// 错误日志
	private function writeLog($res, $path, &$rest, $type='file') {
		if ($this->echoLog) {
			$total = $GLOBALS['STORE_IMPORT_FILE_CNT'];
			echoLog('['.$this->pathIdx.'/'.$total.'].导入'.($res ? '成功' : '失败').'('.$type.').'.$path, ($res ? true : false));
		}
		if ($res) {
			$rest['success']++; return true;
		}
		write_log('create '.$type.' error: '.$path, $this->pluginName);
		$rest['error']++; return false;
	}

	// 任务id
	private function _taskId($pathFrom='', $pathTo='') {
		static $taskId;
		if (!$taskId) {
			$pathFrom = trim($this->in['pathFrom'], '/');
			$pathTo	  = trim($this->in['pathTo'], '/');
			$taskId	  = md5('store_import_'.$pathFrom.'_'.$pathTo);
		}
		return $taskId;
	}

	// 获取主任务show_json错误，更新任务
	public function showErrorCheck($result){
		$this->showJsonError = true;
		if(!is_array($result)) return $this->showJson($result);
		if($result['code'] == true || $result['code'] == 1) return $this->showJson($result);

		$pathFrom = trim($this->in['pathFrom'], '/');
        $pathTo	  = trim($this->in['pathTo'], '/');
		$errMsg	  = is_string($result['data']) ? $result['data'] : '异常终止！';
		if ($pathFrom && $pathTo) {
			$taskId	  = $this->_taskId();
			$task	  = Task::get($taskId);
			if (!$task) $task = array('taskPercent' => 1);
			$task['desc'] = $errMsg;
			$task['status'] = 'error';
			Cache::set($taskId, $task);
			Task::kill($taskId);
		}
		$text = array(
		    '存储导入数据，异常中断：from=>'.$pathFrom.'; to=>'.$pathTo,
		    $this->in,
		    $result,
		    get_caller_info()
		);
		write_log($text, $this->pluginName);
		return $this->showJson($result);
	}

	// 终端执行进度消息
	private function cliProMsg(){
		$idx = $this->pathIdx;		// 当前文件数
		$cnt = $this->pathTotal;	// 文件总数
		$pct = $idx / $cnt;

		// 已用时间（秒）
		$timeUse = timeFloat() - $this->timeStart;
		// 估算总时间 = 已用时间 / 进度百分比
		$timeTotal = $timeUse / $pct;
		// 剩余时间 = 估算总时间 - 已用时间
		$timeRem = $timeTotal - $timeUse;

		$now = str_pad($idx, strlen($cnt), ' ', STR_PAD_LEFT);	// 占位，避免内容抖动
		$rto = str_pad(round($pct * 100, 1), 5, ' ', STR_PAD_LEFT);
		return '('.$now.'/'.$cnt.')'.$rto.'% | 剩余时间约：'.$this->timeNeedFormat($timeRem);
	}
	// 剩余时间（s）格式化
	private function timeNeedFormat($sTime=0) {
		$h = floor($sTime / 3600);	// 小时数
		$m = floor(($sTime % 3600) / 60);	// 剩余分钟数
		$s = $sTime % 60;	// 剩余秒数
		$time = '';
		if ($h > 0) $time .= $h . '小时';
		if ($m > 0) $time .= $m . '分';
		if ($s > 0 || ($h == 0 && $m == 0)) {
			$time .= $s . '秒';
		}
		return $time;
	}
	private function cliEcho($pathFrom, $msg='',$code=false) {
		if (!$this->isCli) return;
		// 3.任务结束
		if ($code === 1) {
			echo PHP_EOL.LNG('explorer.success').$msg.PHP_EOL;exit;
		}
		// 2.进度输出：( 23/999) 2.3% | 剩余时间：1小时15分32秒
		if ($code === false) {
			static $idx = 0;
			$idx++;
			// 非末位且小于指定范围的随机值，则不输出——用时间间隔效果更好，但timeFloat相对消耗更多
			if ($this->pathIdx < $this->pathTotal && $idx < mt_rand(50,100)) return;
			$idx = 0;

			echo "\r";	// 清除当前行
			echo str_pad($this->cliProMsg(), 100);	// 占位，避免未替换掉过长的内容
			ob_flush(); flush();	// 强制刷新输出
			return;
		}
		// 1.任务开始
		ob_implicit_flush(true);	// 禁用输出缓冲

		// 1.1 输出标题
		// 存储导入数据：from=>{io:xx}; to=>{source:xx}
		// 准备导入... => 正在导入...(35.4GB)
		echo str_replace('，开始', '', $msg).PHP_EOL;
		echo '准备导入...(读取中，请稍候)';
		ob_flush(); flush();
		
		// 1.2 统计文件总数
		$info = $this->pathTotalInfo($pathFrom);
		$size = size_format($info['size']);
		$this->timeStart = timeFloat();

		echo "\r";
		echo '正在导入...('.str_pad($size,3).')'.PHP_EOL;
	}

	// 终端执行导入
	// TODO ctrl+c不会杀掉任务（但似乎暂停中），可以监听主动杀掉
	public function cliImport(){
		$this->isCli = true;
		if (!is_cli() || !isset($_SERVER['argv'])) {
			$this->showJson('非法请求！', false);
		}
		$args = $this->cliArgs();
		if (!$args || empty($args['pathFrom']) || empty($args['pathTo'])) {
			$this->showJson('无效的参数！', false);
		}
		if (!KodUser::isLogin()) $this->showJson('登录状态已失效，请求重新获取执行命令。',false);
		if (!KodUser::isRoot()) $this->showJson('您无权执行此操作！',false);
		$this->in['pathFrom'] = $args['pathFrom'];
		$this->in['pathTo'] = $args['pathTo'];
		$this->start();
	}
	private function cliArgs($key=false){
        $argv = $_SERVER['argv'];
        if (!is_array($argv)) return array();
		array_shift($argv);
    	array_shift($argv);	// 移除前2个参数：./index.php、admin/storage/import&accessToken=xxx
    	$name = null;
		$args = array();
		foreach ($argv as $arg) {
			if (!substr($arg, 0, 2) == '--') continue;
			$tmp = explode('-', ltrim($arg, '--'));
			$args[$tmp[0]] = isset($tmp[1]) ? $tmp[1] : '';
		}
		return $args;
    }
	
	// show_json，兼容终端执行
	public function showJson($data,$code=true,$info=null) {
		// web
		if (!$this->isCli) {
			if ($this->showJsonError) return $data;
			show_json($data, $code, $info);
		}
		// cli
		$lf = '';	// 换行
		if ($this->showJsonError) {
			$lf = PHP_EOL;
			if (!is_array($data)) {
				$code = false;
			} else {
				$data = _get($data, 'data', '');
				$code = _get($data, 'code'); 
			}
			if (!$data || !is_string($data)) $data = json_encode($data);
		}
		$msg = $code ? LNG('explorer.success') : LNG('explorer.error');
		echo $lf.$msg.$data.PHP_EOL;exit;
	}

}