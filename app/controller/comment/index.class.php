<?php

/*
 * @link http://kodcloud.com/
 * @author warlee | e-mail:kodcloud@qq.com
 * @copyright warlee 2014.(Shanghai)Co.,Ltd
 * @license http://kodcloud.com/tools/license/license.txt
 */

class commentIndex extends Controller {
	private $model;
	public function __construct(){
		parent::__construct();
		$this->model = Model("Comment");
		Action('comment.auth')->autoCheck();
	}

	/**
	 * 评论列表
	 * 
	 * 通用请求参数:sortField|sortType; page|pageNum
	 * CommentModel::TYPE_SHARE|TYPE_SOURCE|TYPE_USER|TYPE_GROUP
	 */
	public function listData(){
		$data = Input::getArray(array(
			"targetType"	=> array("check"=>"number"),
			"targetID"		=> array("check"=>"number"),
			
			"idFrom"		=> array("check"=>"number","default"=>0),
			"idTo"			=> array("check"=>"number","default"=>0),
		));
		// $this->in['pageNum'] = 5;
		$list = $this->model->listData($data);
		
		// 自动标记已读;
		if(USER_ID && !$data['idFrom'] && !!$data['idTo']){
			Action("comment.topic")->read();
		}		
		show_json($list,!!$list);
	}

	/**
	 * 添加评论
	*/
	public function add(){
		$data = Input::getArray(array(
			"targetType"	=> array("check"=>"number"),
			"targetID"      => array("check"=>"number"),
			"content"       => array("check"=>"require"),
			"pid"           => array("check"=>"number","default"=>0),
		));
		$data['userID'] = USER_ID;
		$result = $this->model->addComment($data);
		show_json($result,true);
	}
	
	// 目标本身点赞用户列表
	public function starUserList(){
		$data = Input::getArray(array(
			"targetType"	=> array("check"=>"number"),
			"targetID"      => array("check"=>"number"),
		));
		$result = $this->model->addComment($data);
		show_json($result,true);
	}
	
	// 评论编辑;
	public function edit(){
		$data = Input::getArray(array(
			"id"			=> array("check"=>"number"),
			"content"       => array("check"=>"require"),
		));
		$result = $this->model->edit($data['id'],$data['continue']);
		show_json($result,true);
	}

	/**
	 * 删除评论
	*/
	public function remove(){
		$id = Input::get("id","number");
		$result = $this->model->remove($id);
		show_json($result,!!$result);
	}

	/**
	 * 点赞or取消赞
	*/
	public function prasise(){
		$id = Input::get("id","number");
		$result = $this->model->prasise($id);
		show_json($result,!!$result);
	}
	
	// 直接点赞目标对象;(点赞文章or文件)
	public function starTarget(){
		$data = Input::getArray(array(
			"targetType"	=> array("check"=>"number"),
			"targetID"      => array("check"=>"number"),
		));
		$result = $this->model->starTarget($data['targetType'],$data['targetID']);
		show_json($result,true);
	}
	// 直接点赞目标对象;(点赞文章or文件); {count:xx,userList:[{},...]}
	public function starTargetUserList(){
		$data = Input::getArray(array(
			"targetType"	=> array("check"=>"number"),
			"targetID"      => array("check"=>"number"),
		));
		$result = $this->model->starTargetUserList($data['targetType'],$data['targetID']);
		show_json($result,true);
	}
	
	// 获取评论点赞信息; {count:xx,userList:[{},...]}
	public function prasiseUserList(){
		$id = Input::get("id","number");
		$result = $this->model->prasiseUserList($id);
		show_json($result,true);
	}
	
	
	/**
	 * 查询用户评论
	 * 
	 * 通用请求参数:sortField|sortType; page|pageNum
	 */
	public function listByUser(){
		$userID = Input::get("userID","number");
		$data   = array('userID'=>$userID);
		$list   = $this->model->listData($data);
		show_json($list,!!$list);
	}
	
	// 自己的评论;
	public function listSelf(){
		$data   = array('userID'=>USER_ID);
		$list   = $this->model->listData($data);
		show_json($list,!!$list);
	}
	
	/**
	 * 评论子评论
	 */
	public function listChildren(){
		$pid   = Input::get("pid","number");
		$data  = array('pid'=>$pid);
		$list  = $this->model->listData($data);
		show_json($list,!!$list);
	}
}