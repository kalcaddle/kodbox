<?php

class adminStorage extends Controller {
    public function __construct() {
        parent::__construct();
        $this->model = Model('Storage');
	}

	/**
	 * 获取存储列表
	 * @return void
	 */
    public function get() {
		$result = $this->model->listData();
		$this->parseData($result);
		show_json($result,true);
	}
	private function parseData(&$result){
		$ids = array_to_keyvalue($result, '', 'id');

		// 获取各存储中占用空间、文件数(io_file)、存储状态
		$key = md5('io.list.get.'.implode(',',$ids));
		$res = Cache::get($key);
		if ($ids && $res === false && $this->in['usage'] == '1') {
			$where = array('ioType'=>array('in',implode(',',$ids)));
			$res = Model('File')->field(array('ioType'=>'id','count(ioType)'=>'cnt','sum(size)'=>'size'))->where($where)->group('ioType')->select();
			$res = array_to_keyvalue($res, 'id');	// 可能没有文件，返回空数组
			Cache::set($key, $res, 600);	// 10分钟
		}
		$fileUse = $res !== false ? true : false;
		foreach ($result as &$item) {
			$item['sizeUse'] = intval(_get($res, $item['id'].'.size', 0));
			$item['fileNum'] = intval(_get($res, $item['id'].'.cnt', 0));
			$item['fileUse'] = $fileUse;	// 是否含文件使用信息
			$item['status']	 = 1;
			if (strtolower($item['driver']) != 'local') continue;
			$config = $this->model->getConfig($item['id']);
			$path = $config['basePath'];
			if (!mk_dir($path) || !path_writeable($path)) {
				$item['status'] = 0;
			}
		}
	}

	/**
	 * 存储配置信息
	 */
	public function getConfig(){
		$id = Input::get('id','int');
		$res = $this->model->getConfig($id);
		// 隐藏密码
		$arr = array('secret','userpass','password');	// os、ftp/uss、dav
		foreach ($arr as $key) {
			if (isset($res[$key])) {
				$res[$key] = str_repeat('*', strlen($res[$key]));
				break;
			}
		}
		show_json($res,true);
	}

	/**
	 * 添加
	 */
	public function add() {
		$data = Input::getArray(array(
			"name" 		=> array("check"=>"require"),
			"sizeMax" 	=> array("check"=>"require","default"=>0),
			"driver" 	=> array("check"=>"require"),
			"default" 	=> array("check"=>"require","default"=>0),
			"system" 	=> array("check"=>"bool","default"=>0),
			"config" 	=> array("check"=>"require"),
		));
		$res = $this->model->add($data);
		$msg = $res ? LNG('explorer.success') : LNG('explorer.repeatError');
		show_json($msg,!!$res, $res);
	}

	/**
	 * 编辑 
	 */
	public function edit() {
		$data = Input::getArray(array(
			"id"		=> array("check"=>"int"),
			"name" 		=> array("check"=>"require","default"=>null),
			"sizeMax" 	=> array("check"=>"require","default"=>null),
			"driver" 	=> array("check"=>"require","default"=>null),
			"default" 	=> array("check"=>"require","default"=>0),
			"editForce"	=> array("default"=>0),
			"config" 	=> array("check"=>"require","default"=>null),
		));
		$res = $this->model->update($data['id'],$data);
		$msg = $res ? LNG('explorer.success') : LNG('explorer.repeatError');
		show_json($msg,!!$res);
	}

	/**
	 * 删除、迁移
	 */
	public function remove() {
		$id = Input::get('id','int');
		$action = Input::get('action','in',null,array('remove','move'));

		// 1.获取删除（迁移）进度
		$taskId = $action.'.storage.'.$id;	// remove/move.storage.id
		if (isset($this->in['progress'])) {
			$data = Cache::get($taskId);
			if ($data) {
				Cache::remove($taskId);
				show_json($data, true, 1);
			}
			$data = Task::get($taskId);
			show_json($data);
		}
		Cache::remove($taskId);

		// 2.删除存储
		$done = isset($this->in['done']) ? true : false;
		// 备份数据没有数据库记录，需单独处理
		if (!$done && $action == 'remove') {
			if (Model('Backup')->findByKey('io', $id)) {
				show_json(LNG('admin.storage.ifRmBakNow'), false, 100110);
			}
		}
		// 存储中有file记录，先迁移文件再删除存储，否则直接删除存储
		$cnt = Model('File')->where(array('ioType' => $id))->count();
		if($cnt) {
			$info = $this->model->listData($id);
			$chks = $this->model->checkConfig($info,true);
			// 存储无法链接，确认后直接删除
			if ($chks !== true) {
				if ($action == 'move') {
					show_json(LNG('admin.storage.moveErr'), false);
				}
				if (!$done) {
					show_json(LNG('admin.storage.ifRmErrNow'), false, 100110);
				}
			}
			$res = $this->model->removeWithFile($id, $action, $info, $done);
		}else{
			$res = $action == 'remove' ? $this->model->remove($id) : true;
		}
		$code = !!$res;
		$msg = $code ? LNG('explorer.success') : LNG('explorer.error');
		show_json($msg,$code,($code ? 1 : ''));
	}
	
	// 系统回收站,自动清空;
	public function systemRecycleClear(){
		$options 	= Model('systemOption')->get();
		$clearDay 	= intval($options['systemRecycleClear']);
		$this->taskInit();
		if($options['systemRecycleOpen'] != '1') return;
		if($clearDay <= 0) return;

		$pathRecycle = KodIO::sourceID(IO_PATH_SYSTEM_RECYCLE);
		$whereEmpty  = array("parentID"	=> $pathRecycle,'size'=>0);
		$this->removeSource($whereEmpty); //清除内容为空的文件夹;

		$pathList 	 = Model('Source')->field('sourceID')->where(array("parentID"=> $pathRecycle))->select();
		$pathList	 = array_to_keyvalue($pathList,'','sourceID');
		if(!$pathList) return;
		
		$timeEnd = time() - ($clearDay * 24 * 3600);
		$whereChild = array('parentID'=>array('in',$pathList),'modifyTime' => array('<=',$timeEnd));
		$this->removeSource($whereChild);
		$this->removeSource($whereEmpty);
	}
	private function removeSource($where){
		$model = Model('Source');
		$pathList = $model->field('sourceID')->where($where)->select();
		if(!$pathList) return;
		foreach ($pathList as $item) {
			$model->removeNow($item['sourceID'],false);
		}
	}
	// 计划任务自动添加和移除;
	private function taskInit(){
		$options = Model('systemOption')->get();
		$action  = 'admin.storage.systemRecycleClear';
		$taskInitKey = 'systemRecycleTaskInit';
		
		if($options['systemRecycleOpen'] != '1'){
			if($options[$taskInitKey] == 'ok'){
				$task = Model('SystemTask')->findByKey('event',$action);
				Model('SystemTask')->remove($task['id'],true);
				Model('systemOption')->set($taskInitKey,'');
			}
			return;
		}
		
		// 已开启;
		if($options[$taskInitKey] == 'ok') return;
		$data = array (
			'name'	=> LNG('explorer.recycle.taskTitle'),
			'type'	=> 'method',
			'event' => $action,
			'time'	=> '{"type":"day","day":"02:00"}',
			'desc'	=> LNG('explorer.recycle.taskDesc'),
			'enable' => '1',
			'system' => '1',
		);
		Model('SystemTask')->add($data); 
		Model('systemOption')->set($taskInitKey,'ok');
	}
}
