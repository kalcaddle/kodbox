<?php 
class officeReaderOfficeLiveIndex extends Controller {
	public function __construct() {
		parent::__construct();
		$this->pluginName = 'officeReaderPlugin';
    }

    public function index(){
		$plugin = Action($this->pluginName);
        if(!$plugin->isNetwork()) {
            show_tips(LNG('officeReader.main.needNetwork') . LNG('officeReader.main.needDomain'), '', '', 'officeLive');
		}
        $data = $plugin->_appConfig('ol');
		$fileUrl = $plugin->filePathLinkOut($this->in['path']);
		header('Location:'.$data['apiServer'].rawurlencode($fileUrl));exit;
    }
}