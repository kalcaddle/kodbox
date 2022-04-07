<?php 

/**
 * 服务器（cache、db）相关配置
 */
class adminServer extends Controller {
	function __construct() {
		parent::__construct();
	}

	// phpinfo
	public function srvPhpinfo(){
		phpinfo();exit;
	}

	/**
	 * 获取服务器缓存、数据库信息
	 * @return void
	 */
	public function srvGet(){
		$this->getSrvState();	// 服务器状态单独获取
		$data = array();
		$data['base'] = $this->getServerInfo();
		$data['cache'] = $GLOBALS['config']['cache'];
		$database = $GLOBALS['config']['database'];
		$data['db'] = array_change_key_case($database);
		$data['db']['db_info'] = $this->getDbInfo($data['db']);
		show_json($data);
	}

	// 获取服务器状态
	public function getSrvState(){
		if(!Input::get('state', null, 0)) return;

		$driver = KodIO::defaultDriver();
		// 默认为本地存储，且大小不限制，则获取所在磁盘大小
		if(strtolower($driver['driver']) == 'local' && $driver['sizeMax'] == '0') {
			$path = realpath($driver['config']['basePath']);
			$sizeDef = $this->srvSize($path);
		}else{
			$sizeUse = Model('File')->where(array('ioType' => $driver['id']))->sum('size');
			$sizeDef = array(
				'sizeTotal' => ((float) $driver['sizeMax']) * 1024 * 1024 * 1024,
				'sizeUse' => (float) $sizeUse
			);
		}
		$server = new ServerInfo();
		$memUsage = $server->memUsage();
		$sizeMem = array(
			'sizeTotal' => $memUsage['total'],
			'sizeUse' => $memUsage['used'],
		);
		$data = array(
			'cpu'		=> $server->cpuUsage(),	// CPU使用率
			'memory'	=> $sizeMem,	// 内存使用率
			'server'	=> $this->srvSize($this->srvPath()),	// 服务器系统盘空间
			'default'	=> $sizeDef,	// 网盘默认存储空间
			'time'		=> array(
				'time'	=> date('Y-m-d H:i:s'), 
				'upTime'=> $this->srvUptime()
			)
		);
		show_json($data);
	}
	// 系统盘路径
	private function srvPath(){
		$path  = '/';
		$isWin = $GLOBALS['config']['systemOS'] == 'windows';
		if($isWin) {
			$path = 'C:/';
			if(function_exists("exec")){
				exec("wmic LOGICALDISK get name",$out);
				$path = $out[1] . '/';
			}
		}
		return !file_exists($path) ? false : $path;
	}
	// 系统盘大小
	private function srvSize ($path){
		$data = array('sizeTotal' => 0, 'sizeUse' => 0);
		if($path) {
			$data['sizeTotal'] = @disk_total_space($path);
			$data['sizeUse'] = $data['sizeTotal'] - @disk_free_space($path);
		}
		return $data;
	}
	// 获取服务器持续运行时间
	private function srvUptime(){
		$list = array(
			'day'		=> 0,
			'hour'		=> 0,
			'minute'	=> 0,
			'second'	=> 0,
		);
		$time = '';
		if ($GLOBALS['config']['systemOS'] == 'windows') {
			$res = shell_exec('WMIC OS Get LastBootUpTime');
			$time = explode("\r\n", $res);
			$time = isset($time[1]) ? intval($time[1]) : '';
			if (!$time) return LNG('common.unavailable');
			$time = time() - strtotime($time);
		}else {
			$filePath = '/proc/uptime';
			if (@is_file($filePath)) {
				$time = file_get_contents($filePath);
			}
			if (!$time) return LNG('common.unavailable');
		}
		$num	= (float) $time;
		$second	= (int) fmod($num, 60);
		$num	= (int) ($num / 60);
		$minute	= (int) $num % 60;
		$num	= (int) ($num / 60);
		$hour	= (int) $num % 24;
		$num	= (int) ($num / 24);
		$day	= (int) $num;
		foreach($list as $k => $v) {
			$list[$k] = $$k;
		}
		$str = '';
		foreach($list as $key => $val) {
			$str .= ' ' . ($val ? $val : 0) . ' ' . LNG('common.'.$key);
		}
		return $str;
	}

