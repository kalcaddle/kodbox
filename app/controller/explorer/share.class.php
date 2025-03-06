<?php 
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/

class explorerShare extends Controller{
	private $model;
	function __construct(){
		parent::__construct();
		$this->model  = Model('Share');
		$notCheck = array('link','file','pathParse','fileDownloadRemove','unzipListHash','fileGetHash');
		// 检测并处理分享信息
		if( strtolower(ST) == 'share' && !in_array_not_case(ACT,$notCheck) ){
			$shareID = $this->parseShareID();
			$this->initShare($shareID);
			if(strtolower(MOD.'.'.ST) == 'explorer.share'){$this->authCheck();}
		}
	}

	// 自动解析分享id; 通过path或多选时dataArr;
	private function parseShareID(){
		$shareID = $this->in['shareID'];
		if($shareID) return $shareID;
		$thePath = $this->in['path'];
		if(!$thePath && isset($this->in['dataArr'])){
			$fileList = json_decode($this->in['dataArr'],true);
			$thePath  = $fileList[0]['path'];
		}
		$parse = KodIO::parse($thePath);
		if($parse['type'] == KodIO::KOD_SHARE_LINK){
			$shareID = $parse['id'];
		}
		return $shareID;
	}

	// 通用生成外链
	public function link($path,$downFilename=''){
		if(!$path || !$info = IO::info($path)) return;
		$pass = Model('SystemOption')->get('systemPassword');
		$hash = Mcrypt::encode($info['path'],$pass);

		$addParam = '&name=/'.rawurlencode($info['name']);
		if($downFilename){
		    $addParam = '&downFilename='.rawurlencode($downFilename);
		}
		return urlApi('explorer/share/file',"hash={$hash}".$addParam);
	}
	
	// 构造加密链接,相同文件每次一样,确保浏览器能够缓存;
	public function linkFile($file){
		$pass = Model('SystemOption')->get('systemPassword');
		$hash = Mcrypt::encode($file,$pass,false,'kodcloud');
		return urlApi('explorer/share/file',"hash={$hash}");
	}
	
	public function linkSafe($path,$downFilename=''){
		if(!KodUser::isLogin()){return $this->link($path,$downFilename);}
		if(!$path || !$info = IO::info($path)) return;
		$link = Action('user.index')->apiSignMake('explorer/index/fileOut',array('path'=>$path));
		
		$addParam = '&name=/'.rawurlencode($info['name']);
		$addParam .= $downFilename ? '&downFilename='.rawurlencode($downFilename):'';
		return $link.$addParam;
	}
	
	public function linkOut($path,$token=false){
		$parse  = KodIO::parse($path);
		$info   = IO::info($path);
		$apiKey = 'explorer/index/fileOut';
		if($parse['type'] == KodIO::KOD_SHARE_LINK){
			$apiKey = 'explorer/share/fileOut';
		}
		$etag = substr(md5($path),0,5);
		$name = isset($this->in['name']) ? rawurlencode($this->in['name']):'';
		if($info){
			$name = rawurlencode($info['name']);
			$etag = substr(md5($info['modifyTime'].$info['size']),0,5);
		}
		$url = urlApi($apiKey,"path=".rawurlencode($path).'&et='.$etag.'&name=/'.$name);
		if($token) $url .= '&accessToken='.Action('user.index')->accessToken();
		return $url;
	}
	
	public function file(){
		if(!$this->in['hash']) return;
		$path = $this->fileHash($this->in['hash']);
		$isDownload = isset($this->in['download']) && $this->in['download'] == 1;
		$downFilename = !empty($this->in['downFilename']) ? $this->in['downFilename'] : false;
		Action('explorer.index')->fileOutUpdate($path,$isDownload,$downFilename);
	}
	
	// 文件外链解析;
	private function fileHash($hash){
		if(!$hash || strlen($hash) > 5000) return;
		$path = Mcrypt::decode($hash,Model('SystemOption')->get('systemPassword'));
		$fileInfo = $path ? IO::info($path) : false;
		if(!$fileInfo){show_json(LNG('common.pathNotExists'),false);}
		if($fileInfo['isDelete'] == '1'){show_json(LNG('explorer.pathInRecycle'),false);}
		return $path;
	}
	
