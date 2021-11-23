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
				'systemOS'		=> $this->config['systemOS'],
				'phpVersion'	=> PHP_VERSION,
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
				'userID'		=> defined('USER_ID') ? USER_ID : '',
				'myhome'    	=> defined('MY_HOME') ? MY_HOME : '',
				'desktop'   	=> defined('MY_DESKTOP') ? MY_DESKTOP : '',
				'isRoot'		=> _get($GLOBALS,'isRoot',0),
				'info'			=> $user,
				'role'			=> Action('user.authRole')->userRoleAuth(),
				'config'		=> $this->config['settingDefault'],
				'editorConfig'	=> $this->config['editorDefault'],
				'isRootAllow'	=> $this->config["ADMIN_ALLOW_IO"],
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
			$options['user']['targetSpace'] = Action('explorer.auth')->space('user',USER_ID);
			$options['user']['role'] = $options['user']['role']['roleList'];
			$options['user']['config'] = array_merge($this->config['settingDefault'],Model('UserOption')->get());
			$options['user']['editorConfig'] = array_merge($this->config['editorDefault'],Model('UserOption')->get(false,'editor'));
		}
		if(_get($GLOBALS,'isRoot')){
			$options['kod']['WEB_ROOT']   = WEB_ROOT;
			$options['kod']['BASIC_PATH'] = BASIC_PATH;
		}
		
		//为https时自适应为https; 兼容https无法请求http的情况;
		if(strstr(APP_HOST,'https://')){
			$api = $options['system']['settings']['kodApiServer'];
			$options['system']['settings']['kodApiServer'] = str_replace('http://','https://',$api);
		}
		$options = Hook::filter('user.view.options.before',$options);
		$options = Hook::filter('user.view.options.after',$options); 
		$options = $this->parseMenu($options);
		$this->companyInfo($options);
		show_json($options);
	}
	
	private function companyInfo(&$options){
		$groupSelf  = array_to_keyvalue(Session::get("kodUser.groupInfo"),'','groupID');
		$groupCompany = $GLOBALS['config']['settings']['groupCompany'];
		if(!$groupCompany || !$groupSelf || $GLOBALS['isRoot']) return false;

		$groupAllowShow = Model('Group')->groupShowRoot($groupSelf[0],$groupCompany);
		$groupInfo = Model('Group')->getInfo($groupAllowShow[0]);
		$options['kod']['companyInfo'] = array('name'=>$groupInfo['name'],'logoType'=>'text','logoText'=>$groupInfo['name']);
	}

	/**
	 * 根据权限设置筛选菜单;
	 */
	private function parseMenu($options){
		$menus  = &$options['system']['options']['menu'];
		$result = array();
		foreach ($menus as $item) {
			if(!isset($item['pluginAuth'])){
				$allow = true;
			}else{
				$allow = ActionCall("user.authPlugin.checkAuthValue",$item['pluginAuth']);
			}
			if($allow){
				$result[] = $item;
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
		AutoTask::start();
		Cache::clearTimeout();
	}
	
	public function parseUrl($link){
		if(!trim($link)) return '';
		if(substr($link,0,4) == 'http') return $link;
		if(substr($link,0,2) == './') {
			$link = substr($link,2);
		}
		return APP_HOST . $link;
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
			QRcode::png($url,false,QR_ECLEVEL_L,7,2);
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
		echo $_GET['callback'].'("'.base64_encode($content).'")';
	}
	
	//chrome安装: 必须https;serviceWorker引入处理;manifest配置; [manifest.json配置目录同sw.js引入];
	public function manifest(){
		$json   = file_get_contents(LIB_DIR.'template/user/manifest.json');
		$name   = stristr(I18n::getType(),'zh') ? '可道云':'kodcloud';
		$static = STATIC_PATH == './static/' ? APP_HOST.'static/':STATIC_PATH;
		$assign = array(
			"{{name}}"		=> $name,
			"{{appDesc}}"	=> LNG('common.copyright.name'),
			"{{static}}"	=> $static,
		);
		$json = str_replace(array_keys($assign),array_values($assign),$json);
		header("Content-Type: application/javascript; charset=utf-8");
		echo $json;
	}
	public function manifestJS(){
		header("Content-Type: application/javascript; charset=utf-8");
		echo file_get_contents(BASIC_PATH.'static/app/vender/sw.js');
	}
}