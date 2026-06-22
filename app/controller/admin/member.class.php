<?php
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/

//用户管理【管理员配置用户，or用户空间大小变更】
class adminMember extends Controller{
	private $model;
	function __construct()    {
		parent::__construct();
		$this->model = Model('User');
		$this->authCheck();
	}

	public function authCheck(){
		if(KodUser::isRoot()) return;
		if(MOD == 'install') return;
		$data = Input::getArray(array(
			"userID"	=> array("default"=>null),
			"roleID"	=> array("default"=>2),
		));
		if(isset($data['userID']) && $data['userID'] == '1') {
			show_json(LNG('admin.member.editNoAuth'), false);
		}
		
		$roleInfo = Model('SystemRole')->listData($data['roleID']);
		if(!in_array(ACTION, array('admin.member.add', 'admin.member.edit'))) return;
		if($roleInfo['administrator'] != 1) return; // 1为系统管理员
		show_json(LNG('admin.member.editNoAuth'), false);
	}

	/**
	 * 根据所在部门获取用户列表
	 */
	public function get() {
		$data = Input::getArray(array(
			"groupID"	=> array("check"=>"require",'default'=>0),
			"fields"	=> array("check"=>"require",'default'=>''),
			"status"	=> array("default"=>null),

			// 时间范围查询，兼容接口需求
			"timeType"	=> array("check"=>"in",'default'=>'createTime','param'=>array('createTime','modifyTime','lastLogin')),
			"timeFrom"	=> array("default"=>null),
			"timeTo"	=> array("default"=>null),
		));
		if(!$data['groupID']){show_json(array(),true);}
		if($data['groupID'] == 1) $data['groupID'] = 0;	// 根部门（id=1）获取全部用户
		$result = $this->model->listByGroup($data['groupID'], $data);
		$result['list'] = $this->showUserfilterAllow($result['list']);
		show_json($result,true);
	}
	
	/**
	 * 根据用户id获取信息
	 */
	public function getByID() {
		$id = Input::get('id','[\d,]*');
		$result = $this->model->listByID(explode(',',$id));
		$result = $this->showUserfilterAllow($result);
		show_json($result,true);
	}

	/**
	 * 搜索用户
	 */
	public function search() {
		$data = Input::getArray(array(
			"words" 		=> array("check"=>"require"),
			"status"		=> array("default"=>null),
			"parentGroup"	=> array("check"=>"require",'default'=>false),// 支持多个父部门,多个逗号隔开;
		));
		
		if(!$data['parentGroup']){
			$result = $this->model->listSearch($data);
		}else{
			$groupArr = explode(',',$data['parentGroup']);$result = false;
			foreach ($groupArr as $groupID) {
				$data['parentGroup'] = intval($groupID);
				$listSearch = $this->model->listSearch($data);
				if(!$result){$result = $listSearch;continue;}
				$result['list'] = array_merge($result['list'],$listSearch['list']);
			}
			if(is_array($result) && is_array($result['list'])){
				$result = array_page_split(array_unique($result['list']),$result['pageInfo']['page'],$result['pageInfo']['pageNum']);
			}
		}
		
		$result['list'] = $this->showUserfilterAllow($result['list']);
		show_json($result,true);
	}
	
	// 过滤不允许的用户信息(根据当前用户可见部门筛选)
	private function showUserfilterAllow($list){
		if($GLOBALS['config']["GROUP_MEMBER_ALLOW_ALL"]){return $list;}
		if(!$list || KodUser::isRoot()){return $list;}
		$userGroupRootShow  = Action("filter.userGroup")->userGroupRootShow(); // 用户所在跟部门;可见范围
		$userGroupAdmin = Action("filter.userGroup")->userGroupAdmin();// 用户所在部门为管理员的部门;
		// if(!$userGroupRootShow){return $list;}

		// 获取完整用户信息, 有部门管理权限且该参数为full才返回用户完整信息,否则精简用户信息(去除邮箱,手机号等敏感信息)
		$userAllow    = array();
		$requestAdmin = isset($this->in['requestFromType']) && $this->in['requestFromType'] == 'admin'; // 后端用户列表;
		foreach($list as $user){
			$groupParentAll = array(); 
			foreach ($user['groupInfo'] as $groupInfo){
				$parents = Model('Group')->parentLevelArray($groupInfo['parentLevel']);
				$groupParentAll = array_merge($parents,$groupParentAll,array($groupInfo['groupID']));
			}
			$groupParentAll = array_unique($groupParentAll);
			$allowShow = array_intersect($groupParentAll,$userGroupRootShow)  ? true : false; //是否有交集
			$allowFull = array_intersect($groupParentAll,$userGroupAdmin) ? true : false;
			if(!$allowShow){continue;}
			if($allowFull && $requestAdmin){$userAllow[] = $user;continue;}
			
			$allowField = explode(',','userID,avatar,name,nickName,sex,groupInfo');//groupInfo
			$userAllow[] = array_field_key($user,$allowField);
		}
		// pr($userAllow,$list,$userGroupRootShow,$userGroupAdmin);exit;
		return $userAllow;
	}
	
