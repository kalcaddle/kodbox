<?php

/**
 * 通知事件
 */
class evntApi {
	protected $plugin;
	protected $pluginName;
	function __construct($plugin) {
		$this->plugin = $plugin;
		$this->pluginName = $plugin->pluginName;
		require_once(__DIR__ .'/data/ntc.evnt.php');
	}
	
    public function getAppConfig($key=false, $def=null){
		$config = $this->plugin->getConfig();
		return isset($key) ? _get($config, $key, $def) : $config;
	}
	public function setAppConfig($value){
		$this->plugin->setConfig($value);
	}

	/**
	 * 初始化通知事件（写入数据库）
	 * @return void
	 */
	public function initData() {
		$list = NtcEvnt::listData();
		$data = $this->getAppConfig('ntcEvntList', array());
		$update = array();
		foreach ($list as $item) {
		    $event = $item['event'];
			if (isset($data[$event])) continue;
			if (!$item['policy']) $item['policy'] = array();

			$policy = array();
			foreach ($item['policy'] as $key => $value) {
				if (!isset($value['value'])) continue;
				$policy[$key] = $value['value'];
			}
			$update[$event] = array(
				// 'type'	 => $item['type'],	// 通知方式
				'status' => $item['status'],// 事件状态
				'policy' => $policy,		// 通知策略
				'notice' => $item['notice'],// 通知设置
				'result' => array(
					'cntToday' => 0,
					'cntTotal' => 0,
					'ntcTime' => 0, 
					'tskTime' => 0, 
				), // 通知结果
			);
		}
		if (!empty($update)) {
			$this->setAppConfig(array('ntcEvntList' => array_merge($data, $update)));
		}
	}

	/**
	 * 通知事件列表：获取全部（包含详情），用于通知任务执行
	 * @return void
	 */
	public function listData() {
		$list = $this->get(array(), true);
		foreach ($list as &$item) {
			$policy = array();
			foreach ($item['policy'] as $key => $val) {
				if (!$val || !isset($val['value'])) continue;
				$policy[$key] = $val['value'];
			}
			$item['policy'] = $policy;
		}
		unset($item);
		return $list;
	}

	/**
	 * 通知事件列表
	 * @return void
	 */
	public function get($req, $ret=false){
		$list = NtcEvnt::listData();
		// 获取追加事件
		$list = Hook::filter('msgWarning.evnt.list', $list);
		$this->evntListFilter($list);

		// 筛选条件
		$where = array();
		if ($req['class'] && $req['class'] != 'all') {
			$where['class'] = $req['class'];
		}
		if ($req['level'] && $req['level'] != 'all') {
			$where['level'] = intval(str_replace('level','',$req['level']));
		}
		if (isset($req['status']) && $req['status'] != 'all') {
			$where['status'] = $req['status'] == '1' ? 1 : 0;
		}

		// 查询详情，覆盖更新
		$data = $this->getAppConfig('ntcEvntList', array());
		$update = array();
		foreach ($list as $i => &$item) {
			$event = $item['event'];
			// 赋值
			$dbopt = _get($data, $event, array());
			foreach ($dbopt['policy'] as $key => $value) {
				if (!isset($item['policy'][$key])) continue;
				$item['policy'][$key]['value'] = $value;
			}
			foreach ($dbopt['notice'] as $key => $value) {
				if (!isset($item['notice'][$key])) continue;
				$item['notice'][$key] = $value;
			}
			$item['result'] = array(
				'cntToday' 		=> intval(_get($dbopt, 'result.cntToday', 0)),
				'cntTotal' 		=> intval(_get($dbopt, 'result.cntTotal', 0)),
				'ntcTime'	=> intval(_get($dbopt, 'result.ntcTime', 0)),
				'tskTime'	=> intval(_get($dbopt, 'result.tskTime', 0)),
			);
			if (isset($dbopt['status'])) {
				$item['status'] = $dbopt['status'];
			}
			// // 解析通知方式
			// if (!$ret) $this->parseEvntType($item);

			// 重置今日通知次数
			if ($item['result']['ntcTime'] < strtotime('today') && $item['result']['cntToday'] > 0) {
				$item['result']['cntToday'] = $dbopt['result']['cntToday'] = 0;
				$update[$event] = $dbopt;
			}

			// 前端请求，按条件过滤
			foreach (array('class','level','status') as $key) {
				if (isset($where[$key]) && $item[$key] != $where[$key]) {
					unset($list[$i]);
				}
			}
		}
		// 重置今日通知次数
		if (!empty($update)) {
			$this->setAppConfig(array('ntcEvntList' => array_merge($data, $update)));
		}

		$list = array_values($list);
		if ($ret) return $list;
		show_json(array('list' => $list));
	}

