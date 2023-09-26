<?php 
class clientTfaIndex extends Controller {
	public function __construct() {
		parent::__construct();
		$this->pluginName = 'clientPlugin';
    }

    // 更新options
    public function options($options) {
		$tfaOpen = Model('SystemOption')->get('tfaOpen');
		$tfaType = Model('SystemOption')->get('tfaType');
		$options['system']['options']['tfaOpen'] = $tfaOpen == '1' ? 1 : 0;
		$options['system']['options']['tfaType'] = $tfaType ? $tfaType : 'email';
		return $options;
	}

    /**
     * 检查所有需登录的请求
     * 参考：user.authRole.autoCheck
     */
    public function autoCheck(){
        $user = Session::get('kodUser');
        if (!is_array($user)) return;
        // 排除无需登录请求
        $theMod 	= strtolower(MOD);
		$theST 		= strtolower(ST);
		$theAction 	= strtolower(ACTION);
		$authNotNeedLogin = $this->config['authNotNeedLogin'];
		foreach ($authNotNeedLogin as &$val) {
			$val = strtolower($val);
		};unset($val);
		if(in_array($theAction,$authNotNeedLogin)) return;
		foreach ($authNotNeedLogin as $value) {
			$item = explode('.',$value); //MOD,ST,ACT
			if( count($item) == 2 && 
				$item[0] === $theMod && $item[1] === '*'){
				return;
			}
			if( count($item) == 3 && 
				$item[0] === $theMod && $item[1] === $theST  &&$item[2] === '*'){
				return;
			}
		}
        // 判断是否已二次验证
        $info = $this->tfaInfo(false,true);
        if ($info['isRoot'] || $info['isTfa'] === 1) return;

        // 跳转到登录页——无法区分是跳转请求还是ajax提交，故只用做后端功能判断
        show_json(LNG('explorer.noPermissionAction').LNG('client.tfa.need2verify'),false);
        header('Location:'.$this->getReferLink());
        exit;
    }
    private function getReferLink(){
		$url = APP_HOST;
		if (!empty($this->in['link'])) {
			$link = $this->in['link'];
			if($this->in['callbackToken'] == '1'){
				$param = 'kodTokenApi='.$this->accessToken();
				$link .= strstr($link,'?') ? '&'.$param:'?'.$param;
			}
			$url .= '#user/login&link='.rawurlencode($link);
		}
        return $url;
	}

    // 入口方法
    public function index(){
        $check = array('tfaInfo','tfaCode','tfaVerify');
        $func = Input::get('action','in',null,$check);
        $user = Session::get('kodUser');
        if (!$user) show_json(LNG('client.tfa.loginErr'), false, 10011);
        $this->$func($user);
    }

    /**
     * 获取用户多重验证信息
     */
    public function tfaInfo($user=array(),$ret=false){
        if (!$user) $user = Session::get('kodUser');
        $key  = $this->pluginName.'_'.$user['userID'].'_isTfa'; // 不直接从meta中取，是避免有其他处覆盖用户信息
        $tfa  = Session::get($key);
        $info = array(
            'userID'    => $user['userID'],
            'isRoot'    => !!$GLOBALS['isRoot'],
            'isTfa'     => intval($tfa),
        );
        if (!$info['isRoot'] && !$info['isTfa']) {
            $info['tfaInfo'] = $this->getTfaInfo($user);
        }
		return $ret ? $info : show_json($info);
	}

    /**
     * tfa信息：tfa类型、发送信息
     * $user    ['email'=>'xx','phone'=>'xx]
     */
    private function getTfaInfo($user){
        $tfaType = Model('SystemOption')->get('tfaType');
		if(!$tfaType) $tfaType = 'email';

        // 优先使用手机
        $info = array(
            'phone' => _get($user, 'phone', ''),
            'email' => _get($user, 'email', ''),
        );
		$type = $input = '';
		$typeArr = explode(',',$tfaType);
		foreach ($typeArr as $tType) {
			if (Input::check($info[$tType], $tType)) {
				$type   = $tType;
				$input  = $info[$tType];
				break;
			}
		}
        return array(
            'tfaType'   => $tfaType,
            'type'      => $type,
            'input'     => $this->getMscValue($input,$type)
        );
    }

