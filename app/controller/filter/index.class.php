<?php
/*
 * @link http://kodcloud.com/
 * @author warlee | e-mail:kodcloud@qq.com
 * @copyright warlee 2014.(Shanghai)Co.,Ltd
 * @license http://kodcloud.com/tools/license/license.txt
 */

/**
 * 动作hook;
 * 
 * 注意: 事件绑定需要在事件触发之前;(之后会造成触发时没有回调情况)
 * 1. 插件注册bind事件: 在pluginModel初始化之后依次绑定  eg:LDAP  @ user.index.loginsubmit.before
 * 2. 插件中trigger的事件: 需要在pluginModel之前绑定好;  eg:webdav@ user.index.userInfo)
 */
class filterIndex extends Controller{
	function __construct() {
		parent::__construct();
	}
	public function bind(){
		Action("filter.userRequest")->bind();
		Action("filter.userCheck")->bind();
		Action("filter.attachment")->bind();
		Action("filter.html")->bind();
		Action("filter.template")->bind();
	}

	public function trigger(){
		Action("filter.post")->check();
		Action("filter.userGroup")->check();
		Action("filter.limit")->check();
		Action("explorer.seo")->check();
		
		Hook::trigger(strtolower(ACTION).'.before',array());
		Hook::bind('show_json',array($this,'eventAfter'));
	}
	public function eventAfter($data){
		if(!$data['code']) return $data;
		$action = strtolower(ACTION).'.after';
		if($action == 'user.view.options.after') return $data;//手动调用过

		$returnData = Hook::trigger($action,$data);
		return is_array($returnData) ? $returnData:$data;
	}
}
