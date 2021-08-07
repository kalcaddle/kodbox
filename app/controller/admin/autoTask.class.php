<?php
/**
 * 计划任务
 */
class adminAutoTask extends Controller {
	function __construct()    {
		parent::__construct();
		$this->model = Model('SystemTask');
	}

	public function taskInit(){
		if(Model('systemOption')->get('autoTaskInit','backup') == 'ok') return;
		// 数据备份
		$data = array (
			'name'	=> LNG('admin.task.backup'),
			'type'	=> 'method',
			'event' => 'admin.backup.start',
			'time'	=> '{"type":"day","month":"1","week":"1","day":"02:00","minute":"10"}',
			'desc'	=> LNG('admin.task.backupDesc'),
			'enable' => '0',
			'system' => '1',
		);
		if(!$this->model->add($data)) return; 
		Model('systemOption')->set('autoTaskInit','ok','backup');
	}
	
    public function get(){
		$this->taskInit();
		$result = $this->model->listData();
		show_json($result,true);
	}
	
	/**
	 * 计划任务添加
	 */
	public function add(){
		$data = Input::getArray(array(
			'name'		=> array('check'=>'require'),	// 名称
			'type'		=> array('check'=>'require'),	// 类型：方法、URL
			'event'		=> array('check'=>'require'),	// 任务值：方法、url地址
			'time'		=> array('check'=>'require'),	// 周期：json
			'desc'		=> array('default'=>''),		// 描述
			'enable'	=> array('default'=>'0'),		// 是否启用
			'system'	=> array('default'=>'0'),		// 系统默认
		));
		$this->checkEvent($data);
		$result = $this->model->add($data);
		$msg = !!$result ? LNG('explorer.success') : LNG('explorer.repeatError');
		show_json($msg,!!$result);
	}
	
	/**
	 * 更新任务信息
	 */
	public function edit(){
		$data = Input::getArray(array(
			"id"    	=> array("check"=>"number"),
			'name'		=> array('check'=>'require'),	// 名称
			'type'		=> array('check'=>'require'),	// 类型：方法、URL
			'event'		=> array('check'=>'require'),	// 任务值：方法、url地址
			'time'		=> array('check'=>'require'),	// 周期：json
			'desc'		=> array('default'=>''),		// 描述
			'enable'	=> array('default'=>'0'),		// 是否启用
			'system'	=> array('default'=>'0'),		// 系统默认
		));
		$this->checkEvent($data);
		$result = $this->model->update($data['id'],$data);
		$msg = !!$result ? LNG('explorer.success') : LNG('explorer.error');
		show_json($msg,!!$result);
	}

	private function checkEvent($data) {
		$action = $data['event'];
		if($data['type'] == 'url') {
			if(!Input::check($action, 'url')){
				show_json('url error!', false);
			}
			return;
		} 
		$last 	  	= strrpos($action,'.');
		$className	= substr($action,0,$last);
		$method   	= substr($action,$last + 1);
		$obj 		= Action($className);
		if(!$obj || !method_exists($obj,$method)){
			show_json("[{$action}] method not exists!", false);
		}
	}

	/**
	 * 启动|关闭某个任务
	 */
    public function enable(){
		$data = Input::getArray(array(
			"id"    	=> array("check"=>"number"),
			"enable"	=> array("check"=>"bool"),
		));
		$result = $this->model->enable($data['id'],(bool)$data['enable']);
		$msg = !!$result ? LNG('explorer.success') : LNG('explorer.error');
		show_json($msg,!!$result);
	}	
	/**
	 * 删除计划任务
	 */
    public function remove(){
		$id = Input::get('id','int');
		$result = $this->model->remove($id);
		$msg = !!$result ? LNG('explorer.success') : LNG('explorer.error');
		show_json($msg,!!$result);
	}
	
	/**
	 * 手动立即执行某个任务
	 */
    public function run(){
		$id = Input::get('id','int');
		$task  = Model("SystemTask")->listData($id);
        if($task){
            $result = AutoTask::taskRun($task);
		}
		$msg = !!$result ? LNG('explorer.success') : LNG('explorer.error');
		show_json($msg,!!$result);
	}
	
	
	// 开启关闭计划任务; 
	public function taskSwitch(){
		$data  = Input::getArray(array(
			"status"	=> array("check"=>"bool"),
			"delay"		=> array("check"=>"int","default"=>10),
		));
		// Cache::deleteAll();
		AutoTask::config($data['status'],$data['delay']);
		show_json($data);
	}
	public function taskRestart(){
		AutoTask::restart();
		sleep(1);
		AutoTask::start();
	}

	// 移动排序、拖拽排序
	public function sort() {
		$ids = Input::get('ids', 'require');
		$ids = explode(',', $ids);
		foreach($ids as $i => $id) {
			$this->model->update($id,array("sort"=> $i));
		}
		show_json(LNG('explorer.success'));
	}
}