<?php 
// OSS
class impDrvOSS extends PathDriverOSS {
	public function __construct($config, $type='') {
		parent::__construct($config);
	}

	/**
	 * 获取指定目录下所有列表，返回生成器
	 * @param [type] $path
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

			// （当前）文件总数
			$GLOBALS['STORE_IMPORT_FILE_CNT'] += count($listObject);

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
}