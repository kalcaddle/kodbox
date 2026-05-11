<?php

/**
 * 存储导入：扫描已存文件数据写入数据库
 */

class storeImportPlugin extends PluginBase{
	function __construct(){
		parent::__construct();
	}
	public function regist(){
		$this->hookRegist(array(
			'user.commonJs.insert' => 'storeImportPlugin.echoJs',
		));
	}
	public function echoJs(){
		$this->echoFile('static/main.js');
	}

	public function api($path){
		$parse	= KodIO::parse($path);
		$id = $parse['id'];
		static $drvClass = array();
		if (!$drvClass[$id]) {
			$store = Model('Storage')->driverInfo($id);
			include_once($this->pluginPath.'lib/driver/DriverBase.class.php');
			$drvClass[$id] = new impDriver($store);
		}
		return $drvClass[$id];
	}

	// 数据导入
	public function start(){
		KodUser::checkRoot();
		// 0.任务进度
		$this->getProcess();

		// 1.导入检查
		$pathFrom = Input::get('pathFrom','require');
		$pathTo	  = Input::get('pathTo','require');
		$pathFrom = trim($pathFrom, '/');
        $pathTo	  = trim($pathTo, '/');
		$taskId   = Input::get('taskId', 'require');
		// 免费版限制
		if (Model('SystemOption')->get('versionType') == 'A') {
			show_json(LNG('admin.restore.needVipTips'), false);
		}

		// 1.1 检查原始目录
		$parse = KodIO::parse($pathFrom);
		if ($parse['type'] != KodIO::KOD_IO) {
			show_json(LNG('storeImport.main.ioPathErr'), false);
		}
		$info = Model('Storage')->listData($parse['id']);
		if (!$info) {
			show_json(LNG('storeImport.main.ioStoreErr'), false);
		}
		$type = strtolower($info['driver']);
		$ioList = $this->api($pathFrom)->ioList;
		if (!in_array($type, $ioList['sg']) && !in_array($type, $ioList['s3'])) {
			show_json(LNG('storeImport.main.ioNotSupErr').$info['driver'], false);
		}
		$chks = Model('Storage')->checkConfig($info, true);
		if ($chks !== true) {
			show_json(LNG('storeImport.main.ioFromNetErr').$chks, false);
		}

		// 1.2 检查目标目录
		$parse = KodIO::parse($pathTo);
		if ($parse['type'] != KodIO::KOD_SOURCE) {
			show_json(LNG('storeImport.main.ioToErr'), false);
		}

		// 2. 开始导入
		$this->doImport($pathFrom, $pathTo);

		$data = Cache::get($taskId);
		show_json($data, true, 1);
	}

	// 导入进度
	private function getProcess(){
		$taskId = Input::get('taskId', 'require');
		$task = Task::get($taskId);	// TODO task存在时为false，不存在为null，待确认
		if (!isset($this->in['process'])) {
			if ($task) {
				show_json(LNG('storeImport.task.rptErr'), false);
			}
			Cache::remove($taskId);
			return;
		}
		if ($this->in['kill']) {
			if (!$task) {$task = $this->taskGet($taskId);}	// 没有必要
			if ($task) $this->taskKill($task);
			show_json('Task killed.');
		}
		$info  = 0;
		$cache = Cache::get($taskId);
		if ($cache) {
			$task = $cache; $info = 1;
			Cache::remove($taskId);
		}
		show_json($task, true, $info);
	}

	// 杀死任务
	private function taskKill($task, $msg='') {
		if ($task['status'] == 1 || $task['taskPercent'] == 1) return;	// register_shutdown_function正常也会触发，无需kill
		if (in_array($task['status'], array('kill', 'error'))) return;	// 避免死循环

		// 杀掉任务
		$status = 'kill';
		$desc = LNG('storeImport.task.stopByUser');
		if ($msg) {
			$status = 'error';
			$desc = $msg;
		}
		$taskId = $task['id'];
		$task['taskPercent'] = 1;
		$task['status'] = $status;
		$task['desc'] = $desc;
		Cache::set($taskId, $task);
		Task::kill($taskId);

		// 更新日志记录
		// $info = Model('StoreImport')->findByKey('taskId', $taskId);
		$info = $this->taskGet($taskId, true);
		if (!empty($info['id'])) {
			$update = array('status' => 2, 'taskInfo' => $task);
			$this->logEdit($info['id'], $update);
		}
	}

