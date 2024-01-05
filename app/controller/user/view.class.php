<?php

class userView extends Controller{
	public function __construct(){
		parent::__construct();
	}
	public function options(){
		$user = Session::get("kodUser");
		if( isset($user['metaInfo'])) unset($user['metaInfo']);
		if( isset($this->config['settings']['language']) ){
			$this->config['settingAll']['language'] = array();
		}
		$options = array(
			"kod"	=> array(
				'systemOS'		=> '-',
				'phpVersion'	=> '-',
				'appApi'		=> appHostGet(),
				'APP_HOST'		=> APP_HOST,
				'APP_HOST_LINK' => $this->config['APP_HOST_LINK'],
				'ENV_DEV'		=> GLOBAL_DEBUG,
				'staticPath'	=> STATIC_PATH,
				'version'		=> KOD_VERSION,
				'build'			=> KOD_VERSION_BUILD,
				'channel'		=> INSTALL_CHANNEL,
			),
			"user"	=> array(
				'userID'		=> USER_ID ? USER_ID:'',
				'myhome'    	=> defined('MY_HOME') ? MY_HOME : '',
				'desktop'   	=> defined('MY_DESKTOP') ? MY_DESKTOP : '',
				'isRoot'		=> KodUser::isRoot() ? 1:0,//固定数值,true/false Android会异常;
				'info'			=> $user,
				'role'			=> Action('user.authRole')->userRoleAuth(),
				'config'		=> $this->config['settingDefault'],
				'editorConfig'	=> $this->config['editorDefault'],
				'isRootAllowIO'	=> $this->config["ADMIN_ALLOW_IO"], //后端处理
				'isRootAllowAll'	=> KodUser::isRoot() ? $this->config["ADMIN_ALLOW_ALL_ACTION"] : 1,
			),
			"system" => array(
				'settings'		=> $this->config['settings'],
				'all'			=> $this->config['settingAll'],
				'options'		=> array(),
			),
			"io"	=> KodIO::typeList(),
			"lang"	=> I18n::getType(),
		);
		if($user){//空间大小信息;
			$userInfoFull = Model('User')->getInfoFull(USER_ID);
			$options['user']['targetSpace'] = Action('explorer.auth')->space('user',USER_ID);
			$options['user']['role'] = $options['user']['role']['roleList'];
			$options['user']['config'] = array_merge($this->config['settingDefault'],Model('UserOption')->get());
			$options['user']['editorConfig'] = array_merge($this->config['editorDefault'],Model('UserOption')->get(false,'editor'));
			$options['user']['isOpenSafeSpace'] = _get($userInfoFull,'metaInfo.pathSafeFolder',0) ? true:false;
		}
		
		// 外链分享,界面部分配置采用分享者的配置;
		if(!$user && isset($this->in['shareID']) && $this->in['shareID']){
			$share = Model('Share')->getInfoByHash($this->in['shareID']);
			$share = $share ? $share : array('userID'=>'_');
			$userOptions = Model('user_option')->where(array('userID'=>$share['userID'],'type'=>''))->select();
			$userOptions = array_to_keyvalue($userOptions,'key','value');
			$userOptions = array_field_key($userOptions,array('theme','themeStyle'));
			$options['user']['config'] = array_merge($options['user']['config'],$userOptions);
		}
		
		if(KodUser::isRoot()){
			$options['kod']['WEB_ROOT']   = WEB_ROOT;
			$options['kod']['BASIC_PATH'] = BASIC_PATH;
			$options['kod']['systemOS']   = $this->config['systemOS'];
			$options['kod']['phpVersion'] = PHP_VERSION;
		}
		unset($options['system']['settings']['sysTempPath']);
		unset($options['system']['settings']['sysTempFiles']);
		
		//为https时自适应为https; 兼容https无法请求http的情况;
		if(strstr(APP_HOST,'https://')){
			$api = $options['system']['settings']['kodApiServer'];
			$options['system']['settings']['kodApiServer'] = str_replace('http://','https://',$api);
		}
		$options = Hook::filter('user.view.options.before',$options);
		$options = Hook::filter('user.view.options.after',$options); 
		$options = $this->parseMenu($options,!!$user);
		$this->companyInfo($options);
		
		if($this->in['full'] == '1'){
			$options['_lang'] = array(
				"list"	=> I18n::getAll(),
				"lang"	=> I18n::getType(),
			);
		}
		show_json($options);
	}
	
