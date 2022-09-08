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
		$list  = $this->model->listData(false,'id');
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
			show_json(array('html'=>$content,'header'=>$header));
		}
		// 图片等处理;
		if(strstr($contentType,'image')){
			$content = curl_get_contents($url,30);
			show_json(array("content"=>base64_encode($content),'isBase64'=>true,'header'=>$header),true);
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
	
	 /**
     * 轻应用列表初始化
     */
    public function initApp(){
		$this->clearOldApps();
		$str = file_get_contents(BASIC_PATH.'data/system/apps.php');
		$data= json_decode(substr($str, strlen('<?php exit;?>')),true);
		$data = array_reverse($data);
		foreach ($data as $app) {
			$type = $app['type'] == 'app' ? 'js' : $app['type'];
			$item = array(
				'name' 		=> $app['name'],
				'group'		=> $app['group'],
				'desc'		=> $app['desc'],
				'content'	=>  array(
					'type'		=> $type,
					'value'		=> $app['content'],
					'icon'		=> $app['icon'],
					'options' => array(
						"width"  	=> $app['width'],
						"height" 	=> $app['height'],
						"simple" 	=> $app['simple'],
						"resize" 	=> $app['resize']
					),
				)
			);
			if(isset($app['openType'])){
				$item['content']['options']['openType'] = $app['openType'];
			}
			if( $this->model->findByName($item['name']) ){
				$this->model->update($item['name'],$item);
			}else{
				$this->model->add($item);
			}
        }
    }
	private function clearOldApps(){
		// $this->model->clear();
		$clearOld = array(
			"豆瓣电台","365日历",
			'Kingdom Rush','Vector Magic','中国象棋','天气',"iqiyi影视",
			'计算器','音悦台','黑8对决','Web PhotoShop','一起写office',
			"微信","百度DOC",'百度随心听',"腾讯canvas","pptv直播","搜狐影视",
		);
		foreach($clearOld as $app){
			$this->model->remove($app);
		}
	}
}