	/**
	 * 存储导入
	 * @param [type] $pathFrom	{io:2}/oldpath	{io:2}=>/var/usr/data
	 * @param [type] $pathTo	{source:1}
	 * @return void
	 */
	public function doImport($pathFrom, $pathTo){
		ignore_timeout();
		// Hook::bind('show_json',array($this,'showJson'));	// 没有必要
		// （当前方法）正常调用结束、手动kill（？）、系统级错误等都会触发，注意后续处理逻辑
		register_shutdown_function(function () {
			$err = error_get_last();
			$msg = _get($err, 'message', '');
			$this->showJson(array('data' => '任务异常中止，错误信息：'.$msg, 'code' => false));
        });

		// 1. 开始任务
		$taskId = Input::get('taskId', 'require');
		$task	= new Task($taskId, 'storeImport', 0, LNG('storeImport.main.dataImport'));
		$this->writeLog('开始导入任务：from=>'.$pathFrom.'; to=>'.$pathTo);

		// 删除记录文件
		$errFile = $this->err255File($pathTo, $taskId);
		if ($errFile) IO::delFile($errFile);
		// 记录日志
		$logId = $this->logAdd($pathFrom, $pathTo, $task->task);

		// 2. 分块获取原目录下列表（生成器），并进行批量导入
		$this->writeLog('开始批量导入');
		include_once($this->pluginPath.'lib/batchImport.class.php');
		$import = new batchImport($task);	// 每次创建新的实例，避免内存累积——实际占用差别不大，改为共用

		$idx = 1;
		$task->task['currentTitle'] = LNG('storeImport.task.readdir').'-'.$idx;
		$task->update(0,true);
		$list = $this->api($pathFrom)->listPath($pathFrom, 200000);	// 500000反而慢
		foreach ($list as $i => $batch) {
			if (!$batch) continue;
			$this->writeLog('开始任务批次（'.$idx.'）');
			try {
				$res = $import->import($pathFrom, $pathTo, $batch);
			} catch (Exception $e) {
				$this->showJson(array('data' => $e->getMessage(), 'code' => false));
			}
			$this->writeLog('结束任务批次（'.$idx.'）');
			$idx++;
			$task->task['currentTitle'] = LNG('storeImport.task.readdir').'-'.$idx;
			$task->update(0,true);
		}
		$this->writeLog("完成批量导入，共导入文件：{$task->task['taskFinished']}/{$task->task['taskTotal']}");

		// 更新日志
		$update = array('status' => 1, 'taskInfo' => $task->task);
		$this->logEdit($logId, $update);

		// 3.统计因路径过长（>255）忽略导入的文件
		$logCnt = 0;
		$errFile = $this->err255File($pathTo, $taskId);
		if ($errFile) {
			$lines = IO::getContent($errFile);
			$lines = array_filter(explode(PHP_EOL, $lines));
			$logCnt  = count($lines);
			$this->writeLog("共有{$logCnt}个文件因路径长度超255字符导入失败");
		}

		// 4. 更新目标目录大小
		$this->writeLog('开始更新目录大小');
		$task->task['currentTitle'] = LNG('storeImport.task.updateSize');
		$task->update(0,true);
		$model = Model('Source');
		$parse = KodIO::parse($pathTo);
		$info = $model->pathInfo($parse['id'],true);
		$model->folderSizeResetChildren($info['sourceID']);
		if ($info['parentID']) {
			$model->folderSizeReset($info['parentID']);
		}
		$this->writeLog('完成更新目录大小');

		// 5. 结束任务
		// $task->task['taskPercent'] = 1;	// TODO 强制设为1；无效，保存前会重新计算；status=done也无效
		$task->task['currentTitle'] = LNG('storeImport.task.importEnd');
		// if ($logCnt) $task->task['desc'] = "有{$logCnt}个文件路径超长";
		if ($logCnt) $this->writeLog("{$pathTo}下有{$logCnt}个文件路径超出长度限制");
		$task->update(0,true);

		// 更新日志
		$update = array('status' => 1, 'taskInfo' => $task->task);
		$this->logEdit($logId, $update);

		Cache::set($taskId, $task->task);
		$task->end();

		// 6.更新MD5
		TaskQueue::add($this->pluginName.'Plugin.fileHashSet',array($pathFrom));

		$this->writeLog('结束导入任务：from=>'.$pathFrom.'; to=>'.$pathTo);
	}

