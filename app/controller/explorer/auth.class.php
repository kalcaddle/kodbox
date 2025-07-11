<?php
/*
 * @link http://kodcloud.com/
 * @author warlee | e-mail:kodcloud@qq.com
 * @copyright warlee 2014.(Shanghai)Co.,Ltd
 * @license http://kodcloud.com/tools/license/license.txt
 */

/**
 * 文档操作权限旁路拦截；
 * 
 * 如下是配置单个文档操作，统一path参数进行权限检测
 * 其他拦截：path为虚拟目录只支持列表模式 explorer.list.path;
 */
class explorerAuth extends Controller {
	private $actionPathCheck;
	function __construct() {
		parent::__construct();
		$this->isShowError = true; //检测时输出报错;
		$this->actionPathCheck = array(
			'show'		=> array("explorer.list"=>'path,listAll'),
			'view'		=> array(
				'explorer.index'=>'fileOut,fileOutBy,unzipList,pathLog,fileView,fileThumb',
				'explorer.editor' =>'fileGet'
			),
			'download'	=> array('explorer.index'=>'fileDownload,zipDownload,fileDownloadRemove'),// 下载/复制;下载/复制/文件预览打印
			'upload'	=> array(
				'explorer.upload'	=>'fileUpload,serverDownload',
				'explorer.index'	=>'mkdir,mkfile',
			),
			'edit' => array(
				'explorer.index'	=>'mkdir,mkfile,setDesc,fileSave,pathRename,pathPast,pathCopyTo,pathCuteTo,setMeta',
				'explorer.editor' 	=>'fileSave'
			),
			// 'remove'	=> array('explorer.index'=>'pathDelete'),	//批量中处理
			'share'		=> array('explorer.userShare'=>'add'),// get,edit,del 在userShare中自行判断(允许协作者(拥有者时)修改协作成员及权限)
			'comment'	=> array('comment.index'=>''),
			'event'		=> array('explorer.index'=>'pathLog'),
			'root'		=> array('explorer.index'=>'setAuth'),
		);
		
		$this->actionCheckSpace = array(//空间大小检测
			'explorer.upload'	=> 'fileUpload,serverDownload',
			'explorer.index'	=> 'mkdir,mkfile,fileSave,pathPast,pathCopyTo,pathCuteTo,unzip',
			'explorer.editor' 	=> 'fileSave',
			'explorer.share'	=> 'fileUpload',
		);
		Hook::bind('SourceModel.createBefore','explorer.auth.checkSpaceOnCreate');
	}

	// 文档操作权限统一拦截检测
	public function autoCheck(){
		$theMod 	= strtolower(MOD);
		$theAction 	= strtolower(ACTION);
		if($theMod !== 'explorer') return;

		$this->targetSpaceCheck();//空间大小检测
		Action('explorer.listGroup')->pathRootCheck($theAction);
		Action('explorer.autoPathParse')->parseAuto();
		
		// 多个请求或者包含来源去向的，分别进行权限判别；
		switch ($theAction) {//小写
			//自行判断权限,兼容文件关联附件获取信息情况;
			// case 'explorer.index.pathinfo':$this->checkAuthArray('show');break; 
			case 'explorer.index.zip':$this->checkAuthArray('edit');break;
			case 'explorer.index.zipdownload':$this->checkAuthArray('download');break;
			case 'explorer.index.unzip':
				$this->canRead($this->in['path']);
				$this->canWrite($this->in['pathTo']);
				break;
			case 'explorer.index.pathdelete':$this->checkAuthArray('remove');break;
			case 'explorer.index.pathcopy':$this->checkAuthArray('download');break;
			case 'explorer.index.pathcute':
				$this->checkAuthArray('remove');
				break;
			case 'explorer.index.pathcopyto':
				$data = json_decode($this->in['dataArr'],true);
				if(!is_array($data)){
					return $this->errorMsg('param error:dataArr!');
				}
				
				// $this->checkAuthArray('download');
				// 来源文档权限检测: 有编辑权限或者下载权限;
				$checkAction 	   = 'download'; // 没有下载权限,有复制粘贴权限时,允许从其他地方复制后粘贴(检测读取权限);
				$seleCanDownload   = Action("user.authRole")->authCanDownload();
				if(!$seleCanDownload){$checkAction = 'view';}
				
				$this->isShowError = false;$errorNum = 0;
				foreach ($data as $item) {
					$result = $this->can($item['path'],$checkAction);
					if(!$result){$errorNum++;}
				}
				$this->isShowError = true;
				if($errorNum){
					$msg  = $this->lastError ? $this->lastError : LNG('explorer.noPermissionAction');
					$code = $this->lastErrorCode ? $this->lastErrorCode : 1007;
					$this->errorMsg($msg,$code);
				}
				$this->canWrite($this->in['path']);
				break;
			case 'explorer.index.pathcuteto':
				$this->checkAuthArray('remove');
				$this->canWrite($this->in['path']);
				break;
			case 'explorer.upload.serverdownload':
			    if($this->in['path']){
			        $this->canWrite($this->in['path']);
			    }
				break;
			default:
				//直接检测；定义在actionPathCheck中的方法；参数为path，直接检测
				$actionType = $this->actionParse();
				if(isset($actionType[$theAction])){
					$authTypeArr = $actionType[$theAction];
					$errorNum = 0; // 一个控制器对应多个权限点时; 所有都失败了才算失败;一个成功就算成功;
					$this->isShowError = false;
					for ($i=0; $i < count($authTypeArr); $i++) { 
						$result = $this->can($this->in['path'],$authTypeArr[$i]);
						if(!$result){$errorNum++;}
					}
					$this->isShowError = true;
					if($errorNum == count($authTypeArr)){
						$msg  = $this->lastError ? $this->lastError : LNG('explorer.noPermissionAction');
						$code = $this->lastErrorCode ? $this->lastErrorCode : 1007;
						$this->errorMsg($msg,$code);
					}
				}
				break;
		}
	}
	
