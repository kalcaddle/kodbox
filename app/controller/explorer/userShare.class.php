<?php 
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/

class explorerUserShare extends Controller{
	private $model;
	function __construct(){
		parent::__construct();
		$this->model  = Model('Share');
	}

	/**
	 * 通过文档获取分享；没有则返回false;
	 */
	public function get(){
		$path = Input::get('path','require');
		$pathParse = KodIO::parse($path);
		// 物理路径,io路径;
		if( $pathParse['type'] == KodIO::KOD_IO || !$pathParse['type'] ){
			$share = $this->model->getInfoBySourcePath($path);
			show_json($share);
		}
		$sourceID = KodIO::sourceID($path);
		$share = $this->model->getInfoByPath($sourceID);
		show_json($share);
	}
	
	// 分享信息处理;
	public function shareAppendItem($item){
		$shareInfo = _get($item,'shareInfo');
		if(!isset($item['sourceInfo'])){$item['sourceInfo'] = array();}
		if(isset($item['sourceInfo']['shareInfo']) ) return $item;
		
		static $shareList = false;
		if($shareList === false){
			$shareList = $this->model->listSimple();
			$shareList = array_to_keyvalue($shareList,'sourcePath');
		}
		
		$shareInfo = $shareInfo ? $shareInfo : _get($shareList,$item['path']);
		$shareInfo = $shareInfo ? $shareInfo : _get($shareList,rtrim($item['path'],'/'));
		$shareInfo = $shareInfo ? $shareInfo : _get($shareList,rtrim($item['path'],'/').'/');
		if(!$shareInfo) return $item;
		$item['sourceInfo']['shareInfo'] = array(
			'shareID' 		=> $shareInfo['shareID'],
			'shareHash' 	=> $shareInfo['shareHash'],
			'shareSource'	=> $shareInfo['sourceID'],
			'isLink' 		=> $shareInfo['isLink'],
			'isShareTo' 	=> $shareInfo['isShareTo'],
		);
		return $item;
	}

	/**
	 * 我的分享列表
	 * 点击进入对应文档目录；
	 * link/to
	 */
	public function myShare($type=''){
		$shareList = $this->model->listData($type);
		$result = array('fileList'=>array(),'folderList'=>array(),'pageInfo'=>$shareList['pageInfo']);
		$sourceArray = array_to_keyvalue($shareList['list'],'','sourceID');
		$sourceArray = array_unique($sourceArray);
		if($sourceArray){
			$where = array(
				'sourceID' => array('in',$sourceArray),
				'isDelete' => 0,
			);
			$sourceList  = Model('Source')->listSource($where);
			$sourceArray = array_merge($sourceList['folderList'],$sourceList['fileList']);
			$sourceArray = array_to_keyvalue($sourceArray,'sourceID');
		}
		$notExist = array();
		foreach ($shareList['list'] as $shareItem) {
			// 物理路径,io路径;
			if($shareItem['sourceID'] == '0'){
				// IO 对象存储等加速;
				$info = IO::info($shareItem['sourcePath']);
			}else{
				$info = $sourceArray[$shareItem['sourceID']];
			}
			if(!$info){
				$notExist[] = $shareItem['shareID'];
				continue;
			}
			$info['shareInfo'] = $shareItem;
			$key = $info['type'] == 'folder' ? 'folderList':'fileList';
			$result[$key][] = $info;
		}
		// pr($result,$shareList,$sourceArray,$notExist);exit;
		// 自动清除不存在的分享内容;
		if($notExist){
			// $this->model->remove($notExist);
		}		
		return $result;
	}
	
