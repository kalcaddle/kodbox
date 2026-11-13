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
 * set					//部门标签设置 [参数]:groupID,diff
 * toolbarSet			//部门标签工具栏配置 [参数]:groupID,toolbar
 * filesSetTags			//部门文件批量设置公共标签 [参数]:groupID,sourceIDs,addTags,removeTags
 * listSourceInPath		//当前目录递归范围内按标签+内置条件筛选
 * 
 * -------------- 
 * tagAppendItem()		//部门文档,追加公共标签信息;
 * listSource()			//根据部门标签筛选内容
 */
/*
 * 权限: 读取类(get/toolbarGet/sourceTags/filesSetTags)要求部门成员(含父部门管理员);
 *      编辑类(set/toolbarSet)要求部门管理员; filesSetTags 还会逐个文档校验写权限。
 * 存储: 标签树 -> group.groupTag(text,JSON); 工具栏配置 -> group meta groupTagsToolbar;
 *      文档-标签关联 -> user_fav 表(type=group-<gid>, path=sourceID)。
 */
class explorerTagGroup extends Controller{
	const MAX_DEPTH = 5;		//分类树最大层级;
	const MAX_SIZE  = 60000;	//标签数据大小上限(字节, text字段64KB);
	private $model;
	function __construct(){
		parent::__construct();
		$this->model  = Model('GroupTag');
		$this->checkAuth();
	}

	/**
	 * 获取设置标签; 前后顺序即为排序关系 ——update: 部门标签树 + 工具栏配置 + 是否部门管理员, 一次返回给管理弹窗/快捷面板;
	 * tagData: {v:2,group:[{id,name,icon,parent}...],list:[{id,name,group}...],toolbar:{...}};// 标签分组(可多级),标签列表;
	 * @return void
	 */
	public function get(){
		$data = Input::getArray(array(
			"groupID"	=> array("check"=>"int"),
		));
		show_json($this->getTagData($data['groupID']));
	}
	
	/**
	 * 标签数据写入; (groupTag:mysql-text字段; json 64k/100 =6千个) 
	 * 标签树写入: 前端 diff -> diffApply 到旧数据 -> tagSetCheck 校验 -> 写 group.groupTag;
	 * 校验项: id唯一/同级不重名/父级存在/深度<=MAX_DEPTH; 校验通过后再清理删除项的关联;
	 * @return void
	 */
	public function set(){
		$data = Input::getArray(array(
			'diff'		=> array("check"=>"json"),
			"groupID"	=> array("check"=>"int"),
		));
		
		if(!Model('Group')->getInfoSimple($data['groupID'])){
			show_json(LNG('explorer.groupTag.deptNotExists'),false);
		}
		$dataLike = array(
			'group' => array(array('_idKey_'=>'id','_autoID_'=>"number")),
			'list'  => array(array('_idKey_'=>'id','_autoID_'=>"number")),
		);
		$listData = $this->groupTag($data['groupID']);
		$data['value']  = kodDiff::diffApply($listData,$data['diff'],$dataLike);
		
		// 新建分组时处理;
		foreach($data['value']['list'] as &$item){
			if(!empty($item['_groupAddTemp'])){
				$group = array_find_by_field($data['value']['group'],'_groupAddTemp',$item['_groupAddTemp']);
				if($group && $group['id']){$item['group'] = $group['id'];}
			}
			unset($item['_groupAddTemp']);
		};unset($item);
		// 新建分类的父级可能也是新建的: 先把"临时标记=>真实id"整表建好, 不能边遍历边取(前面的 item._groupAddTemp 已被 unset, 后面的子分类就找不到父级);
		$tempGroupMap = array();
		foreach ($data['value']['group'] as $groupItem) {
			if(!empty($groupItem['_groupAddTemp']) && !empty($groupItem['id'])){
				$tempGroupMap[(string)$groupItem['_groupAddTemp']] = $groupItem['id'];
			}
		}
		foreach ($data['value']['group'] as &$item) {
			if(!empty($item['_parentAddTemp'])){
				$tempKey = (string)$item['_parentAddTemp'];
				if(isset($tempGroupMap[$tempKey])){$item['parent'] = $tempGroupMap[$tempKey];}
				unset($item['_parentAddTemp']);
			}
			unset($item['_groupAddTemp']);
		};unset($item);

		// 删除分类: 只从提交数据中级联剔除子分类/标签; 文档关联等校验通过后再清理;
		$this->applyDeleteCascade($data['value'],$data['groupID']);
		
		$error = $this->tagSetCheck($data['value'],$data['groupID']);
		if($error !== true){
			show_json($error ? $error : LNG("common.invalidParam"),false);
		}
		// 删除标签/分类的关联清理必须放在校验通过之后: 否则校验失败时标签定义没保存, 关联却已经被删;
		// 同时要放在 model->set 之前: removeByTag 内部还会校验标签是否存在;
		$this->cleanupRemovedTags($data['groupID'],$listData,$data['value']);
		Model('SystemLog')->addLog('explorer.tagGroup.set', $data);//操作日志记录;
		$this->model->set($data['groupID'],$data['value']);
		show_json($this->getTagData($data['groupID']),true);
	}

