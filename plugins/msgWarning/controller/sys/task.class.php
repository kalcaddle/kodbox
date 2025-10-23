<?php 
class msgWarningSysTask extends Controller {
	public function __construct() {
		parent::__construct();
		$this->pluginName = 'msgWarningPlugin';
    }
	public function getConfig() {
		return Action($this->pluginName)->getConfig();
	}

	// 更新同步计划任务
	public function updateTask($status){
		// 删除旧任务
		$this->delTask($this->pluginName.'.warning');

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
			'name'	=> LNG('msgWarning.meta.name'),
			'type'	=> 'method',
			'event' => $this->pluginName.'.autoTask',
			'time'	=> '{"type":"minute","month":"1","week":"1","day":"08:00","minute":"1"}',
			'desc'	=> LNG('msgWarning.main.taskDesc'),
			'enable' => $status,
			'system' => 1,
		);
		return Model('SystemTask')->add($data);
	}
	// 获取计划任务，通过id查找有问题（卸载时可能没有删除，导致无法查找也无法新增）
	public function getTask($event=false){
		if (!$event) $event = $this->pluginName.'.autoTask';
		return Model('SystemTask')->findByKey('event', $event);
	}
	// 删除计划任务
	public function delTask($event=false){
		if(!$task = $this->getTask($event)) return;
		Model('SystemTask')->remove($task['id'], true);
	}


	/**
	 * 获取通知事件列表，执行通知
	 * 计划任务定期获取通知消息，有前端方式则写入缓存；后端则发送消息通知；
	 * 前端通知：前端只从缓存中获取消息，获取后清除自己（直到下次计划任务再次写入）；前端请求同计划任务，每分钟请求一次
	 * @return void
	 */
	public function notice($runTask){
		if (!$runTask) $this->webNotice();
		$plugin = Action($this->pluginName);
		$ntcApi = $plugin->apiAct('sys', 'notice');	// 发送通知类

		// 旧数据处理——重新写入队列
		$ntcApi->oldTaskQueue();

		// 给通知事件列表分类
		$evntData = array();
		$evntList = $plugin->loadLib('evnt')->listData();
		foreach ($evntList as $evntInfo) {
			if ($evntInfo['status'] != '1') continue;
			$clsKey = $evntInfo['class'];
			if (!isset($evntData[$clsKey])) {
				$evntData[$clsKey] = array();
			}
			$evntData[$clsKey][] = $evntInfo;
		}
		unset($evntList);
		
		// 通知消息缓存，用于前端获取
		$wbcache = array();

		// TODO 前端提醒好像有点问题，存储异常3分钟提醒一次
		
		// 获取通知消息
		foreach ($evntData as $clsKey => $evntList) {
		    $msgAct = $plugin->apiAct('msg', $clsKey);	// 获取通知（内容）类
			foreach ($evntList as $evntInfo) {
				$event = $evntInfo['event'];
				// 每次计划任务执行时，清空前端缓存——不能清空，清空则消息只保留1分钟，1分钟内没通知到的（没登录），就接收不到此次通知了
				// $wbcache[$event] = array();

				// 1.通知对象过滤
				if (!$this->filterTarget($evntInfo)) continue;
				// 更新任务执行时间
				$this->updateNtcEvnt($evntInfo, 'tskTime');
				
				// 2.获取通知消息
				$message = $msgAct->index($evntInfo);
				$tempMsg = Hook::trigger('msgWarning.msg.'.$event, $evntInfo);	// 附加通知；eg: msgWarning.msg.sysStoreDefErr
				if($tempMsg && is_array($tempMsg)){
					$message = $tempMsg; 
				}
				if (!$message) continue;
				$evntInfo['message'] = $message;

				// 检查通知频率，判断是否需要通知
				$timeFreq = intval(_get($evntInfo, 'notice.timeFreq', 0));
				$timeLast = intval(_get($evntInfo, 'result.ntcTime', 0));
				if ($timeFreq < 1) $timeFreq = 1;	// 时间间隔至少1分钟
				if (($timeLast + ($timeFreq * 60)) > time()) continue;
				// 更新通知时间、次数
				$this->updateNtcEvnt($evntInfo, 'ntcTime');

				// 有前端通知方式，则写入缓存，供前端请求获取
				// 3.前端通知：写入缓存，前端用户只获取一次（清除自己的id）；
				$methods = explode(',', $evntInfo['notice']['method']);
				$wbcache[$event] = $this->ntcCache($evntInfo, $methods);

				// 4.计划任务通知
				if (!array_intersect($methods, array('sms', 'email', 'weixin', 'dding', 'feishu'))) {
					continue;
				}
				// 4.1 预警级+ 写入log日志
				if ($evntInfo['level'] >= 3) {
					write_log('【系统通知】'.$evntInfo['title'].': '.implode('; ', $message), 'warning');
				}

				// 4.2 发送通知
				$ntcApi->send($evntInfo);
			}
		}
		// 存前端缓存——未被覆盖的继续保留，用户23点登录系统接收到的可能是8点的通知
		$cckey = $this->pluginName.'.webNtcList.'.date('Ymd');
		$cache = Cache::get($cckey);
		if (!is_array($cache)) $cache = array();
		$cache = array_merge($cache, $wbcache);
		Cache::set($cckey, $cache, 3600*24);
		return true;
	}

	// 通知事件过滤
	public function filterTarget(&$evntInfo){
		// 是否启用
		if (!$evntInfo['status']) return false;

		$notice = _get($evntInfo, 'notice', array());	// 通知设置
		$result = _get($evntInfo, 'result', array());	// 通知结果
		$target = json_decode($notice['target'], true);
		if (empty($target)) $target = array();

		// 通知次数
		$cntMax		= intval(_get($notice, 'cntMax', 0));
		$cntMaxDay	= intval(_get($notice, 'cntMaxDay', 0));
		if ($cntMax > 0 && intval($result['cntTotal']) >= $cntMax) return false;
		if ($cntMaxDay > 0 && intval($result['cntToday']) >= $cntMaxDay) return false;

		// 通知时段
		$timeNow = time();
		if (strtotime($notice['timeFrom']) > $timeNow || strtotime($notice['timeTo']) < $timeNow) return false;

		// 任务执行频率——注意这里不是通知频率：事件任务执行后，如果有消息，再根据通知频率判断是否通知
		$taskFreq = intval(_get($evntInfo, 'taskFreq', 0));
		$timeLast = intval(_get($result, 'tskTime', 0));
		// 频率为1分钟的不用检查，避免和计划任务冲突
		if ($taskFreq > 1) {
			if (($timeLast + ($taskFreq * 60)) > $timeNow) return false;
		}

		// 通知方式
		if (empty($notice['method'])) return false;
		// toAll：将[系统通知]添加到通知方式中——前端限制取消后，此处没有必要强制添加
		if ($evntInfo['toAll'] == '1') {
			if (stripos($notice['method'], 'kwarn') === false) {
				$evntInfo['notice']['method'] .= ',kwarn';
			}
		}

		// 通知对象
		if (empty($target['user']) && empty($target['group'])) return false;
		$users = $this->getTargetUsers($target);
		if (empty($users)) return false;

		$evntInfo['target'] = $users;	// 实际通知对象，用于邮件短信；企微和钉钉需传原始值通知
		return true;
	}

	// 通过form.userGroup获取用户id列表：{user:[1,2,3],group:[1,2,3]}
	private function getTargetUsers($target) {
		// 缓存不同条件下的部门用户
		static $groupUsers = array();

		$users = _get($target, 'user', array());
		$group = _get($target, 'group', array());
		if (empty($group)) return array_filter(array_unique($users));
		
		// 获取部门下的用户：有根部门则为全部用户
		$list = array();
		sort($group);
		if (in_array('1', $group)) {
			if (isset($groupUsers['1'])) {
				$list = $groupUsers['1'];
			} else {
				$list = Model('User')->where(array('status' => 1))->field('userID')->select();
				$list = array_to_keyvalue($list, '', 'userID');
				$groupUsers['1'] = $list;
			}
		} else {
			$tmpKey = implode(',', $group);
			if (isset($groupUsers[$tmpKey])) {
				$list = $groupUsers[$tmpKey];
			} else {
				$list = Model('user_group')->alias('g')->field('u.userID')
						->join('INNER JOIN user as u ON u.userID = g.userID AND u.status = 1')
						->where(array('g.groupID' => array('in', $group)))
						->select();
				$list = array_to_keyvalue($list, '', 'userID');
				$groupUsers[$tmpKey] = $list;
			}
		}
		$users = array_merge($users, $list);

		if (empty($users)) return array();
		return array_filter(array_unique($users));
	}

	// 获取用于缓存的数据：{ktips:1,2,3,kwarn:1,2,3,msg:{title,level,message}}
	private function ntcCache($evntInfo, $methods) {
		$cache = array();
		$users = implode(',', $evntInfo['target']);
		if (in_array('ktips', $methods)) {
			$cache['ktips'] = $users;
		}
		if (in_array('kwarn', $methods)) {
			if ($evntInfo['toAll'] == '1') {
				$users = 'all';	// 前端获取时，将当前用户id追加到后面，后续再获取时，如果已在其中则不提示
			}
			$cache['kwarn'] = $users;
		}
		if (!empty($cache)) {
			$cache['msg'] = array(
				'title'		=> $evntInfo['title'],
				'level'		=> intval($evntInfo['level']),
				'message' 	=> $evntInfo['message'],
			);
		}
		return $cache;
	}

	// 更新任务执行时间
	// TODO 应该写一个批量更新的方法
	private function updateNtcEvnt(&$evntInfo, $type) {
		$time = time();
		$evntInfo['result']['tskTime'] = $time;
		if ($type == 'ntcTime') {
			$evntInfo['result']['ntcTime'] = $time;
			$evntInfo['result']['cntToday'] = intval(_get($evntInfo, 'result.cntToday', 0)) + 1;
			$evntInfo['result']['cntTotal'] = intval(_get($evntInfo, 'result.cntTotal', 0)) + 1;
		}
		$update = array(
			'event' => $evntInfo['event'],
			'data'	=> array (
				'result' => $evntInfo['result'],
			)
		);
		Action($this->pluginName)->loadLib('evnt')->setConfig($update, true);
	}


	/**
	 * 前端通知
	 * @return void [ktips=>[event=>[title,level,message],...],kwarn=>[event=>[title,level,message],...]]
	 */
	public function webNotice() {
		if(!defined('USER_ID') || !USER_ID) show_json(array());
		$cckey = $this->pluginName.'.webNtcList.'.date('Ymd');
		$cache = Cache::get($cckey);
		if (empty($cache)) show_json(array());

		$data = array();
		foreach ($cache as $event => &$item) {
			if (!$item) continue;
			// kwarn通知范围为all时，获取并追加自己的id，后续再获取时，如果已存在则忽略
			if (stripos($item['kwarn'], 'all') !== false) {
				$users = explode(',', $item['kwarn']);
				if (!in_array(USER_ID, $users)) {
					$data['kwarn'][$event] = $item['msg'];
					$item['kwarn'] .= ','.USER_ID;
				}
				continue;
			}
			foreach (array('ktips', 'kwarn') as $key) {
				$users = explode(',', $item[$key]);
				if (in_array(USER_ID, $users)) {
					$data[$key][$event] = $item['msg'];
					$users = array_diff($users, array(USER_ID));
					$item[$key] = implode(',', $users);
				}
			}
		}
		unset($item);
		if (empty($data)) show_json(array());

		// 更新缓存
		Cache::set($cckey, $cache, 3600*24);
		show_json($data);
	}

}
