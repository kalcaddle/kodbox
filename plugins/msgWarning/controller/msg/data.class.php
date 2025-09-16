<?php 
/**
 * 通知事件——数据资产类
 */
class msgWarningMsgData extends Controller {
	public function __construct() {
		parent::__construct();
		$this->pluginName = 'msgWarningPlugin';
    }

	public function index($evntInfo) {
		$data = array();
		switch ($evntInfo['event']) {
			case 'dataFileDownErr':
				$data = $this->dataFileDownErr($evntInfo);
				break;
			default:
				# code...
				break;
		}
		return $data;
	}


	/**
	 * 文件下载异常提醒
	 * @param [type] $evntInfo
	 * @return void
	 */
	public function dataFileDownErr($evntInfo) {
		// 触发条件
		$policy = $evntInfo['policy'];
		if (!$policy) return array();
		$cntMax = intval(_get($policy, 'cntMax', 0));
		if (!$cntMax) return array();

		// 超限的用户id列表缓存
		$cckey = $this->pluginName.'.dataFileDownErr.'.date('Ymd');
		$cache = Cache::get($cckey);
		$users = $cache ? explode(',', $cache) : array();

		// 统计用户下载文件次数
		$timeFrom 	= strtotime(date('Y-m-d')); 
		$timeTo 	= time();
		$where = array(
			'createTime'=> array('between', array($timeFrom, $timeTo)),
			'type'		=> array('in', array('explorer.index.fileOut', 'explorer.index.fileDownload',)),
		);
		if ($users) $where['userID'] = array('not in', $users);
		$fields = array('userID', 'count(*)' => 'cnt');
		$list = Model('SystemLog')->where($where)->group('userID')->field($fields)->select();
		$data = array();
		foreach ($list as $item) {
		    $cnt = intval($item['cnt']);
			if (!$cnt || $cnt < $cntMax) continue;
			$data[] = $item['userID'];
		}
		$users = array_unique(array_merge($users, $data));
		if (!$users) return array();
		Cache::set($cckey, implode(',', $users));

		// 获取用户名单
		$list = Model('User')->where(array('userID' => array('in', $users)))->field('userID, name, nickName')->select();
		$user = array();
		foreach ($list as $item) {
		    $user[] = $item['nickName'] ? $item['nickName'] : $item['name'];
		}

		// 前端提醒只显示基础消息（第1条），后端消息展示全部
		$msg = sprintf(LNG('msgWarning.data.downFileErr'), date('Y-m-d H:i'), count($users), $cntMax);
		return array($msg, implode(',', $user));
	}

}
