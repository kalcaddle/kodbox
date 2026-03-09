<?php 
/**
 * S3系存储拓展方法——有新增s3系存储时在Driver.ioList.s3数组中追加
 */
class impDrvS3 {
    private $instance;

    public function __construct($config, $type) {
        $className = 'PathDriver'.$type;    // PathDriverEOS
        // 动态实例化传入的子类并传递参数
        if (class_exists($className)) {
            $this->instance = new $className($config);
        } else {
            throw new Exception("Class $className not found.");
        }
    }

    // 动态调用子类的方法
    public function __call($method, $arguments) {
        if (method_exists($this->instance, $method)) {
            return call_user_func_array([$this->instance, $method], $arguments);
        } else {
            throw new Exception("Method $method not found in " . get_class($this->instance));
        }
    }

    /**
	 * 获取指定目录下所有列表，返回生成器
	 * @param [type] $path
	 * @return void
	 */
    public function listAll($path){
        $client = $this->getProtMember('client');
        $bucket = $this->getProtMember('bucket');

        $path = trim($path, '/');
        $prefix = (empty($path) && $path !== '0') ? '' : $path . '/';	// 要列举文件的公共前缀
		$nextMarker = null;	// 上次列举返回的位置标记（文件key），作为本次列举的起点信息。
        $maxKey = 1000;
        $delimiter = '';

		$hasFile = $hasFolder = array();
		while (true) {
		    $result = $client->getBucket($bucket, $prefix, $nextMarker, $maxKey, $delimiter, true);
			if (!$result) break;
			$nextMarker = $result['nextMarker'];
			$listObject = $result['listObject'];
			$listPrefix = $result['listPrefix'];

            // // （当前）文件总数
			// $GLOBALS['STORE_IMPORT_FILE_CNT'] += count($listObject);

            foreach ($listObject as $objectInfo) {
                $name = $objectInfo['name'];
                if ($name == $prefix) { continue;}	// 包含一条文件夹自身的数据
                $time = _get($objectInfo, 'time', 0);
                $size = _get($objectInfo, 'size', 0);
                $isFolder = ($size == 0 && substr($name,strlen($name) - 1,1) == '/') ? 1:0;
                yield array(
                    'path'		=> $name,
                    'folder'	=> $isFolder,
                    'modifyTime'=> $time,
                    'size'		=> $size,
                );
            }
            foreach ($listPrefix as $objectInfo) {
                yield array(
                    'path'		=> $objectInfo['name'],
                    'folder'	=> 1,
                    'modifyTime'=> 0,
                    'size'		=> 0,
                );
            }
			if ($nextMarker === null) { break; }
		}
    }

    // 使用反射获取protected属性/方法
    private function getProtMember($name) {
        $reflectionClass = new ReflectionClass($this->instance);
        // 先尝试获取属性
        if ($reflectionClass->hasProperty($name)) {
            $property = $reflectionClass->getProperty($name);
            $property->setAccessible(true);
            return $property->getValue($this->instance);
        }
        // 如果属性不存在，尝试调用方法
        if ($reflectionClass->hasMethod($name)) {
            $args = func_get_args(); // 获取所有传入的参数
            array_shift($args);
            $method = $reflectionClass->getMethod($name);
            $method->setAccessible(true);
            // $method->invoke($this->instance, $param);
            return call_user_func_array([$method, 'invoke'], array_merge([$this->instance], $args));
        }
        throw new Exception("Property or method $name not found in " . get_class($this->instance));
    }

    /**
     * 按批次获取文件列表（生成器）
     * @param [type] $path
     * @param integer $batchSize
     * @return void
     */
	public function listPath($path, $batchSize=100000) {
        $this->client = $this->getProtMember('client');
        $this->bucket = $this->getProtMember('bucket');

		$path	= trim($path, '/');
		$prefix = (empty($path) && $path !== '0') ? '' : $path . '/';
		$nextMarker = null;
		$maxkeys = 1000;

		$buffer = [];
		$bufferCount = 0;

		while (true) {
			$options = array(
				'prefix'    => $prefix,
				'marker'    => $nextMarker,
				'limit'     => $maxkeys,
			);
			$ret = $this->listFiles($path, $options);
			if ($ret === false) {
				// 失败时如果有缓冲，先yield出去
				if ($bufferCount > 0) {
					yield $buffer;
				}
				break;
			}
			$nextMarker	= _get($ret, 'nextMarker', null);
			$listObject = _get($ret, 'listObject', array());	// 文件列表
			$listPrefix = _get($ret, 'listPrefix', array());	// 文件列表

			// 如果列表为空且没有下一页，结束循环
			if (empty($listObject) && $nextMarker === '') {
				break;
			}

			// 处理当前页对象
			foreach ($listObject as $item) {
				// 跳过目录本身
				if ($item['name'] == $prefix) continue;
				$buffer[] = $this->makeItem($item);
				$bufferCount++;
				// 缓冲满了就yield
				if ($bufferCount >= $batchSize) {
					yield $buffer;
					$buffer = [];
					$bufferCount = 0;
				}
			}

			// 如果没有下一页，结束循环；注意是null而非''
			if ($nextMarker === null) {
				break;
			}
			// 安全保护：如果nextMarker与上一次相同，避免死循环
			static $lastMarker = null;
			if ($lastMarker === $nextMarker) {
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
		$prefix	= $options['prefix'];
		$nextMarker = $options['marker'];
		$limit	= $options['limit'];
		$delimiter = '';
        $result = $this->client->getBucket($this->bucket, $prefix, $nextMarker, $limit, $delimiter, true);
		if (!$result) return false;
		return $result;
	}

	// 生成列表文件项
	private function makeItem($item) {
        $name = $item['name'];
        $time = _get($item, 'time', 0);
        $size = _get($item, 'size', 0);
        $isFolder = ($size == 0 && substr($name,strlen($name) - 1,1) == '/') ? 1:0;
        return array(
            'path'		=> $name,
            'folder'	=> $isFolder,
            'modifyTime'=> $time,
            'size'		=> $size,
        );
	}

}

