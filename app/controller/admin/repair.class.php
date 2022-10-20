<?php
/**
 * 计划任务
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
	 * 手动执行：
	 * /?admin/repair/autoReset&done=1/2，done=1为清理数据；done=2为实际删除不存在的文件记录
	 */
	public function autoReset(){
		$done = isset($this->in['done']) ? intval($this->in['done']) : 0;
		if ($done == 2) {	// 计划任务执行
			$msg = $this->resetPathSource();
			echoLog('异常数据清理，'.$msg.'可在后台任务管理进行中止。');
		} else {
			$cacheKey = 'autoReset';
			$lastTime = Cache::get($cacheKey);
			$lastTime = false;//debug ;
			if($lastTime && time() - intval($lastTime) < 3600*6 ){
				echo '最后一次执行未超过6小时!';return;
			}
			Cache::set($cacheKey,time());
			echoLog('异常数据清理，可在后台任务管理进行中止。');
		}
		echoLog('请求参数done=1时，不直接删除已缺失的物理文件，可查看“物理文件不存在的数据记录”，确认需要删除后，再执行done=2进行删除');
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
		write_log('手动清理执行完成！', 'sourceClear');
		echoLog('=====================================================');
		echoLog('手动清理执行完成！');
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
		return '执行目录: '.$path.'，';
	}
	private function pathWhere($model, $file=false, $shareTo=false) {
		if (!$this->resetPathKey) return;
		$cache = Cache::get($this->resetPathKey);
		if (!$cache) {
			echoLog('缓存数据异常，请尝试重新执行！');exit;
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
	 * 需先执行autoReset方法，并查看sourceClear日志【resetFileLink--已不存在的物理文件】，确认是否需要清除
	 * @return void
	 */
	public function clearErrorFile(){
		$cache = Cache::get('clear_file_'.date('Ymd'));
		if (!$cache || !is_array($cache)) {
			echoLog('没有缺失的物理文件记录！');
			echoLog('注意：此记录从缓存中获取，缓存数据在执行done=1时产生，因此请务必先执行done=1。');
			exit;
		}
		echoLog('clearErrorFile，物理文件不存在的数据处理；');
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
		echoLog('clearErrorFile，finished:清除已不存在的物理文件记录共' . $result['file'] . '条，涉及source记录' . $result['source'] . '条！');
		// echo '<p style="font-size:14px;">清除已不存在的物理文件记录共' . $result['file'] . '条，涉及source记录' . $result['source'] . '条！</p>';
		exit;
	}

	/**
	 * source表中异常数据处理: 
	 * 1. parentID为0, 但 parentLevel不为0情况处理;
	 * 2. parentID 不存在处理;
	 * 3. sourchHash 为空的数据;
	 */
	public function resetSourceEmpty(){
		$taskType ='resetSourceEmpty';
		$model = Model("Source");
		$pageNum = $this->pageCount;$page = 1;$errorNum = 0;
		$this->pathWhere($model);
		$list = $model->selectPage($pageNum,$page);
		
		$total = 0;
		echoLog($taskType.'，source表异常数据处理；');$timeStart = timeFloat();
		$task = new Task($taskType,'',$list['pageInfo']['totalNum']);
		while($list && $page <= $list['pageInfo']['pageTotal']){
			$parentSource = $removeSource = $removeFiles = array();
			foreach ($list['list'] as $item) {
				$levelEnd = ','.$item['parentID'].',';
				$levelEndNow = substr($item['parentLevel'],- strlen($levelEnd));
				if( $item['sourceHash'] == '' ||
					$levelEndNow != $levelEnd
				){
					$errorNum ++;
					$task->task['currentTitle'] = $errorNum .'个不存在';
					write_log(array($taskType,$item),'sourceClear');
					$parentSource[] = $item['parentID'];
					$removeSource[] = $item['sourceID'];
					$removeFiles[]  = $item['fileID'];
				}
				$total++;
				echoLog('['.$total.'] '.($errorNum > 0 ? $task->task['currentTitle'].';' : ''), true);
				$task->update(1);
			}
			$model->removeRelevance($removeSource,$removeFiles); // 优化性能;
			$this->folderSizeReset($parentSource);
			$page ++;
			$this->pathWhere($model);
			$list = $model->selectPage($pageNum,$page);
		}
		echoLog($taskType.'，total: '.$total.'; error: '.$errorNum.'; t='.(timeFloat() - $timeStart).'s');
		$task->end();
	}
	
	// source对应fileID 不存在处理;
	public function resetSourceFile(){
		$taskType ='resetSourceFile';
		$model = Model("Source");
		$modelFile = Model("File");
		$pageNum = $this->pageCount;$page = 1;$errorNum = 0;
		$this->pathWhere($model);
		$list = $model->selectPage($pageNum,$page);
		
		$total = 0;$timeStart = timeFloat();
		echoLog($taskType.'，source表空数据处理；');
		$task = new Task($taskType,'',$list['pageInfo']['totalNum']);
		while($list && $page <= $list['pageInfo']['pageTotal']){
			$parentSource = $removeSource = $removeFiles = array();
			foreach ($list['list'] as $item) {
				if($item['isFolder'] == '0' && !$modelFile->find($item['fileID'])){
					$errorNum ++;
					$task->task['currentTitle'] = $errorNum .'个不存在';
					write_log(array($taskType,$item),'sourceClear');
					$parentSource[] = $item['parentID'];
					$removeSource[] = $item['sourceID'];
					$removeFiles[]  = $item['fileID'];
				}
				$total++;
				echoLog('['.$total.'] '.($errorNum > 0 ? $task->task['currentTitle'].';' : ''), true);
				$task->update(1);
			}
			$model->removeRelevance($removeSource,$removeFiles); // 优化性能;
			$this->folderSizeReset($parentSource);
			$page ++;
			$this->pathWhere($model);
			$list = $model->selectPage($pageNum,$page);
		}
		echoLog($taskType.'，total: '.$total.'; error: '.$errorNum.'; t='.(timeFloat() - $timeStart).'s');
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
		$taskType ='resetFileHash';
		$model = Model('File');
		$pageNum = $this->pageCount;$page = 1;$errorNum = 0;
		$this->pathWhere($model, true);
		$list = $model->selectPage($pageNum,$page);
		
		$task = new Task($taskType,'',$list['pageInfo']['totalNum']);
		while($list && $page <= $list['pageInfo']['pageTotal']){
			foreach ($list['list'] as $item) {
				if(!$item['hashSimple'] || !$item['hashMd5']){
					$data = array('hashSimple'=>IO::hashSimple($item['path']) );
					if(!$item['hashMd5']){
						$data['hashMd5'] = IO::hashMd5($item['path']);
					}
					$errorNum ++;
					$task->task['currentTitle'] = $errorNum .'个修改';
					write_log(array($taskType,$item),'sourceClear');
					$model->where(array('fileID'=>$item['fileID']))->save($data);
				}
				$task->update(1);
			}
			$page ++;
			$this->pathWhere($model, true);
			$list = $model->selectPage($pageNum,$page);
		}
		$task->end();
	}
	
	// 重置分享,内部协作分享数据;(删除文件夹,删除对应分享,及内部协作分享)
	public function resetShareTo(){
		$taskType ='resetShareTo';
		$model = Model('share_to');$modelShare = Model("share");
		$pageNum = $this->pageCount;$page = 1;$errorNum = 0;
		$this->pathWhere($model, false, true);
		$list = $model->selectPage($pageNum,$page);
		
		$total = 0;$timeStart = timeFloat();
		echoLog($taskType.'，协作分享异常数据处理；');
		$task = new Task($taskType,'',$list['pageInfo']['totalNum']);
		while($list && $page <= $list['pageInfo']['pageTotal']){
			foreach ($list['list'] as $item) {
				$where = array("shareID"=>$item['shareID']);
				if(!$modelShare->where($where)->find()){
					$errorNum ++;
					$task->task['currentTitle'] = $errorNum .'个不存在';
					write_log(array($taskType,$item),'sourceClear');
					$model->where($where)->delete();
				}
				$total++;
				echoLog('['.$total.'] '.($errorNum > 0 ? $task->task['currentTitle'].';' : ''), true);
				$task->update(1);
			}
			$page ++;
			$this->pathWhere($model, false, true);
			$list = $model->selectPage($pageNum,$page);
		}
		echoLog($taskType.'，total: '.$total.'; error: '.$errorNum.'; t='.(timeFloat() - $timeStart).'s');
		$task->end();
	}
	
	// 重置分享,内部协作分享数据;(删除文件夹,删除对应分享,及内部协作分享)
	public function resetShare(){
		$taskType ='resetShare';
		$model = Model('share');$modelSource = Model("Source");
		$pageNum = $this->pageCount;$page = 1;$errorNum = 0;
		$this->pathWhere($model);
		$list = $model->selectPage($pageNum,$page);
		
		$total = 0;$timeStart = timeFloat();
		echoLog($taskType.'，外链分享异常数据处理；');
		$task = new Task($taskType,'',$list['pageInfo']['totalNum']);
		while($list && $page <= $list['pageInfo']['pageTotal']){
			foreach ($list['list'] as $item) {
				$where = array("sourceID"=>$item['sourceID']);
				if($item['sourceID'] != '0' && !$modelSource->where($where)->find()){
					$errorNum ++;
					$task->task['currentTitle'] = $errorNum .'个不存在';
					write_log(array($taskType,$item),'sourceClear');
									
					$where = array('shareID'=>$item['shareID']);
					$model->where($where)->delete();
					Model('share_to')->where($where)->delete();
				}
				$total++;
				echoLog('['.$total.'] '.($errorNum > 0 ? $task->task['currentTitle'].';' : ''), true);
				$task->update(1);
			}
			$page ++;
			$this->pathWhere($model);
			$list = $model->selectPage($pageNum,$page);
		}
		echoLog($taskType.'，total: '.$total.'; error: '.$errorNum.'; t='.(timeFloat() - $timeStart).'s');
		$task->end();
	}
	

	// file表中存在, source表中不存在的进行清除;历史记录表等;
	public function resetFileSource(){
		$taskType ='resetFileSource';
		$model = Model("File");$modelSource = Model("Source");
		$modelHistory = Model('SourceHistory');;
		$pageNum = $this->pageCount;$page = 1;$errorNum = 0;
		$this->pathWhere($model, true);
		$list = $model->selectPage($pageNum,$page);
		$stores = Model('Storage')->listData();
	    $stores = array_to_keyvalue($stores, '', 'id');	// 有效存储列表

		$total = 0;$timeStart = timeFloat();
		echoLog($taskType.'，file+source记录异常处理；');
		$task = new Task($taskType,'',$list['pageInfo']['totalNum']);
		while($list && $page <= $list['pageInfo']['pageTotal']){
			foreach ($list['list'] as $item) {
				$where = array("fileID"=>$item['fileID']);
				$findSource  = $modelSource->where($where)->find();
				$findHistory = $modelHistory->where($where)->find();
				if(!$findSource && !$findHistory){
					$errorNum ++;
					$task->task['currentTitle'] = $errorNum .'个不存在';
					write_log(array($taskType,$item),'sourceClear');
					// if (in_array($item['ioType'], $stores)) {IO::remove($item['path']);}
					$model->where($where)->delete();
				}
				$total++;
				echoLog('['.$total.'] '.($errorNum > 0 ? $task->task['currentTitle'].';' : ''), true);
				$task->update(1);
			}
			$page ++;
			$this->pathWhere($model, true);
			$list = $model->selectPage($pageNum,$page);
		}
		echoLog($taskType.'，total: '.$total.'; error: '.$errorNum.'; t='.(timeFloat() - $timeStart).'s');
		$task->end();
	}
	
	
	// File表中,io不存在的文件进行处理;（被手动删除的）
	public function resetFileLink(){
		$taskType ='resetFileLink';
		$model = Model('File');
		$pageNum = $this->pageCount;$page = 1;$errorNum = 0;
		$this->pathWhere($model, true);
		$list = $model->selectPage($pageNum,$page);
		$stores = Model('Storage')->listData();
	    $stores = array_to_keyvalue($stores, '', 'id');	// 有效存储列表
		
		$total = 0;$timeStart = timeFloat();
		echoLog($taskType.'，物理文件不存在的数据记录；');
		$cache = array();
		$rest = array('file' => 0, 'source' => 0);
		$task = new Task($taskType,'',$list['pageInfo']['totalNum']);
		while($list && $page <= $list['pageInfo']['pageTotal']){
			foreach ($list['list'] as $item) {
				$total++;
				echoLog('['.$total.']', true);
				$ioNone = in_array($item['ioType'], $stores);
				if($ioNone && IO::exist($item['path']) ){
					$model->resetFile($item);
				}else{
					$errorNum ++;
					$task->task['currentTitle'] = $errorNum .'个不存在';
					write_log(array($taskType.'--已不存在的物理文件',$item),'sourceClear');
					echoLog('——第'.$task->task['currentTitle'].';'.$item['path']);
					$cache[] = array(
						'fileID'	=> $item['fileID'],
						'linkCount' => $item['linkCount'],
					);
				}
				$task->update(1);
			}
			$page ++;
			$this->pathWhere($model, true);
			$list = $model->selectPage($pageNum,$page);
		}
		echoLog($taskType.'，total: '.$total.'; error: '.$errorNum.'; t='.(timeFloat() - $timeStart).'s');
		$task->end();

		if ($cache) Cache::set('clear_file_'.date('Ymd'), $cache);
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
		$taskType ='resetSourceHistory';
		$model = Model('SourceHistory');$modelSource = Model("Source");
		$modelFile = Model("File");
		$pageNum = $this->pageCount;$page = 1;$errorNum = 0;
		$this->pathWhere($model);
		$list = $model->selectPage($pageNum,$page);
		
		$total = 0;$timeStart = timeFloat();
		echoLog($taskType.'，历史版本异常数据处理；');
		$task = new Task($taskType,'',$list['pageInfo']['totalNum']);
		while($list && $page <= $list['pageInfo']['pageTotal']){
			foreach ($list['list'] as $item) {
				$where = array("sourceID"=>$item['sourceID']);
				if( !$modelSource->where($where)->find() ){
					$errorNum ++;
					$task->task['currentTitle'] = $errorNum .'个不存在';
					write_log(array($taskType,$item),'sourceClear');
					$model->where($where)->delete();
				}
				
				if( !$modelFile->where(array('fileID'=>$item['fileID']))->find()){
					$errorNum ++;
					$task->task['currentTitle'] = $errorNum .'个不存在';
					write_log(array($taskType.';fileError!',$item),'sourceClear');
					$model->where(array('fileID'=>$item['fileID']))->delete();
				}
				$total++;
				echoLog('['.$total.'] '.($errorNum > 0 ? $task->task['currentTitle'].';' : ''), true);
				$task->update(1);
			}
			$page ++;
			$this->pathWhere($model);
			$list = $model->selectPage($pageNum,$page);
		}
		echoLog($taskType.'，total: '.$total.'; error: '.$errorNum.'; t='.(timeFloat() - $timeStart).'s');
		$task->end();
	}

	/**
	 * 根据sourceID彻底删除文件，sourceID可传多个，如sourceID=1,2,3
	 * @return void
	 */
	public function clearSource(){
		echoLog('根据sourceID彻底删除关联文件！参数sourceID=1,2,3');
		$ids = $this->in['sourceID'];
		if (!$ids) {
			echoLog('无效的参数：sourceID！');exit;
		}
		// 1.根据sourceID查fileID
		$ids = array_filter(explode(',',$ids));
		$where = array(
			'isFolder' => 0,
			'sourceID' => array('in', $ids)
		);
		$list = Model('Source')->where($where)->field('sourceID,fileID')->select();
		if (empty($list)) {
			echoLog('找不到对应的source记录，请检查sourceID是否正确。');
			exit;
		}

		echoLog('删除开始：');
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
			echoLog('source记录：'.($i+1), true);
		}
		// 4.删除可能还存在的file记录——实际物理文件删除与否不影响
		if (!empty($file)) {
			$where = array('fileID'=>array('in', $file));
			Model('File')->where($where)->delete();
		}
		echoLog("删除完成！共删除source记录{$sCnt}条；file记录{$fCnt}条。");
	}
}