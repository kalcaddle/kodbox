<?php 
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/

/**
 * fileOut数据过滤; fileOutBy 相对路径处理:js-import; css-import.src处理;
 */
class filterFileOut extends Controller{
	function __construct(){
		parent::__construct();
	}
	// 自动绑定处理;
	public function bind(){
		$action = strtolower(ACTION);
		$disableCookie = array(
			'explorer.index.filedownload',
			'explorer.index.fileout',
			'explorer.index.fileoutby',
			'explorer.share.filedownload',
			'explorer.share.fileout',
			'explorer.share.fileoutby',
		);
		if(in_array($action,$disableCookie)){Cookie::disable(true);allowCROS();}
		Hook::bind('PathDriverBase.fileOut.before',array($this,'fileOut'));
	}
	
	public function fileOut($file,$fileSize,$filename,$ext){
		if(!isset($this->in['replaceType']) || !$this->in['replaceType']){return;}
		if(!$filename || $fileSize >= 10*1024*1024 || !in_array($ext,array('css','js')) ){return;}

		$content = IO::getContent($file);
		if($ext == 'css' && $this->in['replaceType'] == 'css-import'){$this->cssParse($content);}
		if($ext == 'js'  && $this->in['replaceType'] == 'script-import'){$this->scriptParse($content);}
		if($ext == 'js'  && $this->in['replaceType'] == 'script-wasm'){$this->scriptParseWasm($content);}
	}
	
	private function cssParse($content){
		$self = $this;
		$content = preg_replace_callback("/url\s*\(\s*['\"]*(.*?)['\"]*\s*\)/",function($matchs) use($self){
			return 'url("'.$self->urlFilter($matchs[1]).'")';
		},$content);
		$content = preg_replace_callback("/@import\s+['\"](.*\.css)['\"]/u",function($matchs) use($self){
			return '@import "'.$self->urlFilter($matchs[1]).'"';
		},$content);
		$this->output($content);
	}
	private function scriptParse($content){
		$self = $this;
		$content = preg_replace_callback("/self\.importScripts\s*\(\s*['\"]*(.*?)['\"]*\s*\)/",function($matchs) use($self){
			return 'self.importScripts("'.$self->urlFilter($matchs[1]).'")';
		},$content);
		$content = preg_replace_callback("/\s+from\s+['\"](.*?\.js)['\"]/",function($matchs) use($self){
			return ' from "'.$self->urlFilter($matchs[1]).'"';
		},$content);
		$content = preg_replace_callback("/import\s+['\"](.*?\.js)['\"]/",function($matchs) use($self){
			return 'import "'.$self->urlFilter($matchs[1]).'"';
		},$content);
		$this->output($content);
	}
	private function scriptParseWasm($content){
		$self = $this;
		$content = preg_replace_callback("/=\s*\"([\w\.\-\_]+\.wasm)\"/",function($matchs) use($self){
			return '="'.$self->urlFilter($matchs[1]).'"';
		},$content);
		$this->output($content);
	}
	
	private function urlFilter($url){
		if(strpos($url,'?') > 0){$url = substr($url,0,strpos($url,'?'));}
		if(strpos($url,'#') > 0){$url = substr($url,0,strpos($url,'#'));}
		
		// 采用相对路径重新计算; 确保多个位置import 最后引用路径一致; 
		// 路径有变化时js多个地方import同一个文件,但url路径不一致,时会导致重复执行;
		$addNew = kodIO::pathTrue($this->in['add'].'/../'.$url);
		$param  = '&safeToken='.$this->in['safeToken'].'&replaceType='.$this->in['replaceType'];
		$url = APP_HOST.'?'.str_replace('.','/',ACTION);
		return $url.$param.'&path='.rawurlencode($this->in['path']).'&add='.rawurlencode($addNew);
	}
	private function output($content){
		header('HTTP/1.1 200 OK');
		header('Content-Encoding: none');
		header('Content-Length:'.strlen($content));
		echo $content;exit;
	}
}