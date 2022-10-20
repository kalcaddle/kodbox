<?php 
/**
 * 自动执行
 */
class adminAutoRun extends Controller {
	function __construct()    {
		parent::__construct();
	}

	public function index(){
        ActionCall('admin.log.hookBind');	// 绑定日志hook
    }
}