<?php
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/
class explorerListDriver extends Controller{
	public function __construct(){
		parent::__construct();
	}
	
	/**
	 * 用户存储挂载列表
	 */
	public function get(){
		if(KodUser::isRoot()) return $this->rootList();
		return false;//普通用户挂载暂不支持;
	}
	
	
	private function rootList(){
		$GLOBALS['STORE_WITH_SIZEUSE'] = true;
		$dataList = Model('Storage')->listData();
		unset($GLOBALS['STORE_WITH_SIZEUSE']);
		$list = array();
		
		if($GLOBALS['config']['systemOption']['systemListDriver'] == '1'){
			$diskList = KodIO::diskList(false);
			foreach ($diskList as $path) {
				$this->driverMake($list,$path);
			}
		}
		foreach ($dataList as $item) {
			$list[] = array(
				"name"			=> $item['name'],
				"path"			=> '{io:'.$item['id'].'}/',
				"size"			=> $item['sizeUse'],
				"driverSpace"	=> $item['sizeMax']*1024*1024*1024,
				"driverDefault" => $item['default'],
				"driverType"	=> $item['driver'],
				"icon" 			=> 'io-'.strtolower($item['driver']),
				'isParent'		=> true,
			);
		}
		$result = array('folderList' => $list, 'fileList'=>array());
		$this->driverListGroup($result);
		return $result;
	}
	
	// 对同一类型有多个存储的进行归类;
	private function driverListGroup(&$result){
		$notEqual  = array('!='=>'1');
		$groupShow = array(
			array(
				'type' 	=> 'io-type-default',
				'title' => LNG('admin.storage.current'),
				"filter"=> array('driverDefault'=>array('='=>'1')),
			)
		);
		if(count($result['folderList']) <= 5){
			$groupShow[] = array(
				'type' 	=> 'io-type-others',
				'title' => LNG('common.others'),
				"filter"=> array('driverDefault'=>$notEqual),
			);
			$result['groupShow'] = $groupShow;
			return;
		}
		
		$groupMinNumber = 3; // 超过数量才显示分组,否则归类到其他;
		$driverOthers   = array();
		$listGroup = array_to_keyvalue_group($result['folderList'],'driverType');
		foreach ($listGroup as $key=>$val){
			if(count($val) < $groupMinNumber){
				$driverOthers[] = $key;continue;
			}
			$langKey = 'admin.storage.'.strtolower($key);
			$groupShow[] = array(
				'type' 	=> 'io-type-'.$key,
				'title' => LNG($langKey) != $langKey ? LNG($langKey) : $key,
				"filter"=> array('ioDriver'=>array('='=> $key),'driverDefault'=>$notEqual),
			);
		}
		if(count($driverOthers) > 0){
			$groupShow[] = array(
				'type' 	=> 'io-type-others',
				'title' => LNG('common.others'),
				"filter"=> array('ioDriver'=>array('in'=>$driverOthers),'driverDefault'=>$notEqual),
			);
		}
		$result['groupShow'] = $groupShow;
	}
	
	public function parsePathIO(&$info,$current=false){
		if(substr($info['path'],0,4) != '{io:') return $info;
		static $driverList = false;
		if ($driverList === false) {
			$list = Model('Storage')->driverListSystem();
			$driverList = array_to_keyvalue($list,'id');
		}
		if(!$driverList) return $info;
		
		$isFavPath = false;
		if(is_array($current) && isset($current['path'])){
			$isFavPath = trim($current['path'],'/') == KodIO::KOD_USER_FAV;
		}
		
		$parse = KodIO::parse($info['path']);
		$storage = $driverList[$parse['id']];
		if(!$storage) return $info;

		$storageName = str_replace("/",'-',$storage['name']);
		$info['isReadable']   = array_key_exists('isReadable',$info)  ? $info['isReadable']  : true;
		$info['isWriteable']  = array_key_exists('isWriteable',$info) ? $info['isWriteable'] : true;
		$info['pathDisplay']  = str_replace($parse['pathBase'],$storageName,$info['path']);
		$langKey = 'admin.storage.'.strtolower($storage['driver']);
		$info['ioType'] = LNG($langKey) != $langKey ? LNG($langKey) : $storage['driver'];
		$info['ioDriver'] = $storage['driver'];
		$info['ioIsSystem'] = (isset($storage['default']) && $storage['default'] == 1);

		// 根目录;
		$thePath = trim($parse['param'],'/');
		if( (!$thePath && $thePath !== '0') || $isFavPath ){
			$info['name'] = $storageName;
			if($isFavPath){
			    $info['name'] = $info['sourceInfo']['favName'];
			}
			$info['icon'] = 'io-'.strtolower($storage['driver']);
			if(isset($storage['config']['domain'])){
				$info['ioDomain'] = $storage['config']['domain'];
			}
			if(isset($storage['config']['bucket'])){
				$info['ioBucket'] = $storage['config']['bucket'];
			}
			if(isset($storage['config']['basePath'])){
				$info['ioBasePath'] = $storage['config']['basePath'];
			}
		}
		// pr($storage,$parse,$info,$driverList);exit;
		return $info;
	}
	public function parsePathChildren(&$info,$current){
		if($info['type'] == 'file' || isset($info['hasFolder']) ) return $info;	
		$ioAllow = array('Local');// array('Local','MinIO')
		$pathParse = KodIO::parse($current['path']);
		$isLocal = $pathParse['type'] ? false:true;
		$isIoAllow = isset($current['ioType']) && in_array($current['ioType'],$ioAllow);
		if($pathParse['type'] == KodIO::KOD_BLOCK && $pathParse['id'] != 'driver') return $info;

		$infoMore = array('hasFolder'=>true,'hasFile'=> true);
		if($isLocal || $isIoAllow){
			$infoMore = IO::has($info['path'],1);
		}else if($pathParse['type'] == KodIO::KOD_USER_FAV){
			$itemParse = KodIO::parse($info['path']);
			if(!$itemParse['type']){
				$infoPath = IO::info($info['path']);
				if(!$infoPath){
					$infoMore = array('exists'=>false);
				}else{
					$infoMore = IO::has($info['path'],1);
					$infoMore = is_array($infoMore) ? $infoMore : array();
					$infoMore = array_merge($infoPath,$infoMore);
				}
			}
		}
		if(is_array($infoMore)){
			unset($infoMore['name']);
			$info = array_merge($info,$infoMore);
		}
		return $info;
	}

	private function driverMake(&$list,$path){
		if(!file_exists($path)) return;
		if(!function_exists('disk_total_space')){return;}
		$total  = @disk_total_space($path);
		$list[] = array(
			"name"			=> LNG('admin.storage.driver')."($path)",
			"path"			=> $path,
			"size"			=> $total - @disk_free_space($path),
			"driverSpace"	=> $total,
			"driverType"	=> 'Local',
			"ioType" 		=> LNG("admin.storage.driver"),
			"ioDriver"		=> "Local",			
			"icon" 			=> 'io-driver',
			'isParent'		=> true,
		);
	}
}