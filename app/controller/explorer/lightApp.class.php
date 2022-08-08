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
	
	public function getUrlContent(){
		$url = $this->in['url'];
		$header = url_header($url);
		if(!$header){show_json(array());}
		$contentType = $header['all']['content-type'];
		if(is_array($contentType)){$contentType = $contentType[count($contentType) - 1];}
		
		if(strstr($contentType,'text/html')){
			$content = curl_get_contents($url,30);
			$charset = get_charset($content);
			if($charset !='' && $charset !='utf-8' && function_exists("mb_convert_encoding")){
				$content = @mb_convert_encoding($content,'utf-8',$charset);
			}
			show_json(array('html'=>$content));
		}
		// 图片等处理;
		if(strstr($contentType,'image')){
			$content = curl_get_contents($url,30);
			show_json(array("content"=>base64_encode($content),'isBase64'=>true),true);
		}
		show_json(array('header'=>$header));
	}
	private function input(){
		$arr  = json_decode($this->in['data'],true);
		if(!is_array($arr)){
			show_json(LNG('explorer.error'),false);
		}
		return $arr;
	}
}
