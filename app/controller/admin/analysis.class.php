<?php

class adminAnalysis extends Controller{
    public $model;
	function __construct() {
        parent::__construct();
        $this->model = Model('Analysis');
    }

    // 卡片信息
    public function option(){
        $list   = array('user', 'file', 'access', 'server');
        $type   = Input::get('type','in',null,$list);
        $data = $this->applyCache();
        if (!$data) {
            $data = $this->model->option($type);
            $this->applyCache($data);
        }
        show_json($data);
    }

    // 文件使用列表：用户空间、部门空间
    public function table(){
		$type = Input::get('type','in',null,array('user', 'group'));
        $data = $this->applyCache();
        if (!$data) {
            $data = $this->model->listTable($type);
            $this->applyCache($data);
        }
        show_json($data);
    }

    // 文件使用占比
    public function chart(){
        $param = Input::getArray(array(
            'userID'    => array("check"=>"int","default"=>null),
            'groupID'   => array("check"=>"int","default"=>null),
        ));
        $data = $this->applyCache();
        if (!$data) {
            $data = $this->model->fileChart($param);
            $this->applyCache($data);
        }
        show_json($data);
    }

    /**
     * 趋势：userTrend、storeTrend
     * userTrend: 每日增长（regist,写计划任务）、每日登录（log）
     * storeTrend: 使用空间、时间使用——计划任务
     * @return void
     */
    public function trend(){
        $param = Input::getArray(array(
            'type' => array('check' => 'require', 'default' => 'user'), // user/store
            'time' => array('check' => 'require', 'default' => 'day'),  // day/week/month/year
        ));
        $data = $this->applyCache();
        if (!$data) {
            $data = $this->model->trend($param['type'], $param['time']);
            $this->applyCache($data, 3600*2);
        }
        show_json($data);
    }

    // 概览数据存缓存
    private function applyCache($data=false, $timeout=600){
        $in = $this->in; // 以参数为key，避免列表分页失效
        unset($in['URLrouter'],$in['URLremote'],$in['HTTP_DEBUG_URL'],$in['CSRF_TOKEN'],$in['viewToken'],$in['accessToken']);
        $cckey = 'analysis-data-'.md5(json_encode($in));
        if ($data === false) return Cache::get($cckey);
        Cache::set($cckey, $data, $timeout);
    }
}