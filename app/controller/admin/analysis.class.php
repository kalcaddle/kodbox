<?php

class adminAnalysis extends Controller{
	function __construct() {
        parent::__construct();
        $this->model = Model('Analysis');
    }

    public function option(){
        $list   = array('user', 'file', 'access', 'server');
        $type   = Input::get('type','in',null,$list);
        $result = $this->model->option($type);
        show_json($result);
    }

    public function chart(){
        $data = Input::getArray(array(
            'userID'    => array("check"=>"int","default"=>null),
            'groupID'   => array("check"=>"int","default"=>null),
        ));
        $result = $this->model->fileChart($data);
        show_json($result);
    }

    // 计划任务写入记录：regist、store
    public function record(){
		$type   = Input::get('type','in',null,array('regist', 'store'));
		$result = $this->model->record($type);

		$msg = !!$result ? LNG('explorer.success') : LNG('explorer.error');
		show_json($msg,!!$result);
    }

    // 列表：用户空间、部门空间
    public function table(){
		$type = Input::get('type','in',null,array('user', 'group'));
        $result = $this->model->listTable($type);
        show_json($result);
    }

    /**
     * 趋势：userTrend、storeTrend
     * userTrend: 每日增长（regist,写计划任务）、每日登录（log）
     * storeTrend: 使用空间、时间使用——计划任务
     * @return void
     */
    public function trend(){
        $data = Input::getArray(array(
            'type' => array('check' => 'require', 'default' => 'user'), // user/store
            'time' => array('check' => 'require', 'default' => 'day'),  // day/week/month/year
        ));
        $result = $this->model->trend($data['type'], $data['time']);
        show_json($result);
    }
}