	public function shareToMe($type=''){
		$shareHide = Model('UserOption')->get('hideList','shareToMe');
		$shareHide = $shareHide ? json_decode($shareHide,true):array();
		
		$shareList = $this->model->listToMe();
		$result = array('fileList'=>array(),'folderList'=>array(),'pageInfo'=>$shareList['pageInfo']);
		$sourceArray = array_to_keyvalue($shareList['list'],'','sourceID');
		$sourceArray = array_unique($sourceArray);
		if($sourceArray){
			$where = array(
				'sourceID' => array('in',$sourceArray),
				'isDelete' => 0,
			);
			$sourceList  = Model('Source')->listSource($where);
			$sourceArray = array_merge($sourceList['folderList'],$sourceList['fileList']);
			$sourceArray = array_to_keyvalue($sourceArray,'sourceID');
		}
		$notExist = array();
		foreach ($shareList['list'] as $shareItem) {
			if($shareItem['sourceID'] == '0'){// 物理路径,io路径;
				$info = IO::info($shareItem['sourcePath']);
			}else{
				$info = $sourceArray[$shareItem['sourceID']];
			}
			if(!$info){
				$notExist[] = $shareItem['shareID'];
				continue;
			}
			$info = $this->_shareItemeParse($info,$shareItem);
			$key  = $info['type'] == 'folder' ? 'folderList':'fileList';
			
			$shareID = $info['shareID'].'';
			if(isset($shareHide[$shareID])){
				$info['shareHide'] = 1;
			}else{
				$info['shareHide'] = 0;
			}
			
			//$type:  '':显示内容; hide: 隐藏内容; all: 全部内容;
			if($type == '' && $info['shareHide']) continue;
			if($type == 'hide' && !$info['shareHide']) continue;
									
			$result[$key][] = $info;
		}
		// if($notExist){$this->model->remove($notExist);} // 自动清除不存在的分享内容;
		// pr($result);exit;
		
		return $result;
	}
	
	public function shareDisplay(){
		$data = Input::getArray(array(
			"shareArr"	=> array("check"=>"json","default"=>''),
			"isHide"	=> array("check"=>"bool","default"=>'1'),
		));
		
		$shareHide = Model('UserOption')->get('hideList','shareToMe');
		$shareHide = $shareHide ? json_decode($shareHide,true):array();
		foreach ($data['shareArr'] as $shareID) {
			$shareID = $shareID.'';
			if($data['isHide'] == '1'){
				$shareHide[$shareID] = '1';
			}else{
				unset($shareHide[$shareID]);
			}
		}
		Model('UserOption')->set('hideList',json_encode($shareHide),'shareToMe');
		show_json(LNG('explorer.success'),true);
	}
	
	
	// 分享内容属性; 默认$sourceID为空则分享本身属性; 指定则文件夹字内容属性;
	public function sharePathInfo($shareID,$sourceID=false){
		$shareInfo = $this->model->getInfo($shareID);
		if($shareInfo['sourceID'] == '0'){
			$truePath = KodIO::clear($shareInfo['sourcePath'].$sourceID);
			// $sourceInfo = IO::info($truePath);
			$sourceInfo = array('path'=>$truePath);
		}else{
			$sourceID = $sourceID ? $sourceID : $shareInfo['sourceID'];
			$sourceInfo = Model('Source')->pathInfo($sourceID);
		}
		// pr($sourceID,$truePath,$sourceInfo,$shareInfo);exit;
		
		if(!$this->shareIncludeCheck($shareInfo,$sourceInfo)) return false;
		$sourceInfo = $this->_shareItemeParse($sourceInfo,$shareInfo);
		return $sourceInfo;
	}
	
	// 检测附带文档是否归属于该分享;
	private function shareIncludeCheck($shareInfo,$sourceInfo){
		// pr_trace($shareInfo,$sourceInfo);exit;
		if(!$shareInfo || !$sourceInfo) return false;
		
		// 物理路径,io路径;
		if($shareInfo['sourceID'] == '0'){
			$sharePath = KodIO::clear($shareInfo['sourcePath']);
			$thisPath  = KodIO::clear($sourceInfo['path']);
			if(substr($thisPath,0,strlen($sharePath)) != $sharePath) return false;
			return true;
		}
		
		$shareSource = $shareInfo['sourceInfo'];
		// 分享目标为文件,追加字内容必须是自己;
		if( $shareSource['type'] == 'file' &&
			$shareSource['sourceID'] != $sourceInfo['sourceID']){
			return false; 
		}
		if( $shareSource['type'] == 'folder' &&
			strpos($sourceInfo['parentLevel'],$shareSource['parentLevel']) !== 0 ){
			return false; 
		}
		return true;
	}
	
