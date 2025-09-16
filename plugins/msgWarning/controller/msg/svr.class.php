<?php 
/**
 * 通知事件——操作系统类
 */
class msgWarningMsgSvr extends Controller {
	public function __construct() {
		parent::__construct();
		$this->pluginName = 'msgWarningPlugin';
    }

	public function index($evntInfo) {
		$data = array();
		switch ($evntInfo['event']) {
			case 'svrDiskSizeErr':
				$data = $this->svrDiskSizeErr($evntInfo);
				break;
			case 'svrFileSysErr':
				$data = $this->svrFileSysErr($evntInfo);
				break;
			case 'svrCpuErr':
				$data = $this->svrCpuErr($evntInfo);
				break;
			case 'svrMemErr':
				$data = $this->svrMemErr($evntInfo);
				break;
			default:
				# code...
				break;
		}
		return $data;
	}

	/**
	 * 系统盘剩余空间不足
	 * @param [type] $evntInfo
	 * @return void
	 */
	public function svrDiskSizeErr ($evntInfo) {
		$policy = $evntInfo['policy'];
		if (!$policy) return array();
		$sizeLimit = intval($policy['sizeMin']);
		if ($sizeLimit <= 0) return array();

		// 获取系统盘使用情况
		$info = $this->driverInfo(DATA_PATH);
		if (!$info) return array();

		$sizeFree = ($info['sizeMax'] - $info['sizeUse']);
		if ($sizeFree > 1024*1024*1024*$sizeLimit) return array();

		$size = size_format($sizeFree);
		$msg  = sprintf(LNG('msgWarning.svr.diskSizeErr'), $size);
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

	/**
	 * 文件系统异常
	 * @param [type] $evntInfo
	 * @return void
	 */
	public function svrFileSysErr ($evntInfo) {
		// TODO cockpit中获取
		return array();
	}

	/**
	 * 文件系统异常
	 * @param [type] $evntInfo
	 * @return void
	 */
	public function svrCpuErr ($evntInfo) {
		$policy = $evntInfo['policy'];
		if (!$policy) return array();
		$useRatio = $policy['useRatio'];
		$useTime  = intval($policy['useTime']);
		
		// 1.根据触发条件，判断是否需要发送信息
		$type = 'cpu';
		$data = false;
		$server = new ServerInfo();
		$usage = $server->cpuUsage();	// 0.23
		// pr($usage);exit;
		if($usage && $usage > floatval($useRatio / 100)) {
			$rest = $this->warnUsage(array('value' => $usage), $useTime, $type);
			if (!$rest) $data = $usage;
		}
		if (!$data) return array();

		// 2.拼接消息
		$msg = sprintf(LNG('msgWarning.svr.usageErr'), $useTime, 'CPU', $useRatio.'%', ($data*100).'%');
		return array($msg);
	}

	/**
	 * 文件系统异常
	 * @param [type] $evntInfo
	 * @return void
	 */
	public function svrMemErr ($evntInfo) {
		// pr($evntInfo);exit;
		$policy = $evntInfo['policy'];
		if (!$policy) return array();
		$useRatio = $policy['useRatio'];
		$useTime  = intval($policy['useTime']);
		
		// 1.根据触发条件，判断是否需要发送信息
		$type = 'mem';
		$data = false;
		$server = new ServerInfo();
		$usage = $server->memUsage();	// [total=>1024,used=>24]
		// pr($usage);exit;
		$value = $usage['total'] > 0 ? round($usage['used']/$usage['total'], 3) : 0;
		if($value && $value > floatval($useRatio / 100)) {
			$rest = $this->warnUsage(array('value' => $value, 'data' => $usage), $useTime, $type);
			if (!$rest) $data = $value;
		}
		// pr($usage,$value,$data);exit;
		if (!$data) return array();

		// 2.拼接消息
		$msg = sprintf(LNG('msgWarning.svr.usageErr'), $useTime, LNG('msgWarning.main.memory'), $useRatio.'%', ($data*100).'%');
		return array($msg);
	}

	/**
	 * 记录cpu、内存异常信息
	 * @param [type] $data
	 * @param [type] $useTime	持续时长（分钟）
	 * @param [type] $type
	 * @return void true:不发送；false:发送
	 */
	private function warnUsage($data, $useTime, $type) {
		$model = Model('SystemWarn');
		$model->init($type);

		// 1.写入一条记录
		$insert = array(
			'value'		=> $data['value'],
			'content'	=> !empty($data['data']) ? $data['data'] : ''
		);
		$data = $model->add($insert);
		if (!$useTime || $useTime < 10) $useTime = 10; // 最小持续时长10分钟

		// 2.获取记录列表，删除指定时长之前的数据
		$list = $model->listData();
		$time = time() - 60 * $useTime;
		foreach($list as $k => $item) {
			$crtTime = intval($item['createTime']);
			if(!$useTime || $crtTime < $time) {
				$model->remove($item['id']);
				unset($list[$k]);
			}
		}

		// 3.判断是否符合发送提醒条件——持续的n分钟里，如20分钟，理论上有20条记录，只要达到80%（>=16条），就发送提醒
		$cnt = $useTime * 0.8;
		if(count($list) >= $cnt) {
			// 时间间隔需满足20分钟，避免每次都有数据写入时提前（16分钟）触发
			$max = reset($list);
			$min = end($list);
			$time = ceil(($max['createTime'] - $min['createTime'])/60);	// 持续记录数据时长（分钟）
			if ($time < $useTime) return true;

			// 每发送一次，删除此前的全部记录。持续时长=发送频率
			foreach($list as $k => $item) {
				$model->remove($item['id']);
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
