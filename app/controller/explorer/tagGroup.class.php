<?php 
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/

/**
 * 部门公共标签处理
 * get					//部门标签获取 [参数]:groupID
 * set					//部门标签设置 [参数]:groupID,value
 * filesRemoveFromTag 	//部门文件移除公共标签 [参数]:groupID,files,tagID
 * filesAddToTag 		//部门文件添加公共标签 [参数]:groupID,files,tagID
 * 
 * -------------- 
 * tagAppendItem()		//部门文档,追加公共标签信息;
 * listSource()			//根据部门标签筛选内容
 */
class explorerTagGroup extends Controller{
	private $model;
	function __construct(){
		parent::__construct();
		$this->model  = Model('GroupTag');
		$this->checkAuth();
	}

	/*
	获取设置标签; 前后顺序即为排序关系
	{idMax:10,group:[{name:xx,icon:xx}...]  ,list:[{id:xx,name:xx},...],   ...};// 标签分组,标签列表;
	*/
	public function get(){
		$data = Input::getArray(array(
			"groupID"	=> array("check"=>"int"),
		));
		$tagList = $this->model->get($data['groupID']);
		show_json($tagList);
	}
	
	// 标签数据写入; (groupTag:mysql-text字段; json 64k/100 =6千个) 
	// 标签设置检测: id不重复;idMax比最大id要大;名称不重复; 分组名称不同;
	public function set(){
		$data = Input::getArray(array(
			"value"		=> array("check"=>"json"),
			"groupID"	=> array("check"=>"int"),
		));
		
		// $data['value'] = $this->testData();
		if(!$this->tagSetCheck($data['value'],$data['groupID'])){
			show_json(LNG("common.invalidParam"),false);
		}
		Model('SystemLog')->addLog('explorer.tagGroup.set', $data);//操作日志记录;
		$this->model->set($data['groupID'],$data['value']);
		show_json($this->model->get($data['groupID']),true);
	}
	
	// 标签设置检测: id不重复;idMax比最大id要大;名称不重复; 分组名称不同;  
	// 标签id有删除:前端强提醒, 清空该标签关联到的文档(tagID对应的文档-removeByTag);
	private function tagSetCheck($listData,$groupID){
		if(!is_array($listData) || !$listData['idMax'] || !is_array($listData['list'])) return false;
		$idList = array();$tagNameList = array();
		foreach($listData['list'] as $tag){
			if(!$tag['id'] || $tag['id'] > $listData['idMax']){return false;}
			if(in_array($tag['id'],$idList)){return false;}
			if(in_array($tag['name'],$tagNameList)){return false;}
			$idList[] = $tag['id'];$tagNameList[] = $tag['name'];
		}
		
		// 标签有删除时; 则解除删除标签对文档的关联;
		$beforeList = $this->model->get($groupID);
		foreach($beforeList['list'] as $tag){
			if(in_array($tag['id'],$idList)) continue;
			$this->model->removeByTag($groupID,$tag['id']);
		}
		return true;
	}
	
	// 部门子内容,公共标签追加; 根部门--追加 groupTagList
	public function tagAppendItem($pathInfo){
		if(!isset($pathInfo['targetType']) || isset($pathInfo['shareID'])) return $pathInfo;
		if($pathInfo['targetType'] != 'group') return $pathInfo;
		
		$groupID = $pathInfo['targetID'];
		$groupTagInfo = $this->groupTagGet($groupID);		
		$pathInfo['sourceInfo']['isGroupRoot']   = $groupTagInfo['isGroupRoot'];	// 是否为该部门管理员
		$pathInfo['sourceInfo']['isGroupHasTag'] = $groupTagInfo['isGroupHasTag'];	// 是否有公共标签

		// 部门根目录
		if($pathInfo['parentID'] == '0'){
			$pathInfo['sourceInfo']['groupTagList'] = $groupTagInfo;
		}
				
		// 部门子目录;关联标签; sourceInfo['groupTagInfo'];
		$groupSource = $this->sourceTagList($groupID);
		if(isset($groupSource[$pathInfo['sourceID']])){
			$tags = $groupSource[$pathInfo['sourceID']];
			$pathInfo['sourceInfo']['groupTagInfo'] = $this->getTags($groupID,$tags);
		}
		return $pathInfo;
	}
	
	private function groupTagGet($groupID){
		static $list = array();
		if(!isset($list[$groupID])){
			$groupTag = $this->model->get($groupID);
			$groupTag['isGroupRoot'] = Action('filter.userGroup')->allowChangeGroup($groupID);
			$groupTag['isGroupHasTag'] = isset($groupTag['list']) && count($groupTag['list']) > 0;
			$list[$groupID] = $groupTag;
		}
		return $list[$groupID];
	}
	
	// 该部门下文档公共标签关联数据;
	private function sourceTagList($groupID){
		static $list = array();
		if(!isset($list[$groupID])){
			$arr = $this->model->listData($groupID);
			$list[$groupID] = array_to_keyvalue_group($arr,'path','tagID');
		}
		return $list[$groupID];
	}
	private function getTags($groupID,$tags){
		static $list = array();
		if(!isset($list[$groupID])){
			$arr = $this->model->get($groupID);
			$arr['listTag'] = array_to_keyvalue($arr['list'],'id');
			$list[$groupID] = $arr;
		}

		$result  = array();
		$tagList = $list[$groupID];
		if(!$tags || !$tagList || !$tagList['list']) return $result;
		foreach($tags as $tagID){
			$tagInfo = $tagList['listTag'][$tagID];
			if(!$tagInfo) continue;
			$tagInfo['groupInfo'] = $tagList['group'][$tagInfo['group']];
			unset($tagInfo['group']);
			$result[] = $tagInfo;
		}
		return $result;
	}
	