	/**
	 * 其他业务通过分享路径获取文档真实路径; 文件打开构造的路径 hash/xxx/xxx; 
	 * 解析路径,检测分享存在,过期时间,下载次数,密码检测;
	 */
	public function sharePathInfo($path,$encode=false,$infoChildren=false){
		$parse = KodIO::parse($path);
		if(!$parse || $parse['type'] != KodIO::KOD_SHARE_LINK){return false;}
		$check = ActionCallHook('explorer.share.initShare',$parse['id']);
		if(is_array($check)){
			$GLOBALS['explorer.sharePathInfo.error'] = $check['data'];
			return false; // 不存在,时间过期,下载次数超出,需要登录,需要密码;
		}
		$truePath = $this->parsePath($path);
		if($infoChildren){
			$result = IO::infoWithChildren($truePath);
		}else{
			$result = IO::info($truePath);
		}
		
		// 仅文件,判断预览;
		if(	$result['type'] == 'file' && 
			$this->share['options'] && 
			$this->share['options']['notDownload'] == '1' &&
			$this->share['options']['notView'] == '1'){
			return false;
		}		
		if(is_array($result)){
			if($encode){
				$result = $this->shareItemInfo($result);
			}
			$result['shareID'] = $this->share['shareID'];
			$result['option']  = $this->share['options'];
		}
		return $result;
	}

	/**
	 * 通过分享hash获取分享信息；
	 * 提交分享密码
	 */
	public function get($get=false){
		$field = array(
			'shareHash','title','isLink','timeTo','numView','numDownload',
			'options','createTime','sourceInfo',
		);
		$data  = array_field_key($this->share,$field);
		$data['shareUser']  = Model("User")->getInfoSimpleOuter($this->share['userID']);
		$data['shareUser']  = $this->filterUserInfo($data['shareUser']);
		$data['sourceInfo'] = $this->shareItemInfo($data['sourceInfo']);
		if($get) return $data;
		show_json($data);
	}
	
	/**
	 * 分享信息初始化；
	 * 拦截相关逻辑
	 * 
	 * 过期拦截
	 * 下载次数限制拦截
	 * 登录用户拦截
	 * 密码检测拦截：如果参数有密码则检测并存储；错误则提示 
	 * 下载次数，预览次数记录
	 */
	public function initShare($hash=''){
		if($this->share && !isset($GLOBALS['isRootCall'])) return;
		$this->share = $share = $this->model->getInfoByHash($hash);
		if(!$share || $share['isLink'] != '1'){
			$this->showError(LNG('explorer.share.notExist'),30100);
		}
		if($share['sourceInfo']['isDelete'] == '1'){
			$this->showError(LNG('explorer.share.notExist'),30100);
		}
		if(isset($GLOBALS['isRootCall']) && $GLOBALS['isRootCall']){return;} //后台任务队列获取属性,不做判断;
		
		//外链分享有效性处理: 当分享者被禁用,没有分享权限,所在文件不再拥有分享权限时自动禁用外链分享;
		if(!Action('explorer.authUser')->canShare($share)){
			$userInfo = Model('User')->getInfoSimpleOuter($share['userID']);
			$tips = '('.$userInfo['name'].' - '.LNG('common.noPermission').')';
			$this->showError(LNG('explorer.share.notExist') .$tips,30100);
		}

		//检测是否过期
		if($share['timeTo'] && $share['timeTo'] < time()){
			$this->showError(LNG('explorer.share.expiredTips'),30101,$this->get(true));
		}

		//检测下载次数限制
		if( $share['options'] && 
			$share['options']['downloadNumber'] && 
			$share['options']['downloadNumber'] <= $share['numDownload'] ){
			$msg = LNG('explorer.share.downExceedTips');
			$pathInfo = explode('/', $this->in['path']);
			if(!empty($pathInfo[1]) || is_ajax()) {
				$this->showError($msg,30102,$this->get(true));
			}
			show_tips($msg);
		}
		//检测是否需要登录
		$user = Session::get("kodUser");
		if( $share['options'] && $share['options']['onlyLogin'] == '1' && !is_array($user)){
			$this->showError(LNG('explorer.share.loginTips'),30103,$this->get(true));
		}
		//检测密码
		$passKey  = 'Share_password_'.$share['shareID'];
		if( $share['password'] ){
			if( isset($this->in['password']) && strlen($this->in['password']) < 500 ){
				$code = md5(BASIC_PATH.Model('SystemOption')->get('systemPassword'));
				$pass = Mcrypt::decode(trim($this->in['password']),md5($code));
				
				if($pass == $share['password']){
					Session::set($passKey,$pass);
				}else{
					$this->showError(LNG('explorer.share.errorPwd'),false);
				}
			}
			// 检测密码
			if( Session::get($passKey) != $share['password'] ){
				$this->showError(LNG('explorer.share.needPwd'),30104,$this->get(true));
			}
		}
	}
	private function showError($msg,$code,$info=false){
		$action = strtolower(ACT);
		$tipsAction = array('fileout','filedownload');
		if(in_array($action,$tipsAction)){return show_tips($msg);}
		show_json($msg,$code,$info);
	}

