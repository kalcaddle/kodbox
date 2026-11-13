<?php 

/**
 * 存储同步-文件执行队列
 * 
 */
class syncModelJobQueue {
	protected $tableName = 'plugin_storesync_job_queue';

	protected $tableScan = 'plugin_storesync_scan_cache';
	protected $tableFile = 'plugin_storesync_file';

	public $model;
	public $plugin;
	public function __construct($plugin){
		$this->plugin = $plugin;
		$this->model = Model($this->tableName);
		// $this->model->setDataAuto(false);
	}

	/**
	 * 查找 A/B 端新增的文件
	 * scan_cache.A/B有，file无
	 * 单向同步(A→B)时，A端有但B端scan_cache中无的文件也需重新同步（B端被手动删除的情况）
	 * @param integer $syncID
	 * @param string $side
	 * @param boolean $isBidi  是否双向同步
	 * @return array
	 */
	public function sideCreate($syncID, $side, $lastId=0, $limit=5000, $isBidi=true){
		// 先用主键索引取一批 scan_cache ID，避免 LIMIT OFFSET 全表扫描
		$batch = Model($this->tableScan)->field('id')->where(array(
			'syncID' => $syncID,
			'side'   => $side,
			'id'     => array('>', $lastId)
		))->order('id asc')->limit($limit)->select();
		if (empty($batch)) return array('affect' => 0, 'lastId' => $lastId);

		$lastId = _get(end($batch),'id');
		$idList = implode(',', array_to_keyvalue($batch, '', 'id'));

		$time = time();	// UNIX_TIMESTAMP()
		if ($side == 'A') {
			$actFile = 'CREATE_A_TO_B';
			$actDir  = 'MKDIR_A_TO_B';
		} else {
			$actFile = 'CREATE_B_TO_A';
			$actDir  = 'MKDIR_B_TO_A';
		}
		// 单向同步(A→B)时，B端被手动删除的文件（在基线表中但B端scan_cache无）也需重新同步
		$extraCond = '';
		if (!$isBidi && $side == 'A') {
			$extraCond = " OR NOT EXISTS ( SELECT 1 FROM {$this->tableScan} sc2 WHERE sc2.syncID = sc.syncID AND sc2.side = 'B' AND sc2.path = sc.path )";
		}
		// 仅在选中的 ID 范围内做 JOIN，避免全表扫描
		$sql = "INSERT IGNORE INTO {$this->tableName} (syncID, action, path, srcMtime, srcSize, status, createTime, modifyTime)
			SELECT
				sc.syncID,
				IF(sc.isFolder,'{$actDir}','{$actFile}') AS action,
				sc.path,
				sc.mtime AS srcMtime,
				sc.size AS srcSize,
				0 AS status,
				{$time} AS createTime,
				{$time} AS modifyTime
			FROM {$this->tableScan} sc
			LEFT JOIN {$this->tableFile} f ON sc.syncID = f.syncID AND sc.path = f.path
			WHERE sc.id IN ({$idList}) AND (f.path IS NULL{$extraCond})";	// f.path IS NULL：不在基线表中
		$affect = $this->model->execute($sql);	// 可以以参数形式追加，[1]=>array()
		// write_log(array(__FUNCTION__.'--------',$affect,$this->model->getLastSql()),'storesync');
		return array('affect' => $affect, 'lastId' => $lastId);
	}

	/**
	 * 查找 A/B 端修改的文件
	 * A端已修改：file有，scan_cache.A有且mtime/size不同；scan_cache.B有且mtime/size相同，或scan_cache.B无 (被删除)
	 * B端已修改：同上，相反
	 * @param integer $syncID
	 * @param string $side
	 * @return array
	 */
	public function sideUpdate($syncID, $side, $lastId=0, $limit=5000){
		// 用 file 表主键分页，避免 LIMIT OFFSET 全表扫描
		$batch = Model($this->tableFile)->field('id')->where(array(
			'syncID' => $syncID,
			'id'     => array('>', $lastId)
		))->order('id asc')->limit($limit)->select();
		if (empty($batch)) return array('affect' => 0, 'lastId' => $lastId);

		$lastId = _get(end($batch),'id');
		$idList = implode(',', array_to_keyvalue($batch, '', 'id'));

		$time = time();
		if ($side == 'A') {
			$act = 'UPDATE_A_TO_B';
			$src = 'A'; $tgt = 'B';
		} else {
			$act = 'UPDATE_B_TO_A';
			$src = 'B'; $tgt = 'A';
		}
		$sql = "INSERT IGNORE INTO {$this->tableName} (syncID, action, path, srcMtime, srcSize, status, createTime, modifyTime)
			SELECT
				f.syncID,
				'{$act}' AS action,
				f.path,
				sc1.mtime AS srcMtime,
				sc1.size AS srcSize,
				0 AS status,
				{$time} AS createTime,
				{$time} AS modifyTime
			FROM {$this->tableFile} f
			INNER JOIN {$this->tableScan} sc1 ON f.syncID = sc1.syncID AND sc1.side = '{$src}' AND f.path = sc1.path
			LEFT  JOIN {$this->tableScan} sc2 ON f.syncID = sc2.syncID AND sc2.side = '{$tgt}' AND f.path = sc2.path
			WHERE f.id IN ({$idList})
				AND sc1.isFolder = 0 -- 只处理文件
				AND (f.mtime_{$src} != sc1.mtime OR f.size_{$src} != sc1.size) -- src端发生变化
				AND (
					(sc2.path IS NOT NULL AND sc2.isFolder = 0 AND (f.mtime_{$tgt} = sc2.mtime OR f.mtime_{$tgt} = 0) AND f.size_{$tgt} = sc2.size) -- 目标端未变化（mtime=0不可靠但优先保证源端更新传播）
					OR sc2.path IS NULL -- 目标端已删除（src端更新视为有效）
				)";
		$affect = $this->model->execute($sql);
		// write_log(array(__FUNCTION__.'--------',$affect,$this->model->getLastSql()),'storesync');
		return array('affect' => $affect, 'lastId' => $lastId);
	}

