<?php 
class officeViewerOfficeLiveIndex extends Controller {
	public function __construct() {
		parent::__construct();
		$this->pluginName = 'officeViewerPlugin';
    }

    public function index(){
		$plugin = Action($this->pluginName);
        if(!$plugin->allowExt('ol')) {
			$plugin->showTips(LNG('officeViewer.main.invalidExt'), 'officeLive');
		}
        if(!$plugin->isNetwork()) {
			$msg = LNG('officeViewer.main.error') . LNG('officeViewer.main.needNetwork') . LNG('officeViewer.main.needDomain');
			$plugin->showTips($msg, 'officeLive');
		}
        $data = $plugin->_appConfig('ol');
		$fileUrl = $plugin->filePathLinkOut($this->in['path']);
		header('Location:'.$data['apiServer'].rawurlencode($fileUrl));
    }
}