	/**
	 * 权限检测
	 * 下载次数，预览次数记录
	 */
	private function authCheck(){
		$ACT   = strtolower(ACT);
		$share = $this->share;
		$where = array("shareID"=>$share['shareID']);
		if($ACT == 'get'){
			$this->model->where($where)->setAdd('numView');
			$this->cacheShareClear();
		}
		//权限检测；是否允许下载、预览、上传;
		if( $share['options'] && 
			$share['options']['notDownload'] == '1' && 
			((equal_not_case(ACT,'fileOut') && $this->in['download']=='1') || 
			equal_not_case(ACT,'zipDownload')) ){
			$this->showError(LNG('explorer.share.noDownTips'),false);
		}
		if( $share['options'] && 
			$share['options']['notView'] == '1' && 
			$share['options']['notDownload'] == '1' && 
			(	equal_not_case(ACT,'fileGet') ||
				equal_not_case(ACT,'fileOut') ||
				equal_not_case(ACT,'unzipList')
			)
		){
			$this->showError(LNG('explorer.share.noViewTips'),false);
		}
		
		// 分享者角色没有上传权限时, 忽略配置开启上传;
		$shareUser = $share['userID'];
		$actionUpload = array('fileupload','mkdir','mkfile');
		$actionEdit   = array('fileupload','mkdir','mkfile','pathrename','pathcopy','pathcute','pathcuteto','pathcopyto','pathpast','filesave');
		$canUpload 	  = is_array($share['options']) && $share['options']['canUpload'] == '1';
		$canEdit 	  = is_array($share['options']) && $share['options']['canEditSave'] == '1';
		// TODO 有编辑权限,一定包含上传权限;
		if(in_array($ACT,$actionUpload) && !$canEdit){
			if($canUpload && !Action('user.authRole')->authCanUser('explorer.upload',$shareUser)){$canUpload = false;}
			if($canUpload && isset($this->in['path']) && !$this->checkPathAuth($this->in['path'])){$canUpload = false;}
			if(!$canUpload){$this->showError(LNG('explorer.noPermissionWriteAll'),false);}
		}
		if(in_array($ACT,$actionEdit) && !in_array($ACT,$actionUpload)){// 目的地检测;必须为当前分享文件夹下子内容;
			if($canEdit && !Action('user.authRole')->authCanUser('explorer.edit',$shareUser)){$canEdit = false;}
			if($canEdit && isset($this->in['path']) && !$this->checkPathAuth($this->in['path'])){$canEdit = false;}
			if($canEdit && isset($this->in['dataArr']) && !$this->checkPathAuth($this->in['dataArr'],true)){$canEdit = false;}
			if($canEdit && Model('SystemOption')->get('shareLinkAllowEdit') == '0'){$canEdit = false;}
			if(!$canEdit){$this->showError(LNG('explorer.noPermissionWriteAll'),false);}
		}
		
		if((equal_not_case(ACT,'fileOut') && $this->in['download']=='1') ||
			equal_not_case(ACT,'zipDownload') || 
			equal_not_case(ACT,'fileDownload') ){
			// 下载计数; 分片下载时仅记录起始为0的项(并忽略长度为0的请求),忽略head请求;
			if(!Action('admin.log')->checkHttpRange()){return;}
			$this->model->where($where)->setAdd('numDownload');
			$this->cacheShareClear();
		}
	}
	private function checkPathAuth($path,$isArray = false){
		if(!$this->share || !$this->share['shareHash']){return false;}
		$prePath = "{shareItemLink:".$this->share['shareHash']."}";
		if(!$isArray){return strpos(rawurldecode($path),$prePath) === 0 ? true:false;}
		
		$data = json_decode($this->in['dataArr'],true);
		if(!is_array($data)){return false;}
		foreach ($data as $item){
			if(strpos(rawurldecode($item['path']),$prePath) !== 0){return false;}
		}
		return true;
	}
	
