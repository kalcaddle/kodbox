<?php 
class msgWarningTaskIndex extends Controller {
	public function __construct() {
		parent::__construct();
		$this->pluginName = 'msgWarningPlugin';
    }
	public function getConfig() {
		if (!$this->appConfig) {
			$this->appConfig = Action($this->pluginName)->getConfig();
		}
		return $this->appConfig;
	}

	// 更新同步计划任务
	public function updateTask($status, $config){
		// 任务不存在：状态为0，返回；否则新增
		if(!$task = $this->getTask()) {
			return $this->addTask($status);
		}
		// 任务已存在：更新
		$data = array(
			'name'	 => $task['name'],
			'enable' => $status
		);
		Model('SystemTask')->update($task['id'], $data);
	}
	private function addTask($status){
		$data = array (
			'name'	=> LNG('msgWarning.main.taskTitle'),
			'type'	=> 'method',
			'event' => $this->pluginName.'.warning',
			'time'	=> '{"type":"minute","month":"1","week":"1","day":"08:00","minute":"1"}',
			'desc'	=> LNG('msgWarning.main.taskDesc'),
			'enable' => $status,
			'system' => 1,
		);
		return Model('SystemTask')->add($data);
	}
	// 获取计划任务，通过id查找有问题（卸载时可能没有删除，导致无法查找也无法新增）
	public function getTask(){
		$event = $this->pluginName.'.warning';
		return Model('SystemTask')->findByKey('event', $event);
	}
	// 删除计划任务
	public function delTask(){
		if(!$task = $this->getTask()) return;
		Model('SystemTask')->remove($task['id']);
	}


	/**
	 * 获取异常信息并发送
	 * @return void
	 */
	public function warning() {
		$config = $this->getConfig();
		// 1.是否开启了消息预警
		if($config['enable'] != '1') return;

		// 2.获取发送目标
		$target = array_filter(explode(',', $config['targetx']));
		if (empty($target)) return;

		// 3.获取cpu、内存等使用信息，并写入记录
		// 读取mem、cpu的值；存入日志；删除指定时长之前的日志；取剩余日志量，比如时长为20分钟，理论上会存20条日志，只要达到80%——16条，就发送提醒，占用很高时（99%），直接发送
		$server	  = new ServerInfo();
		$warnType = explode(',', $config['warnType']);

		$data = array();
		// 3.1 内存
		$type = 'mem';
		if (in_array($type, $warnType)) {
			$memUsage = $server->memUsage();	// [total=>1024,used=>24]
			$memValue = $memUsage['total'] > 0 ? round($memUsage['used']/$memUsage['total'], 3) : 0;
			if($memValue > intval($config['useRatio'] / 100)) {
				$rest = $this->warnUsage($type, array('value' => $memValue, 'data' => $memUsage));
				if (!$rest) $data[$type] = $memValue;
			}
		}
		// 3.2 cpu
		$type = 'cpu';
		if (in_array($type, $warnType)) {
			$cpuUsage = $server->cpuUsage();	// 0.23
			if($cpuUsage > intval($config['useRatio'] / 100)) {
				$rest = $this->warnUsage($type, array('value' => $cpuUsage));
				if (!$rest) $data[$type] = $memValue;
			}
		}
		// 内容不为空，获取系统消息
		if (empty($data)) return;

		// 4.拼接消息
		$text = LNG('msgWarning.main.msgSysErr');
		$base = array();
		if (isset($data['mem'])) {
			$ratio = ($data['mem']*100).'%';
			$base[] = sprintf($text, $config['useTime'], LNG('msgWarning.main.memory'), $config['useRatio'].'%', $ratio);
		}
		if (isset($data['cpu'])) {
			$ratio = ($data['cpu']*100).'%';
			$base[] = sprintf($text, $config['useTime'], 'CPU', $config['useRatio'].'%', $ratio);
		}

		// 获取系统消息，合并，发送
		$msg = Action($this->pluginName)->message(true);
		if (!$msg) $msg = array();
		$msg['base'] = $base;
		// 返回执行结果，写入计划任务日志。可在前端tools/table.log加入详情展示结果，暂不处理
		$result = Action($this->pluginName . '.msg.index')->send($msg, $target);
		$result['user'] = $target;
		return $result;
	}

	/**
	 * 记录cpu、内存异常信息
	 * @param [type] $type
	 * @param [type] $data
	 * @return void true:不发送；false:发送
	 */
	private function warnUsage($type, $data) {
		Model('SystemWarn')->init($type);
		$config = $this->getConfig();

		// 1.写入一条记录——超过99%的不写，且删除已有数据，直接发送
		if ($data['value'] > 0.99) {
			$useTime = 0;
		} else {
			$insert = array(
				'value'		=> $data['value'],
				'content'	=> !empty($data['data']) ? $data['data'] : ''
			);
			$data = Model('SystemWarn')->add($insert);
			$useTime = intval($config['useTime']);
		}

		// 2.获取记录列表，删除指定时长之前的数据
		$list = Model('SystemWarn')->listData();
		$time = time() - 60 * $useTime;
		foreach($list as $k => $item) {
			$crtTime = intval($item['createTime']);
			if(!$useTime || $crtTime < $time) {
				Model('SystemWarn')->remove($item['id']);
				unset($list[$k]);
			}
		}

		// 3.判断是否符合发送提醒条件——持续的n分钟里，如20分钟，理论上有20条记录，只要达到80%（>=16条），就发送提醒
		if (!$useTime) return false;
		$cnt = $useTime * 0.8;
		if(count($list) >= $cnt) {
			// 每发送一次，删除此前的全部记录。持续时长=发送频率
			foreach($list as $k => $item) {
				Model('SystemWarn')->remove($item['id']);
			}
			return false;
		}
		return true;
	}

}

/**
 * mem、cpu记录
 */
class SystemWarnModel extends ModelBaseLight{
	public $optionType 	= '';
	public $modelType	= "SystemOption";
	public $field		= array('value','content'); //数据字段

	public function init($type){
		$this->optionType = 'System.'.$type.'WarnList';	// cpu、mem
	}
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
}