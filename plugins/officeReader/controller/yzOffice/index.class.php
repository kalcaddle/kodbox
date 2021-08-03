<?php 
class officeReaderYzOfficeIndex extends Controller {
	public function __construct() {
		parent::__construct();
		$this->pluginName = 'officeReaderPlugin';
        $this->plugin = Action($this->pluginName);
        $this->_dir = __DIR__ . '/';
        $this->filePath = '';   // 解析后的文件路径
    }

    public function index(){
        // 改地址需和yzOffice.class.php保持一致
        if(!check_url('https://www.yozodcs.com/')) {
            show_tips(LNG('officeReader.main.notNetwork'), '', '', LNG('officeReader.yzOffice.name'));
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
			show_tips($infoData['message']);
		}
        if(empty($infoData['data']['viewUrl'])) {
            $app->clearCache();
            show_tips(LNG('officeReader.main.invalidUrl'));
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
        $path = $this->_dir . 'data/';
		if(!is_dir($path)) mk_dir($path);
        $file = $path . 'viewurls.txt';
        if(@!file_exists($file) && !$link) return false;
        $data = file_get_contents($file);
        $data = json_decode($data, true);
        $name = md5($this->filePath);
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
        // 没有错误，且set-cookie不为空（正常为viewpath=xxx，过期的为空），直接输出
        if(!$data && !empty($res['header']['Set-Cookie'])) {
            header('Location:' . $link);exit;
        }
        $app->clearCache();
        $this->fileLink($link, true);
		$msg = isset($data['message']) ? $data['message'] : LNG('explorer.error');
        show_tips($msg . LNG('officeReader.main.tryAgain'));
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
		show_json('success');
	}

}