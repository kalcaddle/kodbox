<?php 
/**
 * 通知事件——运维管理类
 */
class msgWarningMsgOps extends Controller {
	public function __construct() {
		parent::__construct();
		$this->pluginName = 'msgWarningPlugin';
    }

	public function index($evntInfo) {
		$data = array();
		switch ($evntInfo['event']) {
			case 'opsUserAccErr':
				$data = $this->opsUserAccErr($evntInfo);
				break;
			default:
				# code...
				break;
		}
		return $data;
	}

	/**
	 * 账号安全风险，当前仅管理员未绑定邮箱，后续可扩展其他项
	 * @param [type] $evntInfo
	 * @return void
	 */
	public function opsUserAccErr($evntInfo) {
		// $user = Session::get('kodUser');	// 计划任务没有登录，仅isRoot有效
		if (!KodUser::isRoot()) return array();

		// 判断是否绑定了有效邮箱
		$user = Model('User')->getInfoSimple(1);
		if (Input::check($user['email'], 'email')) return array();

		return array(LNG('msgWarning.ops.admEmlErr'));
	}

}
