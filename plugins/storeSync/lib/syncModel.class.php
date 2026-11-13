<?php 

/**
 * 存储同步任务模型
 * 
 */
class syncModel extends ModelBase{
	protected $tableName = 'plugin_storesync';
	protected $dataAuto = array(
		array('modifyTime','time','insert,update','function'), 	//插入或更新时自动添加 callback=当前类方法/function=全局函数;
		array('createTime','time','insert','function'), 		//插入时自定添加

		// 无法自动执行，原因未知
		// array('cursor','textEncode','insert,update','filter'),
		// array('cursor','textDecode','select','filter'),
		// array('lastSyncData','textEncode','insert,update','filter'),
		// array('lastSyncData','textDecode','select','filter'),
	);

	public $plugin;
	public function __construct($plugin){
		parent::__construct();
		$this->plugin = $plugin;
	}


    /**
	 * 任务列表
	 */
	protected function syncList($id=false,$json=true){
		if (!is_numeric($id)) $id = false;       // 过滤非数值参数——不传参时为方法名，原因未知
        if ($id) $this->where(array('syncID'=>intval($id)));
		$list = $this->select();
		if (!$list) $list = array();
		if ($json) $this->_parseList($list);
        return ($id && $list) ? $list[0] : $list;
	}
	private function _parseList(&$list) {
		if (empty($list)) return;
		foreach ($list as &$info) {
			foreach (array('cursor','lastSyncData') as $key) {
				$val = json_decode($info[$key], true);
				$info[$key] = $val ? $val : array();
			}
		};unset($info);
	}

	/**
	 * 任务信息
	 * @param integer $id
	 * @return void
	 */
	protected function syncInfo($id,$json=true) {
		return $this->syncList($id,$json);
	}

    /**
	 * 添加任务
	 */
	protected function syncAdd($param){
		$insert = array(
			'title'		=> $param['title'],
			'pathA'	    => $param['pathA'],
			'pathB'	    => $param['pathB'],
			'isBidi'	=> intval($param['isBidi']),
			'rptType'	=> intval($param['rptType']),
			'status'	=> 0,
			'enable'	=> isset($param['enable']) ? intval($param['enable']) : 1,
            'errMsg'  	=> '',
            'cursor'  	=> '',
            'lastSyncData'  => '',
            'lastSyncTime'  => 0,
			// 'createUser'=> USER_ID,
			// 'modifyUser'=> USER_ID,	// user可能没有必要
		);
		return $this->add($insert);
	}

	/**
	 * 同步任务修改
	 */
	protected function syncEdit($syncID,$data){
		$info = $this->syncList($syncID);
		if(!$info) return false;
		// $data['modifyUser'] = USER_ID;

		foreach (array('cursor','lastSyncData') as $key) {
			if (isset($data[$key]) && is_array($data[$key])) {
				$data[$key] = json_encode_force($data[$key]);
			}
		}
		return $this->where(array("syncID"=>$syncID))->save($data);
	}

    /**
	 * 同步任务删除
	 */
	protected function syncRemove($syncID){
		$this->startTrans();
		try {
			// 删除关联表数据
			$this->plugin->modelScanQueue->removeAll($syncID);
			$this->plugin->modelScanCache->removeAll($syncID);
			$this->plugin->modelJobQueue->removeAll($syncID);
			$this->plugin->modelFile->removeAll($syncID);
			// 删除主任务表数据
			$where = array('syncID' => $syncID);
			$this->where($where)->delete();
			$this->commit();
			return true;
		} catch (Exception $e) {
			$this->rollback();
			return false;
		}
	}

    /**
	 * 同步任务重置
	 */
	protected function syncReset($syncID){
		$info = $this->syncList($syncID);
		if(!$info) return false;

		$this->startTrans();
		try {
			// 删除关联表临时数据
			$this->plugin->modelScanQueue->removeAll($syncID);
			$this->plugin->modelScanCache->removeAll($syncID);
			$this->plugin->modelJobQueue->removeAll($syncID);
			// 初始化主任务表数据
			$update = array(
				'enable'        => 1,
				'status'        => 0,
				'errMsg'  	    => '',
				'cursor'  	    => '',
				'lastSyncData'  => '',
				'lastSyncTime'  => 0,
			);
			$this->where(array("syncID"=>$syncID))->save($update);
			$this->commit();
			return true;
		} catch (Exception $e) {
			$this->rollback();
			return false;
		}
	}

}