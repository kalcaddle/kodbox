<?php 
// USS
class impDrvUSS extends PathDriverUSS {
	public function __construct($config, $type='') {
		parent::__construct($config);
	}

	/**
	 * 获取指定目录下所有列表，返回生成器
	 * @param [type] $path
	 * @return void
	 */
    public function listAll($path){
		$path = rtrim($path,'/').'/';
        $start = '';
		$limit = 1000;
		while (true) {
			// check_abort();
            $headers = array(
                'Accept: application/json',
                'x-list-limit: ' . $limit,	// 默认100，最大10000
            );
            if($start) $headers[] = 'x-list-iter: ' . $start;	// 为g2gCZAAEbmV4dGQAA2VvZg时，表示最后一个分页
            $res = $this->ussRequest($path, 'GET', false, $headers);
            if (!$res['code']) break;
            $start = _get($res, 'data.iter', '');
            $files = _get($res, 'data.files', array());

            // （当前）文件总数
			$GLOBALS['STORE_IMPORT_FILE_CNT'] += count($files);

			foreach($files as $item) {
                $fullpath = $path.$item['name'];
                $isFolder = $item['type'] == 'folder' ? 1 : 0;
                $fullpath = $isFolder ? $fullpath.'/' : $fullpath;
                $time = intval($item['last_modified']);
                $size = intval($item['length']);
				yield array(
                    'path'		=> $fullpath,
                    'folder'	=> $isFolder,
                    'modifyTime'=> $time,
                    'size'		=> $size,
                );
				if ($isFolder) {
					foreach ($this->listAll($fullpath) as $subItem) {
                        yield $subItem;
                    }
				}
			}
			if(count($files) < $limit) break;
		}

    }

}