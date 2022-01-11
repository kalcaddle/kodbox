<?php
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/

class explorerIndex extends Controller{
	private $model;
	public function __construct(){
		parent::__construct();
		$this->model = Model("Source");
	}
	public function pathInfo(){
		$fileList = json_decode($this->in['dataArr'],true);
		if(!$fileList){
			show_json(LNG('explorer.error'),false);
		}
		$result = array();
		for ($i=0; $i < count($fileList); $i++){// 处理掉无权限和不存在的内容;
			if(!Action('explorer.auth')->fileCan($fileList[$i]['path'],'show')) continue;
			
			$itemInfo = $this->itemInfo($fileList[$i]);
			if($itemInfo){$result[] = $itemInfo;}
		}
		if(count($fileList) == 1 && $result){
			$result = $this->itemInfoMore($result[0]);
		}
		$data = !!$result ? $result : LNG('common.pathNotExists');
		show_json($data,!!$result);
	}
	private function itemInfo($item){
		$path = $item['path'];
		$type = _get($item,'type');
		if($type == 'full'){
			$result = IO::infoFull($path);
		}else if($type == 'simple'){
			$result = IO::info($path);
		}else{
			$result = IO::infoWithChildren($path);
		}
		if(!$result) return false;
		// $canLink = Action('explorer.auth')->fileCanDownload($path);
		$canLink = Action('explorer.auth')->fileCan($path,'edit');//edit,share; 有编辑权限才能生成外链;
		if( $result['type'] == 'file' && $canLink){
			$result['downloadPath'] = Action('explorer.share')->link($path);
		}
		$result = Action('explorer.list')->pathInfoParse($result,0,1);
		if($result['isDelete'] == '1'){unset($result['downloadPath']);}
		return $result;
	}

	private function itemInfoMore($item){
		$result = Model('SourceAuth')->authOwnerApply($item);
		if($result['type'] != 'file') return $item;
		
		if( !_get($result,'fileInfo.hashMd5') && 
			($result['size'] <= 200*1024*1024 || _get($this->in,'getMore') )  ){
			$result['hashMd5'] = IO::hashMd5($result['path']);
		}
		$result = Action('explorer.list')->pathInfoMore($result);
		// unset($result['fileInfoMore']);GetInfo::infoAdd($result);pr($result);exit;
		return $result;
	}
	
	public function desktopApp(){
		$desktopApps = include(USER_SYSTEM.'desktop_app.php');
		$desktopApps['myComputer']['value'] = MY_HOME;
		foreach ($desktopApps as $key => &$item) {
			if($item['menuType'] == 'menu-default-open'){
				$item['menuType'] = 'menu-default';
			}
			if(!_get($GLOBALS,'isRoot') && $item['rootNeed']){
				unset($desktopApps[$key]);
			}
		}
		show_json($desktopApps);
	}

	/**
	 * 设置文档描述;
	 */
	public function setDesc(){
		$maxLength = $GLOBALS['config']['systemOption']['fileDescLengthMax'];
		$msg = LNG('explorer.descTooLong').'('.LNG('explorer.noMoreThan').$maxLength.')';
		$data = Input::getArray(array(
			'path'	=> array('check'=>'require'),
			'desc'	=> array('check'=>'length','param'=>array(0,$maxLength),'msg'=>$msg),
		));
		
		$result = false;
		$info   = IO::infoSimple($data['path']);
		if($info && $info['sourceID']){
			$result = $this->model->setDesc($info['sourceID'],$data['desc']);
		}
		// $msg = !!$result ? LNG('explorer.success') : LNG('explorer.error');
		show_json($data['desc'],!!$result);
	}
	
	/**
	 * 设置文档描述;
	 */
	public function setMeta(){
		$data = Input::getArray(array(
			'path'	=> array('check'=>'require'),
			'data'	=> array('check'=>'require'),
		));
		$meta = json_decode($data['data'],true);
		if(!$meta || !is_array($meta)){
			show_json(LNG('explorer.error'),false);
		}

		$info = IO::info($data['path']);
		if($info && $info['sourceID']){
			foreach ($meta as $key => $value) {
				if( !$this->metaKeyCheck($key,$value,$info) ){
					show_json("key error!",false);
				}
				$value = $value === '' ? null:$value; //为空则删除;
				$this->model->metaSet($info['sourceID'],$key,$value);
			}
			show_json(IO::info($data['path']),true);
		}
		show_json(LNG('explorer.error'),false);
	}
	private function metaKeyCheck($key,$value,$info){
		static $metaKeys = false;
		if(!$metaKeys){
			$metaKeys = array_keys($this->config['settings']['sourceMeta']);
			$metaKeys = array_merge($metaKeys,array(
				'systemSort',		// 置顶
				'systemLock',		// 编辑锁定
				'systemLockTime',	// 编辑锁定时间
			));
		}
		$isLock = _get($info,'metaInfo.systemLock') ? true:false;
		if($key == "systemLock" && $value && $isLock){
			show_json(LNG('explorer.fileLockError'),false);
		}
		return in_array($key,$metaKeys);
	}
		
