<?php 
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/

/**
 * 搜索引擎优化;
 */
class explorerSeo extends Controller{
	function __construct(){
		parent::__construct();
	}
	public function check(){
		I18n::set('title','');
		Hook::bind('templateCommonFooter','explorer.seo.makeFooter');
		if(MOD == 'sitemap'){$this->siteMap();}
		if(MOD == 's'){$this->shareView(ST);}
	}
	public function makeFooter(){
		$powerBy 	= LNG("common.copyright.powerBy");
		$homePage	= str_replace('%s',APP_HOST,LNG('common.copyright.homepage'));

		$isKeep   = _get($this->in,'keep') == '1' ? 'keep=1':null;
		$siteMap  = urlApi('sitemap/',$isKeep);		
		$link  = 'https://github.com/kalcaddle/kodbox';
		$html  = "<div class='page-footer' style='display:none;'>\n\t";
		$html .= "<h3>{$powerBy} <a href='{$link}' target='_blank'>V".KOD_VERSION."</a></h3>\n\t";
		$html .= "<h3>{$homePage}</h3>\n\t";
		$html .= "<h3><a href='".$siteMap."'>Files</a></h3></div>\n\t";
		echo $html;
	}
	
	/**
	 * 搜索引擎抓取:
	 * 1. 外链分享列表;分页处理;
	 * 2. 外链分享文件概览,文件夹子文件 (文本文件内容截取10k);
	 * 3. 外链分享文件夹列表;
	 */
	public function siteMap(){
		if($this->config['settings']['allowSEO'] != 1){
			header('HTTP/1.1 404 Not Found');
			return show_tips("Not allow robots!");
		}
		switch(ST){
			case 'index': $this->shareList();exit;break;
			case 'share': $this->shareView(ACT);exit;break;
			case 'file' : $this->shareFileOut();exit;break;
			default: break;
		}
	}