	/**
	 * 查找 A/B 端删除的文件
	 * A端已删除：file有，scan_cache.A无，scan_cache.B有,且mtime/size与file.B一致
	 * B端已删除：同上，相反
	 * @param integer $syncID
	 * @param string $side
	 * @return array
	 */
	public function sideDelete($syncID, $side, $lastId=0, $limit=5000){
		// 用 file 表主键分页
		$batch = Model($this->tableFile)->field('id')->where(array(
			'syncID' => $syncID,
			'id'     => array('>', $lastId)
		))->order('id asc')->limit($limit)->select();
		if (empty($batch)) return array('affect' => 0, 'lastId' => $lastId);

		$lastId = _get(end($batch),'id');
		$idList = implode(',', array_to_keyvalue($batch, '', 'id'));

		// $side为待删除的目标端，$src为源端（已删除）；调用方: DELETE_FROM_B → $side='B' → 生成 DELETE_FROM_B/RMDIR_FROM_B
		$time = time();
		$src = ($side == 'B') ? 'A' : 'B';
		$tgt = $side;
		$actFile = 'DELETE_FROM_'.$tgt;
		$actDir  = 'RMDIR_FROM_'.$tgt;
		// 目标端 mtime=0 时（跨存储不可靠），跳过不删——避免误删已修改的文件/非空目录
		$sql = "INSERT IGNORE INTO {$this->tableName} (syncID, action, path, srcMtime, srcSize, status, createTime, modifyTime)
			SELECT
				f.syncID,
				IF(f.isFolder,'{$actDir}','{$actFile}') AS action,
				f.path,
				0 AS srcMtime, -- 删除操作不需要源文件mtime
				0 AS srcSize,  -- 删除操作不需要源文件size
				0 AS status,
				{$time} AS createTime,
				{$time} AS modifyTime
			FROM {$this->tableFile} f
			LEFT JOIN {$this->tableScan} sc1 ON f.syncID = sc1.syncID AND sc1.side = '{$src}' AND f.path = sc1.path
			LEFT JOIN {$this->tableScan} sc2 ON f.syncID = sc2.syncID AND sc2.side = '{$tgt}' AND f.path = sc2.path
			WHERE f.id IN ({$idList})
				AND sc1.path IS NULL -- 在sc1侧不存在 (已删除)
				AND f.mtime_{$tgt} != 0 -- 目标端mtime为0时不可靠，跳过避免误删
				AND (sc2.path IS NULL OR (f.mtime_{$tgt} = sc2.mtime AND f.size_{$tgt} = sc2.size)) -- 另一侧未修改（或已删除）"; 
		$affect = $this->model->execute($sql);
		// write_log(array(__FUNCTION__.'--------',$affect,$this->model->getLastSql()),'storesync');
		return array('affect' => $affect, 'lastId' => $lastId);
	}

