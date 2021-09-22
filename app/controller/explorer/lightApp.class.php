<?php 
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/

class explorerLightApp extends Controller{
	private $model;
	function __construct()    {
		$this->model = Model('SystemLightApp');
		parent::__construct();
	}

	/**
	 * 获取列表
	 * 通过分类获取；默认为all
	 */
	public function get() {
		$group = Input::get('group','require','all');
		$list  = $this->model->listData();
		$result = array();
		foreach ($list as $item) {
			if($item['group'] == $group || $group == 'all'){
				$result[] = $item;
			}
		}
		show_json($result);
	}

	/**
	 * 添加
	 */
	public function add() {
		$res = $this->model->add($this->input());
		$msg = !!$res ? LNG('explorer.success') : LNG('explorer.repeatError');
		show_json($msg,!!$res);
	}

	/**
	 * 编辑
	 */
	public function edit() {
		$name = $this->in['beforeName'];
		$res  = $this->model->update($name,$this->input());
		$msg = !!$res ? LNG('explorer.success') : LNG('explorer.repeatError');
		show_json($msg,!!$res);
	}
	/**
	 * 删除
	 */
	public function del() {
		$name = rawurldecode($this->in['name']);
		$res = $this->model->remove($name);
		$msg = !!$res ? LNG('explorer.success') : LNG('common.notExists');
		show_json($msg,!!$res);
	}
	
	public function getUrlTitle(){
		$html = curl_get_contents($this->in['url']);
		$charset = get_charset($html);
		if ($charset !='' && $charset !='utf-8' && function_exists("mb_convert_encoding")){
			$html = @mb_convert_encoding($html,'utf-8',$charset);
		}
		$result = match_text($html,"<title>(.*)<\/title>");
		if (!$result || strlen($result) == 0) {$result = $this->in['url'];}
		$result = str_replace(array('http://',':','&','/','?'),array('','_','@','-','_'), $result);
		show_json($result);
	}
	private function input(){
		$arr  = json_decode($this->in['data'],true);
		if(!is_array($arr)){
			show_json(LNG('explorer.error'),false);
		}
		return $arr;
	}
}
