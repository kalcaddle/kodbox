<?php 
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/

/**
 * 通用附件关联处理;
 */
class filterAttachment extends Controller{
	function __construct(){
		parent::__construct();
	}
	
	// 自动绑定处理;
	public function bind(){
		Hook::bind('admin.notice.add.after',array($this,'doNoticeLink'));
		Hook::bind('admin.notice.edit.after',array($this,'doNoticeLinkEdit'));
		Hook::bind('admin.notice.remove.after',array($this,'doNoticeClear'));
		Hook::bind('comment.index.add.after',array($this,'doCommentLink'));
		Hook::bind('comment.index.remove.after',array($this,'doCommentClear'));
	}
	public function doNoticeLink($json){Action('explorer.attachment')->noticeLink($json['info']);}
	public function doNoticeLinkEdit($json){Action('explorer.attachment')->noticeLink($this->in['id']);}
	public function doNoticeClear($json){Action('explorer.attachment')->noticeClear($this->in['id']);}
	public function doCommentLink($json){Action('explorer.attachment')->commentLink($json['data']);}
	public function doCommentClear($json){Action('explorer.attachment')->commentClear($this->in['id']);}
}