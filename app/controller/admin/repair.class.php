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
 * clearOrphanFile		// 物理文件存在但io_file记录不存在, 列出后二次确认删除
		
 * sql清理操作日志: 
 * delete from `system_log` where createTime < UNIX_TIMESTAMP('2023-03-01 00:00:00')
 */
class adminRepair extends Controller {
	function __construct()    {
		parent::__construct();
		$this->resetPathKey = '';
		$this->pageCount    = 20000;// 分页查询限制(单批处理条数)
	}
	
	/**
	 * 清空修复异常数据(php终止,断电,宕机等引起数据库表错误的进行处理;)
	 * 6个小时执行一次;
	 * 
	 * 手动执行:
	 * http://192.168.1.111/kod/kodbox/?admin/repair/autoReset&done=1
	 * done=1为扫描并清理异常数据(缓存当日缺失物理文件记录);done=2为按缓存删除不存在的文件记录
	 */
	public function autoReset(){
		KodUser::checkRoot();
		ignore_timeout();
		$done = isset($this->in['done']) ? intval($this->in['done']) : 0;
		if ($done == 2) {	// 仅消费done=1阶段生成的缺失物理文件缓存
			// clearErrorFile只依赖resetPathKey定位作用域缓存, 无需重新扫描目录，仅生成$this->resetPathKey供clearFileKey定位作用域缓存
			if (!empty($this->in['path'])) {
				$parse = KodIO::parse($this->in['path']);
				$id = !empty($parse['id']) ? intval($parse['id']) : 0;
				if ($id > 0) $this->resetPathKey = 'repair.reset.path.'.$id;
			}
			echoLog('异常数据清理,可在后台任务管理进行中止.');
			echoLog('=====================================================');
			return $this->clearErrorFile();
		}
		// done=1: 有path时先构建path作用域的source/file ID缓存, 供reset*方法限定范围
		$msg = $this->resetPathSource();
		if ($msg) echoLog($msg);
		$cacheKey = 'autoReset';
		$lastTime = Cache::get($cacheKey);
        $lastTime = false;//debug ;
		if($lastTime && time() - intval($lastTime) < 3600*6 ){
			echo '最后一次执行未超过6小时!';return;
		}
		// 开始前写入时间作为执行锁; 若中途异常中断需等6小时, 可手动删除autoReset缓存后重试
		Cache::set($cacheKey,time());
		echoLog('异常数据清理,可在后台任务管理进行中止.');
		// echoLog('请求参数done=1时,不直接删除已缺失的物理文件,可查看“物理文件不存在的数据记录”,确认需要删除后,再执行done=2进行删除');
		echoLog('=====================================================');
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
		KodUser::checkRoot();
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
	// 获取path限定下的ID列表; 未限定返回false; shareTo=true时返回shareID列表
	private function pathIds($file=false, $shareTo=false){
		if (!$this->resetPathKey) return false;
		$cache = Cache::get($this->resetPathKey);
		if (!$cache) {
			echoLog('缓存数据异常,请尝试重新执行!');exit;
		}
		$key = $file ? 'file' : 'source';
		$ids = isset($cache[$key]) ? $cache[$key] : array();
		$ids = array_values(array_filter(array_unique($ids)));
		if ($shareTo) {
			if (!$ids) return array();
			$list = Model('Share')->where(array('sourceID'=>array('in',$ids)))->select();
			if (!$list) return array();
			$ids = array_values(array_filter(array_unique(array_to_keyvalue($list, '', 'shareID'))));
		}
		return $ids;
	}
	// 缺失物理文件记录缓存key; 与path作用域绑定, 避免不同目录/全库数据互相覆盖
	private function clearFileKey(){
		$key = 'clear_file_'.date('Ymd');
		if ($this->resetPathKey) $key .= '_'.md5($this->resetPathKey);
		return $key;
	}
	
	/**
	 * 清除已不存在的物理文件记录
	 * 需先执行autoReset方法,并查看sourceRepair日志【resetFileLink--已不存在的物理文件】,确认是否需要清除
	 * @return void
	 */
	public function clearErrorFile(){
		KodUser::checkRoot();
		$cacheKey = $this->clearFileKey();
		$cache = Cache::get($cacheKey);
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
		Cache::remove($cacheKey);
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
		KodUser::checkRoot();
		$taskID ='resetSourceEmpty';$pageNum = $this->pageCount;$changeNum = 0;
		$model = Model("Source");
		$pathIds = $this->pathIds();
		$total = $pathIds === false ? $model->count() : count($pathIds);
		$task = TaskLog::newTask($taskID,'source表异常数据处理',$total);
		if ($pathIds === false) {
			$lastID = 0;
			while (true) {
				$list = $model->where(array('sourceID'=>array('>',$lastID)))->order('sourceID asc')->limit($pageNum)->select();
				if (empty($list)) break;
				$lastItem = end($list);
				$lastID = intval($lastItem['sourceID']);
				$this->resetSourceEmptyList($model,$list,$changeNum,$task,$taskID);
			}
		} else {
			foreach (array_chunk($pathIds, $pageNum) as $chunk) {
				$list = $model->where(array('sourceID'=>array('in',$chunk)))->select();
				if (empty($list)) continue;
				$this->resetSourceEmptyList($model,$list,$changeNum,$task,$taskID);
			}
		}
		$task->end();
	}
	// resetSourceEmpty单批处理
	private function resetSourceEmptyList($model,$list,&$changeNum,$task,$taskID){
		$parentSource = $removeSource = $removeFiles = array();
		foreach ($list as $item) {
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
	}
	
	// source对应fileID 不存在处理;
	public function resetSourceFile(){
		KodUser::checkRoot();
		$taskID ='resetSourceFile';$pageNum = $this->pageCount;$changeNum = 0;
		$model = Model("Source");$modelFile = Model("File");
		$pathIds = $this->pathIds();
		$total = $pathIds === false ? $model->count() : count($pathIds);
		$task = TaskLog::newTask($taskID,'source表空数据处理',$total);
		if ($pathIds === false) {
			$lastID = 0;
			while (true) {
				$list = $model->where(array('sourceID'=>array('>',$lastID)))->order('sourceID asc')->limit($pageNum)->select();
				if (empty($list)) break;
				$lastItem = end($list);
				$lastID = intval($lastItem['sourceID']);
				$this->resetSourceFileList($model,$modelFile,$list,$changeNum,$task,$taskID);
			}
		} else {
			foreach (array_chunk($pathIds, $pageNum) as $chunk) {
				$list = $model->where(array('sourceID'=>array('in',$chunk)))->select();
				if (empty($list)) continue;
				$this->resetSourceFileList($model,$modelFile,$list,$changeNum,$task,$taskID);
			}
		}
		$task->end();
	}
	// resetSourceFile单批处理
	private function resetSourceFileList($model,$modelFile,$list,&$changeNum,$task,$taskID){
		$parentSource = $removeSource = $removeFiles = array();
		foreach ($list as $item) {
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
	}

	// 重置文件夹大小
	private function folderSizeReset($parentSource){
		$model = Model("Source");
		$parentSource = array_filter(array_unique($parentSource));
		foreach ($parentSource as $sourceID) {
			if ($sourceID > 0) $model->folderSizeReset($sourceID);
		}
	}

	// 重置io_file.hash（hashSimple/hashMd5）
	public function resetFileHash(){
		KodUser::checkRoot();
		$taskID ='resetFileHash';$pageNum = $this->pageCount;$changeNum = 0;
		$model  = Model('File');
		$pathIds = $this->pathIds(true);
		$total = $pathIds === false ? $model->count() : count($pathIds);
		$task = TaskLog::newTask($taskID,'更新文件hash',$total);
		if ($pathIds === false) {
			$lastID = 0;
			while (true) {
				$list = $model->where(array('fileID'=>array('>',$lastID)))->order('fileID asc')->limit($pageNum)->select();
				if (empty($list)) break;
				$lastItem = end($list);
				$lastID = intval($lastItem['fileID']);
				$this->resetFileHashList($model,$list,$changeNum,$task,$taskID);
			}
		} else {
			foreach (array_chunk($pathIds, $pageNum) as $chunk) {
				$list = $model->where(array('fileID'=>array('in',$chunk)))->select();
				if (empty($list)) continue;
				$this->resetFileHashList($model,$list,$changeNum,$task,$taskID);
			}
		}
		$task->end();
	}
	// resetFileHash单批处理: 物理文件缺失时只记录, 不计算hash
	private function resetFileHashList($model,$list,&$changeNum,$task,$taskID){
		foreach ($list as $item) {
			if((!$item['hashSimple'] || !$item['hashMd5'])){
				if (!IO::exist($item['path'])) {
					write_log(array($taskID.'--物理文件不存在',$item),'sourceRepair');
					$task->update(1);
					continue;
				}
				$data = array('hashSimple'=>IO::hashSimple($item['path']) );
				if(!$item['hashMd5']){$data['hashMd5'] = IO::hashMd5($item['path']);}
				
				$changeNum++;write_log(array($taskID,$item),'sourceRepair');
				$task->task['desc'] = $task->task['currentTitle'] = $changeNum.'个修改';
				$model->where(array('fileID'=>$item['fileID']))->save($data);
			}
			$task->update(1);
		}
	}
	
	// 重置分享,内部协作分享数据;(删除文件夹,删除对应分享,及内部协作分享)
	public function resetShareTo(){
		KodUser::checkRoot();
		$taskID ='resetShareTo';$pageNum = $this->pageCount;$changeNum = 0;
		$model  = Model('share_to');$modelShare = Model("share");
		$pathIds = $this->pathIds(false, true);
		$total = $pathIds === false ? $model->count() : count($pathIds);
		$task = TaskLog::newTask($taskID,'重置内部协作数据',$total);
		if ($pathIds === false) {
			$lastID = 0;
			while (true) {
				$list = $model->where(array('id'=>array('>',$lastID)))->order('id asc')->limit($pageNum)->select();
				if (empty($list)) break;
				$lastItem = end($list);
				$lastID = intval($lastItem['id']);
				$this->resetShareToList($model,$modelShare,$list,$changeNum,$task,$taskID);
			}
		} else {
			foreach (array_chunk($pathIds, $pageNum) as $chunk) {
				$list = $model->where(array('shareID'=>array('in',$chunk)))->select();
				if (empty($list)) continue;
				$this->resetShareToList($model,$modelShare,$list,$changeNum,$task,$taskID);
			}
		}
		$task->end();
	}
	// resetShareTo单批处理
	private function resetShareToList($model,$modelShare,$list,&$changeNum,$task,$taskID){
		foreach ($list as $item) {
			$where = array("shareID"=>$item['shareID']);
			if(!$modelShare->where($where)->find()){
				$changeNum++;write_log(array($taskID,$item),'sourceRepair');
				$task->task['desc'] = $task->task['currentTitle'] = $changeNum.'个不存在';
				$model->where($where)->delete();
			}
			$task->update(1);
		}
	}
	
	// 重置分享,内部协作分享数据;(删除文件夹,删除对应分享,及内部协作分享)
	public function resetShare(){
		KodUser::checkRoot();
		$taskID ='resetShare';$pageNum = $this->pageCount;$changeNum = 0;
		$model = Model('share');$modelSource = Model("Source");
		$pathIds = $this->pathIds();
		$total = $pathIds === false ? $model->count() : count($pathIds);
		$task = TaskLog::newTask($taskID,'重置分享数据',$total);
		if ($pathIds === false) {
			$lastID = 0;
			while (true) {
				$list = $model->where(array('shareID'=>array('>',$lastID)))->order('shareID asc')->limit($pageNum)->select();
				if (empty($list)) break;
				$lastItem = end($list);
				$lastID = intval($lastItem['shareID']);
				$this->resetShareList($model,$modelSource,$list,$changeNum,$task,$taskID);
			}
		} else {
			foreach (array_chunk($pathIds, $pageNum) as $chunk) {
				$list = $model->where(array('sourceID'=>array('in',$chunk)))->select();
				if (empty($list)) continue;
				$this->resetShareList($model,$modelSource,$list,$changeNum,$task,$taskID);
			}
		}
		$task->end();
	}
	// resetShare单批处理
	private function resetShareList($model,$modelSource,$list,&$changeNum,$task,$taskID){
		foreach ($list as $item) {
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
	}

	// file表中存在, source表中不存在的进行清除;历史记录表等;
	public function resetFileSource(){
		KodUser::checkRoot();
		$taskID ='resetFileSource';$pageNum = $this->pageCount;$changeNum = 0;
		$model = Model("File");$modelSource = Model("Source");
		$modelHistory = Model('SourceHistory');
		$stores = Model('Storage')->listData();
	    $stores = array_to_keyvalue($stores, '', 'id');	// 有效存储列表; 物理删除放开时使用
		$pathIds = $this->pathIds(true);
		$total = $pathIds === false ? $model->count() : count($pathIds);
		$task = TaskLog::newTask($taskID,'File记录异常处理',$total);
		if ($pathIds === false) {
			$lastID = 0;
			while (true) {
				$list = $model->where(array('fileID'=>array('>',$lastID)))->order('fileID asc')->limit($pageNum)->select();
				if (empty($list)) break;
				$lastItem = end($list);
				$lastID = intval($lastItem['fileID']);
				$this->resetFileSourceList($model,$modelSource,$modelHistory,$stores,$list,$changeNum,$task,$taskID);
			}
		} else {
			foreach (array_chunk($pathIds, $pageNum) as $chunk) {
				$list = $model->where(array('fileID'=>array('in',$chunk)))->select();
				if (empty($list)) continue;
				$this->resetFileSourceList($model,$modelSource,$modelHistory,$stores,$list,$changeNum,$task,$taskID);
			}
		}
		$task->end();
	}
	// resetFileSource单批处理
	private function resetFileSourceList($model,$modelSource,$modelHistory,$stores,$list,&$changeNum,$task,$taskID){
		foreach ($list as $item) {
			$where = array("fileID"=>$item['fileID']);
			$findSource  = $modelSource->where($where)->find();
			$findHistory = $modelHistory->where($where)->find();
			if(!$findSource && !$findHistory){
				// // 正常滞后1天删除的数据，不处理，避免物理文件遗留
				// $fromTime = time() - 3600*24*1;
				// if ($item['linkCount'] == '0' && intval($item['modifyTime']) > $fromTime) continue;

				$changeNum++;write_log(array($taskID,$item),'sourceRepair');
				$task->task['desc'] = $task->task['currentTitle'] = $changeNum.'个不存在';
				// 物理文件删除暂不放开, 由clearOrphanFile人工确认清理
				// if (in_array($item['ioType'], $stores)) {IO::remove($item['path']);}

				// // 清理关联数据, 与delFileNone保持一致
				// Model('io_file_meta')->where($where)->delete();
				// Model('io_file_contents')->where($where)->delete();
				// Model('share_report')->where($where)->delete();
				$model->where($where)->delete();
			}
			$task->update(1);
		}
	}
	
	// File表中,io不存在的文件进行处理;（被手动删除的）
	public function resetFileLink(){
		KodUser::checkRoot();
		$taskID ='resetFileLink';$pageNum = $this->pageCount;$changeNum = 0;
		$model = Model('File');
		$stores = Model('Storage')->listData();
	    $stores = array_to_keyvalue($stores, '', 'id');	// 有效存储列表

		$cache = array();
		$pathIds = $this->pathIds(true);
		$total = $pathIds === false ? $model->count() : count($pathIds);
		$task  = TaskLog::newTask($taskID,'重置清理File表引用',$total);
		if ($pathIds === false) {
			$lastID = 0;
			while (true) {
				$list = $model->where(array('fileID'=>array('>',$lastID)))->order('fileID asc')->limit($pageNum)->select();
				if (empty($list)) break;
				$lastItem = end($list);
				$lastID = intval($lastItem['fileID']);
				$this->resetFileLinkList($model,$stores,$list,$cache,$changeNum,$task);
			}
		} else {
			foreach (array_chunk($pathIds, $pageNum) as $chunk) {
				$list = $model->where(array('fileID'=>array('in',$chunk)))->select();
				if (empty($list)) continue;
				$this->resetFileLinkList($model,$stores,$list,$cache,$changeNum,$task);
			}
		}
		$task->end();
		if($cache) Cache::set($this->clearFileKey(), $cache);
	}
	// resetFileLink单批处理: 物理文件缺失/存储已删除均进入待删除缓存, 日志区分原因
	private function resetFileLinkList($model,$stores,$list,&$cache,&$changeNum,$task){
		foreach ($list as $item) {
			$ioExist = in_array($item['ioType'], $stores);
			if($ioExist && IO::exist($item['path']) ){
				$model->resetFile($item);
			}else{
				// 存储已删除的io_file记录属于垃圾数据, 与物理文件缺失一样进入待删除缓存
				$logKey = $ioExist ? 'resetFileLink--已不存在的物理文件' : 'resetFileLink--存储已删除的垃圾记录';
				$changeNum++;write_log(array($logKey,$item),'sourceRepair');
				$task->task['desc'] = $task->task['currentTitle'] = $changeNum.'个不存在'.';'.$item['path'];
				$cache[] = array(
					'fileID'	=> $item['fileID'],
					'linkCount' => $item['linkCount'],
				);
			}
			$task->update(1);
		}
	}
	// 删除不存在的物理文件
	private function delFileNone($model, $modelSource, $modelHistory, $item){
		$where = array("fileID"=>$item['fileID']);
		$sourceList = $modelSource->where($where)->select();
		$cnt1  = $modelSource->where($where)->delete();
		$modelHistory->where($where)->delete();
		Model('io_file_meta')->where($where)->delete();
		Model('io_file_contents')->where($where)->delete();
		Model('share_report')->where($where)->delete();
		$cnt2 = $model->where(array('fileID'=>$item['fileID']))->delete();
		
		// 重置父目录大小
		$parentList = array_to_keyvalue($sourceList, '', 'parentID');
		$this->folderSizeReset($parentList);	// TODO 不必要每个删除都重置大小，待优化
		return array('source' => intval($cnt1),'file'=> intval($cnt2));
	}

	public function resetSourceHistory(){
		KodUser::checkRoot();
		$taskID ='resetSourceHistory';$pageNum = $this->pageCount;$changeNum = 0;
		$model = Model('SourceHistory');$modelSource = Model("Source");
		$modelFile = Model("File");
		$pathIds = $this->pathIds();
		$total = $pathIds === false ? $model->count() : count($pathIds);
		$task = TaskLog::newTask($taskID,'历史版本异常数据处理',$total);
		// 去重集合跨批次传递: 同一sourceID/fileID只删除一次, 避免重复删除和重复计数
		$sourceDeleted = array();$fileDeleted = array();
		if ($pathIds === false) {
			$lastID = 0;
			while (true) {
				$list = $model->where(array('id'=>array('>',$lastID)))->order('id asc')->limit($pageNum)->select();
				if (empty($list)) break;
				$lastItem = end($list);
				$lastID = intval($lastItem['id']);
				$this->resetSourceHistoryList($model,$modelSource,$modelFile,$list,$changeNum,$task,$taskID,$sourceDeleted,$fileDeleted);
			}
		} else {
			foreach (array_chunk($pathIds, $pageNum) as $chunk) {
				$list = $model->where(array('sourceID'=>array('in',$chunk)))->select();
				if (empty($list)) continue;
				$this->resetSourceHistoryList($model,$modelSource,$modelFile,$list,$changeNum,$task,$taskID,$sourceDeleted,$fileDeleted);
			}
		}
		$task->end();
	}
	// resetSourceHistory单批处理: source缺失按sourceID删全部历史; fileID缺失按fileID删全部历史(该物理记录已失效)
	private function resetSourceHistoryList($model,$modelSource,$modelFile,$list,&$changeNum,$task,$taskID,&$sourceDeleted,&$fileDeleted){
		foreach ($list as $item) {
			$needDelete = false;
			if (!isset($sourceDeleted[$item['sourceID']]) && !$modelSource->where(array("sourceID"=>$item['sourceID']))->find()){
				$model->where(array("sourceID"=>$item['sourceID']))->delete();
				$sourceDeleted[$item['sourceID']] = 1;
				$needDelete = true;
			}
			if (!isset($fileDeleted[$item['fileID']]) && !$modelFile->where(array('fileID'=>$item['fileID']))->find()){
				$model->where(array('fileID'=>$item['fileID']))->delete();
				$fileDeleted[$item['fileID']] = 1;
				$needDelete = true;
			}
			if ($needDelete) {
				$changeNum++;write_log(array($taskID,$item),'sourceRepair');
				$task->task['desc'] = $task->task['currentTitle'] = $changeNum.'个不存在';
			}
			$task->update(1);
		}
	}
	
	// 文件列表自然排序,文件名处理; 升级向下兼容数据处理;
	public function sourceNameInit(){
		KodUser::checkRoot();
		// 排序只执行一次; flag=1执行中, flag=2完成, 默认空; 有值直接跳过
		// Model("SystemOption")->set('sourceNameSortFlag','');
		if(Model("SystemOption")->get('sourceNameSortFlag')) return;
		$this->sourceNameSort();
	}
	public function sourceNameSort(){
		KodUser::checkRoot();
		$taskID ='sourceNameSort';$pageNum = $this->pageCount;$page = 1;$changeNum = 0;
		Model("SystemOption")->set('sourceNameSortFlag','1');
		$model = Model('Source');$modelMeta = Model("io_source_meta");
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
	 * 根据sourceID彻底删除文件: 先按sourceID查到fileID,
	 * 再删除所有引用该fileID的io_source记录(含物理文件及关联历史/分享/元数据);
	 * sourceID可传多个,如sourceID=1,2,3
	 * @return void
	 */
	public function clearSource(){
		KodUser::checkRoot();
		echoLog('根据sourceID彻底删除关联文件!参数示例：sourceID=1,2,3');
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

		$ids  = array_to_keyvalue($list, '', 'sourceID');
		$file = array_to_keyvalue($list, '', 'fileID');
		$file = array_filter(array_unique($file));
		$fCnt = count($file);
		$ids  = array_filter(array_unique($ids));
		$sCnt = count($ids);
		echoLog("共 {$sCnt} 条source记录，{$fCnt} 条file记录，开始删除:");
		// 2.根据fileID查所有sourceID
		if (!empty($file)) {
			$where = array('fileID'=>array('in', $file));
			$list = Model('Source')->where($where)->field('sourceID')->select();
			$ids = array_to_keyvalue($list, '', 'sourceID');
		}
		// 3.清理关联的分享/回收站引用
		if (!empty($ids)) {
			$shareList = Model('Share')->where(array('sourceID'=>array('in',$ids)))->field('shareID')->select();
			$shareIds = array_filter(array_to_keyvalue($shareList, '', 'shareID'));
			if ($shareIds) {
				Model('share_to')->where(array('shareID'=>array('in',$shareIds)))->delete();
				Model('Share')->where(array('shareID'=>array('in',$shareIds)))->delete();
			}
			Model('SourceRecycle')->where(array('sourceID'=>array('in',$ids)))->delete();
		}
		// 4.根据sourceID删除文件
		$seq = 0;
		foreach ($ids as $id) {
			$seq++;
			$path = KodIO::make($id);
			IO::remove($path, false);
			echoLog('source记录:'.$seq, true);
		}
		// 5.删除可能还存在的file记录及关联数据——实际物理文件删除与否不影响
		if (!empty($file)) {
			$where = array('fileID'=>array('in', $file));
			Model('File')->where($where)->delete();
			Model('SourceHistory')->where($where)->delete();
			Model('share_report')->where($where)->delete();
			Model('io_file_meta')->where($where)->delete();
			Model('io_file_contents')->where($where)->delete();
		}
		echoLog("删除完成!共删除source记录{$sCnt}条;file记录{$fCnt}条.");
	}
	
	// 指定sourceID重置对应目录大小
	public function resetSizeById($echo=true){
		KodUser::checkRoot();
		$id = $this->in['sourceID'];
		if(!$id) return;
		model('Source')->folderSizeResetChildren($id);
		if ($echo) echoLog("更新完成.".KodIO::make($id));
	}
	
	// 重复文件清理; 根据hashMd5处理;
	public function clearSameFile(){
		KodUser::checkRoot();
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
			$fileRemove = array();$linkCount = 0;$pathRemove = array();
			foreach ($files as $i=>$file){
				if($i == 0) continue;
				$linkCount += intval($file['linkCount']);
				$fileRemove[] = $file['fileID'];
				if($file['path'] && $file['path'] != $files[0]['path']){
					$pathRemove[] = $file['path'];
				}
			}
			if($fileRemove){
				$db = $modelFile->db();
				$db->startTrans();
				try {
					$fileID = $files[0]['fileID'];
					$linkCount += intval($files[0]['linkCount']);
					$fileWhere = array('fileID'=>array('in',$fileRemove));
					$save = array('fileID'=>$fileID);
					Model("Source")->where($fileWhere)->save($save);
					Model("SourceHistory")->where($fileWhere)->save($save);
					Model("share_report")->where($fileWhere)->save($save);
					Model("io_file_meta")->where($fileWhere)->delete();
					Model("io_file_contents")->where($fileWhere)->delete();
					$modelFile->where($fileWhere)->delete();
					$modelFile->where(array('fileID'=>$fileID))->save(array('linkCount'=>$linkCount));
					$db->commit();
					$changeNum++;write_log(array($taskID,$item),'sourceRepair');
					$task->task['desc'] = $task->task['currentTitle'] = $changeNum.'个修改';
				} catch (Exception $e) {
					$db->rollback();
					write_log(array($taskID.'--处理失败',$item,$e->getMessage()),'sourceRepair');
				}
				// 事务已提交后再删物理文件; 删除失败不回滚, 只记日志, 遗留文件可由clearOrphanFile兜底清理
				$pathRemove = array_unique($pathRemove);
				foreach ($pathRemove as $path) {
					try {
						$res = IO::remove($path);
						if ($res === false || IO::exist($path)) {
							write_log(array($taskID.'--物理文件删除失败',$path),'sourceRepair');
						}
					} catch (Exception $e) {
						write_log(array($taskID.'--物理文件删除异常',$path,$e->getMessage()),'sourceRepair');
					}
				}
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
		KodUser::checkRoot();
		ignore_timeout();
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
        if ($limit) $title .= '回收站文件数超过'.$limit.'个的';
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
			$this->cliEchoLog('重复目录会进行重命名（原名@年月日时分秒-1）；重复文件会被删除到当前用户（管理员）个人回收站，执行后可在回收站查看和处理对应文件。');
			exit;
		}
		// 0.重复数据临时表文件
		mk_dir(TEMP_FILES);
		$file = TEMP_FILES.'tmp_level_source_'.date('YmdHis').'_'.rand_string(6).'.txt';
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
		register_shutdown_function(array($this, 'systemMtce'), 0);	// 异常中断时恢复维护模式

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
						$this->_saveAll($model, $removes, true);
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
		$res = $this->_saveAll($model, $removes, true, true);
		$res = $this->_saveAll($model, $renames, false,true);
		// $res = $this->_saveAll($model, $updates, false, true);
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
	private function _saveAll($model, &$update, $remove=false, $done=false) {
		if (!$done) {
			if (count($update) < 1000) return;
		} else {
			if (empty($update)) return;
		}
		if (empty($update)) return;
		$model->saveAll($update);	// 没有返回结果
		// 添加到个人回收站，管理员自行选择清除
		if ($remove) {
			$ids = array();
			foreach ($update as $value) {$ids[] = $value[1];}
			// 1.将标记为删除的数据写入回收站
			$recModel = Model('SourceRecycle');
			$sql = "insert into io_source_recycle (targetType, targetID, sourceID, userID, parentLevel, createTime) 
					(select targetType, targetID, sourceID, ".KodUser::id().", parentLevel, UNIX_TIMESTAMP()
					from io_source where sourceID in (".implode(',', $ids)."))";
			$recModel->execute($sql);
			// 2.获取写入回收站的parentLevel
			$where = array('userID'=>KodUser::id(), 'sourceID'=>array('in',$ids));
			$list = $recModel->where($where)->field('parentLevel')->select();
			$list = $list ? array_unique(array_to_keyvalue($list, '', 'parentLevel')) : array();
			// 3.根据parentLevel获取sourceID，重置对应目录大小
			$list = $this->getResetSizeIds($list);
			$tmpIn = $this->in;
			foreach ($list as $i => $id) {
				$this->in['sourceID'] = $id;
				$this->resetSizeById(false);
			}
			$this->in = $tmpIn;
		}
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
			$lineLength = (int) @exec('tput cols');
			if (!$lineLength) $lineLength = 80;
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

	// 将异常的（没有归属的）删除数据写入个人回收站，并重置目录大小
	public function resetParentLevelClear(){
		KodUser::checkRoot();
		ignore_timeout();
		echoLog('本接口用于整理异常的删除数据，并重置相关目录大小。执行后可在当前用户（管理员）个人回收站查看和处理相关文件。');
		$model = Model('SourceRecycle');
		$maxId = $model->max('id');

		$sql = "insert into io_source_recycle (targetType, targetID, sourceID, userID, parentLevel, createTime) 
				(select s.targetType, s.targetID, s.sourceID, ".KodUser::id().", s.parentLevel, UNIX_TIMESTAMP()
				from io_source as s 
				left join io_source_recycle as r on s.sourceID = r.sourceID 
				where s.isDelete = 1 and r.sourceID is null)";
		$res = $model->execute($sql);
		if (!$res) {
		    echoLog('目录数据正常，无需处理。');exit;
		}
		// $maxId = 1014;
		$where = array('userID'=>KodUser::id(), 'id'=>array('>',$maxId));
		$list = $model->where($where)->field('parentLevel')->select();
		$list = array_unique(array_to_keyvalue($list, '', 'parentLevel'));

		$list = $this->getResetSizeIds($list);
		echoLog('开始重置目录大小，共计：'.count($list));
		write_log(array('待重置的目录id列表', $list), 'repair');
		foreach ($list as $i => $id) {
			$this->in['sourceID'] = $id;
		    $this->resetSizeById(false);
			echoLog('已重置：'.($i+1).' =>'.KodIO::make($id), true);
		}
		echoLog('重置完成。');
	}
	private function getResetSizeIds($list){
		$data = array();
		$hash = array_flip($list); // 转换为哈希表加速查找
		foreach ($list as $idx => $path) {
			$trimmed = rtrim($path, ',');
			$parts = explode(',', $trimmed);
			$isBase = true;
			// 检查所有可能的上级路径
			for ($i = 1; $i < count($parts); $i++) {
				$parent = implode(',', array_slice($parts, 0, $i)) . ',';
				if (isset($hash[$parent])) {
					$isBase = false;
					break;
				}
			}
			if ($isBase) {
				// $data[] = $path;
				$parts = explode(',', trim($path, ','));
				$data[] = end($parts);
			}
		}
		return array_unique(array_filter($data));
	}

	/**
	 * 清除我的回收站，允许普通用户执行
	 * @return void
	 */
	public function clearMyRecycle(){
		// KodUser::checkRoot();
		ignore_timeout();

		echoLog('本接口用于清空当前（登录）用户的回收站（仅限系统文件）。');
		if (!isset($this->in['done'])) {
            echoLog('如确认需要执行，请在地址中追加参数后再次访问：&done=1');exit;
        }
		// 1.任务
		$recycleModel = Model('SourceRecycle');
		$sourceModel = Model("Source");
		$total = $recycleModel->where(array("userID"=>KodUser::id()))->count();
		if (!$total) {
		    echoLog('当前回收站为空，无需处理。'); exit;
		}
		echoLog('总文件数：'.$total.'，按 200000 条/批分批处理。');

		// 按主键游标分批加载、删除, 避免一次加载过多内存溢出
		$lastID = 0;$totalDone = 0;$targetArr = array();
		while (true) {
			$where = array("userID"=>KodUser::id(), 'id'=>array('>',$lastID));
			$recycleList = $recycleModel->where($where)->order('id asc')->limit(200000)->select();
			if (empty($recycleList)) break;
			$lastItem = end($recycleList);
			$lastID = intval($lastItem['id']);
			$totalDone += count($recycleList);

			echoLog('开始加载任务(已加载'.$totalDone.'条)...');
			$pList = $sList = array();
			foreach ($recycleList as $item) {
				$sourceID = $item['sourceID'];
				$pList[] = array("path"=>KodIO::make($sourceID));
				$sList[] = $sourceID;
				$key = $item['targetType'].'_'.$item['targetID'];
				$targetArr[$key] = array(
					"targetType"	=> $item['targetType'],
					'targetID'		=> $item['targetID']
				);
			}
			$this->taskCopyCheck($pList);//彻底删除: children数量获取为0,只能是主任务计数;
			unset($pList);
			echoLog('任务加载完成。');

			// 2.删除
			echoLog('开始删除文件，共：'.count($sList));
			foreach ($sList as $i => $theID) {
				$sourceModel->remove($theID,false);
				$recycleModel->where(array('sourceID'=>$theID))->delete();
				echoLog($totalDone - count($sList) + $i + 1, true);
			}
			unset($sList);
		}
		echoLog('文件删除完成，共处理'.$totalDone.'条。');

		//更新目标空间大小;
		echoLog('开始更新目录空间占用...');
		foreach ($targetArr as $item) {
			$sourceModel->targetSpaceUpdate($item['targetType'],$item['targetID']);
		}
		unset($targetArr);

		// 3.清空回收站时,重新计算大小; 一小时内不再处理;
		echoLog('开始更新个人空间占用...');
		Model('Source')->targetSpaceUpdate(SourceModel::TYPE_USER,KodUser::id());
		$cacheKey = 'autoReset_'.KodUser::id();
		Cache::set($cacheKey,time());
		$USER_HOME = KodIO::sourceID(MY_HOME);
		Model('Source')->folderSizeResetChildren($USER_HOME);
		Model('Source')->userSpaceReset(KodUser::id());
		echoLog('执行完成！');
	}
	// 文件移动; 耗时任务;
	private function taskCopyCheck($list){
		$list = is_array($list) ? $list : array();
		$taskID = 'copyMove-'.KodUser::id().'-'.rand_string(8);
		
		$task = new TaskFileTransfer($taskID,'copyMove');
		$task->update(0,true);//立即保存, 兼容文件夹子内容过多,扫描太久的问题;
		for ($i=0; $i < count($list); $i++) {
			$task->addPath($list[$i]['path']);
		}
	}

	/**
	 * 直接删除个人回收站下的文件（不进入系统回收站）
	 * @return void
	 */
	public function clearUserRecycleNow(){
	    KodUser::checkRoot();
		ignore_timeout();
		echoLog('本接口用于清空指定用户的回收站（仅限系统文件），文件将被直接删除而不存放到系统回收站，请谨慎操作。');
		if (!isset($this->in['done'])) {
            echoLog('如确认需要执行，请在地址中追加参数后再次访问：&done=1');exit;
        }
		if (empty($this->in['userID'])) {
			echoLog('请指定用户id：&userID=xx');exit;
		}
		$userID = intval($this->in['userID']);
		// 查询指定用户回收站文件列表
		$list = Model('SourceRecycle')->alias('r')->field('r.sourceID,s.fileID')
				->join("INNER JOIN io_source AS s ON r.sourceID = s.sourceID")
				->where(array("r.userID"=>$userID))
				->select();
		if (empty($list)) {
			echoLog('当前回收站为空，无需处理。'); exit;
		}
		$sources = $files = array();
		foreach ($list as $item) {
			$sources[] = $item['sourceID'];
			$files[] = $item['fileID'];
		}
		unset($list);
		// 删除
		echoLog('正在删除，请耐心等待...');
		Model('Source')->removeRelevance($sources,$files);
		echoLog('删除完成，共删除文件/夹：'.count($sources).'个。');exit;
	}

	/**
	 * 获取指定目录（或全部）物理文件不存在的记录
	 * @return void
	 */
	public function listFileNotExists(){
		KodUser::checkRoot();
		$ids = array();
		if (!empty($this->in['sourceID'])) {
			$id = $this->in['sourceID'];
			$info = Model('Source')->where(array('sourceID' => $id))->find();
			if (!$info) show_json('指定文件夹id不存在',0);
			$where = array('isFolder' => 0, 'parentLevel' => array('like', $info['parentLevel'].$id.',%'));
			$list = Model('Source')->where($where)->field('fileID')->select();
			$ids = array_filter(array_to_keyvalue($list, '','fileID'));
		}
		
		$pageNum = 1000;$page = 1;
		$model = Model('File');
		if ($ids) { // 指定sourceID
			$model->where(array('fileID' => array('in', $ids)));
		} else if (!empty($this->in['lastFileID'])) {   // 从大于指定fileID开始，不支持同时指定sourceID
			$model->where(array('fileID' => array('gt', intval($this->in['lastFileID']))));
		}
		$list = $model->selectPage($pageNum,$page);
		$stores = Model('Storage')->listData();
		$stores = array_to_keyvalue($stores, '', 'id'); // 有效存储列表
	
		$file = TEMP_FILES.'filepathlist-'.date('YmdHis').'-'.rand_string(6).'.txt';
	
		$i = $cnt = 0;
		echoLog('不存在或无法访问的物理文件列表：');
		echoLog('begin------------------------------------------------');
		while($list && $page <= $list['pageInfo']['pageTotal']){
			foreach ($list['list'] as $item) {
				$i++;
				$ioExist = in_array($item['ioType'], $stores);
				// ioType不在有效存储列表的io_file记录属于垃圾数据, 与物理文件缺失一并列出
				if($ioExist && IO::exist($item['path']) ){
					echoLog($i.'.存在：'.$item['path'],true);
				}else{
					$cnt++;
					file_put_contents($file, $item['path']."\n", FILE_APPEND);
				}
			}
			$page++;
			if ($ids) {
				$model->where(array('fileID' => array('in', $ids)));
			} else if (!empty($this->in['lastFileID'])) {
				$model->where(array('fileID' => array('gt', intval($this->in['lastFileID']))));
			}
			$list = $model->selectPage($pageNum,$page);
		}
		echoLog('end------------------------------------------------');
		if ($cnt) {
			echoLog('共有'.$cnt.'条异常记录，具体可查看文件：'.$file);
		}
	}

	// ======================================================== 重置层级结构异常文件 ========================================================

	/**
	 * 检测已删除状态异常文件——上级目录删除，但子文件未删除
	 * @param [type] $model
	 * @return void
	 */
	private function errData2Recycle($model) {
		// select * from io_source where parentID = 1739077 —— 层级重复
		// select isDelete,count(*) from io_source where parentLevel like ',0,1,450518,498485,700552,1351194,1739077,1739276,%' group by isDelete
		KodUser::checkRoot();
		echoLog('检测已删除状态异常文件');
	    $where = array(
			'isFolder' => 1,
			'isDelete' => 1,
			'parentID' => array('>', 0)
		);
		$list = $model->where($where)->field('sourceID,parentLevel')->select();
		if (!$list) return;
		$data = array();
		foreach ($list as $item) {
			$data[] = $item['parentLevel'].$item['sourceID'].',';
		}
		$list = array_unique($data);
		$list = $this->getBaseLevels($list);
		echoLog('共有'.count($list).'个已删除目录');
		if (!$list) return;
		$file = TEMP_FILES . 'reset-active.txt';
		del_file($file);
		foreach ($list as $level) {
			echoLog('开始检测状态异常文件：'.$level);
			$where = array(
				'isDelete' 		=> 0,
				'parentLevel'	=> array('like', $level.'%')
			);
			// $res = $model->where($where)->setField('isDelete',1);//所有字内容设置删除标记
			// echoLog('已处理'.$res.'个状态异常文件');
			// TODO 
			$res = $model->where($where)->field('sourceID')->select();
			if ($res) {
				$res = array_to_keyvalue($res, '', 'sourceID');
				file_put_contents($file, implode(',', $res).',', FILE_APPEND);
				echoLog('已记录'.count($res).'个状态异常文件');
			}
		}
		echoLog('完成已删除文件检测');
	}
	private function getBaseLevels($list) {
		// 去除空路径并排序（从短到长）
		$data = array_filter($list, function($path){return !empty(trim($path, ','));});
		usort($data, function($a, $b){return strlen($a) - strlen($b);});
		$base = array();
		foreach ($data as $path) {
			$isContained = false;
			$trimmedPath = trim($path, ',');
			$pathArr = explode(',', trim($trimmedPath, ','));
			foreach ($base as $basePath) {
				$trimmedBase = trim($basePath, ',');
				$baseArr = explode(',', trim($trimmedBase, ','));
				if (count($baseArr) <= count($pathArr)) {
					$prefixOk = true;
					foreach ($baseArr as $k => $v) {
						if ($pathArr[$k] != $v) {$prefixOk = false;break;}
					}
					if ($prefixOk) {$isContained = true;break;}
				}
			}
			if (!$isContained) {
				$base[] = $path;
			}
		}
		return $base;
	}

	/**
	 * 重置层级结构异常文件
	 * @param [type] $model
	 * @return void
	 */
	private function resetSourceLevel($model) {
		// 13093773	—— can't download
		KodUser::checkRoot();
		ignore_timeout();
		// $where = array(
		// 	'isDelete'		=> 0,
		// 	'parentID'		=> array('>',0),
		// 	'parentLevel'	=> array('<>',''),
		// );
		// $where = array();	// 1500w条数据，加条件需要30.5s，不加0.67s
		$total = (int) $model->count();
		if (!$total) return;

		echoLog('任务已提交，可在任务列表查看进度');
		http_close();

		$taskId = __FUNCTION__.'-TASK';
		$task = new Task($taskId,'resetdatatask',$total,'文件层级异常处理');

		$data = $update = $remove = array();
		$pageNum = 50000;
		$lastSid = 0;
		// for ($i=0; $i < $total;$i=$i+$pageNum) {$list = $model->limit($i,$pageNum)->field('sourceID,parentID,parentLevel,isDelete')->select();}
		do {
			$list = $model->where(array('sourceID'=>array('>',$lastSid)))->field('sourceID,parentID,parentLevel,isDelete')->order('sourceID asc')->limit($pageNum)->select();
			if (empty($list)) break;
			$lastSid  = _get(end($list),'sourceID');
			foreach ($list as $item) {
				$task->update(1);
				if ($item['isDelete'] == '1') continue;
				if (!$item['parentID'] || !$item['parentLevel']) continue;	// 部门/用户空间parentID=0
				// parentID!=parentLevel[-2]，说明层级不正常
				$parentLevel = explode(',', trim($item['parentLevel'], ','));
				if ($item['parentID'] != $parentLevel[count($parentLevel) - 1]) {
					$data[$item['sourceID']] = $item['parentID'];
				}
			}
			if (!$data) continue;
			$idx = 0;
			$this->resetSourceGet($model,$data,$update,$remove,$idx);
			$this->resetSourceUpdate($model,$update,$remove);
			$data = $update = $remove = array();
		} while (!empty($list));
		$task->end();
	}
	// 获取待更新level的数据
	private function resetSourceGet($model,$data,&$update,&$remove,&$idx) {
		$idx++;
		$where = array('sourceID'=>array('in',array_unique(array_values($data))));	// 根据parentID查找io_source记录
		$list = $model->where($where)->field('sourceID,parentID,parentLevel')->select();
		$dataTmp = $updateTmp = array();
		foreach ($list as $item) {
			if (!$item['parentID'] || !$item['parentLevel']) continue;
			$parentLevel = explode(',', trim($item['parentLevel'], ','));
			if ($item['parentID'] == $parentLevel[count($parentLevel) - 1]) {
				$updateTmp[$item['sourceID']] = $item['parentLevel'];
				continue;
			}
			$dataTmp[$item['sourceID']] = $item['parentID'];
		}
		foreach ($data as $sourceID => $parentID) {
			if (isset($updateTmp[$parentID])) {	// 待更新parentLevel的数据
				$update[$sourceID] = $updateTmp[$parentID].$parentID.',';
			} else {
				$remove[] = $sourceID;	// 根据parentID找不到记录的数据
			}
		}
		// $idx 防止递归死循环
		if (!empty($dataTmp) && $idx < 100) {
			$this->resetSourceGet($model,$dataTmp,$update,$remove,$idx);
		}
	}
	private function resetSourceUpdate($model,$update,$remove){
		// // TODO test
		// $file1 = TEMP_FILES . 'reset-update.txt';
		// $handle = fopen($file1, 'a+');	// a为追加模式
		// foreach ($update as $sourceID => $parentLevel) {
		//     fwrite($handle, $sourceID.'=>'.$parentLevel . "\n");
		// }
		// fclose($handle);
		// $file2 = TEMP_FILES . 'reset-remove.txt';
		// file_put_contents($file2, implode(',', $remove).',', FILE_APPEND);
		// return;

		// 根据parentID找到记录，更新为parent.parentLevel+parentID
		$updateTmp = array();
		foreach ($update as $sourceID => $parentLevel) {
		    $updateTmp[] = array(
				'sourceID', $sourceID,
				'parentLevel', $parentLevel,
			);
		}
		if (!empty($updateTmp)) {
			$res = $model->saveAll($updateTmp);
			// $file1 = TEMP_FILES . 'reset-update.txt';
			// file_put_contents($file1, 'update:'.$res.';ids:'.implode(',',array_keys($update))."\n", FILE_APPEND);
		}
		write_log('resetSourceLevel-update:'.count($updateTmp), 'repair');
		// 找不到记录的，不直接删除，更新回parentID=parentLevel[-2],name=name@time——已存在会重名，所以需要更新name
		$time = date('YmdHis');
		foreach (array_chunk($remove, 500) as $ids) {
			$sql = "UPDATE io_source SET parentID = SUBSTRING_INDEX(SUBSTRING_INDEX(parentLevel, ',', -2), ',', 1), name = CONCAT(name, '@', '{$time}')
				WHERE sourceID IN (" . implode(',', $ids) . ")";
			$res = $model->execute($sql);
			// $file2 = TEMP_FILES . 'reset-update(remove).txt';
			// file_put_contents($file2, 'update(remove):'.$res.';ids:'.implode(',',$ids)."\n", FILE_APPEND);
		}
		write_log('resetSourceLevel-update(mismatch):'.count($remove), 'repair');
	}

	/**
	 * 更新异常层级文件
	 * @return void
	 */
	public function updateSourceLevel() {
		KodUser::checkRoot();
		$model = Model('Source');

		echoLog(TEMP_FILES);

		// 1.检测状态异常文件
		$this->errData2Recycle($model);
		
		// 2.更新层级异常文件
		// update io_source set parentLevel = ',0,13511771,15453311,14511308,' where parentID = 14511308;
		$this->resetSourceLevel($model);
	}


	/**
	 * 清理“物理文件存在、但 io_file 无对应记录”的孤儿文件（两阶段确认）
	 *
	 * 用法（管理员）:
	 * 1. 先列出: /?admin/repair/clearOrphanFile&path={io:3}
	 *    - 可选 limit=100000 限制列表数量, includeHidden=1 是否包含隐藏文件;
	 *    - 可选 exclude=名称1,名称2 排除指定path下本层的文件/文件夹(也支持相对子路径, 如 202602/temp);
	 *    - path 支持 {io:3} 或 {io:3}/子目录;
	 *    - 使用 storeImport 插件的扫描驱动(支持 local/oss/s3 等存储)。
	 *    - 列表写入TEMP_FILES临时文件, 页面仅输出扫描进度(单行刷新)和摘要, 避免浏览器输出大量数据卡死。
	 * 2. 确认后删除: 在列表输出的地址后追加 &done=1
	 *    - 删除前会再次检查 io_file, 若期间已产生引用则自动跳过。
	 */
	public function clearOrphanFile(){
		KodUser::checkRoot();
		ignore_timeout();

		$path = isset($this->in['path']) ? trim($this->in['path']) : '';
		$path = trim($path, '/');
		if ($path == '') {
			echoLog('请指定存储路径参数, 例如: path={io:3}');exit;
		}
		$parse = KodIO::parse($path);
		$storageID = !empty($parse['id']) ? intval($parse['id']) : 0;
		if ($parse['type'] != KodIO::KOD_IO || $storageID <= 0) {
			echoLog('存储路径参数格式错误, 应为 {io:存储ID} 或 {io:存储ID}/子目录; 当前: '.$path);exit;
		}

		$store = Model('Storage')->listData($storageID);
		if (!$store || empty($store['driver'])) {
			echoLog('存储(id='.$storageID.')不存在, 请检查存储配置。');exit;
		}
		$chks = Model('Storage')->checkConfig($store, true);
		if ($chks !== true) {
			echoLog('存储(id='.$storageID.')无法连接'.$chks);exit;
		}

		$limit  = max(1, intval(_get($this->in, 'limit', 100000)));
		$includeHidden = intval(_get($this->in, 'includeHidden', 0));
		$exclude = isset($this->in['exclude']) ? $this->in['exclude'] : '';
		$exclude = array_values(array_filter(array_map('trim', explode(',', $exclude))));
		$cacheKey = 'repair.orphan.file.'.$storageID.'.'.md5($path.'|'.$includeHidden.'|'.$limit.'|'.implode(',', $exclude));

		// 二次确认后执行删除
		if (isset($this->in['done'])) {
			$data = Cache::get($cacheKey);
			if (!$data || !is_array($data) || empty($data['list'])) {
				echoLog('未找到待清理列表缓存, 请先执行不带done参数的列表步骤。');exit;
			}
			if (!isset($data['path']) || $data['path'] != $path) {
				echoLog('缓存参数path与当前请求不一致, 请重新执行列表步骤。');exit;
			}
			return $this->orphanFileDelete($cacheKey, $data);
		}

		echoLog('开始扫描存储: '.$path);
		echoLog('筛选条件: 物理文件存在, 且 io_file 中无对应 path 记录; 列表上限: '.$limit.'; 跳过隐藏文件: '.($includeHidden ? '否' : '是').'; 排除项: '.($exclude ? implode(',', $exclude) : '无'));
		try {
			if (!Action('storeImportPlugin')) {throw new Exception('本接口依赖于【存储导入】插件，请先安装');}
			$data = $this->orphanFileScan($storageID, $path, $limit, $includeHidden, $exclude);
		} catch (Exception $e) {
			echoLog('扫描失败: '.$e->getMessage());exit;
		}
		$count = count($data['list']);
		echoLog('扫描完成, 共找到残留物理文件 '.$count.' 个, 总大小 '.size_format($data['sizeTotal']).'。');
		if ($count == 0) {
			echoLog('无需处理。');exit;
		}
		if ($data['overLimit']) {
			echoLog('注意: 命中数量达到列表上限, 仅列出前 '.$limit.' 个; 请缩小 path 范围或调大 limit 后重新执行。');
		}
		// 清单写入临时文件, 页面不再逐条输出
		$listFile = TEMP_FILES.'orphan-file-'.date('YmdHis').'-'.rand_string(6).'.txt';
		$handle = fopen($listFile, 'w');
		foreach ($data['list'] as $item) {
			fwrite($handle, $item['path']."\n");
		}
		fclose($handle);
		$data['listFile'] = $listFile;
		$data['path'] = $path;
		$data['storageID'] = $storageID;
		Cache::set($cacheKey, $data, 3600);
		echoLog('残留文件列表已写入: '.$listFile);
		echoLog('如确认需要删除以上 '.$count.' 个文件, 请在地址中追加参数后再次访问: &done=1');
		exit;
	}
	// 使用 storeImport 插件驱动递归扫描(local/oss/s3 等)
	private function orphanFileScan($storageID, $path, $limit, $includeHidden, $exclude){
		$api = Action('storeImportPlugin')->api($path);
		$result = array();$sizeTotal = 0;$overLimit = false;
		$batch = array();$batchMax = 2000;
		$list = $api->listPath($path, 200000);
		$driver = IO::init($path);
		$scanned = 0;
		foreach ($list as $items) {
			if (!is_array($items)) continue;
			foreach ($items as $item) {
				if (!empty($item['folder'])) continue;
				$scanned++;
				$kodPath = rtrim($driver->getPathOuter($item['path']), '/');
				if ($kodPath == '' || $this->orphanPathExcluded($kodPath, $path, $exclude) || !$this->orphanPathCheckHidden($kodPath, $includeHidden)) continue;
				$batch[] = array(
					'path'       => $kodPath,
					'size'       => intval($item['size']),
					'modifyTime' => intval($item['modifyTime']),
				);
				if (count($batch) >= $batchMax) {
					$this->orphanFileFilter($storageID, $batch, $result, $sizeTotal);
					$batch = array();
					echoLog('扫描中: 已处理 '.$scanned.' 个文件, 发现残留 '.count($result).' 个...', true);
					if (count($result) >= $limit) {
						$overLimit = true;
						break 2;
					}
				}
			}
		}
		if ($batch && count($result) < $limit) {
			$this->orphanFileFilter($storageID, $batch, $result, $sizeTotal);
			echoLog('扫描中: 已处理 '.$scanned.' 个文件, 发现残留 '.count($result).' 个...', true);
		}
		if (count($result) > $limit) {
			$result = array_slice($result, 0, $limit);
			$overLimit = true;
		}
		return array('list' => $result, 'sizeTotal' => $sizeTotal, 'overLimit' => $overLimit);
	}
	// 排除判断: 匹配指定path下的相对路径(本层名称或子路径, 目录排除时其后代一并排除)
	private function orphanPathExcluded($kodPath, $path, $exclude){
		if (!$exclude) return false;
		$pathTrim = rtrim($path, '/');
		$rel = ltrim(substr($kodPath, strlen($pathTrim)), '/');
		if ($rel == '') return false;
		foreach ($exclude as $item) {
			if ($rel == $item || strpos($rel, $item.'/') === 0) return true;
		}
		return false;
	}
	// 隐藏文件/目录判断(相对 {io:id} 路径)
	private function orphanPathCheckHidden($kodPath, $includeHidden){
		if ($includeHidden) return true;
		$pos = strpos($kodPath, '/');
		$rel = $pos === false ? '' : substr($kodPath, $pos + 1);
		foreach (explode('/', $rel) as $part) {
			if ($part != '' && substr($part, 0, 1) == '.') return false;
		}
		return true;
	}
	// 过滤一批文件: io_file 中已存在(虚拟路径或驱动内部路径)则视为已引用
	private function orphanFileFilter($storageID, $batch, &$result, &$sizeTotal){
		$paths = array();
		foreach ($batch as $item) {
			$paths[] = $item['path'];
		}
		$paths = array_values(array_unique($paths));
		$exists = array();
		for ($i = 0; $i < count($paths); $i += 5000) {
			$sub = array_slice($paths, $i, 5000);
			$where = array('ioType' => $storageID, 'path' => array('in', $sub));
			$list = Model('File')->field('path')->where($where)->select();
			if ($list) {
				foreach ($list as $row) {
					$exists[$row['path']] = 1;
				}
			}
		}
		foreach ($batch as $item) {
			if (isset($exists[$item['path']])) continue;
			$result[] = $item;
			$sizeTotal += intval($item['size']);
		}
	}
	// 按确认后的列表删除物理文件
	private function orphanFileDelete($cacheKey, $data){
		$list = $data['list'];
		$storageID = intval($data['storageID']);
		echoLog('开始删除残留物理文件, 共 '.count($list).' 个...');
		$ok = $fail = $skip = 0;$sizeFreed = 0;
		foreach ($list as $i => $item) {
			// 删除前复查: 期间是否新增了 io_file 引用
			$where = array('ioType' => $storageID, 'path' => $item['path']);
			if (Model('File')->where($where)->find()) {
				$skip++;
				echoLog(($i + 1).'. 已被 io_file 引用, 跳过: '.$item['path']);
				continue;
			}
			if (!IO::exist($item['path'])) {
				$skip++;
				echoLog(($i + 1).'. 物理文件已不存在, 跳过: '.$item['path']);
				continue;
			}
			try {
				$res = IO::remove($item['path'], false);
			} catch (Exception $e) {
				$res = false;
			}
			if ($res === false || IO::exist($item['path'])) {
				$fail++;
				echoLog(($i + 1).'. 删除失败: '.$item['path']);
			} else {
				$ok++;
				$sizeFreed += intval($item['size']);
				write_log(array('clearOrphanFile', $item['path']), 'sourceRepair');
			}
			echoLog('已删除: '.$ok.'; 失败: '.$fail.'; 跳过: '.$skip, true);
		}
		Cache::remove($cacheKey);
		if (!empty($data['listFile'])) {
			del_file($data['listFile']);
		}
		echoLog('删除完成! 成功 '.$ok.' 个, 失败 '.$fail.' 个, 跳过 '.$skip.' 个, 释放 '.size_format($sizeFreed).'。');
		exit;
	}

	/**
	 * 按io清除异常文件
	 * 传参io=1,2,3，不传递时获取已不存在于存储列表的io_file.ioType记录进行清除
	 * @return void
	 */
	public function clearErrIOFile(){
		KodUser::checkRoot();
		echoLog('本接口用于清除指定io（存储id）关联的所有文件数据，参数示例：io=1,2,3。如不指定io值，将从io_file中获取所有已不存在于存储列表的io进行处理');
		$io = _get($this->in,'io','');
		if ($io) {
			$list = explode(',', $io);
			$list = array_unique(array_filter($list));
			$where = array('ioType'=>array('in',$list));
		} else {
			$list = Model('Storage')->listData();
			$list = array_to_keyvalue($list, '', 'id');
			if (empty($list)) {
				echoLog('存储列表为空，系统异常！');exit;
			}
			$where = array('ioType'=>array('not in',$list));
		}
		// 获取io_file记录
		$list = Model('File')->where($where)->field('fileID,ioType')->select();
		// pr($list);exit;
		if (empty($list)) {
			echoLog('没有需要清理的数据');exit;
		}
		$data = array('io'=>array(),'file'=>array());
		foreach ($list as $item) {
			$data['io'][] = $item['ioType'];
			$data['file'][] = $item['fileID'];
		}
		if ($this->in['done'] != '1') {
			$ioArr = array_unique($data['io']);
			echoLog('搜索到'.count($ioArr).'个存储（io='.implode(',',$ioArr).'）共'.count($data['file']).'个文件，如需清理，请在地址中追加参数后再次访问：&done=1');
			exit;
		}
		// 根据fileID获取sourceID列表
		$where = array('fileID'=>array('in',$data['file']));
		$list = Model('Source')->where($where)->field('sourceID')->select();
		// 没有io_source记录，直接清除io_file记录
		if (empty($list)) {
			Model('File')->where($where)->delete();
			echoLog('成功清除'.count($data['file']).'条io_file记录');exit;
		}
		$this->in['sourceID'] = implode(',', array_to_keyvalue($list,'','sourceID'));
		$this->clearSource();	// TODO 这里会删物理文件，需要调整一下或传个参数
	}

	/**
	 * 按fileID清除异常文件
	 * @return void
	 */
	public function clearErrFile(){
		KodUser::checkRoot();
		echoLog('本接口用于清除指定fileID的所有文件数据（不删除物理文件），参数示例：fileID=1,2,3');
		$ids = array_filter(explode(',', $this->in['fileID']));
		if (empty($ids)) {
			echoLog('请指定参数fileID');exit;
		}
		// 获取io_file记录
		$where = array('fileID' => array('in', $ids));
		$fileList = Model('File')->where($where)->field('fileID')->select();
		$sourceList = Model('Source')->where($where)->field('sourceID')->select();
		if (empty($fileList) && empty($sourceList)) {
			echoLog('没有需要清理的数据');exit;
		}
		$sCnt = is_array($sourceList) ? count($sourceList) : 0;
		$fCnt = is_array($fileList) ? count($fileList) : 0;
		if ($this->in['done'] != '1') {
			echoLog('搜索到'.$sCnt.'条io_source记录，'.$fCnt.'条io_file记录，如确定清理，请在地址中追加参数后再次访问：&done=1');exit;
		}

		// 没有io_source记录，直接清除io_file记录
		if (empty($sourceList)) {
			Model('File')->where($where)->delete();
			echoLog('成功清除'.$fCnt.'条io_file记录');exit;
		}
		$this->in['sourceID'] = implode(',', array_to_keyvalue($sourceList,'','sourceID'));
		$this->clearSource();
	}

	/**
	 * 将指定文件从指定用户回收站还原——仅限系统数据
	 * @return void
	 */
	public function restoreRecyclePath(){
	    KodUser::checkRoot();
	    $userID = $this->in['userID'];
	    $sourceID = $this->in['sourceID'];
	    if (empty($sourceID) || empty($userID)) {
			echoLog('参数缺失：sourceID=id,id/userID=id');exit;
		}
	    $pathArr = explode(',', $sourceID);
	    $res = Model('SourceRecycle')->restore($pathArr,$userID);
	    pr($res);exit;
	}

	
}
