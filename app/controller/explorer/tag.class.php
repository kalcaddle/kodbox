<?php 
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/

/**
 * 标签管理：增删改查、置顶置底；
 * listData();				//tag列表 
 * add();					//tag添加   [参数]:name/style
 * edit();					//重命名Tag [参数]:tagID,name/style
 * remove();				//删除tag 	[参数]:tagID
 * moveTop();				//置顶 		[参数]:tagID
 * moveBottom();			//置底 		[参数]:tagID
 * resetSort();				//重置排序，更具id的顺序重排; [参数]:tagList:逗号隔开的id
 * -------
 * sourceAddToTag();		//添加文档到tag [参数]:tagID/sourceID
 * sourceResetTag();		//重置某个文档所在的tag [参数]:tagList:逗号隔开的id/sourceID
 * sourceRemoveFromTag();	//将文档从某个tag中移除 [参数]:tagID/sourceID
 */
class explorerTag extends Controller{
	private $model;
	function __construct(){
		parent::__construct();
		$this->model  		= Model('UserTag');
		$this->modelSource  = Model('UserTagSource');
	}
	/**
	 * tag列表
	 */
	public function get() {
		show_json($this->data());
	}
	private function data(){
		return $this->model->listData();
	}
	/**
	 * 用户文件标签列表
	 */
	public function tagList(){
		$this->initUserData();
		$dataList = $this->data();
		$tagSource = $this->modelSource->listData();
		$list = array();
		foreach ($dataList as $item) {
			$style  = $item['style']? $item['style'] : 'label-grey-normal';
			$find   = array_filter_by_field($tagSource,'tagID',$item['id']);
			$list[$item['id']] = array(
				"name"		=> $item['name'],
				"path"		=> KodIO::makeFileTagPath($item['id']),
				"icon"		=> 'tag-label label ' . $style,
				'tagInfo' 	=> $item,
				'tagHas' 	=> count($find),
				'pathDesc'  => LNG('explorer.pathDesc.tagItem'),
			);
		}
		return $list;
	}
	
	private function initUserData(){
		if(Model('UserOption')->get('userTagInit','flag') == 'ok') return;
		$list = $GLOBALS['config']['settings']['userDefaultTag'];
		foreach ($list as $item) {
			$this->model->add(LNG($item['name']),$item['style']);
		}
		Model('UserOption')->set('userTagInit','ok','flag');
	}
	
	public function listSource($tags){
		$groupPre = 'group-';
		if(strstr($tags,$groupPre)){
			$tags = explode('-',substr($tags,strlen($groupPre)));
			return Action("explorer.tagGroup")->listSource($tags[0],array_slice($tags,1));
		}
		
		$tags   = explode('-',$tags);
		$tags   = $tags[0] ? $tags : false;
		$result = Model("Source")->listUserTag($tags);
		$tagInfo= $this->tagsInfo($tags);
		$tagInfo['pathAddress'] = array(
			array("name"=> LNG('explorer.userTag.title'),"path"=>'{block:fileTag}/'),
			array("name"=> $tagInfo['name'],"path"=>$this->in['path']),
		);
		$tagInfo['pathDesc'] = LNG('explorer.tag.pathDesc');
		if(!$result){$result = array("fileList"=>array(),'folderList'=>array());}
		$result['currentFieldAdd'] = $tagInfo;
		$result['fileList']   = $this->_checkExists($result['fileList']);
		$result['folderList'] = $this->_checkExists($result['folderList']);
		return $result;
	}
	
	// 自动清理不存在的内容(仅限物理路径, 其他io路径由于比较耗时暂不处理,手动处理)
	private function _checkExists($list){
		if(!$list){return array();}
		foreach($list as $key => &$item){
			if(substr($item['path'],0,1) == '{'){continue;} // 仅处理物理路径;
			if(!file_exists($item['path'])){
				// $item['exists'] = false;
				unset($list[$key]);
				$this->modelSource->removeBySource($item['path']);
			}
		};unset($item);
		return $list;
	}
	
	// 普通路径追加标签信息; source路径会已经自动添加;
	public function tagAppendItem(&$item){
		if(isset($item['sourceInfo']['tagInfo'])){return $item;}
		
		static $listPathMap = false;
		static $tagInfoArr  = false;
		if($listPathMap === false){ // 缓存处理;
			$this->modelSource->cacheFunctionClear('listData',USER_ID);
			$listPathMap = $this->modelSource->listData(); // model查询缓存;			
			$listPathMap = array_to_keyvalue_group($listPathMap,'path','tagID');
			$tagInfoArr  = $this->model->listData();
			$tagInfoArr  = array_to_keyvalue($tagInfoArr,'id');
		}
		if(!$tagInfoArr || !$listPathMap){return $item;}
		
		$item['sourceInfo']['tagInfo'] = 0;
		$path 	 = $item['path'];$path1 = rtrim($item['path'],'/');$path2 = rtrim($item['path'],'/').'/';
		$findItem = isset($listPathMap[$path]) ? $listPathMap[$path]:false;
		$findItem = (!$findItem && isset($listPathMap[$path1])) ? $listPathMap[$path1]:$findItem;
		$findItem = (!$findItem && isset($listPathMap[$path2])) ? $listPathMap[$path2]:$findItem;
		if(!$findItem){return $item;}
		
		$item['sourceInfo']['tagInfo'] = array();
		foreach ($findItem as $tagID) {
			$item['sourceInfo']['tagInfo'][] = array(
				"tagID"	=> $tagInfoArr[$tagID]['id'],
				"name"	=> $tagInfoArr[$tagID]['name'],
				"style"	=> $tagInfoArr[$tagID]['style'],
			);
		}
		return $item;
	}
	
	
	private function tagsInfo($tags){
		$info = false;
		$styleDefault = 'tag-label label label-blue-normal';
		if(!$tags){return array('name'=>'--','icon'=>$styleDefault);}
		
		$tagList = $this->model->listData();
		foreach ($tagList as $item) {
			$icon = 'tag-label label '.$item['style'];
			if(!in_array($item['id'],$tags)) continue;
			if(!$info){$info = array('name'=>$item['name'],'icon'=>$icon);continue;}
			$info['name'] .= ','.$item['name'];
		}
		if(count($tags) > 1){$info['icon'] = $info['icon'].' label-mutil';}
		return $info;
	}
	