	private function companyInfo(&$options){
		$op = &$options['system']['options'];$_op = $this->config['settingSystemDefault']; 
		// 兼容解决部分用户误操作设置logo包含身份token问题;
		if(stristr($op['systemLogo'],'accessToken')){$op['systemLogo'] = $_op['systemLogo'];}
		if(stristr($op['systemLogoMenu'],'accessToken')){$op['systemLogoMenu'] = $_op['systemLogoMenu'];}

		$groupSelf  = array_to_keyvalue(Session::get("kodUser.groupInfo"),'','groupID');
		$groupCompany = $GLOBALS['config']['settings']['groupCompany'];
		if(!$groupCompany || !$groupSelf || KodUser::isRoot()) return false;

		$groupAllowShow = Model('Group')->groupShowRoot($groupSelf[0],$groupCompany);
		$groupInfo = Model('Group')->getInfo($groupAllowShow[0]);
		$options['kod']['companyInfo'] = array('name'=>$groupInfo['name'],'logoType'=>'text','logoText'=>$groupInfo['name']);
	}
	
	// 检测是否支持二进制上传;(部分apache服务器,上传会被拦截报403错误;自动处理为表单上传;)
	public function uploadBindaryCheck(){
		$input = file_get_contents("php://input");
		$result= trim($input) == '[uploadCheck]' ? '[ok]':'[error]';
		echo $result;
	}
	
	/**
	 * 根据权限设置筛选菜单;
	 */
	private function parseMenu($options,$user=false){
		$menus  = &$options['system']['options']['menu'];
		$result = array();
		if ($user) {	// 未登录时不显示菜单信息
			foreach ($menus as $item) {
				if(!isset($item['pluginAuth'])){
					$allow = true;
				}else{
					$allow = ActionCall("user.authPlugin.checkAuthValue",$item['pluginAuth']);
				}
				if($allow){$result[] = $item;}
			}
		}
		$menus = $result;
		return $options;
	}
	
	public function lang(){
		if($this->in['_t']) return;
		$result = array(
			"list"	=> I18n::getAll(),
			"lang"	=> I18n::getType(),
		);
		show_json($result);
	}
	
	public function plugins(){
		ob_get_clean();
		header("Content-Type: application/javascript; charset=utf-8");
		echo 'var kodReady=[];';
		Hook::trigger('user.commonJs.insert');
		$useTime = sprintf('%.4f',mtime() - TIME_FLOAT);
		echo "\n/* time={$useTime} */\n";
	}
	
	// 计划任务触发;
	public function call(){
		header('Content-Type: application/javascript');
		http_close();
		Action('explorer.index')->clearCache();
		Action('explorer.attachment')->clearCache();
		Action("admin.repair")->sourceNameInit();
		
		AutoTask::start();		// 后台计划任务自动启动;已启动则不再处理;
		TaskQueue::runThread(); // 后台任务队列,允许多个进程处理后台任务队列(最多允许3个,队列没有任务则退出);
		Cache::clearTimeout();
		$this->autoSetUploadMax();
	}
	
	// 自动优化配置上传文件分片大小(如果获取到php限制及nginx限制;取限制的最小值,如果大于20M则分片设置为20M)
	private function autoSetUploadMax(){
		$chunkSize   = $GLOBALS['config']['settings']['upload']['chunkSize'];
		if($chunkSize != 0.5*1024*1024){return;}
		if(Model('SystemOption')->get('autoSetUploadMax_set')){return;}

		Model('SystemOption')->set('autoSetUploadMax_set','1');
		$postMaxPhp   = get_post_max();
		$postMaxNginx = get_nginx_post_max();
		if($postMaxPhp && $postMaxNginx){
			$sizeMin = min($postMaxPhp,$postMaxNginx,20*1024*1024) / (1024*1024);// MB;
			if($sizeMin > 0.3){Model('SystemOption')->set('chunkSize',$sizeMin);}
		}
	}
	