	/**
	 * 添加用户
	 */
	public function add() {
		$this->import();
		$data = Input::getArray(array(
			"userID"	=> array("default"=>null),
			"name" 		=> array("check"=>"require"),
			"sizeMax" 	=> array("check"=>"float","default"=>1024*1024*100),
			"roleID"	=> array("check"=>"int"),
			"password" 	=> array("check"=>"require"),
			
			"email" 	=> array("check"=>"email",	"default"=>""),
			"phone" 	=> array("check"=>"phone",	"default"=>""),
			"ioDriver"	=> array("check"=>"int",	"default"=>null),
			"nickName" 	=> array("check"=>"require","default"=>""),
			"avatar" 	=> array("check"=>"require","default"=>""),
			"sex" 		=> array("check"=>"require","default"=>1),//0女1男
			"status" 	=> array("default"=>1),
		));
		$data['password'] = KodUser::parsePass($data['password']);
		if( !ActionCall('filter.userCheck.password',$data['password']) ){
			return ActionCall('filter.userCheck.passwordTips');
		}
		// 1.添加用户
		$res = $userID = $this->model->userAdd($data);
		if($res <= 0) return show_json($this->model->errorLang($res),false);
		
		// 初始化数据,不记录操作日志;
		Model('SourceEvent')->recodeStop();
		$groupInfo = json_decode($this->in['groupInfo'],true);
		if(is_array($groupInfo)){
			$this->model->userGroupSet($userID,$groupInfo,true);
		}

		// 2.添加用户默认配置
		$userInfo = $this->model->getInfo($userID);
		$this->settingDefault($userID);

		// 3.添加用户默认目录
		$sourceID = $userInfo['sourceInfo']['sourceID'];
		$this->folderDefault($sourceID);

		// 4.添加用户默认轻应用
		$desktopID = $userInfo['sourceInfo']['desktop'];
		$this->lightAppDefault($desktopID);
		Model('SourceEvent')->recodeStart();
		return show_json(LNG('explorer.success'), true, $userID);
	}

	/**
     * 用户默认设置——主题、壁纸、界面样式选择等
     */
    public function settingDefault($userID){
		$default = $this->config['settingDefault'];
        $insert = array();
        foreach($default as $key => $value){
            $insert[] = array(
				'type'		=> '',
                'userID'	=> $userID,
				'key'		=> $key,
				'value'		=> $value
            );
        }
        Model('user_option')->addAll($insert);
	}

    /**
     * 用户默认目录
     */
    public function folderDefault($parentID){
        $folderDefault = Model('SystemOption')->get('newUserFolder');
		$folderList = explode(',', $folderDefault);
        foreach($folderList as $name){
            $path = "{source:{$parentID}}/" . $name;
            IO::mkdir($path);
        }
	}
	
	/**
     * 添加用户轻应用
     */
    public function lightAppDefault($desktop){
        $list = Model('SystemLightApp')->listData();
        $appList = array_to_keyvalue($list, 'name');
		$appListID = array_to_keyvalue($list, 'id');

        $defaultApp = Model('SystemOption')->get('newUserApp');
		$defAppList = explode(',', $defaultApp);
        foreach($defAppList as $name){
			$app = _get($appListID,$name,_get($appList,$name));
			if(!$app) continue;
			
			// [user]/desktop/appName.oexe
            $path = "{source:{$desktop}}/" . $app['name'] . '.oexe';
            IO::mkfile($path, json_encode_force($app['content']));
        }
    }