	// 清除分享方法缓存
	private function cacheShareClear(){
		$share = $this->share;
		$this->model->cacheFunctionClear('listSimple', $share['userID']);
		$this->model->cacheFunctionClear('getInfo', $share['shareID']);
	}
	/**
	 * 检测并获取真实路径;
	 */
	private function parsePath($path,$allowNotExist=false){
		if(request_url_safe($path)) return $path;//压缩包支持;
		$rootSource = $this->share['sourceInfo']['path'];
		$parse = KodIO::parse($path);
		if(!$parse || $parse['type']  != KodIO::KOD_SHARE_LINK ||
			$this->share['shareHash'] != $parse['id'] ){
			show_json(LNG('common.noPermission'),false);
		}
		
		$pathInfo = IO::infoFullSimple($rootSource.$parse['param']);
		if(!$pathInfo){
			if($allowNotExist){return $rootSource.$parse['param'];}
			show_json(LNG('common.pathNotExists'),false);
		}
		return $pathInfo['path'];
	}
	
	public function pathInfo(){
		$fileList = json_decode($this->in['dataArr'],true);
		if(!$fileList) show_json(LNG('explorer.error'),false);

		$result = array();
		for ($i=0; $i < count($fileList); $i++) {
			$path = $this->parsePath($fileList[$i]['path']);
			$this->fileCheckDownload($path);
			if($this->in['getChildren'] == '1'){
				$pathInfo = IO::infoWithChildren($path);
			}else{
				$pathInfo = IO::infoFull($path);
			}
			if(!is_array($pathInfo)){continue;}
			if(!array_key_exists('hasFolder',$pathInfo)){
				$has = IO::has($path,true);
				if(is_array($has)){$pathInfo = array_merge($pathInfo,$has);}
			}
			
			$pathInfoOut = $this->shareItemInfo($pathInfo);
			if(is_array($pathInfo['fileInfo'])){$pathInfoOut['fileInfo'] = $pathInfo['fileInfo'];} // 用户hash获取等;
			$pathInfoOut = Action('explorer.list')->pathInfoCover($pathInfoOut);//path参数需要是外链路径;
			if(is_array($pathInfoOut['fileInfo'])){unset($pathInfoOut['fileInfo']);}
			$result[] = $pathInfoOut;
		}
		
		if(count($fileList) == 1){
			// 单个文件属性; 没有仅用预览,则开放预览链接;
			if($result[0]['type'] == 'file' &&  _get($this->share,'options.notDownload') != '1' ){
				$param = "shareID=".$this->share['shareHash']."&path=".rawurlencode($result[0]['path']);
				$param .= '&name=/'.rawurlencode($result[0]['name']);
				$result[0]['downloadPath'] = urlApi('explorer/share/fileOut',$param);
			}
			show_json($result[0]);
		}
		show_json($result);		
	}

	//输出文件
	public function fileOut(){
		$path = rawurldecode($this->in['path']);//允许中文空格等;
		if(request_url_safe($path)){header('Location:'.$path);exit;}
		
		$path = $this->parsePath($path);
		$this->fileCheckDownload($path);
		$isDownload = isset($this->in['download']) && $this->in['download'] == 1;
		Hook::trigger('explorer.fileOut', $path);
		if(isset($this->in['type']) && $this->in['type'] == 'image'){
			$info = IO::info($path);
			$imageThumb = array('jpg','png','jpeg','bmp');
			$width = isset($this->in['width']) ? intval($this->in['width']) :0;
			if($isDownload || !$width || $width >= 2000){Action('explorer.index')->updateLastOpen($path);}
			if($info['size'] >= 1024*200 &&
				function_exists('imagecolorallocate') &&
				in_array($info['ext'],$imageThumb) 
			){return IO::fileOutImage($path,$this->in['width']);}
		}
		Action('explorer.index')->fileOutUpdate($path,$isDownload);
	}
	
	public function fileDownload(){
		$this->in['download'] = 1;
		$this->fileOut();
	}
	public function fileOutBy(){
		$add   = kodIO::pathUrlClear(rawurldecode($this->in['add']));
		$path  = rawurldecode($this->in['path']);
		if(request_url_safe($path)){header('Location:'.$path);exit;}
		
		$distPath = kodIO::pathTrue($path.'/../'.$add);
		$this->in['path'] = rawurlencode($distPath);
		if(isset($this->in['type']) && $this->in['type'] == 'getTruePath'){
			show_json($distPath,true);
		}
		$this->fileOut();
	}
	
