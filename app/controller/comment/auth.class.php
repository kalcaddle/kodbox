<?php
/*
 * @link http://kodcloud.com/
 * @author warlee | e-mail:kodcloud@qq.com
 * @copyright warlee 2014.(Shanghai)Co.,Ltd
 * @license http://kodcloud.com/tools/license/license.txt
 */

/**
 * 评论操作权限旁路拦截；
 * 
 * ## 文档/分享评论:
 * 	- 有comment权限: 可查看评论
 * 	- 有编辑权限: 可添加,删除自己评论
 * 	- 拥有者:可删除任何评论 (可设置权限)
 */
class CommentAuth extends Controller {
	function __construct() {
		parent::__construct();
		$this->model = Model("Comment");
	}
	
	
	// 评论操作权限统一拦截检测
	public function autoCheck(){
		switch(strtolower(ACTION)){
			case 'comment.index.listdata':
				$this->canView($this->in['targetType'],$this->in['targetID']);
				break;
			case 'comment.index.add':
				$this->canEdit($this->in['targetType'],$this->in['targetID']);
				break;
			case 'comment.index.remove':
				$info = $this->info($this->in['id']);
				$this->canRemove($info['targetType'],$info['targetID'],$info);
				break;
			case 'comment.index.prasise':
				$info = $this->info($this->in['id']);
				$this->canEdit($info['targetType'],$info['targetID']);
				break;
			case 'comment.index.listbyuser':
				if(!_get($GLOBALS,'isRoot')){
					show_json(LNG('explorer.noPermissionAction'),false);
				}
				break;
			case 'comment.index.listself':;break;
			case 'comment.index.listchildren':
				$info = $this->info($this->in['pid']);
				$this->canView($info['targetType'],$info['targetID']);
				break;
		}
	}
	
	
	private function canView($targetType,$targetID){
		$this->checkType($targetType,$targetID);
		$this->checkPathAuth($targetType,$targetID,'view');
	}
	private function canEdit($targetType,$targetID){ //添加or点赞;
		$this->checkType($targetType,$targetID);
		$this->checkPathAuth($targetType,$targetID,'edit');
	}
	private function canRemove($targetType,$targetID,$info){
		$this->checkType($targetType,$targetID);
		$this->checkPathAuth($targetType,$targetID,'remove',$info);
	}
	
	// 目前只允许对文档,分享发表评论;
	private function checkType($targetType,$targetID){
		$allowType 	= array(
			CommentModel::TYPE_SOURCE,
			CommentModel::TYPE_SHARE,
		);
		if( !in_array($targetType,$allowType) ||
			!$targetType || 
			!$targetID){
			show_json(LNG('common.invalidParam'),false);
		}
	}
	
	// 文档or内部分享评论权限检测;
	private function checkPathAuth($targetType,$targetID,$action,$param = false){
		$typePath 	= array(
			CommentModel::TYPE_SOURCE,
			CommentModel::TYPE_SHARE,
		);
		if( !in_array($targetType,$typePath)) return;
		if($targetType == CommentModel::TYPE_SOURCE){
			$pathInfo = Model("Source")->pathInfo($targetID,true);
		}else if($targetType == CommentModel::TYPE_SHARE){
			$pathInfo = Model('Share')->getInfoAuth($targetID);
		}
		if(!$pathInfo){ // || $pathInfo['isDelete'] == '1'
			show_json(LNG('common.notExists'),false);
		}

		$authValue = $pathInfo['auth']['authValue'];
		$auth = Model('Auth');
		if(!$pathInfo['auth'] && 
			$pathInfo['targetType'] == 'user' && 
			$pathInfo['targetID'] == USER_ID){
			return true; //自己的文档;
		}
		if($auth->authCheckRoot($authValue)) return true;//拥有者, 管理权限;read/write/delete;
		if(!$auth->authCheckComment($authValue)){ //评论列表权限;
			show_json(LNG('explorer.noPermissionAction'),false);
		}
					
		//查看列表:有comment权限; 可以读取列表;
		if($action == 'view') return true;

		// 添加/点赞: 有编辑权限,才能操作
		if(	$action == 'edit' && 
			!$auth->authCheckEdit($authValue)){
			show_json(LNG('explorer.noPermissionAction'),false);
		}
		
		// 删除: 有编辑权限,才能删除自己的评论
		if( $action == 'remove' && 
			!$auth->authCheckEdit($authValue) &&
			$param['userID'] != USER_ID
		){
			show_json(LNG('explorer.noPermissionAction'),false);
		}
	}
	
	private function info($id){
		return $this->model->where(array("commentID"=>$id))->find();
	}	
}