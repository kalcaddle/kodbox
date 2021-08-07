<?php 
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/

/**
 * 通用图片附件存储模块;
 * 用于文章,日志,聊天,评论等附件上传;
 * 
 * 上传和内容关联为异步关系; 
 * 1. 附件临时存储池: 所有上层都自动存储到临时附件区域; 定期清理创建时间超过24h的临时文件;
 * 2. 存储关联对象(文章,日志,聊天,评论等): 文章或内容发布后根据id关联文档,文档转存到附件区; 
 * 3. 对象内容图片处理: 外链图片下载到本地; 图片去本地连接;
 * 4. 根据对象清理附件: 文章删除,则删除对应关联的附件资源;  meta: linkArticle=>id; linkMessage=>id;
 * 
 * 处理问题: 避免文章编辑过程中上传文件失联,没有地方可以删除(未关联的内容存储在临时存储池,定期清除;)
 * 外链图片下载到本地;域名白名单;
 */
class explorerAttachment extends Controller{
	function __construct(){
		parent::__construct();
		//去除svg; 避免xxs攻击;
		$this->imageExt = array('png','jpg','jpeg','gif','webp','bmp','ico');
	}
	
	// 上传; 扩展名限制jpg,jpeg,png,ico
	public function upload(){
		$ext = get_path_ext(Uploader::fileName());
		if(!in_array($ext,$this->imageExt)){
			show_json("only support image",false);
		}
		$this->in['name'] = date("YmdHi").rand_string(6).'.'.$ext;
		$this->in['path'] = KodIO::systemFolder('attachmentTemp/');
		Action('explorer.upload')->fileUpload();
	}
	
	// 文档评论;
	public function commentLink($id){
		$where 	= array('commentID'=>$id);
		$data 	= Model("Comment")->where($where)->find();
		if(!$data) return;
		
		$contentNew = $this->linkTarget($id,$data['content'],'comment');
		// 内容有解析变更,则替换;
		if($contentNew && $contentNew != $data['content']){
			Model("Comment")->where($where)->save(array('comment'=>$contentNew));
		}
	}
	public function commentClear($id){
		$this->clearTarget($id,'comment');
	}
	public function noticeLink($id){
		$model 	= Model("SystemNotice");
		$data 	= $model->listData($id);
		if(!$data) return;

		$contentNew = $this->linkTarget($id,$data['content'],'notice');
		// 内容有解析变更,则替换;
		if($contentNew && $contentNew != $data['content']){
			$model->update($id,array('comment'=>$contentNew));
		}
	}
	public function noticeClear($id){
		$this->clearTarget($id,'notice');
	}

	// 自动清理24小时未转移的临时文件;
	public function clearCache(){
		$timeStart  = time() - 3600*24;//1天前未关联的临时文件区域;
		$tempFolder = KodIO::systemFolder('attachmentTemp/');
		$where = array(
			'parentID' 		=> KodIO::sourceID($tempFolder),
			'createTime' 	=> array('<',$timeStart),
		);
		$sourceArr = Model("Source")->where($where)->select();
		$sourceArr = array_to_keyvalue($sourceArr,'','sourceID');
		if(!$sourceArr) return;
		foreach($sourceArr as $sourceID){
			Model('Source')->remove($sourceID,false);
		}
	}

	// 解析内容匹配图片; 关联文件到目标对象;
	public function linkTarget($id,$content,$targetType){
		$result = $this->parseImage($content);
		if(!$result['sourceArr']) return $result['content'];
		
		$metaKey 	= 'attachment_'.$targetType;
		$storePath 	= KodIO::systemFolder('attachment/'.date("Ym/d/"));
		$sourceList = Model('Source')->sourceListInfo($result['sourceArr']);
		foreach ($sourceList as $sourceInfo){
			if($sourceInfo['targetType'] != 'system') continue;
			if($sourceInfo['metaInfo'] && isset($sourceInfo['metaInfo'][$metaKey])) continue;

			IO::move($sourceInfo['path'],$storePath,REPEAT_REPLACE);
			// write_log('move from='.$sourceInfo['path'].'; to='.$storePath,'attachment');
			Model('Source')->metaSet($sourceInfo['sourceID'],$metaKey,$id);
		}
		return $result['content'];
	}

	
	// 清除目标对象关联的附件;
	private function clearTarget($id,$targetType){
		$metaKey = 'attachment_'.$targetType;
		$where 	 = array('key'=>$metaKey,'value'=>$id);
		$sourceArr = Model('io_source_meta')->where($where)->select();
		$sourceArr = array_to_keyvalue($sourceArr,'','sourceID');
		if(!$sourceArr) return true;
		
		foreach ($sourceArr as $sourceID){
			Model('Source')->remove($sourceID,false);
		}
	}

