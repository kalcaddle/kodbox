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
		if(_get($GLOBALS,'isRoot')) return;
		if(MOD == 'install') return;
		$data = Input::getArray(array(
			"userID"	=> array("default"=>null),
			"roleID"	=> array("default"=>2),
		));
		if(isset($data['userID']) && $data['userID'] == '1') {
			show_json(LNG('admin.member.editNoAuth'), false);
		}
		if(!in_array(ACTION, array('admin.member.add', 'admin.member.edit'))) return;
		if($data['roleID'] != '1') return;
		show_json(LNG('admin.member.editNoAuth'), false);
	}

	/**
	 * 根据所在部门获取用户列表
	 */
	public function get() {
		$data = Input::getArray(array(
			"groupID"	=> array("check"=>"require",'default'=>0),
			"fields"	=> array("check"=>"require",'default'=>''),
			"status"	=> array("default"=>null)
		));
		if(!$data['groupID']){show_json(array(),true);}
		if($data['groupID'] == 1) $data['groupID'] = 0;	// 根部门（id=1）获取全部用户
		$result = $this->model->listByGroup($data['groupID'], $data);
		show_json($result,true);
	}
	
	/**
	 * 根据用户id获取信息
	 */
	public function getByID() {
		$id = Input::get('id','[\d,]*');
		$result = $this->model->listByID(explode(',',$id));
		show_json($result,true);
	}

	/**
	 * 搜索用户
	 */
	public function search() {
		$data = Input::getArray(array(
			"words" 		=> array("check"=>"require"),
			"parentGroup"	=> array("check"=>"int",'default'=>false),
			"status"		=> array("default"=>null)
		));
		$result = $this->model->listSearch($data);
		show_json($result,true);
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
			"nickName" 	=> array("check"=>"require","default"=>""),
			"avatar" 	=> array("check"=>"require","default"=>""),
			"sex" 		=> array("check"=>"require","default"=>""),//0女1男
			"status" 	=> array("default"=>1),
		));
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
			
			"email" 	=> array("check"=>"email",	"default"=>''),
			"phone" 	=> array("check"=>"phone",	"default"=>''),
			"nickName" 	=> array("check"=>"require","default"=>null),
			"avatar" 	=> array("check"=>"require","default"=>''),
			"sex" 		=> array("check"=>"require","default"=>null),//0女1男
			
			"status" 	=> array("check"=>"require","default"=>null),//0-未启用 1-启用
		));
		if( $data['password'] && 
			!ActionCall('filter.userCheck.password',$data['password']) ){
			return ActionCall('filter.userCheck.passwordTips');
		}
		// 不支持修改自己的权限角色;避免误操作;
		if($data['userID'] == USER_ID){
			$user = Session::get('kodUser');
			if($user['roleID'] != $data['roleID']){
				return show_json("not support change self role!",false);
			}
		}

		$res = $this->model->userEdit($data['userID'],$data);
		$groupInfo = json_decode($this->in['groupInfo'],true);
		if($res > 0 && isset($this->in['groupInfo'])){ 
			// 编辑用户,必须有至少一个默认部门; 即便是没有权限;
			$groupInfo = is_array($groupInfo) ? $groupInfo : array();
			$this->model->userGroupSet($data['userID'],$groupInfo,true);
		}
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
		$authID = 2;
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
		if(!isset($this->in['filePath'])) {
			$this->in['path'] = TEMP_FILES . 'import_' . time();
			$res = ActionCallHook('explorer.upload.fileUpload');
			if(!$res['code']) show_json($res['data'], false);

			$file = $res['info'];
			$data = $this->getImport($file);
			del_file($file);
			if(empty($data['list'])) show_json(LNG('admin.member.uploadInvalid'), false);

			$filename = get_path_this($file);
			Cache::set(md5('memberImport'.$filename), $data);
			show_json('success', true, $filename);
		}
		// 2.读取数据并新增
		$filename = Input::get('filePath','require');
		$fileData = Cache::get(md5('memberImport'.$filename));
		Cache::remove(md5('memberImport'.$filename));
		if(!$fileData || empty($fileData['list'])) show_json(LNG('admin.member.uploadDataInvalid'), false);

		$data = Input::getArray(array(
			'sizeMax'	=> array('check' => 'require'),
			'roleID'	=> array('check' => 'require'),
			'groupInfo' => array('check' => 'require'),
		));
		$total	 = (int) $fileData['total'];
		$success = 0;
		foreach($fileData['list'] as $value) {
			$this->in = array_merge($value, $data);
			$res = ActionCallHook('admin.member.add');
			if($res['code']) $success++;
		}
		$fail  = $total - $success;
		$info  = LNG('common.inAll') . ":{$total}; ";
		$info .= LNG('common.success') . ":{$success}";
		if ($fail > 0) {
			$info .= "<br/>" . LNG('common.fail') . ":{$fail} (".LNG('admin.member.importFailDesc').")";
		}
		$code  = (boolean) $success;
		$data  = $code ? LNG('admin.member.importSuccess') : LNG('admin.member.importFail');
		show_json($data, $code, $info);
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
        $dataList = array();
		while (($data = fgetcsv($handle)) !== false) {
            $dataList[] = $data;
		}
        fclose($handle);
        // 2.获取列表数据
        unset($dataList[0]);
		$dataList = array_filter($dataList);
        $list = array();
        $keys = array('name','nickName','password','sex','phone','email');
		$sex  = array('女' => 0, '男' => 1);
        foreach($dataList as $value) {
            $tmp = array();
            foreach($keys as $i => $key) {
				if($key == 'name' && empty($value[$i])) break;
				if($key == 'password' && empty($value[$i])) break;
				$val = $value[$i];
                switch($key) {
					case 'name':
					case 'nickName':
						$val = $this->iconvValue($val);
						break;
					case 'sex':
						$val = $this->iconvValue($val);
						$val = isset($sex[$val]) ? $sex[$val] : 1;
                        break;
                    case 'phone':
                    case 'email':
                        // 检测手机号、邮箱
						if(!Input::check($val, $key)) $val = '';
                        break;
                    default: break;
                }
                $tmp[$key] = $val;
			}
			if(empty($tmp)) continue;
			if(isset($list[$tmp['name']])) continue;
            $list[$tmp['name']] = $tmp;
        }
        return array(
			'list'	=> array_values($list), 
			'total' => count($dataList), 
		);
	}
	private function iconvValue($value){
		$charset = get_charset($value);
		if(!in_array($charset,array('utf-8','ascii'))){
			$value = iconv_to($value, $charset, 'utf-8');
		}
		return $value;
	}
}
