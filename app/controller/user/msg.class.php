<?php 

/**
 * 发送消息：短信、邮件
 */
class userMsg extends Controller {
	public $user;
	public function __construct() {
        parent::__construct();
    }

    /**
     * 发送短信、邮件
     * @param [type] $data => array(
     * type         发送方式：sms、email
     * input        接收地址：手机号码、邮箱地址
     * action       系统发送api请求事件：phone_bind
     * // 非必需参数
     * emailType    邮件发送方式：系统、自定义
     * config       邮箱配置参数
     * config.add   邮箱配置参数追加-自定义服务器
     * ) 
     * @return void
     */
    public function send($data){
        $type = $data['type'];
        $input = $data['input'];
        $check = array('sms' => 'phone', 'email' => 'email');
        if(!isset($check[$type])) {
            return array('code' => false, 'data' => LNG('common.invalidParam'));
        }
        if (!Input::check($input, $check[$type])) {
            return array('code' => false, 'data' => LNG('common.invalidFormat'));
        }
        return $this->$type($data);
    }

    /**
     * 发送短信
     * @param [type] $data
     * @return void
     */
    public function sms($data){
        $data = array(
			'type'		 => $data['action'],
			'input'		 => $data['input'], // 邮箱or手机
            'language'	 => i18n::getType(),
            'config'     => isset($data['config']) ? $data['config'] : array()
		);
        $res = Hook::trigger('send.sms.before', $data);
        if ($res) return $res;  // [data,code] || false
		return Action('user.bind')->apiRequest('sms', $data);
    }

    /**
     * 发送邮件
     * @param [type] $data
     * @return void
     */
    public function email($data){
        if (isset($data['emailType'])) {
			$type = $data['emailType'];
		} else {
			$type = Model('SystemOption')->get('emailType');
        }
        // 自定义发送
		if ((int) $type) {
			return $this->emailByCustom($data);
		}
		// 系统默认发送
        $data = array(
			'type'      => $data['action'],
			'input'     => $data['input'], // 邮箱or手机
            'language'  => i18n::getType(),
            'config'    => isset($data['config']) ? $data['config'] : ''
        );
        if(!$data['config']) unset($data['config']);
		return Action('user.bind')->apiRequest('email', $data);
    }

    /**
     * 发送邮件-自定义(服务器)
     * @param [type] $data
     * @return void
     */
    public function emailByCustom($data){
        $init = array(
            'address'   => '',  // 收件人
            // 'cc'        => '',  // 抄送 a;b;c
            // 'bcc'       => '',  // 暗抄送 a;b;c
            'subject'   => '',  // 主题
            'content'   => '',  // 内容
            'signature' => '',  // 发送者名称——和邮件内容里的签名不同
            'html'      => 1,   // 是否为html
        );
        foreach($init as $key => &$value) {
            if(isset($data['config'][$key])) $value = $data['config'][$key];
        };
        // 发件服务器信息
        if(isset($data['config']['server'])) {
            $init = array_merge($init, $data['config']['server']);
        }
        // 发送者名称
        $signature = _get($data, 'config.system.name');
        if (!$signature) $signature = Model('SystemOption')->get('systemName');
        $init['signature'] = $signature;
        // 邮件内容，自定义内容为字符串；根据模板获取为数组：array('type'=>'code','data'=>array('code' => 123))
        if(is_array($init['content'])) {
            $init['content'] = $this->emailContent($data);
        }

        // 邮件发送
		$mail = new Mailer();
        $res = $mail->send($init);
        $type = _get($data, 'config.content.type');
        if($res['code'] && $type == 'code') {
            $res['data'] = _get($data, 'config.content.data.code');
        }
        return $res;
    }

    /**
     * 获取邮件内容
     * @param [type] $type
     * @param [type] $data
     * @return void
     */
    public function emailContent($data){
        $system = _get($data, 'config.system', array());
        if (!$system['icon']) $system['icon'] = 'https://api.kodcloud.com/static/images/icon/fav.png';
        if (!$system['name']) $system['name'] = Model('SystemOption')->get('systemName');
        if (!$system['desc']) $system['desc'] = Model('SystemOption')->get('systemDesc');

        $addr = _get($data, 'config.address');
        $type = _get($data, 'config.content.type');
        $data = _get($data, 'config.content.data');
        $user = _get($data, 'user');
        if (!$user) $user = $addr;

        switch($type) {
            case 'code':
                $data = array(
                    'type'  => 'code',
                    'dear'  => sprintf(LNG('admin.emailDear'), $user),
                    'text'  => LNG('admin.emailCodeText'),
                    'code'  => $data['code'],
                    'date'  => date('Y-m-d'),
                    'system'=> $system,
                );
                break;
            case 'notice':
                $data = array(
                    'type'  => 'notice',
                    'dear'  => sprintf(LNG('admin.emailDear'), $user),
                    'text'  => is_array($data['text']) ? $data['text'] : array($data['text']),  // 正文
                    'date'  => date('Y-m-d'),
                    'system'=> $system,
                );
                break;
            default:
                return '';
                break;
        }
        ob_end_clean();
		ob_start();
		extract(array('data' => $data));
		require(TEMPLATE . '/user/email.html');
		$html = ob_get_contents();
		ob_end_clean();
		return $html;
    }
}