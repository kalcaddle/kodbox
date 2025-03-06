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

	/**
	 * 部门子部门获取
	 * 
	 * 搜索用户过滤: 自己可见范围的根部门; 外链分享: 最近+自己所在部门跟部门+对外授权(所在根部门不等于可见根部门时显示); 权限设置同理(根据所在部门传入parentID)
	 * 后台拉取信息: 需要传入requestFromType=admin; 即拉取自己为部门管理员的部门;
	 */
	public function get() {
		$data = Input::getArray(array(
			"parentID"		=> array("check"=>"require",'default'=>''),
			"rootParam" 	=> array("check"=>"require",'default'=>''),
		));
		$userGroupAdmin 	= Action("filter.userGroup")->userGroupAdmin();		// 所在部门为管理员的部门;可多个
		$userGroupAt 		= Action("filter.userGroup")->userGroupAt();		// 所在部门根部门(有包含关系时只显示最上层); 可多个
		$userGroupRootShow  = Action("filter.userGroup")->userGroupRootShow(); 	// 所在部门根部门可见范围;可多个(根据上级部门可见范围决定)
		$requestAdmin   	= isset($this->in['requestFromType']) && $this->in['requestFromType'] == 'admin'; // 后端用户列表;
		
		if(!$data['parentID'] || $data['parentID'] == 'root' || $data['parentID'] == 'rootOuter'){
			if($requestAdmin && $userGroupAdmin){$data['parentID'] = $userGroupAdmin;}
			if(!$requestAdmin && $userGroupAt && $data['parentID'] != 'rootOuter'){$data['parentID']   = $userGroupAt;}
			if($data['parentID'] == 'rootOuter'){$data['parentID'] = $userGroupRootShow;}
			if(KodUser::isRoot()){$data['parentID'] = array('1');}
		}
		// pr($userGroupAdmin,$userGroupAt,$userGroupRootShow,$data);exit;		
		if($data['rootParam']){// 有缓存未更新是否有子部门及用户的问题;
			Model('Group')->cacheFunctionClear('getInfo',$data['parentID']);
		}
		$result = array("list"=>array(),'pageInfo'=>array());
		if($data['parentID'] && is_array($data['parentID'])){
			$result['list'] = Model('Group')->listByID($data['parentID']);
		}else if($data['parentID'] && is_string($data['parentID'])){
			$result = $this->model->listChild($data['parentID']);
		}

		if($data['rootParam'] && strstr($data['rootParam'],'appendShareHistory')){
			$toOuterGroup = array(
				"groupID" 		=> "rootOuter",
				"parentID" 		=> "root",
				"name" 			=> LNG('explorer.auth.toOuter'),
				"isParent"		=> true,
				"disableSelect" => true,
				"disableOpen" 	=> true,//禁用自动展开
				"nodeAddClass" 	=> 'node-append-group',
			);
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
			
			// 权限设置时,parentID为当前部门; 显示部门最上层;
			if($data['parentID'] && is_string($data['parentID'])){
				$result['list'] = Model('Group')->listByID(array($data['parentID']));
			}
			// 自己可见根目录,不再当前显示列表中,则显示对外授权;
			if($userGroupRootShow != array_to_keyvalue($result['list'],'','groupID')){
				$result['list'][] = $toOuterGroup;
			}
			$children = $shareHistory['children'];
			if(is_array($children) && count($children) > 0){
				$result['list'] = array_merge(array($shareHistory),$result['list']);
			}
		}
		show_json($result,true);		
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
			"parentGroup"	=> array("check"=>"require",'default'=>false),// 支持多个父部门,多个逗号隔开;
		));

		if(!$data['parentGroup']){
			$result = $this->model->listSearch($data);
		}else{
			$groupArr = explode(',',$data['parentGroup']);$result = false;
			foreach ($groupArr as $groupID) {
				$data['parentGroup'] = intval($groupID);
				$listSearch = $this->model->listSearch($data);
				if(!$result){$result = $listSearch;continue;}
				$result['list'] = array_merge($result['list'],$listSearch['list']);
			}
			if(is_array($result) && is_array($result['list'])){
				$result = array_page_split(array_unique($result['list']),$result['pageInfo']['page'],$result['pageInfo']['pageNum']);
			}
		}
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
			"sort"		=> array("default"=>null),
			"authShowType" 	=> array("default"=>null),
			"authShowGroup" => array("default"=>null),
			"ioDriver"		=> array("check"=>"int","default"=>null),
		));
		$data['name'] = str_replace('/','',$data['name']);
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
			"sort"		=> array("default"=>null),
			"authShowType" 	=> array("default"=>null),
			"authShowGroup" => array("default"=>null),
			"ioDriver"		=> array("check"=>"int","default"=>null),
		));
		if(!empty($data['name'])){
			$data['name'] = str_replace('/','',$data['name']);
		}
		$res = $this->model->groupEdit($data['groupID'],$data);
		$msg = $res ? LNG('explorer.success') : LNG('explorer.error');
		return show_json($msg,!!$res,$data['groupID']);
	}

	/**
	 * 禁/启用
	 * @return void
	 */
	public function status(){
		$data = Input::getArray(array(
			"groupID" 	=> array("check"=>"int"),
			"status"	=> array("check"=>"in", "param" => array(0, 1)),
		));
		$res = $this->model->groupStatus($data['groupID'], $data['status']);
		$msg = $res ? LNG('explorer.success') : LNG('explorer.error');
		show_json($msg,!!$res);
	}

	/**
	 * 删除
	 */
	public function remove() {
		// $id = Input::get('groupID','bigger',null,1);
		$data = Input::getArray(array(
			"groupID" 	=> array("check"=>"require"),
			"delAll"	=> array("default"=>0),
		));
		$del = boolval($data['delAll']);
		$ids = explode(',', $data['groupID']);
		if (count($ids)>1 && $del) {
			$res = $this->model->listChildIds($ids);
			if ($res === false) show_json(LNG('explorer.error'),false);
			$ids = array_merge($ids, $res);
		}
		$code = 0;
		foreach ($ids as $id) {
			$res = $this->model->groupRemove($id,$del);
			$code += ($res ? 1 : 0);
		}
		$msg = $code ? LNG('explorer.success') : LNG('explorer.error');
		show_json($msg,!!$code);
	}

	/**
	 * 排序
	 */
	public function sort() {
		$ids = Input::get('groupID','require');
		$ids = explode(',', $ids);
		$res = false;
		if (!empty($ids)) {
			$this->model->groupSort($ids);
			$res = true;
		}
		$msg = $res ? LNG('explorer.success') : LNG('explorer.error');
		show_json($msg,!!$res);
	}

	/**
	 * 部门迁移 
	 */
	public function switchGroup(){
		$data = Input::getArray(array(
			"from"		=> array("check"=>"int"),
			"to"		=> array("check"=>"int"),
		));
		$res = $this->model->groupSwitch($data['from'],$data['to']);
		$msg = $res ? LNG('explorer.success') : LNG('explorer.error');
		show_json($msg,!!$res);
	}
}
