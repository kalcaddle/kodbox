<?php 
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/

/**
 * 通用数据过滤;
 */
class filterHtml extends Controller{
	function __construct(){
		parent::__construct();
	}
	
	// 自动绑定处理;
	public function bind(){
		$action = strtolower(ACTION);
		$check  = array(
			'admin.notice.add' 	=> array('content'=>'htmlFilter'),
			'admin.notice.edit' => array('content'=>'htmlFilter'),
			'comment.index.add' => array('content'=>'htmlOnlyImage','title'=>'htmlClear')
		);
		if(!isset($check[$action])) return;
		$this->filter($check[$action]);
	}
	
	public function filter($item){
		$in = $this->in;
		foreach ($item as $key => $method) {
			if(!isset($in[$key]) || !$in[$key]) continue;
			
			switch($method){
				case 'htmlFilter':$this->in[$key] = Html::clean($in[$key]);break;
				case 'htmlOnlyImage':$this->in[$key] = Html::onlyImage($in[$key]);break;
				case 'htmlClear':$this->in[$key] = clear_html($in[$key]);break;
				case 'json':$this->in[$key] = json_decode($in[$key]);break;
				default:break;
			}
		}
	}
}