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
}