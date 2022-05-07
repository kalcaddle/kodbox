<?php

/*
 * @link http://kodcloud.com/
 * @author warlee | e-mail:kodcloud@qq.com
 * @copyright warlee 2014.(Shanghai)Co.,Ltd
 * @license http://kodcloud.com/tools/license/license.txt
 */

class userBind extends Controller {
	const BIND_META_INFO = 'Info';
	const BIND_META_UNIONID = 'Unionid';

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
		$type = $data['type'];

		// 检查图片验证码
		$checkCode = Input::get('checkCode', 'require', '');
		Action('user.setting')->checkImgCode($checkCode);

		// 1.1 判断邮箱是否已绑定-自己
		$userInfo = Session::get("kodUser");
		if ($userInfo[$data['type']] == $data['input']) {
			show_json(LNG('common.' . $type) . LNG('user.binded'), false);
		}
		// 1.2 判断邮箱是否已绑定-他人
		if ($res = Model('User')->userSearch(array($type => $data['input']), 'name,nickName')) {
			$typeTit = $type . ($type == 'phone' ? 'Number' : '');
			show_json(LNG('common.' . $typeTit) . LNG('common.error'), false);
			// $name = $res['nickName'] ? $res['nickName'] : $res['name'];
			// show_json(LNG('common.' . $type) . LNG('user.bindOthers') . "[{$name}]", false);
		}
		$data['source'] = 'bind';
		Action('user.setting')->checkMsgFreq($data);	// 消息发送频率检查

		// 2 发送邮件/短信
		$func = $type == 'email' ? 'sendEmail' : 'sendSms';
		$res = $this->$func($data['input'], $type.'_'.$data['source']);
		if (!$res['code']) {
			show_json(LNG('user.sendFail') . ': ' . $res['data'], false);
		}

		// 3. 存储验证码
		$param = array(
			'type'	=> 'setting',
			'input' => $data['input']
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
	public function sendEmail($input, $action) {
		$systemName = Model('SystemOption')->get('systemName');
		$data = array(
			'type'		=> 'email',
			'input'		=> $input,
			'action'	=> $action,
			'config'	=> array(
				'address'	=> $input,
				'subject'	=> "[{$systemName}]" . LNG('user.emailVerify'),
				'content'	=> array(
					'type'	=> 'code', 
					'data'	=> array('code' => rand_string(6))
				),
				'signature'	=> $systemName,
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
		// 获取secret
		if (!$secret = Model('SystemOption')->get('systemSecret')) {
			// 本地没有,先去kodapi请求获取secret。获取secret的请求，参数secret以Kodid代替
			$secret = $post['type'] != 'secret' ? $this->apiSecret() : $kodid;
		}
		ksort($post);
		$tmp = array();
		$post = stripslashes_deep($post);
		foreach ($post as $key => $value) {
			$tmp[] = $key . '=' . $value;
		}
		$md5 = md5(sha1(implode('&', $tmp) . $secret));
		return strtoupper($md5); //生成签名
	}

	/**
	 * 向api请求secret
	 * @return type
	 */
	private function apiSecret() {
		$res = $this->apiRequest('secret');
		if (!$res['code']) {
			$msg = 'Api secret error' . (!empty($res['data']) ? ': ' . $res['data'] : '');
			show_json($msg, false);
		}
		Model('SystemOption')->set('systemSecret', $res['data']);
		return $res['data'];
	}

}
