<?php 

class adminLog extends Controller{
    public $actionList = array();
	function __construct() {
        parent::__construct();
        $this->model = Model('SystemLog');
    }

    /**
     * 操作类型列表
     * this.actions()
     * @return void
     */
    public function typeList(){
        $typeList = $this->model->allTypeList();
        $list = array(
            'all'   => array('id' => 'all',  'text' => LNG('common.all')),
            'file'  => array('id' => 'file', 'text' => LNG('admin.log.typeFile')),
            'user'  => array('id' => 'user', 'text' => LNG('admin.log.typeUser')),
            'admin' => array('id' => 'admin','text' => LNG('admin.manage')),
        );
        foreach($typeList as $type => $name) {
            $action = explode('.', $type);
            $mod = $action[0];
            if(!isset($list[$mod])) continue;
            $list[$mod]['children'][] = array('id' => $type,'text' => $name);
		}
        $fileList = array(
            array('id' => 'explorer.index.fileDownload', 'text' => LNG('admin.log.downFile')),
            array('id' => 'explorer.index.zipDownload', 'text' => LNG('admin.log.downFolder')),
            array('id' => 'explorer.fav.add', 'text' => LNG('explorer.addFav')),
            array('id' => 'explorer.fav.del', 'text' => LNG('explorer.delFav')),
        );
		$list['file']['children'] = array_merge($list['file']['children'], $fileList);
        $list = $this->typeListMerge($list);
        show_json($list);
	}
	
	// 合并操作日志类型;
	private function typeListMerge($list){
		$mergeList = array(
			'file' => array(
				// 'file.edit,file.rename'  => LNG('admin.log.editFile'),
				// 'file.mkdir,file.mkfile' => '新建文件(夹)',
				'file.copy,file.move,file.moveOut' => LNG('log.file.move'),
				'explorer.fav.add,explorer.fav.del' => LNG('log.file.fav'),
				'file.shareLinkAdd,file.shareLinkRemove' => LNG('log.file.shareLink'),
				'file.shareToAdd,file.shareToRemove' => LNG('log.file.shareTo'),
			),
			'user' => array(
				'user.setting.setHeadImage,user.setting.setUserInfo,user.bind.bindApi,user.bind.unbind' => LNG('log.user.edit'),
			),
			'admin' => array(
				'admin.group.add,admin.group.edit,admin.group.remove,admin.group.status,admin.group.sort' => LNG('log.group.edit'),
				'admin.member.add,admin.member.edit,admin.member.remove,admin.member.addGroup,admin.member.removeGroup,admin.member.switchGroup,admin.member.status' => LNG('log.member.edit'),
				'admin.role.add,admin.role.edit,admin.role.remove' => LNG('log.role.edit'),
				'admin.auth.add,admin.auth.edit,admin.auth.remove' => LNG('log.auth.edit'),
			),
		);
		foreach($list as $listKey => $item) {
			if(!$item['children'] || !$mergeList[$item['id']]) continue;
			$actionMake = array();
			foreach ($mergeList[$item['id']] as $actions => $text) {
				$actionArr = explode(',',$actions);				
				$actionMake[$actions] = false;//isMerged 是否合并;
				foreach ($actionArr as $action) {
					$actionMake[$action] = array('data'=>array('id'=> $actions,'text'=> $text),'actions'=>$actions);
				}
			}
			
			$children = array();
			foreach ($item['children'] as $childItem) {
				$action = $childItem['id'];
				if( isset($actionMake[$action]) ){
					$item = $actionMake[$action];
					if( !$actionMake[$item['actions']] ){
						$children[] = $item['data'];
						$actionMake[$item['actions']] = true;
					}
				}else{
					$children[] = $childItem;
				}
			}
			// pr($item,$children,$actionMake);exit;
			$list[$listKey]['children'] = $children;
		}
		$list = array_values($list);
		return $list;
	}
	

