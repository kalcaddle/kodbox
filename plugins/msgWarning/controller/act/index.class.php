<?php 
/**
 * 通知后绑定的事件
 */
class msgWarningActIndex extends Controller {
	protected $pluginName;
	protected $evntInfo;
	public function __construct() {
		parent::__construct();
		$this->pluginName = 'msgWarningPlugin';
    }

	public function getAppConfig($key=false, $def=null){
		$config = Action($this->pluginName)->getConfig();
		return isset($key) ? _get($config, $key, $def) : $config;
	}
	public function setAppConfig($value){
		$this->plugin->setConfig($value);
	}

	/**
	 * 检查用户下载是否已超限制
	 * @return void
	 */
	public function fileDownload() {
		// Hook::bind('show_json', array($this, 'showErrJson'));

		if (defined('USER_ID') && USER_ID == '1') return;	// 超管不限
		$event = 'dataFileDownErr';
		// 是否开启了通知绑定事件（禁止下载）
		$list = $this->getAppConfig('ntcEvntList', array());
		$info = _get($list, $event, array());
		if (!$info) return;
		$open = _get($info, 'policy.doAction', 0);
		if ($open != '1') return;

		// 检查超限用户是否包含自己
		$cckey = $this->pluginName.'.dataFileDownErr.'.date('Ymd');
		$cache = Cache::get($cckey);
		if (empty($cache)) return;

		$ids = explode(',', $cache);
		if (in_array(USER_ID, $ids)) {
			show_json(LNG('msgWarning.evnt.downFileErr'), false);
		}
	}

	public function showErrJson($result) {
		// throw new Exception($result['data']);
		// exit;
		// write_log('下载show_json--------'.json_encode($result));
	}
}
