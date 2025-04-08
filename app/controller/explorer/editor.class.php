<?php
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/

class explorerEditor extends Controller{
	function __construct()    {
		parent::__construct();
	}
	
	public function fileGet(){
		$path = Input::get('path','require');
		$this->fileGetHistoryCheck($path);
		$this->fileGetZipContentCheck($path);
		if(request_url_safe($path)){
			$driver   = new PathDriverUrl();
			return $this->fileGetMake($path,$driver->info($path),$path);
		}
		$pathInfo = Action('explorer.index')->pathInfoItem($path);
		Action('explorer.index')->updateLastOpen($path);
		$this->fileGetMake($path,$pathInfo);
	}
	
	// 历史版本文件获取;
	private function fileGetHistoryCheck($path){
		if(!request_url_safe($path)) return;
		$urlInfo = parse_url_query($path);
		if(!isset($urlInfo['explorer/history/fileOut']) || !isset($urlInfo['path'])) return;
		
		$this->in['path'] = rawurldecode($urlInfo['path']);
		$this->in['id']   = $urlInfo['id'];
		$info = IO::info($this->in['path']);
		if(!$info) return;
		
		$action = isset($info['sourceID']) ? 'explorer.history':'explorer.historyLocal';
		$pathInfo = Action($action)->fileInfo();// 内部做权限检测;
		if(!$pathInfo){return show_json(LNG('common.pathNotExists'),false);}
		$this->fileGetMake($pathInfo['path'],$pathInfo,$path);exit;
	}
	
	private function contentPage($path,$size){
		// if($size >= 1024*1024*20){show_json(LNG('explorer.editor.fileTooBig'),false);}
		$PAGE_MIN 	= 1024 * 100;
		$PAGE_MAX 	= $GLOBALS['config']['settings']['ioReadMax'];
		$pageNum 	= _get($this->in,'pageNum',1024 * 500);
		$pageNum	= $pageNum <= $PAGE_MIN ? $PAGE_MIN : ($pageNum >= $PAGE_MAX ? $PAGE_MAX : $pageNum);
		if($pageNum >= $size){$pageNum = $size;}

		$pageTotal	= $pageNum > 0 ? ceil($size/$pageNum):0;
		$page		= _get($this->in,'page',1);
		$page		= $page <= 1 ? 1  : ($page >= $pageTotal ? $pageTotal : $page);
		$from = 0;$length = $size;
		if($size > $PAGE_MIN){
			$from = ($page - 1) * $pageNum;
			$length = $pageNum;
		}
		
		if(request_url_safe($path)){
			$driver  = new PathDriverUrl();
			$content = $driver->fileSubstr($path,$from,$length);
		}else{
			$content = IO::fileSubstr($path,$from,$length);
		}
		
		if($content === false){
			show_json(IO::getLastError(LNG('explorer.error')),false);
		}
		if($size == 0){$content = '';}
		return array(
			'content' 	=> $content,
			'pageInfo'	=> array(
				'page'		=> $page,
				'pageNum'	=> $pageNum,
				'pageTotal'	=> $pageTotal,
				'totalNum'	=> $size
			)
		);
	}
	
	// 压缩包内文件请求优化; 请求内部文件链接识别并处理成直接访问
	private function fileGetZipContentCheck($path){
		if(!request_url_safe($path)) return;
		$urlInfo = parse_url_query($path);
		if(!isset($urlInfo['index']) || !isset($urlInfo['path']) || !isset($urlInfo['accessToken'])) return;
		if(!Action('user.index')->accessTokenCheck($urlInfo['accessToken'])){return;}
		
		$zipFile  = rawurldecode($urlInfo['path']);
		$indexArr = @json_decode(rawurldecode($urlInfo['index']),true);
		if(!$indexArr || !$zipFile){show_json(LNG('common.pathNotExists'),false);}
		
		$isShareFile = isset($urlInfo['explorer/share/unzipList']);
		$isViewFile  = isset($urlInfo['explorer/index/unzipList']);
		// 权限判断;
		if($isShareFile){
			$shareFileInfo = Action("explorer.share")->sharePathInfo($zipFile);
			if(!$shareFileInfo){show_json(LNG('explorer.noPermissionAction'),false);}
			$zipFile = $shareFileInfo['path'];
		}else if($isViewFile){
			if(!Action('explorer.auth')->canRead($zipFile)){
				show_json(LNG('explorer.noPermissionAction'),false);
			}
		}else{return;}
		
		// 解压处理;
		$filePart  = IOArchive::unzipPart($zipFile,$indexArr);
		if(!$filePart || !IO::exist($filePart['file'])){
			show_json(LNG('common.pathNotExists'),false);
		}
		$this->fileGetMake($filePart['file'],IO::info($filePart['file']),$path);exit;
	}
	
