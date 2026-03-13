<?php 
include_once(dirname(dirname(dirname(__FILE__))).'/lib/GoogleAuthenticator.class.php');
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
		$options['system']['options']['tfaType'] = $tfaType ? $tfaType : '';
		return $options;
	}

    // 登录成功后（尚未更新登录状态）
    public function loginAfter($user) {
        // 避免死循环
        $userInfo = Session::get('kodUser');
        if($userInfo && isset($userInfo['userID']) && $userInfo['userID'] == $user['userID']) return;
        if(!isset($this->in['withTfa']) || $this->in['withTfa']) return;

		$tfaInfo = $this->getTfaInfo($user);
        if (!$tfaInfo['tfaOpen']) return;

        $key = md5($this->in['name'].$this->in['password'].'_'.$user['userID']);
        Cache::set($key, $user);
        show_json($tfaInfo);
    }

    // 入口方法
    public function index(){
        $check  = array('tfaCode','tfaVerify', 'initGoogle', 'bindGoogle', 'getBindInfo', 'unbindGoogle');
        $func   = Input::get('action','in',null,$check);

        // Setup flow (Logged in user)
        if (in_array($func, array('initGoogle', 'bindGoogle', 'getBindInfo', 'unbindGoogle'))) {
            $user = Session::get('kodUser');
            if (!$user) show_json(LNG('user.loginFirst'), false);
            if ($func == 'initGoogle') $this->initGoogle($user);
            if ($func == 'bindGoogle') $this->bindGoogle($user);
            if ($func == 'getBindInfo') $this->getBindInfo($user);
            if ($func == 'unbindGoogle') $this->unbindGoogle($user);
            return;
        }

        $tfaIn  = Input::get('tfaIn','json');
        $key    = md5($tfaIn['name'].$tfaIn['password'].'_'.$this->in['userID']);
        $user   = Cache::get($key);
        if (!$user) show_json(LNG('client.tfa.userLgErr'), false, 10011);
        if ($func == 'tfaCode') {
            $this->tfaCode($user);
        } else {
            $this->tfaVerify($user);
        }
    }

    /**
     * Initialize Google Authenticator Setup
     */
    public function initGoogle($user) {
        $tfaType = Model('SystemOption')->get('tfaType');
        if (!in_array('google', explode(',', $tfaType))) {
            show_json(LNG('common.invalidRequest'), false);
        }

        $ga = new GoogleAuthenticator();
        $secret = $ga->createSecret();
        $name = $user['name'] . '@' . strip_tags(Model('SystemOption')->get('systemName'));
        
        $issuer = strip_tags(Model('SystemOption')->get('systemName'));
        $otpauth = 'otpauth://totp/' . $name . '?secret=' . $secret . '&issuer=' . urlencode($issuer);
        $qrCodeUrl = APP_HOST . 'index.php?user/view/qrcode&url=' . urlencode($otpauth);
        show_json(array('secret' => $secret, 'qrCode' => $qrCodeUrl));
    }

    /**
     * Bind Google Authenticator
     */
    public function bindGoogle($user) {
        $secret = Input::get('secret', 'require');
        $code = Input::get('code', 'require');
        
        $ga = new GoogleAuthenticator();
        if ($ga->verifyCode($secret, $code, 1)) {
             Model('User')->metaSet($user['userID'], 'tfa_google_secret', $secret);
             Action('user.index')->refreshUser($user['userID']);
             show_json(LNG('client.tfa.bindSuccess'));
        }
        show_json(LNG('user.codeError'), false);
    }

    public function getBindInfo($user) {
        $secret = Model('User')->metaGet($user['userID'], 'tfa_google_secret');
        show_json(array('isBind' => $secret ? 1 : 0));
    }

    public function unbindGoogle($user) {
        Model('User')->metaSet($user['userID'], 'tfa_google_secret', null);
        Action('user.index')->refreshUser($user['userID']);
        show_json(LNG('user.unbindSuccess'));
    }

    /**
     * 获取用户多重验证信息
     */
    public function getTfaInfo($user){
        $tfaOpen = Model('SystemOption')->get('tfaOpen');
        $tfaType = Model('SystemOption')->get('tfaType');
        $data = array(
            'userID'    => $user['userID'],
            'tfaOpen'   => intval($tfaOpen),
        );
        if (!$data['tfaOpen'] || !$tfaType) {
            $data['tfaOpen'] = 0;
            return $data;
        }
        
        $systemTypes = explode(',',$tfaType);
        $userTypes = array();
        
        // Check Phone
        if(in_array('phone', $systemTypes) && !empty($user['phone'])){
            $userTypes[] = 'phone';
        }
        // Check Email
        if(in_array('email', $systemTypes) && !empty($user['email'])){
            $userTypes[] = 'email';
        }
        // Check Google
        $googleSecret = Model('User')->metaGet($user['userID'], 'tfa_google_secret');
        if(in_array('google', $systemTypes) && $googleSecret){
            $userTypes[] = 'google';
        }

        if(empty($userTypes)){
             $data['tfaOpen'] = 0;
             return $data;
        }

        // Default type logic
        $type = $userTypes[0];
        // Prefer Google if available
        if(in_array('google', $userTypes)) $type = 'google';

        $input = '';
        if($type == 'phone') $input = $user['phone'];
        if($type == 'email') $input = $user['email'];

        $tfaInfo = array(
            'tfaType'   => implode(',',$userTypes),
            'type'      => $type,
            'input'     => $this->getMscValue($input,$type)
        );
        return array_merge($data, $tfaInfo);
	}

    // 获取手机/邮箱（加*）
    private function getMscValue($value, $type){
        if ($type == 'google') return 'Google Authenticator';
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
        $type = Input::get('type');
        if ($type == 'google') {
            show_json(LNG('client.tfa.scanCode'), true);
        }
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
		$type   = $data['type'];
        $input	= $data['input'];
        if($data['userID'] != $user['userID']) {
            show_json(LNG('client.tfa.userLgErr'), false);
        }
		if($user[$type]){$input = $user[$type];}
        if(!Input::check($input,$type)) {
            show_json(LNG('client.tfa.sendInvalid'), false);
        }
		
		// 检测是否已被绑定;
		if(!$user[$type] && $input){
			$find = Model('User')->userSearch(array($type => $input));
			$err  = ($type == 'phone') ? LNG('ERROR_USER_EXIST_PHONE') : LNG('ERROR_USER_EXIST_EMAIL');
			if($find){show_json($err, false);}
		}
        return array(
            'type'      => $type,
            'input'     => $input,
            'source'    => $this->pluginName.'_tfa_login',
        );
    }

    // 发送验证码
    private function sendCode($data) {
        $type	= $data['type'];
		$input	= $data['input'];
		$source	= $data['source'];

        // 1.发送验证码
        Action('user.setting')->checkMsgFreq($data);    // 检查发送频率
        if ($type == 'email') {
			$res = Action('user.bind')->sendEmail($input, $type.'_'.$source);
		} else {
			$res = Action('user.bind')->sendSms($input, $type.'_'.$source);
		}
		if (!$res['code']) {
			show_json(LNG('user.sendFail') . ': ' . $res['data'], false);
		}
        Action('user.setting')->checkMsgFreq($data, true);

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
        // 验证码验证
        if (!Input::get('wiotTfa',null,0)) {
            $type = Input::get('type');
            if ($type == 'google') {
                $code = Input::get('code', 'require');
                $secret = Model('User')->metaGet($user['userID'], 'tfa_google_secret');
                if (!$secret) show_json(LNG('common.error'), false);
                
                $ga = new GoogleAuthenticator();
                if (!$ga->verifyCode($secret, $code, 1)) {
                    show_json(LNG('user.codeError'), false);
                }
            } else {
                $data = $this->checkCode($user);
                $code = Input::get('code', 'require');
                $this->checkMsgCode($code, $data);
                // 绑定联系方式
                if($user[$data['type']] != $data['input']) {
                    $update = array($data['type'] => $data['input']);
                    $res = Model('User')->userEdit($user['userID'], $update);
                    $user[$data['type']] = $data['input'];
                }
            }
        }
        // 删除用户缓存
        $this->in = Input::get('tfaIn','json');
        $key = md5($this->in['name'].$this->in['password'].'_'.$user['userID']);
        Cache::remove($key);
        // 更新登录状态
        $this->in['withTfa'] = true;
        Action("user.index")->loginSuccessUpdate($user);
        show_json(LNG('common.loginSuccess'));
    }

}