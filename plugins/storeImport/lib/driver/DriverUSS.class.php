<?php 
// USS
class impDrvUSS extends PathDriverUSS {
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
     * @param string $path
     * @param integer $batchSize
     * @return void
     */
    public function listPathBatch($path, $batchSize=100000) {
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
                if (!$res['code']) {
                    $this->addErr('list_failed', $path, '列举接口失败，已提前结束：' . _get($res, 'msg', '未知原因'));
                    break;    // continue
                }
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
                    array_push($stack, array('path' => $path, 'iter' => $nextIter));
                }

                // 处理完当前页的所有项后，发现的新子文件夹入栈（深度优先）
                // 注意：这里要倒序 push，确保原始顺序（又拍云返回是升序）——后进先出
                foreach (array_reverse($items) as $item) {
                    if ($item['folder']) {
                        array_push($stack, array('path' => $item['path'] . '/', 'iter' => ''));
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