	public function parseUrl($link){
		if(!$link || !trim($link)) return '';
		if(substr($link,0,1) ==  '/'){return HOST.substr($link,1);}
		if(substr($link,0,2) == './'){return APP_HOST.substr($link,2);}
		if(substr($link,0,strlen(HOST)) == HOST){return $link;}
		
		// 域名切换情况处理(用户头像等信息url缓存情况处理)
		if(strstr($link,'explorer/share/file&hash')){
			$hostInfo = parse_url(APP_HOST);
			$linkInfo = parse_url($link);
			$linkQuery= parse_url_query($link);
			if($hostInfo['path'] != $linkInfo['path']){return $link;} // 站点不同则不再继续自适应;
			if(!isset($linkQuery['hash']) || !$linkQuery['hash']){return $link;}
			
			$pathTrue = Mcrypt::decode($linkQuery['hash'],Model('SystemOption')->get('systemPassword'));
			if(!$pathTrue){return $link;}
			
			$linkPort = isset($linkInfo['port']) && $linkInfo['port'] != '80' ? ':'.$linkInfo['port'] : '';
			$linkHost = _get($linkInfo,'scheme','http').'://'.$linkInfo['host'].$linkPort.'/';
			return str_replace($linkHost,HOST,$link);
		}
		return $link;
	}
	public function imageRequest(){
		Action('user.viewImage')->request('start');
	}
	
	
	// 验证码-登录、注册、找回密码、个人设置
	public function checkCode() {
		$captcha = new MyCaptcha(4);
		Session::set('checkCode', $captcha->getString());
	}

	// 发送（消息）验证码-注册、找回密码
	public function sendCode(){
		Action('user.regist')->sendMsgCode();
	}
	public function qrcode() {
		$url = $this->in['url'];
		if (function_exists('imagecolorallocate')) {
			ob_get_clean();
			QRcode::png($url,false,QR_ECLEVEL_L,5,2);
		} else {
			// https://api.pwmqr.com/qrcode/create/?url=
			// https://demo.kodcloud.com/?user/view/qrcode&url=
			// https://api.qrserver.com/v1/create-qr-code/?data=
			header('location: https://api.pwmqr.com/qrcode/create/?url='.rawurlencode($url));
		}
	}
	public function taskAction(){
		$result = ActionCall('admin.task.taskActionRun',0);
		show_json($result['result'],true);
	}

	// 插件说明
	public function pluginDesc(){
		$path = PLUGIN_DIR.KodIO::clear($this->in['app']).'/';
		$lang = I18n::getType();
		$file = $path.'readme.md';
		if(file_exists($path.'readme_'.$lang.'.md')){
			$file = $path.'readme_'.$lang.'.md';
		}
		$content = '';
		if(file_exists($file)){
			$content = file_get_contents($file);
		}
		if(!preg_match("/^[0-9a-zA-Z_.]+$/",$_GET['callback'])){
			die("calllback error!");
		}
		echo $_GET['callback'].'("'.base64_encode($content).'")';
	}
	
	//chrome安装: 必须https;serviceWorker引入处理;manifest配置; [manifest.json配置目录同sw.js引入];
	public function manifest(){
		$json   = file_get_contents(LIB_DIR.'template/user/manifest.json');
		$name   = stristr(I18n::getType(),'zh') ? '可道云':'kodcloud';
		$opt    = $GLOBALS['config']['settingSystemDefault'];
		$name   = isset($opt['systemNameApp']) ? $opt['systemNameApp']: $name;
		$static = STATIC_PATH == './static/' ? APP_HOST.'static/':STATIC_PATH;
		$assign = array(
			"{{name}}"		=> $name,
			"{{appDesc}}"	=> LNG('common.copyright.name'),
			"{{static}}"	=> $static,
		);
		$json = str_replace(array_keys($assign),array_values($assign),$json);
		if(isset($opt['systemIcon'])){
			$jsonArr = json_decode($json,true);
			$jsonArr['icons'] = array(array("src"=>$opt['systemIcon'],"sizes"=>"512x512","type"=>"image/png"));
			if(isset($opt['systemThemeColor'])){$jsonArr['theme_color'] = $opt['systemThemeColor'];}
			$json = json_encode_force($jsonArr);
			$json = str_replace('\\','',$json);
		}
		
		header("Content-Type: application/javascript; charset=utf-8");
		echo $json;
	}
	public function manifestJS(){
		header("Content-Type: application/javascript; charset=utf-8");
		echo file_get_contents(BASIC_PATH.'static/app/vender/sw.js');
	}
}