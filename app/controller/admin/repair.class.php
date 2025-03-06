<?php
/**
 * 数据清理处理
 * autoReset;			// 清空修复异常数据
 * resetSourceEmpty		// source中表清理; sourceHash为空或所属关系错误的条目删除;
 * resetShareTo			// share_to中存在share中不存在的数据清理
 * resetShare			// share中存在,source已不存在的内容清理
 * resetSourceFile		// source中的文件fileID,file中不存在清理;
 * resetFileSource		// file中存在,source中不存在的进行清理
 * resetSourceHistory	// 文件历史版本,fileID不存在的内容清理;
 * resetFileLink		// 重置fileID的linkCount引用计数(source,sourceHistory);
 * clearSameFile		// 清理重复的文件记录
		
 * sql清理操作日志: 
 * delete from `system_log` where createTime < UNIX_TIMESTAMP('2023-03-01 00:00:00')
 */
class adminRepair extends Controller {
	function __construct()    {
		parent::__construct();
		$this->resetPathKey = '';
		$this->pageCount    = 20000;// 分页查询限制; 默认5000, 20000;
	}
	
	/**
	 * 清空修复异常数据(php终止,断电,宕机等引起数据库表错误的进行处理;)
	 * 6个小时执行一次;
	 * 
	 * 手动执行:
	 * /?admin/repair/autoReset&done=1/2,done=1为清理数据;done=2为实际删除不存在的文件记录
	 */
	public function autoReset(){
		$done = isset($this->in['done']) ? intval($this->in['done']) : 0;
		if ($done == 2) {	// 计划任务执行
			$msg = $this->resetPathSource();
			echoLog('异常数据清理,'.$msg.'可在后台任务管理进行中止.');
		} else {
			$cacheKey = 'autoReset';
			$lastTime = Cache::get($cacheKey);
			$lastTime = false;//debug ;
			if($lastTime && time() - intval($lastTime) < 3600*6 ){
				echo '最后一次执行未超过6小时!';return;
			}
			Cache::set($cacheKey,time());
			echoLog('异常数据清理,可在后台任务管理进行中止.');
		}
		// echoLog('请求参数done=1时,不直接删除已缺失的物理文件,可查看“物理文件不存在的数据记录”,确认需要删除后,再执行done=2进行删除');
		echoLog('=====================================================');
		if ($done == 2) {return $this->clearErrorFile();}
		// http_close();

		$this->resetSourceEmpty();		// source中表清理; sourceHash为空或所属关系错误的条目删除;
		$this->resetShareTo();			// share_to中存在share中不存在的数据清理
		$this->resetShare();			// share中存在,source已不存在的内容清理
		$this->resetSourceFile();		// source中的文件fileID,file中不存在清理;
		$this->resetFileSource();		// file中存在,source中不存在的进行清理
		$this->resetSourceHistory();	// 文件历史版本,fileID不存在的内容清理;
		$this->resetFileLink();			// 重置fileID的linkCount引用计数(source,sourceHistory);
		$this->clearSameFile();			// 清理重复的文件记录
		write_log('异常数据清理完成!','sourceRepair');
		echoLog('=====================================================');
		echoLog('异常数据清理完成!');
	}
	
	public function clearEmptyFile(){
		Model('File')->clearEmpty(0);
		pr('ok');
	}

	// 处理指定目录数据
	private function resetPathSource(){
		if (!isset($this->in['path'])) return '';
		$path	= $this->in['path'];
		$parse	= KodIO::parse($path);
		$info	= IO::infoSimple($path);
		$id 	= $parse['id'];
		if (!$id || !$info || $info['isFolder'] != 1) {
			echoLog('指定目录参数错误: path='.$path);exit;
		}
		// 获取指定目录下子文件
		$pathLevel = $info['parentLevel'].$id.',';
		$where	= array(
			'isFolder' => 0,
			'parentLevel' => array('like', $pathLevel.'%')
		);
		$result = Model("Source")->where($where)->select();
		if (!$result) {
			echoLog('指定目录下没有待处理数据: path='.$path);exit;
		}
		$this->resetPathKey = 'repair.reset.path.'.$id;
		Cache::remove($this->resetPathKey);

		$source = $file = array();
		foreach ($result as $item) {
			$source[] = $item['sourceID'];
			$file[] = $item['fileID'];
		}
		$cache = array(
			'source'=> array_filter(array_unique($source)),
			'file'	=> array_filter(array_unique($file)),
		);
		Cache::set($this->resetPathKey, $cache);
		return '执行目录: '.$path.',';
	}
	private function pathWhere($model, $file=false, $shareTo=false) {
		if (!$this->resetPathKey) return;
		$cache = Cache::get($this->resetPathKey);
		if (!$cache) {
			echoLog('缓存数据异常,请尝试重新执行!');exit;
		}
		$key = $file ? 'file' : 'source';
		$ids = $cache[$key];
		$where = array($key.'ID'=>array('in', $ids));
		if ($shareTo) {
			$list	= Model('Share')->where($where)->select();
			if (!$list) {
				$where = array('shareID'=>0);
			} else {
				$ids	= array_to_keyvalue($list, '', 'shareID');
				$ids	= array_filter(array_unique($ids));
				$where	= array('shareID'=>array('in',$ids));
			}
		}
		$model->where($where);
	}
	
