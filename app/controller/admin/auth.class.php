<?php
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/

//权限组管理
class adminAuth extends Controller{
	private $model;
	function __construct()    {
		parent::__construct();
		$this->model = Model('Auth');
	}

	/**
	 * 根据所在部门获取用户列表
	 */
	public function get() {
		$result = $this->model->listData();
		show_json($result,true);
	}

	/**
	 * 添加用户
	 */
	public function add() {
		$data = Input::getArray(array(
			"name" 		=> array("check"=>"require"),
			"display" 	=> array("check"=>"int","default"=>0),
			"auth" 		=> array("check"=>"int"),
			"label" 	=> array("check"=>"require"),
		));
		$res = $this->model->add($data);
		$msg = $res ? LNG('explorer.success') : LNG('explorer.error') . '! ' . LNG('explorer.pathExists');
		show_json($msg,!!$res);
	}

	/**
	 * 编辑 
	 */
	public function edit() {
		$data = Input::getArray(array(
			"id"		=> array("check"=>"int"),
			"name" 		=> array("check"=>"require","default"=>null),
			"display" 	=> array("check"=>"int","default"=>null),
			"auth" 		=> array("check"=>"int","default"=>null),
			"label" 	=> array("check"=>"require","default"=>null),
			// "sort" 		=> array("check"=>"require","default"=>0),
		));
		$res = $this->model->update($data['id'],$data);
		$msg = $res ? LNG('explorer.success') : LNG('explorer.error') . '! ' . LNG('explorer.pathExists');
		return show_json($msg,!!$res);
	}

	/**
	 * 删除
	 */
	public function remove() {
		$id = Input::get('id','int');
		// 判断是否被使用
		$cnt1 = Model('SourceAuth')->where(array('authID' => $id))->count();
		$cnt2 = Model('user_group')->where(array('authID' => $id))->count();
		$cnt = (int) $cnt1 + (int) $cnt2;
		if($cnt) show_json(LNG('admin.auth.delErrTips'), false);
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
}