	public function targetSpaceCheck(){
		$actions = array();
		foreach ($this->actionCheckSpace as $controller => $stActions) {
			$stActions = explode(',',trim($stActions,','));
			foreach ($stActions as $action) {
				$actions[] = strtolower($controller.'.'.$action);
			}
		}
		if(!in_array(strtolower(ACTION),$actions)) return;
		if(!$this->spaceAllow($this->in['path'])){
			show_json(LNG('explorer.spaceIsFull'),false);
		}
	}
	public function spaceAllow($path){
		$parse  = KodIO::parse($path);
		if($parse['isTruePath'] != true) return true;
		if($parse['driverType'] == 'io') return true;
		
		$info = IO::infoAuth($parse['pathBase']);//目标存储;
		// 目标在回收站中: 不支持保存/上传/远程下载/粘贴/移动到此/新建文件/新建文件夹;
		$fromDav = _get($GLOBALS,'requestFrom') == 'webdav';
		if($info['isDelete'] == '1' && !$fromDav){
			$msg = $info['type'] == 'file' ? LNG('explorer.pathInRecycleFile') : LNG("explorer.pathInRecycle");
			show_json($msg,false);
		}
		$space  = Action("explorer.list")->targetSpace($info);
		if(!$space || $space['sizeMax']==0 ) return true; // 没有大小信息,或上限为0则放过;
		$result = $space['sizeMax'] > $space['sizeUse'];
		if(!$result){$this->lastError = LNG('explorer.spaceIsFull');}
		return $result;
	}

	public function checkSpaceOnCreate($sourceInfo){
		$space = false;
		if($sourceInfo['targetType'] == SourceModel::TYPE_GROUP){
			$space = $this->space('group',$sourceInfo['targetID']);
		}else if($sourceInfo['targetType'] == SourceModel::TYPE_USER){
			$space = $this->space('user',$sourceInfo['targetID']);
		}
		
		if(!$space || $space['sizeMax']==0 ) return true; // 没有大小信息,或上限为0则放过;
		if($space['sizeMax']  <= $space['sizeUse']+ $sourceInfo['size'] ){
			show_json(LNG('explorer.spaceIsFull'),false);
		}
	}
	