	// 服务器基本信息
	public function getServerInfo(){
		$data = array(
			'server_state'	=> array(),	// 服务器状态
			'server_info'	=> array(),	// 服务器信息
			'php_info'		=> array(),	// PHP信息
			'db_cache_info' => array(),	// 数据库&缓存信息
			'client_info'	=> array(),	// 我的(客户端)信息
		);

		// 1.服务器状态
		// 2.服务器信息
		$server = $_SERVER;
		$phpVersion = 'PHP/' . PHP_VERSION;
		$data['server_info'] = array(
			'name'		=> $server['SERVER_NAME'],
			'ip'		=> $server['SERVER_ADDR'],
			'time'		=> date('Y-m-d H:i:s'),
			'upTime'	=> '',
			'softWare'	=> $server['SERVER_SOFTWARE'],
			'phpVersion'=> $phpVersion,
			'system'	=> php_uname(),
			'webPath'	=> BASIC_PATH,
		);

		// 3.php信息
		$data['php_info']['detail'] = 'phpinfo';
		$data['php_info']['version'] = $phpVersion;
		$info = array('memory_limit', 'post_max_size', 'upload_max_filesize', 'max_execution_time', 'max_input_time');
		foreach($info as $key) {
			$data['php_info'][$key] = ini_get($key);	// get_cfg_var获取的是配置文件的值，ini_get获取的是当前值
		}
		$data['php_info']['disable_functions'] = ini_get('disable_functions');
		$exts = get_loaded_extensions();
		$data['php_info']['php_ext'] = implode(',',$exts);
		$data['php_info']['php_ext_need'] = $this->phpExtNeed($exts);

		// 4.数据库&缓存信息
		$database = $GLOBALS['config']['database'];
        $dbType = $database['DB_TYPE'];
        if($dbType == 'pdo') {
            $dsn = explode(":", $database['DB_DSN']);
            $dbType = $dsn[0];
		}
		if(in_array($dbType, array('mysql', 'mysqli'))) {
            $res = Model()->db()->query('select VERSION() as version');
            $version = ($res[0] && isset($res[0]['version'])) ? $res[0]['version'] : 0;
            $dbType = 'MySQL' .  ($version ? '/' . $version : '');
        }else{
			$dbType = ($database['DB_TYPE'] == 'pdo' ? 'PDO-' : '') . str_replace('sqlite', 'SQLite', $dbType);
		}
		$data['db_cache_info'] = array(
			'db' => $dbType,
			'cache' => ucfirst($GLOBALS['config']['cache']['cacheType'])
		);

		// 5.我的信息
		$data['client_info'] = array(
			'ip' => get_client_ip(),
			'ua' => $server['HTTP_USER_AGENT'],
			'language' => $server['HTTP_ACCEPT_LANGUAGE']
		);
		return $data;
	}
	private function phpExtNeed($exts){
		$init = 'cURL,date,Exif,Fileinfo,Ftp,GD,gettext,intl,Iconv,imagick,json,ldap,Mbstring,Mcrypt,Memcached,MySQLi,SQLite3,OpenSSL,PDO,pdo_mysql,pdo_sqlite,Redis,session,Sockets,Swoole,dom,xml,SimpleXML,libxml,bz2,zip,zlib';
		$init = explode(',', $init);
		$data = array();
		foreach($init as $ext) {
			$value = in_array_not_case($ext, $exts) ? 1 : 0;
			$data[$ext] = $value;
		}
		return $data;
	}

	// 数据库信息
	public function getDbInfo($database){
		$type = $this->_dbType($database);
		if($type == 'sqlite') {
			$tables = Model()->db()->getTables();
			$rows = 0;
			foreach($tables as $table) {
				$rows += Model($table)->count();
			}
			// 数据库文件大小
			$file = $database['db_name'];
			if(!isset($database['db_name'])) {
				$file = substr($database['db_dsn'], strlen('sqlite:'));
			}
			$size = @filesize($file);
		}else{
			$tables = Model()->db()->query('show table status from `' . $database['db_name'] . '`');
			$rows = $size = 0;
			foreach($tables as $item) {
				$rows += $item['Rows'];
				$size += ($item['Data_lenth'] + $item['Index_length'] - $item['Data_free']);
			}
		}
		return array(
			'total_tables'	=> count($tables),
			'total_rows'	=> $rows,
			'total_size'	=> $size
		);
	}

