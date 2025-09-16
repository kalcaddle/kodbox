<?php 
/**
 * 通知事件——协同办公类
 */
class msgWarningMsgColl extends Controller {
	public function __construct() {
		parent::__construct();
		$this->pluginName = 'msgWarningPlugin';
    }

	public function index($evntInfo) {
		return array();
	}

}
