<?php 
// Qiniu
class impDrvQiniu extends PathDriverQiniu {
	public function __construct($config, $type='') {
		parent::__construct($config);
	}

	/**
	 * 获取指定目录下所有列表，返回生成器
	 * @param [type] $path
	 * @return void
	 */
    public function listAll($path){
		$path	= trim($path, '/');
		$prefix = (empty($path) && $path !== '0') ? '' : $path . '/'; // 要列举文件的公共前缀（父目录）；首位没有'/',否则获取为空
		$marker = '';	// 上次列举返回的位置标记，作为本次列举的起点信息。
		$limit	= 1000; // 本次列举的条目数
		$delimiter = '';

		while (true) {
			// check_abort();
			// 列举文件，获取目录下的文件，末尾需加"/"，不加则只获取目录本身
			list($ret, $err) = $this->bucketManager->listFiles($this->bucket, $prefix, $marker, $limit, $delimiter);
			if ($err) break;

			$marker 	= array_key_exists('marker', $ret) ? $marker = $ret["marker"] : '';
			$listObject = _get($ret, 'items', array());	// 文件列表
			$listPrefix = _get($ret, 'commonPrefixes', array());	// 文件夹列表，$delimiter=/时存在该字段

			// （当前）文件总数
			$GLOBALS['STORE_IMPORT_FILE_CNT'] += count($listObject);

			foreach ($listObject as $item) {
				if ($item['key'] == $prefix) { continue;}
				$name = $item['key'];
				$size = $item['fsize'];
				$time = !empty($item['putTime']) ? substr($item['putTime'].'',0,10) : 0;
				$isFolder = ($size == 0 && substr($name,strlen($name) - 1,1) == '/') ? 1:0;
				yield array(
                    'path'		=> $name,
                    'folder'	=> $isFolder,
                    'modifyTime'=> $time,
                    'size'		=> $size,
                );
			}
			foreach ($listPrefix as $name) {
				if ($name == $prefix) { continue;}
				yield array(
                    'path'		=> $name,
                    'folder'	=> 1,
                    'modifyTime'=> 0,
                    'size'		=> 0,
                );
			}
			if ($marker === '') {break;}
		}
	}

}