	/**
	 * 编辑 
	 */
	public function edit() {
		$data = Input::getArray(array(
			"userID" 	=> array("check"=>"int"),	// userID=1可以编辑			
			"name" 		=> array("check"=>"require","default"=>null),
			"sizeMax" 	=> array("check"=>"float",	"default"=>null),
			"roleID"	=> array("check"=>"int",	"default"=>null),
			"password" 	=> array("check"=>"require","default"=>''),
			
			"email" 	=> array("check"=>"email",	"default"=>null),
			"phone" 	=> array("check"=>"phone",	"default"=>null),
			"ioDriver"	=> array("check"=>"int",	"default"=>''),
			"nickName" 	=> array("check"=>"require","default"=>null),
			"avatar" 	=> array("check"=>"require","default"=>null),
			"sex" 		=> array("check"=>"require","default"=>null),//0女1男
			
			"status" 	=> array("check"=>"require","default"=>null),//0-未启用 1-启用
		));
		// 后台删除这些字段值时（为空），default=null时data不包含导致没有更新
		foreach (array('email','phone','nickName') as $key) {
			if (!isset($data[$key]) && isset($this->in[$key])) {
				$data[$key] = '';
			}
		}
		if( $data['password'] ) {
			$data['password'] = KodUser::parsePass($data['password']);
			if (!ActionCall('filter.userCheck.password',$data['password']) ){
				return ActionCall('filter.userCheck.passwordTips');
			}
		}
		// 不支持修改自己的权限角色;避免误操作;
		if($data['userID'] == KodUser::id() && isset($data['roleID'])){
			$user = Session::get('kodUser');
			if($user['roleID'] != $data['roleID']){
				return show_json(LNG('admin.member.errEditSelfRole'),false);
			}
		}
		// 禁止修改超管角色
		$userInfo = $this->model->getInfo($data['userID']);
		if ($data['userID'] == '1' && $data['userID'] != KodUser::id()) {
			if(isset($data['roleID']) && $data['roleID'] != $userInfo['roleID']){
				return show_json(LNG('admin.member.errEditSelfRole'),false);
			}
		}

		$dataSave = array();$groupSave = false; // 仅处理变化的内容;
		foreach($data as $key => $value) {
			if($key == 'ioDriver'){
				if (_get($userInfo,'metaInfo.ioDriver','') != $value) {
					$dataSave[$key] = $value;
				}
			}
			if($key == 'userID') continue;
			if($value == $userInfo[$key]) continue;
			$dataSave[$key] = $value;
		}
		
		if($dataSave){$res = $this->model->userEdit($data['userID'],$dataSave);}
		$groupInfo = json_decode($this->in['groupInfo'],true);
		if(isset($this->in['groupInfo'])){ 
			// 编辑用户,必须有至少一个默认部门; 即便是没有权限;
			$groupInfo = is_array($groupInfo) ? $groupInfo : array();
			$userGroup = array_to_keyvalue($userInfo['groupInfo'],'groupID','auth.id',true);

			// 添加到指定部门时;保持原来所在部门权限不变;
			$isGroupAppend = isset($this->in['groupInfoAppend']) && $this->in['groupInfoAppend'] == '1';
			// 仅添加时,用户所在部门不在设置范围内则自动加入;
			foreach ($userGroup as $groupID => $auth){
				if($isGroupAppend && !isset($groupInfo[$groupID])){$groupInfo[$groupID] = $auth;}
			}
			if($userGroup != $groupInfo){
				$groupSave = true;$res = 1;
				$this->model->userGroupSet($data['userID'],$groupInfo,true);
			}
		}
		$this->in['_change'] = $dataSave;
		if($groupSave){$this->in['_change']['groupInfo'] = $_REQUEST['groupInfo'];}
		if(!$dataSave && !$groupSave){$res = 1;}
		
		$msg = $res > 0 ? LNG('explorer.success') : $this->model->errorLang($res);
		return show_json($msg,($res>0),$data['userID']);
	}
	
	/**
	 * 添加到部门
	 */
	public function addGroup() {
		$data = Input::getArray(array(
			"userID" 	=> array("check"=>"int"),
			"groupInfo"	=> array("check"=>"json"),
		));
		$res = $this->model->userGroupAdd($data['userID'],$data['groupInfo']);
		$msg = $res ? LNG('explorer.success') : LNG('explorer.error');
		show_json($msg,!!$res);
	}
	/**
	 * 从部门删除
	 */
	public function removeGroup() {
		$data = Input::getArray(array(
			"userID" 	=> array("check"=>"int"),
			"groupID"	=> array("check"=>"int"),
		));
		$res = $this->model->userGroupRemove($data['userID'],$data['groupID']);
		$msg = $res ? LNG('explorer.success') : LNG('explorer.error');
		show_json($msg,!!$res);
	}
	/**
	 * 部门迁移 
	 */
	public function switchGroup(){
		$data = Input::getArray(array(
			"userID" 	=> array("check"=>"int"),
			"from"		=> array("check"=>"int"),
			"to"		=> array("check"=>"int"),
		));
		$userInfo = $this->model->getInfo($data['userID']);
		if(!$userInfo) show_json(LNG('ERROR_USER_NOT_EXISTS'), false);
		$authID = 0;
		// 删除来源部门，新增到新部门
		$groupInfo = !empty($userInfo['groupInfo']) ? $userInfo['groupInfo'] : array();
		foreach($groupInfo as $item) {
			if($item['groupID'] != $data['from']) continue;
			if(isset($item['auth']['id'])) {
				$authID = $item['auth']['id'];
			}
			$this->model->userGroupRemove($data['userID'],$item['groupID']);
			break;
		}
		// 权限无效（无来源部门）时，取权限列表中第一个显示项
		if (!$authID) {
			$list = Model('Auth')->listData();
			foreach ($list as $item) {
				if ($item['display'] != '0') {
					$authID = $item['id'];
					break;
				}
			}
		}
		$groupInfo = array($data['to'] => $authID);
		$res = $this->model->userGroupAdd($data['userID'],$groupInfo);
		$msg = $res ? LNG('explorer.success') : LNG('explorer.error');
		show_json($msg,!!$res);
	}
	/**
	 * 更新用户状态
	 */
	public function status(){
		$data = Input::getArray(array(
			"userID" 	=> array("check"=>"int"),
			"status"	=> array("check"=>"in", "param" => array(0, 1)),
		));
		$res = $this->model->userStatus($data['userID'], $data['status']);
		$msg = $res ? LNG('explorer.success') : LNG('explorer.error');
		show_json($msg,!!$res);
	}
	