    // 获取手机/邮箱（加*）
    private function getMscValue($value, $type){
        if (!$value) return $value;
        $slen = 3; $elen = 2;
        if ($type == 'email') {
            $epos = strripos($value,'@');
            if ($epos <= 3) {
                $slenList = array(1=>0,2=>1,3=>2);
                $slen = $slenList[$epos];
            }
            $elen = strlen($value) - $epos - 1;
        }
        $rpls = substr($value, $slen, strlen($value) - $slen - $elen);
        $rpld = str_repeat('*', strlen($rpls));
        return substr($value, 0, $slen) . $rpld . substr($value, -$elen);
    }

    /**
     * 发送验证码
     */
    public function tfaCode($user) {
        $data = $this->checkCode($user);
        $this->sendCode($data);
    }

    private function checkCode($user) {
        $data = Input::getArray(array(
            'userID'	=> array('check' => 'int'),
			'type'	    => array('check' => 'require'),
			'input'	    => array('check' => 'require'),
			'default'   => array('default' => 0),
        ));
        if ($data['userID'] != $user['userID']) {
            show_json(LNG('client.tfa.userErr'), false);
        }
        if ($data['default'] == '1') {
            if (empty($user[$data['type']])) {
                show_json(LNG('client.tfa.sendEmpty'), false);
            }
            $data['input'] = $user[$data['type']];
        }
        if (!Input::check($data['input'], $data['input'])) {
            show_json(LNG('client.tfa.sendInvalid'), false);
        }
        return array(
            'type'      => $data['type'],
            'input'     => $data['input'],
            'source'    => $this->pluginName.'_tfa_login',
        );
    }

    // 发送验证码
    private function sendCode($data) {
        // 1.检查发送频率
        Action('user.setting')->checkMsgFreq($data);

        // 2.发送验证码
        $func = $data['type'] == 'email' ? 'sendEmail' : 'sendSms';
		$res = Action('user.bind')->$func($data['input'], "{$data['type']}_{$data['source']}");
		if (!$res['code']) {
			show_json(LNG('user.sendFail') . ': ' . $res['data'], false);
		}

		// 3.存储验证码
		$this->checkMsgCode($res['data'], $data, true);
		show_json(LNG('user.sendSuccess'), true);
    }

    /**
	 * 证码存储、验证
	 * @param [type] $code
	 * @param array $data
	 * @param boolean $set
	 * @return void
	 */
	private function checkMsgCode($code, $data = array(), $set = false) {
		$name = md5(implode('_', $data).'_msgcode');
		// 1. 存储
		if ($set) {
			$sess = array(
				'code'	 => $code,
				'cnt'	 => 0,
				'time'	 => time()
			);
			return Session::set($name, $sess);
		}
		// 2. 验证
		if (!$sess = Session::get($name)) {
			$msg = LNG('common.invalid') . LNG('user.code');
			show_json($msg, false);
		}
		// 超过20分钟
		if (($sess['time'] + 60 * 20) < time()) {
			Session::remove($name);
			show_json(LNG('user.codeExpired'), false);
		}
		// 错误次数过多，锁定一段时间——没有锁定，重新获取
		if ($sess['cnt'] >= 10) {
			Session::remove($name);
			show_json(LNG('user.codeErrorTooMany'), false);
		}
		if (strtolower($sess['code']) != strtolower($code)) {
			$sess['cnt'] ++;
			Session::set($name, $sess);
			show_json(LNG('user.codeError'), false);
		}
		Session::remove($name);
	}

    /**
     * 提交验证码
     */
    public function tfaVerify($user) {
        $data = $this->checkCode($user);
        $code = Input::get('code', 'require');

        // 验证码验证
        $this->checkMsgCode($code, $data);

        // 更新验证信息
        $key  = $this->pluginName.'_'.$user['userID'].'_isTfa';
        Session::set($key, 1);
        show_json(LNG('explorer.success'));
    }

}