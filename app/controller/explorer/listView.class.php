<?php
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/

/**
 * 文件夹首选项(显示模式,排序方式)
 * 跟随文件夹,前端可切换,切换后保持到当前路径配置;并且设置为全局默认值; 保留最近200条记录;
 * 优先级: 指定自己 > 指定最近上级 > 默认
 * -----------------------------------
 * listType 		// list|icon|split 指定显示模式;
 * listSortField 	// name|type|size|modifyTime 指定排序字段;
 * listSortOrder 	// up|down 指定排序方式
 * listIconSize 	// number listType为icon图标模式时图标大小;
 * 
 * 其他文件夹列表接口字段扩展;
 * -----------------------------------
 * listTypeSet 		// list|icon|split 强制指定显示模式; 指定后前端不再能切换显示模式;
 * pageSizeArray 	// [100,200,500,1000,2000,5000] 后端指定分页方式;
 * disableSort		// 0|1 禁用排序;
 * listTypePhoto 	// 0|1 强制相册模式展示;
 */
class explorerListView extends Controller{
	public function __construct(){
		parent::__construct();
		$this->model = Model("Source");
	}

	// 数据处理;
	public function listDataSet(&$data){
		if(!$data || !is_array($data['current'])) return;
		if(isset($data['listTypeSet'])) return;
		
		$listTypeAllow = Model("UserOption")->get('listTypeKeep') != '0';
		$listSortAllow = Model("UserOption")->get('listSortKeep') != '0';
		if(!$listTypeAllow && !$listSortAllow) return;

		$findPath = $this->makePathParents($data['current']);
		if($listTypeAllow){$this->listDataSetListType($findPath,$data);}
		if($listSortAllow){$this->listDataSetListSort($findPath,$data);}
	}
	
	private function listDataSetListType($findPath,&$data){
		$dataListType 	= $this->dataGetListType();
		$listType 		= '';$listIconSize = '';
		foreach($findPath as $thePath){
			if(!isset($dataListType[$thePath])) continue;
			$listType = $dataListType[$thePath];break;
		}
		$listTypeInfo = explode(':',$listType);
		$listType 	  = $listTypeInfo[0];
		if(count($listTypeInfo) == 2 && $listTypeInfo[0] == 'icon'){
			$listIconSize = $listTypeInfo[1];
		}
		if($listType){$data['listType'] = $listType;}
		if($listIconSize){$data['listIconSize'] = $listIconSize;}
	}
	private function listDataSetListSort($findPath,&$data){
		$dataListSort = $this->dataGetSort();
		$sortBy = '';$sortField = '';$sortOrder = '';
		foreach($findPath as $thePath){
			if(!isset($dataListSort[$thePath])) continue;
			$sortBy = $dataListSort[$thePath];break;
		}
		$sortByInfo   = explode(':',$sortBy);
		if(count($sortByInfo) == 2){
			$sortField = $sortByInfo[0];
			$sortOrder = $sortByInfo[1];
		}
		if($sortField){$data['listSortField'] = $sortField;}
		if($sortOrder){$data['listSortOrder'] = $sortOrder;}
	}
	
	// 获取目录所有上级目录;
	private function makePathParents($pathInfo){
		$parents = array();
		if(_get($pathInfo,'sourceID')){
			if(!_get($pathInfo,'parentLevel')){
				$sourceInfo = Model('Source')->sourceInfo($pathInfo['sourceID']);
				$pathInfo['parentLevel'] = $sourceInfo ? $sourceInfo['parentLevel'] : '';
			}
			$parents   = Model("Source")->parentLevelArray($pathInfo['parentLevel']);
			$parents[] = $pathInfo['sourceID'];
		}else{
			$last = '';
			$pathArr = explode('/', rtrim($pathInfo['path'],'/'));
			for ($i = 0; $i < count($pathArr); $i++){
				$last = $last.$pathArr[$i].'/';
				$parents[] = $last;
			}
		}
		return array_reverse($parents);
	}

	private function dataGetListType(){
		$data =  Model("UserOption")->get('listType','folderInfo',true);
		return is_array($data) ? $data : array();
	}
	private function dataGetSort(){
		$data =  Model("UserOption")->get('listSort','folderInfo',true);
		return is_array($data) ? $data : array();
	}
	public function dataSave($param){
		$listType 	= Input::get('listViewKey','in',null,array('listType','listSort'));
		$value 		= Input::get('listViewValue','require');
		$path 		= Input::get('listViewPath','require');

		// 清空数据;
		if(isset($param['clearListView']) && $param['clearListView'] == '1'){
			Model("UserOption")->remove('listType','folderInfo');
			Model("UserOption")->remove('listSort','folderInfo');
			return;
		}

		$listTypeAllow = Model("UserOption")->get('listTypeKeep') != '0';
		$listSortAllow = Model("UserOption")->get('listSortKeep') != '0';
		if(!$listTypeAllow && $listType == 'listType') return;
		if(!$listSortAllow && $listType == 'listSort') return;
		
		$numberMax 	= 200;
		$listData 	= Model("UserOption")->get($listType,'folderInfo',true);
		$storePath 	= $this->parseStorePath($path).'';
		if(!is_array($listData)){$listData = array();}
		if(isset($listData[$storePath])){unset($listData[$storePath]);}
		if(count($listData) >= $numberMax){
			$listData = array_slice($listData,count($listData) - $numberMax + 1,$numberMax - 1,true);
		}
		// trace_log(array($path,$storePath,$value));
		$listData[$storePath] = $value;
		Model("UserOption")->set($listType,$listData,'folderInfo');
	}
	
	private function parseStorePath($path){
		$pathParse = KodIO::parse($path);
		$thePath   = rtrim($path,'/').'/';
		if($pathParse['type'] == '') return $thePath;
		if($pathParse['type'] == KodIO::KOD_SOURCE) return $pathParse['id'];
		if($pathParse['type'] == KodIO::KOD_SHARE_ITEM){
			$shareInfo = Model('Share')->getInfo($pathParse['id']);
			if($shareInfo && $shareInfo['sourceID'] != '0') return trim($pathParse['param'],'/');
		}
		return $thePath;
	}
}