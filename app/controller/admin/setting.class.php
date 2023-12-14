<?php

/*
 * @link http://kodcloud.com/
 * @author warlee | e-mail:kodcloud@qq.com
 * @copyright warlee 2014.(Shanghai)Co.,Ltd
 * @license http://kodcloud.com/tools/license/license.txt
 */

class adminSetting extends Controller {
	function __construct() {
		parent::__construct();
	}

	public function get(){
		$data = Model('SystemOption')->get();
		$data = array_merge($this->config['settingSystemDefault'],$data);
		$removeKey = array(
			'versionLicense','versionUser','versionHashUser','versionHash',
			'systemSecret','systemPassword','deviceUUID',
		);
		foreach ($removeKey as $key) {
			unset($data[$key]);
		}
		unset($data['regist']['loginWith']);	// 兼容旧版已存在的数据
		
		// 根部门名称;
		$groupRoot = Model('Group')->where(array("parentID"=>0))->find();
		if($groupRoot){$data['groupRootName'] = $groupRoot['name'];}
		show_json($data);
	}
	//管理员  系统设置全局数据
	public function set() {
		$data = json_decode($this->in['data'], true);
		if (!$data) {
			show_json(LNG('explorer.error'), false);
		}
		
		if (isset($data['chunkSize'])) {
			$postMax = get_post_max();
			if($data['chunkSize']*1024*1024  >= $postMax){
				$sizeTips = ($postMax/(1024*1024)) .'MB';
				show_json(LNG('admin.setting.transferChunkSizeDescError1').
				":$sizeTips,<br/>".LNG('admin.setting.transferChunkSizeDescError2'),false);
			}
		}
		Model('SystemOption')->set($data);
		show_json(LNG('explorer.success'));
	}
	
	/**
	 * 发送邮件测试-用户注册功能设置
	 */
	public function mailTest() {
		$input = Input::get('address', 'require');

		$systemName = Input::get('systemName');
		if (!$systemName) $systemName = Model('SystemOption')->get('systemName');
		$systemDesc = Input::get('systemDesc');
		if (!$systemDesc) $systemDesc = Model('SystemOption')->get('systemDesc');

		$user = Session::get('kodUser');
		$name = _get($user, 'nickName', _get($user, 'name'));
		$data = array(
			'type'			=> 'email',
			'input'			=> $input,
			'emailType' 	=> 1,
			'action'		=> 'email_test',
			'config'		=> array(
				'address'	=> $input,
				'subject'	=> "[{$systemName}]" . LNG('user.emailVerify') . '-' . LNG('common.test'),
				'content'	=> array(
					'type'	=> 'code', 
					'data'	=> array(
						'user' => $name,
						'code' => rand_string(6)
					)
				),
				'system'	=> array(	// 系统信息
					'icon'	=> STATIC_PATH.'images/icon/fav.png',
					'name'	=> $systemName,
					'desc'	=> $systemDesc
				),
				'server'		=> Input::getArray(array(
					'host'		=> array('check' => 'require'),
					'email'		=> array('check' => 'require'),
					'password'	=> array('check' => 'require'),
					'secure'	=> array('default' => 'null'),
				))
			)
		);
		$res = Action('user.msg')->send($data);
		if (!$res['code']) {
			show_json(LNG('user.sendFail') . ': ' . $res['data'], false);
		}
		show_json(LNG('user.sendSuccess'), true);
	}
	
	/**
	 * 动态添加菜单;
	 */
	public function addMenu($options,$menu=array()){
		$menus = &$options['system']['options']['menu'];
		$menusKeys = array_to_keyvalue($menus,'name');
		if( isset($menusKeys[$menu['name']]) ) return $options;

		$menus[] = $menu;$menuNum = 0;
		foreach ($menus as &$theMenu) {
			if(!isset($theMenu['subMenu']) || $theMenu['subMenu'] == '0'){
				$menuNum += 1;
			}
			// 一级目录最多5个;超出自动添加到子目录; 前端自适应处理
			// if($menuNum >= 5){$theMenu['subMenu'] = 1;}
		};unset($theMenu);
		return $options;
	}

	public function clearCache(){
		if($this->clearCacheType()){return;}
		Cache::clearTimeout();
		Cache::deleteAll();
		http_close();
		@del_dir(TEMP_PATH);
		@del_dir('/tmp/fileThumb');
		mk_dir(TEMP_PATH . 'log');
		Model("File")->clearEmpty();
		$this->removeFolder('zipView');
		$this->removeEmptyFolder();
		Action('explorer.attachment')->clearCache();
		AutoTask::restart();//停止计划任务; (再次访问自动开启)
	}

	private function clearCacheType(){
		$clearType = $this->in['type'];
		switch($clearType){
			case 'image':$this->removeFolder('thumb');break;
			case 'video':$this->removeFolder('plugin/fileThumb');break;
			case 'plugin':
				$this->removeFolder('zipView');
				$this->removeFolder('plugin',true);
				break;
			default:$clearType = false;break;
		}
		if(!$clearType) return;
		$this->removeEmptyFolder();
		return true;
	}
	
	// 清理插件目录下的空文件夹;
	private function removeEmptyFolder(){
		$info  = IO::infoFullSimple(IO_PATH_SYSTEM_TEMP.'plugin');
		if (!$info || !$info['sourceID'] || !$info['parentLevel']) return;
		$where = array('parentLevel'=>array('like',$info['parentLevel'].'%'),'size'=>0);
		$lists = Model("Source")->field('sourceID,name')->where($where)->limit(5000)->select();
		$lists = $lists ? $lists : array();
		foreach ($lists as $item){
			Model("Source")->removeNow($item['sourceID'],false);
		}
	}
	
	// 清理文件夹;
	private function removeFolder($folder,$children=false){
		$model = Model("Source");
		$pathInfo = IO::infoFullSimple(IO_PATH_SYSTEM_TEMP . $folder);
		if(!$folder || !$pathInfo || !$pathInfo['sourceID']) return;
		if(!$children){$model->removeNow($pathInfo['sourceID'],false);return;}
		
		$where = array('parentID'=>$pathInfo['sourceID']);
		$lists = $model->field("sourceID,name")->where($where)->select();
		$lists = $lists ? $lists : array();
		foreach ($lists as $item){
			$model->removeNow($item['sourceID'],false);
		}
	}

	/**
	 * 服务器管理：基础信息、缓存、db
	 * @return void
	 */
	public function server(){
		$data = Input::getArray(array(
			'tab'	 => array('default'=>'', 'aliasKey'=>'type'),
			'action' => array('check'=>'in', 'param'=>array('get', 'pinfo', 'save', 'task', 'clear'))
		));
		$function = ($data['type'] ? $data['type'] : 'srv') . ucfirst($data['action']);
		// srvGet/cacheSave/dbSave/recoverySave
		Action('admin.server')->$function();
	}

}
