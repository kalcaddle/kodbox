<?php 

/**
 * 初始化项目基础数据
 * 需先创建数据库，建表，并配置好config/setting_user.php
 */
class installIndex extends Controller {
    public $roleID;
    public $admin = array();
    public $userSetting; 
    public $installLock; 
    public $dbType;
    public $engine;
	public function __construct() {
        parent::__construct();
        $this->userSetting      = BASIC_PATH  . 'config/setting_user.php';
        $this->installLock      = USER_SYSTEM . 'install.lock';
        $this->installFastLock  = USER_SYSTEM . 'fastinstall.lock'; // 旧版
		$this->authCheck();
    }
    // 请求权限检测
    public function authCheck(){
        if(MOD.'.'.ST == 'install.index'){
            if(!ACT || !in_array(ACT, array('env', 'save', 'auto'))){
                show_json(LNG('common.illegalRequest'), false);
            }
            $check = $this->initCheck();
            if(ACT == 'auto') $this->cliInstall($check);    // cli自动安装
            if($check === 2) show_json(LNG('admin.install.errorRequest'), false);
        }
    }
    // 安装配置初始化检测
    private function initCheck(){
        if(!defined('STATIC_PATH')){
            define('STATIC_PATH',$GLOBALS['config']['settings']['staticPath']);
        }
        if(!defined('INSTALL_PATH')){
            define('INSTALL_PATH', CONTROLLER_DIR.'install/');
        }
        // 1.setting_user.php、install.lock、数据库配置都存在，已安装；
        // 2.setting_user.php、数据库配置存在，install.lock不存在，重置管理员密码
        if(@file_exists($this->userSetting)) {
            if($this->defDbConfig(true)) {
                // if(@file_exists($this->installLock)) return true;
                return @file_exists($this->installLock) ? 2 : 1;
            } else {
                del_file($this->userSetting);
            }
        }
        if(!@file_exists($this->userSetting)) del_file($this->installLock);
        // return false;
        return 0;
    }

    // 安装检测
    public function check(){
        // 判断是否需要安装
        if($this->initCheck() === 2) return;
		if(ACTION == 'user.view.call'){exit;}
        if(ACTION == 'user.view.options'){
            $options = array(
                "kod"	=> array(
                    'systemOS'		=> $this->config['systemOS'],
                    'phpVersion'	=> PHP_VERSION,
                    'appApi'		=> appHostGet(),
                    'APP_HOST'		=> APP_HOST,
                    'ENV_DEV'		=> !!STATIC_DEV,
                    'staticPath'	=> STATIC_PATH,
                    'version'		=> KOD_VERSION,
                    'build'			=> KOD_VERSION_BUILD,
                    'channel'		=> INSTALL_CHANNEL,
                ),
                "user"	=> array('config' => array()),
                "io"	=> array(),
                "lang"	=> I18n::getType(),
            );
			if($this->in['full'] == '1'){
				$options['_lang'] = array(
					"list"	=> I18n::getAll(),
					"lang"	=> I18n::getType(),
				);
			}
            show_json($options);
        }
		$actions = array(
			'install.index.env', 
			'install.index.save',
			'user.view.lang',
		);
        if( in_array(ACTION, $actions) ){
			ActionCall(ACTION);exit;
        }
		
		// 安装路径合法性检测; 不允许[, ;%][*][&=+%#?{}]; 允许(_!@$[]()-./); 需要转义([]@$)   
        $uri = str_replace(HOST,'',APP_HOST);$matchArr = array();
        if($uri && !preg_match("/^[a-zA-Z0-9_!@$\[\]\(\)\-\.\/]*$/",$uri,$matchArr)){
        	show_tips("App path cann't has special chars<br/>(".LNG('admin.install.pathErrDesc').")<pre>".$uri.'</pre>');
        }
		
        $this->tpl = CONTROLLER_DIR . 'install/static/';
        $value = array(
            'installPath'   => APP_HOST.'app/controller/install/static/', 
            'envList'       => $this->envList(),
            'installFast'   => @file_exists($this->userSetting) ? 1 : 0,    // 是否已安装（setting_user.php存在）
            'installAuto'   => ''   // 管理员账号名
        );
        // 获取管理员账号
        if ($value['installFast']) {
            try{
                $info = Model('User')->find('1');
                if (!empty($info['name'])) $value['installAuto'] = $info['name'];
			}catch(Exception $e){} 
        }
        $this->values = $value;
        $this->display('index.html');
        exit;
    }
    private function envList(){
        $opts = array(
            'path_writable'     => array('title' => LNG('admin.install.dirRight'), 'text' => LNG('admin.install.pathNeedWirte')),
            'php_version'       => array('title' => 'PHP '.LNG('common.version'), 'text' => LNG('admin.install.phpVersionTips')),
            'allow_url_fopen'   => array('text'  => LNG('admin.install.mustOpen')),
            'php_bit'           => array('title' => 'PHP '.LNG('common.sysVersion'), 'text' => LNG('admin.install.phpBitTips').'<br/><span class="bit-desc">('.LNG('admin.install.phpBitDesc').')</span>'),
            'iconv'             => array(),
            'mb_string'         => array(),
            'json'              => array(),
            'curl'              => array(),
            'xml'               => array(),
            'shell_exec'        => array('title' => 'shell_exec、exec '.LNG('common.method')),
            'gd'                => array(),
            'path_list'         => array('title' => LNG('admin.install.serverDir'), 'text' => LNG('admin.install.suggestClose'))
        );
        foreach ($opts as $key => &$opt) {
            $opt['title'] = _get($opt, 'title', strtoupper($key).' '.LNG('common.extend'));
            $opt['text'] = _get($opt, 'text', LNG('admin.install.suggestOpen'));
        }
        return $opts;
    }

