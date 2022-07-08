<?php
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/
class explorerListFileType extends Controller{
	public function __construct(){
		parent::__construct();
		$this->model = Model("Source");
	}
	
	/**
	 * 文件类型列表
	 */
	public function block(){
		$docType = KodIO::fileTypeList();
		$list	 = array();
		foreach ($docType as $key => $value) {
			$list[$key] = array(
				"name"		=> $value['name'],
				"path"		=> KodIO::makeFileTypePath($key),
				'ext'		=> $value['ext'],
				'extType'	=> $key,
			);
		}
		return $list;
	}
	
	public function get($type,$pathParse){
		if($type == 'photo') return $this->getPhoto($pathParse);
		return $this->model->listPathType($type);
	}
	
	private function getPhoto($pathParse){
		$pageNum = $this->in['pageNum'];
		$data = $this->listData($pathParse);
		$this->groupData($data,$pageNum);
			
		// 设置了分页时按路径保存在本地,不记录服务端; 默认500
		$data['pageSizeArray'] 	= array(100,200,500,1000,2000,5000); 
		$data['disableSort'] 	= 1;	// 禁用客户端排序;
		$data['listTypePhoto']  = 1; 	// 强制显示图片模式;
		$data['listTypeSet'] 	= 'icon'; 	// 强制显示模式;
		return $data;
	}
		
	// 默认个人空间; 可扩展到任意文件夹相册模式;
	private function listData($pathParse){
		$search   = Action('explorer.listSearch');
		$sizeFrom = 100;
		$homePath = MY_HOME;
		$fileType = 'jpg,jpeg,png,gif,bmp,heic,webp,mov,mp4';// livp;
		$option   = json_decode(Model("UserOption")->get('photoConfig'),true);
		if(is_array($option)){
			if(isset($option['pathRoot'])){$homePath = $option['pathRoot'];}
			if(isset($option['fileSize'])){$sizeFrom = intval($option['fileSize']) * 1024;}
			if(isset($option['fileType'])){$fileType = $option['fileType'];}
		}

		$thePath = rtrim($pathParse['param'],'/');
		$thePath = $thePath ? $thePath : $homePath;
		if(substr($thePath,0,2) == '/{'){$thePath = substr($thePath,1);}
		if(!Action('explorer.auth')->canView($thePath)){
			show_json(LNG('explorer.noPermissionAction'),false);	
		}
		
		$this->in['pageNum'] = 20000; // 最多查询数量;
		$param  = array('parentPath'=>$thePath,'fileType'=>$fileType);
		if($sizeFrom > 0){$param['sizeFrom'] = $sizeFrom;}
		$param['parentID']  = $search->searchPathSource($thePath);
		$result = $search->searchData($param);
		$result['current'] = $this->photoCurrent($pathParse,$thePath);
		return $result;
	}
	private function photoCurrent($pathParse,$thePath){
		$option   	 = json_decode(Model("UserOption")->get('photoConfig'),true);
		$thePath     = rtrim($pathParse['param'],'/');
		$pathAddress = array(array('name' =>LNG('explorer.toolbar.photo'),'path'=>$pathParse['path']));
		$pathDesc    = LNG('explorer.photo.desc');
		if($thePath){
			$thePath = substr($thePath,0,2) == '/{' ? ltrim($thePath,'/') : $thePath;
			$info = IO::info($thePath);
			$pathAddress[0]['name'] = LNG('explorer.toolbar.folder').' - '.$info['name'];
			$pathAddress[] = array('name' =>trim($info['pathDisplay'],'/'),'path'=>$thePath);
			$pathDesc = $info['pathDisplay'] ? $info['pathDisplay']: $info['path'];
		}else if(is_array($option)){
			if(isset($option['pathRootShow'])){$pathDesc .= '<br/>'.LNG('explorer.photo.pathRoot').': '.$option['pathRootShow'];}
		}
		if(is_array($option)){
			if(isset($option['fileType'])){$pathDesc .= '<br/>'.LNG('explorer.photo.fileType').': '.$option['fileType'];}
		}
		
		$result = array(
			'path' 		=> $pathParse['path'],
			'name' 		=> $pathAddress[0]['name'],
			'pathDesc' 	=> $pathDesc,
			'type' 		=> 'folder',
			'pathAddress' => $pathAddress,
		);
		return $result;
	}
	
	
	// 数据分组
	private function groupData(&$data,$pageNum){
		$groupBy 	= Input::get('photoListBy','in','month',array('year','month','day'));// 分组类型
		$pageNum 	= $pageNum <= 100 ? 100 : $pageNum;
		$fileList 	= $this->resetImageTime($data['fileList']);
		$pageTotal  = ceil(count($fileList) / $pageNum);
		$page 		= isset($this->in['page']) ? intval($this->in['page']) : 1;
		$page 		= $page <= 1 ? 1 : ($page >= $pageTotal ? $pageTotal : $page); 

		$groupArray = array();
		$listPage   = array_slice($fileList,$pageNum * ($page - 1),$pageNum);
		$groupTypeArr = array(
			'year'  => array('Y','-01-01 00:00:00',' +1 year'),
			'month' => array('Y-m','-01 00:00:00', ' +1 month'),
			'day'   => array('Y-m-d',' 00:00:00',  ' +1 day'),
		);
		$groupType = $groupTypeArr[$groupBy];
		
		foreach($listPage as $file){
			$key = date($groupType[0],$file['modifyTime']);
			if(!isset($groupArray[$key])){
				$timeStart = strtotime($key.$groupType[1]);
				$timeTo    = strtotime(date('Y-m-d H:i:s',$timeStart).$groupType[2]);				
				$groupArray[$key] = array(
					'type' 	=> 'photo-group-'.$timeStart,
					'title' => $key,
					"desc"  => '', 'count'=> 0,
					"filter"=> array('modifyTime'=>array('>'=> $timeStart,'<'=> $timeTo)),
				);
			}
			$groupArray[$key]['count'] += 1;
			$groupArray[$key]['desc']   = $groupArray[$key]['count'] .' '. LNG('common.items');
		}
		// pr($groupArray,$page,$pageTotal);exit;
		$data['fileList']  = $listPage;
		$data['groupShow'] = array_values($groupArray);
		$data['pageInfo']  = array('page'=>$page,'pageTotal'=>$pageTotal,'totalNum'=>count($fileList),'pageNum'=>$pageNum);
	}
	
	// 图片时间处理, 优先级: 拍摄时间>本地最后修改时间>上传时间
	private function resetImageTime($fileList){
		foreach($fileList as &$file){
			if(is_array($file['fileInfoMore']) && isset($file['fileInfoMore']['createTime'])){
				$file['modifyTime'] = strtotime($file['fileInfoMore']['createTime']);
				if($file['modifyTime']){continue;}
			}
			if(is_array($file['metaInfo']) && isset($file['metaInfo']['modifyTimeLocal'])){
				$file['modifyTime'] = intval($file['metaInfo']['modifyTimeLocal']);
				continue;
			}
			// $file['imageTime'] = intval($file['modifyTime']);
		};unset($file);
		$fileList = array_sort_by($fileList,'modifyTime',true);
		return $fileList;
	}
}