	/**
	 * 删除
	 */
	public function remove() {
		$id = Input::get('userID','bigger',null,1);
		$res = $this->model->userRemove($id);
		$msg = $res ? LNG('explorer.success') : LNG('explorer.error');
		show_json($msg,!!$res);
	}

	/**
	 * 获取用户meta信息
	 * @return void
	 */
	public function metaInfo() {
		$data = Input::getArray(array(
			'userID'	=> array('check' => 'bigger'),
			'metaKey'	=> array('default' => '')
		));
		if (empty($data['metaKey'])) {
			show_json(array());
		}
		$list = $this->model->getMetaInfo($data['userID']);
		$metaKey = explode(',', $data['metaKey']);
		$data = array();
		foreach ($metaKey as $key) {
			$data[$key] = _get($list, $key, '');
		}
		show_json($data);
	}

	// ------------------------------------------------------------ 用户导入 start ------------------------------------------------------------

	/**
	 * 批量导入用户
	 * @return void
	 */
	private function import(){
		if(!isset($this->in['isImport'])) return;
		set_timeout();

		// 导入提交
		$cckey = Input::get('taskID', 'require');
		$this->importAction($cckey);

		// 导入提交（首次）-检查并获取数据
		// 1.检查参数
		$data = Input::getArray(array(
			'sizeMax'		=> array('check'=>'float'),
			"roleID"		=> array("check"=>"int"),
			'filePath'		=> array('check'=>'require', 'aliasKey'=>'path'),
			'importType'	=> array('default'=>'0'),
			'metaMore'		=> array('default'=>'0'),
			'bindField'		=> array('default'=>''),
		));
		// 检查所在部门，导入设置时要求必须含要有部门/权限字段
		$groupType = $this->in['groupType'] == 'more' ? 'more' : 'base';
		if ($groupType != 'more') {
			$data['groupInfo'] = Input::get('groupInfo','require');
		}
		// 导入方式
		if ($data['importType'] == '1') {
			$data['typeValue'] = Input::get('typeValue','require');
			if ($data['typeValue'] == 'change') {
				$data['groupChange'] = Input::get('groupChange','require');
			}
		}
		// 更多字段
		$metaKeys = array();
		if ($data['metaMore'] == '1') {
			$metaFields = json_decode($this->in['metaField'], true);
			if (!is_array($metaFields)) $metaFields = array();
			foreach ($metaFields as $item) {
				if ($item['status'] != '1') continue;
				if (!$item['metaKey']) continue;
				$metaKeys[] = $item['metaKey'];
			}
			$metaKeys = array_unique($metaKeys);	// 过滤重名
		}
		// 身份匹配
		if (!empty($data['bindField'])) {
			$bindField = explode(',',$data['bindField']);
			$data['bindField'] = array_intersect($bindField, $metaKeys);
		}
		$data['metaKeys'] = array_diff($metaKeys,array('phone','email'));	// 排除系统字段

		// 2.检查文件，并下载到本地
		$fileInfo = IO::info($data['path']);
		if (!$fileInfo) {show_json(LNG('common.pathNotExists'), false);}
		if (intval($fileInfo['size']) > 1024 * 1024 * 50) {
			show_json(str_replace('[1]', '50MB', LNG('admin.member.importSizeLimit')), false);
		}
		mk_dir(TEMP_FILES);
		$filename = 'import_' . USER_ID . '_' . time().'.csv';
		$path = IO::copy($data['path'], TEMP_FILES, REPEAT_RENAME,$filename);
		if (!$path || !file_exists($path)) {
			show_json(LNG('admin.member.importDownErr'), false);
		}

		// 3.获取文件内容（账号列表）
		$dataList = $this->getCsvData($path);
		del_file($path);	// 读取完成，删除临时文件
		if (empty($dataList)) {
			show_json(LNG('admin.member.importEmpty'), false);
		}
		$userList = $this->getUserData($data, $dataList);
		unset($dataList);

		// 4.账号列表存缓存并返回提示
		$total = count($userList);
		$error = count(array_filter($userList, function($item) {
			return !empty($item['error']);
		}));
		// 有正确项才写入缓存
		$data = LNG('admin.member.importAllErr');
		if ($total != $error) {
			Cache::set($cckey, $userList);
			$data = LNG('admin.member.importPartErr');
			if (!$error) {
				$data = LNG('admin.member.importStart');
				$userList = 'total:'.$total.'; ok:'.$total;
			}
		}
		show_json($data, true, $userList);
	}
	private function getUserData($data, $dataList) {
		$list = array();
		$userKeys = array('name','nickName','password','sex','phone','email','disable');
		// 所在部门为导入设置——要求必须存在（不取默认值），否则报错
		if (!isset($data['groupInfo'])) {
			$userKeys[] = 'group';
			$userKeys[] = 'groupAuth';
		}
		$userKeys = array_merge($userKeys, $data['metaKeys']);
		foreach ($dataList as $lineNum => $line) {
			// 跳过空行（行号仍与CSV文件对应）
			$isEmpty = true;
			foreach ($line as $val) {
				if (trim($val) !== '') {$isEmpty = false; break;}
			}
			if ($isEmpty) continue;

			$info = array();
			$info['num'] = $lineNum+1;	// 行号
			foreach ($userKeys as $idx => $key) {
				$info[$key] = trim($line[$idx]);
			}
			// 检查用户信息是否有效
			$error = $this->checkUserInfo($info);
			// meta字段拆分
			$info['meta'] = array();
			foreach ($data['metaKeys'] as $key) {
				if (!empty($info[$key])) {
					$info['meta'][$key] = $info[$key];
				}
				unset($info[$key]);
			}
			unset($info['group'],$info['groupAuth']);
			// groupInfo
			if (!isset($info['groupInfo'])) {
				$info['groupInfo'] = _get($data, 'groupInfo', '');
			}
			// 要求所在部门不能为空，避免导入时复杂处理（读取默认数据覆盖）
			if (empty($error)) {
				$groupInfo = json_decode($info['groupInfo'], true);
				if (empty($groupInfo)) $error = LNG('admin.member.importGroupErr');
			}
			$info['error'] = trim($error);
			$list[] = $info;
		}
		return $list;
	}
	private function checkUserInfo(&$info) {
		// 临时存放手机号、邮箱，用于检测重复
		static $tmpTypeList = null;
		if (is_null($tmpTypeList)) {
			$tmpTypeList = array('phone'=>array(), 'email'=>array());
		}
		// 账号
		if (empty($info['name'])) return LNG('admin.member.importNameErr');
		// 密码——身份匹配成功的账号更新时不覆盖密码，但要求密码必须填写
		if (empty($info['password'])) return LNG('admin.member.importPwdErr');
		// 性别
		$info['sex'] = $info['sex'] != '0' ? 1 : 0;
		// 手机/邮箱
		foreach (array('phone','email') as $type) {
			if (empty($info[$type])) continue;
			$value = $info[$type];
			// $info[$type] = $value = preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $value]);	// TODO 删除不可见的特殊字符，如null
			if (isset($tmpTypeList[$type][$value])) {
				return implode(' ', array($type,LNG('user.repeat').':',$value));
			}
			if (!Input::check($value, $type)) {
				$info[$type] = '';
			} else {
				$tmpTypeList[$type][$value] = 1;
			}
		}
		// 状态
		$info['status'] = $info['disable'] == '1' ? 0 : 1;
		// 所在部门和权限
		if (isset($info['group'])) {
			if (empty($info['group'])) return LNG('admin.member.importGroupErr');
			if (empty($info['groupAuth'])) return LNG('admin.member.importGroupAuthErr');
			// 根据部门路径+权限获取所在部门信息（groupInfo）
			$groupArr = array_filter(explode(';', $info['group']));
			$groupAuthArr = array_filter(explode(';', $info['groupAuth']));
			if (count($groupArr) != count($groupAuthArr)) return LNG('admin.member.importGroupAuthCntErr');
			$groupInfo = array();
			foreach ($groupArr as $i => $groupLevel) {
				$groupID = $this->getGroupByPath($groupLevel);
				if (!is_numeric($groupID)) return $groupID;
				$authID = $this->getAuthByName($groupAuthArr[$i]);
				if (!is_numeric($authID)) return $authID;
				$groupInfo[$groupID] = $authID;
			}
			$info['groupInfo'] = json_encode($groupInfo);
		}
		return '';
	}