	/**
	 * 设置权限
	 */
	public function setAuth(){
		$data = Input::getArray(array(
			'path'	=> array('check'=>'require'),
			'auth'	=> array('check'=>'json','default'=>''),
			'action'=> array('check'=>'in','default'=>'','param'=>array('clearChildren','getData') ),
		));

		$result = false;
		$info   = IO::info($data['path']);
		if( $info && $info['sourceID'] && $info['targetType'] == 'group'){//只能设置部门文档;
			if($data['action'] == 'getData'){
				$result = Model('SourceAuth')->getAuth($info['sourceID']);
				show_json($result);
			}

			//清空所有子文件(夹)的权限；
			if($data['action'] == 'clearChildren'){
				$result = Model('SourceAuth')->authClear($info['sourceID']);
			}else{
				$setAuth = $this->setAuthSelf($info,$data['auth']);
				$result = Model('SourceAuth')->setAuth($info['sourceID'],$setAuth);
			}
		}
		$msg = !!$result ? LNG('explorer.success') : LNG('explorer.error');
		show_json($msg,!!$result);
	}
	
	// 设置权限.默认设置自己为之前管理权限; 如果只有自己则清空;
	private function setAuthSelf($pathInfo,$auth){
		if(!$auth) return $auth;
		$selfAuth = _get($pathInfo,'auth.authInfo.id','1');
		$authList = array();
		foreach($auth as $item){
			if( $item['targetID'] == USER_ID && 
				$item['targetType'] == SourceModel::TYPE_USER){
				continue;
			}
			$authList[] = $item;
		}
		if(!$authList) return $authList;
		$authList[] = array(
			'targetID' 	=> USER_ID, 
			'targetType'=> SourceModel::TYPE_USER,
			'authID' 	=> $selfAuth
		);
		return $authList;
	}
	
	public function pathAllowCheck($path){
		$notAllow = array('/', '\\', ':', '*', '?', '"', '<', '>', '|');
		$parse = KodIO::parse($path);
		if($parse['pathBase']){
			$path = $parse['param'];
		}
		$name = get_path_this($path);
		$checkName = str_replace($notAllow,'_',$name);
		if($name != $checkName){
		    show_json(LNG('explorer.charNoSupport').implode(',',$notAllow),false);
		}
		
		$maxLength = $GLOBALS['config']['systemOption']['fileNameLengthMax'];
		if($maxLength && strlen($name) > $maxLength ){
			show_json(LNG("common.lengthLimit")." (max=$maxLength)",false);
		}
		return;
	}
	
	public function mkfile(){
		$this->pathAllowCheck($this->in['path'],true);
		$info = IO::info($this->in['path']);
		if($info && $info['type'] == 'file'){ //父目录为文件;
			show_json(LNG('explorer.success'),true,IO::pathFather($info['path']));
		}
		
		$tplPath = BASIC_PATH.'static/others/newfile-tpl/';
		$ext     = get_path_ext($this->in['path']);
		$tplFile = $tplPath.'newfile.'.$ext;
		$content = _get($this->in,'content','');
		if( isset($this->in['content']) ){
			if( _get($this->in,'base64') ){ //文件内容base64;
				$content = base64_decode($content);
			}
		}else if(@file_exists($tplFile)){
			$content = file_get_contents($tplFile);
		}
		$repeat = !empty($this->in['fileRepeat']) ? $this->in['fileRepeat']:REPEAT_SKIP;
		$result = IO::mkfile($this->in['path'],$content,$repeat);
		$msg = !!$result ? LNG('explorer.success') : LNG('explorer.error');
		show_json($msg,!!$result,$result);
	}
	public function mkdir(){
		$this->pathAllowCheck($this->in['path']);
		$repeat = !empty($this->in['fileRepeat']) ? $this->in['fileRepeat']:REPEAT_SKIP;
		$info = IO::info($this->in['path']);
		if($info && $info['type'] == 'file'){ //父目录为文件;
			show_json(LNG('explorer.success'),true,IO::pathFather($info['path']));
		}
				
		$result = IO::mkdir($this->in['path'],$repeat);
		$msg = !!$result ? LNG('explorer.success') : LNG('explorer.error');
		show_json($msg,!!$result,$result);
	}
	public function pathRename(){
		$this->pathAllowCheck($this->in['newName']);
		$path = $this->in['path'];
		if(IO::isTypeObject($path)){
			$this->taskCopyCheck(array(array("path"=>$path)));
		}
		
		$result = IO::rename($path,$this->in['newName']);
		$msg = !!$result ? LNG('explorer.success') : LNG("explorer.pathExists");
		show_json($msg,!!$result,$result);
	}

