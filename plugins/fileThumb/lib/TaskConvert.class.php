<?php
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/
/**
 * 转码任务处理;
 */
class TaskConvert extends Task{
	public function onKillSet($call,$args=array()){
		$this->onKillCall = array($call,$args);
	}
	public function onKill(){
		if(!$this->onKillCall) return;
		ActionApply($this->onKillCall[0],$this->onKillCall[1]);
		$this->onKillCall = false;
	}
	protected function endAfter(){
		$this->onKill();
	}
}