	/**
	 * 清除已不存在的物理文件记录
	 * 需先执行autoReset方法,并查看sourceRepair日志【resetFileLink--已不存在的物理文件】,确认是否需要清除
	 * @return void
	 */
	public function clearErrorFile(){
		$cache = Cache::get('clear_file_'.date('Ymd'));
		if (!$cache || !is_array($cache)) {
			echoLog('没有缺失的物理文件记录!');
			echoLog('注意:此记录从缓存中获取,缓存数据在执行done=1时产生,因此请务必先执行done=1.');
			exit;
		}
		echoLog('clearErrorFile,物理文件不存在的数据处理;');
		$model = Model('File');
		$modelSource = Model("Source");$modelHistory = Model("SourceHistory");
		$result = array('file' => 0, 'source' => 0);
		foreach ($cache as $item) {
			$rest = $this->delFileNone($model, $modelSource, $modelHistory, $item);
			$result['file'] += $rest['file'];
			$result['source'] += $rest['source'];
			echoLog('file:'.$result['file'].';source:'.$result['source'],true);
		}
		Cache::remove('clear_file_'.date('Ymd'));
		echoLog('clearErrorFile,finished:清除已不存在的物理文件记录共'.$result['file'].'条,涉及source记录'.$result['source'].'条!');
		exit;
	}

	/**
	 * source表中异常数据处理: 
	 * 1. parentID为0, 但 parentLevel不为0情况处理;
	 * 2. parentID 不存在处理;
	 * 3. sourchHash 为空的数据;
	 */
	public function resetSourceEmpty(){
		$taskID ='resetSourceEmpty';$pageNum = $this->pageCount;$page = 1;$changeNum = 0;
		$model = Model("Source");
		$this->pathWhere($model);
		$list = $model->selectPage($pageNum,$page);
		
		$task = TaskLog::newTask($taskID,'source表异常数据处理',$list['pageInfo']['totalNum']);
		while($list && $page <= $list['pageInfo']['pageTotal']){
			$parentSource = $removeSource = $removeFiles = array();
			foreach ($list['list'] as $item) {
				$levelEnd = ','.$item['parentID'].',';
				$levelEndNow = substr($item['parentLevel'],- strlen($levelEnd));
				if( $item['sourceHash'] == '' || $levelEndNow != $levelEnd ){
					$changeNum++;write_log(array($taskID,$item),'sourceRepair');
					$task->task['desc'] = $task->task['currentTitle'] = $changeNum.'个不存在';
					$parentSource[] = $item['parentID'];
					$removeSource[] = $item['sourceID'];
					$removeFiles[]  = $item['fileID'];
				}
				$task->update(1);
			}
			$model->removeRelevance($removeSource,$removeFiles); // 优化性能;
			$this->folderSizeReset($parentSource);
			$this->pathWhere($model);$page++;
			$list = $model->selectPage($pageNum,$page);
		}
		$task->end();
	}
	
	// source对应fileID 不存在处理;
	public function resetSourceFile(){
		$taskID ='resetSourceFile';$pageNum = $this->pageCount;$page = 1;$changeNum = 0;
		$model = Model("Source");$modelFile = Model("File");
		$this->pathWhere($model);
		$list = $model->selectPage($pageNum,$page);

		$task = TaskLog::newTask($taskID,'source表空数据处理',$list['pageInfo']['totalNum']);
		while($list && $page <= $list['pageInfo']['pageTotal']){
			$parentSource = $removeSource = $removeFiles = array();
			foreach ($list['list'] as $item) {
				if($item['isFolder'] == '0' && !$modelFile->find($item['fileID'])){
					$changeNum++;write_log(array($taskID,$item),'sourceRepair');
					$task->task['desc'] = $task->task['currentTitle'] = $changeNum.'个不存在';
					$parentSource[] = $item['parentID'];
					$removeSource[] = $item['sourceID'];
					$removeFiles[]  = $item['fileID'];
				}
				$task->update(1);
			}
			$model->removeRelevance($removeSource,$removeFiles); // 优化性能;
			$this->folderSizeReset($parentSource);
			$this->pathWhere($model);$page++;
			$list = $model->selectPage($pageNum,$page);
		}
		$task->end();
	}

	// 重置文件夹大小
	private function folderSizeReset($parentSource){
		$model = Model("Source");
		$parentSource = array_filter(array_unique($parentSource));
		foreach ($parentSource as $sourceID) {
			$model->folderSizeReset($sourceID);
		}
	}

