<?php 

/**
 * 图片请求转发;
 * https://api.kodcloud.com/#explorer&pathFile=%2Fwww%2Fwwwroot%2Fapi.kodcloud.com%2Fplugins%2FplatformKod%2Fcontroller%2Ftools%2FwallpageApi.class.php
 */
class userViewImage extends Controller{
	public function __construct(){
		parent::__construct();
	}
	
	// type=show;[page=xx]; type=search;[words=xx,page=xx]
	public static $allowCache = 1;
	public function request($load){
		$apiArr = $this->loadApi();
		$type   = $this->in['type'] == 'search' ? 'search':'show';
		if(!is_array($apiArr) || !is_array($apiArr[$type]) ){
			show_json('Api config error!',false);
		}
		$api = $apiArr[$type];
		$search = _get($this->in,'search','');
		$page   = intval(_get($this->in,'page','1'));
		$pageMax= intval(_get($api['parse'],'pageTotalSet',0));
		$page   = $page <= 0 ? 1: (($pageMax && $page >= $pageMax) ? $pageMax : $page);
		$pageValue = $api['parse']['pageOffset'] ? intval($api['parse']['pageOffset'] * $page) : $page;
		$replaceTo = array($pageValue,rawurlencode($search));
		$url = str_replace(array('{{page}}','{{search}}'),$replaceTo,$api['url']);
		
		$cacheKey = "wallpageImageApi-".md5($url);
		$result = Cache::get($cacheKey);
		if(is_array($result) && self::$allowCache){show_json($result,true);}
		
		$header = is_array($api['header']) ? $api['header']: false;
		$res  = url_request($url,'GET',false,$header,false,false,30);
		$result = $this->imageParse($res['data'],$api);
		if(!$result){show_json("Request data error!",false);}
		
		$result['pageInfo']['page'] = $page;
		Cache::set($cacheKey,$result,600);
		show_json($result,true);
	}
	private function loadApi(){
		$url = $GLOBALS['config']['settings']['kodApiServer'];
		if(!$url) return false;
		$result = Cache::get("wallpageImageApi");
		if(is_array($result) && self::$allowCache) return $result;

		$res  = url_request($url.'wallpage/api','GET',false,false,false,false,30);
		$json = json_decode(_get($res,'data',''),true);
		if(!$json || !$json['code'] || !is_array($json['data'])) return false;
		Cache::set("wallpageImageApi",$json['data'],3600);
		return $json['data'];
	}
	private function imageParse(&$body,$api){
		if( strstr($body,'charset=gb2312') || 
			strstr($body,'charset=gbk')){
			$body = iconv_to($body,'gbk','utf-8');
		}
		$parse = $api['parse'];
		$list  = array();
		$pageInfo = array(
			'pageTotal'	=> _get($parse,'pageTotalSet',''),
			'totalNum'	=> _get($parse,'totalNumSet',''),
		);
		$urlAdd = $parse['urlAdd'] ? $parse['urlAdd']: '';
		if($parse['type'] == 'json'){
			$json = json_decode($body,true);
			$listData = $parse['arr'] ? _get($json,$parse['arr'],array()) : $json;
			if(!$listData) return false;
			
			foreach ($listData as $item) {
				$list[] = array(
					'link'	=> $urlAdd._get($item,$parse['link']),
					'thumb'	=> _get($item,$parse['thumb']),
					'title'	=> _get($item,$parse['title'])
				);
			}
			$pageInfo['pageTotal'] = _get($json,$parse['pageTotal'],$pageInfo['pageTotal']);
			$pageInfo['totalNum']  = _get($json,$parse['totalNum'], $pageInfo['totalNum']);
			return array('list'=>$list,'pageInfo'=>$pageInfo);
		}
		
		if(!$parse['link'] || !$body) return false;
		$this->matchSet('link',$parse,$body,$list,true);	
		$this->matchSet('thumb',$parse,$body,$list,true);
		$this->matchSet('title',$parse,$body,$list,true);

		$this->matchSet('pageTotal',$parse,$body,$pageInfo);
		$this->matchSet('totalNum',$parse,$body,$pageInfo);		
		return array('list'=>$list,'pageInfo'=>$pageInfo);
	}
	
	private function matchSet($key,$parse,$body,&$data,$isList=false){
		$isArr  = in_array($key,array('thumb','title'));
		$reg    = $parse[$key] ? $parse[$key] : ($isArr ? $parse['link']:'');
		$regAt  = $parse[$key.'Reg'];
		$regReplace = $parse[$key.'Replace'];
		if(!$reg && !$regAt) return;

		$match = preg_match_all('/'.$reg.'/',$body,$matchRes);//pr($matchRes);exit;
		if(!$match || !is_array($matchRes[0])) return;
		if(!$isList){ // 单个值处理;
			$regAt = str_replace('{{last}}',count($matchRes[0]) - 1,$regAt);
			$data[$key] = intval(_get($matchRes,$regAt));
			return;
		}
		
		// 多个值处理;
		$listMatch = _get($matchRes,$regAt);
		foreach ($listMatch as $i => $val){
			$value = $val;
			if(!$data[$i]){$data[$i] = array();}
			if(is_array($regReplace) && !$regReplace[0]){
				$value = str_replace($regReplace[1],$regReplace[2],$value);
			}
			if(is_array($regReplace) && $regReplace[0]){
				$value = preg_replace('/'.$regReplace[1].'/',$regReplace[2],$value);
			}
			$data[$i][$key] = $value;
		}
	}
}