<?php

//通知管理
class adminNotice extends Controller{
	private $model;
	function __construct()    {
		parent::__construct();
		$this->model = Model('SystemNotice');
	}

	/**
	 * 获取列表
	 */
	public function get() {
		$result = $this->model->listData();
		show_json($result,true);
	}

	/**
	 * 添加
	 */
	public function add() {
		$data = Input::getArray(array(
			"name" 		=> array("check"=>"require"),
			"content" 	=> array("check"=>"require"),
			"auth" 		=> array("check"=>"require"),
			"mode" 		=> array("check"=>"require"),
			"time" 		=> array("check"=>"require"),
			"type" 		=> array("default"=>"1"),	// 通知类型，1:系统通知；2:活动通知
			"level" 	=> array("default"=>"0"),	// 提示级别，0:弱提示；1:强提示
			'enable'	=> array('default'=>'0'),		// 是否启用
		));
		$res = $this->model->add($data);
		$msg = $res ? LNG('explorer.success') : LNG('explorer.error') . '! ' . LNG('explorer.pathExists');
		return show_json($msg,!!$res, $res);
	}

	/**
	 * 编辑 
	 */
	public function edit() {
		$data = Input::getArray(array(
			"id"		=> array("check"=>"int"),
			"name" 		=> array("check"=>"require"),
			"content" 	=> array("check"=>"require"),
			"auth" 		=> array("check"=>"require"),
			"mode" 		=> array("check"=>"require"),
			"time" 		=> array("check"=>"require"),
			"type" 		=> array("default"=>"1"),
			"level" 	=> array("default"=>"0"),
			'enable'	=> array('default'=>'0'),		// 是否启用
		));
		$res = $this->model->update($data['id'],$data);
		$msg = $res ? LNG('explorer.success') : LNG('explorer.error') . '! ' . LNG('explorer.pathExists');
		show_json($msg,!!$res);
	}

	/**
	 * 删除
	 */
	public function remove() {
		$id  = Input::get('id','int');
		$res = $this->model->remove($id);
		$msg = $res ? LNG('explorer.success') : LNG('explorer.error');
		show_json($msg,!!$res);
	}

	// 移动排序、拖拽排序
	public function sort() {
		$ids = Input::get('ids', 'require');
		$ids = explode(',', $ids);
		foreach($ids as $i => $id) {
			$this->model->sort($id,array("sort"=> $i));
		}
		show_json(LNG('explorer.success'));
	}

	/**
	 * 启/禁用通知
	 */
	public function enable() {
		$data = Input::getArray(array(
			"id"    	=> array("check"=>"int"),
			"enable"	=> array("check"=>"bool"),
		));
		$res = $this->model->enable($data['id'],(bool)$data['enable']);
		$msg = !!$res ? LNG('explorer.success') : LNG('explorer.error');
		show_json($msg,!!$res);
	}
	// ----------------------- 用户通知 -----------------------

	// 检查是否具有接受某通知的权限
	private function authCheck($auth){
		if(!defined('USER_ID')) return false; //未登录
		if(_get($GLOBALS,'isRoot')) return true;
		return  ActionCall('user.authPlugin.checkAuthValue',$auth);
	}
	// 用户获取推送的通知列表
	public function noticeGet($id = false){
		// 根据id获取详情
		if($id) $this->noticeInfo($id);

		// 初始化用户通知列表
		$result = $this->model->listData(false, 'id');	// 通知列表，按id升序
		foreach($result as $value) {
			if(isset($value['enable']) && !$value['enable']) continue;	// 未启用
			if($value['time'] > time()) continue;	// 未到通知时间
			if(!$this->authCheck($value['auth'])) continue;	// 权限
			$this->model->userNoticeAdd($value);
		}
		// 获取列表，过滤已删除项
		$list = $this->model->userNoticeGet();
		foreach($list as $k => $value) {
			if($value['delete']) unset($list[$k]);
		}
		show_json($list);
	}
	// 用户通知详情
	public function noticeInfo($id){
		// 通知列表——先获取列表，避免被初始化为User.noticeList
		$result = $this->model->listData();
		if(!$result) show_json(array(), false);
		$list = array_to_keyvalue($result,'id');
		// 用户通知
		$notice = $this->model->userNoticeGet($id);
		if(!$notice) show_json(array(), false);

		$noticeID = $notice['noticeID'];
		if(!isset($list[$noticeID])) show_json(array(), false);
		$notice['content'] = $list[$noticeID]['content'];

		show_json($notice);
	}
	// 用户通知更新(已查看)
	public function noticeEdit($id = false){
		if(!$id) show_json(LNG('explorer.share.errorParam'), false);
		$update = array('status' => 1);
		$this->model->userNoticeEdit($id, $update);
		show_json(LNG('explorer.success'));
	}
	// 用户通知删除
	public function noticeRemove($id = false){
		$update = array('delete' => 1);
		if($id) { // 单个删除
			$this->model->userNoticeEdit($id, $update);
		}else{ // 清空全部
			$list = $this->model->userNoticeGet();
			foreach($list as $value) {
				if($value['delete'] == '1') continue;
				$this->model->userNoticeEdit($value['id'], $update);
			}
		}
		show_json(LNG('explorer.success'));
	}
}