	// 用户空间占用集中处理;
	public function space($targetType,$targetID){
		if($targetType == 'user'){//空间追加处理;
			$target = Model('User')->getInfo($targetID);
		}else{
			$target = Model('Group')->getInfo($targetID);
		}
		$target['sizeUse'] = (!$target['sizeUse'] || $target['sizeUse'] <= 0 ) ? 0 : intval($target['sizeUse']);
		$result = array(
			'targetType'	=> $targetType,
			'targetID' 		=> $targetID,
			'targetName'	=> $target['name'],
			"sizeMax" 		=> floatval($target['sizeMax'])*1024*1024*1024,
    		"sizeUse" 		=> $target['sizeUse'],
		);
		$result = Hook::filter("explorer.targetSpace",$result);
		return $result;
	}
	
	
	// 外部获取文件读写权限; Action("explorer.auth")->fileCanRead($path);
	public function fileCanRead($file){
		if($this->fileIsShareLink($file)){return $this->fileCan($file,'view');}
		if(!ActionCall('user.authRole.authCanRead')) return false;
		return $this->fileCan($file,'view');
	}
	public function fileCanDownload($file){
		if($this->fileIsShareLink($file)){return $this->fileCan($file,'download');}
		if(!ActionCall('user.authRole.authCanRead')) return false;
		return $this->fileCan($file,'download');
	}
	public function fileCanWrite($file){
		if($this->fileIsShareLink($file)){return $this->fileCan($file,'edit');}
		if(!ActionCall('user.authRole.authCanEdit')) return false;
		return $this->fileCan($file,'edit');
	}
	private function fileIsShareLink($file){
		return strpos($file,'{shareItemLink:') === 0 ? true:false;
	}
	
	public function fileCan($file,$action){
		if(request_url_safe($file)) return true;
		$this->isShowError = false;
		$result = $this->can($file,$action);
		$this->isShowError = true;
		return $result;
	}

	/**
	 * 检测文档权限，是否支持$action动作
	 * 目录解析：拦截只支持列目录，但当前方法为其他操作的
	 * 获取目录信息：自己的文档，则放过
	 * 
	 * 权限判断：
	 * 1. 是不是：是不是自己的文档；是的话则跳过权限检测
	 * 2. 能不能：
	 * 		a. 是我所在的部门的文档：则通过权限&动作判别
	 * 		b. 是内部协作分享给我的：检测分享信息，通过权限&动作判别
	 * 		c. 其他情况：一律做权限拦截；
	 * 
	 * 操作屏蔽：remove不支持根目录：用户根目录，部门根目录，分享根目录；
	 */
	public function can($path,$action){
		if(!$path || !is_string($path)){return false;}
		$isRoot = KodUser::isRoot();
		$parse  = KodIO::parse($path);
		$ioType = $parse['type'];
		
		if($ioType == KodIO::KOD_SHARE_LINK){
			$shareInfo  = Action('explorer.share')->sharePathInfo($path);
			if($shareInfo && in_array($action,array('view','show'))) return true;
			if($shareInfo && $action == 'download' && _get($shareInfo,'option.notDownload') !='1' ) return true;
			if($shareInfo && $action == 'edit' && _get($shareInfo,'option.canEditSave') == '1' ){
				if(Model('SystemOption')->get('shareLinkAllowEdit') == '0'){ // 全局开关;
					return $this->errorMsg(LNG('explorer.pathNotSupport'),1108);
				}
				return true;
			}
			return $this->errorMsg(LNG('explorer.pathNotSupport'),1108);
		}
		if(!$isRoot && !Action('user.authRole')->canCheckRole($action)){
			return $this->errorMsg(LNG('explorer.noPermissionAction'),1021);
		}
		// 物理路径 io路径拦截；只有管理员且开启了访问才能做相关操作;
		if( $ioType == KodIO::KOD_IO || $ioType == false ){
			if( request_url_safe($path) ) return $action == 'view';
			
			// 允许管理存储,即可访问系统回收站及挂载的存储.
			$pathCanRead = Action('user.authRole')->authCan('admin.storage.edit');
			if($pathCanRead && $this->config["ADMIN_ALLOW_IO"]) return true;
			if($pathCanRead && $parse['id'] == 'systemRecycle') return true;
			return $this->errorMsg(LNG('explorer.pathNotSupport'),1001);
		}
		
		//个人挂载目录；跨空间移动复制根据身份处理；
		if( $ioType == KodIO::KOD_USER_DRIVER ) return true;
		
		//不支持删除自己的桌面
		if($action == 'remove'){
			if(trim($path,'/') == trim(MY_DESKTOP,'/')){
				return $this->errorMsg(LNG('explorer.desktopDelError'),1100);
			}
			if(trim($path,'/') == trim(MY_HOME,'/')){
				return $this->errorMsg(LNG('explorer.pathNotSupport'),1100);
			}
		}
		
		// 纯虚拟路径只能列表; 不支持其他任何操作;
		if( $this->pathOnlyShow($path) ){
			if($action == 'show') return true;
			return $this->errorMsg(LNG('explorer.pathNotSupport'),1002);
		}

		//分享内容;分享子文档所属分享判别，操作分享权限判别；
		if( $ioType == KodIO::KOD_SHARE_ITEM){
			return $this->checkShare($parse['id'],trim($parse['param'],'/'),$action);
		}

		$pathInfo = IO::infoAuth($parse['pathBase']);
		Hook::trigger("explorer.auth.can",$pathInfo,$action);
		// 个人私密空间是否登录检测;
		if($pathInfo && isset($pathInfo['sourceID']) && $pathInfo['targetType'] == 'user'){
			$errorMsg = Action('explorer.listSafe')->authCheck($pathInfo,$action);
			if($errorMsg){return $this->errorMsg($errorMsg,1101);}
		}
		
		//上层文件夹是否需要密码;
		if($pathInfo && isset($pathInfo['sourceID'])){
			$errorMsg = Action('explorer.listPassword')->authCheck($pathInfo,$action);
			if($errorMsg){return $this->errorMsg($errorMsg,1102);}
		}
		
		// source 类型; 新建文件夹 {source:10}/新建文件夹; 去除
		//文档类型检测：屏蔽用户和部门之外的类型；
		if($this->allowRootSourceInfo($pathInfo)) return true;
		$targetType = $pathInfo['targetType'];
		// if(!$pathInfo) return true; 
		if(!$pathInfo){//不存在,不判断文档权限;
			return $this->errorMsg(LNG('common.pathNotExists'),0);
		}
		if( $targetType != 'user' && $targetType != 'group' ){
			return $this->errorMsg(LNG('explorer.noPermissionAction'),1003);
		}
		
		//个人文档；不属于自己
		if( $targetType == 'user' && $pathInfo['targetID'] != USER_ID ){
			return $this->errorMsg(LNG('explorer.noPermissionAction'),1004);
		}

		//部门文档：权限拦截；会自动匹配权限；我在的部门会有对应权限
		if($targetType == 'group'){
			$auth  = $pathInfo['auth']['authValue'];
			return $this->checkAuthMethod($auth,$action);
		}
		// 删除操作：拦截根文件夹；用户根文件夹，部门根文件夹
		if( $pathInfo['parentID'] == '0' && $action=='remove' ){
			return $this->errorMsg(LNG('explorer.noPermissionAction'),1100);
		}
		return true;
	}
	