    /**
     * cli一键安装
     * php /usr/local/var/www/kod/kodbox/index.php "install/index/auto" 
     * --database mysql --database-name "kodbox" --database-user "root" --database-pass "root" --database-host "127.0.0.1:3306" 
     * --database sqlite    // or
     * --cache redis --redis-host "127.0.0.1" --redis-port "6379" --redis-auth "1234"   // 不传递是默认文件缓存
     * --user-name "admin" --user-pass "admin"
     * --db-del     1/0     // 数据表已存在，是否删除/保留，默认保留
     * --user-auto  1       // 随机密码：admin/xxx
     * --user-reset 1       // 重置管理员密码
     * --language en        // 默认为中文
     * @return void
     */
    public function cliInstall($check=0) {
        if (!is_cli() || !isset($_SERVER['argv'])) return;
        // 接管show_tips
        Hook::bind('show_tips', array($this, 'cliShowTips'));
        // 切换英文——会导致系统以英文初始化
        if ($this->cliArgs('language') == 'en' && I18n::getType() != 'en') {
            $array = include(LANGUAGE_PATH.'en/index.php');
            if (!empty($array)) I18n::set($array);
        }
        // 已安装
        if ($check === 2) $this->cliEcho(LNG('admin.install.errorRequest'));

        // 获取一键安装参数
        $action = _get($this->in, 'action', '');
        if ($action && in_array($action, array('db', 'user'))) return;  // 避免自动安装死循环——ACT=auto
        $data = $this->cliInstallData();

        //0.非重置账号，检测db参数及配置——仅新版支持参数检查、重置密码
        if (!isset($this->cliInstVer)) {
            if (!$this->cliMore('reset')) {
                if (!$check && empty($data['db'])) {
                    $this->cliEcho(LNG('common.invalidParam'));
                }
                // db配置已存在，禁止再次执行
                if ($check === 1) {
                    $msg = PHP_EOL.LNG('admin.install.dbWasSet').PHP_EOL;
                    $txt = empty($data['user']['pass']) ? '--user-name "'.LNG('user.account').'" --user-pass "'.LNG('common.password').'" ' : '';
                    $msg .= LNG('admin.install.ifResetAuto').$txt.'--user-reset 1';
                    $this->cliEcho($msg);
                }
            } else {
                if (!$check) $this->cliEcho(LNG('admin.install.resetSysErr'));
                $this->cliSaveUser($data); exit;
            }
        }

        // 1.提交数据库设置——成功后自动提交管理员设置
        $this->cliSaveDb($data);
    }

    // 1.自动提交数据库保存
    public function cliSaveDb($data){
        $self = $this;
        $init = array(
            'action'    => 'db', 
            'del'       => $this->cliMore('del') ? 1 : 0
        );
        $this->in = array_merge($init, $data['db']);
        ActionCallResult("install.index.save",function(&$res) use($self,$data){
            $msg = str_replace('<br/>',PHP_EOL,$res['data']);
            if (!$res['code']) {
                // 数据库已存在且有表，视作自行导入的kod库（暂不做对比检测）
                if (_get($res,'info') == 10001) {
                    $keep = !$self->cliMore('del');    // 默认保留：del=0/null
                    if (!$keep) {   // 不保留——执行不到这里：默认保留（$keep），不保留（del=1）会直接删除，不返回info=10001
                        $msg = LNG('admin.install.ifDelDbAuto');
                        $msg = str_replace('[1]', '['._get($data,'db.dbName','').']', $msg);
                    } else {
                        // 添加配置文件——旧版存在，新版不存在
                        if (!isset($self->cliInstVer)) $self->cliUserSetting($data['db']);
                    }
                } else {
                    $self->cliEcho($msg);
                }
            }
            // $msg = stristr(I18n::getType(),'zh') ? '数据库配置完成！' : 'Database configuration completed!';    // LNG('admin.install.dbSetOk')
            $self->cliEcho('1.Database configuration completed!',true,false);

            // 2.执行成功，写入管理员账号
            $self->cliSaveUser($data);
		});
    }
    // 保存数据库配置文件
    private function cliUserSetting($db) {
        $type   = _get($db, 'cacheType', 'file');
        $cache  = array('type' => $type);
        if ($type != 'file') {
            $cache['host'] = _get($db, $type.'Host', '');
            $cache['port'] = _get($db, $type.'Port', '');
            if ($type == 'redis' && !empty($db['redisAuth'])) {
                $cache['auth'] = $db['redisAuth'];
            }
        }
        $data = $this->dbConfig();
        $this->setUserSetting($data, $cache);
    }

