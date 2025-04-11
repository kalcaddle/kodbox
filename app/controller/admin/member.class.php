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
			
			$allowField = explode(',','userID,avatar,name,nickName,sex');//groupInfo
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
		if($data['userID'] == USER_ID && isset($data['roleID'])){
			$user = Session::get('kodUser');
			if($user['roleID'] != $data['roleID']){
				return show_json(LNG('admin.member.errEditSelfRole'),false);
			}
		}
		// 禁止修改超管角色
		$userInfo = $this->model->getInfo($data['userID']);
		if ($data['userID'] == '1' && $data['userID'] != USER_ID) {
			if(isset($data['roleID']) && $data['roleID'] != $userInfo['roleID']){
				return show_json(LNG('admin.member.errEditSelfRole'),false);
			}
		}

		$dataSave = array();$groupSave = false; // 仅处理变化的内容;
		foreach($data as $key => $value) {
			if($key == 'ioDriver'){$dataSave[$key] = $value;}
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
	 * 批量导入用户
	 * @return void
	 */
	private function import(){
		if(!isset($this->in['isImport'])) return;
		// 1.上传
		if(empty($this->in['filePath'])) {
			// 1.1 上传文件——返回前端：>100kb上传分多次请求，无法直接获取结果
			if (empty($this->in['path'])) {
				$path = IO::mkdir(TEMP_FILES . 'import_' . time());
				$this->in['path'] = $path;
				Action('explorer.upload')->fileUpload();
			}
			// 1.2 获取上传文件内容
			$file = $this->in['path'];
			$data = $this->getImport($file);
			del_file($file);
			if(empty($data['list'])) show_json(LNG('admin.member.uploadInvalid'), false);

			$filename = get_path_this($file);
			Cache::set(md5('memberImport'.$filename), $data);
			show_json('success', true, $filename);
		}
		$filename = Input::get('filePath','require');
		// 获取新增用户进度
		$taskId = md5('import-user-'.$filename);
		if (isset($this->in['process'])) {
			$cache = Cache::get($taskId);
			if ($cache) {
				Cache::remove($taskId);
				show_json($cache,true,1);
			}
			$data = Task::get($taskId);
			show_json($data);
		}
		Cache::remove($taskId);
		// 2.读取数据并新增
		$fileData = Cache::get(md5('memberImport'.$filename));
		Cache::remove(md5('memberImport'.$filename));
		if(!$fileData || empty($fileData['list'])) show_json(LNG('admin.member.uploadDataInvalid'), false);

		$data = Input::getArray(array(
			'sizeMax'	=> array('check' => 'require'),
			'roleID'	=> array('check' => 'require'),
			'groupInfo' => array('check' => 'require'),
		));
		$total	 = (int) $fileData['total'];
		$task	 = new Task($taskId,'importUser',$total,LNG('admin.member.userImport'));
		$error	 = array();
		foreach($fileData['list'] as $value) {
			$task->update(1);
			if(!is_array($value)) continue;
			$this->in = array_merge($value, $data);
			$res = ActionCallHook('admin.member.add');
			if (!$res['code']) $error[$this->in['name']] = $res['data'];
		}
		$task->task['error'] = $error;
		Cache::set($taskId, $task->task);
		$task->end();
		$success = $total - count($error);
		$info = array(
			'total'		=> $total,
			'success'	=> $success,
			'error'		=> $error,
		);
		$code  = (boolean) $success;
		$data  = $code ? LNG('admin.member.importSuccess') : LNG('admin.member.importFail');
		show_json($data, $code, $info);
	}

	// 获取csv分隔符——编辑后的csv文件不一定是默认的','
	private function getCsvSep($line) {
		if (empty($line)) return ',';
		$data = array();
		$separators = array(',', ';', ':', "\t", '|');
		foreach ($separators as $separator) {  
			$fields = explode($separator, $line);
			$fields = array_filter($fields, 'trim');
			$data[$separator] = count($fields);
		}  
		// 找到字段数量最多的分隔符
		$maxCnt = max($data);  
		return array_search($maxCnt, $data);  
	}

	/**
	 * 获取导入文件数据
	 * @param [type] $file
	 * @return void
	 */
	private function getImport($file){
		if (!$handle = fopen($file, 'r')) {
			del_file($file);
			show_json('read file error.', false);
		}
		$line = fgets($handle);	// 获取分隔符
		$separator = $this->getCsvSep(trim($line));
		$dataList = array();
		while (($data = fgetcsv($handle,0,$separator)) !== false) {
            $dataList[] = $data;
		}
        fclose($handle);
        // 2.获取列表数据
        unset($dataList[0]);
		$dataList = array_filter($dataList);
        $list = array();
        $keys = array('name'=>'','nickName'=>'','password'=>'','sex'=>1,'phone'=>'','email'=>'');
        foreach($dataList as $value) {
            $tmp = array();
			$i = 0;
            foreach($keys as $key => $val) {
				$val = trim($value[$i]);
				$i++;
				if($key == 'name' && empty($val)) break;
				if($key == 'password' && empty($val)) break;
				if (is_null($val)) $val = '';
                switch($key) {
					case 'name':
					case 'nickName':
						$val = $this->iconvValue($val);
						break;
					case 'sex':
						$val = $val != '0' ? 1 : 0;
                        break;
                    case 'phone':
                    case 'email':
						$val = preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $val);	// 删除不可见的特殊字符，如null
						if(!Input::check($val, $key)) $val = '';
                        break;
                    default: break;
                }
                $tmp[$key] = $val;
			}
			if(empty($tmp) || empty($tmp['name']) || empty($tmp['password'])) continue;
			if(isset($list[$tmp['name']])) continue;
            $list[$tmp['name']] = array_merge($keys,$tmp);
        }
        return array(
			'list'	=> array_values($list), 
			'total' => count($list), 
		);
	}
	// 部分文件转换无效
	private function iconvValue($value){
		// $encoding = array('GB2312', 'GBK', 'GB18030', 'UTF-8', 'ASCII', 'BIG5');
		// $charset = mb_detect_encoding($value,$encoding);
		// $value = iconv_to($value,$charset,'utf-8');
		$charset = get_charset($value);
		if(!in_array($charset,array('utf-8','ascii'))){
			$value = iconv_to($value, $charset, 'utf-8');
		}
		return $value;
	}
}
