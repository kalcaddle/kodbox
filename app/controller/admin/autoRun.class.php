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
        // 删除操作只有id，没有名称，故提前获取
        if (MOD == 'admin' && ACT == 'remove') {
            if (!isset($this->in['name'])) {
                switch (ST) {
                    case 'group':
                        $info = Model('Group')->getInfoSimple($this->in['groupID']);
                        break;
                    case 'member':
                        $info = Model('User')->getInfoSimple($this->in['userID']);
                        break;
                    case 'auth':
                    case 'role':
                        $table = ST == 'auth' ? 'Auth' : 'SystemRole';
                        $info = Model($table)->listData($this->in['id']);
                    break;
                }
                $name = !empty($info['nickName']) ? $info['nickName'] : $info['name'];
                $this->in['name'] = $name;
            }
        }
        // 退出时在请求出记录，其他在出执行结果后记录
        if (ACTION == 'user.index.logout') {
            $user = Session::get('kodUser');
            if (!$user) return;
            $data = array(
                'code' => true,
                'data' => array(
                    'userID'    => $user['userID'], 
                    'name'      => $user['name'],
                    'nickName'  => $user['nickName'],
                )
            );
            return $this->log($data);
        }
        Hook::bind('show_json','admin.AutoRun.log');
        Hook::bind('explorer.fileDownload','admin.AutoRun.log');
    }

    public function log($data){
        if (isset($data['code']) && !$data['code']) return false;
        if (!isset($data['data']) || !is_array($data)) {
            $data = array('data' => $data);
        }
        // $info = isset($data['info']) ? $data['info'] : null;
        ActionCall('admin.log.log',$data['data']);
    }
}