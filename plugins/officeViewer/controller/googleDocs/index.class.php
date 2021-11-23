<?php 
class officeViewerGoogleDocsIndex extends Controller {
	public function __construct() {
		parent::__construct();
		$this->pluginName = 'officeViewerPlugin';
		$this->appName = LNG('officeViewer.googleDocs.name');
    }

    public function index(){
		$plugin = Action($this->pluginName);
		if(!$plugin->allowExt('gg')) {
			$plugin->showTips(LNG('officeViewer.main.invalidExt'), $this->appName);
		}
        if(!$plugin->isNetwork(false)) {
			$msg = LNG('officeViewer.main.error') . LNG('officeViewer.main.needNetwork') . LNG('officeViewer.googleDocs.needNetwork');
			$plugin->showTips($msg, $this->appName);
		}
        $data = $plugin->_appConfig('gg');
		$fileUrl = $plugin->filePathLinkOut($this->in['path']);
		header('Location:'.$data['apiServer'].rawurlencode($fileUrl));
    }
}