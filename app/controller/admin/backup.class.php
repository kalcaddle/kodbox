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
		$database = array_change_key_case($GLOBALS['config']['database']);
		$data['dbType'] = Action('admin.server')->_dbType($database);	// mysql/sqlite
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
	 * 获取备份列表
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
	
	// 激活授权,自动开启备份;(没有开启时,设置仅备份数据库;备份到默认存储)
	public function initStart($status){
		$data = $this->model->config();
		if($data['enable'] == '1') return;
		$backup = Model('SystemOption')->get('backup');
		$backup = json_decode($backup, true);

		$driver = KodIO::defaultDriver();
		$backup['io'] = $driver['id'];
		$backup['content'] = 1;
		$backup['enable'] = 1;
		Model('SystemOption')->set('backup', $backup);
		$update = array('enable' => 1);
		Model('SystemTask')->update($data['id'], $update);
	}

    // 备份——终止http请求，后台运行
    public function start(){
		$config = $this->model->config();
		if($config['enable'] != '1') {
			show_json(LNG('admin.backup.notOpen'), false);
		}
		$data = Model('Storage')->listData($config['io']);
		if (!$data) show_json(LNG('admin.backup.storeNotExist'), false);
		Model('Storage')->checkConfig($data);
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
