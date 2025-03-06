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
					'smtp'		=> array('default' => 1),
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
		Model("File")->clearEmpty(0);
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
			'tab'	 => array('default'=>'', 'aliasKey'=>'type'),	// array('cache','db','recovery')
			'action' => array('check'=>'in', 'param'=>array('get', 'pinfo', 'save', 'task', 'clear'))
		));
		// srvGet/srvPinfo/cacheSave/dbSave/recoverySave
		$function = ($data['type'] ? $data['type'] : 'srv') . ucfirst($data['action']);
		$svcAct = Action('admin.server');
		if (!method_exists($svcAct, $function)) {
			show_json(LNG('common.illegalRequest'), false);
		}
		$svcAct->$function();
	}
	
	// 将mysql表转为mb4编码; >= 5.53支持mb4;
	public function updateMysqlCharset(){
		$dbType = $this->config['database']['DB_TYPE'];
		if($dbType != 'mysql' && $dbType != 'mysqli'){echoLog("not Mysql!");return;}
		
		$version = Model()->query('select VERSION() as version');
		$version = ($version[0] && isset($version[0]['version'])) ? floatval($version[0]['version']) : 0;
		if($version < 5.53){echoLog("Mysql version need bigger than 5.53");return;}
		
		//CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
		$db = Model()->db();
		$reset   = isset($this->in['reset']) && $this->in['reset'] == '1';
		$charsetSimple = $reset ? 'utf8':'utf8mb4';
		$charset = $reset ? 'utf8_general_ci':'utf8mb4_general_ci';
		$tables  = $db->getTables();
		$tables  = is_array($tables) ? $tables:array();
		
		$sqlArray = array(// 索引长度要小于1000;否则转为utf8mb4_general_ci会失败(转mb4后会变成4字节)
			"ALTER TABLE `comment_meta` ADD INDEX `key` (`key`(200)),DROP INDEX `key`",
			"ALTER TABLE `group` ADD INDEX `name` (`name`(200)),DROP INDEX `name`",
			"ALTER TABLE `comment_meta` ADD UNIQUE `commentID_key` (`commentID`, `key`(200)),DROP INDEX `commentID_key`",
			"ALTER TABLE `group_meta` ADD INDEX `key` (`key`(200)),DROP INDEX `key`",
			"ALTER TABLE `group_meta` ADD UNIQUE `groupID_key` (`groupID`, `key`(200)),DROP INDEX `groupID_key`;",
			"ALTER TABLE `io_file` ADD INDEX `name` (`name`(200)),	DROP INDEX `name`",
			"ALTER TABLE `io_file` ADD INDEX `path` (`path`(200)),	DROP INDEX `path`",
			"ALTER TABLE `io_file_meta` ADD INDEX `key` (`key`(200)),DROP INDEX `key`",
			"ALTER TABLE `io_file_meta` ADD UNIQUE `fileID_key` (`fileID`, `key`(200)),DROP INDEX `fileID_key`",
			"ALTER TABLE `io_source` ADD INDEX `name` (`name`(200)),DROP INDEX `name`",
			
			"ALTER TABLE `io_source_meta` ADD INDEX `key` (`key`(200)),DROP INDEX `key`",
			"ALTER TABLE `io_source_meta` ADD UNIQUE `sourceID_key` (`sourceID`, `key`(200)),DROP INDEX `sourceID_key`",
			"ALTER TABLE `user_meta` ADD INDEX `metaKey` (`key`(200)),DROP INDEX `metaKey`",
			"ALTER TABLE `user_meta` ADD UNIQUE `userID_metaKey` (`userID`, `key`(200)),DROP INDEX `userID_metaKey`",
			"ALTER TABLE `user_option` ADD INDEX `key` (`key`(200)),DROP INDEX `key`",
			"ALTER TABLE `user_option` ADD UNIQUE `userID_key_type` (`userID`, `key`(200), `type`),DROP INDEX `userID_key_type`",
			"ALTER TABLE `system_option` ADD UNIQUE `key_type` (`key`(200), `type`),DROP INDEX `key_type`",
		);
		
		echoLog("update index:".count($sqlArray));
		foreach ($sqlArray as $i=>$sql){
			// $sql = str_replace('(200)','',$sql);
			echoLog($sql);
			try{
				$db->execute($sql);
			}catch(Exception $e){echoLog("==error==:".$e->getMessage());}
		}
		
		// $config['databaseDefault'] = array('DB_CHARSET' => 'utf8')      // 数据库编码默认采用utf8
		// ALTER TABLE `group_meta` CHANGE `key` `key` varchar(255) NOT NULL COMMENT '存储key'
		echoLog(str_repeat('-',50)."\ntabls:".count($tables)." (speed ≈ 5w row/s);\n".str_repeat('-',50)."\n");
		foreach ($tables as $i=>$table){ //速度取决于表大小; 5w/s; 
			echoLog($table.":".$charset.";row=".Model($table)->count().'; '.($i+1).'/'.count($tables));
			//$res = $db->query('show create table `'.$table.'`');// 字段varchar 编码和表编码维持一致;
			// $sql = "ALTER TABLE `".$table."` COLLATE ".$charset;
			$sql = "ALTER TABLE `".$table."` convert to character set ".$charsetSimple." collate ".$charset;
			try {
				$db->execute($sql);
			}catch(Exception $e){echoLog("==error== ".$table.':'.$e->getMessage()."; ".$sql);}
		}
		
		// 自动更新数据库配置charset;
		$content = file_get_contents(BASIC_PATH.'config/setting_user.php');
		$content = preg_replace("/[ \t]*'DB_CHARSET'\s*=\>.*\n?/",'',$content);
		if(!$reset){
			$replaceTo = "  'DB_SQL_BUILD_CACHE'=>false,\n  'DB_CHARSET'=>'utf8mb4',\n";
			$content = preg_replace("/[ \t]*'DB_SQL_BUILD_CACHE'\s*=\>.*\n?/",$replaceTo,$content);
		}
		file_put_contents(BASIC_PATH.'config/setting_user.php',$content);
		
		echoLog(str_repeat('-',50));
		echoLog("update config/setting_user.php DB_CHARSET'=>'${charsetSimple}',");
		echoLog("Successfull!");
	}
}
