<?php 
/**
 * 通知事件——安全防护类
 */
class msgWarningMsgSafe extends Controller {
	public function __construct() {
		parent::__construct();
		$this->pluginName = 'msgWarningPlugin';
    }

	public function index($evntInfo) {
		return array();
	}

}
