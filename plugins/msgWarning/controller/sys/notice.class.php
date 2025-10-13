<?php 
/**
 * 发送通知
 * 短信、邮件添加到队列，第三方直接发送
 */
class msgWarningSysNotice extends Controller {
	protected $pluginName;
	protected $evntInfo;
	public function __construct() {
		parent::__construct();
		$this->pluginName = 'msgWarningPlugin';
		// TODO 暂不支持短信通知——需要申请模板
    }

	/**
	 * 按事件发送通知
	 * @param [type] $evntInfo
	 * @param [type] $msg
	 * @return void
	 */
	public function send($evntInfo) {
		// 通知方式
		$methods = _get($evntInfo, 'notice.method', '');
		$methods = explode(',', $methods);
		if (empty($methods)) return;

		// 通知目标
		// $target = _get($evntInfo, 'notice.target', array()); // 原始值
		$target = _get($evntInfo, 'target', array());
		if (empty($target)) return;

		// 通知内容
		$message = _get($evntInfo, 'message', '');
		if (empty($message)) return;

		// 通知日志相关数据
		$logs = array(
			'event'		=> $evntInfo['event'],
			'title'		=> $evntInfo['title'],
			'userID'	=> 0,
			'method'	=> '',
			'target'	=> '',
		);
		$this->evntInfo = $evntInfo;
		foreach ($methods as $method) {
			switch ($method) {
				case 'email':
				case 'sms':
					$alias = array('email' => 'email', 'sms' => 'phone');
					$check = $alias[$method];
					// 获取用户列表
					$where = array(
						'userID' => array('in', $target),
						$check	 => array('<>',''),	// sms/email分别查询，无需用or
					);
					$users = Model('User')->where($where)->field('userID,name,nickName,email,phone')->select();
					if (empty($users)) continue;

					// 发送消息
					$logs['method'] = $method;
					foreach ($users as $user) {
						$name	= !empty($user['nickName']) ? $user['nickName'] : $user['name'];
						$value	= $user[$check];
						if (empty($value)) continue;
						// if (!Input::check($value, $check)) continue;	// TODO 国外手机号检查不通过，暂不处理——实际发送时也会检查，此处无需处理
						$logs['userID'] = $user['userID'];
						$logs['target'] = $value;

						// 发送消息
						$user = array('name' => $name, $check => $value);
						$func = 'by' . ucfirst($method);	// bySms、byEmail
						$this->$func($user, $message, $logs);
					}
					break;
				case 'weixin':
				case 'dding':
					$logs['method'] = $method;
					$logs['target'] = '';
					$this->byThird($target, $message, $logs);
					break;
			}
		}
		TaskQueue::addSubmit();
	}

	/**
	 * 通过第三方（钉钉、企业微信）发送通知
	 * @param [type] $user
	 * @param [type] $content	['','']
	 * @param [type] $logs
	 * @return void
	 */
	public function byThird($user, $content, $logs){
		// 获取有效发送用户；无效的写失败日志
		$userDef = $user;
		$this->filterThirdUsers($user);
		$userDiff = array_diff($userDef, $user);
		if (!empty($userDiff)) {
			$logs['status'] = 0;
			$logs['desc'] = LNG('msgWarning.main.invalidUser');
			$this->addUsersLog($userDiff, $logs);
		}
		if (empty($user)) return;

		// 发送消息
		$typeArr = array(
			'weixin' => 'weChat',
			'dding'	 => 'dingTalk'
		);
		$title = $logs['title'];
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
			'type'		=> $typeArr[$logs['method']]
		);
		$rest = Action('msgGatewayPlugin')->send($data);

