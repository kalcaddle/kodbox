<?php
// 分享管理
class adminShare extends Controller{
	private $model;
	function __construct()    {
		parent::__construct();
		$this->model = Model('Share');
    }

    // 分享列表
    public function get(){
        // 举报列表
        if(isset($this->in['table']) && $this->in['table'] == 'report') {
            return $this->reportList();
        }
        $data = Input::getArray(array(
            'timeFrom'  => array('default' => null),
            'timeTo'    => array('default' => null),
            'type'      => array('default' => ''),
            'userID'    => array('default' => ''),
            'words'     => array('default' => ''),
        ));
        $res = $this->model->listAll($data);
        if(!$res) $res = array();
        show_json($res);
    }

    // 取消分享
    public function remove(){
        $id  = Input::get('id','int');
        $res = $this->model->remove($id);
        $msg = $res ? LNG('explorer.success') : LNG('explorer.error');
		show_json($msg,!!$res);
    }

    // 分享举报列表
    private function reportList(){
        $data = Input::getArray(array(
            'timeFrom'  => array('default' => null),
            'timeTo'    => array('default' => null),
            'type'      => array('default' => ''),
            'status'    => array('default' => ''),
            'words'     => array('default' => ''),
        ));
        $res = $this->model->reportList($data);
        if(!$res) $res = array();
        show_json($res);
    }

    // 举报处理
    public function status(){
        $data = Input::getArray(array(
            'id'        => array('check' => 'int'),
            'status'    => array('check' => 'int'),
        ));
        $res = $this->model->reportStatus($data);
        $msg = !!$res ? LNG('explorer.success') : LNG('explorer.error');
		show_json($msg,!!$res);
    }
}