<?php 
/**
 * 本地存储拓展方法
 */
class impDrvLocal extends PathDriverLocal {
	/**
	 * 遍历过程中的异常记录（只记录，不改变既有遍历行为）
	 * unreadable: 目录无法读取 —— 整棵子树会被跳过，是“导入后少文件/少字节”的头号原因
	 * stat      : 条目无法 stat（断链软链、权限不足等）
	 * symlink   : 软链接目录 —— 目前会被继续向下遍历（listAll 跳过、listPathBatch 不跳，两者行为不一致）
	 */
	public $errList = array('unreadable' => array(), 'stat' => array(), 'symlink' => array(), 'list_failed' => array());

	public function __construct($config, $type='') {
		parent::__construct($config);
	}

	// 取最近一次被 @ 抑制的错误原因（供上面几类异常记录使用）
	private function lastError(){
		$err = error_get_last();
		return ($err && !empty($err['message'])) ? $err['message'] : '未知原因';
	}
	// 各类异常条数
	public function errSummary(){
		$out = array();
		foreach ($this->errList as $k => $v) { $out[$k] = count($v); }
		return $out;
	}

	/**
	 * 获取指定目录下所有列表，返回生成器
	 * @param string $path
	 * @return void
	 */
	public function listAll($path, &$result = array()) {
		$path = $this->iconvSystem($path);
		$path = rtrim($path,'/').'/';
		// readdir依次读取，无法阶段性获取（当前目录下的）文件总数，故改用scandir
		$entries = scandir($path);  
		if (!$entries) $entries = array();
		$list = array_filter($entries, function($entry) use ($path) {
			return $entry != "." && $entry != "..";
		});

		// // （当前）文件总数
		// $GLOBALS['STORE_IMPORT_FILE_CNT'] += count($list);

		foreach ($list as $file) {
			$fullpath = $path.$file;
			$isFolder = (is_dir($fullpath) && !is_link($fullpath)) ? 1:0;
			$fullpath = $isFolder ? $fullpath.'/' : $fullpath;
            $time = intval(@filemtime($fullpath));
            $size = $isFolder ? 0 : intval($this->size($fullpath));
			yield array(
				'path'		=> $fullpath,
				'folder'	=> $isFolder,
				'modifyTime'=> $time,
				'size'		=> $size,
			);
			if($isFolder){
                // 递归返回的是一个子生成器对象，需要显式地迭代其结果并yeild给父生成器
                foreach ($this->listAll($fullpath, $result) as $subItem) {
                    yield $subItem;
                }
			}
		}
		unset($list);
	}

	/**
     * 按批次获取文件列表（生成器）
     * @param string $path
     * @param integer $batchSize
     * @return void
     */
	public function listPathBatch($path, $batchSize=100000) {
		$path = rtrim($path, '/') . '/';
		if (!is_dir($path)) return array();

		// 使用栈手动遍历，避免递归迭代器的内存开销
		$stack = array($path);
		$batch = array();
		$processed = 0;

		while (!empty($stack)) {
			$curPath = array_pop($stack);
			// 读取当前目录
			$items = @scandir($curPath);
			if ($items === false) {
				// 原为静默 continue：目录不可读时整棵子树会被无声跳过，必须记录
				$this->errList['unreadable'][] = $curPath . "\t" . $this->lastError();
				continue;
			}

			foreach ($items as $item) {
				if ($item === '.' || $item === '..') continue;
				$fullPath = $curPath . $item;

				// 获取文件信息
				$stat = @stat($fullPath);
				if ($stat === false) {
					// 原为静默 continue：断链软链、无权限等会整条丢掉
					$this->errList['stat'][] = $fullPath . "\t" . $this->lastError();
					continue;
				}

				$isFolder = is_dir($fullPath);
				if ($isFolder && is_link($fullPath)) {
					// 仅记录：同一个物理目录会被按两条路径各导入一次（软链路径 + 真实路径）
					$this->errList['symlink'][] = $fullPath;
				}
				$batch[] = array(
					'path'      => $fullPath . ($isFolder ? '/' : ''),	// 调用getPathOuter无效，$this->pathDriver缺失
					'folder'    => (int)$isFolder,
					'modifyTime'=> $stat['mtime'],
					'size'      => $isFolder ? 0 : $stat['size'],
				);
				$processed++;

				// 如果是目录，加入栈
				if ($isFolder) {
					$stack[] = $fullPath . '/';
				}

				// 达到批次大小时yield
				if ($processed >= $batchSize) {
					yield $batch;
					$batch = array();
					$processed = 0;
				}
			}
		}

		// 返回剩余的数据
		if (!empty($batch)) {
			yield $batch;
		}
	}

}