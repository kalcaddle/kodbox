<?php 
/**
 * 验证器管理
 */
class clientTfaTotp extends Controller {
    public $pluginName;
	public function __construct() {
		parent::__construct();
		$this->pluginName = 'clientPlugin';
    }

	public function api(){
		include_once(Action($this->pluginName)->pluginPath.'lib/Authenticator.class.php');
		return new Authenticator();
	}

	/**
	 * 个人中心操作入口
	 * @return void
	 */
	public function index(){
		$check  = array('initInfo','bind','unbind','bindInfo');
		$func   = Input::get('action');
		if (!in_array($func, $check)) return;

		$funcWrite = array('bind','unbind');	// 绑定、解绑仅允许在个人中心进行（已登录）——登录绑定由 tfaVerify 完成
		if (in_array($func, $funcWrite)) {
			$user = Session::get('kodUser');
		} else {
			$tfaKey = Input::get('sign');	// 存在时为前端绑定，否则为个人中心
			$user   = $tfaKey ? Cache::get('tfaSign_'.$tfaKey) : Session::get('kodUser');
		}
		if (!$user || empty($user['userID'])) {
			show_json(LNG('client.tfa.userLgErr'), false, 10011);
		}
		if ($func == 'initInfo') $this->initInfo($user);
		if ($func == 'bind') $this->bind($user);
		if ($func == 'unbind') $this->unbind($user);
		if ($func == 'bindInfo') $this->bindInfo($user);
	}

	/**
	 * 初始化绑定信息
	 * @param array $user
	 * @return void
	 */
	public function initInfo($user){
		$tfaType = Model('SystemOption')->get('tfaType');
        if (!in_array('totp', explode(',', $tfaType))) {
            show_json(LNG('common.invalidRequest'), false);
        }
		$sysName = Model('SystemOption')->get('systemName');
		if (Model('SystemOption')->get('versionType') == 'A') {
			$sysName = stristr(I18n::getType(),'zh') ? '可道云' : 'kodbox';
		}
        $secret = $this->api()->createSecret();
		$issuer = strip_tags($sysName);                     // 发行者=公司名，作为标题
		$account = $user['name'] . '@' . strip_tags(get_url_domain(APP_HOST));  // 账号=用户名@站点地址
        $name = $issuer . ':' . $account;                   // LABEL格式: issuer:account
        $otpauth = 'otpauth://totp/' . urlencode($name) . '?secret=' . $secret . '&issuer=' . urlencode($issuer);
            // . '&image=' . urlencode($iconUrl);
        show_json(array('secret' => $secret, 'qrCodeUri' => $otpauth));
	}

	/**
	 * 绑定
	 * @param array $user
	 * @return void
	 */
	public function bind($user){
		$data = Input::getArray(array(
            // 'userID'	=> array('check' => 'int'),
			'type'	    => array('check' => 'require'),
			'input'	    => array('check' => 'require'),
			'code'	    => array('check' => 'require'),
        ));
		$secret = $data['input'];
        if (!$this->verifyCode($secret, $data['code'])) {
			show_json(LNG('user.codeError'), false);
		}
		$this->setBindInfo($user, $secret);
		Action('user.index')->refreshUser($user['userID']);
		show_json(LNG('explorer.success'));
	}

	/**
	 * 解绑
	 * @param array $user
	 * @return void
	 */
	public function unbind($user){
		$this->setBindInfo($user, null);
        Action('user.index')->refreshUser($user['userID']);
        show_json(LNG('explorer.success'));
	}

	/**
	 * 获取绑定信息
	 * @param array $user
	 * @return void
	 */
	public function bindInfo($user) {
        $secret = $this->getBindInfo($user);
        show_json(array('isBind' => $secret ? 1 : 0));
    }

	/**
	 * 检查验证码
	 * @param array $user
	 * @param string $code
	 * @return void
	 */
	public function checkCode($user, $code) {
		$secret = $this->getBindInfo($user);
        if (!$secret) show_json(LNG('client.tfa.bindInfoErr'), false);

		if (!$this->verifyCode($secret, $code)) {
			show_json(LNG('user.codeError'), false);
		}
	}

	// =================================================================================

	/**
	 * 获取绑定信息（secret）
	 * @param array $user
	 * @return string
	 */
	public function getBindInfo($user){
		return Model('User')->metaGet($user['userID'], 'tfa_totp_secret');
	}

	/**
	 * 设置绑定信息
	 * @param array $user
	 * @param string $secret
	 * @return int
	 */
	public function setBindInfo($user, $secret) {
		return Model('User')->metaSet($user['userID'], 'tfa_totp_secret', $secret);
	}

	/**
	 * 校验验证码
	 * @param string $secret
	 * @param string $code
	 * @return boolean
	 */
	public function verifyCode($secret, $code) {
		return $this->api()->verifyCode($secret, $code, 1);
	}

}