	private function call($action){
		if(isset($this->in['path'])){
			$this->in['path'] = $this->parsePath($this->in['path'],true);
		}
		$self = $this;
		ActionCallResult($action,function(&$res) use($self){
			if(!$res['code']){return;}
			if(ACT == 'fileSave'){$res['info'] = $this->shareItemInfo($res['info']);return;}
			
			// 粘贴,拖拽情况处理;
			if(ACT == 'pathCuteTo' || ACT == 'pathCopyTo' || ACT == 'pathPast'){
				$resNew = $self->callParsePast($res);
				if($resNew && $resNew['info']){$res['info'] = $resNew['info'];}
				if($resNew && $resNew['infoMore']){$res['infoMore'] = $resNew['infoMore'];}
				return;
			}
			
			if(!$res['info'] || !is_string($res['info'])){return;}
			$pathInfo = $self->shareItemInfo(IO::info($res['info']));
			$res['info'] = $pathInfo['path'];
		});		
	}
	public function fileUpload(){$this->call("explorer.upload.fileUpload");}
	public function mkfile(){$this->call("explorer.index.mkfile");}
	public function mkdir(){$this->call("explorer.index.mkdir");}

	public function pathRename(){$this->call("explorer.index.pathRename");}
	public function pathCopy(){$this->call("explorer.index.pathCopy");}
	public function pathCute(){$this->call("explorer.index.pathCute");}
	public function pathCuteTo(){$this->call("explorer.index.pathCuteTo");}
	public function pathCopyTo(){$this->call("explorer.index.pathCopyTo");}
	public function pathPast(){$this->call("explorer.index.pathPast");}
	public function fileSave(){$this->call("explorer.editor.fileSave");}
	
	public function callParsePast($res){
		if(!is_array($res['info']) || !isset($res['info'][0])){return false;}
		foreach($res['info'] as $i=>$path){
			$pathInfo = $this->shareItemInfo(IO::info($res['info'][$i]));
			$res['info'][$i] = $pathInfo['path'];
			if(is_array($res['infoMore']['listTo'])){
				$res['infoMore']['listTo'][$i] = $pathInfo['path'];
			}
		}
		if(is_array($res['infoMore']) && isset($res['infoMore']['pathTo'])){
			$res['infoMore']['pathTo'] = $_REQUEST['path'];
		}
		return $res;
	}
	
	public function fileGet(){
		$pageNum = 1024 * 1024 * 10;$self = $this;
		$this->in['pageNum'] = isset($this->in['pageNum']) ? $this->in['pageNum'] : $pageNum;
		$this->in['pageNum'] = $this->in['pageNum'] >= $pageNum ? $pageNum : $this->in['pageNum'];
		$this->in['path'] = $this->parsePath($this->in['path']);
		$this->fileCheckDownload($this->in['path']);
		ActionCallResult("explorer.editor.fileGet",function(&$res) use($self){
			if(!$res['code']){return;}
			$pathDisplay = $res['data']['pathDisplay'];
			$res['data'] = $self->shareItemInfo($res['data']);
			if($pathDisplay && substr($pathDisplay,0,1) == '['){$res['data']['pathDisplay'] = $pathDisplay;}
			if(is_array($res['data']['fileInfo'])){unset($res['data']['fileInfo']);}
		});
	}
	
	// 文件下载权限校验
	public function fileCheckDownload($path){
		$parse  = KodIO::parse($path);
		if($parse['type'] == KodIO::KOD_SOURCE){ // 上层包含密码时处理;
			$errorMsg = Action('explorer.listPassword')->authCheck(IO::info($path),'download');
			if($errorMsg){show_json($errorMsg,false,1102);}
		}
	}
	