		// 记录日志
		$logs['status'] = $rest['code'] ? 1 : 0;
		$logs['desc'] = _get($rest, 'data', '');
		$this->addUsersLog($user, $logs);
	}
	// 钉钉/企微 过滤非绑定用户——前面已经筛选过一次，这里没必要关联主表（user）查询
	private function filterThirdUsers(&$user) {
		$where = array(
			'userID' => array('in', $user),
			'key'	 => 'uid'
		);
		$data = Model('user_meta')->where($where)->field('userID,value')->select();
		$data = array_to_keyvalue($data, '', 'userID');
		$user = array_intersect($user, $data);
	}
	private function addUsersLog($user, $logs) {
		foreach ($user as $userID) {
			$logs['userID'] = $userID;
			$this->addLog($logs);
		}
	}

	/**
	 * 通过邮件发送通知
	 * @param [type] $user
	 * @param [type] $content	['','']
	 * @return void
	 */
	public function byEmail($user, $content, $logs){
		$title = $logs['title'];
		$content = array_merge(array($title), $content);
        $systemName = Model('SystemOption')->get('systemName');
		$data = array(
			'type'			=> 'email',
			'input'			=> $user['email'],  // 邮箱地址
			'action'		=> 'email_sys_notice',
			'config'		=> array(
				'address'	=> $user['email'],
				'subject'	=> "[{$systemName}]".$title,
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
		return $this->addTaskQueue($user, $data, $logs);
    }

	/**
	 * 通过短信发送通知——暂不支持
	 * @param [type] $user
	 * @param [type] $content	[msg]
	 * @return void
	 */
	public function bySms($user, $content, $logs) {
		// content为变量名（模板中固定），对应内容长度不能超过35个字符
		$content = array('content' => msubstr($content[0], 0, 35));
        $data = array(
			'type'			=> 'sms',
			'input'			=> $user['phone'],  // 手机号码
			'action'		=> 'phone_sys_notice',
			'config'		=> array(
				'content'	=> array(
					'type'	=> 'notice',		// code/notice
					'data'	=> $content,		// 变量不能超过35个字符
					'code'	=> 'xxxxxx',		// TODO 模板ID，待完善
				),
			)
		);
		return $this->addTaskQueue($user, $data, $logs);
    }


	/**
	 * 旧消息发送添加到任务队列
	 * @return void
	 */
	public function oldTaskQueue(){
		$key = $this->pluginName.'.msgQueue';
		$cache = Cache::get($key);
		if (!$cache) return;

		$call = $this->pluginName.'.sys.notice.byQueue';
		foreach ($cache as $i => $args) {
			$rest = TaskQueue::add($call, $args);
			if (!$rest) break;	// 添加失败直接终止
			unset($cache[$i]);
		}
		$cache = array_filter($cache);
		Cache::set($key, $cache, 3600*24);
	}

	/**
	 * 消息发送添加到任务队列
	 * @param [type] $data	消息发送参数
	 * @return void
	 */
	public function addTaskQueue($user, $data, $logs){
		$call = $this->pluginName.'.sys.notice.byQueue';
		$args = array($user, $data, $logs);
		$rest = TaskQueue::add($call, $args);
		if ($rest) return;
		// 添加失败，存入缓存，下次任务开始时读取然后继续添加
		$key = $this->pluginName.'.msgQueue';
		$cache = Cache::get($key);
		if (!$cache) $cache = array();
		$cache[] = $args;
		Cache::set($key, $cache, 3600*24);
	}

	/**
	 * 通过任务队列发送sms、email消息
	 * @param [type] $user	[name=>'',phone/email=>'']
	 * @param [type] $data	消息参数
	 * @param [type] $logs	日志参数
	 * @return void
	 */
	public function byQueue($user, $data, $logs){
		// 发送消息
		$func = $data['type'];	// sms、email，不直接使用send，避免再次检测格式
		$rest = Action('user.msg')->$func($data);

		// 写入日志
		$data = array(
			'status' => $rest['code'] ? 1 : 0,
			'desc'	 => _get($rest, 'data', ''),
		);
		$data = array_merge($logs, $data);
		$this->addLog($data);
		return;
	}

	/**
	 * 写入通知日志
	 * @param [type] $data
	 * @return void
	 */
	public function addLog($data) {
	    Action($this->pluginName)->loadLib('logs')->add($data);
	}

}
