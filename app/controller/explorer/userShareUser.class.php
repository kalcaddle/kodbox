<?php 
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/

class explorerUserShareUser extends Controller{
	private $model;
	function __construct(){
		parent::__construct();
		$this->model  = Model('Share');
	}
	public function get($id){
		if(!$id || $id == 'user') return $this->listRoot();
		return $this->listByUser(substr($id,strlen('user-')));
	}

	private function listRoot(){
		$shareList = $this->model->listToMe(5000);
		$userArray = array_to_keyvalue($shareList['list'],'','userID');
		$userArray = array_unique($userArray);

		$folderList= array();
		foreach($userArray as $userID ){
			$folderList[] = $this->makeItemUser(Model('User')->getInfoSimpleOuter($userID));
		}
		$result = array('fileList'=>array(),'folderList'=>$folderList);//,"disableSort"=>true
		$result['currentFieldAdd'] = array("pathDesc"=>'['.LNG('admin.setting.shareToMeUser').'],'.LNG('explorer.pathDesc.shareToMeUser'));
		return $result;
	}
	private function listByUser($userID){
		$shareList = $this->model->listToMe(5000);
		$listData  = array();
		foreach ($shareList['list'] as $shareItem){
			if($shareItem['userID'] == $userID){$listData[] = $shareItem;}
		}
		$result = Action('explorer.userShare')->shareToMeListMake($listData);
		$result['currentFieldAdd'] = $this->makeItemUser(Model('User')->getInfoSimpleOuter($userID));
		return $result;
	}
	
	private function makeItemUser($userInfo){
		$defaultThumb = STATIC_PATH.'images/common/default-avata.png';
		$defaultThumb = 'root-user-avatar';// $userInfo["avatar"] = false;
		$result = array(
			"name" 			=> _get($userInfo,"nickName",$userInfo["name"]),
			"type" 			=> "folder",
			"path" 			=> "{shareToMe:user-".$userInfo['userID']."}/",
			"icon"			=> $userInfo["avatar"] ? $userInfo["avatar"]:$defaultThumb,//fileThumb,icon
			"iconClassName"	=> 'user-avatar',		
		);
		$result['pathDescAdd'][] = array(
			"title"		=> LNG("common.desc"),
			"content"	=> LNG('explorer.pathDesc.shareToMeUserItem')
		);
		$userInfo = Model('User')->getInfo($userInfo['userID']);
		$groupArr = array_to_keyvalue($userInfo['groupInfo'],'','groupName');
		if($groupArr){
			$result['pathDescAdd'][] = array("title"=>LNG("admin.member.group"),"content"=>implode(',',$groupArr));
		}
		return $this->makeAddress($result);
	}
	private function makeAddress($itemInfo){
		$address = array(array("name"=> LNG('explorer.toolbar.shareToMe'),"path"=>'{shareToMe}'));
		$address[] = array("name"=> $itemInfo["name"],"path"=>$itemInfo['path']);
		$itemInfo['pathAddress'] = $address;
		return $itemInfo;
	}
}