	// 数据库类型：sqlite、mysql
	public function _dbType($database){
		$type = $database['db_type'];
		if($type == 'pdo') {
			$dsn = explode(':', $database['db_dsn']);
			$type = $dsn[0];
		}
		$typeArr = array('sqlite3' => 'sqlite', 'mysqli' => 'mysql');
		if(isset($typeArr[$type])) $type = $typeArr[$type];
		return $type;
	}

    /**
	 * 缓存配置切换检测、保存
	 */
	public function cacheSave(){
		if($this->in['check'] == '1'){
			$type = Input::get('type','in',null,array('file','redis','memcached'));
		}else{
			$type = Input::get('cacheType','in',null,array('file','redis','memcached'));
		}
		if(in_array($type, array('redis','memcached'))) {
			$data = $this->_cacheCheck($type);
			if(Input::get('check', null, 0)) {
				show_json(LNG('admin.setting.checkPassed'));
			}
		}
		// 更新setting_user.php
		$file = BASIC_PATH . 'config/setting_user.php';
		$text = array(
			PHP_EOL . PHP_EOL,
            "\$config['cache']['sessionType'] = '{$type}';",
            "\$config['cache']['cacheType'] = '{$type}';"
		);
		if($type != 'file'){
			$text[] = "\$config['cache']['{$type}']['host'] = '".$data['host']."';";
			$text[] = "\$config['cache']['{$type}']['port'] = '".$data['port']."';";
		}
		$content = implode(PHP_EOL, $text);
		if(!file_put_contents($file, $content, FILE_APPEND)) {
            show_json(LNG('explorer.error'), false);
		}
		Cache::deleteAll();
		show_json(LNG('explorer.success'));
	}
	private function _cacheCheck($type){
		if(!extension_loaded($type)){
			show_json(sprintf(LNG('common.env.invalidExt'), "[php-{$type}]"), false);
		}
		$data = Input::getArray(array(
			"{$type}Host" => array('check'=>'require', 'aliasKey'=>'host'),
			"{$type}Port" => array('check'=>'require', 'aliasKey'=>'port')
		));
		$cacheType = ucfirst($type);
        $handle = new $cacheType();
		try{
			if($type == 'redis') {
				$conn = $handle->connect($data['host'], $data['port'], 1);
			}else{
				$conn = $handle->addServer($data['host'], $data['port']);
				if($conn && !$handle->getStats()) $conn = false;
			}
			if(!$conn) show_json(sprintf(LNG('admin.install.cacheError'),"[{$type}]"), false);
		}catch(Exception $e){
			show_json(sprintf(LNG('admin.install.cacheConnectError'),"[{$type}]"), false);
		}
		return $data;
	}

