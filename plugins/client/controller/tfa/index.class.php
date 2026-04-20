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
		$options['system']['options']['tfaType'] = $tfaType ? $tfaType : '';
		return $options;
	}

    // 是否为第三方认证登录
    public function isSsoAuth() {
        $cckey = 'tfa.ssologin.check';
        $cache = Cache::get($cckey);
        if ($cache !== false) return $cache;
        // user.index.loginsubmit.before
        $appList = array(
			'ldapAccess'	=> 'action',
			'oemBjtbc'		=> 'action',
			'oemCasicloud'	=> 'action',
			'oemXiaoKu'		=> 'login',
			'tongdaOa'		=> 'action',
		);
		$issso = 0;
		$model = Model('Plugin');
		foreach ($appList as $app => $act) {
			if (Action($app.'Plugin') && method_exists(Action($app.'Plugin'),$act)) {
				$plugin = $model->loadList($app);
				if ($plugin && $plugin['status'] == '1') {
					$issso = 1; break;
				}
			}
		}
        Cache::set($cckey, $issso);
        return $issso;
    }

    // 登录成功后（尚未更新登录状态）
    public function loginAfter($user) {
        $tfaOpen = Model('SystemOption')->get('tfaOpen');
        if ($tfaOpen != '1') return;
        // 仅限kod账号密码登录
        if (ACTION != 'user.index.loginSubmit') return;
        if ($this->isSsoAuth()) return;
        // 避免死循环
        $userInfo = Session::get('kodUser');
        if (_get($userInfo, 'userID') == $user['userID']) return;
        if ($user['tfaVerified']) {
            unset($user['tfaVerified']);
            return;
        }

		$tfaInfo = $this->getTfaInfo($user);
        if (!$tfaInfo['tfaOpen']) return;

        $tfaKey = md5('tfa-'.$user['userID'].time().rand_string(6));
        Cache::set($tfaKey, $user, 600);
        $tfaInfo['sign'] = $tfaKey;
        show_json($tfaInfo);
    }

    // 入口方法
    public function index(){
        $check  = array('tfaCode','tfaVerify');
        $func   = Input::get('action','in',null,$check);

        $tfaKey = Input::get('sign','require');
        $user   = Cache::get($tfaKey);
        if (!$user) show_json(LNG('client.tfa.userLgErr'), false, 10011);
        if ($func == 'tfaCode') {
            $this->tfaCode($user);
        } else {
            $this->tfaVerify($user);
        }
    }

    /**
     * 获取用户多重验证信息
     */
    public function getTfaInfo($user){
        $tfaOpen = Model('SystemOption')->get('tfaOpen');
        $tfaType = Model('SystemOption')->get('tfaType');
        $data = array(
            // 'userID'    => $user['userID'],
            'tfaOpen'   => intval($tfaOpen),
        );
        if (!$data['tfaOpen'] || !$tfaType) {
            $data['tfaOpen'] = 0;
            return $data;
        }
        // 发送类型，优先使用手机
		$type = $input = '';
		$typeArr = explode(',',$tfaType);
        $typeArr = array_intersect(array('phone','email'), $typeArr);
		foreach ($typeArr as $tType) {
            $value = _get($user, $tType, '');
            if (!$value) continue;
			if (Input::check($value, $tType)) {
				$type   = $tType;
				$input  = $value;
				break;
			}
		}
        $tfaInfo = array(
            'tfaType'   => implode(',',$typeArr),
            'type'      => $type,
            'input'     => $this->getMscValue($input,$type)
        );
        return array_merge($data, $tfaInfo);
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
            // 'userID'	=> array('check' => 'int'),
			'type'	    => array('check' => 'require'),
			'input'	    => array('check' => 'require'),
        ));
		$type   = $data['type'];
        $input	= $data['input'];
        // if($data['userID'] != $user['userID']) {
        //     show_json(LNG('client.tfa.userLgErr'), false);
        // }
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
        $data = $this->checkCode($user);
        $code = Input::get('code', 'require');
        $this->checkMsgCode($code, $data);
        // 绑定联系方式
        if($user[$data['type']] != $data['input']) {
            $update = array($data['type'] => $data['input']);
            $res = Model('User')->userEdit($user['userID'], $update);
            $user[$data['type']] = $data['input'];
        }
        // 删除用户缓存
        $tfaKey = Input::get('sign');
        if ($tfaKey) Cache::remove($tfaKey);
        // 更新登录状态
        $user['tfaVerified'] = true;
        Action("user.index")->loginSuccessUpdate($user);
        show_json(LNG('common.loginSuccess'));
    }

}