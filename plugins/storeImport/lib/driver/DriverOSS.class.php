<?php 
// OSS
class impDrvOSS extends PathDriverOSS {
	/**
	 * 列表过程中的异常记录（与 DriverLocal 同一套结构，供 app.php 汇总进“文件导入明细”）
	 * list_failed：分页/列举接口失败 → 剩余条目不会再被导入（原来只写日志或完全静默）
	 */
	public $errList = array('unreadable' => array(), 'stat' => array(), 'symlink' => array(), 'list_failed' => array());
	private $lastErr = '';

	// 记录一条列表异常（obj 与 reason 用 \t 分隔，与 DriverLocal 的格式一致）
	protected function addErr($type, $obj, $reason = '') {
		$this->errList[$type][] = $obj . "\t" . $reason;
	}

	public function __construct($config, $type='') {
		parent::__construct($config);
	}

	/**
	 * 获取指定目录下所有列表，返回生成器
	 * @param string $path
	 * @return void
	 */
    public function listAll($path){
		$path = trim($path, '/');
		$prefix = (empty($path) && $path !== '0') ? '' : $path . '/'; // 需要加上/,才能找到该目录,根目录为空
		$nextMarker = '';
		$maxkeys = 1000;

		$folderList = $fileList = array();
		while (true) {
			// check_abort();
			$options = array(
				'delimiter'	 => '',
				'prefix'	 => $prefix,
				'max-keys'	 => $maxkeys,
				'marker'	 => $nextMarker,
			);
			try {
				$listObjectInfo = $this->client->listObjects($this->bucket, $options);
			} catch (OSS\Core\OssException $e) {
				$this->writeLog(__FUNCTION__.'|'.$e->getMessage());
				break;
			}
			$nextMarker = $listObjectInfo->getNextMarker();
			$listObject = $listObjectInfo->getObjectList();	// 文件列表

			// // （当前）文件总数
			// $GLOBALS['STORE_IMPORT_FILE_CNT'] += count($listObject);

			foreach ($listObject as $objectInfo) {
				if ($objectInfo->getKey() == $prefix) { continue;}	// 包含一条文件夹自身的数据
				$name = $objectInfo->getKey();
				$size = $objectInfo->getSize();
                $time = strtotime($objectInfo->getLastModified());

				$isFolder = ($size == 0 && substr($name,strlen($name) - 1,1) == '/') ? 1 : 0;
                yield array(
                    'path'		=> $name,
                    'folder'	=> $isFolder,
                    'modifyTime'=> $time,
                    'size'		=> $size,
                );
			}
			if ($nextMarker === '') {break;}
		}
	}

	/**
     * 按批次获取文件列表（生成器）
     * @param string $path
     * @param integer $batchSize
     * @return void
     */
	public function listPathBatch($path, $batchSize=100000) {
		$path	= trim($path, '/');
		$prefix = (empty($path) && $path !== '0') ? '' : $path . '/';
		$nextMarker = '';
		$maxkeys = 1000;

		$buffer = array();
		$bufferCount = 0;

		while (true) {
			$options = array(
				'prefix'    => $prefix,
				'marker'    => $nextMarker,
				'limit'     => $maxkeys,
			);
			$listObjectInfo = $this->listFiles($path, $options);
			if ($listObjectInfo === false) {
				// 记录异常：剩余条目不会再被导入（原来只写日志，导入汇总里看不到）
				$this->addErr('list_failed', $path, '列举接口失败，已提前结束：' . ($this->lastErr ?: '未知原因'));
				// 失败时如果有缓冲，先yield出去
				if ($bufferCount > 0) {
					yield $buffer;
				}
				break;
			}
			$nextMarker = $listObjectInfo->getNextMarker();
			if (!$nextMarker) $nextMarker = '';
			$listObject = $listObjectInfo->getObjectList();
			if (!$listObject) $listObject = array();

			// 如果列表为空且没有下一页，结束循环
			if (empty($listObject) && $nextMarker === '') {
				break;
			}

			// 处理当前页对象
			foreach ($listObject as $objectInfo) {
				// 跳过目录本身
				$key = $objectInfo->getKey();
				if ($key == $prefix) {
					continue;
				}
				$buffer[] = $this->makeItem($objectInfo);
				$bufferCount++;
				// 缓冲满了就yield
				if ($bufferCount >= $batchSize) {
					yield $buffer;
					$buffer = array();
					$bufferCount = 0;
				}
			}

			// 如果没有下一页，结束循环
			if ($nextMarker === '') {
				break;
			}
			// 安全保护：如果nextMarker与上一次相同，避免死循环
			static $lastMarker = null;
			if ($lastMarker === $nextMarker) {
				$this->addErr('list_failed', $path, '分页标记重复，已提前结束（防死循环保护）');
				break;
			}
			$lastMarker = $nextMarker;
		}

		// 剩余部分
		if ($bufferCount > 0) {
			yield $buffer;
		}
	}

	// 按批次获取文件/夹列表
	private function listFiles($path, $options) {
		$prefix = $options['prefix'];
		try {
			$options = array(
				'delimiter'	 => '',
				'prefix'	 => $prefix,
				'max-keys'	 => $options['limit'],
				'marker'	 => $options['marker'],
			);
			$listObjectInfo = $this->client->listObjects($this->bucket, $options);
		} catch (OSS\Core\OssException $e) {
			$this->writeLog(__FUNCTION__.'|'.$e->getMessage());
			return false;
		}
		return $listObjectInfo;
	}

	// 生成列表文件项
	private function makeItem($objectInfo) {
		$name = $objectInfo->getKey();
		$size = $objectInfo->getSize();
		$time = strtotime($objectInfo->getLastModified());

		$isFolder = ($size == 0 && substr($name,strlen($name) - 1,1) == '/') ? 1 : 0;
		return array(
			'path'		=> $name,
			'folder'	=> $isFolder,
			'modifyTime'=> $time,
			'size'		=> $size,
		);
	}
}