	public function resetFileHash(){
		$taskID ='resetFileHash';$pageNum = $this->pageCount;$page = 1;$changeNum = 0;
		$model  = Model('File');
		$this->pathWhere($model, true);
		$list = $model->selectPage($pageNum,$page);
		
		$task = TaskLog::newTask($taskID,'更新文件hash',$list['pageInfo']['totalNum']);
		while($list && $page <= $list['pageInfo']['pageTotal']){
			foreach ($list['list'] as $item) {
				if(!$item['hashSimple'] || !$item['hashMd5']){
					$data = array('hashSimple'=>IO::hashSimple($item['path']) );
					if(!$item['hashMd5']){$data['hashMd5'] = IO::hashMd5($item['path']);}
					
					$changeNum++;write_log(array($taskID,$item),'sourceRepair');
					$task->task['desc'] = $task->task['currentTitle'] = $changeNum.'个修改';
					$model->where(array('fileID'=>$item['fileID']))->save($data);
				}
				$task->update(1);
			}
			$this->pathWhere($model, true);$page++;
			$list = $model->selectPage($pageNum,$page);
		}
		$task->end();
	}
	
	// 重置分享,内部协作分享数据;(删除文件夹,删除对应分享,及内部协作分享)
	public function resetShareTo(){
		$taskID ='resetShareTo';$pageNum = $this->pageCount;$page = 1;$changeNum = 0;
		$model  = Model('share_to');$modelShare = Model("share");
		$this->pathWhere($model, false, true);
		$list = $model->selectPage($pageNum,$page);
		
		$task = TaskLog::newTask($taskID,'重置内部协作数据',$list['pageInfo']['totalNum']);
		while($list && $page <= $list['pageInfo']['pageTotal']){
			foreach ($list['list'] as $item) {
				$where = array("shareID"=>$item['shareID']);
				if(!$modelShare->where($where)->find()){
					$changeNum++;write_log(array($taskID,$item),'sourceRepair');
					$task->task['desc'] = $task->task['currentTitle'] = $changeNum.'个不存在';
					$model->where($where)->delete();
				}
				$task->update(1);
			}
			$this->pathWhere($model, false, true);$page++;
			$list = $model->selectPage($pageNum,$page);
		}
		$task->end();
	}
	
	// 重置分享,内部协作分享数据;(删除文件夹,删除对应分享,及内部协作分享)
	public function resetShare(){
		$taskID ='resetShare';$pageNum = $this->pageCount;$page = 1;$changeNum = 0;
		$model = Model('share');$modelSource = Model("Source");
		$this->pathWhere($model);
		$list = $model->selectPage($pageNum,$page);

		$task = TaskLog::newTask($taskID,'重置分享数据',$list['pageInfo']['totalNum']);
		while($list && $page <= $list['pageInfo']['pageTotal']){
			foreach ($list['list'] as $item) {
				$where = array("sourceID"=>$item['sourceID']);
				if($item['sourceID'] != '0' && !$modelSource->where($where)->find()){
					$changeNum++;write_log(array($taskID,$item),'sourceRepair');
					$task->task['desc'] = $task->task['currentTitle'] = $changeNum.'个不存在';
					
					$where = array('shareID'=>$item['shareID']);
					$model->where($where)->delete();
					Model('share_to')->where($where)->delete();
				}
				$task->update(1);
			}
			$this->pathWhere($model);$page++;
			$list = $model->selectPage($pageNum,$page);
		}
		$task->end();
	}

	// file表中存在, source表中不存在的进行清除;历史记录表等;
	public function resetFileSource(){
		$taskID ='resetFileSource';$pageNum = $this->pageCount;$page = 1;$changeNum = 0;
		$model = Model("File");$modelSource = Model("Source");
		$modelHistory = Model('SourceHistory');;
		$this->pathWhere($model, true);
		$list = $model->selectPage($pageNum,$page);
		$stores = Model('Storage')->listData();
	    $stores = array_to_keyvalue($stores, '', 'id');	// 有效存储列表

		$task = TaskLog::newTask($taskID,'Source记录异常处理',$list['pageInfo']['totalNum']);
		while($list && $page <= $list['pageInfo']['pageTotal']){
			foreach ($list['list'] as $item) {
				$where = array("fileID"=>$item['fileID']);
				$findSource  = $modelSource->where($where)->find();
				$findHistory = $modelHistory->where($where)->find();
				if(!$findSource && !$findHistory){
					$changeNum++;write_log(array($taskID,$item),'sourceRepair');
					$task->task['desc'] = $task->task['currentTitle'] = $changeNum.'个不存在';
					// if (in_array($item['ioType'], $stores)) {IO::remove($item['path']);}
					$model->where($where)->delete();
				}
				$task->update(1);
			}
			$this->pathWhere($model, true);$page++;
			$list = $model->selectPage($pageNum,$page);
		}
		$task->end();
	}
	
