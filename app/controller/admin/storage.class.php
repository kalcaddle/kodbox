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
			// 没有文件直接删除
			$res = $this->model->remove($data['id']);
		}
		$msg = $res ? LNG('explorer.success') : LNG('explorer.error');
		show_json($msg,!!$res,true);
	}
	
	
	
	// File 中不存在的文件进行清理；清空不存在的文件（被手动删除的）
	public function fileListClear(){
		$list = Model('File')->select();
		$notExist = 0;
		http_close();
		$task = new Task("fileCheck",'',count($list));
		foreach ($list as $item) {
			if(!IO::exist($item['path'])){
				$notExist ++;
				$task->task['currentTitle'] = $notExist .'个不存在';
				$this->removeByFileID($item['fileID']);
			}
			$task->update(1);
		}
	}
	// source文件,落地file表中不存在的进行自动删除;
	public function sourceListClear(){
		$modelSource = Model("Source");
		$modelFile   = Model("File");
		$list = $modelSource->select();
		$notExist = 0;
		http_close();
		$task = new Task("SourceCheck",'',count($list));
		foreach ($list as $item) {
			if($item['isFolder'] == '0' && !$modelFile->find($item['fileID'])){
				$notExist ++;
				$task->task['currentTitle'] = $notExist .'个不存在';
				$modelSource->remove($item['sourceID'],false);
			}
			$task->update(1);
		}
	}
	public function fileClearBySource(){
		$source = Model("Source")->field('sourceID,fileID')->select();
		$source = array_to_keyvalue($source,'','sourceID');
		$list	= Model('File')->select();
		
		$modelFile   = Model("File");
		$notExist = 0;
		http_close();
		$task = new Task("fileClearCheck",'',count($list));
		foreach ($list as $item) {
			if(!$source[$item['fileID']]){
				$notExist ++;
				$task->task['currentTitle'] = $item['fileID'].';'.$notExist .'个不存在';
				// usleep(5000);
				$modelFile->remove($item['fileID']);
			}
			$task->update(1);
		}
	}
	private function removeByFileID($fileID){
		$source = Model("Source")->field('sourceID,fileID')->where(array('fileID'=>$fileID))->select();
		$source = array_to_keyvalue($source,'','sourceID');
		foreach ($source as $srouceID) {
			Model('source')->remove($srouceID,false);
		}
	}
}
