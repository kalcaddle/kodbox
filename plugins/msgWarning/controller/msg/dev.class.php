<?php 
/**
 * 通知事件——硬件资源类
 */
class msgWarningMsgDev extends Controller {
	public function __construct() {
		parent::__construct();
		$this->pluginName = 'msgWarningPlugin';
    }

	public function index($evntInfo) {
		return array();
	}


}
