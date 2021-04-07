<?php

/*
 * @link http://kodcloud.com/
 * @author warlee | e-mail:kodcloud@qq.com
 * @copyright warlee 2014.(Shanghai)Co.,Ltd
 * @license http://kodcloud.com/tools/license/license.txt
 */

/**
 * 聊天列表主题相关;
 * 
 * index 	// 聊天对象列表; 按最后评论时间降序;
 * notify 	// 获取聊天对象个数;
 * readAll 	// 全部已读
 * read 	// 聊天对象已读;
 */
class commentTopic extends Controller {
	public function __construct(){
		parent::__construct();
		$this->model = Model("Comment");
	}

	public function index(){
		$chatTopic = $this->chatTopic();
		foreach ($chatTopic as $key => $item) {
			$id = $item['targetID'];
			$where = array(
				'targetType'	=> $item['targetType'],
				'targetID'		=> $id,
			);
			$listLast = $this->model->limit(1)->listData($where);
			$last = $listLast['list'][0];
			switch($item['targetType']){
				case CommentModel::TYPE_SHARE:
					$last['target'] = Action("explorer.userShare")->sharePathInfo($id);
					break;
				case CommentModel::TYPE_SOURCE:
					$last['target'] = Model('Source')->pathInfo($id);
					break;
				case CommentModel::TYPE_USER:
					$last['target'] = Model('User')->getInfo($id);
					break;
				case CommentModel::TYPE_GROUP:
					$last['target'] = Model('Group')->getInfo($id);
					break;
				case CommentModel::TYPE_TOPIC:
					break;
				default:break;
			}
			$chatTopic[$key] = array_merge($last,$item);
		}
		$chatTopic = array_values($chatTopic);
		$chatTopic = array_sort_by($chatTopic,'createTime',true);
		show_json($chatTopic);
	}
	
	// 通知更新获取;
	public function notify(){
		$chatTopic = $this->chatTopic();
		foreach ($chatTopic as &$item) {
			$where = array(
				'targetType'	=> $item['targetType'],
				'targetID'		=> $item['targetID'],
				'commentID'		=> array(">",$item['readLast']),
			);
			//该主题:未读消息数;
			$item['newCount'] = $this->model->where($where)->count();
		}
		$chatTopic = array_values($chatTopic);
		show_json($chatTopic);
	}
	
	// 全部已读
	public function readAll(){
		$chatTopic = $this->chatTopic();
		foreach ($chatTopic as $item) {
			$this->readItem($item['targetType'],$item['targetID']);
		}
		show_json($chatTopic);
	}
	public function read(){
		$item = Input::getArray(array(
			"targetType"	=> array("check"=>"in","param"=>CommentModel::$TYPEALL),
			"targetID"		=> array("check"=>"number"),
		));
		$this->readItem($item['targetType'],$item['targetID']);
	}
	

	// 某讨论主题已读; 用户/部门
	private function readItem($targetType,$targetID){
		$key   = "userChatReadLast_".USER_ID;
		$topic = Cache::get($key);
		$topic = $topic ? $topic : array();
		$where = array(
			'targetType'	=> $targetType,
			'targetID'		=> $targetID,
		);
		$topicMax = $this->model->where($where)->max('commentID');
		$topic[$targetType.'_'.$targetID] = $topicMax;
		Cache::set($key,$topic);
	}
	
	
	// 自己参与的讨论主题: 文档/分享;部门/用户/群聊;
	// 主题只包含: 用户,部门,关注文档; 群聊; [数据自动构建;] 没有评论过也会有该主题;
	private function chatTopic(){
		// $field = 'targetType,targetID';
		// $where = array("userID"=> USER_ID);
		// $topic = $this->model->field($field)->where($where)->group($field)->select();
		
		// $topicList = array();
		// $topicRead = Cache::get("userChatReadLast_".USER_ID);
		// foreach($topic as $item){
		// 	$id = $item['targetType'].'_'.$item['targetID'];
		// 	$item['readLast'] = isset($topicRead[$id]) ? $topicRead[$id]:0;
		// 	$topicList[$id] = $item;
		// }
		// return $topicList;
	}
}