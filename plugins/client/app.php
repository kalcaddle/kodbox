<?php

class clientPlugin extends PluginBase{
	function __construct(){
		parent::__construct();
	}
	public function regist(){
		$this->hookRegist(array(
			'user.commonJs.insert'	=> 'clientPlugin.echoJs'
		));
	}
	
	private function needPost(){
		if(REQUEST_METHOD != 'POST'){exit;}
	}
	/**
	 * 生成二维码
	 * 有效期: 10分钟; 超过时间不再可用;
	 * 使用次数: 3次;  超过则不再可用;
	 * 每个人一个业务只保留一个,重新生成之前的不再可用;
	 * 
	 * 扫码登录web端需要确认;
	 */
	public function qrcodeToken(){
		$this->needPost();
		$systemPassword = Model('SystemOption')->get('systemPassword');
		$currentKey = rand_string(8);
		Session::set('pluginsClientQrcode',array('count'=>0,'time'=>time(),'key'=>$currentKey));
		$token = Mcrypt::encode(Session::sign(),$systemPassword.$currentKey);
		show_json($currentKey.$token,true);
	}
	private function qrcodeParse($token){
		$codeTimeout = 5*60;		// 二维码有效期; 默认5分钟
		$timeCanUse  = 1;			// 二维码可用次数;

		$currentKey = substr($token,0,8);
		$token 		= substr($token,8);
		$systemPassword = Model('SystemOption')->get('systemPassword');
		$sessionSign = Mcrypt::decode($token,$systemPassword.$currentKey);
		if(!$sessionSign){return array('code'=>0,'data'=>LNG('client.app.scanError').'(sign)');}
		
		$result = array('code'=>true,'data'=>$sessionSign);
		$sessionData = $this->sessionValue($sessionSign);
		$scanData    = $sessionData['pluginsClientQrcode'];
		if(!$scanData || !is_array($scanData) ){return $this->qrError('disable');}
		if($scanData['key'] != $currentKey){return $this->qrError('replace');}
		if(time() - $scanData['time'] > $codeTimeout){return $this->qrError('timeout');}
		if($scanData['count'] >= $timeCanUse){$result = $this->qrError('count');}
		
		$scanData['count'] += 1;
		$sessionData['pluginsClientQrcode'] = $scanData;
		$this->sessionValue($sessionSign,$sessionData);
		return $result;
	}
	private function qrError($error){
		return array('code'=>0,'data'=>LNG('client.app.scanError').'('.$error.')');
	}
	public function checkPass(){
		$userInfo = Session::get('kodUser');
		if(!$userInfo || !isset($userInfo['userID'])){show_json(LNG('user.loginFirst'),false);}
		
		$password = Mcrypt::decode($this->in['pass'],$this->in['sign']);
		if(!$password){show_json(LNG("ERROR_USER_PASSWORD_ERROR"),false);}
		$user = Model("User")->userLoginCheck($userInfo['name'],$password);
		if(!is_array($user) || !isset($user['userID'])){show_json(LNG("ERROR_USER_PASSWORD_ERROR"),false);}
		show_json(LNG("explorer.success"),true);
	}

	
	/**
	 * App扫码登录web端; 扫码跳转到url;
	 * 1. 打开url: HOST/#?loginWeb=xxx; 未登录则先登录; 已登录则弹出确认框 [生成验证key,并附带到页面]
	 * 2. 弹出确认框; [确认; 取消]
	 */
	public function loginWeb(){
		$this->needPost();
		$userInfo = Session::get('kodUser');
		if(!$userInfo || !isset($userInfo['userID'])){show_json(LNG('user.loginFirst'),false);}

		$targetData  = $this->qrcodeParse($this->in['token']);
		$sessionSign = $targetData['data'];
		if(!$targetData['code']){show_json($targetData['data'],false);}

		// 更新目标用户的session数据;
		$sessionData = $this->sessionValue($sessionSign);
		if($this->in['status'] != '1'){ // 取消登录;
			$sessionData['_kodUserLoginApp'] = array('status'=>'cancel');
			$this->sessionValue($sessionSign,$sessionData);
			show_json(LNG('explorer.success'),true);
		}
		if(!is_array($sessionData['kodUser'])){
			$sessionData['_kodUserLoginApp'] = $userInfo;
			$this->sessionValue($sessionSign,$sessionData);
		}
		show_json(LNG('explorer.success'),true);
	}
	
	// APP扫码登录web端,自动检查是否自动登录;
	public function check(){
		$userInfo = Session::get('kodUser');// 同sessionid 在其他地方登录
		if($userInfo && isset($userInfo['userID'])){
			show_json(LNG('explorer.success'),true);
		}
		
		$loginUser = Session::get('_kodUserLoginApp');
		$code = $loginUser && isset($loginUser['userID']);
		Session::remove('_kodUserLoginApp');
		if($code){
			Action("user.index")->loginSuccessUpdate($loginUser);
		}
		show_json($loginUser,$code);
	}
	
	
	// ===============================================================
	// App扫码登录App; 扫码后构造请求; 支持浏览器扫描登录;
	public function loginApp(){
		$this->needPost();
		$targetData  = $this->qrcodeParse($this->in['token']);
		$sessionSign = $targetData['data'];
		if(!$targetData['code']){
			show_json($targetData['data'],false);
		}
		
		// 更新目标用户的session数据;(切换账号)
		$sessionData = $this->sessionValue($sessionSign);
		$userInfo = $sessionData['kodUser'];
		if(!$userInfo){show_json(LNG('common.loginError').',userInfo error!',false);}
		
		$this->sessionValue($sessionSign,$sessionData);
		Action("user.index")->loginSuccessUpdate($userInfo);
		$accessToken = Action("user.index")->accessToken();
		show_json($accessToken,true,$userInfo['userID']);
	}
	
	
	private function sessionValue($sign,$data=false){
		CacheLock::lock($sign);
		if($data === false){
			$result = unserialize(Session::$handle->get($sign));
		}else{
			$result = Session::$handle->set($sign,serialize($data),Session::$sessionTime);
		}
		CacheLock::unlock($sign);
		return $result;
	}
}