    // 2.自动提交管理员保存（系统初始化）
    public function cliSaveUser($data) {
        $name = _get($data, 'user.name', '');
        $pass = _get($data, 'user.pass', '');
        // 非重置账号，没有传递账号密码时，提醒在web访问设置
        if (!$this->cliMore('reset') && (empty($name) || empty($pass))) {
            del_file($this->installFastLock);
            $this->cliEcho(LNG('admin.install.userOnWeb'), true);
        }
        $this->in = array(
            'name'      => $name,
            'password'  => $pass,
            'action'    => 'user'
        );
        $self = $this;
        ActionCallResult("install.index.save",function(&$res) use($self,$name,$pass){
            if (!$res['code']) {
                $msg = str_replace('<br/>',PHP_EOL,$res['data']);
                $self->cliEcho(LNG('admin.install.userSaveErr').$msg);
            }
            del_file($self->installFastLock);
            if (!$self->cliMore('reset')) {
                // $msg = stristr(I18n::getType(),'zh') ? '系统初始化完成！' : 'System initialization completed!';    // LNG('admin.install.userSetOk')
                $self->cliEcho('2.System initialization completed!',true,false);
            }

            $msg = LNG('admin.install.autoPwdTips').$name.' '.$pass;
            if (!$self->cliMore('auto')) $msg = ''; // 仅自动生成密码时，在回复中显示
            $self->cliEcho($msg, true);
		});
    }

    // 获取自动安装配置参数
    private function cliInstallData(){
        if (empty($_SERVER['argv']) || !is_array($_SERVER['argv'])) return;
        $this->cliInstallOld();    // 兼容旧版
		$args = $this->cliArgs();
		if (empty($args)) return;
        $this->cliMore($args);

        // 1.检查参数：非重置密码时，检查db、缓存；重置时，忽略数据库、缓存（要求系统已完成初始化）
        $dbType = _get($args, 'database', '');
        $ccType = _get($args, 'cache', 'file');
        if (!$this->cliMore('reset')) {
            if (!$dbType || !in_array($dbType, array('mysql', 'sqlite'))) {
                $this->cliEcho(LNG('common.invalidParam').' database');
            }
            if (!in_array($ccType, array('file', 'redis', 'memcached'))) {
                $this->cliEcho(LNG('common.invalidParam').' cache');
            }
        } 

        // 2.构建前端提交的参数样式
        $db = $user = array();
        $db['dbType'] = $dbType;
        $db['cacheType'] = $ccType;
        foreach ($args as $key => $value) {
            $arr = explode('-', $key);
            if (empty($arr[1])) continue;
            $tmp = $arr[0];
            // 管理员
            if ($tmp == 'user') {
                $user[$arr[1]] = $value;
                continue;
            }
            // 数据库、缓存
            if ($tmp == 'database') {
                $tmp = 'db';
                if ($arr[1] == 'pass') $arr[1] = 'pwd';
            }
            $db[$tmp.ucfirst($arr[1])] = $value;
        }
        // redis、memcached端口赋默认值
        if ($ccType != 'file' && empty($db[$ccType.'Port'])) {
            $db[$ccType.'Port'] = $ccType == 'redis' ? '6379' : '11211';
        }
        if ($dbType == 'mysql') $db['dbEngine'] = 'innodb';

        // 3.账号密码
        if (empty($user['name'])) $user['name'] = 'admin';  // 不传递默认为admin——重置时应该从数据库读取，暂不处理
        if ($this->cliMore('auto')) {
            $user['pass'] = 'K0d#'.rand_string(6);
        } else {
            // 重置且非自动时，要求必须填写密码
            if ($this->cliMore('reset') && empty($user['pass'])) {
                $this->cliEcho(LNG('admin.install.resetPwdTips'));
            }
        }
        // 4.重置用户，数据库、缓存置为空
        if ($this->cliMore('reset')) $db = array();
        
        return array('db' => $db, 'user' => $user);
    }
    private function cliArgs($key=false){
        $argv = $_SERVER['argv'];
        if (!is_array($argv)) return array();
		array_shift($argv);
    	array_shift($argv);	// 移除前2个参数：./index.php、install/index/auto
    	$name = null;
		$args = array();
		foreach ($argv as $arg) {
			if (substr($arg, 0, 1) == '-') {
				$name = ltrim($arg, '-');
			} else {
				if ($name) {
					$args[$name] = $arg;
					$name = null;
				}
			}
		}
        return $key ? _get($args, $key) : $args;
    }
    // 获取cli调用附加参数
    private function cliMore($key=false) {
        if (!$key) unset($GLOBALS['AUTO_INSTALL_IN_MORE']);
        if (is_string($key)) {
            $val = _get($GLOBALS, 'AUTO_INSTALL_IN_MORE.'.$key, 0);
            return $val == '1' ? true : false;
        }
        $args = $key;
        $GLOBALS['AUTO_INSTALL_IN_MORE'] = array(
            'del'       => _get($args,'db-del',0),      // 数据表删除/保留
            'auto'      => _get($args,'user-auto',0),   // 管理员随机密码
            'reset'     => _get($args,'user-reset',0),  // 管理员密码重置
        );
    }
    // 命令行中输出结果——换行
    private function cliEcho($msg, $code=false, $prfx=true) {
        // $pre = !$this->cliMore('reset') ? 'admin.install.' : 'explorer.';
        // echo ($prfx ? LNG($pre.($code ? 'success' : 'error')).' ' : '').$msg.PHP_EOL; 
        echo ($code ? 'Success! ' : 'Error! ').$msg.PHP_EOL; 
        if ($prfx) exit;
    }
    // 捕获show_tips错误，直接输出信息——开启debug时无效（pr输出）
    public function cliShowTips($message,$url, $time,$title) {
        $msg = '';  // think_exception输出html
        // if (stripos($message,'<div class="desc"') !== false) {
        if (stripos($message,'<div') !== false) {
            $dom = new DOMDocument();  
            @$dom->loadHTML(mb_convert_encoding($message, 'HTML-ENTITIES', 'UTF-8'));
            // $xpath = new DOMXPath($dom);
            // $elements = $xpath->query('//div[@class="desc"]');
            // if (!is_null($elements)) $msg = $elements[0]->nodeValue;
            foreach ($dom->getElementsByTagName('body')->item(0)->childNodes as $node) {
                $msg .= $node->nodeValue.PHP_EOL;
            }
            $msg = rtrim($msg, PHP_EOL);
        }
        if (!$msg) $msg = $message;
        $this->cliEcho($msg);
    }