	public function pathDelete(){
		$list = json_decode($this->in['dataArr'],true);
		$this->taskCopyCheck($list);
		$toRecycle = Model('UserOption')->get('recycleOpen');
		if( _get($this->in,'shiftDelete') == '1' ){
			$toRecycle = false;
		}
		$success=0;$error=0;
		$errorMsg = LNG('explorer.removeFail');
		foreach ($list as $val) {
			$result = Action('explorer.recycleDriver')->removeCheck($val['path'],$toRecycle);
			$result ? $success ++ : $error ++;
		}
		$code = $error === 0 ? true:false;
		$msg  = $code ? LNG('explorer.removeSuccess') : $errorMsg;
		if(!$code && $success > 0){
			$msg = $success.' '.LNG('explorer.success').', '.$error.' '.LNG('explorer.error');
		}
		show_json($msg,$code);
	}
	// 从回收站删除
	public function recycleDelete(){		
		$pathArr   = false;
		if( _get($this->in,'all') ){
			$recycleList = Model('SourceRecycle')->listData();
			foreach ($recycleList as $key => $sourceID) {
				$recycleList[$key] = array("path"=>KodIO::make($sourceID));
			}
			$this->taskCopyCheck($recycleList);//彻底删除: children数量获取为0,只能是主任务计数;
		}else{
			$dataArr = json_decode($this->in['dataArr'],true);
			$this->taskCopyCheck($dataArr);
			$pathArr = $this->parseSource($dataArr);
		}
		Model('SourceRecycle')->remove($pathArr);
		Action('explorer.recycleDriver')->remove($pathArr);

		// 清空回收站时,重新计算大小; 一小时内不再处理;
		Model('Source')->targetSpaceUpdate(SourceModel::TYPE_USER,USER_ID);
		$cacheKey = 'autoReset_'.USER_ID;
		if(isset($this->in['all']) && time() - intval(Cache::get($cacheKey)) > 3600 * 10 ){
			Cache::set($cacheKey,time());
			$USER_HOME = KodIO::sourceID(MY_HOME);
			Model('Source')->folderSizeResetChildren($USER_HOME);
			Model('Source')->userSpaceReset(USER_ID);
		}
		show_json(LNG('explorer.success'));
	}
	//回收站还原
	public function recycleRestore(){
		$pathArr = false;
		if( _get($this->in,'all') ){
			$recycleList = Model('SourceRecycle')->listData();
			foreach ($recycleList as $key => $sourceID) {
				$recycleList[$key] = array("path"=>KodIO::make($sourceID));
			}
			$this->taskCopyCheck($recycleList);
		}else{
			$dataArr = json_decode($this->in['dataArr'],true);
			$this->taskCopyCheck($dataArr);
			$pathArr = $this->parseSource($dataArr);
		}

		Action('explorer.recycleDriver')->restore($pathArr);
		Model('SourceRecycle')->restore($pathArr);
		show_json(LNG('explorer.success')); 
	}
	private function parseSource($list){
		$result = array();
		foreach ($list as $value) {
			$parse = KodIO::parse($value['path']);
			$thePath = $value['path'];// io路径;物理路径;协作分享路径处理保持不变;
			if($parse['type'] == KodIO::KOD_SOURCE){
				$thePath = IO::getPath($value['path']);
			}
			$result[] = $thePath;
		}
		return $result;
	}

	
	public function pathCopy(){
		Session::set(array(
			'pathCopyType'	=> 'copy',
			'pathCopy'		=> $this->in['dataArr'],
		));
		show_json(LNG('explorer.copySuccess'));
	}
	public function pathCute(){
		Session::set(array(
			'pathCopyType'	=> 'cute',
			'pathCopy'		=> $this->in['dataArr'],
		));
		show_json(LNG('explorer.cuteSuccess'));
	}
	public function pathCopyTo(){
		$this->pathPast('copy',$this->in['dataArr']);	
	}
	public function pathCuteTo(){
		$this->pathPast('cute',$this->in['dataArr']);	
	}
	public function clipboard(){
		if(isset($this->in['clear'])){
			Session::set('pathCopy', json_encode(array()));
			Session::set('pathCopyType','');
			return;
		}
		$clipboard = json_decode(Session::get('pathCopy'),true);
		if(!$clipboard){
			$clipboard = array();
		}
		show_json($clipboard,true,Session::get('pathCopyType'));
	}
	public function pathLog(){
		$info = IO::info($this->in['path']);
		if(!$info['sourceID']){
			show_json('path error',false);
		}
		$data = Model('SourceEvent')->listBySource($info['sourceID']);
		
		// 协作分享;路径数据处理;
		if($info['shareID']){
			$shareInfo	= Model('Share')->getInfo($info['shareID']);
			$userActon  = Action('explorer.userShare');
			foreach($data['list'] as $i=>$item){
				if($item['sourceInfo']){
					$data['list'][$i]['sourceInfo'] = $userActon->_shareItemeParse($item['sourceInfo'],$shareInfo);
				}
				if($item['parentInfo']){
					$data['list'][$i]['parentInfo'] = $userActon->_shareItemeParse($item['parentInfo'],$shareInfo);
				}
				if(is_array($item['desc']['from'])){
					$data['list'][$i]['desc']['from'] = $userActon->_shareItemeParse($item['desc']['from'],$shareInfo);
				}
				if(is_array($item['desc']['to'])){
					$data['list'][$i]['desc']['to'] = $userActon->_shareItemeParse($item['desc']['to'],$shareInfo);
				}
				if(is_array($item['desc']['sourceID'])){
					$data['list'][$i]['desc']['sourceID'] = $userActon->_shareItemeParse($item['desc']['sourceID'],$shareInfo);
				}
			}
		}
		show_json($data);
	}

