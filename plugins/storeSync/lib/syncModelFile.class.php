<?php 

/**
 * 存储同步-文件基线（已同步文件列表）
 * 
 */
class syncModelFile {
	protected $tableName = 'plugin_storesync_file';

	public $model;
	public $plugin;
	public function __construct($plugin){
		$this->plugin = $plugin;
		$this->model = Model($this->tableName);
		// $this->model->setDataAuto(false);
	}

    /**
	 * 获取基线列表
	 */
	public function getList($syncID=0){
		$where = array();
		if ($syncID) $where['syncID'] = $syncID;
		if ($where) $this->model->where($where);
		return $this->model->order('id asc')->limit(1000)->select();
	}

    /**
	 * 获取基线信息
	 */
	public function find($syncID,$path){
		$where = array(
			'syncID' => $syncID,
			'path'	 => $path,
		);
		return $this->model->where($where)->find();
	}

    /**
	 * 添加基线
	 */
	public function add($syncID,$data){
		$time = time();
		$insert = array(
			'syncID' 	=> $syncID,
			'path'		=> _get($data,'path',''),
			'isFolder'	=> _get($data,'isFolder',0),
			'size_A'	=> _get($data,'size_A',0),
			'mtime_A'	=> _get($data,'mtime_A',0),
			'fileID_A'	=> 0,	// TODO 备用
			'size_B'	=> _get($data,'size_B',0),
			'mtime_B'	=> _get($data,'mtime_B',0),
			'fileID_B'	=> 0,
			'createTime'=> $time,
			'modifyTime'=> $time,
		);
		return $this->model->add($insert);
	}

	/**
	 * 修改基线
	 */
	public function edit($syncID,$path,$data=array()){
		$where = array(
			'syncID' => $syncID,
			'path'	 => $path,
		);
		$data['modifyTime'] = time();
		return $this->model->where($where)->save($data);
	}

    /**
	 * 删除基线
	 */
	public function remove($syncID,$path){
		$where = array(
			'syncID' => $syncID,
			'path'	 => $path,
		);
		return $this->model->where($where)->delete();
	}

	/**
	 * 清空基线（按任务）
	 */
	public function removeAll($syncID){
		$cnt = 0;
		$batchSize = 10000;
		while (true) {
			$ids = $this->model->where(array("syncID"=>$syncID))->field('id')->limit($batchSize)->select();
			if (empty($ids)) break;
			$ids = array_to_keyvalue($ids, '', 'id');
			$cnt += $this->model->where(array('id' => array('in', $ids)))->delete();
		}
		return $cnt;
	}

}