<?php
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/

class adminGroup extends Controller{
	private $model;
	function __construct(){
		parent::__construct();
		$this->model = Model('Group');
	}

	public function get() {
		$data = Input::getArray(array(
			"parentID"		=> array("check"=>"int",'default'=>0),
			"rootParam" 	=> array("check"=>"require",'default'=>''),
		));

		if(isset($_REQUEST['rootParam']) ){
			$groupCurrent = $this->model->getInfo($data['parentID']);
			$items = array("list"=>array($groupCurrent));
			if($data['rootParam'] == 'appendRootGroup'){
				$items['list'][] = array(
					"groupID" 		=> "1",
					"parentID" 		=> "0",
					"name" 			=> LNG('explorer.auth.toOuter'),
					"isParent"		=> true,
					"disableSelect" => true,
					"nodeAddClass" 	=> 'node-append-group',
				);
			}
			if($data['rootParam'] == 'appendShareHistory'){
				$shareHistory = array(
					"groupID" 		=> "-",
					"parentID" 		=> "0",
					"name" 			=> urldecode(LNG('explorer.groupAuthRecent')),
					"isParent"		=> true,
					"disableSelect" => true,
					"nodeAddClass" 	=> 'node-append-shareTarget',
					'children'		=> Action('explorer.userShareTarget')->get(10),
					'icon' 			=> '<i class="font-icon ri-star-fill"></i>',
				);
				if(count($shareHistory['children']) > 0){
				    $items['list'] = array($shareHistory,$items['list'][0]);
				}
			}
		}else{
			$items = $this->model->listChild($data['parentID']);
		}
		show_json($items,true);
	}

	/**
	 * 根据部门id获取信息
	 */
	public function getByID() {
		$id = Input::get('id','[\d,]*');
		$result = $this->model->listByID(explode(',',$id));
		show_json($result,true);
	}
	
	/**
	 * 搜索部门
	 */
	public function search() {
		$data = Input::getArray(array(
			"words" 		=> array("check"=>"require"),
			"parentGroup"	=> array("check"=>"int",'default'=>false),
		));
		$result = $this->model->listSearch($data);
		show_json($result,true);
	}
	
	/**
	 * 群组添加
	 * admin/group/add&name=t1&parentID=101&sizeMax=0
	 */
	public function add(){
		$data = Input::getArray(array(
			'groupID'	=> array("check"=>"int","default"=>null),	// 第三方导入
			"name" 		=> array("check"=>"require","default"=>""),
			"sizeMax" 	=> array("check"=>"float","default"=>1024*1024*100),
			"parentID"	=> array("check"=>"int"),
		));
		$groupID = $this->model->groupAdd($data);
		
		// 添加部门默认目录
		$groupInfo = Model('Group')->getInfo($groupID);
		$sourceID = $groupInfo['sourceInfo']['sourceID'];
		$this->folderDefault($sourceID);
		
		$msg = $groupID ? LNG('explorer.success') : LNG('explorer.error');
		return show_json($msg,!!$groupID,$groupID);
	}

	/**
	 * 部门默认目录
	 */
	public function folderDefault($sourceID){
		$folderDefault = Model('SystemOption')->get('newGroupFolder');
		$folderList = explode(',', $folderDefault);
        foreach($folderList as $name){
            $path = "{source:{$sourceID}}/" . $name;
            IO::mkdir($path);
        }
    }

	/**
	 * 编辑 
	 * admin/group/edit&groupID=101&name=warlee&sizeMax=0
	 */
	public function edit() {
		$data = Input::getArray(array(
			"name" 		=> array("default"=>null),
			"sizeMax" 	=> array("check"=>"float","default"=>null),
			"groupID" 	=> array("check"=>"int"),
			"parentID"	=> array("default"=>null),
		));
		// if($data['groupID'] != '1' && !$data['parentID']){
		// 	show_json(LNG('admin.group.parentNullError'), false);
		// }
		$res = $this->model->groupEdit($data['groupID'],$data);
		$msg = $res ? LNG('explorer.success') : LNG('explorer.error');
		return show_json($msg,!!$res,$data['groupID']);
	}

	/**
	 * 删除
	 */
	public function remove() {
		$id = Input::get('groupID','bigger',null,1);
		$res = $this->model->groupRemove($id);
		$msg = $res ? LNG('explorer.success') : LNG('explorer.error');
		show_json($msg,!!$res);
	}
}
