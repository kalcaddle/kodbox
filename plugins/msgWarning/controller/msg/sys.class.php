<?php 
/**
 * 通知事件——系统服务类
 */
class msgWarningMsgSys extends Controller {
	public function __construct() {
		parent::__construct();
		$this->pluginName = 'msgWarningPlugin';
    }

	public function index($evntInfo) {
		$data = array();
		switch ($evntInfo['event']) {
			case 'sysStoreErr':
				$data = $this->sysStoreErr($evntInfo);
				break;
			case 'sysStoreBakErr':
				$data = $this->sysStoreBakErr($evntInfo);
				break;
			case 'sysStoreSizeErr':
				$data = $this->sysStoreSizeErr($evntInfo);
				break;
			case 'sysBakTaskErr':
				$data = $this->sysBakTaskErr($evntInfo);
				break;
			default:
				# code...
				break;
		}
		return $data;
	}

	/**
	 * 存储异常
	 * @param [type] $evntInfo
	 * @return void
	 */
	public function sysStoreErr($evntInfo) {
		$data = Action($this->pluginName)->apiAct('sys', 'storage')->checkStoreList(true);
		// 过滤无系统数据的存储
        $data = array_filter($data, function ($item) {
            return $item['sysdata'] == 1;
        });
		if (!$data) return array();

		$msg = sprintf(LNG('msgWarning.sys.storeErr'), count($data));
		return array($msg);
	}

	/**
	 * 备份存储异常
	 * @param [type] $evntInfo
	 * @return void
	 */
	public function sysStoreBakErr($evntInfo) {
		// 获取备份存储
		$backup = Model('Backup')->config();
		if (!$backup || $backup['enable'] != '1') return array();
		$storeId = $backup['io'];

		// 获取异常存储列表
		// 此任务在sysStoreErr之后执行，直接获取缓存
		$data = Action($this->pluginName)->apiAct('sys', 'storage')->checkStoreList();
		if (!$data || !isset($data[$storeId])) return array();

		$msg = sprintf(LNG('msgWarning.sys.storeBakErr'), $data[$storeId]['name']);
		return array($msg);
	}

	/**
	 * 默认存储空间不足
	 * @param [type] $evntInfo
	 * @return void
	 */
	public function sysStoreSizeErr($evntInfo) {
		$policy = $evntInfo['policy'];
		if (!$policy) return array();
		$sizeLimit = intval($policy['sizeMin']);
		if ($sizeLimit <= 0) return array();

		// 本地存储，获取所在磁盘的实际大小；其他获取配置大小
		$driver = KodIO::defaultDriver();
		if (strtolower($driver['driver']) == 'local') {
			$path = _get($driver, 'config.basePath', '');
			if (!$path) return array();
			$path = realpath($path);
			$data = $this->driverInfo($path);
            if(!$data) return array();
		} else {
			$sizeUse = Model('File')->where(array('ioType' => $driver['id']))->sum('size');
            $data = array(
                'sizeMax'   => floatval($driver['sizeMax']) * 1024 * 1024 * 1024,
                'sizeUse'   => floatval($sizeUse)
            );
		}

		$sizeFree = ($data['sizeMax'] - $data['sizeUse']);
		if ($sizeFree > 1024*1024*1024*$sizeLimit) return array();

		$size = size_format($sizeFree);
		$msg  = sprintf(LNG('msgWarning.sys.storeSizeErr'), $size);
		return array($msg);
	}
	// 根据路径获取磁盘使用情况
    private function driverInfo($path) {
		if(!file_exists($path)) return false;
		if(!function_exists('disk_total_space')){return false;}
		$sizeMax = @disk_total_space($path);
		return array(
			'sizeMax'	=> $sizeMax,
			'sizeUse'	=> $sizeMax - @disk_free_space($path),
		);
    }

	// 默认存储配置提醒（在系统盘）——不在此处获取，仅teamos
	public function sysStoreDefErr($evntInfo) {
		return array();
	}


	/**
	 * 备份任务异常
	 * @param [type] $evntInfo
	 * @return void
	 */
	public function sysBakTaskErr($evntInfo) {
		// 1.判断备份是否启用
		$backup = Model('Backup')->config();
		if (!$backup || $backup['enable'] != '1') return array();

		// 2.获取最近一条计划任务备份的记录
		$list = Model('Backup')->listData();
		$info = false;
		foreach ($list as $item) {
			if ($item['manual'] != '1') {
				$info = $item;
				break;
			}
		}
		if (!$info) return array();

		// 最近一条计划任务记录不是今天
		if ($info['name'] != date('Ymd')) return array();
		// TODO 不是今天且已过备份时间，也许应该提示——暂不处理

		// 3.判断备份是否成功
		// 3.1 备份中——应该根据Task任务判断是否异常中止（>2h没有更新），暂不处理
		if ($info['status'] == '0') return array();

		// 3.2 备份完成，判断是否成功——参考前端bakStatus
		$state = true;
		unset($info['result']['keep']);
		if ($info['content'] == 'sql') {
			unset($info['result']['file']);
		}
		foreach ($info['result'] as $key => $item) {
			if (!$item['status']) {
				$state = false;
				break;
			}
			// 数据库文件数不匹配
			if (in_array($key, array('db', 'dbFile')) && $item['total'] > $item['success']) {
				$state = false;
				break;
			}
			// // 文件未备份完成
			// if ($key == 'file' && $item['cntTotal'] != $item['cntSuccess']) {
			// 	$state = false;
			// 	break;
			// }
		}
		if ($state) return array();

		$msg = LNG('msgWarning.sys.bakTaskErr');
		return array($msg);
	}

}
