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
		$setting = array();
		foreach ($data as $key => $value) {
			$setting[$key] = $value;
		}
		
		$postMax = get_post_max();
		if($setting['chunkSize']*1024*1024  >= $postMax){
			$sizeTips = ($postMax/(1024*1024)) .'MB';
			show_json(LNG('admin.setting.transferChunkSizeDescError1').
			":$sizeTips,<br/>".LNG('admin.setting.transferChunkSizeDescError2'),false);
		}

		Model('SystemOption')->set($setting);
		show_json(LNG('explorer.success'));
	}
	
	/**
	 * 发送邮件测试-用户注册功能设置
	 */
	public function mailTest() {
		$input = Input::get('address', 'require');
		$systemName = Model('SystemOption')->get('systemName');
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
					'data'	=> array('code' => rand_string(6))
				),
				'signature'	=> $systemName,
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
		Cache::clearTimeout();
		Cache::deleteAll();
		http_close();
		del_dir(TEMP_PATH);
		mk_dir(TEMP_PATH . 'log');
		Action('explorer.attachment')->clearCache();
		AutoTask::restart();//停止计划任务; (再次访问自动开启)
	}

	/**
	 * 服务器管理：基础信息、缓存、db
	 * @return void
	 */
	public function server(){
		$data = Input::getArray(array(
			'tab'	 => array('default'=>'', 'aliasKey'=>'type'),
			'action' => array('check'=>'in', 'param'=>array('get', 'phpinfo', 'save', 'task', 'clear'))
		));
		$function = ($data['type'] ? $data['type'] : 'srv') . ucfirst($data['action']);
		Action('admin.server')->$function();
	}

}
