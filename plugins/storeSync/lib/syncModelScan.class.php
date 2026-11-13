<?php 

/**
 * 存储同步-目录扫描队列
 * 
 */
class syncModelScanQueue {
	protected $tableName = 'plugin_storesync_scan_queue';

	public $model;
	public $plugin;
	public function __construct($plugin){
		$this->plugin = $plugin;
		$this->model = Model($this->tableName);
		$this->model->setDataAuto(false);
	}

    /**
	 * 获取队列列表
	 */
	public function getList($syncID=0,$side='',$limit=100){
		$where = array();
		if ($syncID) $where['syncID'] = $syncID;
		if ($side) $where['side'] = $side;
		if ($where) $this->model->where($where);
		return $this->model->order('id asc')->limit($limit)->select();
	}

    /**
	 * 获取队列信息
	 */
	public function find($id){
		return $this->model->where(array("id"=>$id))->find();
	}

    /**
	 * 添加队列
	 */
	public function add($syncID,$side,$data){
		$insert = $this->parseInsert($syncID,$side,$data);
		return $this->model->add($insert);
	}

    /**
	 * 批量添加队列
	 */
	public function addAll($syncID,$side,$data){
		$insert = array();
		foreach ($data as $item) {
			// 兼容旧格式（字符串）和新格式（关联数组含path和relPath）
			if (is_array($item)) {
				$insert[] = $this->parseInsert($syncID,$side,$item);
			} else {
				$insert[] = $this->parseInsert($syncID,$side,array('path'=>$item));
			}
		}
		return $this->model->addAll($insert,array(),true);
	}
	private function parseInsert($syncID,$side,$data) {
		return array(
			'syncID'	=> $syncID,
			'side'	    => $side,
			'path'	    => $data['path'],
			'relPath'	=> _get($data, 'relPath', ''),
			'createTime'=> time()
		);
	}

	/**
	 * 修改队列
	 */
	public function edit($id,$data){
		// $info = $this->find($id);
		// if(!$info) return false;
		return $this->model->where(array("id"=>$id))->save($data);
	}

    /**
	 * 删除队列
	 */
	public function remove($id){
		return $this->model->where(array("id"=>$id))->delete();
	}

	/**
	 * 清空队列（按任务）
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

/**
 * 存储同步-文件扫描结果（临时）
 * 
 */
class syncModelScanCache {
	protected $tableName = 'plugin_storesync_scan_cache';

	public $model;
	public $plugin;
	public function __construct($plugin){
		$this->plugin = $plugin;
		$this->model = Model($this->tableName);
		$this->model->setDataAuto(false);
	}

    /**
	 * 获取队列信息
	 */
	public function find($id){
		return $this->model->where(array("id"=>$id))->find();
	}

    /**
	 * 添加文件
	 */
	public function add($syncID,$side,$data){
		$insert = $this->parseInsert($syncID,$side,$data);
		return $this->model->add($insert);
	}

    /**
	 * 批量添加文件
	 */
	public function addAll($syncID,$side,$data){
		$insert = array();
		foreach ($data as $item) {
			$insert[] = $this->parseInsert($syncID,$side,$item);
		}
		return $this->model->addAll($insert,array(),true);
	}

	private function parseInsert($syncID,$side,$data) {
		return array(
			'syncID'	=> $syncID,
			'side'	    => $side,
			'path'	    => $data['path'],
			'isFolder'	=> $data['isFolder'],
			'size'	    => $data['size'],
			'mtime'	    => $data['mtime'],
			'createTime'=> time()
		);
	}

	/**
	 * 修改文件
	 */
	public function edit($id,$data){
		// $info = $this->find($id);
		// if(!$info) return false;
		return $this->model->where(array("id"=>$id))->save($data);
	}

    /**
	 * 删除文件
	 */
	public function remove($id){
		return $this->model->where(array("id"=>$id))->delete();
	}

    /**
	 * 清空文件（按任务）
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