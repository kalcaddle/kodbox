<?php 

class impDriver {
    public $ioList = array(
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
            throw new Exception(LNG('storeImport.main.ioNotSup').$class);
        }
        // 独立子类、S3系子类
        if (in_array($type, $this->ioList['sg'])) {
            include_once(__DIR__.'/Driver'.$sfxType.'.class.php'); // DriverOSS.class.php
            $class = 'impDrv'.$sfxType;
        } else if (in_array($type, $this->ioList['s3'])) {
            include_once(__DIR__.'/DriverS3.class.php');
            $class = 'impDrvS3';
        } else {
            // // 不追加此else项则调用了父类存储：PathDriverXXX
            // include_once(__DIR__.'/DriverOTS.class.php');
            // $class = 'impDrvOTS';
            $class = $sfxType;
        }
        if( !class_exists($class) ){
            throw new Exception(LNG('storeImport.main.ioNotSup').$class);
        }
        // 自定义子类是否存在
        $config = $store['config'];
        $this->driver = new $class($config, $sfxType, $this);
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

    /**
     * 按批次获取文件列表（生成器），注意：driver方法未实现时会调用父类方法
     * @param [type] $path
     * @param integer $batchSize
     * @return void
     */
    public function listPath($path, $batchSize=100000) {
        $path = $this->getPathInner($path);
        return $this->driver->listPath($path, $batchSize);
    }
}