	// 解析图片链接到sourceID; 如果有外链需要转入的替换内容$content;
	private function parseImage($content){
		preg_match_all("/<img.*?src=[\'|\"](.*?)[\'|\"].*?[\/]?>/i",$content,$imageArr);
		if(!$imageArr) return array("sourceArr"=>false,'content'=>$content);
		$sourceArr 	= array();
		$replace 	= array();
		for($i = 0; $i < count($imageArr[1]); $i++) {
			$imageSrc 	= $imageArr[1][$i];
			$imageHtml 	= $imageArr[0][$i];
			$imageParse = $this->parseLink($imageSrc);

			if($imageParse['sourceID']) {
				$sourceArr[] = $imageParse['sourceID'];
			}
			//url解析替换;
			if($imageParse['linkNew'] && $imageSrc != $imageParse['linkNew']){
				$replace[$imageHtml] = str_replace($imageSrc,$imageParse['linkNew'],$imageHtml);
			}
		}
		$sourceArr  = array_unique($sourceArr);
		$contentNew = str_replace(array_keys($replace),array_values($replace),$content);
		return array("sourceArr"=>$sourceArr,'content'=>$contentNew);
	}
	
	private function parseLink($link){
		$local = array(
			'http://127.0.0.1/',
			'https://127.0.0.1/',
			'//127.0.0.1/',
			'http://localhost/',
			'https://localhost/',
			'//localhost/',
		);
		$linkNew = str_replace($local,'/',$link);
		if($this->proxyNeed($link)){ //外部图片链接转为内部链接;
			// $linkNew = $this->fileProxy($link);
		}
		
		preg_match("/explorer\/share\/file(&|&amp;)hash=([0-9a-zA-z_-]+)/",$link,$hash);
		$sourceID = '';
		if($hash){ // 外站kod的url自动处理;
			$pass = Model('SystemOption')->get('systemPassword');
			$path = Mcrypt::decode($hash[2],$pass);
			$ioSource = KodIO::parse($path);
			if($ioSource['type'] == KodIO::KOD_SOURCE){
				$sourceID = $ioSource['id'];
			}
		}
		return array(
			'linkNew'	=> $linkNew == $link ? '' : $linkNew,
			'sourceID'	=> $sourceID,
		);
	}

	// 外链防跨站资源; 自动远程下载;
	private function fileProxy($link){
		$saveFile = TEMP_FILES.'fileProxy_download_'.md5($link);
		$result = Downloader::start($link,$saveFile);
		if(!$result['code']) return $link;

		$ext = get_path_ext($link);
		if(!in_array($ext,$this->imageExt)){
			$ext = 'jpg';
		}
		$filename = date("YmdHi").rand_string(6).'.'.$ext;
		$storePath = KodIO::systemFolder('attachmentTemp/');
		$file = IO::move($saveFile,$storePath,REPEAT_REPLACE);
		IO::rename($file,$filename);
		return Action('explorer.share')->link($file);
	}
	private function proxyNeed($link){
		$parseUrl = parse_url($link);
		if(!$parseUrl['host']) return false;
		
		$hostAllow = array(
			'douban.com','doubanio.com',
			'qq.com',
		);
		foreach ($hostAllow as $domain){
			if(substr($parseUrl['host'],-strlen($domain)) == $domain) return true;
		}
		return false;
	}
	
}