	// 根据$id获取部门筛选tag的文档列表;
	public function listSource($groupID,$tags){
		$tags   	= $tags[0] ? $tags : false;
		$groupInfo 	= Model('Group')->getInfo($groupID);
		$result 	= Model("GroupTag")->listSource($groupID,$tags);
		$tagList 	= $this->getTags($groupID,$tags);
		$tagName 	= $tags ? implode(',',array_to_keyvalue($tagList,'','name')) : '-';

		$tagInfo = array('name'=>$tagName,'pathDesc'=>LNG('explorer.groupTag.pathDesc'));
		$tagInfo['pathAddress'] = array(
			array("name"=> $groupInfo['name'],"path"=>KodIO::make($groupInfo['sourceInfo']['sourceID'])),
			array("name"=> LNG('common.tag').': '.$tagInfo['name'],"path"=>$this->in['path']),
		);
		if(!$result){$result = array("fileList"=>array(),'folderList'=>array());}

		$tagInfo['sourceRoot'] = "groupPath";
		$result['currentFieldAdd'] = $tagInfo;
		$result['groupTagList'] = Model("GroupTag")->get();
		// pr($result);exit;
		return $result;
	}

	
	/**
	 * 权限检测;
	 * 部门公共标签编辑: 仅部门管理员有权限
	 * 文档标签设置: 对文档有编辑权限
	 */
	private function checkAuth(){
		if(strtolower(MOD.'.'.ST) != 'explorer.taggroup') return;
		$ACTION = strtolower(ACT);
		$checkSourceAuth = array('filesRemoveFromTag','filesAddToTag');
		
		// 文档权限检测;
		$groupID = $this->in['groupID'];
		foreach($checkSourceAuth as $action){
			if($ACTION != strtolower($action)) continue;
			$files = explode(',',$this->in['files']);
			foreach($files as $file){
				$path = KodIO::make($file);
				if(!Action('explorer.auth')->fileCanWrite($path)){
					show_json(LNG('explorer.noPermissionAction'),false);
				}
				$pathInfo = IO::info($path);
				if($pathInfo['targetType'] != 'group' || $pathInfo['targetID'] != $groupID){
					show_json(LNG('common.notExists'),false);
				}
			}
			return;
		}
	}


	//======== tag关联资源管理 =========	
	//将文档从某个tag中移除
	public function filesRemoveFromTag(){
		$data = Input::getArray(array(
			"tagID"		=> array("check"=>"int"),
			"files"		=> array("check"=>"require"),
			"groupID"	=> array("check"=>"int"),
		));
		$files = explode(',',$data['files']);
		if(!$files){show_json(LNG('explorer.error'),false);}
		
		$res = $this->model->removeFromTag($data['groupID'],$files,$data['tagID']);
		show_json($res? LNG('explorer.success'): LNG('explorer.error'),!!$res);
	}
	
	//添加文档到tag;
	public function filesAddToTag(){
		$data = Input::getArray(array(
			"tagID"	=> array("check"=>"int"),
			"files"	=> array("check"=>"require"),
			"groupID"	=> array("check"=>"int"),
		));
		$files = explode(',',$data['files']);
		if(!$files){show_json(LNG('explorer.error'),false);}
		
		foreach ($files as $file) {
			$res = $this->model->addToTag($data['groupID'],$file,$data['tagID']);
		}
		show_json(LNG('explorer.success'),true);
	}

	// 前后顺序即为排序关系; 标签组包含子标签
	public function testData(){
		$listData = array(
			'idMax'	=> 21,
			'group' => array(
				array('name'=>'用户规模','icon'=>'ri-user-line'),
				array('name'=>'客户分类','icon'=>'ri-profile-line'),
				array('name'=>'所属行业','icon'=>'ri-building-line'),
				array('name'=>'项目状态','icon'=>'ri-money-cny-circle-line'),
			),
			'list'	=> array(
				array('id'=>1,'name'=>'<50','group'=>'0'),
				array('id'=>2,'name'=>'50~100','group'=>'0'),
				array('id'=>3,'name'=>'100~300','group'=>'0'),
				array('id'=>4,'name'=>'300~500','group'=>'0'),
				array('id'=>5,'name'=>'500~1000','group'=>'0'),
				array('id'=>6,'name'=>'1000~3000','group'=>'0'),
				array('id'=>7,'name'=>'>3000','group'=>'0'),

				array('id'=>8,'name'=>'个人采购','group'=>'1'),
				array('id'=>9,'name'=>'企业采购','group'=>'1'),
				array('id'=>10,'name'=>'第三方','group'=>'1'),
				array('id'=>11,'name'=>'合作伙伴','group'=>'1'),
				
				array('id'=>12,'name'=>'金融保险','group'=>'2'),
				array('id'=>13,'name'=>'IT互联网','group'=>'2'),
				array('id'=>14,'name'=>'能源行业','group'=>'2'),
				array('id'=>15,'name'=>'学校教育','group'=>'2'),
				array('id'=>16,'name'=>'政府单位','group'=>'2'),
				array('id'=>17,'name'=>'房地产','group'=>'2'),
				
				array('id'=>18,'name'=>'跟进中','group'=>'3'),
				array('id'=>19,'name'=>'成单-已付款','group'=>'3'),
				array('id'=>20,'name'=>'未成单-已终止','group'=>'3'),
			)
		);
		return $listData;
	}
}