	public function sharePathList($parseInfo){
		$shareID  	= $parseInfo['id'];
		$param    	= explode('/',trim($parseInfo['param'],'/'));
		$shareInfo	= $this->model->getInfo($shareID);
		
		// 物理路径,io路径;
		if($shareInfo['sourceID'] == '0'){
			$truePath = KodIO::clear($shareInfo['sourcePath'].$parseInfo['param']);
			$sourceInfo = IO::info($truePath);
			if(!$sourceInfo) return false;
			$list = IO::listPath($truePath);
		}else{
			$sourceInfo = Model('Source')->pathInfo($param[0]);
			if(!$this->shareIncludeCheck($shareInfo,$sourceInfo)) return false;
			$list = Model('Source')->listSource(array('parentID' => $param[0]));
		}
	
		foreach ($list as $key => &$keyList) {
			if($key != 'folderList' && $key != 'fileList' ) continue;
			foreach ($keyList as &$source) {
				$source = $this->_shareItemeParse($source,$shareInfo);
			}
		}

		$list['current'] = $this->_shareItemeParse($sourceInfo,$shareInfo);
		// pr($parseInfo,$truePath,$sourceInfo,$shareInfo,$list);exit;
		return $list;
	}

	/**
	 * 处理source到分享列表
	 * 去除无关字段；处理parentLevel，pathDisplay
	 */
	public function _shareItemeParse($source,$share){
		$sourceBefore = $source;
		$user = Model('User')->getInfoSimpleOuter($share['userID']);
		$source['auth']	= Model("SourceAuth")->authMake($share['authList']);//覆盖原来文档权限;每次进行计算
		$source['shareUser'] = $user;
		$source['shareCreateTime'] 	= $share['createTime'];
		$source['shareModifyTime'] 	= $share['modifyTime'];
		$source['shareID']  = $share['shareID'];
		$sourceRoot = isset($share['sourceInfo']) ? $share['sourceInfo'] : $source;
		
		// 物理路径,io路径;
		$pathAdd = $source['sourceID'];
		if($share['sourceID'] == '0'){
			$sharePath = KodIO::clear($share['sourcePath']);
			$thisPath  = KodIO::clear($source['path']);
			$pathAdd   = substr($thisPath,strlen($sharePath));
			if(substr($thisPath,0,strlen($sharePath)) != $sharePath) return false;
			unset($source['authMode']);

			// 子目录不再追加;
			if($pathAdd){unset($source['shareInfo']);}
		}
		$source['path'] = KodIO::makeShare($share['shareID'],$pathAdd);
		$source['path'] = KodIO::clear($source['path']);

		// 分享者名字;
		$displayUser = $user['nickName'] ? $user['nickName']:$user['name'];
		$displayUser = '['.$displayUser.']'.LNG('common.share').'-'.$sourceRoot['name'];
		if($share['userID'] == USER_ID){
			$displayUser = '['.LNG('explorer.toolbar.myShare').']-'.$sourceRoot['name'];
		}
		$source['parentLevel'] = ',0,'.substr($source['parentLevel'],strlen($sourceRoot['parentLevel']));
		$source['pathDisplay'] = $displayUser.'/'.substr($source['pathDisplay'],strlen($sourceRoot['pathDisplay']));
		if($share['sourceID'] == '0'){
			$source['parentLevel'] = '';
			$source['pathDisplay'] = $displayUser.'/'.$pathAdd;
		}
		
		$source['pathDisplay'] = KodIO::clear($source['pathDisplay']);
		if($source['type'] == 'folder'){
			$source['pathDisplay'] = rtrim($source['pathDisplay'],'/').'/';
		}

		// 读写权限;
		if($source['auth']){
			$source['isWriteable'] = AuthModel::authCheckEdit($source['auth']['authValue']);
			$source['isReadable']  = AuthModel::authCheckView($source['auth']['authValue']);
		}
		if(isset($source['sourceInfo']['tagInfo'])){
			unset($source['sourceInfo']['tagInfo']);
		}
		// pr($source,$sourceBefore,$share);exit;
		return $source;
	}
	