	private function displayContent($html,$link=''){
		if(!$html) return;
		$link = $link ? $link : APP_HOST;
		$location = '<script type="text/javascript">window.location.href="'.$link.'"</script>';
		$GLOBALS['templateContent'] = "<div class='page-view-search'>{$html}</div>";
		if(_get($this->in,'keep') != '1'){
			// pr_trace($link);exit;
			$GLOBALS['templateContent'] =$location.$GLOBALS['templateContent'];
		}
		Hook::bind('templateCommonContent','explorerSeo.echoContent');
		include(TEMPLATE.'user/index.html');
	}
	private function displayError($content,$link=''){
		header('HTTP/1.1 404 Not Found');
		$html = '<div class="info-alert info-alert-red"><p>'.$content.'</p></div>';
		$this->displayContent($html,$link);
	}
	public function echoContent(){
		echo "<style>
			body{
				position:relative !important;width:auto;height:auto;
				background:#f0f2f5;overflow:auto !important;
			}
			.page-footer{display:block !important;text-align: center;color:#aaa;}
			.page-footer h3{display:inline-block;font-size: 13px;font-weight:400;}
		</style>";
		echo $GLOBALS['templateContent'];
	}
	
	// 分享列表;
	private function shareList(){
		$title = LNG('explorer.share.linkTo');
		I18n::set('title',$title.' - ');
		$model = Model('Share');
		$where = array(
			'isLink'	=> '1',
			'password'	=> '',
			'timeTo'	=> array('<',time())
		);
		$this->in['pageNum'] = 15;//>5;
		$this->in['page'] 	 = _get($this->in,'page','1');
		$list = $model->where($where)->order("createTime desc")->selectPage();

		$listHtml = '';
		$isKeep   = _get($this->in,'keep') == '1' ? 'keep=1':null;
		$siteMap  = urlApi('sitemap/',$isKeep);
		$pageHtml = $this->makePage($list['pageInfo'],$siteMap,5);
		$list = Model('Share')->listDataApply($list['list']);
		foreach ($list as $item){
			$listHtml .= $this->shareMakeItem($item);
		}
		if(!$list){
			$listHtml = "<div class='grey-6 align-center mt-30'>".LNG('common.empty')."</div>";
		}else{
			$listHtml = "
			<li class='file-item header'>
				<span class='title-item item-name'>".LNG('common.name')."</span>
				<span class='title-item item-user'>".LNG('explorer.auth.share')."</span>
				<span class='title-item item-size'>".LNG('explorer.file.size')."</span>
				<span class='title-item item-time'>".LNG('explorer.file.shareTime')."</span>
			</li>".$listHtml;
		}
		$html = "<h3>{$title}</h3><ul class='list-file list-file-share'>$listHtml</ul>$pageHtml";
		$this->displayContent($html);
	}
	
	/**
	 * 分享内容: 分享文件;分享文件夹;分享文件夹子文件
	 * 分享信息: 分享信息,路径信息,文件; 文件夹--列表
	 * 文件展示: 文本-展示1M; 图片展示img; 其他文件:下载链接; (有预览权限)
	 */
	private function shareView($hash){
		$viewPath 	= trim(_get($this->in,'view',''),'/');
		$view  		= $viewPath ? '&view='.rawurlencode($viewPath):'';
		$linkTrue 	= APP_HOST.'#s/'.$hash.str_replace('%2F','/',$view);
		
		$shareInfo 	= Model('Share')->getInfoByHash($hash);
		$error  	= $this->shareCheck($shareInfo);		
		if($error) return $this->displayError($error,$linkTrue);
		
		$sourcePath = $shareInfo['sourcePath'].'/'.$viewPath;
		$shareDesc 	= $this->shareMakeItem($shareInfo);
		$linkPage 	= $this->shareLink($shareInfo);
		$addressHtml = "<a href='{$linkPage}'>{$shareInfo['title']}</a>";
		$viewPathArr = explode('/',$viewPath);
		for($i = 0; $i < count($viewPathArr); $i++){
			if(!$viewPathArr[$i]) continue;
			$viewPathNow = implode('/',array_slice($viewPathArr,0,$i+1));
			$link = $this->shareLink($shareInfo,$viewPathNow);			
			$addressHtml .= " / <a href='{$link}'>".htmlentities($viewPathArr[$i])."</a>";
		}
		$html = "
		<div class='share-header-info'>{$shareDesc}</div>
		<div class='address-info'>".LNG('common.position').": ".$addressHtml."</div>";

		$pathInfo = IO::infoFull($sourcePath);
		if(!$pathInfo) return $this->displayError(LNG('common.pathNotExists'),$linkTrue);
		
		// 标题处理;
		$currentName = $viewPath ? $pathInfo['name'].' - ' : '';
		I18n::set('title',$currentName.$shareInfo['title'].' - ');
		
		if($pathInfo['type'] == 'file'){
			$movieFile = explode(',','mov,mp4,webm,m4v,mkv');
			$imageFile = explode(',','jpg,jpeg,png,bmp,ico,gif,webp');
			$linkFile  = $this->shareLink($shareInfo,$viewPath,'file');
			if(is_text_file($pathInfo['ext'])){
				$content = IO::fileSubstr($pathInfo['path'],0,1024*500);
				$html .= "<p><pre class='the-code'><code>".htmlentities($content)."</code></pre></p>";
				$html .= "<script src='".STATIC_PATH."app/vender/markdown/highlight.min.js'></script>
					<script>hljs.initHighlightingOnLoad();</script>";
			}else if(in_array($pathInfo['ext'],$imageFile)){
				$html .= "<p class='content-file'><img src='{$linkFile}' /></p>";
			}else if(in_array($pathInfo['ext'],$movieFile)){
				$html .= "<video class='content-file' src='{$linkFile}' controls='controls'></video>";
			}else{
				$html .= "
				<div class='content-download'>
					<a class='kui-btn kui-btn-blue' href='{$linkFile}' 
					target='_blank'>".LNG('common.download')."</a>
					<div class='grey-6'>".LNG('explorer.share.errorShowTips')."</div>
				</div>";
			}
		}else{
			$html .= $this->shareViewFolder($shareInfo,$pathInfo);
		}
		$this->displayContent($html,$linkTrue);
	}
	private function shareFileOut(){
		$shareInfo= Model('Share')->getInfoByHash(ACT);
		$error = $this->shareCheck($shareInfo);
		if($error) return $this->displayError($error);

		$viewPath   = trim(_get($this->in,'view',''),'/');
		$sourcePath = $shareInfo['sourcePath'].'/'.$viewPath;
		$pathInfo = IO::infoFull($sourcePath);
		if(!$pathInfo) return $this->displayError(LNG('common.pathNotExists'));
		IO::fileOut($pathInfo['path']);exit;
	}
	
	private function shareViewFolder($shareInfo,$pathInfo){
		$pathPre 	= _get($shareInfo['sourceInfo'],'pathDisplay',$shareInfo['sourceInfo']['path']);
		$list 		= IO::listPath($pathInfo['path']);
		$fileList 	= array_sort_by($list['fileList'],'name');
		$folderList = array_sort_by($list['folderList'],'name');
		$listAll 	= array_merge($folderList,$fileList);
		$listAll 	= array_slice($listAll,0,2000);
				
		$listHtml 	= '';
		foreach ($listAll as $pathInfo){
			$pathDisplay = _get($pathInfo,'pathDisplay',$pathInfo['path']);
			if(substr($pathDisplay,0,strlen($pathPre)) != $pathPre) continue;
			$viewPath = substr($pathDisplay,strlen($pathPre));
			$link = $this->shareLink($shareInfo,$viewPath);
			$time = date('Y-m-d H:i',$pathInfo['createTime']);
			$size = size_format($pathInfo['size']);
			$size = $pathInfo['type'] != 'folder' || $pathInfo['size'] ? $size:'';
			$ext  = $pathInfo['type'] == 'folder' ? 'folder' : $pathInfo['ext'];
			$listHtml .= "
			<li class='file-item {$pathInfo['type']}'>
				<a class='file-link' href='{$link}'></a>
				<span class='title-item item-name'>
					<i class='path-ico'><i class='x-item-icon x-{$ext} small'></i></i>
					<a href='{$link}'>".htmlentities($pathInfo['name'])."</a>
				</span>
				<span class='title-item item-size'>{$size}</span>
				<span class='title-item item-time'>{$time}</span>
			</li>";
		}
		if(!$listAll){
			$listHtml = "<div class='grey-6 align-center mt-30'>".LNG('common.empty')."</div>";
		}else{
			$listHtml = "
			<li class='file-item header'>
				<span class='title-item item-name'>".LNG('common.name')."</span>
				<span class='title-item item-size'>".LNG('explorer.file.size')."</span>
				<span class='title-item item-time'>".LNG('common.createTime')."</span>
			</li>".$listHtml;
		}
		return "\n<ul class='list-file list-file-folder'>".$listHtml."</ul>\n";
	}
	private function shareLink($shareInfo,$viewPath='',$page='share'){
		$view  = $viewPath ? '&view='.rawurlencode($viewPath):'';
		$view  = str_replace('%2F','/',$view);
		$keep  = _get($this->in,'keep') == '1' ? '&keep=1' : '';
		return urlApi('sitemap/'.$page.'/'.$shareInfo['shareHash'],ltrim($keep.$view,'&'));
	}
	
	// 分享权限处理;
	private function shareCheck($shareInfo){
		$msg = array(
			'notExists' 	=> LNG('explorer.share.notExist'),
			'needPassword' 	=> LNG('explorer.share.needPwd'),
			'onlyLogin'		=> LNG('explorer.share.onlyLogin'),
			'timeout' 		=> LNG('explorer.share.errorTime'),
			'notDownload' 	=> LNG('explorer.share.noDownTips'),
			'downloadLimit'	=> LNG('explorer.share.downExceedTips'),
		);
		if(!$shareInfo || !is_array($shareInfo)) return $msg['notExists'];
		
		$downloadNumber = _get($shareInfo,'options.downloadNumber');
		$downloadLimit  =  $downloadNumber && intval($downloadNumber) > intval($shareInfo['numDownload']);	
		if($shareInfo['timeTo'] && intval($shareInfo['timeTo']) < time()) return $msg['timeout'];
		if($shareInfo['password']) return $msg["needPassword"];
		if(_get($shareInfo,'options.notDownload') == '1') return $msg['notDownload'];
		if(_get($shareInfo,'options.onlyLogin') == '1') return $msg['onlyLogin'];
		if($downloadLimit) return $msg['downloadLimit'];
		return false;
	}
	private function shareMakeItem($item){
		if($this->shareCheck($item)) return '';
		$pathInfo = $item['sourceInfo'];
		if(!$item['userInfo']){
			$userList = Model('User')->userListInfo(array($item['userID']));
			$item['userInfo'] = $userList[$item['userID']];
		}
		if(!$pathInfo && $item['sourceID'] == '0'){
			$pathInfo = IO::info($item['sourcePath']);
		}
		if(!$pathInfo) return '';
		$link = $this->shareLink($item);
		$user = _get($item['userInfo'],'nickName',_get($item['userInfo'],'name'));

		$time = date('Y-m-d H:i',$item['createTime']);	
		$size = size_format($pathInfo['size']);
		$size = $pathInfo['type'] != 'folder' || $pathInfo['size'] ? $size:'';
		$ext  = $pathInfo['type'] == 'folder' ? 'folder' : $pathInfo['ext'];
		return "
		<li class='file-item'>
			<a class='file-link' href='{$link}'></a>
			<span class='title-item item-name'>
				<i class='path-ico'><i class='x-item-icon x-{$ext} small'></i></i>
				<a href='{$link}'>".htmlentities($item['title'])."</a>
			</span>
			<span class='title-item item-user'>".htmlentities($user)."</span>
			<span class='title-item item-size'>{$size}</span>
			<span class='title-item item-time'>{$time}</span>
		</li>";
	}
	private function makePage($info,$linkPre,$showNum=5){
		if($showNum <= 2 || !$info || $info['pageTotal'] == 0 ) return '';
		$total 	= $info['pageTotal'];
		$pageDesc = 
		"<span class='page-info-text'>
			{$total}".LNG('explorer.table.page')." <span class='grey-6'>
			({$info['totalNum']}".LNG('explorer.table.items').")</span>
		</span>";
		if($total <= 1) return "<div class='page-box'>\n{$pageDesc}</div>";
		
		$from 	= $info['page'] - intval(($showNum - 1) / 2);
		$to   	= $info['page'] + intval(($showNum - 1) / 2) + ($showNum % 2 == 0 ? 1 : 0);
		$from 	= $to   > $total ? ($total - $showNum + 1) : $from;
		$to 	= $from < 1 ? $showNum : $to;
		$from 	= $from <= 1 ? 1 : $from;
		$to 	= $to   >= $total ? $total : $to;
		
		$html 	= '';
		for($i = $from; $i<=$to; $i++){
			if($i == $info['page']){
				$html .= "<a href='{$linkPre}&page={$i}' class='current'>{$i}</a>\n";
			}else{
				$html .= "<a href='{$linkPre}&page={$i}'>{$i}</a>\n";
			}
		}
		if($total > $showNum){
			$html = "<a href='{$linkPre}&page=1' class='page-first'>".LNG('explorer.table.first')."</a>\n".$html;
			$html.= "<a href='{$linkPre}&page={$total}' class='page-last'>".LNG('explorer.table.last')."</a>\n";
		}
		return "<div class='page-box'>\n{$html}{$pageDesc}\n</div>";
	}
}