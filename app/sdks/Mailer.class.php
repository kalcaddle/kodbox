<?php 
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer {
	function __construct() {
		$rootPath = dirname(__FILE__);
		require_once $rootPath . '/Mailer/src/Exception.class.php';
		require_once $rootPath . '/Mailer/src/PHPMailer.class.php';
		require_once $rootPath . '/Mailer/src/POP3.class.php';
		require_once $rootPath . '/Mailer/src/SMTP.class.php';
	}

	/**
	 *  'address' => '',	// 收件人
		'replay' => '',		// 回复人
		'cc' => '',			// 抄送
		'bcc' => '',		// 秘密抄送
		'subject' => '',	// 主题
		'content' => '',	// 内容
		'html' => ''		// 是否为html
	 * @param array $data
	 */
	public function send($data){
		$config = $this->getConfig($data);
		if(isset($config['code']) && !$config['code']) return $config;

		$mail = new PHPMailer(true);
		try {
			if(i18n::getType() == 'zh-CN') {
				$mail->setLanguage('zh_cn', __DIR__ . '/Mailer/language/');
			}
		    //Server settings
		    // $mail->SMTPDebug = SMTP::DEBUG_SERVER;				// Enable verbose debug output
		    $mail->isSMTP();										// Send using SMTP
		    $mail->Host			= $config['host'];					// Set the SMTP server to send through
		    $mail->SMTPAuth		= true;								// Enable SMTP authentication
		    $mail->Username		= $config['email'];					// SMTP username
		    $mail->Password		= $config['password'];				// SMTP password
		    // $mail->SMTPSecure	= PHPMailer::ENCRYPTION_STARTTLS;	// Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` also accepted
		    // $mail->SMTPSecure	= false;         					// Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` also accepted
		    // $mail->SMTPSecure	= PHPMailer::ENCRYPTION_SMTPS;
			$mail->SMTPSecure	= $config['secure'];
			if (!$mail->SMTPSecure) $mail->SMTPAutoTLS = false;		// 默认自动启用tls加密
		    // $mail->Port       	= 587;								// TCP port to connect to
		    $mail->Port       	= $config['port'];	// 不同服务提供的端口不同，总体包含：25、465、587、2525
			$mail->setFrom($config['email'], $config['signature']);
		    $mail->CharSet		= 'UTF-8';

		    //Recipients
		    foreach ($config['addList'] as $address) {
		    	$mail->addAddress($address);		// 收件人
		    }
		    if (!empty($data['replay'])) {
				$mail->addReplyTo($data['replay']);	// 回复人
			}
		    foreach ($config['ccList'] as $address) {
		    	$mail->addCC($address);				// 抄送
		    }
		    foreach ($config['bccList'] as $address) {
		    	$mail->addBCC($address);			// 暗抄送
		    }
		    // // 附件
		    // $mail->addAttachment('/tmp/image.jpg', 'new.jpg');    // Optional name
		    $mail->Subject = $data['subject'];
		    if(!empty($data['html'])){
		    	$mail->isHTML(true);				// Set email format to HTML
		    	$mail->Body = $data['content'];
		    }else{
		    	$mail->AltBody = $data['content'];
		    }
			// $mail->Body = "test";pr($mail);exit;			
			if($mail->send()) return array('code' => true);
			return array('code' => false, 'data' => LNG('User.Regist.sendFail'));
        } catch (Exception $e) {
			return array('code' => false, 'data' => $mail->ErrorInfo);
        }
	}

	public function getConfig($data){
		$result = array(
			'addList'	=> explode(';', $data['address']),	// 收件人列表
			'ccList'	=> isset($data['cc']) ? explode(';', $data['cc']) : array(),	// 抄送
			'bccList'	=> isset($data['bcc']) ? explode(';', $data['bcc']) : array(),	// 暗抄送
		);
		if(!isset($data['email'])) {
			if(!$email = Model('SystemOption')->get('email')){
				return array('code' => false, 'data' => LNG('User.Regist.emailSetError'));
			}
			$data = array_merge($data, $email);
		}
		// 允许自定义端口
		$parts = parse_url($data['host']);
		$data['host'] = isset($parts['host']) ? $parts['host'] : $parts['path'];
		$data['port'] = isset($parts['port']) ? $parts['port'] : 465;
		if(empty($data['secure']) || $data['secure'] == 'ssl') {
			$data['secure'] = PHPMailer::ENCRYPTION_SMTPS;
		}else if($data['secure'] == 'tls') {
			$data['secure'] = PHPMailer::ENCRYPTION_STARTTLS;
		}else{
			$data['secure'] = false;
		}
		if(!isset($data['signature'])) $data['signature'] = 'kodbox';

		return array_merge($data, $result);
	}
}