	// 标签设置检测; 通过返回true, 否则返回错误提示;
	// id不重复; 父级存在且无环; 层级<=MAX_DEPTH; 同级名称唯一; 体积不超限;
	private function tagSetCheck($listData,$groupID){
		if(!is_array($listData) || !is_array($listData['group']) || !is_array($listData['list'])){
			return LNG("common.invalidParam");
		}
		if(strlen(json_encode($listData)) > self::MAX_SIZE){
			return LNG('explorer.groupTag.sizeLimit');
		}
		
		$groupMap = array();$catName = array();
		foreach($listData['group'] as $group){
			if(!is_array($group)){continue;}
			$id = (string)_get($group,'id','');
			if($id === ''){return LNG('explorer.groupTag.idEmpty');}
			if(isset($groupMap[$id])){return LNG('explorer.groupTag.idRepeat');}
			$groupMap[$id] = $group;
		}
		$tagMap = array();
		foreach($listData['list'] as $tag){
			if(!is_array($tag)){continue;}
			$id = (string)_get($tag,'id','');
			if($id === ''){return LNG('explorer.groupTag.idEmpty');}
			if(isset($tagMap[$id])){return LNG('explorer.groupTag.idRepeat');}
			$tagMap[$id] = $tag;
		}
		
		// 父级必须存在, 不能成环, 层级不超过MAX_DEPTH层;
		foreach($groupMap as $id=>$group){
			$parent = (string)_get($group,'parent','');
			if($parent === '0'){$parent = '';} // 兼容旧数据/外部调用把一级分类 parent 写成 0;
			if($parent !== '' && !isset($groupMap[$parent])){return LNG('explorer.groupTag.parentNotExists');}
			$depth = 1;$cur = $parent;$seen = array();
			while($cur !== ''){
				if(isset($seen[$cur])){return LNG('explorer.groupTag.loop');}
				$seen[$cur] = 1;
				$depth++;
				if($depth > self::MAX_DEPTH){return LNG('explorer.groupTag.depthLimit');}
				$cur = (string)_get($groupMap[$cur],'parent','');
				if($cur !== '' && !isset($groupMap[$cur])){return LNG('explorer.groupTag.parentNotExists');}
			}
			// 同级分类名唯一(分类与标签分开命名空间, 同名不冲突);
			$name = trim((string)_get($group,'name',''));
			if($name === ''){return LNG('explorer.groupTag.nameEmpty');}
			$key = $parent.'|'.$name;
			if(isset($catName[$key])){return LNG('explorer.groupTag.nameRepeat');}
			$catName[$key] = 1;
		}
		$tagName = array();
		foreach($tagMap as $tag){
			$typeID = (string)_get($tag,'group','');
			if($typeID === '' || !isset($groupMap[$typeID])){return LNG('explorer.groupTag.groupNotExists');}
			// 同一分类下标签名唯一;
			$name = trim((string)_get($tag,'name',''));
			if($name === ''){return LNG('explorer.groupTag.nameEmpty');}
			$key = $typeID.'|'.$name;
			if(isset($tagName[$key])){return LNG('explorer.groupTag.nameRepeat');}
			$tagName[$key] = 1;
		}
		return true;
	}