	public function allowRootSourceInfo($pathInfo){
		$isRoot = KodUser::isRoot();
		if($isRoot && $this->config["ADMIN_ALLOW_SOURCE"]) return true;
		if($isRoot && $pathInfo && $pathInfo['pathType'] == '{systemRecycle}') return true;
		return false;
	}

	// 路径类型中: 检测目录是否可操作(属性,重命名,新建上传等); 纯虚拟路径只能列表;
	public function pathOnlyShow($path){
		$parse  = KodIO::parse($path);
		$truePath = array( // 可以操作的目录类型;
			KodIO::KOD_IO,
			KodIO::KOD_SOURCE,
			KodIO::KOD_SHARE_ITEM,
		);
		$canAction = in_array($parse['type'],$truePath);
		return $canAction? false:true;
	}
	
	private function errorMsg($msg,$code=false){
		if($this->isShowError){
			return show_json($msg,false,$code);	
		}
		$this->lastError 	 = $msg;
		$this->lastErrorCode = $code;
		return false;
	}
	public function getLastError(){return $this->lastError;}
	public function canView($path){return $this->can($path,'view');}
	public function canRead($path){return $this->can($path,'download');}
	public function canWrite($path){return $this->can($path,'edit');}
	private function checkAuthArray($action){
		$data = json_decode($this->in['dataArr'],true);
		if(!is_array($data)){
			return $this->errorMsg('param error:dataArr!');
		}
		foreach ($data as $item) {
			$this->can($item['path'],$action);
		}
	}
	
	/**
	 * 根据权限值判别是否允许该动作
	 * $method: view,show,...   AuthModel::authCheckShow...
	 */
	private function checkAuthMethod($auth,$method){
		if(KodUser::isRoot() && $this->config["ADMIN_ALLOW_SOURCE"]) return true;
		$auth = intval($auth);
		if(!$auth || $auth == 0){
			return $this->errorMsg(LNG('explorer.noPermissionAction'),1005);
		}

		//某文档有权限,打通上层文件夹通路;
		if($method == 'show' && $auth === -1) return true;
				
		$method   = strtoupper(substr($method,0,1)).substr($method,1);
		$method   = 'authCheck'.$method;
		$allow = Model('Auth')->$method($auth);
		if(!$allow){
			return $this->errorMsg(LNG('explorer.noPermissionAction'),1006);
		}
		return true;
	}
	
