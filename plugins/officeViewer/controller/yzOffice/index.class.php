<?php 
class officeViewerYzOfficeIndex extends Controller {
	public function __construct() {
		parent::__construct();
		$this->pluginName = 'officeViewerPlugin';
		$this->plugin = Action($this->pluginName);
        $this->_dir = __DIR__ . '/';
        $this->filePath = '';   // 解析后的文件路径
        $this->appName = LNG('officeViewer.yzOffice.name');
    }

    public function index(){
        if(!$this->plugin->allowExt('yz')) {
            $this->plugin->showTips(LNG('officeViewer.main.invalidExt'), $this->appName);
		}
        // 改地址需和yzOffice.class.php保持一致
        $header = url_header('https://www.yozodcs.com/');
        if(!$header || !$header['status']) {
        // if(!check_url('https://www.yozodcs.com/')) {
            $this->plugin->showTips(LNG('officeViewer.main.notNetwork'), $this->appName);
        }
        $app = $this->getObj();
		if(!$app->task['success'] ){
            if($link = $this->fileLink()) {
                $this->fileOutLink($app, $link);
            }
			include_once($this->_dir.'template.php');
			return;
		}
		//获取预览url
		$step     = count($app->task['steps']) - 1;
		$infoData = $app->task['steps'][$step]['result'];
		if($infoData['errorcode'] || !is_array($infoData['data']) ){
			$app->clearCache();
			$this->plugin->showTips($infoData['message'], $this->appName);
		}
        if(empty($infoData['data']['viewUrl'])) {
            $app->clearCache();
            $this->plugin->showTips(LNG('officeViewer.main.invalidUrl'), $this->appName);
        }
		$link = $infoData['data']['viewUrl'];
        $link = $this->fileLink($link);
        $this->fileOutLink($app, $link);
    }
    /**
     * viewUrl读取、更新和删除
     * @param boolean $link
     * @param boolean $del
     * @return void
     */
    private function fileLink($link = false, $del = false){
        $key = md5($this->pluginName . '.yzOffice.viewUrls');
        $data = Cache::get($key);
        if(!$data) $data = array();
        $info = IO::info($this->filePath);
        // $name = md5($info['path'] . (isset($info['fileID']) ? '_'.$info['fileID'] : ''));
        $name = md5(isset($info['fileID']) ? $info['fileID'] : $info['path'].'_'.$info['size']);
        if(!$link) {
            return isset($data[$name]) ? $data[$name] : false;
        }
        if($del) {
            unset($data[$name]);
        }else{
            $data[$name] = $link;
        }
        Cache::set($key, $data);
        return $link;

        $path = $this->_dir . 'data/';
		if(!is_dir($path)) mk_dir($path);
        $file = $path . 'viewurls.txt';
        if(@!file_exists($file) && !$link) return false;
        $data = file_get_contents($file);
        $data = json_decode($data, true);

        $info = IO::info($this->filePath);
        $name = md5($info['path'] . (isset($info['fileID']) ? '_'.$info['fileID'] : ''));
        if(!$link) {
            return isset($data[$name]) ? $data[$name] : false;
        }
        if($del) {
            unset($data[$name]);
        }else{
            $data[$name] = $link;
        }
        // 可能要加锁
        file_put_contents($file, json_encode_force($data));
        return $link;
    }
    // 链接可能已失效，输出前先判断
    private function fileOutLink($app, $link){
        $res = url_request($link);
		$data = json_decode($res['data'], true);
        // 没有错误(字符串decode结果为null)，且set-cookie不为空（正常为viewpath=xxx，过期的为空），直接输出
        if(!$data && (!empty($res['header']['Set-Cookie']) || !empty($res['header']['set-cookie']))) {
            header('Location:' . $link);
        } else {
            $app->clearCache();
            $this->fileLink($link, true);
            // $this->index();
            $msg = isset($data['message']) ? $data['message'] : LNG('officeViewer.yzOffice.linkExpired');
            $this->plugin->showTips($msg . LNG('officeViewer.main.tryAgain'), $this->appName);
        }
    }

	public function task(){
		$app = $this->getObj();
		$app->runTask();
	}
	public function getFile(){
		$app = $this->getObj();
		$app->getFile($this->in['file']);
	}
	private function getObj(){
        $path = $this->plugin->filePath($this->in['path']);
        $this->filePath = $path;
		require_once($this->_dir.'yzOffice.class.php');
		return new yzOffice($this->plugin, $path);
	}

	public function restart(){
		$app = $this->getObj();
		$res = $app->clearCache();
        $this->fileLink(true, true);
		show_json('success');
	}

}