	// 删除分类级联: 收集被删分类 id, 从提交数据中剔除子分类及其下标签; 不在此处删关联;
	private function applyDeleteCascade(&$value,$groupID){
		if(!is_array($value) || !is_array($value['group']) || !is_array($value['list'])) return;
		$before = $this->groupTag($groupID);
		$oldGroups = array();
		foreach($before['group'] as $group){$oldGroups[(string)$group['id']] = $group;}
		$newGroups = array();
		foreach($value['group'] as $group){
			if(!is_array($group) || !isset($group['id'])) continue;
			$newGroups[(string)$group['id']] = $group;
		}
		
		$removed = array();
		foreach($oldGroups as $id=>$group){
			if(!isset($newGroups[$id])){$removed[$id] = 1;}
		}
		if(!$removed) return;
		
		// 子分类级联(以改动前的树为准);
		$children = array();
		foreach($oldGroups as $id=>$group){
			$parent = (string)_get($group,'parent','');
			$children[$parent][$id] = 1;
		}
		$queue = array_keys($removed);
		while($queue){
			$id = array_shift($queue);
			if(empty($children[$id])) continue;
			foreach($children[$id] as $childID=>$v){
				if(isset($removed[$childID])) continue;
				$removed[$childID] = 1;$queue[] = $childID;
			}
		}
		
		// 从新数据中剔除被删分类及其下标签;
		$groupList = array();
		foreach($value['group'] as $group){
			if(!is_array($group) || isset($removed[(string)_get($group,'id','')])) continue;
			$groupList[] = $group;
		}
		$value['group'] = $groupList;
		$tagList = array();
		foreach($value['list'] as $tag){
			if(!is_array($tag) || isset($removed[(string)_get($tag,'group','')])) continue;
			$tagList[] = $tag;
		}
		$value['list'] = $tagList;
		// 关联清理不在这里做: 此时校验还没通过, 校验失败会丢关联;
		// 统一由 set() 校验通过后调用 cleanupRemovedTags()。
	}

	// 清理"旧列表里有、新列表里没有"的标签关联; 必须在标签定义仍存在时调用(removeByTag 会校验);
	private function cleanupRemovedTags($groupID,$beforeList,$newList){
		$newTags = array();
		foreach((array)_get($newList,'list',array()) as $tag){
			if(is_array($tag) && isset($tag['id'])){$newTags[(string)$tag['id']] = 1;}
		}
		foreach((array)_get($beforeList,'list',array()) as $tag){
			if(!is_array($tag) || !isset($tag['id'])) continue;
			if(!isset($newTags[(string)$tag['id']])){
				$this->model->removeByTag($groupID,$tag['id']);
			}
		}
	}
	
	//======== 工具栏配置 ========

	/**
	 * 工具栏配置读取(只有配置,无敏感信息); 部门成员可读, 供顶栏入口渲染;
	 * @return void
	 */
	public function toolbarGet(){
		$groupID = intval(_get($this->in,'groupID',0));
		show_json($this->toolbarData($groupID));
	}

	// 读部门 toolbar 配置(不存在时返回默认关闭状态);
	private function toolbarData($groupID){
		$json = Model('Group')->metaGet($groupID,'groupTagsToolbar');
		$data = json_decode($json,true);
		return $this->toolbarNormalize(is_array($data) ? $data : array());
	}
	// 规整工具栏配置: {enable:'0'|'1', listAllCats:'0'|'1', fields:[...]}; 只保留内置字段并去重;
	private function toolbarNormalize($data){
		$result = array('enable'=>'0','listAllCats'=>'0','fields'=>array());
		if(!is_array($data)) return $result;
		$result['enable']      = !empty($data['enable']) ? '1' : '0';
		$result['listAllCats'] = !empty($data['listAllCats']) ? '1' : '0';
		$builtin = array('fileType','fileTime','fileSize','user');
		foreach((array)_get($data,'fields',array()) as $field){
			$field = trim((string)$field);
			if($field === '' || !in_array($field,$builtin)) continue;
			if(in_array($field,$result['fields'])) continue;
			$result['fields'][] = $field;
		}
		return $result;
	}

