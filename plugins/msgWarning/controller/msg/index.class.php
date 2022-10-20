<?php 
/**
 * 发送通知
 */
class msgWarningMsgIndex extends Controller {
	public function __construct() {
		parent::__construct();
		$this->pluginName = 'msgWarningPlugin';
    }

	/**
	 * 发送消息
	 * @param [type] $msg
	 * @param [type] $target	userid[,,,]
	 * @return void
	 */
	public function send($msg, $target) {
		$config = Action($this->pluginName)->getConfig();
		$typeList = explode(',', $config['sendType']);

		$result = array('code' => false, 'data' => array());
		foreach ($typeList as $type) {
			$result['data'][$type] = array('code' => false, 'data' => array());
			$data = &$result['data'][$type];

			$content = $this->getContent($msg, $type);
			switch ($type) {
				case 'email':
					// 获取用户列表，依次发送
					$where = array('userID' => array('in', $target));
					$users = Model('User')->where($where)->select();
					foreach ($users as $user) {
						$userID = $user['userID'];
						$name	= !empty($user['nickName']) ? $user['nickName'] : $user['name'];
						$value	= $user[$type];
						if (empty($value)) {
							$data['data']['error'][$name] = LNG('common.'.$type) . LNG('msgWarning.main.msgEmpty');
							continue;
						}
						// 检测邮箱地址
						if (!Input::check($value, $type)) {
							$data['data']['error'][$name] = "{$value} " . LNG('msgWarning.main.msgFmtErr');
							continue;
						}

						// 发送消息
						$user = array('name' => $name, $type => $value);
						$func = 'by' . ucfirst($type);
						$rest = $this->$func($user, $content);
						$name = $name.'|'.$value;	// 张三|zhangsan@163.com
						// 有一个发送成功，即为成功
						if ($rest['code']) {
							$data['code'] = true;
							$data['data']['success'][$name] = 1;
						} else {
							$data['data']['error'][$name] = $rest['data'] ? $rest['data'] : 0;
						}
					}
					break;
				case 'weChat':
				case 'dingTalk':
					$data = $this->byThird($target, $content, $type);
					break;
				default:break;
			}
		}
		// 有一类发送成功，即为成功
		foreach($result['data'] as $item) {
			if ($item['code']) {
				$result['code'] = true;
				break;
			}
		}
		return $result;
	}

	/**
	 * 通过第三方（钉钉、企业微信）发送通知
	 * @param [type] $user
	 * @param [type] $content	['','']
	 * @param [type] $type
	 * @return void
	 */
	public function byThird($user, $content, $type){
		$systemName = Model('SystemOption')->get('systemName');
		$title = "[{$systemName}]".LNG('msgWarning.main.ntcTitle');
		$content = array_merge(array('**'.$title.'**'), $content);
		$data = array(
			'msg'		=> array(
				'type'		=> 'markdown',	// text、markdown
				'title'		=> $title,		// 企业微信用不上
				'content'	=> implode("\n\n", $content)	// 一个\n无效
			),
			'target'	=> array(
				'user'	=> implode(',', $user)
			),
			'type'		=> $type
		);
		return Action('msgGatewayPlugin')->send($data);
		// $res = ActionCallHook('msgGatewayPlugin.send', $data);
	}

	/**
	 * 通过邮件发送通知
	 * @param [type] $user
	 * @param [type] $content	['','']
	 * @return void
	 */
	public function byEmail($user, $content){
        $systemName = Model('SystemOption')->get('systemName');
		$data = array(
			'type'			=> 'email',
			'input'			=> $user['email'],  // 邮箱地址
			'action'		=> 'email_msg_warning',
			'config'		=> array(
				'address'	=> $user['email'],
				'subject'	=> "[{$systemName}]".LNG('msgWarning.main.ntcTitle'),
				'content'	=> array(
					'type'  => 'notice', 
					'data'  => array(
						'user' => $user['name'],
						'text' => $content	// 数组
					)
				),
				'system'	=> array(	// 系统信息
					'icon'	=> STATIC_PATH.'images/icon/fav.png',
					'name'	=> $systemName,
					'desc'	=> Model('SystemOption')->get('systemDesc')
				),
			)
		);
		return Action('user.msg')->email($data);	// ->send()
    }

	/**
	 * 消息内容
	 * @param [type] $msg
	 * @param [type] $type
	 * @return void
	 */
	public function getContent($msg, $type){
		// 账号立即设置链接
		if ($type != 'email' && !empty($msg['user'])) {
			$msg['user'][count($msg['user'])-1] = '['.LNG('msgWarning.main.setNow').']('.APP_HOST.'#setting/user/account)';
		}
		// 获取消息列表
		$content = array();
		foreach ($msg as $key => $value) {
			if ($type == 'email' && !empty($content) && !empty($value)) {
				$content[] = '<hr style="border:none;border-top:1px solid #eee;">';
			}
			foreach ($value as $val) {
				// if ($type != 'email' && $key == 'raid') {
				// 	$val = '`'.$val.'`';
				// }
				$content[] = $val;
			}
		}
		return $content;
	}
}