    /**
     * cli一键安装（兼容旧版）
     * 根据fastinstall.lock判断，默认包含数据库和配置文件，以此构建argv参数以适配新版逻辑
     * @return void
     */
    public function cliInstallOld(){
        $file = $this->installFastLock;
        if (!@file_exists($file)) return;   // KOD_VERSION >= 1.52
        if (!@file_exists($this->userSetting)) $this->cliEcho('Invalid configuration file (config/setting_user.php).');
        $this->cliInstVer = 'old';

        // 构建argv参数
        $data = array();
        $db = $this->config['database'];
        $cc = $this->config['cache'];
        // 数据库
        if (stripos($db['DB_TYPE'],'sqlite') === 0) {
            $data = array('--database', 'sqlite');
        } else {
            $data = array(
                '--database', 'mysql',
                '--database-host',
                $db['DB_HOST'].':'._get($db,'DB_PORT',3306),
                '--database-user',
                $db['DB_USER'],
                '--database-pass',
                $db['DB_PWD'],
                '--database-name',
                $db['DB_NAME'],
            );
        }
        // 缓存
        $ccType = _get($cc, 'cacheType', 'file');
        if (in_array($ccType, array('redis', 'memcached'))) {
            $data[] = '--cache';
            $data[] = $ccType;
            $data[] = '--'.$ccType.'-host';
            $data[] = _get($cc, $ccType.'.host','');
            $data[] = '--'.$ccType.'-port';
            $data[] = _get($cc, $ccType.'.port','');
            if ($ccType == 'redis' && !empty($cc['redis']['auth'])) {
                $data[] = '--redis-auth';
                $data[] = $cc['redis']['auth'];
            }
        }
        // 管理员
        $user = $this->getFastAcc($file);
        if ($user) {
            $data[] = '--user-name';
            $data[] = $user['name'];
            $data[] = '--user-pass';
            $data[] = $user['pass'];
        }

        $_SERVER['argv'] = array_merge($_SERVER['argv'], $data);
    }
    // 获取自动安装账号密码
    private function getFastAcc($file){
        $content = trim(file_get_contents($file));
        if(empty($content)) return false;

        $data = array();
        $content = array_filter(explode(PHP_EOL, $content));
        foreach($content as $line) {
            $tmp = explode("=", trim($line));
            if(empty(trim($tmp[1]))) continue;
            $data[strtolower(trim($tmp[0]))] = trim($tmp[1]);
        }
        if(isset($data['adm_name']) && isset($data['adm_pwd'])) {
            return array(
                'name' => $data['adm_name'],
                'pass' => $data['adm_pwd']
            );
        }
        return false;
    }

    /**
     * 获取数据库默认配置信息
     * @param boolean $return false:表单数据;true:db配置数据
     * @return void
     */
    public function defDbConfig($return=false){
        $data = array();
        // 1.获取文件数据
        if(!$dbConfig = $this->config['database']) return $data;
        if(!$dbConfig['DB_TYPE']) return $data;
        if($return) return $dbConfig;

        // 2.解析为form表单数据
        $database = array();
        unset($dbConfig['DB_SQL_LOG'],$dbConfig['DB_FIELDS_CACHE'],$dbConfig['DB_SQL_BUILD_CACHE']);
        foreach($dbConfig as $key => $value) {
            $keys = explode("_", strtolower($key));
            $key = $keys[0] . ucfirst($keys[1]);
            $database[$key] = $value;
        }
        // 2.1 pdo数据处理
        if($database['dbType'] == 'pdo') {
            $dsn = explode(":", $database['dbDsn']);
            $database['pdoType'] = $dsn[0];
            unset($database['dbDsn']);
            foreach($database as $key => $value) {
                if(in_array($key, array('dbType', 'pdoType'))) continue;
                $database['pdo'.ucfirst($key)] = $value;
                unset($database[$key]);
            }
        }
        if($database['dbType'] == 'mysqli') $database['dbType'] = 'mysql';
        if(in_array($database['dbType'], array('sqlite', 'sqlite3'))) {
            if($database['dbType'] == 'sqlite3') $database['dbType'] = 'sqlite';
            $database['dbName'] = '';
        }
        if(!$cache = $this->config['cache']) return $database;  // 无cache

        $cacheType = $cache['cacheType'];
        $database['cacheType'] = $cacheType;
        if($cacheType == 'file') return $database;  // 文件cache
        $database[$cacheType.'Host'] = $cache[$cacheType]['host'];
        $database[$cacheType.'Port'] = $cache[$cacheType]['port'];

        return $database;
    }

