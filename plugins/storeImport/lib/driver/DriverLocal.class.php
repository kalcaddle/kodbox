<?php 
/**
 * 本地存储拓展方法
 */
class impDrvLocal extends PathDriverLocal {
	public function __construct($config, $type='') {
		parent::__construct($config);
	}

	/**
	 * 获取指定目录下所有列表，返回生成器
	 * @param [type] $path
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
     * @param [type] $path
     * @param integer $batchSize
     * @return void
     */
	public function listPath($path, $batchSize=100000) {
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
			if ($items === false) continue; // 无法读取目录，跳过

			foreach ($items as $item) {
				if ($item === '.' || $item === '..') continue;
				$fullPath = $curPath . $item;

				// 获取文件信息
				$stat = @stat($fullPath);
				if ($stat === false) continue; // 无法获取信息，跳过

				$isFolder = is_dir($fullPath);
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