	/**
	 * 数据库切换检测、保存
	 * @return void
	 */
	public function dbSave(){
		$this->taskGet('change');		// 获取任务状态
		$this->taskClear('change');		// 清除失败的数据
		// 当前数据库配置
		$database = $GLOBALS['config']['database'];
		$database = array_change_key_case($database);
		$type = $this->_dbType($database);

		// 数据库类型
		$data = Input::getArray(array(
			'db_type' => array('check' => 'in', 'param' => array('sqlite', 'mysql', 'pdo')),
			'db_dsn'  => array('default' => ''),	// mysql/sqlite
		));
		$dbType = !empty($data['db_dsn']) ? $data['db_dsn'] : $data['db_type'];
		$pdo = !empty($data['db_dsn']) ? 'pdo' : '';
		$dbList = $this->validDbList();
		// 判断系统环境是否支持选择的数据库类型
		if($pdo == 'pdo') {
			if(!in_array('pdo_'.$dbType, $dbList)) {
				show_json(sprintf(LNG('common.env.invalidExt'), 'pdo_'.$dbType), false);
			}
		}else{
			$allow = false;
			foreach($dbList as $value) {
				if($value == $dbType || stripos($value, $dbType) === 0) {
					$allow = true;
					break;
				}
			}
			if(!$allow) show_json(sprintf(LNG('common.env.invalidExt'), $dbType), false);
		}

		// 1. 切换了数据库类型，则全新安装，走完整流程
		if($dbType != $type) {
			return $this->dbChangeSave($dbType, $pdo, $database);
		}

		// 2. 没有改变数据库类型：pdo连接、配置参数、数据库变更等
		if($type == 'sqlite') {
			// 无论是检测还是保存，都直接返回
			show_json(LNG('admin.setting.dbNeedOthers'), false);
		}
		$data = $this->filterMysqlData();
		$match = true;
		foreach($data as $key => $value) {
			if($value != $database[$key]) {
				$match = false;
				break;
			}
		}
		// 2.2.1 配置参数不同
		if(!$match) {
			return $this->dbChangeSave($dbType, $pdo, $database);
		}
		$check = Input::get('check', null, false);
		// 2.2.2 配置参数相同，都是or不是pdo方式
		if($pdo == $database['db_type'] ||
			(!$pdo && $database['db_type'] != 'pdo')) {
			if($check) show_json(LNG('admin.setting.dbNeedChange'), false); // 说明没有修改，禁止切换
			show_json(LNG('explorer.success'));
		}
		if($check) show_json(LNG('admin.setting.checkPassed'));
		// 2.2.3 只是变更了pdo连接方式，更新配置文件，无需其他操作
		$option = $this->filterDatabase($pdo, $type, $database, $dbList);
		$option = array_merge($option, $data);

		$option = array_merge($option, $this->dbExtend($database));
		$option = array_change_key_case($option, CASE_UPPER);

        // 3. 保存配置
        $this->settingSave($dbType, $option, 'change');

		show_json(LNG('explorer.success'));
	}
    // 获取db_type、db_name/dsn配置
    private function filterDatabase($pdo, $type, $data, $dbList){
        if($pdo == 'pdo') {
            $option = array(
                'db_type'	=> 'pdo',
				'db_dsn'	=> $type,
				'db_name'	=> $data['db_name']
            );
            $dsn = $data['db_name'];
            if($type == 'mysql') {
				$port = (isset($data['db_port']) && $data['db_port'] != '3306') ? "port={$data['db_port']};" : '';
                $dsn = "host={$data['db_host']};{$port}dbname={$data['db_name']}";
            }
            $option['db_dsn'] .= ':' . $dsn;
        }else{
            $option = array(
                'db_type'	=> $type,
                'db_name'	=> $data['db_name']
            );
            if($type == 'sqlite') {
                if(in_array('sqlite3', $dbList)) $option['db_type'] = 'sqlite3';
            }else{
                if(in_array('mysqli', $dbList)) $option['db_type'] = 'mysqli';
            }
        }
        return $option;
    }
    // 获取mysql配置参数
    private function filterMysqlData(){
		$data = Input::getArray(array(
            'db_host'	=> array('check' => 'require'),
            'db_port'	=> array('default' => 3306),
            'db_user'	=> array('check' => 'require'),
            'db_pwd'	=> array('check' => 'require', 'default' => ''),
            'db_name'	=> array('check' => 'require'),
		));
		$host = explode(':', $data['db_host']);
        if(isset($host[1])) {
            $data['db_host'] = $host[0];
            $data['db_port'] = (int) $host[1];
		}
		return $data;
    }
    // 数据库配置追加内容
    private function dbExtend($database){
		$keys = array('db_sql_log', 'db_fields_cache', 'db_sql_build_cache');
		$data = array();
		foreach($keys as $key) {
			$data[$key] = $database[$key];
		}
		return $data;
	}
    // 写入配置文件的sqlite信息位置过滤
	private function filterSqliteSet($content) {
		$replaceFrom = "'DB_NAME' => '".USER_SYSTEM;
		$replaceTo   = "'DB_NAME' => USER_SYSTEM.'";
		$replaceFrom2= "'DB_DSN' => 'sqlite:".USER_SYSTEM;
		$replaceTo2  = "'DB_DSN' => 'sqlite:'.USER_SYSTEM.'";
		$content = str_replace($replaceFrom,$replaceTo,$content);
		$content = str_replace($replaceFrom2,$replaceTo2,$content);
		return $content;
    }
    // 有效的数据库扩展
    private function validDbList(){
        $db_exts = array('sqlite', 'sqlite3', 'mysql', 'mysqli', 'pdo_sqlite', 'pdo_mysql');
        $dblist = array_map(function($ext){
            if (extension_loaded($ext)){
                return $ext;
            }
        }, $db_exts);
        return array_filter($dblist);
    }

    // 数据库配置保存到setting_user.php
    private function settingSave($dbType, $option, $type){
        $option = var_export($option, true);
		$file = BASIC_PATH . 'config/setting_user.php';
        $content = PHP_EOL . PHP_EOL . "\$config['database'] = {$option};";
        if($dbType == 'sqlite') {
            $content = $this->filterSqliteSet($content);
        }
		if(!file_put_contents($file, $content, FILE_APPEND)) {
			// 删除复制的数据表文件
			del_dir($this->tmpActPath($type));
            show_json(LNG('explorer.error'), false);
        }
    }

