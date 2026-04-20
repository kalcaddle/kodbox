<?php 
/**
 * 除基础类中ioList之外的存储类
 */
class impDrvOTS {
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
	 * 获取指定目录下所有列表，返回生成器
	 * @param [type] $path
	 * @return void
	 */
	public function listAll($path, &$result = array()) {
		// TODO
	}

	/**
     * 按批次获取文件列表（生成器）——对其他各存储类不再分别实现，直接获取全部列表分块返回（会占用更多内存）
     * @param [type] $path
     * @param integer $batchSize
     * @return void
     */
	public function listPath($path, $batchSize=100000) {
		$path = rtrim($path, '/') . '/';
		// $list = IO::listAll($path);
		$list = $this->getProtMember('listAll', $path);

        $count = count($list);
		for ($i = 0; $i < $count; $i += $batchSize) {
			$chunk = array_slice($list, $i, $batchSize);
			yield $chunk;
		}
	}

}