	/**
	 * 复制或移动
	 */
	public function pathPast($copyType=false,$list=false){
		if(!$copyType){
			$copyType = Session::get('pathCopyType');
			$list     = Session::get('pathCopy');
			if($copyType == 'cute'){
				Session::set('pathCopy', json_encode(array()));
				Session::set('pathCopyType', '');
			}
		}

		$list = json_decode($list,true);
		$list = is_array($list) ? $list : array();
		if($copyType == 'copy'){
			$list = $this->copyCheckShare($list);
		}
		$pathTo = $this->in['path'];
		if (count($list) == 0 || !$pathTo) {
			show_json(LNG('explorer.clipboardNull'),false);
		}
		$this->taskCopyCheck($list);
		
		$error = '';
		$repeat = Model('UserOption')->get('fileRepeat');
		$repeat = !empty($this->in['fileRepeat']) ? $this->in['fileRepeat'] :$repeat;
		$result = array();
		for ($i=0; $i < count($list); $i++) {
			$thePath = $list[$i]['path'];
			if ($copyType == 'copy') {
				//复制到自己所在目录,则为克隆;
				$driver = IO::init($thePath);
				$father = $driver->getPathOuter($driver->pathFather($driver->path));
				$repeatType = $repeat;
				if(KodIO::clear($father) == KodIO::clear($pathTo) ){
					$repeatType = REPEAT_RENAME_FOLDER;
				}
				$result[] = IO::copy($thePath,$pathTo,$repeatType);
			}else{
				$result[] = IO::move($thePath,$pathTo,$repeat);
			}
		}
		$msg= $copyType == 'copy'?LNG('explorer.pastSuccess').$error:LNG('explorer.cutePastSuccess').$error;
		$code = $error =='' ?true:false;
		show_json($msg,$code,$result);
	}
	
