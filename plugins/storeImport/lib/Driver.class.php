<?php 

class impDriver {
    protected $ioList = array(
        'sg' => array('local','oss','qiniu','uss'), // ftp暂不考虑支持
        's3' => array('s3','bos','cos','eds','eos','jos','minio','obs','oos','moss','nos')
    );
	public function __construct($store) {
        if ($this->driver) return;

        $type     = strtolower($store['driver']);
        $typeList = $GLOBALS['config']['settings']['ioClassList'];
        $sfxType  = (isset($typeList[$type]) ? $typeList[$type] : ucfirst($type));
        // 基础类是否存在
        $class    = 'PathDriver'.$sfxType;
        if( !class_exists($class) ){
            throw new Exception('不支持的存储类型：'.$class);
        }
        // 独立子类、S3系子类
        if (in_array($type, $this->ioList['sg'])) {
            include_once(__DIR__.'/Driver'.$sfxType.'.class.php'); // DriverOSS.class.php
            $class = 'impDrv'.$sfxType;
        } else if (in_array($type, $this->ioList['s3'])) {
            include_once(__DIR__.'/DriverS3.class.php');
            $class = 'impDrvS3';
        } 
        if( !class_exists($class) ){
            throw new Exception('不支持的存储类型：'.$class);
        }
        // 自定义子类是否存在
        $config = $store['config'];
        $this->driver = new $class($config, $sfxType);
	}

    /**
	 * 获取内部地址；外部地址转内部地址
	 */
    public function getPathInner($path) {
        return $this->driver->getPathInner($path);
	}
	/**
	 * 内部地址转外部地址
	 */
	public function getPathOuter($path) {
        return $this->driver->getPathOuter($path);
	}

    /**
     * 获取文件列表
     * @param [type] $path
     * @return void yield [[path,size],...]
     */
    public function listAll($path){
        $path = $this->getPathInner($path);    // {io:xx}/abc/ => /xx/abc/
        return $this->driver->listAll($path);
    }
}