	// 根据部门路径获取对应id
	private function getGroupByPath($groupLevel,$return=false) {
		static $levelList = null;
		if (is_null($levelList)) {
			// 查出所有列表，拼接为完整部门路径列表=>id
			$model = Model('Group');
			$data = $levels = array();
			$list = $model->field('groupID,name,parentLevel')->select();
			foreach ($list as $item) {
				$data[$item['groupID']] = $item['name'];
				$tmpLevel = $model->parentLevelArray($item['parentLevel']);
				$tmpLevel[] = $item['groupID'];
				$levels[] = $tmpLevel;
			}
			$levelList = array();
			foreach ($levels as $item) {
				$level = array();
				foreach ($item as $groupID) {
					$level[] = $data[$groupID];
				}
				// $levelList[implode('/',$level)] = implode('/',$item);	// 同一层级下可能存在同名，属于脏数据，忽略
				$levelList[implode('/',$level)] = end($item);
			}
			unset($list,$data,$levels);
		}
		if ($return) return $levelList;
		$tmpLevel = implode('/',array_map('trim', explode('/',$groupLevel)));	// 重新过滤拼接
		return _get($levelList, $tmpLevel, LNG('admin.member.importGroupPathErr').': '.$groupLevel);
	}
	// 根据权限名称获取id
	private function getAuthByName($auth,$return=false) {
		static $authList = null;
		if (is_null($authList)) {
			$authList = Model('Auth')->listData();
			$authList = array_to_keyvalue($authList, 'name', 'id');
		}
		if ($return) return $authList;
		return _get($authList, trim($auth), LNG('admin.member.importAuthErr').': '.$auth);
	}