	/**
	 * 工具栏配置写入(group meta groupTagsToolbar); 仅部门管理员;
	 * @return void
	 */
	public function toolbarSet(){
		$data = Input::getArray(array(
			"groupID"	=> array("check"=>"int"),
			"toolbar"	=> array("check"=>"json"),
		));
		if(!Action('filter.userGroup')->allowChangeGroup($data['groupID'])){
			show_json(LNG('explorer.noPermissionAction'),false);
		}
		if(!Model('Group')->getInfoSimple($data['groupID'])){
			show_json(LNG('explorer.groupTag.deptNotExists'),false);
		}
		// 免费版不支持快捷入口设置(入口本身也不渲染);
		if(Model('SystemOption')->get('versionType') == 'A'){
			show_json(LNG('explorer.groupTag.freeNoToolbar'),false);
		}
		$toolbar = $this->toolbarNormalize($data['toolbar']);
		Model('Group')->metaSet($data['groupID'],'groupTagsToolbar',json_encode($toolbar));
		show_json($toolbar);
	}
	
	// 管理弹窗用的完整数据: 标签树 + toolbar + isGroupRoot;
	private function getTagData($groupID){
		$tagData = $this->groupTag($groupID);
		$tagData['toolbar']     = $this->toolbarData($groupID);
		$tagData['isGroupRoot']	= Action('filter.userGroup')->allowChangeGroup($groupID);
		return $tagData;
	}

