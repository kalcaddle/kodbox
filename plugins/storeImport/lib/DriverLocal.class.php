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
		$list = array_filter($entries, function($entry) use ($path) {
			return $entry != "." && $entry != "..";
		});

		// （当前）文件总数
		$GLOBALS['STORE_IMPORT_FILE_CNT'] += count($list);

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

}