    // 生成全新的数据库
	public function dbChangeSave($dbType, $pdo, $database){
        // 1. 获取数据库配置信息
		$dbList = $this->validDbList();
		if($dbType == 'sqlite') {
			if(Input::get('check', null, false)) {
				show_json(LNG('admin.setting.checkPassed'));
			}
			$dbFile = USER_SYSTEM . rand_string(12) . '.php';
			if(!@touch($dbFile)) {
				show_json(LNG('admin.setting.dbCreateError'), false);
			}
            $data = array('db_name' => $dbFile);
            $option = $this->filterDatabase($pdo, $dbType, $data, $dbList);
		}else{
            $data = $this->filterMysqlData();
            $option = $this->filterDatabase($pdo, $dbType, $data, $dbList);
			$option = array_merge($option, $data);
		}
		$option = array_merge($option, $this->dbExtend($database));
		$option = array_change_key_case($option, CASE_UPPER);

		// 数据库配置存缓存，用于清除获取
		$key = 'db_change.new_config.' . date('Y-m-d');
		Cache::set($key, array('type' => $dbType, 'db' => $option));

        // 2. 复制数据库
        $this->dbChangeAct($database, $option, $dbType);

        // 3.保存配置
		$taskSet = new Task('db.setting_user.set', $dbType, 1, LNG('admin.setting.dbSetSave'));
		$this->settingSave($dbType, $option, 'change');
		$taskSet->update(1);
		$this->taskToCache($taskSet);

		show_json(LNG('explorer.success'));
	}

	// 任务进度入缓存
	private function taskToCache($task, $id = null){
		$total = isset($task->task['taskTotal']) ? $task->task['taskTotal'] : $task->task['taskFinished'];
        $cache = array(
            'currentTitle'  => $task->task['currentTitle'],
            'taskTotal'     => $total,
            'taskFinished'  => $task->task['taskFinished'],
		);
		if($cache['taskFinished'] > 0 && $cache['taskTotal'] == $cache['taskFinished']) {
			$cache['success'] = 1;
		}
		$key = $id ? $id : $task->task['id'];
		Cache::set('task_'.$key, $cache);
		$task->end();
    }

	/**
	 * 复制数据库：当前到新增
	 * @param [type] $database
	 * @param [type] $option
	 * @param [type] $type	新增db类型
	 * @return void
	 */
    public function dbChangeAct($database, $option, $type){
		// 1.初始化db
		$manageOld = new DbManage($database, $type);
		$manageNew = new DbManage($option, $type);
		$dbNew = $manageNew->db(true);

		// 2.指定库存在数据表，提示重新指定；不存在则继续
		$tableNew = $dbNew->getTables();
		if(!empty($tableNew)) {
			show_json(LNG('admin.setting.dbNeedNew'), false);
		}
		if(Input::get('check', null, false)) {
			show_json(LNG('admin.setting.checkPassed'));
		}

		// 截断http请求，后面的操作继续执行
		echo json_encode(array('code'=>true,'data'=>'OK', 'info'=>1));
		http_close();

		$taskCrt = new Task('db.new.table.create', $type, 0, LNG('admin.setting.dbCreate'));
		// 3.表结构写入目标库
		// TODO 这里其实应该从当前库导出表结构，并创建
        $file = CONTROLLER_DIR . "install/data/{$type}.sql";
        $manageNew->createTable($file, $taskCrt);
		$tableNew = $dbNew->getTables();

		// 4.获取当前表数据，写入sql文件
		$pathLoc = $this->tmpActPath('change');
		del_dir($pathLoc); mk_dir($pathLoc);

		$fileList = array();
		$tableOld = $manageOld->db()->getTables();
		$tableOld = array_diff($tableOld, array('______', 'sqlite_sequence'));
		$total = 0;
        foreach($tableOld as $table) {
			if(!in_array($table, $tableNew)) continue;
			$total += $manageOld->model($table)->count();
		}
		$taskGet = new Task('db.old.table.select', $type, $total, LNG('admin.setting.dbSelect'));
        foreach($tableOld as $table) {
			// 对比原始库，当前库如有新增表，直接跳过
			if(!in_array($table, $tableNew)) continue;
			$file = $pathLoc . $table . '.sql';
            $manageOld->sqlFromDb($table, $file, $taskGet);
            $fileList[] = $file;
		}
		// 这里的task缺失id等参数，导致cache无法保存，原因未知
		$this->taskToCache($taskGet, 'db.old.table.select');

		$taskAdd = new Task('db.new.table.insert', $type, 0, LNG('admin.setting.dbInsert'));
		// 5.读取sql文件，写入目标库
        $manageNew->insertTable($fileList, $taskAdd);

		// 6.删除临时sql文件
        del_dir($pathLoc);
	}