	// 导入提交
	private function importAction($cckey) {
		$actKey = _get($this->in, 'actKey', false);
		if (!$actKey) return;

		// 1. 获取模板文件数据
		if ($actKey == 'getTpl') {
			$this->importTplData();
		}
		// 2. 获取部门level列表
		if ($actKey == 'getGroupLevel') {
			$groupList = $this->getGroupByPath('',true);
			$groupList = array_flip($groupList);
			show_json($groupList);
		}

		// 3. 清除缓存
		if ($actKey == 'clear') {
			Cache::remove($cckey);
			show_json(LNG('explorer.success'));
		}
		// 4. 获取进度
		$taskKey = 'task_'.$cckey;
		if ($actKey == 'getProgress') {
			$cache = Cache::get($taskKey);
			if ($cache) {
				Cache::remove($taskKey);
				show_json($cache,true,1);
			}
			$data = Task::get($taskKey);
			show_json($data);
		}
		if ($actKey != 'done') return;

		// 5. 导入提交
		$userList = Cache::get($cckey);
		Cache::remove($taskKey);	// 清除任务
		Cache::remove($cckey);		// 清除导入数据
		if(empty($userList)) {
			show_json(LNG('admin.member.uploadDataInvalid'), false);
		}
		// 开始导入
		$data = Input::getArray(array(
			"sizeMax" 	=> array("check"=>"float","default"=>1024*1024*100),	// 默认值
			"roleID"	=> array("check"=>"int"),	// 默认值
		));
		$total	 = count($userList);
		$task	 = new Task($taskKey,'importUser',$total,LNG('admin.member.userImport'));

		$inOld = $this->in;
		$userIds = array();
		$list = array();
		foreach($userList as $user) {
			$task->update(1);
			$tmpInfo = array(
				'num'	=> $user['num'],
				'name'	=> $user['name'],
				'error'	=> ''
			);
			if (!empty($user['error'])) {
				$tmpInfo['error'] = $user['error'];
				$list[] = $tmpInfo;
				continue;
			}
			// 逐条捕获异常，防止单条失败中断整个导入
			try {
				// 重置输入状态，避免上一条异常残留影响当前迭代
				$this->in = $inOld;
				$user = array_merge($data, $user);
				// 新增 or 编辑
				$findInfo = $this->findUserByField($user, $inOld);
				if ($findInfo) {
					// 不更新账户名
					$keys = array('nickName','email','phone','sex','groupInfo','status');
					$this->in = array_intersect_key($user, array_flip($keys));
					$this->in['userID'] = $findInfo['userID'];
					if ($this->in['userID'] == '1') {
						$this->in['status'] = 1;
					}
					$this->in = array_filter($this->in, function($v){
						if (!is_null($v) && $v !== '') return true;
					});
					// TODO 检查用户信息是否有变化，没有变化不更新：$this->hasUserChange();
					$res = ActionCallHook('admin.member.edit');
				} else {
					$keys = array('name','nickName','password','roleID','email','phone','sex','groupInfo','sizeMax','status');
					$this->in = array_intersect_key($user, array_flip($keys));
					$res = ActionCallHook('admin.member.add');
				}
				
				// 收集userID
				$userID = 0;
				if ($this->in['userID']) {
					$userID = $this->in['userID'];
				} else {
					if ($res['code'] && $res['info']) {
						$userID = $res['info'];
					}
				}
				if ($userID) {$userIds[] = $userID;}

				// 执行失败
				if (!$res['code']) {
					$tmpInfo['error'] = $res['data'];
					$list[] = $tmpInfo;
					continue;
				}
				// meta单独处理，也可以先判断是否需要更新
				if (!empty($user['meta'])) {
					foreach ($user['meta'] as $key => $value) {
						$this->model->metaSet($userID, $key, $value);
					}
				}
			} catch (\Exception $e) {
				$tmpInfo['error'] = LNG('admin.member.importSysErr') . ': ' . $e->getMessage();
			}
			$list[] = $tmpInfo;
		}
		$this->in = $inOld;
		$task->task['info'] = $list;
		Cache::set($taskKey, $task->task);
		$task->end();

		// 全量导入，失效账号处理
		$this->importAfter($userIds);

		// 统计执行成功失败数量
		$error = count(array_filter($list, function($item) {
			return !empty($item['error']);
		}));
		$code = false;
		$data = LNG('admin.member.importFail');
		if ($total != $error) {
			$code = true;
			$data = LNG('admin.member.importSuccess');
			// // 没有失败项，不返回数组——保持数组，用于前端显示
			// if (!$error) {$list = $total;}
		}
		show_json($data, $code, $list);
	}

