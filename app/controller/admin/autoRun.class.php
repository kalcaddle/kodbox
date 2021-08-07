<?php 
/**
 * 自动执行
 */
class adminAutoRun extends Controller {
	function __construct()    {
		parent::__construct();
	}

	public function index(){
        $this->logBind();
    }
    public function logBind(){
        // 退出时在请求出记录，其他在出执行结果后记录
        if(ACTION == 'user.index.logout'){
            if($user = Session::get('kodUser')) {
                $data = array(
                    'code' => true,
                    'data' => array(
                        'userID'    => $user['userID'], 
                        'name'      => $user['name'],
                        'nickName'  => $user['nickName'],
                    )
                );
                $this->log($data);
            }
            return;
        }
        Hook::bind('show_json','admin.AutoRun.log');
        Hook::bind('explorer.fileDownload','admin.AutoRun.log');
    }

    public function log($data){
        if(isset($data['code']) && !$data['code']) return false;
        if(!isset($data['data']) || !is_array($data)){
            $data = array('data' => $data);
        }
        $info = isset($data['info']) ? $data['info'] : null;
        ActionCall('admin.log.log',$data['data'], $info);
    }
}