	/**
	 * 数据库恢复
	 * @return void
	 */
	public function recoverySave(){
		// TODO 待优化问题：
		// 备份文件先下载至临时目录，如果本就在本地，则没有必要；中途失败，显示提示到弹窗下；中途失败不能继续（包括切换）
		$this->taskGet('recovery');		// 获取任务状态
		$this->taskClear('recovery');	// 清除失败的数据
		$data = Input::getArray(array(
			'recType'	=> array('check' => 'in', 'param' => array('sqlite', 'mysql'), 'aliasKey' => 'type'),
			'recPath'	=> array('check' => 'require', 'aliasKey' => 'path'),
		));
		if(!$info = IO::info($data['path'])){
			show_json(LNG('admin.setting.recPathErr'), false);
		}

		// 1.判断选择的路径是否有效
		$type = $data['type'];
		$path = $info['path'];
		if($info['type'] != 'folder') {
			show_json(LNG('admin.setting.recSysPathErr'), false);
		}
		// 1.1 结构文件是否存在
		if(!IO::fileNameExist($path, $type.'.sql')) {
			show_json(LNG('admin.setting.recSysTbErr'), false);
		}
		// 1.2 数据表文件是否完整
		$list = IO::listPath($path, true);
		$tableNew = array();
		foreach($list['fileList'] as $value) {
			$tableNew[] = basename($value['name'], '.sql');
		}
		$tableOld = Model()->db()->getTables();
		$tableOld = array_diff($tableOld, array('______', 'sqlite_sequence'));
		$diff = array_diff($tableOld, $tableNew);
		if(!empty($diff)) {
			show_json(LNG('admin.setting.recDbFileErr'), false);
		}
		// 检测结果直接返回
		if(Input::get('check', null, false)) {
			$dbname = 'kod_rebuild_test';
			// 如果没有权限，这里会直接报错
		    $res = Model()->db()->execute("create database `{$dbname}`");
		    if ($res) {
		        Model()->db()->execute("drop database if exists `{$dbname}`");
		    }
			show_json(LNG('admin.setting.checkPassed'));
		}
		echo json_encode(array('code'=>true,'data'=>'OK', 'info'=>1));
		http_close();

		// 2.导入数据库
		ActionCall('user.index.maintenance', true, 1);
		// 2.1 下载备份文件到本地临时目录
		$pathLoc = $this->tmpActPath('recovery');
		$path = $this->recLocPath($type, $path, $pathLoc);
		$list = IO::listPath($path, true);
		$fileList = array_to_keyvalue($list['fileList'], 'name', 'path');
		$file = $fileList[$type . '.sql'];	// sqlite.sql、mysql.sql
		if(!$file) {
			ActionCall('user.index.maintenance', true, 0);
			show_json(LNG('admin.setting.dbFileDownErr'), false);
		}

		// 2.2 新建数据库
		$database = $this->recDatabase($data);
        $manage = new DbManage($database, $type);
        $manage->db(true);   // 新建数据库

        // 2.3 新建数据表
        $taskCrt = new Task('recovery.db.table.create', $type, 0, LNG('admin.setting.dbCreate'));
        $manage->createTable($file, $taskCrt);

		// 2.4 读取sql文件，写入目标库
        $taskAdd = new Task('recovery.db.table.insert', $type, 0, LNG('admin.setting.dbInsert'));
        $manage->insertTable($fileList, $taskAdd);

		// 2.5 删除临时sql文件
        del_dir($pathLoc);
		$database = array_change_key_case($database, CASE_UPPER);

        // 3.保存配置
		$taskSet = new Task('recovery.db.setting_user.set', $type, 1, LNG('admin.setting.dbSetSave'));
		$this->settingSave($type, $database, 'recovery');
		$taskSet->update(1);
		$this->taskToCache($taskSet);

		ActionCall('user.index.maintenance', true, 0);
		// $this->in['clear'] = 1;
		// $this->in['success'] = 1;
		// $this->taskClear('recovery');

		show_json(LNG('explorer.success'));
	}

