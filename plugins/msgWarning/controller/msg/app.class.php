<?php 
/**
 * 通知事件——应用服务类
 */
class msgWarningMsgApp extends Controller {
	public function __construct() {
		parent::__construct();
		$this->pluginName = 'msgWarningPlugin';
    }

	public function index($evntInfo) {
		return array();
	}

}