	// 压缩包内文本文件请求(不再做权限校验; 通过文件外链hash校验处理)
	public function fileGetHash(){
		$pageNum = 1024 * 1024 * 10;
		$this->in['pageNum'] = isset($this->in['pageNum']) ? $this->in['pageNum'] : $pageNum;
		$this->in['pageNum'] = $this->in['pageNum'] >= $pageNum ? $pageNum : $this->in['pageNum'];
		// ActionCall("explorer.editor.fileGet");exit;

		$url = $this->in['path'];
		$urlInfo = parse_url_query($url);
		if( !isset($urlInfo["explorer/share/unzipListHash"]) && 
			!isset($urlInfo["accessToken"])){
			show_json(LNG('common.pathNotExists'),false);
		}
		$index 	  = json_decode(rawurldecode($urlInfo['index']),true);
		$zipFile  = $this->fileHash(rawurldecode($urlInfo['path']));
		$filePart = IOArchive::unzipPart($zipFile,$index ? $index:'-1');
		if(!$filePart || !IO::exist($filePart['file'])){
			show_json(LNG('common.pathNotExists'),false);
		}
		Action("explorer.editor")->fileGetMake($filePart['file'],IO::info($filePart['file']),$url);
	}
	
	public function pathList(){
		$path = $this->in['path'];
		$pathParse = KodIO::parse($path);
		if($pathParse['type'] == KodIO::KOD_SEARCH){
			$searchParam = Action('explorer.listSearch')->parseSearch($pathParse['param']);
			$this->parsePath($searchParam['parentPath']); //校验path;
			$data = Action('explorer.listSearch')->listSearchPath($pathParse);
			Action('explorer.list')->pageParse($data);
			Action('explorer.list')->pathListParse($data);
			$data['current']  = Action('explorer.list')->pathCurrent($path);
			$data['thisPath'] = $path;
		}else{
			$allowPath = explode(',','{block:fileType}'); //允许的目录;虚拟目录;
			if(!in_array($path,$allowPath)){$this->parsePath($path);}
			$data = Action('explorer.list')->path($path);
		}
		
		// 默认自动展开文件夹层级; deep=1/2(文档预览模式,优化请求)
		if(isset($this->in['deep']) && is_array($data['folderList']) && 
			$this->in['deep'] >= 1){
			foreach ($data['folderList'] as $key=>$item){
				$child = Action('explorer.list')->path($item['path']);
				$item['children'] = $child;$data['folderList'][$key] = $item;
				if($this->in['deep'] == 1 || !is_array($child['folderList'])){continue;}

				foreach($child['folderList'] as $key2=>$item2) {
					$child2 = Action('explorer.list')->path($item2['path']);
					$item2['children'] = $child2;
					$data['folderList'][$key]['children']['folderList'][$key2] = $item2;
				}
			}
		}
		
		// 文件快捷方式处理;
		if($data && $data['fileList']){
			foreach($data['fileList'] as &$file){
				if(is_array($file['fileInfo'])){unset($file['fileInfo']);}
				$this->filterOexeContent($file);
			}
		}		
		show_json($data);
	}
	
	/**
	 * 分享压缩下载
	 * 压缩和下载合并为同一方法
	 */
	public function zipDownload(){
		$dataArr = json_decode($this->in['dataArr'],true);
		if($this->in['zipClient'] == '1'){
			$result = Action('explorer.index')->zipDownloadClient($dataArr);
			$sharePath = $this->share['sourcePath'];
			$shareLink = "{shareItemLink:".$this->share['shareHash']."}/";
			foreach($result as $i => $item){
				if(!$item['filePath']){continue;}
				$result[$i]['filePath'] = str_replace($sharePath,$shareLink,$item['filePath']);
			}
			show_json($result,true);return;
		}
		$this->zipSupportCheck();
		foreach($dataArr as $i => $item){
			$dataArr[$i]['path'] = $this->parsePath($item['path']);
		}
		$this->in['dataArr'] = json_encode($dataArr);
		Action('explorer.index')->zipDownload();
	}
	public function fileDownloadRemove(){
		Action('explorer.index')->fileDownloadRemove();
	}
	public function unzipList(){
		$this->zipSupportCheck();
		$this->in['path'] = $this->parsePath($this->in['path']);
		Action('explorer.index')->unzipList();
	}
	public function unzipListHash(){
		$this->zipSupportCheck();
		$this->in['path'] = $this->fileHash($this->in['path']);
		Action('explorer.index')->unzipList();
	}

	private function zipSupportCheck(){
		if(Model('SystemOption')->get('shareLinkZip') == '1') return true;
		show_json(LNG('admin.setting.shareLinkZipTips'),false);
	}
	

