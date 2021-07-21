<?php 
class officeReaderOfficeLiveIndex extends Controller {
	public function __construct() {
		parent::__construct();
		$this->pluginName = 'officeReaderPlugin';
    }

    public function index(){
        if(!$this->isNetwork()) {
            show_tips(LNG('officeReader.main.needNetwork'), '', '', 'officeLive');
		}
		$plugin = Action($this->pluginName);
        $data = $plugin->_appConfig('ol');
		$fileUrl = $plugin->filePathLinkOut($this->in['path']);
		header('Location:'.$data['apiServer'].rawurlencode($fileUrl));exit;
    }
    
	/**
	 * 可通过互联网访问
	 * @param boolean $domain	要求是域名
	 * @return void
	 */
	public function isNetwork($domain = true){
		$key = md5($this->pluginName . '_officelive_network');
		$check = Cache::get($key);
		if($check !== false) return (boolean) $check;
		$time = ($check ? 30 : 3) * 3600 * 24;	// 可访问保存30天，否则3天

		// 1. 判断是否为域名
		$host = get_url_domain(APP_HOST);
		if($host == 'localhost') return false;
		if($domain && !is_domain($host)) return false;

		// 2. 判断外网能否访问
		$data = array(
			'type'	=> 'network',
			'url'	=> APP_HOST
		);
		$url = $GLOBALS['config']['settings']['kodApiServer'] . 'plugin/platform/';
		$res = url_request($url, 'POST', $data, false, false, false, 3);
		if(!$res || $res['code'] != 200 || !isset($res['data'])) {
			Cache::set($key, 0, $time);
			return false;
		}
		$res = json_decode($res['data'], true);

		$check = (boolean) $res['code'];
		Cache::set($key, (int) $check, $time);
		return $check;
	}

}