	// 获取通知方式名称——弃用
	private function parseEvntType(&$item) {
		static $list;
		if (!$list) {
			// require_once(__DIR__ .'/data/ntc.type.php');
			// $list = NtcType::listData();
			// $list = array_to_keyvalue($list, 'type', 'name');
			$data = $this->plugin->loadLib('type')->listData();
			$list = array();
			foreach ($data as $value) {
				if (!$value['status']) continue;
				$list[$value['type']] = $value['name'];
			}
		}
		$method = _get($item,'notice.method','');	// 通知方式
		$mtdArr = explode(',', $method);
		foreach ($mtdArr as &$mthd) {
			// $mthd = _get($list, $mthd, $mthd);
			$mthd = _get($list, $mthd, '');
		}
		$item['type'] = $method;	// 为与前端统一，这里保留原始值
		$item['typeText'] = implode(',', $mtdArr);
		$item['typeList'] = $list;	// 获取可用的通知方式列表
	}

	// 过滤不支持的通知事件
	private function evntListFilter(&$list) {
		$plugin = Model('Plugin')->loadList('oemCockpit');
		if ($plugin && $plugin['status'] == '1') return;

		// 非teamos，排除硬件类、及文件系统检测、默认存储设置通知
		foreach ($list as $i => $item) {
			if ($item['class'] == 'dev' || in_array($item['event'], array('svrFileSysErr','sysStoreDefErr'))) {
				unset($list[$i]);
			}
		}
	}

	/**
	 * 通知事件操作
	 * @return void
	 */
	public function action ($req) {
		$action = $req['action'];
		switch ($action) {
			case 'getConfig':
				$this->getConfig($req);
				break;
			case 'setConfig':
				$this->setConfig($req);
				break;
			case 'getRawData':
				$this->getRawData($req);
				break;
			default:break;
		}
        show_json(LNG('common.illegalRequest'), false);
	}

	/**
	 * 通知事件信息获取
	 * @return void
	 */
	public function getConfig($req) {
		
	}
	/**
	 * 通知事件信息保存
	 * @return void
	 */
	public function setConfig($req, $ret=false) {
		$event = $req['event'];
		$data = _get($req, 'data', array());
        if (!is_array($data)) $data = json_decode($data,true);

		// 前端提交且为详情保存，检查参数——启/禁用不处理
		if (!$ret && isset($data['notice'])) {
			$timeFreq = intval(_get($data, 'notice.timeFreq', 0));
			if (in_array($event, array('svrCpuErr','svrMemErr'))) {
				if ($timeFreq < intval(_get($data, 'policy.useTime', 0))) {
					show_json(LNG('msgWarning.evnt.freqLsUseTimeErr'), false);
				}
			}
			$info = $this->getRawList($event);
			$taskFreq = _get($info, 'taskFreq', 0);
			if ($timeFreq < $taskFreq) {
				show_json(sprintf(LNG('msgWarning.evnt.freqLsTimeErr'), $taskFreq), false);
			}
		}

		// 获取列表，覆盖对应项
		$list = $this->getAppConfig('ntcEvntList', array());
		if (!isset($list[$event])) $list[$event] = array();
		// // TODO 启用状态会被覆盖，可能来自计划任务，待确认
		// write_log(array('更新evnt---',$req, $list[$event],get_caller_info()), 'msgwarning');
        $list[$event] = array_merge($list[$event], $data);
		// write_log(array('更新evnt2---',$req, $list[$event]), 'msgwarning');
        $this->setAppConfig(array('ntcEvntList'=>$list));
		if ($ret) return true;
		show_json(LNG('explorer.success'), true);
	}

	/**
	 * 获取通知事件列表原始数据——目前仅通知方式，后续可按需扩展
	 * @param [type] $req
	 * @return void
	 */
	public function getRawData($req, $ret=false) {
		$list = $this->getRawList();
		foreach ($list as &$item) {
			$item = $item['notice']['method'];
		};unset($item);
		show_json($list);
	}
	private function getRawList($event=false){
		$list = NtcEvnt::listData();
		$data = array();
		foreach ($list as $item) {
		    $data[$item['event']] = $item;
		}
		return $event ? $data[$event] : $data;
	}
}