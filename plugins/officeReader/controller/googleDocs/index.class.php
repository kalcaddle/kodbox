<?php 
class officeReaderGoogleDocsIndex extends Controller {
	public function __construct() {
		parent::__construct();
		$this->pluginName = 'officeReaderPlugin';
    }

    public function index(){
		$plugin = Action($this->pluginName);
        if(!$plugin->isNetwork(false)) {
            show_tips(LNG('officeReader.main.needNetwork'), '', '', LNG('officeReader.googleDocs.name'));
		}
        $data = $plugin->_appConfig('gg');
		$fileUrl = $plugin->filePathLinkOut($this->in['path']);
		header('Location:'.$data['apiServer'].rawurlencode($fileUrl));exit;
    }
}