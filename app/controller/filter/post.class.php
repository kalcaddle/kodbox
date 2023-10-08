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
		$theMod 	= strtolower(MOD);
		$theST 		= strtolower(ST);
		$theACT 	= strtolower(ACT);
		$theAction 	= strtolower(ACTION);
		$GLOBALS['config']['jsonpAllow'] = false; //全局禁用jsonp
		if($theMod == 'plugin'){
			$GLOBALS['config']['jsonpAllow'] = true;
			return; //插件内部自行处理;
		}

		$allowGetArr = array(
			'explorer.fileview'	=> 'index',
			'explorer.history'	=> 'fileOut',
			'explorer.index'	=> 'fileOut,fileDownload,fileOutBy,fileDownloadRemove',
			'explorer.share'	=> 'file,fileOut,fileDownload,zipDownload,fileDownloadRemove',
			'admin.setting'		=> 'get,server',
			'admin.repair'		=> '*',

			'install.index'	 	=> '*',
			'user.index' 		=> 'index,autoLogin,loginSubmit,logout',//accessTokenGet logout
			'user.view'	 		=> '*',
			'user.sso'			=> '*',
			'test'				=> '*',
			'sitemap'			=> '*',
		);
		$allowGet  = false;
		$ST_MOD = $theMod.'.'.$theST;
		if(isset($allowGetArr[$ST_MOD])){
			$methods = strtolower($allowGetArr[$ST_MOD]);
			$methodArr = explode(',',$methods);
			if($methods == '*' || in_array($theACT,$methodArr)){
				$allowGet = true;
			}
		}
		if(isset($allowGetArr[MOD])){$allowGet = true;}

		//必须使用POST的请求:统一检测csrfToken;
		if($allowGet) return;
		
		// 无需登录的接口不处理;
		$authNotNeedLogin = $this->config['authNotNeedLogin'];
		foreach ($authNotNeedLogin as &$val) {
			$val = strtolower($val);
		};unset($val);
		if(in_array($theAction,$authNotNeedLogin)) return;
		foreach ($authNotNeedLogin as $value) {
			$item = explode('.',$value); //MOD,ST,ACT
			if( count($item) == 2 && 
				$item[0] === $theMod && $item[1] === '*'){
				$allowGet = true;break;
			}
			if( count($item) == 3 && 
				$item[0] === $theMod && $item[1] === $theST  &&$item[2] === '*'){
				$allowGet = true;break;
			}
		}
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
		if($this->in['CSRF_TOKEN'] != Cookie::get('CSRF_TOKEN')){
			return show_json('CSRF_TOKEN error!',false);
		}
	}
}