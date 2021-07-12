<?php 
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/

class explorerHistory extends Controller{
	private $model;
	function __construct(){
		parent::__construct();
		$this->model = Model('SourceHistory');
		$this->checkAuth();
	}

	private function checkAuth(){
		$path = Input::get('path','require');
		$info = IO::info($path);
		if( !isset($info['sourceID']) ){ // 必须是kod的目录;
			show_json(LNG('explorer.dataError'),false);
		}
		Action('explorer.auth')->canWrite($path);
		$this->sourceID = $info['sourceID'];
	}
	private function checkItem(){
		$id = Input::get('id','require');
		$where = array('sourceID' =>$this->sourceID,'id'=> $id);
		$itemInfo = $this->model->where($where)->find();
		if(!$itemInfo){ // 没有匹配到该文档的某条历史记录;
			show_json(LNG('explorer.dataError'),false);
		}
		return $id;
	}

	/**
	 * 获取历史记录列表;
	 */
	public function get() {
		$result = $this->model->listData($this->sourceID);
		$result['current'] = IO::info($this->in['path']);
		show_json($result);
	}
	
	/**
	 * 删除某个文件的某个版本;
	 */
	public function remove() {
		$id  = $this->checkItem();
		$res = $this->model->removeItem($id);
		$msg = !!$res ? LNG('explorer.success') : LNG('explorer.error');
		show_json($msg,!!$res);
	}
	
	/**
	 * 删除某个文件的所有版本;
	 */
	public function clear() {
		$res = $this->model->removeBySource($this->sourceID);
		$msg = !!$res ? LNG('explorer.success') : LNG('explorer.error');
		show_json($msg,!!$res);
	}
	
	/**
	 * 设置某个版本为最新版;
	 */
	public function rollback() {
		$id  = $this->checkItem();
		$fileInfo = $this->model->fileInfo($id);
		Model("Source")->checkLock($this->sourceID,$fileInfo['fileID']);
		$res = $this->model->rollbackToItem($this->sourceID,$id);
		$msg = !!$res ? LNG('explorer.success') : LNG('explorer.error');
		show_json($msg,!!$res);
	}
	public function setDetail(){
		$maxLength = $GLOBALS['config']['systemOption']['historyDescLengthMax'];
		$msg  = LNG('common.lengthLimit').'('.LNG('explorer.noMoreThan').$maxLength.')';
		$data = Input::getArray(array(
			'detail'=> array('check'=>'length','param'=>array(0,$maxLength),'msg'=>$msg),
		));
		
		$id  = $this->checkItem();
		$res = $this->model->setDetail($id,$data['detail']);
		$msg = !!$res ? LNG('explorer.success') : LNG('explorer.error');
		show_json($msg,!!$res);
	}
	public function fileOut(){
		$id  = $this->checkItem();
		$fileInfo = $this->model->fileInfo($id);
		$isDownload = isset($this->in['download']) && $this->in['download'] == 1;
		if(isset($this->in['type']) && $this->in['type'] == 'image'){
			return IO::fileOutImage($fileInfo['path'],$this->in['width']);
		}
		IO::fileOut($fileInfo['path'],$isDownload,$fileInfo['name']);
	}
}