	// File表中,io不存在的文件进行处理;（被手动删除的）
	public function resetFileLink(){
		$taskID ='resetFileLink';$pageNum = $this->pageCount;$page = 1;$changeNum = 0;
		$model = Model('File');
		$this->pathWhere($model, true);
		$list = $model->selectPage($pageNum,$page);
		$stores = Model('Storage')->listData();
	    $stores = array_to_keyvalue($stores, '', 'id');	// 有效存储列表

		$cache = array();$rest = array('file' => 0, 'source' => 0);
		$task  = TaskLog::newTask($taskID,'重置清理File表引用',$list['pageInfo']['totalNum']);
		while($list && $page <= $list['pageInfo']['pageTotal']){
			foreach ($list['list'] as $item) {
				$ioNone = in_array($item['ioType'], $stores);
				if($ioNone && IO::exist($item['path']) ){
					$model->resetFile($item);
				}else{
					$changeNum++;write_log(array($taskID.'--已不存在的物理文件',$item),'sourceRepair');
					$task->task['desc'] = $task->task['currentTitle'] = $changeNum.'个不存在'.';'.$item['path'];
					$cache[] = array(
						'fileID'	=> $item['fileID'],
						'linkCount' => $item['linkCount'],
					);
				}
				$task->update(1);
			}
			$this->pathWhere($model, true);$page++;
			$list = $model->selectPage($pageNum,$page);
		}
		$task->end();
		if($cache) Cache::set('clear_file_'.date('Ymd'), $cache);
		return $rest;
	}
	// 删除不存在的物理文件
	private function delFileNone($model, $modelSource, $modelHistory, $item){
		$where = array("fileID"=>$item['fileID']);
		$list  = $modelSource->where($where)->select();
		$cnt1  = $modelSource->where($where)->delete();
		$modelHistory->where($where)->delete();

		$model->metaSet($item['fileID'],null,null);
		$cnt2 = $model->where(array('fileID'=>$item['fileID']))->delete();
		
		// 重置父目录大小
		$list = array_to_keyvalue($list, '', 'parentID');
		$this->folderSizeReset($list);
		return array('source' => intval($cnt1),'file'=> intval($cnt2));
	}

	public function resetSourceHistory(){
		$taskID ='resetSourceHistory';$pageNum = $this->pageCount;$page = 1;$changeNum = 0;
		$model = Model('SourceHistory');$modelSource = Model("Source");
		$modelFile = Model("File");
		$this->pathWhere($model);
		$list = $model->selectPage($pageNum,$page);

		$task = TaskLog::newTask($taskID,'历史版本异常数据处理',$list['pageInfo']['totalNum']);
		while($list && $page <= $list['pageInfo']['pageTotal']){
			foreach ($list['list'] as $item) {
				$where = array("sourceID"=>$item['sourceID']);
				if( !$modelSource->where($where)->find() ){
					$changeNum++;write_log(array($taskID,$item),'sourceRepair');
					$task->task['desc'] = $task->task['currentTitle'] = $changeNum.'个不存在';
					$model->where($where)->delete();
				}
				if( !$modelFile->where(array('fileID'=>$item['fileID']))->find()){
					$changeNum++;write_log(array($taskID.',fileError!',$item),'sourceRepair');
					$task->task['desc'] = $task->task['currentTitle'] = $changeNum.'个不存在';	
					$model->where(array('fileID'=>$item['fileID']))->delete();
				}
				$task->update(1);
			}
			$this->pathWhere($model);$page++;
			$list = $model->selectPage($pageNum,$page);
		}
		$task->end();
	}
	
	// 文件列表自然排序,文件名处理; 升级向下兼容数据处理;
	public function sourceNameInit(){
		// Model("SystemOption")->set('sourceNameSortFlag','');
		if(Model("SystemOption")->get('sourceNameSortFlag')) return;
		$this->sourceNameSort();
	}
	public function sourceNameSort(){
		$taskID ='sourceNameSort';$pageNum = $this->pageCount;$page = 1;$changeNum = 0;
		Model("SystemOption")->set('sourceNameSortFlag','1');
		$model = Model('Source');$modelMeta = Model("io_source_meta");
		$pageNum = $this->pageCount;$page = 1;$changeNum = 0;
		$model->selectPageReset();
		$list = $model->field('sourceID,name')->selectPage($pageNum,$page);
		
		$task = TaskLog::newTask($taskID,'更新Source排序名',$list['pageInfo']['totalNum']);
		while($list && $page <= $list['pageInfo']['pageTotal']){
			$metaAdd = array();
			foreach ($list['list'] as $item){
				if(!$item['name']) continue;
				$metaAdd[] = array(
					'sourceID' 	=> $item['sourceID'],
					'key'		=> 'nameSort',
					'value'		=> KodSort::makeStr($item['name']),
				);
				$task->update(1);
				if(count($metaAdd) >= 1000){
					$modelMeta->addAll($metaAdd,array(),true);$metaAdd = array();
				}
			}
			if(count($metaAdd) > 0){
				$modelMeta->addAll($metaAdd,array(),true);$metaAdd = array();
			}
			$page++;
			$list = $model->field('sourceID,name')->selectPage($pageNum,$page);
		}
		Model("SystemOption")->set('sourceNameSortFlag','2');
		$model->selectPageRestore();
		$task->end();
	}