	// 外链分享复制;
	private function copyCheckShare($list){
		for ($i=0; $i < count($list); $i++) {
			$path = $list[$i]['path'];
			$pathParse= KodIO::parse($path);
			if($pathParse['type'] != KodIO::KOD_SHARE_LINK) continue;
			
			// 外链分享处理; 权限限制相关校验; 关闭下载--不支持转存; 转存数量限制处理;
			$info = Action('explorer.share')->sharePathInfo($path);
			if(!$info){
				show_json($GLOBALS['explorer.sharePathInfo.error'], false);
			}
			if($info['option'] && $this->share['options']['notDownload'] == '1'){
				show_json(LNG('explorer.share.noDownTips'), false);
			}
			$list[$i]['path'] = $info['path'];
			// pr($path,$info);exit;
		}
		return $list;
	}

	// 文件移动; 耗时任务;
	private function taskCopyCheck($list){
		$list = is_array($list) ? $list : array();
		$defaultID = 'copyMove-'.USER_ID.'-'.rand_string(8);
		$taskID = $this->in['longTaskID'] ? $this->in['longTaskID']:$defaultID;
		
		$task = new TaskFileTransfer($taskID,'copyMove');
		for ($i=0; $i < count($list); $i++) {
			$task->addPath($list[$i]['path']);
		}
	}
	
	/**
	 * 压缩下载
	 */
	public function fileDownloadRemove(){
		$path = Input::get('path', 'require');
		$path = $this->pathCrypt($path,false);
		if(!$path || !IO::exist($path)) {
			show_json(LNG('common.pathNotExists'), false);
		}
		IO::fileOut($path,true);
		$dir = get_path_father($path);
		if(strstr($dir,TEMP_FILES)){
		    del_dir($dir);
		}
	}

	private function tmpZipName($dataArr){
		$files = array();
		foreach($dataArr as $item){
			$info	 = IO::info($item['path']);
			$files[] = IOArchive::tmpFileName($info);
		}
		sort($files);
		return md5(json_encode($files));
	}

	public function clearCache(){
		$maxTime = 3600*24;
		$list = IO::listPath(TEMP_FILES);
		$list = array_merge($list['fileList'],$list['folderList']);
		foreach($list as $item){
			if(time() - $item['modifyTime'] < $maxTime) continue;
			if(is_dir($item['path'])){
				del_dir($item['path']);
			}else{
				del_file($item['path']);
			}
		}
	}
	/**
	 * 多文件、文件夹压缩下载
	 * @return void
	 */
	public function zipDownload(){	
		ignore_timeout();
		$dataArr  = json_decode($this->in['dataArr'],true);
		$downName = $this->tmpZipName($dataArr);
		$zipCache = TEMP_FILES;mk_dir($zipCache);

		$zipPath = Cache::get($downName);
		if($zipPath && IO::exist($zipPath) ){
			show_json(LNG('explorer.zipSuccess'),true,$this->pathCrypt($zipPath));
		}

		$zipPath = $this->zip($zipCache.$downName . '/');
		Cache::set($downName, $zipPath, 3600*6);
		show_json(LNG('explorer.zipSuccess'),true,$this->pathCrypt($zipPath));
	}
	// 文件名加解密
	public function pathCrypt($path, $en=true){
		$pass = Model('SystemOption')->get('systemPassword').'encode';
		return $en ? Mcrypt::encode($path,$pass) : Mcrypt::decode($path,$pass);
	}

	/**
	 * 压缩
	 * @param string $zipPath
	 */
	public function zip($zipPath=''){
		ignore_timeout();
		$dataArr  = json_decode($this->in['dataArr'],true);
		$fileType = Input::get('type', 'require','zip');
		$repeat   = Model('UserOption')->get('fileRepeat');
		$repeat   = !empty($this->in['fileRepeat']) ? $this->in['fileRepeat'] :$repeat;

		$this->taskZip($dataArr);
		$zipFile = IOArchive::zip($dataArr, $fileType, $zipPath,$repeat);
		if($zipPath != '') return $zipFile;
		$info = IO::info($zipFile);
		$data = LNG('explorer.zipSuccess').LNG('explorer.file.size').":".size_format($info['size']);
		show_json($data,true,$zipFile);
	}
	
	private function taskZip($list){
		$list = is_array($list) ? $list : array();
		$defaultID = 'zip-'.USER_ID.'-'.rand_string(8);
		$taskID = $this->in['longTaskID'] ? $this->in['longTaskID']:$defaultID;
		$task = new TaskZip($taskID,'zip');
		for ($i=0; $i < count($list); $i++) {
			$task->addPath($list[$i]['path']);
		}
	}
	private function taskUnzip($data){
		$defaultID = 'unzip-'.USER_ID.'-'.rand_string(8);
		$taskID = $this->in['longTaskID'] ? $this->in['longTaskID']:$defaultID;
		$task = new TaskUnzip($taskID,'zip');
		$task->addFile($data['path']);
	}
	
