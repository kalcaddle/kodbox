<?php 
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/

class explorerFav extends Controller{
	private $model;
	function __construct(){
		parent::__construct();
		$this->model  = Model('UserFav');
	}
	/**
	 * 获取收藏夹json
	 */
	public function get() {
		$pageNum = $GLOBALS['in']['pageNum'];$GLOBALS['in']['pageNum'] = 2000;//分页处理;
		$list = $this->model->listView();
		$GLOBALS['in']['pageNum'] = $pageNum ? $pageNum:null;
		$list = $this->_checkExists($list);
		// pr($list);exit;
		return $list;
	}
	
	private function _checkExists($list){
		foreach ($list as &$item) {
			if(!isset($item['sourceInfo'])){
				$item['sourceInfo'] = array();
			}
			$item['sourceInfo']['isFav']   = 1;
			if(!$item['sourceInfo']['favName']){
				$item['sourceInfo']['favName'] = $item['name'];
			}
			if(!$item['sourceInfo']['favID']){
				$item['sourceInfo']['favID'] = $item['id'];
			}
			unset($item['id']);
			if( $item['type'] == 'source' && $item['sourceID']){
				$item['type'] = $item['isFolder'] == '1' ? 'folder':'file';
				$item['path'] = KodIO::make($item['path']);
				continue;
			}
			if( $item['type'] == 'source' ){
				// 文件不存在处理;
				$item['type']   = 'folder';
				$item['exists'] = false;
				$item['path']   = KodIO::make($item['path']);
			}else{
				$info = Action('explorer.list')->pathCurrent($item['path'],false);
				if($item['type'] == 'file'){$info['type'] = 'file';}
				unset($info['name']);
				$item = array_merge($item,$info);
				if($item['type'] == 'file'){
					$item['ext'] = get_path_ext($item['name']);
				}
			}
		};unset($item);
		return $list;
	}
	public function favAppendItem($item){
		static $favList = false;
		if($favList === false){
			$favList = $this->model->listData();
			$favList = array_to_keyvalue($favList,'path');
		}
		
		if(!isset($item['sourceInfo'])){
			$item['sourceInfo'] = array();
		}
		$path 	 = $item['path'];$favItem = false;
		$favItem = isset($favList[$path]) ? $favList[$path]:$favItem;
		$path 	 = rtrim($item['path'],'/');
		$favItem = isset($favList[$path]) ? $favList[$path]:$favItem;
		$path 	 = rtrim($item['path'],'/').'/';
		$favItem = isset($favList[$path]) ? $favList[$path]:$favItem;
		
		if( $favItem ){
			$item['sourceInfo']['isFav'] = 1;
			$item['sourceInfo']['favName'] = $favItem['name'];
			$item['sourceInfo']['favID'] = $favItem['id'];
		}
		// $item['$favItem'] = $favItem;		
		// 收藏文件;
		if($item['type'] == 'file'){
			$item['ext'] = get_path_ext($item['name']);
		}
		return $item;
	}

	/**
	 * 添加
	 */
	public function add(){
		$data = Input::getArray(array(
			"path"	=> array("check"=>"require"),
			"name"	=> array("check"=>"require"),
			"type"	=> array("check"=>"require","default"=>'folder'),
		));
		$list = $this->model->listData();
		$list = is_array($list) ? $list : array();
		if( count($list) > $GLOBALS['config']['systemOption']['favNumberMax'] ){
			show_json(LNG("common.numberLimit"),false);
		}
		
		$pathInfo = KodIO::parse($data['path']);
		if($pathInfo['type'] == KodIO::KOD_USER_FAV){
			show_json(LNG("explorer.pathNotSupport"),false);
		}
		if($pathInfo['type'] == KodIO::KOD_SOURCE){
			$data['type'] = 'source';
			$data['path'] = $pathInfo['id'];
		}
		$res = $this->model->addFav($data['path'],$data['name'],$data['type']);
		$msg = !!$res ? LNG('explorer.addFavSuccess') : LNG('explorer.pathHasFaved');
		show_json($msg,!!$res);
	}

	/**
	 * 重命名
	 */
	public function rename() {
		$data = Input::getArray(array(
			"name"		=> array("check"=>"require"),
			"newName"	=> array("check"=>"require"),
		));
		$res = $this->model->rename($data['name'],$data['newName']);
		$msg = !!$res ? LNG('explorer.success') : LNG('explorer.repeatError');
		show_json($msg,!!$res);
	}
	
	/**
	 * 置顶
	 */
	public function moveTop() {
		$name = Input::get('name','require');
		$res = $this->model->moveTop($name);
		$msg = !!$res ? LNG('explorer.success') : LNG('explorer.error');
		show_json($msg,!!$res);
	}

	/**
	 * 置底
	 */
	public function moveBottom() {
		$name = Input::get('name','require');
		$res = $this->model->moveBottom($name);
		$msg = !!$res ? LNG('explorer.success') : LNG('explorer.error');
		show_json($msg,!!$res);
	}

	/**
	 * 重置排序，根据id的顺序重排;
	 */
	public function resetSort() {
		$idList = Input::get('favList',"require");
		$idArray = explode(',',$idList);
		if(!$idArray) {
			show_json(LNG('explorer.error'),false);
		}
		$res = $this->model->resetSort($idArray);
		$msg = $res ? LNG('explorer.success') : LNG('explorer.error');
		show_json($msg,!!$res);
	}	

	/**
	 * 删除
	 */
	public function del() {
		$name = Input::get('name','require');
		$res = $this->model->removeByName($name);
		$msg = !!$res ? LNG('explorer.delFavSuccess') : LNG('explorer.error');
		show_json($msg,!!$res);
	}
}