	/**
	 * 根据sourceID彻底删除文件,sourceID可传多个,如sourceID=1,2,3
	 * @return void
	 */
	public function clearSource(){
		echoLog('根据sourceID彻底删除关联文件!参数sourceID=1,2,3');
		$ids = $this->in['sourceID'];
		if (!$ids) {
			echoLog('无效的参数:sourceID!');exit;
		}
		// 1.根据sourceID查fileID
		$ids = array_filter(explode(',',$ids));
		$where = array(
			'isFolder' => 0,
			'sourceID' => array('in', $ids)
		);
		$list = Model('Source')->where($where)->field('sourceID,fileID')->select();
		if (empty($list)) {
			echoLog('找不到对应的source记录,请检查sourceID是否正确.');
			exit;
		}

		echoLog('删除开始:');
		$ids  = array_to_keyvalue($list, '', 'sourceID');
		$file = array_to_keyvalue($list, '', 'fileID');
		$file = array_filter($file);
		$fCnt = count($file);
		// 2.根据fileID查所有sourceID
		if (!empty($file)) {
			$where = array('fileID'=>array('in', $file));
			$list = Model('Source')->where($where)->field('sourceID')->select();
			$ids = array_to_keyvalue($list, '', 'sourceID');
		}
		$ids  = array_filter($ids);
		$sCnt = count($ids);
		// 3.根据sourceID删除文件
		foreach ($ids as $i => $id) {
			$path = KodIO::make($id);
			IO::remove($path, false);
			echoLog('source记录:'.($i+1), true);
		}
		// 4.删除可能还存在的file记录——实际物理文件删除与否不影响
		if (!empty($file)) {
			$where = array('fileID'=>array('in', $file));
			Model('File')->where($where)->delete();
		}
		echoLog("删除完成!共删除source记录{$sCnt}条;file记录{$fCnt}条.");
	}
	
	public function resetSize(){
		$id = $this->in['sourceID'];
		if(!$id) return;
		model('Source')->folderSizeResetChildren($id);
		echoLog("更新完成!");
	}
	
	// 重复文件清理; 根据hashMd5处理;
	public function clearSameFile(){
		$taskID ='clearSameFile';$pageNum = $this->pageCount;$page = 1;$changeNum = 0;
		$list = Model()->query('select hashMd5,count(1) from io_file group by hashMd5 having count(hashMd5)>1;');
		$list = is_array($list) ? $list : array();
		$modelFile = Model("File");
		
		$task = TaskLog::newTask($taskID,'重复文件清理',count($list));
		foreach ($list as $item) {
			if(!$item['hashMd5'] || $item['hashMd5'] == '0') continue;
			$where = array("hashMd5"=>$item['hashMd5']);
			
			$files = $modelFile->field('fileID,path,linkCount')->where($where)->order('fileID asc')->select();
			$files = is_array($files) ? $files : array();
			$fileRemove = array();$linkCount = 0;
			foreach ($files as $i=>$file){
				if($i == 0) continue;
				$linkCount += intval($file['linkCount']);
				$fileRemove[] = $file['fileID'];
				if($file['path'] && $file['path'] != $files[0]['path']){
					IO::remove($file['path']);
				}
			}
			if($fileRemove){
				$fileID = $files[0]['fileID'];
				$linkCount += intval($files[0]['linkCount']);
				$fileWhere = array('fileID'=>array('in',$fileRemove));
				$save = array('fileID'=>$fileID);
				Model("Source")->where($fileWhere)->save($save);
				Model("SourceHistory")->where($fileWhere)->save($save);
				Model("share_report")->where($fileWhere)->save($save);
				
				Model("io_file_meta")->where($fileWhere)->delete();
				Model("io_file_contents")->where($fileWhere)->delete();
				Model("io_file_meta")->where($fileWhere)->delete();
				$modelFile->where($fileWhere)->delete();
				$modelFile->where(array('fileID'=>$fileID))->save(array('linkCount'=>$linkCount));
				
				$changeNum++;write_log(array($taskID,$item),'sourceRepair');
				$task->task['desc'] = $task->task['currentTitle'] = $changeNum.'个修改';
			}
			$task->update(1);
		}
		$task->end();
	}

	/**
	 * 清除用户回收站文件
	 * /?admin/repair/clearUserRecycle&limit=100&days=3&userID=1
	 * @return void
	 */
	public function clearUserRecycle() {
		$limit  = intval(_get($this->in, 'limit', 100));
        $days   = intval(_get($this->in, 'days', 3));
        $userID = intval(_get($this->in, 'userID', 0));

        $cckey  = 'clearRecycleFiles-'.implode('-', array($limit, $days, $userID));
        $data	= Cache::get($cckey);

        // 获取待清理列表
        if (!isset($this->in['done']) || !$data) {
            $data = $this->getRecycleList($limit, $days, $userID);
        }

        // 标题
        $title = '清理用户回收站，筛选条件：';
        if ($limit) $title .= '回收站文件超过'.$limit.'个的';
        if ($userID) {
            $title .= '指定用户(userID='.$userID.')';
        } else {
            $title .= '所有用户';
        }
        $title .= '的，回收站中';
        if ($days) {
            $title .= $days.'天前删除的文件';
        } else {
            $title .= '所有的文件';
        }
        echoLog($title.'。');

        // 清理确认
        if (!$data) {
            echoLog('符合条件的数据为空，无需处理。');exit;
        }
        echoLog('符合条件的共'.count($data['list']).'个用户，待清理'.$data['count'].'个文件。');
        echoLog('');
        if (!isset($this->in['done'])) {
            Cache::set($cckey, $data, 600);
            echoLog('如确认需要清理，请在地址中追加参数后再次访问：&done=1');exit;
        }
        Cache::remove($cckey);

        // 按用户循环清理
        echoLog('开始清理...');
        foreach ($data['list'] as $userID => $pathArr) {
            echoLog('开始清理用户（'.$userID.'）的回收站中，共'.count($pathArr).'个文件；');
            Model('SourceRecycle')->remove($pathArr, $userID);
		    Action('explorer.recycleDriver')->remove($pathArr);
            Model('Source')->targetSpaceUpdate(SourceModel::TYPE_USER,$userID);
        }
        echoLog('清理完成！');exit;
	}
	// 获取回收站文件列表
    private function getRecycleList($limit, $days, $userID=0) {
        $model = Model('SourceRecycle');

        $where = array();
        // >n个文件
		if ($userID) {
			$where['userID'] = $userID;
		} else {
			if ($limit) {
				$sql = "SELECT count(sourceID) as cnt, userID FROM `io_source_recycle` GROUP BY userID HAVING cnt > $limit";
				$list = $model->query($sql);
				if (!$list) return array();
				$list = array_to_keyvalue($list, '', 'userID');
				$where['userID'] = array('in', $list);
			}
		}
		// n天前
        if ($days) {
            $time = date("Y-m-d 23:59:59",strtotime("-$days day"));
            $where['createTime'] = array('<=', strtotime($time));
        }
        if ($where) $model->where($where);

        // 按条件查询用户回收站中的文件sourceID
        $list = $model->field('sourceID, userID')->select();
        if (!$list) return array();
        $count = count($list);

        $list = array_to_keyvalue_group($list, 'userID', 'sourceID');
        return array('list' => $list, 'count' => $count);
    }