	/**
	 * tag添加
	 */
	public function add(){
		$data = Input::getArray(array(
			"name"		=> array("check"=>"require"),
			"style"		=> array('check'=>"require"),
		));
		if(count($this->data()) > $GLOBALS['config']['systemOption']['tagNumberMax']){
			show_json(LNG("common.numberLimit"),false);
		}
		
		$res = $this->model->add($data['name'],$data['style']);
		$msg = $res ? LNG('explorer.success') : LNG('explorer.repeatError');
		show_json($msg,!!$res,$this->data());
	}

	/**
	 * 重命名Tag
	 */
	public function edit() {
		$data = Input::getArray(array(
			"tagID"		=> array("check"=>"int"),
			"name"		=> array('default'=>null),
			"style"		=> array('default'=>null),
		));
		$res = $this->model->update($data['tagID'],$data);
		$msg = $res ? LNG('explorer.success') : LNG('explorer.repeatError');
		show_json($msg,!!$res,$this->data());
	}
	
	/**
	 * 删除tag
	 */
	public function remove(){
		$tagID = Input::get('tagID',"int");
		$res = $this->model->remove($tagID);
		$this->modelSource->removeByTag($tagID);
		$msg = $res ? LNG('explorer.success') : LNG('explorer.error');
		show_json($msg,!!$res,$this->data());
	}
	
	/**
	 * 置顶
	 */
	public function moveTop() {
		$tagID = Input::get('tagID',"int");
		$res = $this->model->moveTop($tagID);
		$msg = $res ? LNG('explorer.success') : LNG('explorer.error');
		show_json($msg,!!$res,$this->data());
	}

	/**
	 * 置底
	 */
	public function moveBottom() {
		$tagID = Input::get('tagID',"int");
		$res = $this->model->moveBottom($tagID);
		$msg = $res ? LNG('explorer.success') : LNG('explorer.error');
		show_json($msg,!!$res,$this->data());
	}
	/**
	 * 重置排序，根据id的顺序重排;
	 */
	public function resetSort() {	
		$idList = Input::get('tagList',"require");
		$idArray = explode(',',$idList);
		if(!$idArray) {
			show_json(LNG('explorer.error'),false);
		}
		$res = $this->model->resetSort($idArray);
		$msg = $res ? LNG('explorer.success') : LNG('explorer.error');
		show_json($msg,!!$res,$this->data());
	}

	
	//======== tag关联资源管理 =========
	//将文档从某个tag中移除
	public function filesRemoveFromTag(){
		$data = Input::getArray(array(
			"tagID"	=> array("check"=>"int"),
			"files"	=> array("check"=>"require"),
		));
		
		$data['files'] = str_replace("__*@*__",',',$data['files']);
		$files = explode(',',$data['files']);
		if(!$files){
			show_json(LNG('explorer.error'),false);
		}
		$res = $this->modelSource->removeFromTag($files,$data['tagID']);
		if(!$res && count($files) == 1){ // 部分老数据处理; 文件夹统一去除结尾斜杠;
			$files[0] = rtrim($files[0],'/');
			$res = $this->modelSource->removeFromTag($files,$data['tagID']);
		}
		show_json(LNG('explorer.success'),true);
	}
	
	//添加文档到tag;
	public function filesAddToTag(){
		$data = Input::getArray(array(
			"tagID"	=> array("check"=>"int"),
			"files"	=> array("check"=>"require"),
		));
		$data['files'] = str_replace("__*@*__",',',$data['files']);
		$files = explode(',',$data['files']);
		if(!$files){
			show_json(LNG('explorer.error'),false);
		}
		foreach ($files as $file) {
			$res = $this->fileAddTag($file,$data['tagID']);
		}
		show_json(LNG('explorer.success'),true);
	}
	
	// 标签包含内容数量上限控制;
	private function fileAddTag($file,$tagID){
		$count = $this->modelSource->where(array('tagID'=>$tagID))->count();
		if( $count > $GLOBALS['config']['systemOption']['tagContainMax'] ){
			show_json(LNG("common.numberLimit"),false);
		}
		Action('explorer.listSafe')->authCheckAllow($file);
		return $this->modelSource->addToTag($file,$tagID);
	}
	
}
