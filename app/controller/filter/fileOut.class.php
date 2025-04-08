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
			'explorer.index.fileoutby',
			'explorer.share.fileoutby',
			'explorer.index.filedownload',
			'explorer.index.fileout',
			'explorer.share.filedownload',
			'explorer.share.fileout',
		);
		$allowViewToken = array(
			'explorer.index.fileoutby',
			'explorer.share.fileoutby',
		);
		
		if(in_array($action,$disableCookie)){
			Cookie::disable(true);allowCROS();
		}
		
		// 仅限当前ip使用的token,有效期1天(该token仅限文件相对路径获取使用)
		if(in_array($action,$allowViewToken)){
			$token = isset($_REQUEST['viewToken']) ? $_REQUEST['viewToken']:'';
			if($token && strlen($token) < 500){
				$pass = substr(md5('safe_'.get_client_ip().Model('SystemOption')->get('systemPassword')),0,15);
				$sessionSign = Mcrypt::decode($token,$pass);
				if($sessionSign){Session::sign($sessionSign);}
				
				$parse = kodIO::parse($this->in['path']);// 不允许以相对路径获取php扩展名文件;避免管理员被钓鱼攻击;
				$pathAdd  = kodIO::pathUrlClear(rawurldecode($this->in['add']));
				$distPath = kodIO::pathTrue($parse['path'].'/../'.$pathAdd);
				if(get_path_ext($distPath) == 'php'){show_json('not allow',false);}
				// header("Status: 404 Not Found [viewToken]");exit;
			}
			Hook::bind('PathDriverBase.fileOut.before',array($this,'fileOut'));
		}
	}
	
	public function fileOut($file,$fileSize,$filename,$ext){
		// write_log(REQUEST_METHOD.":".$filename.';'.$file,'test');
		if(!isset($this->in['replaceType']) || !$this->in['replaceType']){
			if($ext != 'js'){return;}
			$this->outputJs(IO::getContent($file));
			return;
		}
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
		$self = $this;$contentOld = $content;
		$content = preg_replace_callback("/importScripts\s*\(\s*([`'\"].*?[`'\"])\s*\)/",function($matchs) use($self){
			$char = substr($matchs[1],0,1);$url = substr($matchs[1],1,strlen($matchs[1])-2);
			return 'importScripts('.$char.$self->urlFilter($url).$char.')';
		},$content);
		$content = preg_replace_callback("/\s+from\s+([`'\"].*?['\"`])/",function($matchs) use($self){
			$char = substr($matchs[1],0,1);$url = substr($matchs[1],1,strlen($matchs[1])-2);
			return ' from '.$char.$self->urlFilter($url).$char;
		},$content);
		$content = preg_replace_callback("/import\s+(['\"`].*?['\"`])/",function($matchs) use($self){
			$char = substr($matchs[1],0,1);$url = substr($matchs[1],1,strlen($matchs[1])-2);
			return 'import '.$char.$self->urlFilter($url).$char;
		},$content);
		
		// await import( `./Sidebar.Geometry.${ geometry.type }.js` ); 该情况兼容;
		$content = preg_replace_callback("/import\s*\(\s*(['\"`].*?['\"`])\s*\)/",function($matchs) use($self){
			$char = substr($matchs[1],0,1);$url = substr($matchs[1],1,strlen($matchs[1])-2);
			return 'import('.$char.$self->urlFilter($url).$char.')';
		},$content);
		// var_dump($contentOld,$content);exit;
		$this->outputJs($content);
	}
	private function outputJs($content){
		// window.location处理; 统一替换为_location_;解决跨域问题;
		$locationHas = "(hash|host|hostname|href|orgin|pathname|port|protocol|reload|replace|search)";
		$content = preg_replace("/(^|[^\w_.])window\.location($|[^\w_])/","$1window._location_$2",$content);
		$content = preg_replace("/(^|[^\w_.])location\.".$locationHas."($|[^\w_])/","$1_location_.$2$3",$content);
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
		if(substr($url,0,5) == 'http:' || substr($url,0,6) == 'https:'){return $url;} // 外部链接;
		$url = kodIO::pathUrlClear($url);
		$path = rawurldecode($_GET['path']);// $this->in['path'], 外链分享时可能被替换;
		// 采用相对路径重新计算; 确保多个位置import 最后引用路径一致; 
		// 路径有变化时js多个地方import同一个文件,但url路径不一致,时会导致重复执行;
		$addNew  = kodIO::pathTrue(kodIO::pathUrlClear($this->in['add']).'/../'.$url);
		
		// 路径中不替换部分; 兼容js中url字符串带`${v}`变量情况;
		$addNew  = str_replace(array('%24','%20','%7B','%7D'),array('$',' ','{','}'),rawurlencode($addNew)); 
		$shareID = isset($this->in['shareID']) ? '&shareID='.$this->in['shareID']:'';
		$param   = '&viewToken='.$this->in['viewToken'].$shareID.'&replaceType='.$this->in['replaceType'];
		$url = APP_HOST.'index.php?'.str_replace('.','/',ACTION); // chrome xhr跨域options目录预检处理;需要带上index.php
		return $url.$param.'&path='.rawurlencode($path).'&add='.$addNew;
	}
	private function output($content){
		header('HTTP/1.1 200 OK');
		header('Content-Encoding: none');
		header('Content-Length:'.strlen($content));
		echo $content;exit;
	}
}