	// 全量导入后续处理
	private function importAfter($userIds) {
		$typeValue = $this->in['typeValue'];
		if ($this->in['importType'] != '1' || !in_array($typeValue, array('disable','change'))) return;

		// 获取需要处理的用户id；排除超管和当前用户，防止误禁用/迁移
		$ignoreIds = array('1', strval(USER_ID));
		$ignoreIds = array_unique($ignoreIds);
		$excludeIds = $userIds;
		if (!empty($excludeIds)) {
			$excludeIds = array_merge($excludeIds, $ignoreIds);
		} else {
			$excludeIds = $ignoreIds;
		}
		// 获取列表
		$where = array();
		$where['userID'] = array('not in', $excludeIds);
		if ($typeValue == 'disable') {
			$where['status'] = 1;
		}
		$list = $this->model->where($where)->field('userID')->select();
		$list = array_to_keyvalue($list, '', 'userID');
		if (empty($list)) return;

		// 1.禁用
		$where = array('userID' => array('in', $list));
		if ($typeValue == 'disable') {
			return $this->model->where($where)->save(array('status' => 0));
		}
		// 2.迁移部门
		$groupChange = _get($this->in, 'groupChange');	// 目标部门
		$groupChange = json_decode($groupChange, true);
		// 2.1 删除指定用户所在部门
		Model('user_group')->where($where)->delete();
		// 2.2 批量写入部门
		$data = array();
		foreach ($list as $userID) {
			foreach ($groupChange as $groupID => $authID) {
				$data[] = array(
					'userID'	=> $userID,
					'groupID'	=> $groupID,
					'authID'	=> $authID,
					'sort'		=> 0,
				);
			}
		}
		if (!empty($data)) Model('user_group')->addAll($data, array(), true);
	}

	// 根据指定字段检查用户是否存在，返回
	private function findUserByField($user, $in){
		// 仅全量导入时允许身份匹配查询
		if ($this->in['importType'] != '1') return false;

		// if (empty($in['bindField'])) return false;	// 没有选择绑定字段，当做不存在新增
		$bindFields = explode(',', $in['bindField']);
		$bindFields[] = 'name';	// 账号名兜底

		$model = Model('user_meta');
		foreach ($bindFields as $key) {
			$key = trim($key);
			if (!$key) continue;
			// meta字段
			if (!in_array($key, array('phone','email','name'))) {
				$value = _get($user, 'meta.'.$key, false);
				if ($value) {
					// $findInfo = $this->model->getInfoByMeta($key, $value);
					$info = $model->where(array('key'=>$key,'value'=>$value))->find();
					if ($info) return $info;
				}
			} else { // 系统字段
				$value = _get($user, $key, false);
				if ($value) {
					$info = $this->model->where(array($key => $value))->find();
					// $findInfo = $this->model->getInfo($info['userID']);
					if ($info) return $info;
				}
			}
		}
		return false;
	}