	public function shareItemInfo($item){
		$rootPath = $this->share['sourceInfo']['pathDisplay'];
		// 物理路径,io路径;
		if($this->share['sourceID'] == '0'){
			$rootPath = KodIO::clear($this->share['sourcePath']);
			
			// io路径(本地磁盘挂载时); 外链分享搜索时会处理为真实路径情况处理;
			if(substr($item['path'],0,strlen($rootPath)) != $rootPath){
			    $io = IO::init($rootPath);
			    $rootPath = $io->path;
			}
		}
		$item['pathDisplay'] = $item['pathDisplay'] ? $item['pathDisplay']:$item['path'];

		$field = array(
			'name','path','type','size','ext','searchTextFile',
			'createUser','modifyUser','createTime','modifyTime','sourceID','parentLevel',
			'hasFolder','hasFile','children','targetType','targetID','pageInfo','searchContentMatch',
			'base64','content','charset','oexeContent','oexeSourceInfo','fileInfoMore','fileInfo','fileThumb',
			// 'isReadable','isWriteable',//(不处理, 部门文件夹分享显示会有区分)
		);
		$theItem = array_field_key($item,$field);
		$path 	 = KodIO::makePath(KodIO::KOD_SHARE_LINK,$this->share['shareHash']);
		$name    = $this->share['sourceInfo']['name'];
		$theItem['pathDisplay'] = ltrim(substr($item['pathDisplay'],strlen($rootPath)),'/');
		$theItem['path'] = rtrim($path,'/').'/'.$theItem['pathDisplay'];
		$theItem['pathDisplay'] = $name.'/'.$theItem['pathDisplay'];

		if(is_array($item['metaInfo'])){
			$picker = 'user_sourceCover,folderPassword';
			$theItem['metaInfo'] = array_field_key($item['metaInfo'],explode(',',$picker));
		}
		if($theItem['type'] == 'folder'){$theItem['ext'] = 'folder';}
		if(is_array($theItem['createUser'])) $theItem['createUser'] = $this->filterUserInfo($theItem['createUser']);
		if(is_array($theItem['modifyUser'])) $theItem['modifyUser'] = $this->filterUserInfo($theItem['modifyUser']);
		return $theItem;
	}
	
	private function filterOexeContent(&$theItem){
		if($theItem['type'] != 'file' || $theItem['ext'] != 'oexe') return;
		if(!isset($theItem['oexeContent'])) return;
		if($theItem['oexeContent']['type'] != 'path') return;
		
		$rootPath = $this->share['sourceInfo']['pathDisplay'];
		if($this->share['sourceID'] == '0'){
			$rootPath = KodIO::clear($this->share['sourcePath']);
			$pathDisplay = $theItem['oexeContent']['value'];
		}else{
			$sourceInfo  = IO::info($theItem['oexeContent']['value']);
			$pathDisplay = $sourceInfo['pathDisplay'];
		}
		$path = KodIO::makePath(KodIO::KOD_SHARE_LINK,$this->share['shareHash']);
		$pathDisplay = ltrim(substr($pathDisplay,strlen($rootPath)),'/');
		$theItem['oexeContent']['value'] = rtrim($path,'/').'/'.$pathDisplay;
		
		if($sourceInfo){
			$sourceInfo['path'] = $theItem['oexeContent']['value'];
			$theItem['oexeSourceInfo'] =  $sourceInfo;
		}
	}
	private function filterUserInfo($userInfo){
		$name = !empty($userInfo['nickName']) ? $userInfo['nickName'] : $userInfo['name'];
		unset($userInfo['nickName'], $userInfo['name']);
		$userInfo['nameDisplay'] = $this->parseName($name);
		return $userInfo;
	}
	private function parseName($name){
		$len = mb_strlen($name);
		return $len > 2 ? mb_substr($name,0,2).'***':$name;
	}

	/**
	 * 分享文件举报
	 * @return void
	 */
	public function report(){
		$data = Input::getArray(array(
			'path'	=> array('check' => 'require'),
			'type'	=> array('check' => 'in', 'param' => array('1','2','3','4','5')),
			'desc'	=> array('default' => '')
		));
		$fileID = 0;
		if($this->share['sourceInfo']['type'] == 'file') {
			$info = $this->sharePathInfo($data['path']);
			$fileID = $info['fileInfo']['fileID'];
		}
		$data['shareID']	= $this->share['shareID'];
		$data['sourceID']	= $this->share['sourceID'];
		$data['title']		= $this->share['title'];
		$data['fileID']		= $fileID;
		$res = $this->model->reportAdd($data);
		show_json('OK', !!$res);
	}
}