	// 长度超出255字符的列表记录文件
	private function err255File($pathTo, $taskId) {
		// 文件夹
		$source = IO::fileNameExist($pathTo, LNG('storeImport.task.errLog'));
		if (!$source) return false;
		// 文件
		$name = 'task-'.$taskId.'.txt';
		$source = IO::fileNameExist(KodIO::make($source), $name);
		if (!$source) return false;
		return $source ? KodIO::make($source) : false;
	}

	// 更新io_file.hash；io文件内容有变更时，会导致md5不匹配——忽略
	public function fileHashSet($pathFrom=''){
		// 1.获取本地存储——仅更新本地存储
		$data = array();
		if ($pathFrom) {
			$parse = KodIO::parse($pathFrom);
			if ($parse['type'] != KodIO::KOD_IO) return;
			$store = Model('Storage')->listData($parse['id']);
			if (strtolower($store['driver']) != 'local') return;
			$data[] = $store['id'];
		} else { // 前端请求时更新全部，暂不开启
			return;
			$list = Model('Storage')->listData();
			foreach ($list as $item) {
				if (strtolower($item['driver']) != 'local') continue;
				$data[] = $item['id'];
			}
			if (!$data) return;
		}

		// 2.获取待更新的文件列表
		$defMd5 = 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';	// 默认md5，占位
		$where = array(
			'ioType'	=> array('in', $data),
			'hashMd5'	=> array('eq', $defMd5),
		);
		if ($pathFrom) {
			$where['ioType'] = $parse['id'];
			$where['path'] = array('like', "{$pathFrom}%");
		}
		$model = Model('File');
		$list = $model->where($where)->field('fileID,path')->select();

		$title = $pathFrom ? "目录（{$pathFrom}）下" : "全部";
		$this->writeLog("正在更新{$title}文件MD5，共：".count($list)."条记录");
		// 更新hash；每次重新获取判断，避免多任务重复
		foreach ($list as $item) {
			// 非全量请求不必单独查询
			$where = array(
				'fileID'	=> $item['fileID'],
			);
			// $info = $model->where($where)->field('path,hashMd5')->find();
			// if (!$info || !$info['path'] || $info['hashMd5'] != $defMd5) continue;
			$info = $item;
			$hashSimple = IO::hashSimple($info['path']);
			if (!$hashSimple) continue;
			$hashMd5 = IO::hashMd5($info['path']);
			if (!$hashMd5) continue;
			$update = array(
				'hashSimple'	=> $hashSimple,
				'hashMd5'		=> $hashMd5,
			);
			$model->where($where)->save($update);
		}
		// show_json('update '.count($list).' files.');
		$this->writeLog("完成更新{$title}文件MD5");
	}

