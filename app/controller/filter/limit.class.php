<?php 

class filterLimit extends Controller{
	function __construct(){
		parent::__construct();
    }
    
    public function check(){
        $action  = strtolower(ACTION);
		$actions = array(
			'explorer.index.pathcopyto',    // 分享转存
            'explorer.usershare.edit',      // 外链分享
			'explorer.index.unzip',         // 解压缩
			'explorer.index.zip',           // 压缩
			'explorer.index.zipdownload',   // 下载压缩
			'explorer.upload.fileupload',   // 上传
		);
        if(!in_array($action,$actions)) return;

        switch($action) {
            case 'explorer.index.pathcopyto':
                $this->checkShareCopy();
                break;
            case 'explorer.usershare.edit':
                $this->checkShareLink();
                break;
            case 'explorer.index.unzip':
                $this->checkUnzip();
                break;
            case 'explorer.index.zip':
            case 'explorer.index.zipdownload':
                $this->checkZip();
                break;
            case 'explorer.upload.fileupload':
                $this->checkUpload();
                break;
            default: break;
        }
    }

    /**
     * 分享转存个数限制
     * @return void
     */
    private function checkShareCopy(){
        $list  = json_decode($this->in['dataArr'],true);
		$list  = is_array($list) ? $list : array();
		$storeMax = intval($this->config['settings']['storeFileNumberMax']);
		if(!$storeMax) return;
		
        for ($i=0; $i < count($list); $i++) {
			$path = $list[$i]['path'];
            $pathParse= KodIO::parse($path);
			if($pathParse['type'] != KodIO::KOD_SHARE_LINK) continue;
			if(!$info = Action('explorer.share')->sharePathInfo($path)){
				show_json($GLOBALS['explorer.sharePathInfo.error'], false);
			}
			if($info['type'] == 'folder' && $storeMax && $info['children']['fileNum'] > $storeMax){
                show_json(LNG('explorer.filter.shareCopyLimit').$storeMax, false);
			}
		}
    }

    /**
     * 分享文件/夹大小限制
     * @return void
     */
    private function checkShareLink(){
        $data = Input::getArray(array(
			"shareID"	=> array("check"=>"int"),
			"isLink"	=> array("check"=>"bool", "default"=>0),
		));
        if($data['isLink'] == 1){
			$shareMax    = floatval($this->config['settings']['shareLinkSizeMax'])*1024*1024*1024;
            $shareInfo = Model('Share')->getInfo($data['shareID']);
            if($shareMax && floatval($shareInfo['sourceInfo']['size']) > $shareMax){
                $sizeShow = size_format($shareMax);
                show_json(LNG('explorer.filter.shareSizeLimit').$sizeShow, false);
            }
        }
    }

    /**
     * 解压缩大小限制
     * @return void
     */
    private function checkUnzip(){
        $path = Input::get('path', 'require');
        $info = IO::info($path);
        if(!$info) return;
        $unzipMax  = floatval($this->config['settings']['unzipFileSizeMax'])*1024*1024*1024;
        if($unzipMax && floatval($info['size']) > $unzipMax){
            $sizeShow = size_format($unzipMax);
            show_json(LNG('explorer.filter.unzipSizeLimit').$sizeShow, false);
        }
    }

    /**
     * 压缩大小限制
     * @return void
     */
    private function checkZip(){
        $list  = json_decode($this->in['dataArr'],true);
        $size = 0;
        foreach($list as $item) {
            $info = IO::infoSimple($item['path']);
            $size += (float) $info['size'];
        }
		$zipMax  = floatval($this->config['settings']['zipFileSizeMax'])*1024*1024*1024;
        if($zipMax && $size > $zipMax){
            $sizeShow = size_format($zipMax);
            show_json(LNG('explorer.filter.zipSizeLimit').$sizeShow, false);
        }
    }

    /**
     * 上传大小限制
     * @return void
     */
    private function checkUpload(){
        $size = Input::get('size', null, 0);
		$uploadMax  = floatval($this->config['settings']['ignoreFileSize'])*1024*1024*1024;
        if($size && $uploadMax && floatval($size) > $uploadMax){
            $sizeShow = size_format($uploadMax);
            show_json(LNG('explorer.filter.uploadSizeLimit').$sizeShow, false);
        }
    }
}