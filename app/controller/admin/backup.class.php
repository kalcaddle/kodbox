<?php
// 数据备份
class adminBackup extends Controller{
	private $model;
	function __construct()    {
		parent::__construct();
		$this->model = Model('Backup');
	}

	/**
	 * 初始化备份计划任务
	 * @return void
	 */
	public function taskInit($config=false){
		if (MOD.'.'.ST != 'admin.backup') return;	// 仅手动操作请求可修改任务（计划任务获取的状态值可能滞后）
		$optModel = Model('systemOption');
		$tskModel = Model('SystemTask');
		// 获取配置
		if (!$config) $config = $this->model->configSimple();

		// 初始化标识
		$autoTaskKey = 'autoTaskInit';
		$fileTaskKey = 'fileTaskInit';
		$isTaskInit = array(
			$autoTaskKey => $optModel->get($autoTaskKey, 'backup'),
			$fileTaskKey => $optModel->get($fileTaskKey, 'backup'),
		);
		$taskEvent = array(
			$autoTaskKey => 'admin.backup.autoTask',
			$fileTaskKey => 'admin.backup.fileTask',
		);

		// 1.没有启用，删除任务
		if ($config['enable'] != '1') {
			foreach ($isTaskInit as $initKey => $initVal) {
				if($initVal != 'ok') continue;
				$this->taskRemove($tskModel,$optModel,$initKey);
			}
			return;
		}
		// 2.有启用，更新原任务（兼容旧版）
		if($optModel->get('autoTaskUpdate','backup') != 'ok') {
			// 旧任务时间，可以直接删除，但还得在添加时更新（时间）
			$task = $tskModel->findByKey('event','admin.backup.start');
			if ($task) {
				$update = array(
					'event'		=> 'admin.backup.autoTask',
					'desc'		=> LNG('admin.backup.taskDbDesc'),
					'enable'	=> 1,
				);
				$tskModel->update($task['id'], $update);
				$isTaskInit[$autoTaskKey] = 'ok';
				$optModel->set($autoTaskKey,'ok','backup');
			}
			$optModel->set('autoTaskUpdate','ok','backup');
		}
		// 3.添加任务
		// 授权失效后已存在的文件备份任务应该删除，考虑到内容已切换，可以不处理
		foreach ($isTaskInit as $initKey => $initVal) {
			// 仅sql备份时，不添加/删除文件备份任务
			if ($initKey == $fileTaskKey && $config['content'] != 'all') {
				if ($initVal == 'ok') {
					$this->taskRemove($tskModel,$optModel,$initKey);
				}
				continue;
			}
			if ($initVal == 'ok') continue;
			$data = $this->taskInitData($initKey);
			if(!$tskModel->add($data)) return;
			$optModel->set($initKey,'ok','backup');
		}
	}
	private function taskRemove($tskModel,$optModel,$initKey) {
		$taskEvent = array(
			'autoTaskInit' => 'admin.backup.autoTask',
			'fileTaskInit' => 'admin.backup.fileTask',
		);
		$task = $tskModel->findByKey('event', $taskEvent[$initKey]);
		if ($task) $tskModel->remove($task['id'],true);
		$optModel->set($initKey, '', 'backup');
	}
	private function taskInitData($taskKey) {
		$data = array(
			'autoTaskInit' => array(
				'name'	=> LNG('admin.task.backup'),
				'type'	=> 'method',
				'event' => 'admin.backup.autoTask',
				'time'	=> '{"type":"day","day":"02:00"}',
				'desc'	=> LNG('admin.backup.taskDbDesc'),
				'enable' => 1,
				'system' => 1,
			),
			'fileTaskInit' => array(
				'name'	=> LNG('admin.task.backup').' ('.LNG('common.file').')',
				'type'	=> 'method',
				'event' => 'admin.backup.fileTask',
				'time'	=> '{"type":"minute","minute":1}',
				'desc'	=> LNG('admin.backup.taskFileDesc'),
				'enable' => 1,
				'system' => 1,
			),
		);
		return $data[$taskKey];
	}