	/**
	 * 解压缩
	 */
	public function unzip(){
		ignore_timeout();
		$data = Input::getArray(array(
			'path' => array('check' => 'require'),
			'pathTo' => array('check' => 'require'),
			'unzipPart' => array('check' => 'require', 'default' => '-1')
		));
		
		$repeat = Model('UserOption')->get('fileRepeat');
		$repeat = !empty($this->in['fileRepeat']) ? $this->in['fileRepeat'] :$repeat;
		$this->taskUnzip($data);
		IOArchive::unzip($data,$repeat);
		show_json(LNG('explorer.unzipSuccess'));
	}

	/**
	 * 查看压缩文件列表
	 */
	public function unzipList(){
		$data = Input::getArray(array(
			'path' => array('check' => 'require'),
			'index' => array('check' => 'require', 'default' => '-1'),
			'download' => array('check' => 'require', 'default' => false),
			'name' => array('check' => 'require', 'default' => ''),
		));
		$this->taskUnzip($data);
		$list = IOArchive::unzipList($data);
		show_json($list);
	}

	public function fileDownload(){
		$this->in['download'] = 1;
		Hook::trigger('explorer.fileDownload', $this->in['path']);
		$this->fileOut();
	}
	//输出文件
	public function fileOut(){
		$path = $this->in['path'];
		if(!$path) return; 
		$isDownload = isset($this->in['download']) && $this->in['download'] == 1;
		if($isDownload && !Action('user.authRole')->authCanDownload()){
			show_json(LNG('explorer.noPermissionAction'),false);
		}
		if(isset($this->in['type']) && $this->in['type'] == 'image'){
			$info = IO::info($path);
			$imageThumb = array('jpg','png','jpeg','bmp');
			if ($info['size'] >= 1024*200 &&
				function_exists('imagecolorallocate') &&
				in_array($info['ext'],$imageThumb) 
			){
				return IO::fileOutImage($path,$this->in['width']);
			}
		}
		$this->updateLastOpen($path);
		IO::fileOut($path,$isDownload);
	}
	/*
	相对某个文件访问其他文件; 权限自动处理;支持source,分享路径,io路径,物理路径;
	path={source:1138926}/&add=images/as.png; path={source:1138926}/&add=as.png
	path={shareItem:123}/1138934/&add=images/as.png
	*/
	public function fileOutBy(){
		if(!$this->in['path']) return; 
		
		// 拼接转换相对路径;
		$io = IO::init($this->in['path']);
		$parent = $io->getPathOuter($io->pathFather($io->path));
		$find   = $parent.'/'.rawurldecode($this->in['add']); //支持中文空格路径等;
		$find   = KodIO::clear(str_replace('./','/',$find));
		$info   = IO::infoFull($find);
		// pr($parent,$this->in,$find,$info,IO::info($this->in['path']));exit;
		if(!$info || $info['type'] != 'file'){
			return show_json(LNG('common.pathNotExists'),false);
		}

		$dist = $info['path'];
		ActionCall('explorer.auth.canView',$dist);// 再次判断新路径权限;
		$this->updateLastOpen($dist);
		IO::fileOut($dist,false);
	}
	
	/**
	 * 打开自己的文档；更新最后打开时间
	 */
	private function updateLastOpen($path){
		$driver = IO::init($path);
		if($driver->pathParse['type'] != KodIO::KOD_SOURCE) return;

		$sourceID = $driver->pathParse['id'];
		$sourceInfo = $this->model->sourceInfo($sourceID);
		if( $sourceInfo['targetType'] == SourceModel::TYPE_USER && 
			$sourceInfo['targetID'] == USER_ID ){
			$data = array('viewTime' => time());
			$this->model->where(array('sourceID'=>$sourceID))->save($data);
		}
	}
	
	//通用保存
	public function fileSave(){
		if(!$this->in['path'] || !$this->in['path']) return; 
		$result = IO::setContent($this->in['path'],$this->in['content']);
		Hook::trigger("explorer.fileSaveStart",$this->in['path']);
		show_json($result,!!$result);
	}
	//通用预览
	public function fileView(){
	}

	//通用缩略图
	public function fileThumb(){
		Hook::trigger("explorer.fileThumbStart",$this->in['path']);
	}	
}