    // 环境检测
    public function env(){
        if(isset($this->in['db']) && $this->in['db'] == 1) {
            $data = $this->defDbConfig();
            show_json($data);
        }
        $env = array(
            'path_writable'     => array(),
            'php_version'       => phpversion(),
            'allow_url_fopen'   => ini_get('allow_url_fopen') == '1',
            'php_bit'           => phpBuild64() ? 64 : 32,
            'iconv'             => function_exists('iconv'),
            'mb_string'         => function_exists('mb_convert_encoding'),
            'json'              => function_exists('json_encode'),
            'curl'              => function_exists('curl_init'),
			'xml'               => function_exists('xml_parser_create'),
            'shell_exec'        => (function_exists('shell_exec') && function_exists('exec')),
            'gd'                => true,
            'path_list'         => check_list_dir(),
        );
        if( !function_exists('imagecreatefromjpeg')||
            !function_exists('imagecreatefromgif')||
            !function_exists('imagecreatefrompng')||
            !function_exists('imagecolorallocate')){
            $env['gd'] = false;
        }
        $pathWrt = true;
		$pathList = array(BASIC_PATH, DATA_PATH, DATA_PATH.'system');
        foreach ($pathList as $value) {
            if(!path_writeable($value)) $pathWrt = false;
            break;
        }
        $env['path_writable'] = $pathWrt ? $pathWrt : rtrim(BASIC_PATH, '/');
        show_json($env);
    }

    /**
     * 数据库、管理员账号提交
     *
     * @return void
     */
    public function save(){
        $action = Input::get('action', 'in', null, array('db', 'user'));
        if ($action == 'db') {
            $this->saveDb();
        } else {
            $this->saveUser();
        }
    }

    /**
     * 1. 数据库配置
     */
    private function saveDb(){
        // 1.1 获取db配置信息
        $data = $this->dbConfig();
        if (isset($dat['code']) && !$data['code']) return $data;
        $dbName = $data['DB_NAME'];
        $cacheType = Input::get('cacheType', 'in', null, array('file', 'redis', 'memcached'));

        // 1.2 连接数据库
        // 如果用include引入配置文件，$config的值会是上次请求的（？），所以直接用$data赋值
        $GLOBALS['config']['database'] = $data;
        
        // $GLOBALS['config']['cache']['sessionType'] = $cacheType;
        // $GLOBALS['config']['cache']['cacheType'] = $cacheType;
        think_config($GLOBALS['config']['databaseDefault']);
        think_config($GLOBALS['config']['database']);

        if($this->dbType == 'mysql'){
            // mysql连接，先不指定数据库，配置错误时会报错
            $GLOBALS['config']['database']['DB_NAME'] = '';
            think_config($GLOBALS['config']['database']);
            $db = Model()->db();
            $dbexist = $db->execute("show databases like '{$dbName}'");
        }
        $GLOBALS['config']['database']['DB_NAME'] = $dbName;    // 避免auto调用时后续取该值为空（同一进程）

        // 1.3 检测缓存配置
        // 判断所需缓存配置是否有效——redis、memcached
        if(in_array($cacheType, array('redis', 'memcached'))){
            if(!extension_loaded($cacheType)){
                return show_json(sprintf(LNG('common.env.invalidExt'), "[php-{$cacheType}]"), false);
            }
            $host = Input::get("{$cacheType}Host", 'require');
            $port = Input::get("{$cacheType}Port", 'require');

            $type = ucfirst($cacheType);
            $handle = new $type();
            try{
                if($cacheType == 'redis') {
                    $handle->connect($host, $port, 1);
                    $auth = Input::get('redisAuth');
                    if ($auth) $handle->auth($auth);
                    $conn = $handle->ping();
                }else{
                    $conn = $handle->addServer($host, $port);
                    if($conn && !$handle->getStats()) $conn = false;
                }
                if(!$conn) return show_json(sprintf(LNG('admin.install.cacheError'),"[{$cacheType}]"), false);
            }catch(Exception $e){
                $msg = sprintf(LNG('admin.install.cacheConnectError'),"[{$cacheType}]");
                $msg .= '<br/>'.$e->getMessage();
                return show_json($msg, false);
            }
        }

        // 1.4 创建数据库
        if($this->dbType == 'mysql'){
            if(!$dbexist){
                // host=localhost时，root空密码默认没有权限，会创建失败
                $db->execute("create database `{$dbName}`");
            }
            $db->execute("use `{$dbName}`");
            $tables = $db->getTables($dbName);
            if (!empty($tables)) {
                if(!isset($this->in['del']) || $this->in['del'] != '1'){
                    return show_json(LNG('admin.install.ifDelDb'), false, 10001);
                }
                // 删除数据表
                foreach($tables as $table) {
                    if ($table) {
                        $table = strtolower($table);
                    } else {continue;}
                    $db->execute("drop table if exists `{$table}`");
                }
            }
        }else{
            $db = Model()->db();
        }

        // 1.5 写入配置文件：数据库、缓存
        $data['DB_NAME'] = $dbName;
        $cache = array('type' => $cacheType);
        if (isset($host) && isset($port)) {
            $cache['host'] = $host;
            $cache['port'] = $port;
            if (!empty($auth)) $cache['auth'] = $auth;
        }
        $this->setUserSetting($data, $cache);

        // 1.6 创建数据表
        $res = $this->createTable($db);
        if (isset($res['code']) && !$res['code']) return $res;
        return show_json(LNG('explorer.success'));
    }
    private function sqliteFilter($content) {
		$replaceFrom = "'DB_NAME' => '".USER_SYSTEM;
		$replaceTo   = "'DB_NAME' => USER_SYSTEM.'";
		$replaceFrom2= "'DB_DSN' => 'sqlite:".USER_SYSTEM;
		$replaceTo2  = "'DB_DSN' => 'sqlite:'.USER_SYSTEM.'";
		$content = str_replace($replaceFrom,$replaceTo,$content);
		$content = str_replace($replaceFrom2,$replaceTo2,$content);
		return $content;
	}