	/**
	 * 计划任务配置信息
	 * @return void
	 */
    public function config(){
		// 0.前端（其他）请求
		$this->bakConfig();
		// 1.获取备份配置信息
		$data = $this->model->config(true);
		// pr($data);exit;
		if (!$data) {
			show_json(LNG('admin.backup.errInitTask'), false);
		}
		// 2.获取当前数据库类型
		$database = array_change_key_case($GLOBALS['config']['database']);
		$data['dbType'] = Action('admin.server')->_dbType($database);	// mysql/sqlite
		// 3.获取最近一条备份记录
		$last	= $this->model->lastItem();
		if($data['enable'] != '1') {
		    $process= null;
		    if(isset($last['status'])) $last['status'] = 1;
		} else {
		    $process= $this->model->process();	// 备份进度
		}
		$info	= array('last' => $last, 'info' => $process);
		show_json($data, true, $info);
	}
	private function bakConfig(){
		// 获取最近一条备份记录
		if (Input::get('last',null,0) == '1') {
			$last = $this->model->lastItem();
			// if($last && $last['name'] != date('Ymd')) $last = null;
			show_json($last);
		}
		if (Input::get('check',null,0) != '1') return;
		// 检查备份是否有效
		$io = Input::get('io', 'int');
		$check = $this->checkStore($io);
		if ($check !== true) {
			show_json($check, false);
		}
		// 检查是否存在系统数据
		$cnt = Model('File')->where(array('ioType'=>$io))->count();
		if ($cnt) {show_json(LNG('admin.backup.addStoreHasFile'), false);}
	}

	/**
	 * 获取备份列表
	 */
	public function get() {
		$id		= Input::get('id',null,null);
		$result	= $this->model->listData($id);
		$info	= $id ? $this->model->process() : array();
		if (!$id) $this->_getDataApply($result);
		show_json($result,true, $info);
	}
	// 追加备份所在存储，便于识别管理
	private function _getDataApply(&$data){
		if (empty($data)) return;
		$list = Model('Storage')->listData();
		$list = array_to_keyvalue($list, 'id', 'name');
		foreach ($data as &$item) {
			$io = $item['io'];
			$item['ioName'] = isset($list[$io]) ? $list[$io] : '0';
		}
	}

	/**
	 * 删除备份记录
	 */
	public function remove() {
		$id  = Input::get('id','int');
		$res = $this->model->remove($id);
		$msg = $res ? LNG('explorer.success') : LNG('explorer.error');
		show_json($msg,!!$res);
    }
	
	// 激活授权,自动开启备份;(没有开启时,设置仅备份数据库;备份到默认存储)
	public function initStart($status){
		// 1.获取配置信息，已激活则不处理——计划任务不一定开启，暂不处理
		$backup = Model('SystemOption')->get('backup');
		$backup = json_decode($backup, true);
		if (!$backup) $backup = array();
		if ($backup['enable'] == '1') return;

		// 2.添加/更新（激活）配置
		$driver = KodIO::defaultDriver();
		$backup['io'] = $driver['id'];
		$backup['content'] = 'sql';	// 备份内容：all/sql
		$backup['enable'] = 1;
		Model('SystemOption')->set('backup', $backup);

		// 3.添加并激活计划任务
		$this->taskInit($backup);
	}

	/**
	 * 计划任务
	 * @return void
	 */
	public function autoTask() {
		if (!KodUser::isRoot()) return;
		return $this->start(true);
	}
	/**
	 * 计划任务（文件）
	 * @return void
	 */
	public function fileTask() {
		if (!KodUser::isRoot()) return;
		return $this->start(true, 'file');
	}

