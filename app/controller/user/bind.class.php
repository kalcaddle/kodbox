<?php

/*
 * @link http://kodcloud.com/
 * @author warlee | e-mail:kodcloud@qq.com
 * @copyright warlee 2014.(Shanghai)Co.,Ltd
 * @license http://kodcloud.com/tools/license/license.txt
 */

class userBind extends Controller {
	public function __construct() {
		parent::__construct();
	}
	/**
	 * 发送信息(验证码)-短信、邮件	当前只有个人设置绑定使用,暂时只记为绑定
	 */
	public function sendMsg() {
		$data = Input::getArray(array(
			'type'	 => array('check' => 'in', 'param' => array('email', 'phone')),
			'input'	 => array('check' => 'require'),
		));
		$type	= $data['type'];
		$input	= $data['input'];
		$source = $data['source'] = 'bind';

		// 检查图片验证码
		$checkCode = Input::get('checkCode', 'require', '');
		Action('user.setting')->checkImgCode($checkCode);

		// 1.1 判断邮箱是否已绑定-自己
		$userInfo = Session::get("kodUser");
		if ($userInfo[$data['type']] == $input) {
			show_json(LNG('common.' . $type) . LNG('user.binded'), false);
		}
		// 1.2 判断邮箱是否已绑定-他人
		if ($res = Model('User')->userSearch(array($type => $input), 'name,nickName')) {
			$typeTit = $type . ($type == 'phone' ? 'Number' : '');
			$message = $type == 'phone' ? LNG('ERROR_USER_EXIST_PHONE') : LNG('ERROR_USER_EXIST_EMAIL');
			show_json($message.'.', false);
		}

		// 2 发送邮件/短信
		Action('user.setting')->checkMsgFreq($data);	// 消息发送频率检查
		if($type == 'email'){
			$res = $this->sendEmail($input, $type.'_'.$source);
		}else{
			$res = $this->sendSms($input, $type.'_'.$source);
		}
		if (!$res['code']) {
			show_json(LNG('user.sendFail') . ': ' . $res['data'], false);
		}
		Action('user.setting')->checkMsgFreq($data, true);

		// 3. 存储验证码
		$param = array(
			'type'	=> 'setting',
			'input' => $input
		);
		Action("user.setting")->checkMsgCode($type, $res['data'], $param, true);
		show_json(LNG('user.sendSuccess'), true);
	}

	/**
	 * 发送(验证码)邮件
	 * @param [type] $input
	 * @param [type] $action
	 * @return void
	 */
	public function sendEmail($input, $action,$title = '',$code = false) {
		$systemName = Model('SystemOption')->get('systemName');
		$user = Session::get('kodUser');
		$name = _get($user,'name','');
		$name = _get($user,'nickName',$name);// _get 连续获取,部分安全软件会误报;
		$desc = Model('SystemOption')->get('systemDesc');
		$code = $code ? $code : rand_string(6,1);
		if(!$name && isset($user['name'])){$name = $user['name'];}
		$data = array(
			'type'		=> 'email',
			'input'		=> $input,
			'action'	=> $action,
			'config'	=> array(
				'address'	=> $input,
				'subject'	=> "[".$systemName."]" . LNG('user.emailVerify').$title,
				'content'	=> array(
					'type'	=> 'code', 
					'data'	=> array('user' => $name,'code' => $code)
				),
				'system'	=> array(	// 系统信息
					'icon'	=> STATIC_PATH.'images/icon/fav.png',
					'name'	=> $systemName,
					'desc'	=> $desc
				),
			)
		);
		return Action('user.msg')->send($data);
	}

	/**
	 * 发送(验证码)短信
	 * @param [type] $input
	 * @param [type] $action
	 * @return void
	 */
	public function sendSms($input, $action) {
		$data = array(
			'type'		=> 'sms',
			'input'		=> $input,
			'action'	=> $action
		);
		return Action('user.msg')->send($data);
	}

	/**
	 * 请求Kodapi服务器
	 * @param type $type
	 * @param type $data
	 * @return type
	 */
	public function apiRequest($type, $data = array()) {
		$kodid = md5(BASIC_PATH . Model('SystemOption')->get('systemPassword'));
		if(is_array($data) && defined('INSTALL_CHANNEL')){$data['channel'] = INSTALL_CHANNEL;}
		$post = array(
			'type'		 => $type,
			'kodid'		 => $kodid,
			'timestamp'	 => time(),
			'data'		 => is_array($data) ? json_encode($data) : $data
		);
		$post['sign'] = $this->makeSign($kodid, $post);
		$url = $this->config['settings']['kodApiServer'] . 'plugin/platform/';
		$response = url_request($url, 'GET', $post);
		if ($response['status']) {
			$data = json_decode($response['data'], true);
			if (!$data) {	// 平台异常报错（show_tips）
				if ($response['data']) {
					preg_match('/<div id="msgbox">(.*?)<\/div>/s', $response['data'], $matches);
					if ($matches[1]) write_log('API request error: '.$matches[1], 'error');
				}
				return array('code' => false, 'data' => LNG('explorer.systemError'));
			}
			// secret有变更，和平台不一致
			if (!$data['code'] && isset($data['info']) && $data['info'] == '40003') {
				Model('SystemOption')->set('systemSecret', '');
			}
			return $data;
		}
		// Network error. Please check whether the server can access the external network.
		return array('code' => false, 'data' => 'network error.');
	}

	/**
	 * kodapi请求参数签名
	 * @param type $kodid
	 * @param type $post
	 * @return type
	 */
	public function makeSign($kodid, $post) {
		$secret = $this->getApiSecret($kodid, $post['type']);
		ksort($post);
		$tmp = array();
		$post = stripslashes_deep($post);
		foreach ($post as $key => $value) {
			$tmp[] = $key . '=' . $value;
		}
		$md5 = md5(sha1(implode('&', $tmp) . $secret));
		return strtoupper($md5); //生成签名
	}

	//获取api secret
	private function getApiSecret($kodid, $type) {
		$secret = Model('SystemOption')->get('systemSecret');
		if ($secret) return $secret;
		// 本身为获取secret请求时，secret以kodid代替
		if ($type == 'secret') return $kodid;

		// 从平台获取;需要站点认证; kodid变化重新获取(服务端重新生成)
		$initPass  = Model('SystemOption')->get('systemPassword');
		$res  = $this->apiRequest('secret',array('initPath'=>BASIC_PATH,'initPass'=>$initPass));
		if (!$res['code'] || !$res['data']) {
			$msg = !empty($res['data']) ? ': ' . $res['data'] : '';
			show_json('Api secret error. '.$msg, false);
		}
		$secret = addslashes($res['data']);
		Model('SystemOption')->set('systemSecret', $secret);
		return $secret;
	}

}