    // 写入配置文件setting_user.php
    private function setUserSetting($data, $cache){
        if($data['DB_TYPE'] == 'pdo'){
            $dbDsn = explode(':', $data['DB_DSN']);
            if($dbDsn[0] == 'mysql'){
                $data['DB_DSN'] .= ';dbname=' . $data['DB_NAME'];
            }
        }
        $database = var_export($data, true);
        $ccType = $cache['type'];
        $text = array(
            "<?php ",
            "\$config['database'] = {$database};",
            "\$config['cache']['sessionType'] = '{$ccType}';",
            "\$config['cache']['cacheType'] = '{$ccType}';"
        );
        if(isset($cache['host'])){
            $text[] = "\$config['cache']['{$ccType}']['host'] = '".$cache['host']."';";
            $text[] = "\$config['cache']['{$ccType}']['port'] = '".$cache['port']."';";
            if (isset($cache['auth'])) {
                $text[] = "\$config['cache']['{$ccType}']['auth'] = '".$cache['auth']."';";
            }
        }
        $file = $this->userSetting;
        if(!@file_exists($file)) @touch($file);
        $content = file_get_contents($file);
        $pre = '';
        if(stripos(trim($content),"<?php") !== false) {
            $pre = PHP_EOL;
            unset($text[0]);
        }
        $content = implode(PHP_EOL, $text);
        if($this->dbType == 'sqlite') {
            $content = $this->sqliteFilter($content);
        }
        if(!file_put_contents($file,$pre.$content, FILE_APPEND)) {
            $msg = LNG('admin.install.dbSetError');
            $tmp = explode('<br/>', LNG('common.env.pathPermissionError'));
            $msg .= "<br/>" . $tmp[0];
            return show_json($msg, false, 10000);
        }
    }

    /**
     * 创建数据表
     * @param [type] $db
     * @return void
     */
    private function createTable($db){
        $dbFile = INSTALL_PATH . "data/{$this->dbType}.sql";
		if (!@file_exists($dbFile)) {
			return show_json(LNG('admin.install.dbFileError'), false);
		}
		$content = file_get_contents($dbFile);
		if($this->dbType == 'mysql') {
		    if(!empty($this->engine) && $this->engine == 'innodb') {
		        $content = str_ireplace('MyISAM', 'InnoDB', $content);
		    }
            // fulltext索引兼容
            $res = $db->query('select VERSION() as v');
            $mysqlVersion = floatval(($res[0] && isset($res[0]['v'])) ? $res[0]['v'] : 0);
			if($mysqlVersion && $mysqlVersion >= 5.7){
				// 删除索引不存在时报错; 需手动处理;
				//$content .= "\n".file_get_contents(INSTALL_PATH."data/fulltext.sql");
			}else{
				$content = str_ireplace('FULLTEXT ','', $content);
			}
		}
		$sqlArr = sqlSplit($content);
		foreach($sqlArr as $sql){
			$db->execute($sql);
		}
    }

