<?php
/**
 * 任务管理
 */
class adminTask extends Controller {
	function __construct()    {
		parent::__construct();
	}

	public function taskList($userID=false){
		// Cache::deleteAll();
		$result  = Task::taskListUser($userID);pr($result);
		$userArr = array_to_keyvalue($result,'','userID');
		$userArr = Model('User')->userListInfo($userArr);		
		foreach ($result as $key =>$value) {
			if( $value['status'] == 'kill' && 
				timeFloat() - $value['timeUpdate'] >= 10){
				Task::valueSet($value['id'],false);
			}
			if( timeFloat() - $value['timeUpdate'] >= 600 ){
				Task::valueSet($value['id'],false);//超过10分钟没有更新则删除;
			}
			$result[$key]['userInfo'] = $userArr[$value['userID']];
		}
		
		$result = array_slice($result,0,50);//最多50
		show_json($result,true);
	}
	public function taskKillAll($userID=false){
		$result  = Task::taskListUser($userID);
		foreach ($result as $item) {
			Task::kill($item['id']);
		}
		$this->taskList($userID);
	}
	public function taskAction(){
		$result = $this->taskActionRun(false);
		if( !is_array($result['taskInfo']) ){
			show_json(LNG('common.notExists'),false);
		}
		show_json($result['result'],true);
	}
	
	// 不允许直接访问;
	public function taskActionRun($param){
		$allow = array('get','kill','stop','restart');
		$param = Input::getArray(array(
			"action"	=> array("check"=>"in","param"=>$allow),
			"id"		=> array("check"=>"key"),
		));
		$taskInfo = Task::get($param['id']);
		if(!$taskInfo){
			// 结束数据缓存并返回;有数据时输出并清空缓存;
			if($param['action'] == 'get'){
				$data = Cache::get('result_'.$param['id']);
				if($data){
					Cache::remove('result_'.$param['id']);
					show_json($data,true,'task_finished');
				}
			}
			return array('result'=>false,'taskInfo'=>false);
		}
		switch($param['action']){
			case 'get':		$result = $taskInfo;break;
			case 'stop':	$result = Task::stop($param['id']);break;
			case 'restart':	$result = Task::restart($param['id']);break;
			case 'kill':	$result = Task::kill($param['id']);break;
			default:break;
		}
		return array('result'=>$result,'taskInfo'=>$taskInfo);
	}
}