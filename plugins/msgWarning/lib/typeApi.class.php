<?php

/**
 * 通知方式
 */
class typeApi {
	protected $plugin;
	protected $pluginName;
	function __construct($plugin) {
		$this->plugin = $plugin;
		$this->pluginName = $plugin->pluginName;
	}

    public function getAppConfig($key=false, $def=null){
		$config = $this->plugin->getConfig();
		return isset($key) ? _get($config, $key, $def) : $config;
	}
	public function setAppConfig($value){
		$this->plugin->setConfig($value);
	}
	
	/**
	 * 获取通知方式列表
	 * @return void
	 */
	public function listData() {
		return $this->get(array(), true);
	}

	/**
	 * 通知方式列表
	 * @return void
	 */
	public function get($req, $ret=false){
        require_once(__DIR__ .'/data/ntc.type.php');
        $list = NtcType::listData();

		// 查询状态，覆盖更新
		foreach ($list as &$item) {
            $type = $item['type'];
			if (in_array($type, array('ktips', 'kwarn'))) continue;
			$this->typeUpdate($type, $item);
		}
		if ($ret) return $list;
		show_json(array('list'=>$list));
	}

	// 通知方式项检测更新
	private function typeUpdate($type, &$item){
		switch ($type) {
			case 'email':
				$this->checkEmail($item);
				break;
			case 'sms':
				$this->checkSms($item);
				break;
			case 'weixin':
			case 'dding':
				$this->check3rdMsg($type, $item);
                if ($item['data'] == '1') {
                    $list = $this->getAppConfig('ntcTypeList', array());
                    $item['status'] = intval($list[$type]);
                }
				break;
			default: break;
		}
	}

	// 检查邮箱
	private function checkEmail(&$item){
		$model = Model('systemOption');
		$type = $model->get('emailType');
		if ($type != '1') return;
		$info = $model->get('email');
		if (empty($info)) return;
		$stat = 1;
		foreach (array('smtp','host','email','password') as $key) {
			if (empty($info[$key])) {
				$stat = 0; break;
			}
		}
		$item['data'] = $stat;
	}

	// 检查短信；后续考虑增加自定义模板以适应自定义通知事件
	private function checkSms(&$item){
		if (Model('SystemOption')->get('versionType') == 'A') return;
		$item['status'] = 1; // 默认启用
		$plugin = Model('Plugin')->loadList('smsGateway');
		if (!$plugin || $plugin['status'] != '1') return;

		$data = array('aliSecretId', 'aliSecretKey', 'aliSignName', 'aliTplCode', 'length');
		if (_get($plugin, 'config.type', 'ali') == 'tx') {
			$data = array('txAppID', 'txSecretId', 'txSecretKey', 'txSignName', 'txTplCode', 'length');
		}
		$stat = 1;
		foreach ($data as $key) {
			if (empty($plugin['config'][$key])) {
				$stat = 0; break;
			}
		}
		$item['data'] = $stat;
	}

	// 检查第三方同步消息（钉钉、企业微信）；依赖msgGateway插件，后续可以整合到此（同时调整调用处）
	private function check3rdMsg($type, &$item){
	    if (Model('SystemOption')->get('versionType') == 'A') return;
		$plugin = Model('Plugin')->loadList('msgGateway');
		if (!$plugin || $plugin['status'] != '1') return;

		if (_get($plugin, 'config.isOpen', 0) != '1') return;
		$appList = array(
			'weixin'=> 'weChat',
			'dding'	=> 'dingTalk', 
		);
		if (_get($plugin, 'config.type') != $appList[$type]) return;
		$item['data'] = 1;
		// $item['status'] = 1;	// 前端启/禁用
	}

	/**
	 * 通知方式操作
	 * @return void
	 */
	public function action ($req) {
		$action = $req['action'];
		switch ($action) {
			case 'getConfig':
				$this->getConfig($req);
				break;
			case 'setConfig':
				$this->setConfig($req);
				break;
			default:break;
		}
        show_json(LNG('common.illegalRequest'), false);
	}

	/**
	 * 通知方式配置信息获取——仅邮件
	 * @return void
	 */
	public function getConfig($req) {
		if ($req['type'] != 'email') {
            show_json(LNG('explorer.share.errorParam'), false);
        }
		$emailType = Model('systemOption')->get('emailType');
		$email = Model('systemOption')->get('email');
		$data = array('type' => intval($emailType));
		if (!$email) $email = array();
		$data = array_merge($data, $email);
		show_json($data);
	}
	/**
	 * 通知方式配置信息保存
	 * @return void
	 */
	public function setConfig($req) {
        $type = $req['type'];
        $data = json_decode($req['data'],true);
        if (!in_array($type, array('sms','email','weixin','dding')) || empty($data)) {
            show_json(LNG('explorer.share.errorParam'), false);
        }
		// 邮件、短信
		if ($type == 'sms') show_json(LNG('explorer.success'));
		if ($type == 'email') {
			Model('systemOption')->set('emailType', $data['type']);
			unset($data['type'],$data['tested']);
			Model('systemOption')->set('email', $data);
			show_json(LNG('explorer.success'));
		}
		// 企业微信、钉钉
		$status = intval($data['status']);
		$item = array('status'=>$status);
		if ($status === 1) {
			$this->check3rdMsg($type, $item);
			if ($item['data'] != 1) {
				show_json(LNG('msgWarning.type.setAppFirst'), false);
			}
		}
        $list = $this->getAppConfig('ntcTypeList', array());
        $list[$type] = $status;
        $this->setAppConfig(array('ntcTypeList'=>$list));
		show_json(LNG('explorer.success'), true);
	}

}