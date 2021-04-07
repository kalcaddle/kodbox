<?php 
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/

/**
 * 用户部门查询,搜索处理
 */
class filterUserGroup extends Controller{
	function __construct(){
		parent::__construct();
	}

	public function check(){
		$action  = strtolower(ACTION);
		$actions = array(
			'admin.member.get',
			'admin.member.getbyid',
			'admin.member.search',
			
			'admin.group.get',
			'admin.group.getbyid',
			'admin.group.search',
		);
		if(!in_array($action,$actions)) return;
		if($this->enableGroup()) return;
		// return show_json(array(),true); //不处理数据;
		
		if($action == 'admin.member.getbyid') return;
		if($action == 'admin.group.getbyid'){
			return show_json(array(),true);
		}		
		$list = array();
		$page = array("page"=>1,"totalNum"=>0,"pageTotal"=>0);
		if($action == 'admin.member.search'){
			$words = Input::get('words','require');
			$where = array(
				'name' 		=> $words,
				'nickName' 	=> $words,
				'email' 	=> $words,
				'phone' 	=> $words,
				'_logic' 	=> 'or',
			);
			$user = Model("User")->where($where)->find();
			if($user){
				$list = Model('User')->userListInfo(array($user['userID']));
				$list = array_values($list);
				$page['totalNum'] = 1;
			}
			return show_json(array('list'=>$list,'pageInfo'=>$page),true);
		}
		/*
		I18n::set(array(
			'admin.member.searchUser' => '通过用户名/手机号/邮箱查找用户',//"搜索用户(支持拼音及模糊匹配)"
		));
		*/
		show_json(array('list'=>$list,'pageInfo'=>$page),true);
	}
	
	// 是否允许查询数据;
	private function enableGroup(){
		if(_get($GLOBALS,'isRoot')) return true;
		$groupInfo 	= Session::get("kodUser.groupInfo");
		if(!$groupInfo || count($groupInfo) == 1) return false;
		return true;
	}
}