    /**
	 * 备份——终止http请求，后台运行
	 * @param boolean $runTask
	 * @param boolean $type	备份内容：db/file，默认为db
	 * @return void
	 */
    public function start($runTask=false,$type=''){
		// 0.获取备份内容/类型
		if (empty($type)) {
			$type = Input::get('type',null,'db');
		}
		// 手动文件备份获取进度
		if ($type == 'file' && $this->in['manual'] == '1' && $this->in['process'] == '1') {
			$this->manualProcess();
			return;
		}

		// 1.检查备份是否开启
		$config = $this->model->config();
		if($config['enable'] != '1') {
			if ($runTask) return;
			show_json(LNG('admin.backup.notOpen'), false);
		}

		// 2.检查存储是否有效
		// 2.1 检查是否为默认存储——文件备份
		if ($type == 'file') {
			$driver = KodIO::defaultDriver();
			if ($driver['id'] == $config['io']) {
				if ($runTask) return;
				show_json(LNG('admin.backup.needNoDefault'), false);
			}
		}
		// 2.2 手动文件备份：检查是否有任务在进行中
		// 在 checkStore() 之前就登记"手动执行已开始"，避免长期阻塞导致Task、活动标记未写入而获取不到进度
		$manualOwned = false;
		if ($type == 'file' && $this->in['manual'] == '1') {
			$manualOwned = BackupFile::manualStart();
			if (!$manualOwned) {
				// 已有另一个活跃的手动执行：直接拒绝（避免两个手动任务并行，也避免互相覆盖进度）
				if ($runTask) return;
				show_json(LNG('explorer.error').LNG('admin.backup.taskAlready'), false);
			}
		}
		// 2.3 检查存储是否有效——存储检测比较耗时，计划任务直接跳过（实例中检测）
		$check = ($runTask && $type == 'file') ? true : $this->checkStore($config['io']);
		if ($check !== true) {
			if ($manualOwned) BackupFile::manualFinish($check);	// 让轮询也能看到失败原因，而不是一直"准备中"
			if ($runTask) return;
			show_json($check, false);
		}

		// 3.检查临时目录是否可写——数据库备份
		if ($type != 'file') {
			mk_dir(TEMP_FILES);
			if(!path_writeable(TEMP_FILES)) {
				show_json(LNG('admin.backup.pathNoWrite'), false);
			}
		}

		// 4.开始备份
		$showExp = _get($GLOBALS, 'SHOW_OUT_EXCEPTION', false);
		$GLOBALS['SHOW_OUT_EXCEPTION'] = true;
		try {
			$res = $this->model->start($type);
		} catch(Exception $e) {
			$msg = $e->getMessage();
			if ($type != 'file') {
				Backup::set(array('status' => 1, 'timeTo' => time()));	// 强制结束任务
				Backup::log('数据库备份失败，任务异常中止：' . $msg);
			} else {
				write_log('备份异常中止：' . $msg, 'backup-file');
				write_log('=== 文件备份结束，备份失败 ===', 'backup-file');
				// 异常时 BackupFile 的析构可能已经跑过（栈展开先销毁对象），manualFinish 会允许"标记已清但仍是本人"时补写消息
				if ($manualOwned) BackupFile::manualFinish(LNG('admin.backup.errAbort').': '.$msg);
			}
			$res = false;
		}
		$GLOBALS['SHOW_OUT_EXCEPTION'] = $showExp;	// 恢复
		if ($runTask) return $res;
		if ($res === true) {
			// 成功但有更具体说明（如“已有备份任务在运行”）时优先返回
			$msg = $this->model->message ? $this->model->message : LNG('explorer.success');
			if ($this->model->runningTask) {
				// 已有任务被继续执行：任务仍在跑，用 info.running=true 让前端转进度轮询。其余成功分支说明本次执行已经结束，前端直接提示并刷新
				show_json($msg, true, array('running' => true));
			}
			show_json($msg);
		} else {
			if (!isset($msg)) $msg = $this->model->message;
			show_json(LNG('explorer.error').$msg, false);
		}
    }
	// 检查存储是否有效
	private function checkStore($io){
		$model = Model('Storage');
		$data = $model->listData($io);
		if (!$data) show_json(LNG('admin.backup.storeNotExist'), false);
		return $model->checkConfig($data, true);
	}
    
