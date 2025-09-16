<?php

/**
 * 通知日志
 */
class logsApi {
	protected $plugin;
	protected $pluginName;
	protected $tableName;
	function __construct($plugin) {
		$this->plugin = $plugin;
		$this->pluginName = $plugin->pluginName;
		$this->tableName = 'plugin_msgwarning_log';
	}
	
	public function getAppConfig($key=false, $def=null){
		$config = $this->plugin->getConfig();
		return isset($key) ? _get($config, $key, $def) : $config;
	}
	public function setAppConfig($value){
		$this->plugin->setConfig($value);
	}

	/**
	 * 初始化日志表
	 * @return void
	 */
	public function initTable() {
		if ($this->getAppConfig('initDbTable')) return;
		// 判断插件表是否已存在，不存在则新建
		$tables = Model()->db()->getTables();
		if (in_array($this->tableName, $tables)) {
			return $this->setAppConfig(array('initDbTable' => 1));
		}
		$path = __DIR__.'/data/plugin_msgwarning_log.sql';
		if(stristr($GLOBALS['config']['database']['DB_TYPE'],'sqlite')){
			$path = __DIR__.'/data/plugin_msgwarning_log.sqlite.sql';
		}
		$sqlArr = sqlSplit(file_get_contents($path));
		foreach($sqlArr as $sql){
			$result = Model()->db()->execute($sql);
		}
		$this->setAppConfig(array('initDbTable' => 1));
	}

    /**
     * 通知日志列表
     * @return void
     */
    public function get($req) {
		$this->initTable();
		$page = _get($req, 'page', 1);
		$pageNum = _get($req, 'pageNum', 50);

		$where = array();
		$time = _get($req, 'time', 30);	// 默认近30天
		if ($time != 'all') {
			if ($time == 'diy') {
				$timeFrom = strtotime($req['timeFrom']);
				$timeTo = strtotime($req['timeTo'].' 23:59:59');
			} else {
				$timeFrom = strtotime(date('Y-m-d', strtotime('-'.$time.' days')));
				$timeTo = strtotime(date('Y-m-d 23:59:59'));
			}
			$where['createTime'] = array('between', array($timeFrom, $timeTo));
		}
		$result = Model($this->tableName)->where($where)->order('createTime desc')->selectPage($pageNum, $page);
		if (empty($result['list'])) show_json($result);

		$evntList = $this->plugin->loadLib('evnt')->listData();
		$evntList = array_to_keyvalue($evntList, 'event', 'title');

		$userArray = array_to_keyvalue($result['list'], '', 'userID');
		$userArray = Model('User')->userListInfo(array_unique($userArray));
		foreach ($result['list'] as &$item) {
			$item['title'] = _get($evntList, $item['event'], '');
		    $item['userInfo'] = _get($userArray, $item['userID'], array());
		}
		unset($item);

		show_json($result);
    }

	/**
	 * 写入通知日志
	 * @param [type] $data
	 * @return void
	 */
	public function add($data){
		// 日志：通知事件、通知对象、通知方式、通知结果、通知时间
		$data = array(
			'event'		=> _get($data, 'event', ''),
			'userID'	=> _get($data, 'userID', 0),
			'method'	=> _get($data, 'method', ''),	// 通知方式
			'target'	=> _get($data, 'target', ''),	// 通知目标（联系方式）
			'status'	=> _get($data, 'status', 0),	// 通知结果状态
			'desc'		=> _get($data, 'desc', ''),		// 通知结果详情
			'createTime'=> time()
		);
		Model($this->tableName)->setDataAuto(false);	// 取消自动处理字段
		return Model($this->tableName)->add($data);
	}
}