	/**
	 * 分享检测：
	 * 1. 分享存在；文档存在；
	 * 2. 文档属于该分享或该分享的子目录
	 * 2. 且自己在分享目标中; 权限不等于0 说明自己在该分享中；
	 * 3. 权限动作检测
	 * 
	 * 分享根文件夹不支持删除操作；
	 */
	public function checkShare($shareID,$sourceID,$method){
		$shareInfo = Model('Share')->getInfoAuth($shareID);
		$sharePath = $shareInfo['sourceID'].'';
		if(!$shareInfo || !$shareInfo['sourceInfo'] ){
			return $this->errorMsg(LNG('explorer.share.notExist'));
		}
		if( $sharePath == $sourceID && $method =='remove' ){
			return $this->errorMsg("source share root can't remove !");
		}
		
		// 自己协作分享的内容; 权限同自己拥有的权限;
		if($shareInfo['userID'] == USER_ID){
			$sourceInfo = $shareInfo['sourceInfo'];
			if( $sourceInfo['targetType'] == 'user' && $sourceInfo['targetID'] == USER_ID ){
				return Action('user.authRole')->canCheckRole($method);
			}
			if(!$shareInfo['sourceInfo']['auth']){ // 自己内部协作分享 io路径(拥有后台存储管理权限的角色)
				return Action('explorer.authUser')->can($shareInfo['sourcePath'],$method,$shareInfo['userID']);
			}
			return $this->checkAuthMethod($shareInfo['sourceInfo']['auth']['authValue'],$method);
		}

		// 分享时间处理;
		$timeout = intval(_get($shareInfo,'options.shareToTimeout',0));
		if($timeout > 0 && $timeout < time()){
			return $this->errorMsg(LNG('explorer.share.expiredTips'));
		}
		
		// 关闭协作分享的;
		if($shareInfo['isShareTo'] == '0'){
			return $this->errorMsg(LNG('explorer.share.notExist').'[status=0]');
		}

		// 内部协作分享有效性处理: 当分享者被禁用,没有分享权限,所在文件不再拥有分享权限时自动禁用外链分享;
		if(!Action('explorer.authUser')->canShare($shareInfo)){
			$userInfo = Model('User')->getInfoSimpleOuter($shareInfo['userID']);
			$tips = '('.$userInfo['name'].' - '.LNG('common.noPermission').')';
			return $this->errorMsg(LNG('explorer.share.notExist').$tips);
		}
		
		// 物理路径,io路径;
		if($shareInfo['sourceID'] == '0'){
			$sharePath = KodIO::clear($shareInfo['sourcePath']);
			$thisPath  = KodIO::clear($shareInfo['sourcePath'].$sourceID);
			if(substr($thisPath,0,strlen($sharePath)) != $sharePath) return false;
			return $this->checkAuthMethod($shareInfo['auth']['authValue'],$method);
		}
		
		if (!$sourceID) $sourceID = $shareInfo['sourceID'];
		$sourceInfo = Model('Source')->sourceInfo($sourceID);
		$parent = Model('Source')->parentLevelArray($sourceInfo['parentLevel']);
		array_push($parent,$sourceID);
		
		if(!$sourceInfo || !in_array($sourceID,$parent) ){
			return $this->errorMsg(LNG('explorer.share.notExist'));
		}
		// 自己的分享，不判断权限；协作中添加了自己或自己所在的部门；
		if( $sourceInfo['targetType'] == SourceModel::TYPE_USER && 
			$sourceInfo['targetID'] == USER_ID ){
			return true;
		}
		return $this->checkAuthMethod($shareInfo['auth']['authValue'],$method);
	}
	
	//解析上述配置到action列表；统一转为小写;
	private function actionParse(){
		$actionArray = array();
		foreach ($this->actionPathCheck as $authType => $modelActions) {
			foreach ($modelActions as $controller => $stActions) {
				if(!$stActions) continue;
				$stActions = explode(',',trim($stActions,','));
				foreach ($stActions as $action) {
					$fullAction = strtolower($controller.'.'.$action);
					if(!isset($actionArray[$fullAction])){
						$actionArray[$fullAction] = array();
					}
					$actionArray[$fullAction][] = $authType;
				}
			}
		}
		// pr($actionArray,$this->actionPathCheck);exit;
		return $actionArray;
	}
}