	/**
	 * 部门子内容,公共标签追加; 根部门--追加 groupTagList
	 * 列表每一项都过这里: 补 isGroupRoot/isGroupHasTag(右键入口是否显示)、部门根的 groupTagList、文档的 groupTagInfo(标签徽标);
	 * @param array $pathInfo
	 * @return array
	 */
	public function tagAppendItem(&$pathInfo){
		if(!isset($pathInfo['targetType']) || isset($pathInfo['shareID'])) return $pathInfo;
		$targetType = _get($pathInfo,'targetType','');
		if($targetType !== 'group' && intval($targetType) !== SourceModel::TYPE_GROUP) return $pathInfo;
		
		$groupID = $pathInfo['targetID'];
		$groupTagInfo = $this->groupTagGet($groupID);
		
		// 是否为部门管理员,继承当前文档权限( 下层权限小于上层权限, 使用下层权限)
		$auth = _get($pathInfo,'auth',null);
		if(is_array($auth) && !AuthModel::authCheckRoot(_get($auth,'authValue',''))){
			$groupTagInfo['isGroupRoot'] = false;
		}
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
	
	// 单次请求内缓存"部门标签概况", 避免列表每一项都查一次;
	private function groupTagGet($groupID){
		static $list = array();
		if(!isset($list[$groupID])){
			$groupTag = $this->groupTag($groupID);
			$groupTag['isGroupRoot']   = Action('filter.userGroup')->allowChangeGroup($groupID);
			$groupTag['isGroupHasTag'] = isset($groupTag['list']) && count($groupTag['list']) > 0;
			$list[$groupID] = $groupTag;
		}
		return $list[$groupID];
	}

	// 读标签树(进程内静态缓存), 兼容旧版并惰性升级到v2; 幂等; 空部门返回默认空结构;
	private function groupTag($groupID){
		$listData = $this->model->get($groupID);
		if(!is_array($listData)){$listData = array();}
		$changed = false;
		if(isset($listData['idMax'])){unset($listData['idMax']);$changed = true;}
		if(!$listData['list']){$listData['list'] = array();}
		if(!$listData['group']){$listData['group'] = array();}
		
		// 旧版数据处理兼容; 分组id,标签id自适应处理;
		if(!$listData['_hasDiff']){
			$listData['_hasDiff'] = true;$changed = true;
			kodDiff::arrayAutoID($listData['list']);
			kodDiff::arrayAutoID($listData['group']);
			foreach ($listData['list'] as $i => &$tag){
				$tag['group'] = _get($listData['group'][$tag['group']],'id','1');
			};unset($tag);
		}
		
		// v2迁移: 补version/parent; 父级不存在时置为根;
		if(intval(_get($listData,'v',0)) < 2){$listData['v'] = 2;$changed = true;}
		$groupMap = array();
		foreach($listData['group'] as $group){
			if(!is_array($group) || !isset($group['id'])) continue;
			$groupMap[(string)$group['id']] = $group;
		}
		foreach($listData['group'] as $i => &$group){
			if(!is_array($group)){unset($listData['group'][$i]);$changed = true;continue;}
			if(!isset($group['parent'])){$group['parent'] = '';$changed = true;}
			$parent = (string)$group['parent'];
			if($parent !== '' && !isset($groupMap[$parent])){
				$group['parent'] = '';$changed = true;
			}
		};unset($group);
		
		// 读请求只做内存归一化, 不写 meta: 旧数据迁移由管理保存(set)最终落库, 避免成员读列表时产生写操作;
		return $listData;
	}
	
	// 该部门"文档 -> 标签id列表"映射, 供 tagAppendItem 批量补 groupTagInfo;
	private function sourceTagList($groupID){
		static $list = array();
		if(!isset($list[$groupID])){
			$arr = $this->model->listData($groupID);
			$map = array_to_keyvalue_group($arr,'path','tagID');
			// user_fav 没有唯一索引, 历史数据/并发写入可能产生重复关联; 出口统一去重, 避免芯片重复;
			foreach($map as $sourceID=>$tagIDs){
				$map[$sourceID] = array_values(array_unique(array_map('intval',(array)$tagIDs)));
			}
			$list[$groupID] = $map;
		}
		return $list[$groupID];
	}
	// 标签 id 数组 -> 标签详情数组;
	private function getTags($groupID,$tags){
		static $list = array();
		if(!isset($list[$groupID])){
			$arr = $this->groupTag($groupID);
			$arr['listTag'] = array_to_keyvalue($arr['list'],'id');
			$list[$groupID] = $arr;
		}

		$result  = array();
		$tagList = $list[$groupID];
		if(!$tags || !$tagList || !$tagList['list']) return $result;
		foreach($tags as $tagID){
			$tagInfo = $tagList['listTag'][$tagID.''];
			if(!$tagInfo) continue;
			$tagInfo['groupInfo'] = array_find_by_field($tagList['group'],'id',$tagInfo['group']);
			unset($tagInfo['group']);
			$result[] = $tagInfo;
		}
		return $result;
	}
	
	/**
	 * 根据$id获取部门筛选tag的文档列表;
	 * {userFileTag:group-<gid>-<tagid>...} 列表数据入口(explorer/list 路由过来);
	 * 带 parentPath/内置条件 -> 目录递归范围筛选; 只有标签 -> 全部门范围;
	 * 两种模式最后都过 listResultApply 补标签名/分页。
	 */
	public function listSource($groupID,$tags,$pathParse=null){
		$groupID = intval($groupID);
		$tags    = ($tags && $tags[0]) ? $tags : array();
		if(!$this->canReadGroup($groupID) || !Model('Group')->getInfoSimple($groupID)){
			show_json(LNG('explorer.noPermissionAction'),false);
		}
		$path  = _get($this->in,'path','');
		$param = array();
		$paramStr = _get($pathParse,'param','');
		if(!$paramStr){$paramStr = _get($this->in,'pathParam','');}	// 兼容直接传参;
		// 前端把多个条件用 & 连接(参数值已 urlencode), parseSearch 只认 @, 这里统一成 @;
		if($paramStr){$paramStr = str_replace('&','@',$paramStr);}
		if($paramStr){$param = Action('explorer.listSearch')->parseSearch($paramStr);}
		$parentPath = trim(_get($param,'parentPath',''),'/');
		$parentInfo = $parentPath ? IO::info($parentPath) : false;
		// 标签匹配方式: all(默认)=跨分类 AND/同分类 OR; any=全局 OR;
		// 全部门范围(无 parentPath)与目录范围都要生效, 不能只放在 parentPath 分支里;
		$tagMode = _get($param,'tagMode','') == 'any' ? 'any' : '';
		// 只有内置条件(无 parentPath)时, 以部门根为范围;
		if(!$parentInfo && $this->paramCondCount($param)){
			$rootSource = Model('Source')->where(array('parentID'=>0,'targetType'=>SourceModel::TYPE_GROUP,'targetID'=>$groupID))->find();
			if($rootSource){
				$parentInfo = $rootSource;
				$parentPath = trim(KodIO::make(_get($rootSource,'sourceID','')),'/');
			}
		}
		// parentPath: 限定在该目录(含子目录)内筛选; 缺失则全部门范围;
		// targetType 在 IO::info 返回的是 'group', 但直接查 source 根时是数字 2, 两种都要认;
		$parentTargetType = _get($parentInfo,'targetType','');
		$parentIsGroup    = $parentTargetType === 'group' || intval($parentTargetType) === SourceModel::TYPE_GROUP;
		if($parentInfo && _get($parentInfo,'type') != 'file'
			&& $parentIsGroup && intval(_get($parentInfo,'targetID',0)) == $groupID){
			if(!Action('explorer.auth')->canView($parentPath)){
				show_json(LNG('explorer.noPermissionAction'),false);
			}
			$where	= $this->model->pathFilterWhere($parentInfo,$param);
			$result	= Model('GroupTag')->listSourceInPath($groupID,$tags,$where,$tagMode);
			if(!is_array($result)){$result = array();}
			$result = $this->listResultApply($result,$groupID,$tags,$path,$parentPath,$param);
			return $result;
		}
		$result = Model("GroupTag")->listSource($groupID,$tags ? $tags : false,$tagMode);
		$result = $this->listResultApply($result,$groupID,$tags,$path,'',$param);
		return $result;
	}

	// 内置筛选条件个数(类型/时间/大小/用户各算一个); 用于判断"是否只按内置条件筛选"(范围取部门根);
	private function paramCondCount($param){
		$param = is_array($param) ? $param : array();
		$count = 0;
		// 只认菜单值: diy 时 sizeFrom/timeFrom 必定与 fileSize/fileTime=diy 同时出现, 不再单独计数旧参数;
		if(isset($param['fileSize'])){$count++;}
		if(isset($param['fileTime'])){$count++;}
		if(isset($param['fileType']) || isset($param['fileExt'])){$count++;}
		if(isset($param['user'])){$count++;}
		return $count;
	}

	// 筛选结果追加标签路径信息, 与{userFileTag:...}列表保持一致;
	// 补命中标签名(面包屑"标签: xxx")、current.path/parentPath 等;
	private function listResultApply($result,$groupID,$tags,$path,$parentPath='',$param=array()){
		if(!is_array($result)) $result = array();
		$groupInfo = Model('Group')->getInfo($groupID);
		$tagData   = $this->groupTag($groupID);
		$tagMap    = array_to_keyvalue(_get($tagData,'list',array()),'id');
		$tagName   = array();
		foreach((array)$tags as $tagID){
			$item = _get($tagMap,(string)$tagID);
			if($item){$tagName[] = $item['name'];}
		}
		if(!isset($result['folderList'])){$result['folderList'] = array();}
		if(!isset($result['fileList'])){$result['fileList'] = array();}
		
		$tagInfo = array('name'=>implode(',',$tagName),'pathDesc'=>LNG('explorer.groupTag.pathDesc'));
		// 面包屑: "标签:xx,xx;n 个条件筛选"(与搜索页一致); 无标签时只显示条件个数;
		$condCount    = $this->paramCondCount($param);
		$tagCrumbName = $tagInfo['name'] ? (LNG('common.tag').': '.$tagInfo['name']) : '';
		if($condCount){
			$tagCrumbName .= ($tagCrumbName ? ';' : '').$condCount.' '.LNG('explorer.search.byItems');
		}
		if($tagCrumbName === ''){$tagCrumbName = LNG('explorer.groupTag.filterTitle');}
		$parentPath = trim($parentPath,'/');
		$parentInfo = $parentPath ? IO::info($parentPath) : false;
		$groupSource = Model('Source')->where(array('parentID'=>0,'targetType'=>SourceModel::TYPE_GROUP,'targetID'=>$groupID))->find();
		$firstPath  = KodIO::make(_get($groupSource,'sourceID',''));
		$firstName  = _get($groupInfo,'name','');
		// 第一段固定是部门根, 子目录由下面的 parentLevel 逐级追加;

		// 面包屑: "部门/a/b" 作为一个整体(点击进入 b) + "标签:xx"; 与搜索结果一致;
		$crumbNames = array($firstName);
		$crumbPath  = rtrim($firstPath,'/').'/';
		if($parentInfo && _get($parentInfo,'type') != 'file'){
			$rootSourceID = intval(_get($groupSource,'sourceID',0));
			$levels = trim((string)_get($parentInfo,'parentLevel',''),',');
			$levelIDs = $levels !== '' ? explode(',',$levels) : array();
			foreach($levelIDs as $sid){
				$sid = intval($sid);
				if(!$sid || $sid == $rootSourceID) continue;
				$info = Model('Source')->pathInfo($sid);
				if(!$info) continue;
				$crumbNames[] = _get($info,'name','');
			}
			// parentLevel 不含自身, 把当前目录补上(部门根则跳过);
			$curSourceID = intval(_get($parentInfo,'sourceID',0));
			if($curSourceID && $curSourceID != $rootSourceID){
				$crumbNames[] = _get($parentInfo,'name','');
				$crumbPath    = rtrim($parentPath,'/').'/';
			}
		}
		$address = array(
			array("name"=>implode('/',$crumbNames),"path"=>$crumbPath),
			array("name"=> $tagCrumbName,"path"=>$path),
		);
		$tagInfo['pathAddress'] = $address;
		$tagInfo['sourceRoot'] = 'groupPath';
		$result['currentFieldAdd'] = $tagInfo;
		$result['groupTagList'] = $this->getTagData($groupID);
		return $result;
	}

	// 可读: 部门存在 且 (部门管理员 或 本部门/上级部门成员);
	private function canReadGroup($groupID){
		$groupID = intval($groupID);
		if($groupID <= 0) return false;
		if(!Model('Group')->getInfoSimple($groupID)) return false;
		$userGroup = Action('filter.userGroup');
		// 部门管理员(含父部门管理员)
		if($userGroup->allowChangeGroup($groupID)) return true;
		// 部门直属成员或上级部门成员(与前端"能看该目录"口径一致);
		// 与所在部门树无关的部门(跨公司/跨根部门)拒绝;
		return $userGroup->allowViewGroup($userGroup->userGroupAt(),$groupID.'');
	}
	// 统一权限入口: 读取类要求部门成员, 编辑类要求部门管理员;
	// - 读取: 部门成员(含父部门管理员)可读, 跨部门拒绝;
	// - 部门公共标签编辑/工具栏配置: 仅部门管理员(含父部门管理员);
	// - 文档标签设置: 对文档有编辑权限;
	private function checkAuth(){
		if(strtolower(MOD.'.'.ST) != 'explorer.taggroup') return;
		$ACTION = strtolower(ACT);
		$checkSourceAuth = array();
		$groupID = intval(_get($this->in,'groupID',0));

		// 读取类动作: 部门成员可读(含父部门管理员); 跨部门拒绝;
		// filesSetTags 先过读取校验, 再逐个文档校验写权限;
		$readAction = array('get','toolbarget','sourcetags','filessettags');
		if(in_array($ACTION,$readAction) && !$this->canReadGroup($groupID)){
			show_json(LNG('explorer.noPermissionAction'),false);
		}
		// 部门公共标签编辑/工具栏配置: 仅部门管理员(含父部门管理员)
		$adminAction = array('set','toolbarset');
		if(in_array($ACTION,$adminAction) && !Action('filter.userGroup')->allowChangeGroup($groupID)){
			show_json(LNG('explorer.noPermissionAction'),false);
		}

		// 文档权限检测;
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

	/**
	 * 批量给文档加/减公共标签(右键面板勾选后立即调用); 标签须属于该部门, 文档须可写且属于该部门;
	 * 返回每个文档的逐条结果(部分失败不回滚, 由前端提示);
	 */
	public function filesSetTags(){
		$groupID   = intval(_get($this->in,'groupID',0));
		$sourceIDs = $this->parseIDList(_get($this->in,'sourceIDs',array()));
		$addTags   = $this->parseIDList(_get($this->in,'addTags',array()));
		$removeTags= $this->parseIDList(_get($this->in,'removeTags',array()));
		if(!$groupID || !$sourceIDs || (! $addTags && !$removeTags)){
			show_json(LNG("common.invalidParam"),false);
		}
		// 标签必须属于该部门;
		foreach(array_merge($addTags,$removeTags) as $tagID){
			if(!$this->model->tagInfo($groupID,$tagID)){
				show_json(LNG('explorer.groupTag.tagNotExists'),false);
			}
		}
		// 每个文档都要有写权限, 且属于该部门;
		foreach($sourceIDs as $sourceID){
			$path = KodIO::make($sourceID);
			if(!Action('explorer.auth')->fileCanWrite($path)){
				show_json(LNG('explorer.noPermissionAction'),false);
			}
			$pathInfo = IO::info($path);
			if(!$pathInfo || _get($pathInfo,'targetType','') != 'group' || intval(_get($pathInfo,'targetID',0)) != $groupID){
				show_json(LNG('common.notExists'),false);
			}
		}
		
		$result = array();
		foreach($sourceIDs as $sourceID){
			$item = array('sourceID'=>$sourceID,'add'=>array(),'remove'=>array());
			foreach($addTags as $tagID){
				$item['add'][$tagID] = (bool)$this->model->addToTag($groupID,$sourceID,$tagID);
			}
			foreach($removeTags as $tagID){
				$item['remove'][$tagID] = (bool)$this->model->removeFromTag($groupID,$sourceID,$tagID);
			}
			$result[] = $item;
		}
		show_json($result);
	}
	// 批量读取文档已关联的标签id(供右键面板初始化勾选态); [参数]:groupID,sourceIDs; 返回 {sourceID:[tagID,...]}
	public function sourceTags(){
		$groupID   = intval(_get($this->in,'groupID',0));
		$sourceIDs = $this->parseIDList(_get($this->in,'sourceIDs',array()));
		if(!$groupID || !$sourceIDs){show_json(LNG('common.invalidParam'),false);}
		$map = $this->sourceTagList($groupID);
		$result = array();
		foreach($sourceIDs as $sourceID){
			// 只返回当前用户可读、且仍属于该部门的文档; 避免通过标签关联读到无权限子目录的信息;
			$path = KodIO::make($sourceID);
			// 用 fileCan 而不是 canView: canView 失败会直接 show_json 中断整个请求;
			if(!Action('explorer.auth')->fileCan($path,'view')) continue;
			$pathInfo = IO::info($path);
			if(!$pathInfo || _get($pathInfo,'targetType','') != 'group' || intval(_get($pathInfo,'targetID',0)) != $groupID) continue;
			$tags = isset($map[$sourceID]) ? (array)$map[$sourceID] : array();
			$result[$sourceID] = array_values(array_map('intval',$tags));
		}
		show_json($result);
	}
	
	private function parseIDList($value){
		if(!$value) return array();
		$result = array();
		foreach(explode(',', (string)$value) as $id){
			$id = intval($id);
			if($id > 0){ $result[$id] = 1; }
		}
		return array_keys($result);
	}
}
