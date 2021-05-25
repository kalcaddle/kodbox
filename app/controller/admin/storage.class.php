<?php

class adminStorage extends Controller {
    public function __construct() {
        parent::__construct();
        $this->model = Model('Storage');
	}

    public function get() {
		$result = $this->model->listData();
		show_json($result,true);
	}

	/**
	 * 存储配置信息
	 */
	public function getConfig(){
		$id = Input::get('id','int');
		$res = $this->model->getConfig($id);
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
		show_json($msg,!!$res);
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
	 * 删除
	 */
	public function remove() {
		$data = Input::getArray(array(
			'id'		=> array('check'=>'int'),
			'progress'	=> array('default'=>null),
		));
		if(!isset($data['progress'])) {
			$this->removeDone($data);
		}
		// 获取进度
		$result = $this->model->progress($data['id']);
		show_json($result);
	}
	private function removeDone($data){
		$cnt = Model('File')->where(array('ioType' => $data['id']))->count();
		// 有文件先返回结果，再执行迁移任务
		if($cnt) {
			echo json_encode(array('code'=>true,'data'=>'OK'));
			http_close();
			$res = $this->model->removeWithFile($data['id']);
		}else{
			$res = $this->model->remove($data['id']);
		}
		$msg = $res ? LNG('explorer.success') : LNG('explorer.error');
		show_json($msg,!!$res,true);
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