	public function fileGetMake($path,$pathInfo,$pathUrl = false){
		// pr($path,$pathInfo,$pathUrl);exit;
		if($pathUrl){ //url类文件信息处理; 只读, pathUrl,编辑器刷新支持;
			$urlInfo = parse_url_query($pathUrl);
			$showPath = trim(rawurldecode($urlInfo['name']),'/');
			if(isset($urlInfo['index']) && $urlInfo['index']){ // 压缩包内部文件;
				$indexArr = json_decode(rawurldecode($urlInfo['index']),true);
				if(is_array($indexArr)){$showPath = $indexArr[count($indexArr) - 1]['name'];}
			}
			$pathInfo['path'] 	= '';$pathInfo['pathUrl'] = $pathUrl;
			$pathInfo['name'] 	= get_path_this($showPath);
			$pathInfo['ext'] 	= get_path_ext($pathInfo['name']);
			$pathInfo['pathDisplay'] = "[".$showPath."]";
			$pathInfo['isWriteable'] = false;
		}
		if(!$pathInfo || $pathInfo['type'] == 'folder'){
			return show_json(LNG('common.pathNotExists'),false);
		}
		Hook::trigger('explorer.fileGet', $path);
		$contentInfo = $this->contentPage($path,$pathInfo['size']);
		$content 	 = $contentInfo['content'];
		if(isset($this->in['charset']) && $this->in['charset']){
			$charset = strtolower($this->in['charset']);
		}else{
			$charset = strtolower(get_charset($content));
		}
		
		//ISO-8859-1 // 0x00 ~ 0xff;不做转换 1字节全; byte类型;  避免编码转换导致内容变化的情况: \xC3\x86 => \xC6;
		$isBindary = strstr($content,"\x00") ? true:false;//pr($isBindary,$content);exit;
		if($isBindary){$this->in['base64'] = 1;}
		if($isBindary && !isset($this->in['charset'])){$charset = 'iso-8859-1';}
		if(!$isBindary && $charset !='' && $charset !='utf-8' && function_exists("mb_convert_encoding")){
			$content = @mb_convert_encoding($content,'utf-8',$charset);
		}
		
		$data = array_merge($pathInfo,array(
			'charset'		=> $charset,
			'base64'		=> $this->in['base64'] == '1' ?'1':'0',// 部分防火墙编辑文件误判问题处理
			'pageInfo' 		=> $contentInfo['pageInfo'],
			'content'		=> $content,
		));
		
		// 避免截取后乱码情况; 去掉不完整的字符(一个汉字3字节,最后剩余1,2字节都会导致乱码)
		if(!$isBindary && is_text_file($pathInfo['ext']) && $data['pageInfo']['pageTotal'] > 1){
			$data['content'] = utf8Repair($data['content'],chr(1));
		}
		if($data['base64']=='1'){
			$data['content'] = strrev(base64_encode($data['content']));
		}
		show_json($data);
	}
	
	public function fileSave(){
		$data = Input::getArray(array(
			'path'			=> array('check'=>'require'),
			'content'		=> array('default'=>''),
			'base64'		=> array('default'=>''),
			'charset'		=> array('default'=>''),
			'charsetSave' 	=> array('default'=>''),
		));
		$pathInfo = IO::info($data['path']);
		if(!$pathInfo) show_json(LNG('common.pathNotExists'),false);
		
		//支持二进制文件读写操作（base64方式）
		$content = $data['content'];
		if($data['base64'] == '1'){
			$content = base64_decode(strrev($content));//避免防火墙拦截部分关键字内容
		}
		$charset 	 = strtolower($data['charset']);
		$charsetSave = strtolower($data['charsetSave']);
		$charset  	 = $charsetSave ? $charsetSave : $charset;
		if ( $charset !='' && 
			 $charset != 'utf-8' && 
			 $charset != 'ascii' &&
			 function_exists("mb_convert_encoding")
			) {
			$content = @mb_convert_encoding($content,$charset,'utf-8');
		}
		$result = IO::setContent($data['path'],$content);
		$msg = $result ? LNG("explorer.saveSuccess") : IO::getLastError(LNG('explorer.saveError'));
		$pathInfo = Action('explorer.index')->pathInfoItem($data['path']);
		show_json($msg,!!$result,$pathInfo);
	}
	
	/*
	* 保存编辑器配置信息
	*/
	public function setConfig(){
		$optionKey = array_keys($this->config['editorDefault']);
		$data = Input::getArray(array(
			"key"	=> array("check"=>"in","param"=>$optionKey),
			"value"	=> array("check"=>"require"),
		));
		Model('UserOption')->set($data['key'],$data['value'],'editor');
		show_json(LNG('explorer.settingSuccess'));
	}	
}
