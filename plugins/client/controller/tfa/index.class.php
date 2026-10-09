<?php 
class clientTfaIndex extends Controller {
    public $pluginName;
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

    // 登录成功后（尚未更新登录状态），获取验证方式
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

        // 获取多重验证方式
		$tfaInfo = $this->getTfaInfo($user);
        if (!$tfaInfo['tfaOpen']) return;

        $tfaKey = md5('tfa-'.$user['userID'].time().rand_string(6));
        Cache::set('tfaSign_'.$tfaKey, $user, 600);
        $tfaInfo['sign'] = $tfaKey;
        show_json($tfaInfo);
    }

    // 入口方法
    public function index(){
        // 验证器请求事件
        $type = Input::get('type','require');
        if ($type == 'totp') {
            $this->totpAct()->index();
        }
        // 默认请求事件：发送、验证验证码
        $check  = array('tfaCode','tfaVerify');
        $func   = Input::get('action','in',null,$check);

        $tfaKey = Input::get('sign','require');
        $user   = Cache::get('tfaSign_'.$tfaKey);
        if (!$user) show_json(LNG('client.tfa.userLgErr'), false, 10011);
        if ($func == 'tfaCode') {
            $this->tfaCode($user);
        } else {
            $this->tfaVerify($user);
        }
    }

    /**
     * 获取用户多重验证信息
     * @param array $user
     */
    public function getTfaInfo($user){
        $tfaOpen = Model('SystemOption')->get('tfaOpen');   // 开启
        $tfaType = Model('SystemOption')->get('tfaType');   // 方式：phone,email,...
        $data = array(
            // 'userID'    => $user['userID'],
            'tfaOpen'   => intval($tfaOpen),
        );
        if (!$data['tfaOpen'] || !$tfaType) {
            $data['tfaOpen'] = 0;
            return $data;
        }
        $typeArr = explode(',',$tfaType);

        // 获取已绑定的发送类型
        $data = array();
        // 手机号/邮箱
        foreach (array('phone', 'email') as $type) {
            if (!in_array($type, $typeArr)) continue;
            $value = _get($user, $type, '');
            if (!$value) continue;
            if (Input::check($value, $type)) {
				$data[$type] = $this->getMscValue($value,$type);
			}
        }
        // google、微软动态验证码
        $type = 'totp';
        if (in_array($type, $typeArr)) {
            $value = $this->totpAct()->getBindInfo($user);    // secret
            if ($value) {
                $data[$type] = $this->getMscValue($value,$type);
            }
        }

        // 有绑定则只显示绑定项，否则显示全部（前端绑定）
        if (!empty($data)) {
            $typeArr = array_keys($data);
        }
        return array(
            'tfaOpen' => 1,
            'tfaType' => implode(',',$typeArr),
            'tfaList' => $data  // 已绑定项列表
        );
	}

    /**
     * 检查请求的验证方式：有绑定时仅允许已绑定项，无绑定则为配置全集
     * @param array  $user
     * @param string $type
     * @return array tfaInfo
     */
    private function checkTfaType($user, $type) {
        $tfaInfo = $this->getTfaInfo($user);
        if (empty($tfaInfo['tfaOpen'])) {
            show_json(LNG('common.invalidRequest'), false);
        }
        $allow = array_filter(explode(',', _get($tfaInfo, 'tfaType', '')));
        if (!$type || !in_array($type, $allow)) {
            show_json(LNG('common.invalidRequest'), false);
        }
        return $tfaInfo;
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
            $elen = strlen($value) - $epos;
        }
        $rpls = substr($value, $slen, strlen($value) - $slen - $elen);
        $rpld = str_repeat('*', strlen($rpls));
        return substr($value, 0, $slen) . $rpld . substr($value, -$elen);
    }

    /**
     * 发送验证码
     * @param array $user
     */
    public function tfaCode($user) {
        // 只允许该账号当前可用的验证方式（已绑定优先；无绑定时为配置全集）
        $this->checkTfaType($user, Input::get('type'));
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
		if($user[$type]){$input = $user[$type];}    // 有值（已绑定）时直接将*值替换
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
	 * @param string $code
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
     * TOTP 失败次数检查（计数绑定在本次登录sign上），超过则使sign失效，需重新用密码登录
     * @param string $tfaKey
     */
    private function totpFailCheck($tfaKey) {
        if (!$tfaKey) return;
        // 无需判断过期时间，Cache缓存已有设置
        $sess = Cache::get('tfaFail_'.$tfaKey);
        if ($sess && _get($sess, 'cnt', 0) >= 10) {
            Cache::remove('tfaFail_'.$tfaKey);
            Cache::remove('tfaSign_'.$tfaKey);
            show_json(LNG('user.codeErrorTooMany'), false);
        }
    }

    /**
     * TOTP 失败次数累加
     * @param string $tfaKey
     */
    private function totpFailAdd($tfaKey) {
        if (!$tfaKey) return;
        $sess = Cache::get('tfaFail_'.$tfaKey);
        $cnt  = $sess ? intval(_get($sess, 'cnt', 0)) : 0;
        Cache::set('tfaFail_'.$tfaKey, array('cnt' => $cnt + 1), 600);
    }

    /**
     * 提交验证码
     * @param array $user
     */
    public function tfaVerify($user) {
        // 验证码验证
        $type    = Input::get('type');
        // 只接受该账号当前允许的验证方式（已绑定优先；无绑定时为配置全集）
        $tfaInfo = $this->checkTfaType($user, $type);
        $hasBind = !empty($tfaInfo['tfaList']);   // 账号是否已绑定任一因子
        $tfaKey  = Input::get('sign');

        if ($type == 'totp') { // 验证器单独处理
            $input = Input::get('input', 'require');
            $code  = Input::get('code', 'require');
            // 失败次数过多则使本次登录流程失效
            $this->totpFailCheck($tfaKey);

            // 获取绑定信息
            $secret = $this->totpAct()->getBindInfo($user);
            if ($secret) {
                // 已绑定：只验证已绑定密钥，不使用请求提交的 input 覆盖
                if (!$this->totpAct()->verifyCode($secret, $code)) {
                    $this->totpFailAdd($tfaKey);
                    show_json(LNG('user.codeError'), false);
                }
            } else {
                // 未绑定：仅允许零因子（无任何绑定）账号在登录时完成首次绑定
                if ($hasBind) {
                    show_json(LNG('common.invalidRequest'), false);
                }
                if (!$this->totpAct()->verifyCode($input, $code)) {
                    $this->totpFailAdd($tfaKey);
                    show_json(LNG('user.codeError'), false);
                }
                $this->totpAct()->setBindInfo($user, $input);
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
        // 删除用户缓存
        if ($tfaKey) {
            Cache::remove('tfaFail_'.$tfaKey);
            Cache::remove('tfaSign_'.$tfaKey);
        }
        // 更新登录状态
        $user['tfaVerified'] = true;
        Action("user.index")->loginSuccessUpdate($user);
        show_json(LNG('common.loginSuccess'));
    }

    public function totpAct(){
        return Action($this->pluginName . '.tfa.totp');
    }

}