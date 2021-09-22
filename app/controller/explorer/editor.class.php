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
		if(request_url_safe($path)){
			$urlInfo = parse_url_query($path);
			$driver  = new PathDriverUrl();
			$pathInfo  = $driver->info($path);
			$pathInfo['path'] = '';
			$pathInfo['name'] = isset($urlInfo['name']) ? rawurldecode($urlInfo['name']) : $pathInfo['name'];
			$pathInfo['pathDisplay'] = "[" . trim($pathInfo['name'], '/') . "]";
			return $this->fileGetMake($path,$pathInfo);
		}
		$pathInfo = IO::info($path);
		$pathInfo = Action('explorer.list')->pathInfoParse($pathInfo);
		$this->fileGetMake($path,$pathInfo);
	}
	
	private function contentPage($path,$size){
		// if($size >= 1024*1024*20){show_json(LNG('explorer.editor.fileTooBig'),false);}
		$PAGE_MIN 	= 1024 * 100;
		$PAGE_MAX 	= 1024 * 1024 * 10;
		$pageNum 	= _get($this->in,'pageNum',1024 * 500);
		$pageNum	= $pageNum <= $PAGE_MIN ? $PAGE_MIN : ($pageNum >= $PAGE_MAX ? $PAGE_MAX : $pageNum);
		$pageTotal	= ceil($size/$pageNum);
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

	private function fileGetMake($path,$pathInfo){
		// pr($path,$pathInfo);exit;
		if(!$pathInfo || $pathInfo['type'] == 'folder'){
			return show_json(LNG('common.pathNotExists'),false);
		}
		$contentInfo = $this->contentPage($path,$pathInfo['size']);
		$content 	 = $contentInfo['content'];
		if(isset($this->in['charset']) && $this->in['charset']){
			$charset = strtolower($this->in['charset']);
		}else{
			$charset = get_charset($content);
		}
		if ($charset !='' && $charset !='utf-8' && function_exists("mb_convert_encoding")){
			$content = @mb_convert_encoding($content,'utf-8',$charset);
		}
		
		$data = array_merge($pathInfo,array(
			'charset'		=> $charset,
			'base64'		=> $this->in['base64'] == '1' ?'1':'0',// 部分防火墙编辑文件误判问题处理
			'pageInfo' 		=> $contentInfo['pageInfo'],
			'content'		=> $content,
		));
		
		// 避免截取后乱码情况; 去掉不完整的字符(一个汉字3字节,最后剩余1,2字节都会导致乱码)
		if(is_text_file($pathInfo['ext']) && $data['pageInfo']['pageTotal'] > 1){
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
		$msg = $result ? LNG("explorer.saveSuccess") : LNG('explorer.saveError');
		show_json($msg,!!$result);
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
