<?php 

/**
 * 消息预警
 */
class msgWarningPlugin extends PluginBase{
	function __construct(){
		parent::__construct();
	}
	public function regist(){
		$this->hookRegist(array(
			'user.commonJs.insert' => 'msgWarningPlugin.echoJs',
		));
	}
	public function echoJs(){
		$this->echoFile('static/main.js');
	}

    // 切换状态——更新计划任务
	public function onChangeStatus($status){
		$config = $this->getConfig();
		$this->task()->updateTask($status, $config);
	}
	// 保存配置——更新计划任务
	public function onSetConfig($config){
		$status = 1;
		$this->task()->updateTask($status, $config);
	}
	// 卸载插件——删除计划任务
	public function onUninstall(){
		$this->task()->delTask();
	}
	// 与本插件相关联的功能
	public function task(){
		return Action($this->pluginName . "Plugin.task.index");
	}

    /**
     * 预警消息发送-计划任务
     * @return void
     */
    public function warning(){
        return $this->task()->warning();
    }

    /**
	 * 获取有效的发送方式列表
	 * @return void
	 */
	public function sendType(){
		$data = array (
			'dingTalk'	=> 0,
			'weChat'	=> 0,
			// 'sms'		=> 0,
			'email'		=> 1,	// 邮件默认可用
		);
		$plugin = Model('Plugin')->loadList('msgGateway');
		if ($plugin['status'] == '1' && $plugin['config']['isOpen'] == '1') {
			$data[$plugin['config']['type']] = 1;
		}
		// $plugin = Model('Plugin')->loadList('smsGateway');
		// if ($plugin['status'] == '1') {
		// 	$data['sms'] = 1;
		// }
		show_json($data);
	}


    /**
     * KOD默认存储使用情况
     * @return void
     */
    public function defaultDriver(){
        $key    = $this->pluginName .'_'. __FUNCTION__;
        $cache  = Cache::get($key);
        if ($cache) return $cache;

        $driver = KodIO::defaultDriver();
        // 检查存储是否正常
        $driver['config'] = json_encode($driver['config']);
        $check = Model('Storage')->checkConfig($driver, true);
        if ($check !== true) return false;

		// 默认为本地存储，且大小不限制，则获取所在磁盘的实际大小
		if(strtolower($driver['driver']) == 'local' && $driver['sizeMax'] == '0') {
			$path = realpath($driver['config']['basePath']);
			$data = $this->driverInfo($path);
            if (!$data) return false;
		} else {
            $sizeUse = Model('File')->where(array('ioType' => $driver['id']))->sum('size');
            $data = array(
                'sizeMax'   => floatval($driver['sizeMax']) * 1024 * 1024 * 1024,
                'sizeUse'   => floatval($sizeUse)
            );
        }
        Cache::set($key, $data, 60*3);
        return $data;
    }
    /**
     * 服务器系统(盘)存储使用情况
     * @return void
     */
    public function systemDriver(){
        $key    = $this->pluginName .'_'. __FUNCTION__;
        $cache  = Cache::get($key);
        if ($cache) return $cache;
        $data   = $this->driverInfo();
        if (!$data) return false;
        Cache::set($key, $data, 60*3);
        return $data;
    }

    /**
     * 根据路径获取磁盘使用情况
     * @param string $path
     * @return void
     */
    private function driverInfo($path = '') {
        $data = array();
        // 未指定路径，获取系统盘
        if (!$path) {
            $path = $GLOBALS['config']['systemOS'] == 'windows' ? 'C:/' : '/';
            if (function_exists('exec')) {
                if ($path == '/') {
                    exec('df -k /', $out);
                } else {
                    exec('wmic logicaldisk get caption,freespace,size', $out);
                }
                if (!empty($out[1])) {
                    $line = array_values(array_filter(explode(' ', $out[1])));
                    if ($path == '/') {
                        $sizeMax    = intval($line[1]) * 1024;
                        $sizeFree   = intval($line[3]) * 1024;
                    } else {
                        // $path       = $line[0].'/';
                        $sizeMax    = intval($line[2]);
                        $sizeFree   = intval($line[1]);
                    }
                    $data['sizeMax'] = $sizeMax;
                    $data['sizeUse'] = $sizeMax - $sizeFree;
                    return $data;
                }
            }
            if (!file_exists($path)) return false;  // 系统盘不检查写权限
        } else {
            if(!file_exists($path) || !path_writeable($path)) return false;
        }
        $data['sizeMax'] = $sizeMax = @disk_total_space($path);
        $data['sizeUse'] = $sizeMax - @disk_free_space($path);
        return $data;
    }

    // 待提醒消息详情
    public function message($ret = false){
        $user = Session::get('kodUser');
        if (!$user || _get($GLOBALS,'isRoot') != 1) {
            if ($ret) return false;
            show_json(LNG('msgWarning.main.msgSysOK'));
        }

        $data = array(
            'user'  => array(),     // 账号：email/pass
            'disk'  => array(),     // 存储：系统盘、存储盘空间使用
            'raid'  => array()      // raid：硬件异常信息
        );
        // 1.账号信息
        // 一体机检查初始密码——安装了cockpit才有此项
        $kptAcc = Session::get('oemCockpitPluginAccount');  // cockpit account
        if ($kptAcc) {
            if (isset($kptAcc['password']) && $kptAcc['password'] == 'admin') {
                $data['user'][] = LNG('msgWarning.main.msgPwdErr');
            }
        }
        if (empty($user['email'])) {
            $data['user'][] = LNG('msgWarning.main.msgEmlErr');
        }
        if (!empty($data['user'])) {
            $style = '';
            $setLink = '<a style="'.$style.'padding:0px;text-decoration:none;" link-href="#setting/user/account">'.LNG('msgWarning.main.setNow').'</a >';
            $data['user'][count($data['user'])-1] = end($data['user']) . $setLink;
        }

        // 2.磁盘空间
        $sysDriver = $this->systemDriver();     // 系统盘
        $defDriver = $this->defaultDriver();    // 默认存储
        // if (!$sysDriver) {   // 系统路径异常时不提示
        //     $root = $GLOBALS['config']['systemOS'] == 'windows' ? 'C:/' : '/';
        //     $data['disk'][] = sprintf(LNG('msgWarning.main.msgSysPathErr'), $root);
        // }
        if ($sysDriver) {
            $sizeFree = ($sysDriver['sizeMax'] - $sysDriver['sizeUse']);
            if ($sizeFree < 1024*1024*1024*10) {    // 暂时固定为10GB
                $size = size_format($sizeFree);
                $data['disk'][] = sprintf(LNG('msgWarning.main.msgSysSizeErr'), $size);
            }
        }
        if (!$defDriver) {
            $data['disk'][] = sprintf(LNG('msgWarning.main.msgDefPathErr'), APP_HOST.'#admin/storage/index');
        } else {
            $sizeFree = ($defDriver['sizeMax'] - $defDriver['sizeUse']);
            if ($sizeFree < 1024*1024*1024*10) {
                $size = size_format($sizeFree);
                $data['disk'][] = sprintf(LNG('msgWarning.main.msgDefSizeErr'), $size);
            }
        }

        // 3.raid异常
        if ($kptAcc) {
            // TODO 获取raid异常信息，多条以数组形式返回
        }

        if ($ret) return $data;
        show_json($data);
    }

}