	// 获取主任务show_json错误，更新任务
	public function showJson($result){
		if(!is_array($result)) return $result;
		if($result['code'] == true || $result['code'] == 1) return $result;

		$data = Input::getArray(array(
			'pathFrom'	=> array('default' 	=> ''),
			'pathTo'	=> array('default' 	=> ''),
			'taskId'	=> array('default' 	=> ''),
		));
		$errMsg = is_string($result['data']) ? $result['data'] : LNG('storeImport.task.stopErr');
		$taskId = $data['taskId'];

		// 正常结束、外部kill、系统级错误（应该是shutdown触发），此处均为false；throw err可获取——在此主动kill
		$task = Task::get($taskId);
		if (!$task) {$task = $this->taskGet($taskId);}
		$title = LNG('storeImport.task.importEnd');
		if ($task) {
			if ($task['status'] == '1' || $task['taskPercent'] == 1 || $task['currentTitle'] == $title) return;	// 正常结束
			$this->taskKill($task, $errMsg);
		}

		// $text = array(
		//     'from=>'.$data['pathFrom'].'; to=>'.$data['pathTo'] . '. error: ' . $errMsg,
		//     $this->in,
		//     $result,
		//     get_caller_info()
		// );
		// $this->writeLog($text);
		$desc = ($task['status'] == '1' || $task['currentTitle'] == $title) ? 'success.' : 'error: '.$errMsg;
		$this->writeLog('存储导入：from=>'.$data['pathFrom'].'; to=>'.$data['pathTo'] . '. 结果：' . $desc);
		return $result;
	}

	public function writeLog($msg) {
		$taskId = $this->in['taskId'] ? substr($this->in['taskId'], 0, 6) : '';
		$title	= $taskId ? "[{$taskId}]" : '';
		$data	= is_array($msg) ? array($title, $msg) : $title . $msg;
        write_log($data, $this->pluginName);
	}

	/**
	 * 导入历史记录
	 * @return void
	 */
	public function logGet($id=false){
		$list = Model('StoreImport')->listData($id);
		if ($id) return $list;

		$uids = array_to_keyvalue($list, '', 'userID');
		$userArray = Model('User')->userListInfo(array_unique($uids));
		$state = array(
			'0' => array('color' => 'grey',	 'text' => LNG('storeImport.task.notFinished')),
			'1' => array('color' => 'green', 'text' => LNG('storeImport.task.importOK')),
			'2' => array('color' => 'red',	 'text' => LNG('storeImport.task.importErr').LNG('storeImport.task.errDesc')),
			'-1'=> array('color' => 'orange','text' => LNG('storeImport.task.importEnd').LNG('storeImport.task.partDesc')),
		);
		foreach ($list as $i => &$item) {
			if (isset($userArray[$item['userID']])) {
				$item['userInfo'] = $userArray[$item['userID']];
			}
			if ($item['status'] == 1 && _get($item, 'taskInfo.taskPercent', 1) < 1) {
				$item['status'] = '-1';
			}
			$item['stateInfo'] = $state[$item['status']];
		};unset($item);

		show_json($list);
	}
	// 获取任务信息（或完整日志）
	public function taskGet($taskId, $log=false) {
		$info = Model('StoreImport')->findByKey('taskId', $taskId);
		return $log ? $info : _get($info, 'taskInfo', false);
	}
	public function logAdd ($pathFrom, $pathTo, $task) {
		$data = array(
			'userID' 		=> USER_ID,
			'pathFrom' 		=> $pathFrom,
			'pathTo'		=> $pathTo,
			'taskId'		=> $task['id'],
			'taskInfo'		=> $task,
			'status'		=> 0,	// 导入状态：0-未结束；1-完成；2-异常终止
			'createTime'	=> time(),
			'modifyTime'	=> 0
		);
		return Model('StoreImport')->add($data);
	}
	// 更新导入记录
	public function logEdit ($id, $update) {
		$info = Model('StoreImport')->edit($id, $update);
	}

}

/**
 * 存储导入记录
 */
class StoreImportModel extends ModelBaseLight{
	public $optionType 	= 'Store.importLogList';
	public $modelType	= "SystemOption";
	public $field		= array('userID','pathFrom','pathTo','taskInfo','status','taskId'); //value中的数据字段

	//默认正序
	public function listData($id=false,$sort='modifyTime',$sortDesc=true){
		return parent::listData($id,$sort,$sortDesc);
	}
	public function remove($id){ 
		return parent::remove($id);
	}
	public function add($data){
		return parent::insert($data);
	}
	public function edit($id,$data){
		return parent::update($id, $data);
	}
}