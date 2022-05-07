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
		$notCheck = array('link','file','pathParse','fileDownloadRemove');
		// 检测并处理分享信息
		if( equal_not_case(ST,'share') && 
			!in_array_not_case(ACT,$notCheck) ){
			$shareID = $this->parseShareID();
			$this->initShare($shareID);
			if(equal_not_case(MOD.'.'.ST,'explorer.share')){
				$this->authCheck();
			}
		}
	}

	// 自动解析分享id; 通过path或多选时dataArr;
	private function parseShareID(){
		if(!defined('USER_ID')){define('USER_ID',0);}
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
	public function linkFile($file){
		$pass = Model('SystemOption')->get('systemPassword');
		$hash = Mcrypt::encode($file,$pass,false,'kodcloud');
		return urlApi('explorer/share/file',"hash={$hash}");
	}
	
	public function linkSafe($path,$downFilename=''){
		if(!$path || !$info = IO::info($path)) return;
		$link = Action('user.index')->apiSignMake('explorer/index/fileOut',array('path'=>$path));
		
		$addParam = '&name=/'.rawurlencode($info['name']);
		$addParam .= $downFilename ? '&downFilename='.rawurlencode($downFilename):'';
		return $link.$addParam;
	}
	
	public function linkOut($path,$token=false){
		if(!defined("USER_ID")){define("USER_ID",0);}
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
		$pass = Model('SystemOption')->get('systemPassword');
		$path = Mcrypt::decode($this->in['hash'],$pass);
		if(!$path || !IO::info($path)){
			show_json(LNG('common.pathNotExists'),false);
		}
		$fileInfo = IO::info($path);
		if($fileInfo['isDelete'] == '1'){
		    show_json(LNG('explorer.pathInRecycle'),false);
		}
		//pr(IO::info($path));exit;
		$isDownload = isset($this->in['download']) && $this->in['download'] == 1;
		$downFilename = !empty($this->in['downFilename']) ? $this->in['downFilename'] : false;
		IO::fileOut($path,$isDownload,$downFilename);
	}
	
	/**
	 * 其他业务通过分享路径获取文档真实路径; 文件打开构造的路径 hash/xxx/xxx; 
	 * 解析路径,检测分享存在,过期时间,下载次数,密码检测;
	 */
	public function sharePathInfo($path,$encode=false,$infoChildren=false){
		$parse = KodIO::parse($path);
		if(!$parse || $parse['type'] != KodIO::KOD_SHARE_LINK){
			return false;
		}
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
		if($this->share) return;
		$this->share = $share = $this->model->getInfoByHash($hash);
		if(!$share || $share['isLink'] != '1'){
			show_json(LNG('explorer.share.notExist'),30100);
		}
		if($share['sourceInfo']['isDelete'] == '1'){
			show_json(LNG('explorer.share.notExist'),30100);
		}
		//外链分享有效性处理: 当分享者被禁用,没有分享权限,所在文件不再拥有分享权限时自动禁用外链分享;
		if(!Action('explorer.authUser')->canShare($share)){
			$userInfo = Model('User')->getInfoSimpleOuter($share['userID']);
			$tips = '('.$userInfo['name'].' - '.LNG('common.noPermission').')';
			show_json(LNG('explorer.share.notExist') .$tips,30100);
		}

		//检测是否过期
		if($share['timeTo'] && $share['timeTo'] < time()){
			show_json(LNG('explorer.share.expiredTips'),30101,$this->get(true));
		}

		//检测下载次数限制
		if( $share['options'] && 
			$share['options']['downloadNumber'] && 
			$share['options']['downloadNumber'] <= $share['numDownload'] ){
			$msg = LNG('explorer.share.downExceedTips');
			$pathInfo = explode('/', $this->in['path']);
			if(!empty($pathInfo[1]) || is_ajax()) {
				show_json($msg,30102,$this->get(true));
			}
			show_tips($msg);
		}
		//检测是否需要登录
		$user = Session::get("kodUser");
		if( $share['options'] && 
			$share['options']['onlyLogin'] == '1' && 
			!is_array($user)){
			show_json(LNG('explorer.share.loginTips'),30103,$this->get(true));
		}
		//检测密码
		$passKey  = 'Share_password_'.$share['shareID'];
		if( $share['password'] ){
			if( isset($this->in['password']) ){
				$code = md5(BASIC_PATH.Model('SystemOption')->get('systemPassword'));
				$pass = Mcrypt::decode(trim($this->in['password']),md5($code));
				
				if($pass == $share['password']){
					Session::set($passKey,$pass);
				}else{
					show_json(LNG('explorer.share.errorPwd'),false);
				}
			}
			// 检测密码
			if( Session::get($passKey) != $share['password'] ){
				show_json(LNG('explorer.share.needPwd'),30104,$this->get(true));
			}
		}
	}

	/**
	 * 权限检测
	 * 下载次数，预览次数记录
	 */
	private function authCheck(){
		$share = $this->share;
		$where = array("shareID"=>$share['shareID']);
		if( equal_not_case(ACT,'get') ){
			$this->model->where($where)->setAdd('numView');
		}
		//权限检测；是否允许下载、预览、上传;
		if( $share['options'] && 
			$share['options']['notDownload'] == '1' && 
			((equal_not_case(ACT,'fileOut') && $this->in['download']=='1') || 
			equal_not_case(ACT,'zipDownload')) ){
			show_json(LNG('explorer.share.noDownTips'),false);
		}
		if( $share['options'] && 
			$share['options']['notView'] == '1' && 
			(	equal_not_case(ACT,'fileGet') ||
				equal_not_case(ACT,'fileOut') ||
				equal_not_case(ACT,'unzipList')
			)
		){
			show_json(LNG('explorer.share.noViewTips'),false);
		}
		if( $share['options'] && 
			$share['options']['canUpload'] != '1' && 
			equal_not_case(ACT,'fileUpload') ){
			show_json(LNG('explorer.share.noUploadTips'),false);
		}
		if((equal_not_case(ACT,'fileOut') && $this->in['download']=='1') ||
			equal_not_case(ACT,'zipDownload') || 
			equal_not_case(ACT,'fileDownload')){
			$this->model->where($where)->setAdd('numDownload');
		}
	}
	/**
	 * 检测并获取真实路径;
	 */
	private function parsePath($path){
		if(request_url_safe($path)) return $path;//压缩包支持;
		$rootSource = $this->share['sourceInfo']['path'];
		$parse = KodIO::parse($path);
		if(!$parse || $parse['type']  != KodIO::KOD_SHARE_LINK ||
			$this->share['shareHash'] != $parse['id'] ){
			show_json(LNG('explorer.dataError'),false);
		}
		
		$pathInfo = IO::infoFull($rootSource.$parse['param']);
		if(!$pathInfo){
			show_json(LNG('explorer.pathError'),false);
		}
		return $pathInfo['path'];
	}
	
	public function pathInfo(){
		$fileList = json_decode($this->in['dataArr'],true);
		if(!$fileList) show_json(LNG('explorer.error'),false);

		$result = array();
		for ($i=0; $i < count($fileList); $i++) {
			$path 	= $this->parsePath($fileList[$i]['path']);
			$result[] = $this->shareItemInfo(IO::infoWithChildren($path));
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
		if(request_url_safe($path)) {
			header('Location:' . $path);exit;
		} 
		$path = $this->parsePath($path);
		$isDownload = isset($this->in['download']) && $this->in['download'] == 1;
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
		IO::fileOut($path,$isDownload);
	}
	public function fileDownload(){
		$this->in['download'] = 1;
		$this->fileOut();
	}
	
	public function fileUpload(){
		$this->in['path'] = $this->parsePath($this->in['path']);
		Action("explorer.upload")->fileUpload();
	}
	public function fileGet(){
		$this->in['path'] = $this->parsePath($this->in['path']);
		$this->in['pageNum'] = 1024 * 1024 * 10;
		$result = ActionCallHook("explorer.editor.fileGet");
		if($result['code']){
			$result['data'] = $this->shareItemInfo($result['data']);
		}
		show_json($result['data'],$result['code'],$result['info']);
	}
	
	public function pathList(){
		$path = $this->in['path'];
		$pathParse = KodIO::parse($path);
		if($pathParse['type'] == KodIO::KOD_SEARCH){
			$searchParam = Action('explorer.listSearch')->parseSearch($pathParse['param']);
			$this->parsePath($searchParam['parentPath']); //校验path;
			$data = Action('explorer.listSearch')->listSearchPath($pathParse);
			Action('explorer.list')->pageParse($data);
			$data['current']  = Action('explorer.list')->pathCurrent($path);
			$data['thisPath'] = $path;
		}else{
			$allowPath = explode(',','{block:fileType}'); //允许的目录;虚拟目录;
			if(!in_array($path,$allowPath)){
				$this->parsePath($path); //校验path;
			}
			$data = Action('explorer.list')->path($path);
		}
		show_json($data);
	}
	
	/**
	 * 分享压缩下载
	 * 压缩和下载合并为同一方法
	 */
	public function zipDownload(){
		$this->zipSupportCheck();
		$dataArr = json_decode($this->in['dataArr'],true);
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
	private function zipSupportCheck(){
		$config = Model('SystemOption')->get();
		if($config['shareLinkZip'] == '1') return true;
		show_json(LNG('admin.setting.shareLinkZipTips'),false);
	}
	

	public function shareItemInfo($item){
		$rootPath = $this->share['sourceInfo']['pathDisplay'];
		// 物理路径,io路径;
		if($this->share['sourceID'] == '0'){
			$rootPath = KodIO::clear($this->share['sourcePath']);
		}
		$item['pathDisplay'] = $item['pathDisplay'] ? $item['pathDisplay']:$item['path'];

		$field = array(
			'name','path','type','size','ext',
			'createUser','modifyUser','createTime','modifyTime','sourceID',
			'hasFolder','hasFile','children','targetType','targetID','pageInfo',
			'base64','content','charset','oexeContent','fileInfoMore','fileThumb',
		);
		$theItem = array_field_key($item,$field);
		$path 	 = KodIO::makePath(KodIO::KOD_SHARE_LINK,$this->share['shareHash']);
		$name    = $this->share['sourceInfo']['name'];
		$theItem['pathDisplay'] = ltrim(substr($item['pathDisplay'],strlen($rootPath)),'/');
		$theItem['path'] = rtrim($path,'/').'/'.$theItem['pathDisplay'];
		$theItem['pathDisplay'] = $name.'/'.$theItem['pathDisplay'];

		if($theItem['type'] == 'folder'){
			$theItem['ext'] = 'folder';
		}
		if(is_array($theItem['createUser'])) $theItem['createUser'] = $this->filterUserInfo($theItem['createUser']);
		if(is_array($theItem['modifyUser'])) $theItem['modifyUser'] = $this->filterUserInfo($theItem['modifyUser']);
		return $theItem;
	}
	private function filterUserInfo($userInfo){
		$name = !empty($userInfo['nickName']) ? $userInfo['nickName'] : $userInfo['name'];
		unset($userInfo['nickName'], $userInfo['name']);
		$userInfo['nameDisplay'] = $this->parseName($name);
		return $userInfo;
	}
	private function parseName($name){
		$len = mb_strlen($name);
		if($len > 3) {
			$len = ($len > 5 ? 5 : $len) - 2;
			$name = mb_substr($name, 0, 2) . str_repeat('*', $len);	// AA***
		}else{
			$name = mb_substr($name, 0, 1) . str_repeat('*', $len - 1);	// A**
		}
		return $name;
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