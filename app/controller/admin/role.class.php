<?php
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/

//权限组管理
class adminRole extends Controller{
	private $model;
	function __construct()    {
		parent::__construct();
		$this->model = Model('SystemRole');
	}

	/**
	 * 根据所在部门获取用户列表
	 */
	public function get() {
		$roleList = $this->model->listData();$result = array();
		$Action = Action('filter.UserGroup');
		foreach($roleList as $item){//过滤比自己权限小的项;
			if($Action->allowChangeUserRole($item['id'])){$result[] = $item;}
		}
		show_json($result,true);
	}

	/**
	 * 添加用户
	 */
	public function add() {
		$data = Input::getArray(array(
			"name" 		=> array("check"=>"require"),
			"display" 	=> array("check"=>"int","default"=>null),
			"auth" 		=> array("check"=>"require"),
			"label" 	=> array("check"=>"require","default"=>null),
			"desc"		=> array("default"=>''),
			"ignoreExt" => array("check"=>"require","default"=>''),
			"ignoreFileSize" => array("check"=>"require","default"=>''),
		));
		$res = $this->model->add($data);
		$msg = $res ? LNG('explorer.success') : LNG('explorer.error') . '! ' . LNG('explorer.pathExists');
		return show_json($msg,!!$res, $res);	// $res=>$id
	}

	/**
	 * 编辑 
	 */
	public function edit() {
		$data = Input::getArray(array(
			"id"		=> array("check"=>"int"),
			"name" 		=> array("check"=>"require","default"=>null),
			"display" 	=> array("check"=>"int","default"=>null),
			"auth" 		=> array("check"=>"require","default"=>''),
			"label" 	=> array("check"=>"require","default"=>null),
			"desc"		=> array("default"=>''),
			"ignoreExt" => array("check"=>"require","default"=>''),
			"ignoreFileSize" => array("check"=>"require","default"=>''),
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
		// 判断是否有用户使用
		$cnt = Model('User')->where(array('roleID' => $id))->count();
		if($cnt) show_json(LNG('admin.role.delErrTips'), false);
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