	/**
     * 后台管理-日志列表
     * @return void
     */
    public function get(){
        $data = Input::getArray(array(
            'timeFrom'  => array('check' => 'require'),
            'timeTo'    => array('check' => 'require'),
            'userID'    => array('default' => ''),
            'type'      => array('default' => ''),
            'ip'        => array('default' => null),
        ));
		
		// 部门管理员, 只能查询自己为部门管理员部门下的成员操作日志;
		if(!_get($GLOBALS,'isRoot')){
			$filter = Action("filter.UserGroup");
			if($data['userID'] && !$filter->allowChangeUser($data['userID'])){
				show_json(LNG('explorer.noPermissionAction'),false);
			}
			$groupAdmin = $filter->userAdminGroup();
			if(!$data['userID'] && !in_array('1',$groupAdmin)){
				$groupAll = Model('Group')->groupChildrenAll($groupAdmin);
				$userAll  = Model('User')->groupUserAll($groupAll);
				$data['userID'] = array('in',$userAll);
			}
		}
		
        $res = $this->model->get($data);
        if(empty($res)) show_json(array());
        show_json($res['list'], true, $res['pageInfo']);
    }

    /**
     * 记录日志
     * @param boolean $data
     * @return void
     */
    public function log($data=false,$info=null){
        $typeList = $this->model->allTypeList();
        if(!isset($typeList[ACTION])) return;
		if($GLOBALS['loginLogSaved'] ==1) return;
        $actionList = array(
            'user.index.logout',
            'user.index.loginSubmit',
            'user.bind.bindApi'
        );
        // 操作日志
        if(!in_array(ACTION, $actionList)){
            // 文件类的操作，此处只收集这3个
            if(MOD == 'explorer') {
                $act = ST . '.' . ACT;
                $func = array('fav.add', 'fav.del', 'index.fileDownload', 'index.zipDownload');
                if(!in_array($act, $func)) return;
            }
            if(!is_array($data)) {
                $data = $this->in;
                unset($data['URLremote'], $data['URLrouter'], $data['HTTP_DEBUG_URL'], $data[str_replace(".", "/", ACTION)]);
            }
        }
        // 第三方绑定
        if(ACTION == 'user.bind.bindApi' && !$data['success']) return;
        if(ACTION == 'user.index.loginSubmit'){
            return $this->loginLog();
        }		
        return $this->model->addLog(ACTION, $data);
    }

    /**
     * 登录日志
     * @param string $action
     * @param [type] $ip
     * @return void
     */
    public function loginLog(){
		if($GLOBALS['loginLogSaved'] == 1) return;
		$GLOBALS['loginLogSaved'] = 1;
        $data = array(
            'is_wap' => is_wap(),
            'ua' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''
        );
        if(isset($this->in['HTTP_X_PLATFORM'])) {
            $data['is_wap'] = true;
            $data['HTTP_X_PLATFORM'] = $this->in['HTTP_X_PLATFORM'];
        }
        return $this->model->addLog('user.index.loginSubmit', $data);
    }

    /**
     * 个人中心-用户文档日志
     * @return void
     */
    public function userLog(){
        $userID = Input::get('userID', 'int');
        // 获取文件操作类型
		$typeList = $this->model->allTypeList();
		$types = array();
		foreach($typeList as $key => $value) {
			if(strpos($key, 'file.') === 0) $types[] = $key;
		}
		$add = array(
			'explorer.index.fileDownload',
			'explorer.index.zipDownload',
			'explorer.fav.add',
			'explorer.fav.del'
		);
        $types = array_merge($types, $add);
		$data = array(
			'userID' => $userID,
			'type'	 => implode(',', $types)
		);
		$res = $this->model->get($data);
		foreach($res['list'] as $i => &$item) {
			$value = array(
				'type' 			=> $item['type'],
				'createTime'	=> $item['createTime'],
				'title'			=> $item['title'],
				'address'		=> $item['address']
			);
			$item['desc'] = is_array($item['desc']) ? $item['desc']:array();
			$item = array_merge($value, $item['desc']);
		}
		show_json($res);
    }
    /**
     * 个人中心-用户登录日志
     * @return void
     */
    public function userLogLogin(){
        $data = Input::getArray(array(
            'type'      => array('check' => 'require'),
            'userID'    => array('check' => 'require'),
        ));
        $res = $this->model->get($data);
        if(empty($res)) show_json(array());
        show_json($res['list'], true, $res['pageInfo']);
    }
}