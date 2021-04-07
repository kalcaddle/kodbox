<?php
// 数据备份
class adminBackup extends Controller{
	private $model;
	function __construct()    {
		parent::__construct();
		$this->model = Model('Backup');
	}

    public function config(){
		if(Input::get('last', null, 0)) {
			$this->lastItem();
		}
		$data	= $this->model->config();
		$last	= $this->model->lastItem();
		$process= $this->model->process();
		$info	= array('last' => $last, 'info' => $process);
		show_json($data, true, $info);
	}
	// 最近一条备份记录
	private function lastItem(){
		$last = $this->model->lastItem();
		if($last && $last['name'] != date('Ymd')) $last = null;
		show_json($last);
	}

	/**
	 * 根据所在部门获取用户列表
	 */
	public function get() {
		$id		= Input::get('id',null,null);
		$result	= $this->model->listData($id);
		$info	= $id ? $this->model->process() : array();
		show_json($result,true, $info);
	}

	/**
	 * 删除
	 */
	public function remove() {
		$id  = Input::get('id','int');
		$res = $this->model->remove($id);
		$msg = $res ? LNG('explorer.success') : LNG('explorer.error');
		show_json($msg,!!$res);
    }

    // 备份——终止http请求，后台运行
    public function start(){
		$config = $this->model->config();
		if($config['enable'] != '1') {
			show_json(LNG('admin.backup.notOpen'), false);
		}
		mk_dir(TEMP_FILES);
		if(!path_writeable(TEMP_FILES)) {
			show_json(LNG('admin.backup.pathNoWrite'), false);
		}
        echo json_encode(array('code'=>true,'data'=>'OK'));
		http_close();
		$this->model->start();
    }
    
    // 还原，禁止任何操作
    public function restore(){
        $id  = Input::get('id','int');
        echo json_encode(array('code'=>true,'data'=>'OK'));
        http_close();
        $this->model->restore($id);
	}

	public function kill(){
		$id  = Input::get('id','int');
		$res = $this->model->kill($id);
		$msg = $res ? LNG('explorer.success') : LNG('explorer.error');
		show_json($msg,!!$res);
	}
}
