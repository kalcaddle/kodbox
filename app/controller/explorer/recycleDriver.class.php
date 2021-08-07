<?php


/**
 * 物理文件夹删除;
 * 回收站支持处理;
 */
class explorerRecycleDriver extends Controller{
	public function __construct(){
		parent::__construct();
	}
	public function removeCheck($path,$toRecycle=true){
		if(!$toRecycle) return IO::remove($path,$toRecycle);
		$path 		= KodIO::clear($path);
		$pathParse  = KodIO::parse($path);
		$beforePath = get_path_father($path);
		
		$recycleName  = '.recycle/user_'.USER_ID.'/';
		$recycleLocal = rtrim(DATA_PATH,'/').'/'.$recycleName;
		if(!$pathParse['type']){// 物理路径
			$recyclePath = $recycleLocal;
			return $this->moveToRecycle($path,$recyclePath,$beforePath);
		}

		// io路径
		if($pathParse['type'] == KodIO::KOD_IO){
			$recyclePath = rtrim($pathParse['pathBase'],'/').'/'.$recycleName;
			return $this->moveToRecycle($path,$recyclePath,$beforePath);
		}
		
		if($pathParse['type'] == KodIO::KOD_SHARE_ITEM){
			$driver = IO::init($path);
			if($driver->getType() == 'drivershareitem'){
				$pathParseIO = KodIO::parse($driver->path);
				if(!$pathParseIO['type']){// 物理路径
					$recyclePath = $recycleLocal;
					return $this->moveToRecycle($driver->path,$recyclePath,$beforePath);
				}
				// io路径
				if($pathParseIO['type'] == KodIO::KOD_IO){
					$recyclePath = rtrim($pathParse['pathBase'],'/').'/'.$recycleName;
					return $this->moveToRecycle($driver->path,$recyclePath,$beforePath);
				}
			}
		}
		return IO::remove($path,$toRecycle);
	}
	
	/**
	 * 追加物理路径
	 */
	public function appendList(&$data,$pathParse){
		if($pathParse['type'] != KodIO::KOD_USER_RECYCLE) return;
		$list = $this->listData();
		$listNew = $list;
		foreach ($list as $toPath => $fromPath){
			$parse = KodIO::parse($toPath);
			if($parse['driverType'] == 'io') {
				$info = Model('Storage')->driverInfo($parse['id']);
				if(!$info) {
					unset($listNew[$toPath]);
					continue;
				}
			}
			$info = IO::info($toPath);
			if(!$info){
				unset($listNew[$toPath]);
				continue;
			}
			$info['path'] = rtrim($fromPath,'/').'/'.$info['name'];
			if($info['type'] == 'folder'){
				$data['folderList'][] = $info;
			}else{
				$data['fileList'][] = $info;
			}
		}
		
		// 有不存在内容则自动清除;
		if(count($listNew) != count($list)){
			$this->resetList($listNew);
		}
	}
	
	// 彻底删除;
	public function remove($sourceArr){
		$list = $this->listData();
		$listNew = $list;
		foreach ($list as $toPath => $fromPath){
			// 删除所有, 或者当前在待删除列表中则删除该项;
			$beforePath = rtrim($fromPath,'/').'/'.get_path_this($toPath);
			if(!$sourceArr || 
				in_array($beforePath,$sourceArr) || 
				in_array(trim($beforePath,'/').'/',$sourceArr)
			){
				$result = IO::remove($toPath);
				if($result){
					unset($listNew[$toPath]);
				}
			}
		}
		if(count($listNew) != count($list)){
			$this->resetList($listNew);
		}
	}

	// 还原
	public function restore($sourceArr){
		$list = $this->listData();
		$listNew = $list;
		foreach ($list as $toPath => $fromPath){
			// 还原所有, 或者当前在待还原列表中则还原该项;
			$beforePath = rtrim($fromPath,'/').'/'.get_path_this($toPath);
			if(!$sourceArr || 
				in_array($beforePath,$sourceArr) || 
				in_array(trim($beforePath,'/').'/',$sourceArr)
			){
				$result = IO::move($toPath,$fromPath,REPEAT_RENAME_FOLDER);
				if($result){
					unset($listNew[$toPath]);
				}
			}
		}
		if(count($listNew) != count($list)){
			$this->resetList($listNew);
		}
	}
	
	/**
	 * 删除到回收站;
	 * 物理路径: 移动到 TEMP_PATH/.recycle/[user_id]
	 * io路径  : 移动到该io/.recycle 下;
	 */
	private function moveToRecycle($path,$recyclePath,$beforePath){
		if(substr($path,0,strlen($recyclePath)) == $recyclePath){
			return IO::remove($path);//已经在回收站中,则不再处理;
		}
		
		IO::mkdir($recyclePath);
		$toPath = IO::move($path,$recyclePath,REPEAT_RENAME_FOLDER);
		$list = $this->listData();
		$list[$toPath] = $beforePath;
		$this->resetList($list);
		return $toPath;
	}
	private function listData(){
		$list = Model("UserOption")->get('recycleList','recycle');
		return $list ? json_decode($list,true):array();
	}
	private function resetList($list){
		$listData = json_encode($list);
		// options表key=>value value最长长度限制;
		if(strlen($listData) > 65536){
			show_json(LNG('explorer.recycleClearForce'),false);
		}
		Model("UserOption")->set('recycleList',$listData,'recycle');
	}
}
