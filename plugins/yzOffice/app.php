<?php
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/
class yzOfficePlugin extends PluginBase{
	function __construct(){
		parent::__construct();
	}
	public function regist(){
		$this->hookRegist(array(
			'user.commonJs.insert'	=> 'yzOfficePlugin.echoJs'
		));
	}
	public function echoJs(){
		$this->echoFile('static/main.js');
	}

	public function index(){
        $app = $this->getObj();
		if(!$app->task['success'] ){
            if($link = $this->fileLink()) {
                $this->fileOutLink($app, $link);
            }
			include($this->pluginPath.'php/template.php');
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
            show_tips(LNG('yzOffice.Main.invalidUrl'));
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
		$name = md5($this->realFilePath);
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

		$path = $this->pluginPath . 'data/';
		if(!is_dir($path)) mk_dir($path);
        $file = $path . 'viewurls.txt';
        if(@!file_exists($file) && !$link) return false;
        $data = file_get_contents($file);
        $data = json_decode($data, true);
        $name = md5($this->realFilePath);
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
            $msg = isset($data['message']) ? $data['message'] : LNG('yzOffice.Main.linkExpired');
			show_tips($msg . LNG('yzOffice.Main.tryAgain'));
        }
		exit;

        $res = url_request($link);
		$data = json_decode($res['data'], true);
        // 没有错误，直接输出
        if(!$data && !empty($res['header']['Set-Cookie'])) {
            header('Location:' . $link);exit;
        }
        $app->clearCache();
        $this->fileLink($link, true);
		$msg = isset($data['message']) ? $data['message'] : LNG('explorer.error');
        show_tips($msg . LNG('yzOffice.Main.tryAgain'));
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
		$path = $this->filePath($this->in['path']);
		$this->realFilePath = $path;
		// if(filesize($path) > 1024*1024*2){
		// 	//show_tips("由于永中官方接口限制,<br/>暂不支持大于2M的文件在线预览！");
		// }
		//文档分享预览; http://yozodoc.com/
		// require_once($this->pluginPath.'php/yzOffice.class.php');
		// return  new yzOffice($this,$path);
		
		//官网用户demo;
		//http://www.yozodcs.com/examples.html     2M上传限制;
		//http://dcs.yozosoft.com/examples.html
		require_once($this->pluginPath.'php/yzOffice.class.php');
		return new yzoffice($this,$path);
	}

	public function restart(){
		$app = $this->getObj();
		$res = $app->clearCache();
		show_json('success');
	}
}