	/**
	 * 查找 A/B 端冲突的文件
	 * A, B 端都修改，以 mtime 最新的为准
	 * @param integer $syncID
	 * @return array
	 */
	public function sideConflict($syncID, $isBidi=true, $lastId=0, $limit=5000){
		// 用 file 表主键分页
		$batch = Model($this->tableFile)->field('id')->where(array(
			'syncID' => $syncID,
			'id'     => array('>', $lastId)
		))->order('id asc')->limit($limit)->select();
		if (empty($batch)) return array('affect' => 0, 'lastId' => $lastId);

		$lastId = _get(end($batch),'id');
		$idList = implode(',', array_to_keyvalue($batch, '', 'id'));

		$time = time();
		if ($isBidi) { // 双向同步
			$colAction = "CASE WHEN sca.mtime >= scb.mtime THEN 'CONFLICT_A_WINS' ELSE 'CONFLICT_B_WINS' END";
			$colMtime  = "CASE WHEN sca.mtime >= scb.mtime THEN sca.mtime ELSE scb.mtime END";
			$colSize   = "CASE WHEN sca.mtime >= scb.mtime THEN sca.size ELSE scb.size END";
		} else { // 单向同步 A->B，冲突时A侧优先
			$colAction = "'CONFLICT_A_WINS'";
			$colMtime  = "sca.mtime";
			$colSize   = "sca.size";
		}
		$sql = "INSERT IGNORE INTO {$this->tableName} (syncID, action, path, srcMtime, srcSize, status, createTime, modifyTime)
			SELECT
				f.syncID,
				{$colAction} AS action,
				f.path,
				{$colMtime} AS srcMtime,
				{$colSize} AS srcSize,
				0 AS status,
				{$time} AS createTime,
				{$time} AS modifyTime
			FROM {$this->tableFile} f
			INNER JOIN {$this->tableScan} sca ON f.syncID = sca.syncID AND sca.side = 'A' AND f.path = sca.path
			INNER JOIN {$this->tableScan} scb ON f.syncID = scb.syncID AND scb.side = 'B' AND f.path = scb.path
			WHERE f.id IN ({$idList})
				AND sca.isFolder = 0 -- 只处理文件
				AND scb.isFolder = 0 -- 只处理文件
				AND (f.mtime_A != 0 AND (f.mtime_A != sca.mtime OR f.size_A != sca.size)) -- A端发生变化（mtime=0不可靠跳过）
				AND (f.mtime_B != 0 AND (f.mtime_B != scb.mtime OR f.size_B != scb.size)) -- B端也发生变化";
		$affect = $this->model->execute($sql);
		// write_log(array(__FUNCTION__.'--------',$affect,$this->model->getLastSql()),'storesync');
		return array('affect' => $affect, 'lastId' => $lastId);
	}

	// ----------------------------------------- 写入数据（从数据库操作） -----------------------------------------


    /**
	 * 获取队列列表
	 */
	public function getList($syncID, $status=''){
		$where = array('syncID'=>$syncID);
		if ($status !== '') {
			$where['status'] = $status;
		}
		$page	 = intval(_get($GLOBALS['in'], 'page', 1));
		$pageNum = intval(_get($GLOBALS['in'], 'pageNum', 50));
		return $this->model->where($where)->order('id asc')->selectPage($pageNum, $page);
	}


	/**
	 * 获取待执行的任务列表
	 * status=3（失败待重试）不参与 offset 分页，避免被 offset 跳过
	 * status=0（新任务）使用 offset 游标分页
	 * @param integer $syncID
	 * @param integer $lastId 仅对 status=0 生效
	 * @param integer $limit
	 * @return array
	 */
	public function getJobList($syncID, $lastId = 0, $limit = 500) {
		// 1. 拉取需重试任务
		// 1.1 status=3(失败待重试)，直接拉取——update: 间隔60秒，避免失败任务频繁重试
		$failJobs = $this->model->where(array(
			'syncID' 	=> $syncID,
			'status' 	=> 3,
			'modifyTime'=> array('lt', time() - 60),
		))->order('id asc')->limit(50)->select();
		if (!$failJobs) $failJobs = array();

		// 1.2 status=1(执行中僵尸，进程崩溃残留)，仅拉取超过5分钟的，避免误拉取正在执行的job——实际无限时的必要
		$zombieJobs = $this->model->where(array(
			'syncID' => $syncID,
			'status' => 1,
			'modifyTime' => array('lt', time() - 300),
		))->order('id asc')->limit(50)->select();
		if (!$zombieJobs) $zombieJobs = array();

		$retryJobs = array_merge($failJobs, $zombieJobs);

		// 2.拉取新任务；排序：先新建后删除、先子目录（最长）后父目录
		$retryCnt = count($retryJobs);
		$newJobs = $this->model->where(array(
			'syncID'	=> $syncID,
			'status'	=> 0,
			'id'		=> array('>', $lastId)
		))->order("CASE WHEN action LIKE 'MKDIR%' THEN 0 WHEN action LIKE 'RMDIR%' THEN 2 ELSE 1 END, LENGTH(path) DESC, id asc")->limit($limit - $retryCnt)->select();
		if (!$newJobs) $newJobs = array();

		return array_merge($retryJobs, $newJobs);
	}

	/**
	 * （按状态）获取任务队列总量
	 *
	 * @param integer $syncID
	 * @param string $status
	 * @return integer
	 */
	public function getJobCnt($syncID,$status='') {
		$where = array('syncID' => $syncID);
		if ($status !== '') {
			$where['status'] = $status;
		}
		$cnt = $this->model->where($where)->count();
		return intval($cnt);
	}

	/**
	 * 修改文件
	 */
	public function edit($id,$data){
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