    /**
     * 获取数据库配置信息
     */
    private function dbConfig(){
        $this->dbType = $dbType = Input::get('dbType', 'in', null, array('sqlite', 'mysql', 'pdo'));
        $dbList = $this->dbList();

        $init = array('db_type' => $dbType);
        if($dbType == 'mysql'){
            $data = Input::getArray(array(
                'dbHost'    => array('aliasKey' => 'db_host', 'check'   => 'require'),
                'db_port'   => array('default'  => 3306),
                'dbUser'    => array('aliasKey' => 'db_user', 'check'   => 'require'),
                'dbPwd'     => array('aliasKey' => 'db_pwd', 'check'    => 'require', 'default' => ''),
                'dbName'    => array('aliasKey' => 'db_name', 'check'   => 'require'),
            ));
            $this->execPort($data);
            $dbType = in_array('mysqli', $dbList) ? 'mysqli' : 'mysql';
            $this->engine = Input::get('dbEngine', null, 'myisam');
        }else if($dbType == 'pdo'){
            $this->dbType = $pdoType = Input::get('pdoType', 'in', null, array('sqlite', 'mysql'));
            if($pdoType == 'mysql'){
                $data = Input::getArray(array(
                    'db_dsn'    => array('default'  => ''),
                    'pdoDbHost' => array('aliasKey' => 'db_host', 'check'   => 'require'),
                    'db_port'   => array('default'  => 3306),
                    'pdoDbUser' => array('aliasKey' => 'db_user', 'check'   => 'require'),
                    'pdoDbPwd'  => array('aliasKey' => 'db_pwd', 'check'    => 'require', 'default' => ''),
                    'pdoDbName' => array('aliasKey' => 'db_name', 'check'   => 'require'),
                ));
                $this->execPort($data);
                $data['db_dsn'] = "mysql:host={$data['db_host']}";
                $this->engine = Input::get('pdoDbEngine', null, 'myisam');
            }else{
                $data['db_dsn'] = "sqlite:" . $this->sqliteDbFile();
            }
            $dbType .= '_' . $pdoType;
        }else{
            $dbType = in_array('sqlite3', $dbList) ? 'sqlite3' : 'sqlite';
            $data = array('db_name' => $this->sqliteDbFile());
        }
        if(!in_array($dbType, $dbList)){
            if(stripos($dbType, 'sqlite') === 0 || 
            (isset($data['db_dsn']) && stripos($data['db_dsn'], 'sqlite') === 0)) {
                del_file($data['db_name']);
            }
            return show_json(sprintf(LNG('admin.install.dbTypeError'),$dbType), false);
        }
        if($init['db_type'] != 'pdo') $init['db_type'] = $dbType;

        return array_change_key_case(array_merge($init, $data, array(
            'DB_SQL_LOG'            => true,		// SQL执行错误日志记录	
            'DB_FIELDS_CACHE'       => true,		// 启用字段缓存
            'DB_SQL_BUILD_CACHE'	=> false,		// sql生成build缓存
        )), CASE_UPPER);
    }
    private function execPort(&$data) {
        $host = explode(':', $data['db_host']);
        if(isset($host[1])) {
            $data['db_host'] = $host[0];
            $data['db_port'] = $host[1];
        }
    }

    /**
     * sqlite database文件
     */
    private function sqliteDbFile(){
        @chmod(DATA_PATH, 0755);
        $dbFile = USER_SYSTEM . rand_string(12) . '.php';
        @touch($dbFile);
        return $dbFile;
    }

    /**
     * 支持的数据库类型列表
     * @return void
     */
    private function dbList(){
        $data = array('sqlite', 'sqlite3', 'mysql', 'mysqli', 'pdo_sqlite', 'pdo_mysql');
        $list = array_map(function($type){
            if (extension_loaded($type)){
                return $type;
            }
        }, $data);
        return array_filter($list);
    }

    /**
     * 2. 账号设置
     * @return void
     */
    private function saveUser(){
        $data = Input::getArray(array(
            'name'      => array('check' => 'require'),
            'password'  => array('check' => 'require')
        ));
        $this->checkDbInit();

        $userID = 1;
        if(Model('User')->find($userID)) {
            if(!Model('User')->userEdit($userID, $data)) {
                show_json(LNG('user.bindUpdateError'), false);
            }
            @touch($this->installLock);
            show_json(LNG('admin.install.updateSuccess'), true, $userID);
        }
        $this->admin = $data;
        $this->sysInit();
    }
    // （检查）数据库初始化
    private function checkDbInit(){
        think_config($GLOBALS['config']['databaseDefault']);
        // think_config($GLOBALS['config']['database']);
        $data = $GLOBALS['config']['database'];
        // 获取数据库类型，配置数据库信息（不指定数据库）
        $type = $data['DB_TYPE'];
        $dbName = $data['DB_NAME'];
        switch ($data['DB_TYPE']) {
            case 'pdo':
                $dsn = explode(':', $data['DB_DSN']);
                $type = $dsn[0];
                $dsn = explode(';', $data['DB_DSN']);
                $GLOBALS['config']['database']['DB_DSN'] = $dsn[0];  // 去掉数据库名
                break;
            case 'mysql':
            case 'mysqli':
                $type = 'mysql';
                $GLOBALS['config']['database']['DB_NAME'] = '';
                break;
            case 'sqlite3':
                $type = 'sqlite';
                break;
        }
        think_config($GLOBALS['config']['database']);
        // 判断数据库（表）是否存在
        $db = Model()->db();
        if ($type != 'sqlite') {
            $exist = $db->execute("show databases like '{$dbName}'");
            if (!$exist) show_json(LNG('ERROR_DB_NOT_EXIST'), false);
            $db->execute("use `{$dbName}`");
        }
        // sqlite可直接调用该方法，无论库文件是否存在
        $tables = $db->getTables();
        if (empty($tables) || !in_array('user', $tables)) {
            $msg = $type == 'sqlite' ? 'dbError' : 'dbTableError';
            show_json(LNG('admin.install.'.$msg), false);
        }
        // 重新配置数据库信息
        $GLOBALS['config']['database'] = $data;
        think_config($GLOBALS['config']['database']);
    }

    /**
     * 初始化数据
     */
    public function sysInit(){
        define('USER_ID',1);
        Cache::deleteAll();
        $this->systemDefault();
		$this->storageDefault();
        $this->initLightApp();
        $this->initPluginList();

        $this->addGroup();
        $this->addAuth();
        $this->roleID = $this->getRoleID();
		$this->addUser();
		KodIO::initSystemPath();

        @touch($this->installLock);
        show_json(LNG('admin.install.createSuccess'), true);
    }

