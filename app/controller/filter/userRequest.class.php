<?php

/**
 * url请求限制处理;
 * 
 * 限制用户请求过于频繁处理;
 * 限制用户同时进行中的长任务数量;
 */
class filterUserRequest extends Controller {
	function __construct() {
		parent::__construct();
	}
	public function bind(){
		$this->checkRequestMany();
		Hook::bind('Task.init',array($this,'taskCheck'));
	}
	public function taskCheck(){
		$taskAllowMax = $this->config['systemOption']['userTaskAllowMax'];
		if(!$taskAllowMax || _get($GLOBALS,'isRoot') || !USER_ID) return;
		
		$result  = Task::listData(USER_ID);
		if(count($result) > $taskAllowMax){
			$error = "Task is too many! (".$taskAllowMax.")";
			Task::log($error.';user='.USER_ID.';');
			show_json($error,false);
		}
	}
	
	/**
	 * 防止用户恶意请求;
	 */
	public function checkRequestMany(){
		//每分钟最大请求数; 300个则每秒5个,每5秒25个, 25个内小于5s
		if(_get($GLOBALS,'isRoot')) return;
		$requestPerMinuteMax = $this->config['systemOption']['requestPerMinuteMax'];
		$requestAllowPerMinuteMax = $this->config['systemOption']['requestAllowPerMinuteMax'];
		if(!$requestPerMinuteMax || !$requestAllowPerMinuteMax) return;
		$ingoreActions = array(
			'explorer.index.mkdir',
			'explorer.list.path',
			'explorer.index.mkfile',
			'explorer.upload.fileupload',
			
			'explorer.index.fileout',
			'explorer.share.file',
			'explorer.share.fileout',
			'user.setting.taskaction',
		);
		if(in_array(strtolower(ACTION),$ingoreActions)){
			$this->checkRequestTimer('userRequestAllow',$requestAllowPerMinuteMax);//
		}else{
			$this->checkRequestTimer('userRequest',$requestPerMinuteMax);//
		}
	}
	
	// 常规请求与高频词请求,分开处理;
	private function checkRequestTimer($key,$requestMax){
		$timeList = Session::get($key);
		$timeList = $timeList ? $timeList:array();
		$timeList[] = timeFloat();
		$seconds    = 5;// 检测间隔
		$lastNumber = intval($requestMax * ($seconds / 60));
		$count      = count($timeList);	
		if($count > $lastNumber){
			$timeUse = $timeList[$count - 1] - $timeList[$count - $lastNumber + 1];
			$timeList = array_slice($timeList,1,$lastNumber);
			if($timeUse < $seconds){
				Session::set($key,$timeList);
				// usleep(50000);//50ms;
				// write_log([$key,timeFloat(),$timeUse,$seconds,$requestMax,$timeList],'task');
				show_json("request too many!",false);exit;
			}
		}
		Session::set($key,$timeList);
	}
}
