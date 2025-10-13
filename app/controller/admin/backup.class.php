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
				$task = $tskModel->findByKey('event', $taskEvent[$initKey]);
				if ($task) $tskModel->remove($task['id'],true);
				$optModel->set($initKey, '', 'backup');
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
			if ($initVal == 'ok') continue;
			if ($initKey == $fileTaskKey && $config['content'] != 'all') continue;
			$data = $this->taskInitData($initKey);
			if(!$tskModel->add($data)) return;
			$optModel->set($initKey,'ok','backup');
		}
	}
	private function taskInitData($taskKey) {
		$data = array(
			'autoTaskInit' => array(
				'name'	=> LNG('admin.task.backup'),
				'type'	=> 'method',
				'event' => 'admin.backup.autoTask',
				'time'	=> '{"type":"day","month":"1","week":"1","day":"02:00","minute":"10"}',
				'desc'	=> LNG('admin.backup.taskDbDesc'),
				'enable' => 1,
				'system' => 1,
			),
			'fileTaskInit' => array(
				'name'	=> LNG('admin.task.backup').' ('.LNG('common.file').')',
				'type'	=> 'method',
				'event' => 'admin.backup.fileTask',
				'time'	=> '{"type":"minute","minute":"60"}',
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
			show_json($chk, false);
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
		// 2.2 检查存储是否有效
		$check = $this->checkStore($config['io']);
		if ($check !== true) {
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

		// 手动执行，终止http请求
		if (!$runTask) {
			echo json_encode(array('code'=>true,'data'=>'OK'));
			http_close();
		}
		return $this->model->start($type);
    }
	// 检查存储是否有效
	private function checkStore($io){
		$model = Model('Storage');
		$data = $model->listData($io);
		if (!$data) show_json(LNG('admin.backup.storeNotExist'), false);
		return $model->checkConfig($data, true);
	}
    
    /**
	 * 还原，禁止任何操作——未实现
	 * @return void
	 */
    public function restore(){
        $id  = Input::get('id','int');
        echo json_encode(array('code'=>true,'data'=>'OK'));
        http_close();
        $this->model->restore($id);
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
