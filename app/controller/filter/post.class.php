<?php 
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/

/**
 * method过滤;
 * 
 * csrf防护:（外部js或内部链接构造越权接口请求)
 * 1. refer白名单;
 * 2. 一律post请求; get允许的控制器白名单; 插件处理;
 * 3. hash校验;(UA不为app和pc客户端);
 */
class filterPost extends Controller{
	function __construct(){
		parent::__construct();
	}

	// 一律post请求; get请求白名单; 插件处理;
	public function check(){
		if( Model('SystemOption')->get('csrfProtect') != '1') return;
		$ua = strtolower($_SERVER ['HTTP_USER_AGENT']);
		if( strstr($ua,'kodbox') || 
			strstr($ua,'okhttp') ||
			strstr($ua,'kodcloud')
		){return;}

		// if(GLOBAL_DEBUG) return;
		$GLOBALS['config']['jsonpAllow'] = false; //全局禁用jsonp
		if(strtolower(MOD) == 'plugin'){
			$GLOBALS['config']['jsonpAllow'] = true;
			return; //插件内部自行处理;
		}

		$allowGetArr = array(
			'explorer.fileview'	=> 'index',
			'explorer.history'	=> 'fileOut',
			'explorer.index'	=> 'fileOut,fileDownload,fileOutBy,fileDownloadRemove',
			'explorer.share'	=> 'file,fileOut,fileDownload,zipDownload',
			
			'install.index'	 	=> '*',
			'user.index' 		=> 'index,loginSubmit,logout',//accessTokenGet logout
			'user.view'	 		=> '*',
			'user.sso'			=> '*',
			'test.debug'		=> '*',
			'test.language'		=> '*',
		);
		$allowGet  = false;
		$ST_MOD = strtolower(MOD.'.'.ST);
		if(isset($allowGetArr[$ST_MOD])){
			$methods = strtolower($allowGetArr[$ST_MOD]);
			$methodArr = explode(',',$methods);
			if($methods == '*' || in_array(strtolower(ACT),$methodArr)){
				$allowGet = true;
			}
		}

		//必须使用POST的请求:统一检测csrfToken;
		if($allowGet) return;
		
		$this->checkCsrfToken();
		if(REQUEST_METHOD != 'POST'){
			// csrf校验后, post不再是必须的;
			// show_json('REQUEST_METHOD must be POST!',false);
		}
	}
	
	// csrfToken检测; 允许UA为APP,PC客户端的情况;
	private function checkCsrfToken(){
		if(isset($_REQUEST['accessToken'])) return;
		if( isset($this->in['CSRF_TOKEN']) && $this->in['CSRF_TOKEN'] != Cookie::get('CSRF_TOKEN')){
			return show_json('CSRF_TOKEN error!',false);
		}
	}
}