	// sql文件下载到本地临时目录
	private function recLocPath($type, $path, $pathLoc){
		del_dir($pathLoc); mk_dir($pathLoc);
		$task = new TaskFileTransfer('recovery.db.file.download', $type, 0, LNG('admin.setting.dbFileDown'));
		$task->addPath($path);
		$path = IO::copy($path, $pathLoc);
		$this->taskToCache($task);
		return $path;
	}

	// 数据恢复使用的db配置信息
	private function recDatabase($data, $name = '') {
		$type = $data['type'];
		$database = $GLOBALS['config']['database'];
		$database = array_change_key_case($database);
		if($type == 'sqlite') {
			if(!$name) {
				$name = USER_SYSTEM . rand_string(12) . '.php';
				if(!@touch($name)) {
					ActionCall('user.index.maintenance', true, 0);
					show_json(LNG('admin.setting.dbCreateError'), false);
				}
			}
		}else{
			$name = $database['db_name'] . '_' . date('Ymd') . '_rebuild';
			$name = substr($name, 0, 64);	// 长度限制64
		}
		$database['db_name'] = $name;
		if($database['db_type'] == 'pdo') {
			if($type == 'mysql') {
				$dsn = explode(';', $database['db_dsn']);
				$dsn[count($dsn) - 1] = 'dbname=' . $name;
				$dsn = implode(';', $dsn);
			}else{
				$dsn = $type . ':' . $name;
			}
			$database['db_dsn'] = $dsn;
		}
		$key = 'db_recovery.new_config.' . date('Y-m-d');
		Cache::set($key, array('type' => $type, 'db' => $database));
		return $database;
	}

	// 临时目录
	private function tmpActPath($type){
		return TEMP_FILES . 'db_' . $type . '_' . date('Ymd') . '/';
	}

	// 获取（切换、恢复）任务名称
	private function actTask($type, $step = '') {
		$task = array(
			'change' 	=> array(
				'step1' => 'db.new.table.create',
				'step2' => 'db.old.table.select',
				'step3' => 'db.new.table.insert',
				// 'step4' => 'db.temp_dir.del',
				'step4' => 'db.setting_user.set',
			),
			'recovery'	=> array(
				'step1' => 'recovery.db.file.download',
				'step2' => 'recovery.db.table.create',
				'step3' => 'recovery.db.table.insert',
				'step4' => 'recovery.db.setting_user.set',
			)
		);
		return $step ? $task[$type][$step] : $task[$type];
	}
	// 进行中（切换、恢复）任务进度获取
	private function taskGet($type){
		if(!Input::get('task', null, 0)) return;
		$task = $this->actTask($type);
		$data = array();
		foreach($task as $k => $val) {
			$value = Cache::get('task_'.$val);
			if(!$value) $value = Task::get($val);
			// if(isset($value['status']) && $value['status'] == 'kill') $value = false;
			$data[$k] = $value;
		}
		show_json($data);
	}
	// 结束后（切换、恢复）任务、及其他清除
	private function taskClear($type){
		if(!Input::get('clear', null, 0)) return;
		if(Input::get('success', null, false)) {
			Cache::clearTimeout();
			Cache::deleteAll();
			del_dir(TEMP_PATH);
			show_json(LNG('explorer.success'));
		}
		// 1.杀掉任务、清除缓存
		$task = $this->actTask($type);
		foreach($task as $key) {
			Task::kill($key);
			// Cache::remove($key);
			Cache::remove('task_'.$key);
		}
		// 2.删除临时sql目录
		del_dir($this->tmpActPath($type));
		// 3.删除导入失败的数据库
		$this->dropErrorDb($type);
		show_json(LNG('explorer.success'));
	}
	// 删除导入失败的数据表
	private function dropErrorDb($type){
		$key = 'db_'.$type.'.new_config.'.date('Y-m-d');
		if(!$cache = Cache::get($key) || empty($cache['db'])) return;

		$type = $cache['type'];
		if($type == 'sqlite') {
			del_file($cache['db']['db_name']);
		}else if ($type == 'mysql') {
			$manage = new DbManage($cache['db'], $type);
			// if($manage) $manage->dropTable();
			if($manage) {
				$dbname = $cache['db']['db_name'];
				model()->db()->execute("drop database if exists `{$dbname}`");
			}
		}
		Cache::remove($key);
	}

}