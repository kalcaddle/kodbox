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
        $iter = '';
		$limit = 1000;
		while (true) {
			// check_abort();
            $headers = array(
                'Accept: application/json',
                'x-list-limit: ' . $limit,	// 默认100，最大10000
            );
            if($iter) $headers[] = 'x-list-iter: ' . $iter;	// 为g2gCZAAEbmV4dGQAA2VvZg时，表示最后一个分页
            $res = $this->ussRequest($path, 'GET', false, $headers);
            if (!$res['code']) break;
            $iter  = _get($res, 'data.iter', '');
            $files = _get($res, 'data.files', array());

            // // （当前）文件总数
			// $GLOBALS['STORE_IMPORT_FILE_CNT'] += count($files);

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

    /**
     * 按批次获取文件列表（生成器）
     * @param [type] $path
     * @param integer $batchSize
     * @return void
     */
    public function listPath($path, $batchSize=100000) {
        $path = rtrim($path,'/');

        $stack = array(array('path' => $path . '/', 'iter' => ''));
        $buffer = array();
        $bufferCount = 0;

        // 添加安全计数器
        $maxIterations = 10000; // 最大迭代数
        $iteration = 0;
        while ($stack || $bufferCount > 0) {
            $iteration++;
            if ($iteration > $maxIterations) {
                break;
            }

            // 如果缓冲已满，先yield出去
            if ($bufferCount >= $batchSize) {
                yield $buffer;
                $buffer = array();
                $bufferCount = 0;
            }
            // 当栈空但缓冲有数据时，直接处理缓冲
            if (empty($stack) && $bufferCount > 0) {
                yield $buffer;
                $buffer = array();
                $bufferCount = 0;
                break; // 栈已空，缓冲已清空，退出循环
            }

            // 如果缓冲不足且栈还有目录需要处理
            if ($bufferCount < $batchSize && $stack) {
                $current = array_pop($stack);
                $path = $current['path'];
                $iter = $current['iter'];

                // 获取列表
                $options = array(
                    'limit' => 1000,
                    'iter'  => $iter,
                );
                $res = $this->listFiles($path, $options);
                if (!$res['code']) break;    // continue
                $nextIter = _get($res, 'data.iter', '');
                $isEnd = ($nextIter === 'g2gCZAAEbmV4dGQAA2VvZg');

                $items = array();
                foreach(_get($res, 'data.files', array()) as $item) {
                    $item['path'] = $path.$item['name'];
                    $items[] = $this->makeItem($item);
                }
                // 将当前页的项加入缓冲
                foreach ($items as $item) {
                    $buffer[] = $item;
                    $bufferCount++;

                    // 如果缓冲达到batchSize，立即yield
                    if ($bufferCount >= $batchSize) {
                        yield $buffer;
                        $buffer = array();
                        $bufferCount = 0;
                    }
                }

                // 如果当前目录还有下一页，重新入栈继续
                if (!$isEnd && $nextIter !== '') {
                    array_push($stack, ['path' => $path, 'iter' => $nextIter]);
                }

                // 处理完当前页的所有项后，发现的新子文件夹入栈（深度优先）
                // 注意：这里要倒序 push，确保原始顺序（又拍云返回是升序）——后进先出
                foreach (array_reverse($items) as $item) {
                    if ($item['folder']) {
                        array_push($stack, ['path' => $item['path'] . '/', 'iter' => '']);
                    }
                }
            }
        }

    }

    // 请求网络获取文件列表
    private function listFiles($path, $options) {
        $headers = array(
            'Accept: application/json',
            'x-list-limit: ' . $options['limit'],	// 默认100，最大10000
        );
        if($options['iter']) $headers[] = 'x-list-iter: ' . $options['iter'];	// 为g2gCZAAEbmV4dGQAA2VvZg时，表示最后一个分页
        return $this->ussRequest($path, 'GET', false, $headers);
    }

    // 生成列表文件项
	private function makeItem($item) {
        $isFolder = $item['type'] == 'folder' ? 1 : 0;
        return array(
            'path'		=> $item['path'].($isFolder ? '/' : ''),
            'folder'	=> $isFolder,
            'modifyTime'=> intval($item['last_modified']),
            'size'		=> intval($item['length']),
        );
	}

}