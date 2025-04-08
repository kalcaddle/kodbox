<?php 

class adminLog extends Controller{
    public $actionList = array();
	function __construct() {
        parent::__construct();
        $this->model = Model('SystemLog');
    }

    // 操作类型列表
    private function typeListAll(){
        $typeList = $this->model->typeListAll();
        if (_get($GLOBALS, 'config.settings.fileViewLog') != 1) {
            unset($typeList['file.view']);
        }
        return $typeList;
    }

    /**
     * 操作类型列表
     * this.actions()
     * @return void
     */
    public function typeList(){
        $typeList = $this->typeListAll();
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
			if(!is_array($list[$mod]['children'])){$list[$mod]['children'] = array();}
            $list[$mod]['children'][] = array('id' => $type,'text' => $name);
		}
        $fileList = array(
            array('id' => 'explorer.index.zipDownload', 'text' => LNG('admin.log.downFolder')),
            array('id' => 'explorer.index.fileOut', 'text' => LNG('admin.log.downFile')),
            array('id' => 'explorer.index.fileDownload', 'text' => LNG('admin.log.downFile')),
            array('id' => 'explorer.fav.add', 'text' => LNG('explorer.addFav')),
            array('id' => 'explorer.fav.del', 'text' => LNG('explorer.delFav')),
            array('id' => 'explorer.history.remove', 'text' => ''), // 删除历史版本
            array('id' => 'explorer.history.rollback', 'text' => ''),   // 回滚历史版本
            array('id' => 'explorer.history.clear', 'text' => ''),  // 清空历史版本——合并为历史版本操作，无需单独显示
        );
		if(!is_array($list['file']['children'])){$list['file']['children'] = array();}
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
				'explorer.index.fileOut,explorer.index.fileDownload' => LNG('admin.log.downFile'),
				'file.shareLinkAdd,file.shareLinkRemove' => LNG('log.file.shareLink'),
				'file.shareToAdd,file.shareToRemove' => LNG('log.file.shareTo'),
				'explorer.history.remove,explorer.history.rollback,explorer.history.clear' => LNG('explorer.history.action'),
			),
			'user' => array(
				'user.setting.setHeadImage,user.setting.setUserInfo' => LNG('log.user.edit'),
			),
			'admin' => array(
				'admin.group.add,admin.group.edit,admin.group.remove,admin.group.status,admin.group.sort,admin.group.switchGroup' => LNG('log.group.edit'),
				'admin.member.add,admin.member.edit,admin.member.remove,admin.member.addGroup,admin.member.removeGroup,admin.member.switchGroup,admin.member.status' => LNG('log.member.edit'),
				'admin.role.add,admin.role.edit,admin.role.remove' => LNG('log.role.edit'),
				'admin.auth.add,admin.auth.edit,admin.auth.remove' => LNG('log.auth.edit'),
				'admin.storage.add,admin.storage.edit,admin.storage.remove' => LNG('admin.menu.storageDriver'),
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
		if(!KodUser::isRoot()){
			$filter = Action("filter.UserGroup");
			if($data['userID'] && !$filter->allowChangeUser($data['userID'])){
				show_json(LNG('explorer.noPermissionAction'),false);
			}
			$groupAdmin = $filter->userGroupAdmin();
			if(!$data['userID'] && !in_array('1',$groupAdmin)){
				$groupAll = Model('Group')->groupChildrenAll($groupAdmin);
				$userAll  = Model('User')->groupUserAll($groupAll);
				if(!$userAll){
					show_json(array());
				}
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
     * @param boolean $info
     * @return void
     */
    public function add($data=false, $info=null){
        if (isset($this->in['disableLog']) && $this->in['disableLog'] == '1') return;
        $this->fileUploadLog($data, $info);
        $typeList = $this->typeListAll();
        if(!isset($typeList[ACTION])) return;
		if($GLOBALS['loginLogSaved'] ==1) return;
        $actionList = array(
            'user.index.logout',
            'user.index.loginSubmit',
        );
        // 操作日志
        if(!in_array(ACTION, $actionList)){
            // 文件类的操作，此处只收集这3个
            if(MOD == 'explorer') {
                $act  = ST . '.' . ACT;
                $func = array(
                    'fav.add', 'fav.del', 
                    'index.fileOut', 'index.fileDownload', 'index.zipDownload',
                    'history.remove', 'history.rollback', 'history.clear',
                );
                if(!in_array($act, $func)) return;
                if (in_array(ACT, array('fileOut', 'fileDownload'))) { // 多线程下载，或某些浏览器会请求多次
                    if (!$this->checkHttpRange()) return;
                } else if (ACT == 'zipDownload') {
                    // 前端压缩下载返回文件列表，列表文件分别请求下载（并记录日志），故此处不记录
                    if (_get($this->in, 'zipClient') == '1' && _get($this->in, 'zipDownloadDisable') == '1') return;
                    // if (isset($this->in['zipClient']) && $this->in['zipClient'] == '1') {
                    //     $data = false;  // 前端压缩下载会返回列表，故下方以$this->in赋值
                    // }
                }
            }
            if(!is_array($data)) $data = $this->filterIn();
        }
        // 第三方绑定
        if(ACTION == 'user.index.loginSubmit'){
            if (!is_array($data)) return;
            return $this->loginLog();
        }
        return $this->model->addLog(ACTION, $data);
    }

    /**
     * 过滤in中多余参数
     * @return void
     */
    private function filterIn(){
        $in = $this->in;
        unset($in['URLrouter'],$in['URLremote'],$in['HTTP_DEBUG_URL'],$in['CSRF_TOKEN'],
			$in['viewToken'],$in['accessToken'],$in[str_replace(".", "/", ACTION)]);
        // 替换密码参数——也可以直接删除
        if (isset($in['password']) && !isset($in['salt'])) {
            $in['password'] = str_repeat('*', strlen($in['password']));
        }
        if (isset($in['_change']['password'])) {
            $in['_change']['password'] = str_repeat('*', strlen($in['_change']['password']));
        }
        return $in;
    }

	// 文件预览下载,是否为从头开始下载(用于下载计数,或统计日志);  允许的情况:没有range; 有range且start=0且end大于5
	public function checkHttpRange(){
		if(strtoupper($_SERVER['REQUEST_METHOD']) == 'HEAD'){return false;}
		if(!isset($_SERVER['HTTP_RANGE'])){return true;}
		
		$start = 0;$end = 100;
		$find = preg_match('/bytes=\s*(\d+)-(\d*)/i',$_SERVER['HTTP_RANGE'],$matches);
		if($find && is_array($matches)){$start = intval($matches[1]);}
		if(!empty($matches[2])){$end = intval($matches[2]);}
		return ($start == 0 && $end >= 5) ? true : false;
	}

    /**
     * 登录日志
     * @param string $action
     * @param [type] $ip
     * @return void
     */
    public function loginLog(){
		if($GLOBALS['loginLogSaved'] == 1 || !Session::get('kodUser')) return;
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
		$typeList = $this->typeListAll();
		$types = array();
		foreach($typeList as $key => $value) {
			if(strpos($key, 'file.') === 0) $types[] = $key;
		}
		$add = array(
			'explorer.index.fileOut',
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
		};unset($item);
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

    /**
     * hook绑定
     * @return void
     */
    public function hookBind(){
        // 账号、存储管理：删除（及编辑等）操作只有id，没有名称，故提前获取
        if (MOD == 'admin' && in_array(ACT, array('edit','status','remove'))) {
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
                if (!empty($info['name'])) $this->in['name'] = $info['name'];
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
            return $this->autoLog($data);
        }
        // 存储管理，部分操作参数只有id
        if (MOD.'.'.ST == 'admin.storage' && in_array(ACT,array('add','edit','remove'))) {
            if (ACT == 'remove' && isset($this->in['progress'])) return;
            if (empty($this->in['name'])) {
                $info = Model('Storage')->listData($this->in['id']);
                $this->in['name'] = $info['name'];
                $this->in['driver'] = $info['driver'];  // 谨慎追加参数，避免影响主方法调用
            }
        }
        // 历史版本，追加当前操作的版本号；只记录source文件
        if (ST == 'history' && ACT != 'clear') {
            $parse = KodIO::parse($this->in['path']);
            if ($parse['type'] == KodIO::KOD_SOURCE && $parse['id']) {
                $info = Model('SourceHistory')->fileInfo($this->in['id'],true);
                if ($info) $this->in['fileInfo'] = $info;
            }
        }
        Hook::bind('show_json', array($this, 'autoLog'));
        Hook::bind('explorer.fileDownload', array($this, 'autoLog'));

        // 开启了文件预览日志
        // explorer.index.fileout
        // explorer.editor.fileget
        // explorer.share.fileout
        // explorer.share.file      // 主要用于外链（如用户头像），排除
        // explorer.history.fileout
        if (_get($GLOBALS, 'config.settings.fileViewLog') == 1) {
            Hook::bind('plugin.fileView',  array($this, 'fileViewLog'));
            Hook::bind('explorer.fileOut', array($this, 'fileViewLog'));
            Hook::bind('explorer.fileGet', array($this, 'fileViewLog'));
        }
    }
    // 通用日志
    public function autoLog($data){
        if (isset($data['code']) && !$data['code']) return false;
        if (!isset($data['data']) || !is_array($data)) {
            $data = array('data' => $data);
        }
        // $info = isset($data['info']) ? $data['info'] : null;
        $this->add($data['data'], _get($data, 'info'));
    }
    // 文件预览日志
    public function fileViewLog($path){
        if (MOD == 'plugin' && ACT != 'index') return;
        $in = $this->in;
        if (strtolower(ACT) == 'fileout') {
            // if (isset($in['download']) && $in['download'] == 1) return;
            if(isset($in['type']) && $in['type'] == 'image'){
                if (isset($in['width']) && $in['width'] == '250') return;
            }
            // 该参数由插件打开时filePathLink调用fileOut追加，排除
            if (isset($in['et'])) return;
        }
        if (isset($in['download']) && $in['download'] == 1) return; // 分享下载：fileDownload=>fileOut
        if (!$this->checkHttpRange()) return;

        // 获取文件信息，写入日志
        $parse = KodIO::parse($path);
		if ($parse['type'] != KodIO::KOD_SOURCE || !$parse['id']) {
            $parse = KodIO::parse($this->in['path']);
            if ($parse['type'] != KodIO::KOD_SOURCE || !$parse['id']) return;
        }
		$sourceID = $parse['id'];
		$sourceInfo = Model("Source")->sourceInfo($sourceID);
        if(!$sourceInfo || $sourceInfo['targetType'] == SourceModel::TYPE_SYSTEM) return;

        // 参考sourceEvent.addSystemLog
        $data = array(
			"sourceID"		=> $sourceID,
			"sourceParent"  => $sourceInfo['parentID'],
			"sourceTarget"  => $sourceID,
			'pathName'		=> $sourceInfo['name'],
			'pathDisplay'	=> !empty($sourceInfo['pathDisplay']) ? $sourceInfo['pathDisplay'] : '',
			"userID" 		=> USER_ID,
			"type" 			=> 'view',
			"desc"		    => $this->filterIn(),
		);
		$this->model->addLog('file.view', $data);	
    }

    // 文件上传日志——非系统目录
    public function fileUploadLog($data, $info=null) {
        if (ACTION != 'explorer.upload.fileUpload') return;
        if (!is_array($info) || !array_key_exists('uploadLinkInfo', $info) || empty($this->in['path'])) return;
        $parse = KodIO::parse($this->in['path']);
		if ($parse['type'] == KodIO::KOD_SOURCE) return;

        $data = $this->filterIn();
        $data['path'] = rtrim($parse['path'], '/') . '/' . $data['name'];
        unset($data['name']);
        $id = $this->model->addLog('file.upload', $data);
        if ($id) $this->model->where(array('id' => $id))->save(array('type' => 'explorer.upload.fileUpload'));
    }

}