    /**
     * 系统默认设置
     */
    public function systemDefault(){
        $default = $this->config['settingSystemDefault'];
        $res = Model('SystemOption')->set($default);
        
        if(!$res) show_json(LNG('admin.install.defSetError'), false);
    }
    /**
     * 默认存储配置
     */
    public function storageDefault(){
        $driver = KodIO::defaultDriver();
        if($driver) return $driver['id'];

        $dataPath = './data/files/';
        if(!is_dir($dataPath)) @mk_dir($dataPath);
        $freeSize = @disk_free_space($dataPath);
        $freeSize = $freeSize ? floor($freeSize / 1024 / 1024 / 1024) : 0;
        $freeSize = $freeSize > 10 ? floor($freeSize / 10) * 10 : 10;
        $data = array (
            'name' => LNG('admin.storage.localStore'),
            'sizeMax' => $freeSize,
            'driver' => 'Local',
            'default' => '1',
            'system' => '1',
            'config' => json_encode(array(
                "basePath" => $dataPath
            )),
        );
        $res = Model('Storage')->add($data);
        if(!$res) show_json(LNG('admin.install.defStoreError'), false);
	}
	
    /**
     * 轻应用列表初始化
     */
    public function initLightApp(){
		Action('explorer.lightApp')->initApp();
    }

    /**
     * 初始化插件列表
     */
    public function initPluginList(){
        Model('Plugin')->viewList();
        $list = Model('Plugin')->loadList();
        foreach($list as $app => $item) {
            Model('Plugin')->changeStatus($app, 1);
        }
    }

    /**
     * 添加根部门
     */
    public function addGroup(){
        $this->in = array(
            "groupID"   => 1,
            "name" 		=> $this->config['settingSystemDefault']['groupRootName'],
			"sizeMax" 	=> 0,
			"parentID"	=> 0,
        );
        $res = ActionCallHook('admin.group.add');
        if(!$res['code']){
            show_json(LNG('admin.install.defGroupError'), false);
        }
    }

    /**
     * 文档权限
     */
    public function addAuth(){
        Model('Auth')->initData();
    }

    /**
     * 系统内置角色
     */
    public function getRoleID(){
        $list = Model('SystemRole')->listData();
        $roleList = array_to_keyvalue($list, 'administrator', 'id');

        $administrator = 1;
        if(!isset($roleList[$administrator])){
            $roleID = $this->roleDefault();
            if(!$roleID) show_json(LNG('admin.install.defRoleError'), false);
        }else{
            $roleID = $roleList[$administrator];
        }
        return $roleID;
    }

    /**
     * 添加角色——Administrator、default
     */
    private function roleDefault(){
        $administrator = array (
            'name' => LNG('admin.role.administrator'),
            'display' => 1,
            'system' => 1,
            'administrator' => 1,
            'auth' => 'explorer.add,explorer.upload,explorer.view,explorer.download,explorer.share,explorer.shareLink,explorer.remove,explorer.edit,explorer.move,explorer.serverDownload,explorer.search,explorer.unzip,explorer.zip,user.edit,user.fav,admin.index.dashboard,admin.index.setting,admin.index.loginLog,admin.index.log,admin.index.server,admin.role.list,admin.role.edit,admin.job.list,admin.job.edit,admin.member.list,admin.member.userEdit,admin.member.userAuth,admin.member.groupEdit,admin.auth.list,admin.auth.edit,admin.plugin.list,admin.plugin.edit,admin.storage.list,admin.storage.edit,admin.autoTask.list,admin.autoTask.edit',
            'label' => 'label-green-deep',
            'sort' => 2,
        );
        $groupOwner = array (
            'name' => LNG('admin.role.group'),
            'display' => 1,
            'system' => 1,
            'auth' => 'explorer.add,explorer.upload,explorer.view,explorer.download,explorer.share,explorer.shareLink,explorer.remove,explorer.edit,explorer.move,explorer.serverDownload,explorer.search,explorer.unzip,explorer.zip,user.edit,user.fav,admin.index.loginLog,admin.index.log,admin.member.list,admin.member.userEdit,admin.member.userAuth,admin.member.groupEdit,admin.auth.list',
            'label' => 'label-blue-deep',
            'sort' => 1,
        );
        $defaultUser = array (
            'name' => LNG('admin.role.default'),
            'display' => 1,
            'system' => 1,
            'auth' => 'explorer.add,explorer.upload,explorer.view,explorer.download,explorer.share,explorer.shareLink,explorer.remove,explorer.edit,explorer.move,explorer.serverDownload,explorer.search,explorer.unzip,explorer.zip,user.edit,user.fav',
            'label' => 'label-blue-normal',
            'sort' => 0,
        );
		Model('SystemRole')->add($defaultUser);
		Model('SystemRole')->add($groupOwner);
        $administrator = Model('SystemRole')->add($administrator);
        return $administrator;
    }

    /**
     * 新增用户-管理员
     */
    public function addUser(){
        $this->in = array(
            "userID"    => 1,
			"name" 		=> !empty($this->admin['name']) ? $this->admin['name'] : 'admin',
			"nickName" 	=> LNG('admin.role.administrator'),
			"password" 	=> !empty($this->admin['password']) ? $this->admin['password'] : 'admin',
            "roleID"	=> $this->roleID,
            "groupInfo" => json_encode(array('1'=>'1')),
			"sizeMax" 	=> 0,
        );
        $res = ActionCallHook('admin.member.add');
        if(!$res) return;
        if(!$res['code']){
            show_json($res['data'], false);
        }
    }
}