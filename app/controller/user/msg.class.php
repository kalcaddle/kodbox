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
            // 'signature'	 => Model('SystemOption')->get('systemName')
		);
        Hook::trigger('send.sms.before', $data);
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
            // 'signature' => Model('SystemOption')->get('systemName'),
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
        }
        // 发件服务器信息
        if(isset($data['config']['server'])) {
            $init = array_merge($init, $data['config']['server']);
        }
        // 邮件内容，自定义内容为字符串；根据模板获取为数组：array('type'=>'code','data'=>array('code' => 123))
        if(is_array($init['content'])) {
            $type = $init['content']['type'];
            $data = $init['content']['data'];
            $init['content'] = $this->emailContent($type, $data);
        }
        // 邮件发送
		$mail = new Mailer();
        $res = $mail->send($init);
        if($res['code'] && isset($type) && $type == 'code') {
            $res['data'] = $data['code'];
        }
        return $res;
    }

    /**
     * 获取邮件内容
     * @param [type] $type
     * @param [type] $data
     * @return void
     */
    public function emailContent($type, $data){
        $systemName = Model('SystemOption')->get('systemName');
        $signature = !empty($data['signature']) ? $data['signature'] : $systemName;

        switch($type) {
            case 'code':
                $data = array(
                    'type'  => 'code',
                    'dear'  => LNG('admin.dearUser'),
                    'text'  => sprintf(LNG('admin.emailThxUse'), $systemName) . LNG('admin.emailVerifyCode'),
                    'code'  => $data['code'],
                    'tips'  => LNG('admin.emailVerifyInTime'),
                    'name'  => $signature,
                    'date'  => date('Y-m-d'),
                );
                break;
            case 'notice':
                $data = array(
                    'type'  => 'notice',
                    'dear'  => isset($data['dear']) ? $data['dear'] : LNG('admin.dearUser'),
                    'text'  => is_array($data['text']) ? $data['text'] : array($data['text']),  // 正文
                    'name'  => $signature,
                    'date'  => date('Y-m-d'),
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