	/**
	 * 添加分享;
	 */
	public function add(){
		$data = $this->_getParam('sourceID');
		$pathParse = KodIO::parse($data['path']);
		
		// 物理路径,io路径;
		$data['sourcePath'] = KodIO::clear($data['path']);
		if( $pathParse['type'] == KodIO::KOD_IO || !$pathParse['type'] ){
			$result = $this->model->shareAdd('0',$data);
		}else{
			$sourceID = KodIO::sourceID($data['path']);
			$result = $this->model->shareAdd($sourceID,$data);
		}
		
		if(!$result) show_json(LNG('explorer.error'),false);
		$shareInfo = $this->model->getInfo($result);
		show_json($shareInfo,true);
	}

	/**
	 * 编辑分享
	 */
	public function edit(){
		$data = $this->_getParam('shareID');
		$result = $this->model->shareEdit($data['shareID'],$data);
		if(!$result) show_json(LNG('explorer.error'),false);
		
		$shareInfo = $this->model->getInfo($data['shareID']);
		show_json($shareInfo,true);
	}
	
	/**
	 * 添加/编辑分享;
	 * shareType: 
	 * 		0: 暂未指定分享
	 * 		1: 内部指定用户分享
	 * 		2: 外链分享
	 * 		3: 内部指定、外链分享同时包含
	 * 
	 * 外链分享; title,password,timeTo,options
	 * authTo: [
	 * 		{"targetType":"1","targetID":"23","authID":"1"},
	 * 		{"targetType":"2","targetID":"3","authDefine":"512"}
	 * ]
	 * param: title,password,timeTo,options
	 */
	private function _getParam($key='shareID'){
		$keys = array(
			"isLink"	=> array("check"=>"bool",	"default"=>0),
			"isShareTo"	=> array("check"=>"bool",	"default"=>0),
			"title"		=> array("check"=>"require","default"=>''),
			"password"	=> array("default"=>''),//密码设置为空处理;
			"timeTo"	=> array("check"=>"require","default"=>0),
			"options"	=> array("check"=>"json",	"default"=>''),
			"authTo"	=> array("check"=>"json", 	"default"=>''),
		);
		//修改，默认值为null不修改；
		if($key == 'shareID'){
			$keys['shareID'] = array("check"=>"int");
			foreach ($keys as $key => &$value) {
				$value['default'] = null;
			}
		}else{//添加时，目录值
			$keys['path'] = array("check"=>"require");
		}
		$data = Input::getArray($keys);
		return $data;
	}

	/**
	 * 批量取消分享;
	 * 如果制定了分享类型: 则不直接删除数据; 
	 */
	public function del() {
		$list  = Input::get('dataArr','json');
		if( !isset($this->in['type']) ){
			$res = $this->model->remove($list);
		}else{
			// 批量删除指定内部协作分享, or外链分享;
			foreach ($list as $shareID) {
				if($this->in['type'] == 'shareTo'){
					$data = array('isShareTo'=>0,'authTo'=>array());
				}else{
					$data = array('isLink'=>0);
				}

				// 都为空时则删除数据, 再次分享shareID更新;
				$shareInfo = $this->model->getInfo($shareID);
				if( $data['isLink'] == 0 && $shareInfo['isShareTo'] == 0 ||
					$data['isShareTo'] == 0 && $shareInfo['isLink'] == 0 ){
					$res = $this->model->remove(array($shareID));
					continue;
				}
								
				$res = $this->model->shareEdit($shareID,$data);
			}
		}
		$msg  = !!$res ? LNG('explorer.success'): LNG('explorer.error');
		show_json($msg,!!$res);
	}
}