	// 获取导入模板数据
	private function importTplData(){
		// 获取字段列表
		$data = array(
			'name'		=> LNG('admin.member.importName'),
			'nickName'	=> LNG('admin.member.importNickName'),
			'password'	=> LNG('admin.member.importPwd'),
			'sex' 		=> LNG('admin.member.importSex'),	// 1-男,0-女
			'phone'		=> LNG('admin.member.importPhone'),
			'email'		=> LNG('admin.member.importEmail'),
			'disable'	=> LNG('admin.member.importDisable'),
		);
		if ($this->in['groupType'] == 'more') {
			$data['group'] = LNG('admin.member.group');
			$data['groupAuth'] = LNG('admin.authFrom.groupAt');
		}
		$metaKeys = array();
		$metaIgnore = array('phone','email');
		if ($this->in['metaMore'] == '1') {
			$metaFields = json_decode($this->in['metaField'], true);
			if (!is_array($metaFields)) $metaFields = array();
			foreach ($metaFields as $item) {
				if ($item['status'] != '1') continue;
				if (!$item['metaKey'] || in_array($item['metaKey'], $metaIgnore)) continue;
				$data[$item['metaKey']] = $item['title'];
				$metaKeys[] = $item['metaKey'];
			}
		}

		// 获取数据
		$groupAuthList = $metaList = array();
		if ($this->in['actValue'] == 'data') {
			// 获取用户列表
			$list = $this->model->field('userID,name,nickName,sex,phone,email,status')->select();
			// 获取部门/权限列表
			$userIds = array_to_keyvalue($list, '', 'userID');
			$where	 = array('userID'=>array('in',$userIds));
			$tmpList = Model('user_group')->where($where)->field('userID,groupID,authID')->select();
			$groupList = $authList = array();
			if (!empty($tmpList)) {
				$groupList = $this->getGroupByPath('',true);
				$groupList = array_flip($groupList);
				$authList = $this->getAuthByName('',true);
				$authList = array_flip($authList);
			}
			foreach ($tmpList as $item) {
				$userID = $item['userID'];
				if (!isset($groupAuthList[$userID])) {
					$groupAuthList[$userID] = array();
				}
				$groupLevel = _get($groupList, $item['groupID'], '');
				$authName = _get($authList, $item['authID'], '');
				if ($groupLevel && $authName) {
					$groupAuthList[$userID][] = array($groupLevel, $authName);
				}
			}
			unset($groupList,$authList);
	
			// 获取meta列表
			if (!empty($metaKeys)) {
				$where['key'] = array('in', $metaKeys);
				$metaList = Model('user_meta')->where($where)->field('userID,key,value')->select();
				$tmpArr = array();
				foreach ($metaList as $item) {
					$userID = $item['userID'];
					if (!isset($tmpArr[$userID])) $tmpArr[$userID] = array();
					$tmpArr[$userID][$item['key']] = $item['value'];
				}
				$metaList = $tmpArr;
				unset($tmpArr);
			}
		} else {
			// 模板示例
			$list = array(array(
				'userID'	=> '0',
				'name'		=> 'lilei',
				'nickName'	=> 'Lei',
				'password'	=> 'Li@123456',
				'sex'		=> '1',
				'phone'		=> '13500000000',
				'email'		=> 'lilei@mail.com',
				'status'	=> '1',
			));
			$groupAuthList = array('0' => array(
				array(LNG('admin.member.importGroup1'), LNG('admin.auth.editor')),
				array(LNG('admin.member.importGroup2'), LNG('admin.auth.viewer')),
			));
			$metaList = array('0' => array());
			foreach ($metaKeys as $key) {
				$metaList['0'][$data[$key]] = '';
			}
		}

		// 生成数据
		$userList = array(array_values($data));
		foreach ($list as $item) {
			$userID = $item['userID'];
			$userInfo = array();
			foreach ($data as $key => $value) {
				if (empty($userInfo[$key])) { // groupAuth提前赋值，避免被覆盖
					$userInfo[$key] = _get($item, $key, '');
				}
				if ($key == 'password') {
					if (empty($userInfo['password'])) {
						$userInfo['password'] = '******';	// 占位
					}
				} else if ($key == 'disable') {
					$userInfo['disable'] = $item['status'] == '1' ? 0 : 1;
				} else if ($key == 'group') {
					$userInfo['group'] = $userInfo['groupAuth'] = array();
					$groupAuth = _get($groupAuthList, $userID, array());
					foreach ($groupAuth as $val) {
						$userInfo['group'][] = $val[0];
						$userInfo['groupAuth'][] = $val[1];
					}
					$userInfo['group'] = implode(';', $userInfo['group']);
					$userInfo['groupAuth'] = implode(';', $userInfo['groupAuth']);
				} else if (in_array($key, $metaKeys)) {
					$userInfo[$key] = _get($metaList, $userID.'.'.$key, '');
				}
			}
			$userList[] = array_values($userInfo);
		}
		foreach ($userList as &$user) {
			// CSV注入防护：对以公式触发字符开头的单元格加单引号前缀——实际没有必要
			foreach ($user as &$cell) {
				$cell = filter_csv_cell($cell);
			};unset($cell);
			$user = "\"".implode("\",\"", $user)."\"";
		};unset($user);

		show_json($userList);
	}

	// 获取csv文件内容，去掉标题行
	private function getCsvData($file){
		// 1.检查并读取文件内容
		if (!is_csv_file($file)) {
			del_file($file);
			show_json('无效的CSV文件', false);
		}
		// 2.获取内容列表
		$dataList = array();
		$charset = get_file_charset($file);
    	$needConvert = !in_array($charset, array('utf-8','ascii'));
		$handle = fopen($file, 'r');
		if (!$handle) return array();
		// 读取第一行用于获取分隔符
		$line = fgets($handle);
		$sep = get_csv_sep($line);
		while (($data = fgetcsv($handle,0,$sep)) !== false) {
			if ($needConvert) {
				foreach ($data as $i => $val) {
					$data[$i] = iconv_to($val, $charset, 'utf-8');
				}
			}
            $dataList[] = $data;
		}
        fclose($handle);
        // unset($dataList[0]);	// 去掉标题行 —— fgets已跳过首行，无需重复处理
		return $dataList;
	}

	// ------------------------------------------------------------ 用户导入 end ------------------------------------------------------------

}