	/**
	 * 重置文件层级
	 * 1.parentID和parentLevel[-2]不等，更新parentLevel；剩余部分为不等且parentID无对应记录，更新parentID——还有剩余，则为相等且无对应记录，不处理
	 * 2.查询所有parentID+parentLevel+fileID+isFolder+name重复的记录，文件夹则重命名，文件则删除
	 * @return void
	 */
	public function resetParentLevel(){
		KodUser::checkRoot();
		ignore_timeout();
		$model = Model('Source');

		if (!$this->in['done']) {
			$this->cliEchoLog('本接口用于修复文件层级异常，调用前请务必备份数据库。如已备份且确定执行，请在地址中追加参数后再次访问：&done=1');
			exit;
		}
		// 0.重复数据临时表文件
		mk_dir(TEMP_FILES);
		$file = TEMP_FILES.'tmp_level_source_'.date('Ymd').'.txt';
		if (file_exists($file) && !filesize($file)) del_file($file);

		// io_source总记录数超过500w时，建议命令行调用
		$total = $model->count();
		if (!is_cli() && $total > 10000*500) {
			$cmd = 'php '.BASIC_PATH.'index.php "admin/repair/resetParentLevel&accessToken='.Action("user.index")->accessToken().'&done=1"';
			$this->cliEchoLog('数据量过大，为避免执行超时，请在命令行执行：'.$cmd);
			exit;
		}
		$model->execute('SET SESSION group_concat_max_len = 1000000');
		$this->systemMtce(1);

		// 1.父目录层级与PID不匹配
		$timeStart = microtime(true);
		if (!file_exists($file)) {
			$this->cliEchoLog('1.正在处理层级异常数据，共'.$total.'条记录，可能耗时较长，请耐心等待...');
			// 批量查询后批量更新特别慢，改为直接数据库更新
			// // 1.0 删除找不到parentID的记录——暂不处理，可以考虑统一移到某个指定目录
			// $sql = "UPDATE io_source AS t1 LEFT JOIN io_source AS t2 ON t1.parentID = t2.sourceID
			// 		SET t1.isDelete = 1
			// 		WHERE t1.isDelete = 0 AND t1.parentID > 0 AND t2.sourceID IS NULL";

			// 1.1 更新为：parentLevel=>parentID.parentLevel+sourceID——2千万条记录，380万条更新，需要约30分钟
			$sql = "UPDATE io_source AS t1 JOIN io_source AS t2 ON t1.parentID = t2.sourceID
					SET t1.parentLevel = CONCAT(t2.parentLevel, t2.sourceID, ',')
					WHERE t1.isDelete = 0 AND t1.parentID > 0 
					AND t1.parentLevel != '' AND t1.parentID != SUBSTRING_INDEX(SUBSTRING_INDEX(t1.parentLevel, ',', -2), ',', 1)";
			$cnt = $model->execute($sql);
			$timeNow = microtime(true);
			$this->cliEchoLog('>1.1 层级异常数据[1]处理完成，异常文件：'.intval($cnt).'，耗时：'.round(($timeNow - $timeStart),1).'秒。');

			// 1.2 不等且parentID对应记录为空（剩余部分为相等且为空，不处理）：parentID=>parentLevel[-2]——2千万条记录需要约3分钟，查询需1分钟
			$sql = "UPDATE io_source SET parentID = SUBSTRING_INDEX(SUBSTRING_INDEX(parentLevel, ',', -2), ',', 1)
					WHERE isDelete = 0 AND parentID > 0 
					AND parentLevel != '' AND parentID != SUBSTRING_INDEX(SUBSTRING_INDEX(parentLevel, ',', -2), ',', 1)";
			$cnt = $model->execute($sql);
			$timeStart = microtime(true);
			$this->cliEchoLog('>1.2 层级异常数据[2]处理完成，异常文件：'.intval($cnt).'，耗时：'.round(($timeStart - $timeNow),1).'秒。');
		}

		// 2.文件/夹重复：parentLevel/fileID/parentID相同
		// 2.1 创建临时表，获取（parentLevel，fileID）重复的记录
		// $timeStart = microtime(true);
		$this->cliEchoLog('2.准备处理重复文件：');
		if (!file_exists($file)) {
			$this->cliEchoLog('2.1 正在统计重复文件，共'.$total.'条记录...');
			// 1.获取fileID重复的记录
			// 直接查询会因为group_concat导致内存溢出，改为按fileID分批查询
			// $sql = 'SELECT GROUP_CONCAT(sourceID) AS ids, isFolder, COUNT(*) AS cnt 
			// 		FROM io_source 
			// 		WHERE isDelete = 0 AND parentID > 0 GROUP BY parentLevel, fileID, isFolder, name
			// 		HAVING cnt > 1 
			// 		ORDER BY isFolder ASC';
			$idx = 0;
			$tmp = array();
			$sql = 'SELECT fileID,count(*) AS cnt FROM io_source WHERE isDelete = 0 AND parentID > 0
					GROUP BY fileID HAVING cnt > 1 ORDER BY fileID DESC';	// 文件夹排后面
			$res = $model->query($sql);	// 2千万条数据耗时2分钟
			$cnt = count($res);
			// 2.根据sourceID获取parentID+parentLevel+fileID+isFolder+name重复的记录
			$handle = fopen($file, 'w');	// a为追加模式
			foreach ($res as $i => $item) {
				if ($i % 100 == 0 || $i == ($cnt - 1)) {
					$msg = $this->ratioText($i, $cnt);
					$this->cliEchoLog('>['.$idx.'] '.$msg, true);
				}
			    $tmp[$item['fileID']] = intval($item['cnt']);
				if (array_sum($tmp) < 10000) continue;
				$this->groupLevelList($model, $handle, $tmp, $idx);
			}
			$this->groupLevelList($model, $handle, $tmp, $idx);
			fclose($handle);

			if (!filesize($file)) {
				$this->systemMtce();
				$this->cliEchoLog('>文件层级正常，无需处理。');exit;
			}
			$this->cliEchoLog('>重复文件统计完成，耗时：'.round((microtime(true) - $timeStart),1).'秒。');
		} else {
			if (!filesize($file)) {
				$this->systemMtce();
				$this->cliEchoLog('>临时表文件为空，请重试。');
				del_file($file);
				exit;
			}
		}

		// 2.2 比较parentLevel最后一位和parentID，相等说明同一目录下重复，则只保留一条（其他删除）；不等则获取parentID对应的parentLevel并更新
		$timeStart = microtime(true);
		$cnt = 0;
		$res = array();
		$handle = fopen($file, 'r');
		while (!feof($handle)) {
			$tmp = json_decode(trim(fgets($handle)), true);
			if (!$tmp) continue;
			$cnt+= intval($tmp['cnt']);
			$res[] = $tmp;
		}
		fclose($handle);
		$this->cliEchoLog('2.2 正在处理重复文件，共'.$cnt.'条数据...');
		$idx = $tmpIdx = 0;
		$ids = $plvls = $updates = $renames = $removes = array();
		// $data = array('repeat' => 0, 'rename' => 0, 'remove' => 0, 'update' => 0);
		$data = array('repeat' => 0, 'rename' => 0);
		foreach($res as $i => $item) {
			$where = array(
				'sourceID'		=> array('in', explode(',', $item['ids'])),
			);
			$tmps = array();
			$list = $model->where($where)->field('sourceID,parentID,parentLevel,name')->order('isDelete,sourceID asc')->select();
			// $cnt += (count($list) - $item['cnt']);	// 执行过程中，总数据量可能有变化，实时更新
			foreach($list as $j => $value) {
				$idx++;
				$tmpIdx++;
				$sid = $value['sourceID'];
				$arr = explode(',', $value['parentLevel']);
				$pid = $arr[count($arr)-2];		// level中的parentID
				$thePid = $value['parentID'];	// 实际的parentID
				
				// 输出进度
				$prfx = '>'.$idx.'['.$sid.'] ';
				if ($tmpIdx >= mt_rand(1000,2000) || $idx == $cnt) {
					$tmpIdx = 0;
					$msg = $this->ratioText($idx, $cnt);
					$msg .= ' '.str_replace(array('{','}','"'),'',json_encode($data));
				}
				if ($idx % 100 == 0 || $idx == $cnt) {
					$this->cliEchoLog($prfx.$msg, true);
				}
				// 2.1 parentID=parentLevel[-2]，说明层级正常，判断是否有重名：文件夹重命名；文件删除
				if ($pid == $thePid) {
					$name = $value['name'];
					// 1.不重名，不处理
					if (!in_array($name, $tmps)) {
						$tmps[] = $name;
						continue;
					}
					// 2.文件重名，删除
					if ($item['isFolder'] != '1') {
						$data['repeat']++;
						// TODO 移到到回收站比较慢；直接批量更新会导致文件没有归属
						// $res = $model->remove($sid);	// 删除到回收站
						$removes[] = array(
							'sourceID',$sid, //where
							'isDelete',1, //save，只能更新一个字段
						);
						$this->_saveAll($model, $removes);
						continue;
					}
					// 3.文件夹重名，只重命名不删除——无法判断子内容是否相同
					$data['rename']++;
					$renames[] = array(
						'sourceID',$sid, //where
						'name',addslashes($name).'@'.date('YmdHis').'-'.$j //save，只能更新一个字段
					);
					$this->_saveAll($model, $renames);
					continue;
				}
				// 不相等的是sourceID=parentID不存在的数据，不做处理
				// // 2.2 parentID和parentLevel中的不同，说明层级异常，根据parentID查询并更新parentLevel
				// if (!isset($plvls[$thePid])) {
				// 	// update io_source set parentLevel = (select concat(parentLevel,sourceID,',') from io_source where sourceID = 27138034) where sourceID = 27138041
				// 	$res = $model->where(array('sourceID' => $thePid))->field('parentLevel')->find();
				// 	// parentID不存在，删除
				// 	if (!$res) {
				// 		$data['remove']++;
				// 		// $res = IO::remove(KodIO::make($sid));
				// 		// $res = $model->remove($sid);
				// 		$res = $model->where(array('sourceID'=>$sid))->save(array('isDelete'=>1,'modifyTime'=>time()));
				// 		continue;
				// 	}
				// 	$parentLevel = $res['parentLevel'];
				// 	$plvls[$thePid] = $parentLevel;
				// } else {
				// 	$parentLevel = $plvls[$thePid];
				// }
				// $parentLevel = $parentLevel . $thePid . ',';
				// // 批量更新
				// // $update = array('parentLevel' => $parentLevel, 'modifyTime' => time());
				// $updates[] = array(
				// 	'sourceID',$sid, //where
				// 	'parentLevel',$parentLevel //save，只能更新一个字段
				// );
				// $this->_saveAll($model, $updates);
				// $data['update']++;
			}
			$tmps = array();
		}
		$res = $this->_saveAll($model, $removes, true);
		$res = $this->_saveAll($model, $renames, true);
		// $res = $this->_saveAll($model, $updates, true);
		del_file($file);

		$logs = array(
			'重复文件:' . $data['repeat'],
			'重名目录:' . $data['rename'],
			// '无效目录:' . $data['remove'],
			// '层级异常:' . $data['update']
		);
		$this->cliEchoLog('>执行完成，统计文件/夹共：'.$idx.'，' . implode('，', $logs) . '。总耗时：'.round((microtime(true) - $timeStart),1).'秒');
		$this->systemMtce();
	}
	// 按fileID分组
	private function groupLevelList($model, $handle, &$data, &$idx) {
		if (empty($data)) return;
		$ids = array_keys($data);
		$data = array();
		// 按 fileID 分批处理——已更新过parentID<>parentLevel[-2]的数据，未能更新的是sourceID=parentID不存在的数据，所以此处group by加上parentID
		$where = 'fileID' . (count($ids) > 1 ? ' IN (' . implode(',', $ids) . ')' : '=' . $ids[0]);
		$sql = "SELECT GROUP_CONCAT(sourceID) AS ids, isFolder, COUNT(*) AS cnt
					FROM io_source
					WHERE {$where} AND isDelete = 0 AND parentID > 0
					GROUP BY parentID, parentLevel, fileID, isFolder, BINARY name
					HAVING cnt > 1";
		$res = $model->query($sql);
		if (!$res) return;
		foreach ($res as $item) {
			$idx += intval($item['cnt']);
			fwrite($handle, json_encode($item) . "\n");
		}
	}
	// 批量更新
	private function _saveAll($model, &$update, $done=false) {
		if (!$done) {
			if (count($update) < 1000) return;
		} else {
			if (empty($update)) return;
		}
		if (empty($update)) return;
		$model->saveAll($update);	// 没有返回结果
		$update = array();
	}
	private function cliEchoLog($msg, $rep = false){
		static $iscli;
		if (is_null($iscli)) $iscli = is_cli();
		if (!$iscli) return echoLog($msg, $rep);
		// 替换最后执行时没有换行
		static $repLast;
		if ($rep) {
			if ($repLast) echo "\033[A";  // ANSI 转义码：回到上一行
			$lineLength = (int) exec('tput cols');
			echo "\r" . str_repeat(' ', $lineLength) . "\r" . $msg . "\n";
		} else {
			echo $msg."\n";
		}
		ob_flush(); flush();
		$repLast = $rep;
	}
	private function ratioText($idx, $cnt){
		$now = str_pad($idx, strlen($cnt), ' ', STR_PAD_LEFT);	// 占位，避免内容抖动
		$rto = str_pad(round(($idx / $cnt) * 100, 1), 5, ' ', STR_PAD_LEFT);
		return $now.'/'.$cnt.' | '.$rto.'%';
	}
	private function systemMtce($status=0){
		ActionCall('user.index.maintenance', true, $status);
	}
	
}