	/**
	 * 手动文件备份进度
	 * 只看手动备份：fileTaskInfo.manualRunAt（BackupFile::manualStart 在 checkStore 之前登记、执行期间每次落盘刷新）与 manualMessage（本次手动的结果/中止原因）。
	 * 计划任务的（runAt / message / 计划任务的 Task）与手动共用同一份记录和同一个 Task id，绝不能当手动的进度用。
	 * 1.manualRunAt 新鲜 + 手动 Task 已创建：running（用 Task 显示动态进度）
	 * 2.manualRunAt 新鲜 + 没有手动 Task：starting（存储检查中 / 等待计划任务结束）
	 * 3.manualRunAt=0：finished（正常结束 / 熔断中止 / 提前失败）
	 * 4.manualRunAt 长时间未刷新：finished，并提示"上次进程已中断"（被强杀/超时）
	 */
	private function manualProcess(){
		$task = Task::get('backup.file.new');
		$info = json_decode(Model('SystemOption')->get('fileTaskInfo', 'backup'), true);
		if (!is_array($info)) $info = array();

		$stale     = BackupFile::STALE_LIMIT;
		$mRunAt    = intval(_get($info, 'manualRunAt', 0));
		$mAlive    = ($mRunAt > 0 && (time() - $mRunAt) <= $stale);
		$hasTask   = ($task && _get($task, 'status', '') == 'running');
		$isMyTask  = ($hasTask && _get($task, 'type', '') === 'backup-manual');   // 只有手动任务才算"本次手动执行"
		$dead      = false;
		if ($isMyTask) {
			$state = 'running'; // 手动任务已创建：返回 Task，前端据此显示动态进度
		} elseif ($mAlive) {
			$state = 'starting'; // 已登记手动执行、任务还没建（存储检查中 / 等待计划任务结束）：继续轮询
		} else {
			$state = 'finished'; // 本次手动执行已结束（正常结束 / 熔断中止 / 提前失败）
			// 手动标记有值但不新鲜 = 进程没走完收尾就被杀（崩溃/OOM/超时），结果可能不完整
			if ($mRunAt > 0) $dead = true;
		}
		$result  = _get($info, 'result', array());
		$current = _get($info, 'current', array());
		$message = (string) _get($info, 'manualMessage', '');
		if ($dead) {
			$message = trim($message.' '.LNG('admin.backup.taskDead'));
		}
		$more = array(
			'state'    => $state,	// 'running'（手动任务进行中），'starting'（已登记、任务未建），'finished'（本次手动执行已结束）
			'running'  => ($state !== 'finished'),	// 是否未结束（true 时前端继续轮询）
			'finished' => ($state === 'finished'),	// 是否已结束（true 时前端停止轮询并展示结果）
			'message'  => ($state === 'finished') ? $message : '',	// 结束时的提示/失败原因/汇总（取 manualMessage）；进行中恒为 ''（避免旧消息干扰）
			'result'   => array(	// 累计统计
				'total'        => intval(_get($result, 'total', 0)),
				'finished'     => intval(_get($result, 'finished', 0)),
				'failed'       => intval(_get($result, 'failed', 0)),
				'sizeTotal'    => intval(_get($result, 'sizeTotal', 0)),
				'sizeFinished' => intval(_get($result, 'sizeFinished', 0)),
				'sizeFailed'   => intval(_get($result, 'sizeFailed', 0)),
			),
			'current'  => array(	// 本轮统计
				'total'    => intval(_get($current, 'total', 0)),
				'finished' => intval(_get($current, 'finished', 0)),
				'failed'   => intval(_get($current, 'failed', 0)),
			),
		);
		show_json(($isMyTask ? $task : null), true, $more);
	}

    /**
	 * 还原，禁止任何操作——未实现
	 * @return void
	 */
    public function restore(){
		show_json(LNG('common.illegalRequest'), false);
		// $id  = Input::get('id','int');
		// echo json_encode(array('code'=>true,'data'=>'OK'));
		// http_close();
		// $this->model->restore($id);
	}

	/**
	 * 终止备份
	 * @return void
	 */
	public function kill(){
		$id  = Input::get('id','int');
		$res = $this->model->kill($id);
		$msg = $res ? LNG('explorer.success') : LNG('explorer.error');
		show_json($msg,!!$res);
	}
}
