<?php 
class storeSyncIndexIndex extends Controller {
	public $pluginName;
	public function __construct() {
		parent::__construct();
		$this->pluginName = 'storeSyncPlugin';
    }

    // 更新同步计划任务
	public function updateTask($status){ 
		// 任务不存在，新建
		$task = $this->getTask();
		if(!$task && $status) {return $this->addTask($status);}
		// 任务已存在：更新
        $update = array('enable' => $status);
		Model('SystemTask')->update($task['id'], $update);
		// Action('admin.autoTask')->taskRestart();	// 重启计划任务，请求会被中断，不能在此直接调用
	}
	private function addTask($status){
		$data = array (
			'name'	=> LNG('storeSync.task.title'),
			'type'	=> 'method',
			'event' => $this->pluginName.'.autoTask',
			'time'	=> '{"type":"minute","minute":"1"}',   // 每分钟执行一次
			'desc'	=> LNG('storeSync.task.desc'),
			'enable' => $status,
			'system' => 1,
		);
		return Model('SystemTask')->add($data);
	}
	// 获取计划任务，通过id查找有问题（卸载时可能没有删除，导致无法查找也无法新增）
	private function getTask(){
		$event = $this->pluginName.'.autoTask';
		return Model('SystemTask')->findByKey('event', $event);
	}

	// 检测并创建数据表
	public function initDbTable($status) {
		$this->installSet('0');
		if($status == '1'){
			$this->checkInstall();
			$error = LNG('storeSync.main.errDbInstall');
			if(!$this->isInstall()){show_json($error,false);}
		}
	}
	private function checkInstall(){
		if($this->isInstall()) return;
		$tableHas = 'plugin_storesync';
		$tables = Model()->db()->getTables();
		if(in_array($tableHas, $tables)){return $this->installSet('1');}
		$pluginPath = Action($this->pluginName)->pluginPath;
		$sqlFile = $pluginPath.'lib/data/plugin_storesync.sql';
		if(getDatabaseType() == 'sqlite'){
			$sqlFile = $pluginPath.'lib/data/plugin_storesync_sqlite.sql';
		}
		$sqlArr = sqlSplit(file_get_contents($sqlFile));
		foreach($sqlArr as $sql){
			Model()->db()->execute($sql);
		}

		$tables = Model()->db()->getTables();
		if(in_array($tableHas,$tables)){$this->installSet('1');}
	}
	private function installSet($set){
		Model('SystemOption')->set('installDB',$set,'storeSync');
	}
	private function isInstall($set = false){
		return Model('SystemOption')->get('installDB','storeSync') == '1';
	}


}
