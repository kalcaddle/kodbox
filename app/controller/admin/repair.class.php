<?php
/**
 * 计划任务
 */
class adminRepair extends Controller {
	function __construct()    {
		parent::__construct();
	}
	
	/**
	 * 清空修复异常数据(php终止,断电,宕机等引起数据库表错误的进行处理;)
	 * 5个小时执行一次; 凌晨2点才执行;
	 */
	public function autoReset(){
		$cacheKey = 'autoReset';
		if(date('H',time()) != '2' ) return;
		if(time() - intval(Cache::get($cacheKey)) > 3600*5 ) return;
		Cache::set($cacheKey,time());
		http_close();

		$this->resetSourceEmpty();		// source中表清理; sourceHash为空或所属关系错误的条目删除;
		$this->resetShareTo();			// share_to中存在share中不存在的数据清理
		$this->resetShare();			// share中存在,source已不存在的内容清理
		$this->resetSourceFile();		// source中的文件fileID,file中不存在清理;
		$this->resetFileSource();		// file中存在,source中不存在的进行清理
		$this->resetSourceHistory();	// 文件历史版本,fileID不存在的内容清理;
		$this->resetFileLink();			// 重置fileID的linkCount引用计数(source,sourceHistory);
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
		$pageNum = 5000;$page = 1;$errorNum = 0;
		$list = $model->selectPage($pageNum,$page);
		
		$task = new Task($taskType,'',$list['pageInfo']['totalNum']);
		while($list && $page < $list['pageInfo']['pageTotal']){
			$removeSource = array();$removeFiles = array();
			foreach ($list['list'] as $item) {
				$levelEnd = ','.$item['parentID'].',';
				$levelEndNow = substr($item['parentLevel'],- strlen($levelEnd));
				if( $item['sourceHash'] == '' ||
					$levelEndNow != $levelEnd
				){
					$task->task['currentTitle'] = $errorNum .'个不存在';
					write_log(array($taskType,$item),'sourceClear');$errorNum ++;
					
					$removeSource[] = $item['sourceID'];
					$removeFiles[]  = $item['fileID'];
				}
				$task->update(1);
			}
			$model->removeRelevance($removeSource,$removeFiles); // 优化性能;
			$page ++;$list = $model->selectPage($pageNum,$page);
		}
		$task->end();
	}
	
	// source对应fileID 不存在处理;
	public function resetSourceFile(){
		$taskType ='resetSourceFile';
		$model = Model("Source");
		$modelFile = Model("File");
		$pageNum = 5000;$page = 1;$errorNum = 0;
		$list = $model->selectPage($pageNum,$page);
		
		$task = new Task($taskType,'',$list['pageInfo']['totalNum']);
		while($list && $page < $list['pageInfo']['pageTotal']){
			$removeSource = array();$removeFiles = array();
			foreach ($list['list'] as $item) {
				if($item['isFolder'] == '0' && !$modelFile->find($item['fileID'])){
					$task->task['currentTitle'] = $errorNum .'个不存在';
					write_log(array($taskType,$item),'sourceClear');$errorNum ++;
					$removeSource[] = $item['sourceID'];
					$removeFiles[]  = $item['fileID'];
				}
				$task->update(1);
			}
			$model->removeRelevance($removeSource,$removeFiles); // 优化性能;
			$page ++;$list = $model->selectPage($pageNum,$page);
		}
		$task->end();
	}
		
	public function resetFileHash(){
		$taskType ='resetFileHash';
		$model = Model('File');
		$pageNum = 5000;$page = 1;$errorNum = 0;
		$list = $model->selectPage($pageNum,$page);
		
		$task = new Task($taskType,'',$list['pageInfo']['totalNum']);
		while($list && $page < $list['pageInfo']['pageTotal']){
			foreach ($list['list'] as $item) {
				if(!$item['hashSimple'] || !$item['hashMd5']){
					$data = array('hashSimple'=>IO::hashSimple($item['path']) );
					if(!$item['hashMd5']){
						$data['hashMd5'] = IO::hashMd5($item['path']);
					}
					$task->task['currentTitle'] = $errorNum .'个修改';
					write_log(array($taskType,$item),'sourceClear');$errorNum ++;
					$model->where(array('fileID'=>$item['fileID']))->save($data);
				}
				$task->update(1);
			}
			$page ++;$list = $model->selectPage($pageNum,$page);
		}
		$task->end();
	}
	
	// 重置分享,内部协作分享数据;(删除文件夹,删除对应分享,及内部协作分享)
	public function resetShareTo(){
		$taskType ='resetShareTo';
		$model = Model('share_to');$modelShare = Model("share");
		$pageNum = 5000;$page = 1;$errorNum = 0;
		$list = $model->selectPage($pageNum,$page);
		
		$task = new Task($taskType,'',$list['pageInfo']['totalNum']);
		while($list && $page < $list['pageInfo']['pageTotal']){
			foreach ($list['list'] as $item) {
				$where = array("shareID"=>$item['shareID']);
				if(!$modelShare->where($where)->find()){
					$task->task['currentTitle'] = $errorNum .'个不存在';
					write_log(array($taskType,$item),'sourceClear');$errorNum ++;
					$model->where($where)->delete();
				}
				$task->update(1);
			}
			$page ++;$list = $model->selectPage($pageNum,$page);
		}
		$task->end();
	}
	
	// 重置分享,内部协作分享数据;(删除文件夹,删除对应分享,及内部协作分享)
	public function resetShare(){
		$taskType ='resetShare';
		$model = Model('share');$modelSource = Model("Source");
		$pageNum = 5000;$page = 1;$errorNum = 0;
		$list = $model->selectPage($pageNum,$page);
		
		$task = new Task($taskType,'',$list['pageInfo']['totalNum']);
		while($list && $page < $list['pageInfo']['pageTotal']){
			foreach ($list['list'] as $item) {
				$where = array("sourceID"=>$item['sourceID']);
				if($item['sourceID'] != '0' && !$modelSource->where($where)->find()){
					$task->task['currentTitle'] = $errorNum .'个不存在';
					write_log(array($taskType,$item),'sourceClear');$errorNum ++;
									
					$where = array('shareID'=>$item['shareID']);
					$model->where($where)->delete();
					Model('share_to')->where($where)->delete();
				}
				$task->update(1);
			}
			$page ++;$list = $model->selectPage($pageNum,$page);
		}
		$task->end();
	}
	

	// file表中存在, source表中不存在的进行清除;历史记录表等;
	public function resetFileSource(){
		$taskType ='resetFileSource';
		$model = Model("File");$modelSource = Model("Source");
		$pageNum = 5000;$page = 1;$errorNum = 0;
		$list = $model->selectPage($pageNum,$page);
		
		$task = new Task($taskType,'',$list['pageInfo']['totalNum']);
		while($list && $page < $list['pageInfo']['pageTotal']){
			foreach ($list['list'] as $item) {
				$where = array("fileID"=>$item['fileID']);
				if(!$modelSource->where($where)->find()){
					$task->task['currentTitle'] = $errorNum .'个不存在';
					write_log(array($taskType,$item),'sourceClear');$errorNum ++;
					IO::remove($item['path']);
					$model->where($where)->delete();
				}
				$task->update(1);
			}
			$page ++;$list = $model->selectPage($pageNum,$page);
		}
		$task->end();
	}
	
	
	// File表中,io不存在的文件进行处理;（被手动删除的）
	public function resetFileLink(){
		$taskType ='resetFileLink';
		$model = Model('File');
		$modelSource = Model("Source");$modelHistory = Model("SourceHistory");
		$pageNum = 5000;$page = 1;$errorNum = 0;
		$list = $model->selectPage($pageNum,$page);
		
		$task = new Task($taskType,'',$list['pageInfo']['totalNum']);
		while($list && $page < $list['pageInfo']['pageTotal']){
			foreach ($list['list'] as $item) {
				if( IO::exist($item['path']) ){
					$model->resetFile($item);
				}else{
					$task->task['currentTitle'] = $errorNum .'个不存在';
					write_log(array($taskType,$item),'sourceClear');$errorNum ++;
					
					// $model->remove($item['fileID']);
					// $where = array("fileID"=>$item['fileID']);
					// $modelSource->where($where)->delete();
					// $modelHistory->where($where)->delete();
				}
				$task->update(1);
			}
			$page ++;$list = $model->selectPage($pageNum,$page);
		}
		$task->end();
	}

	public function resetSourceHistory(){		
		$taskType ='resetSourceHistory';
		$model = Model('SourceHistory');$modelSource = Model("Source");
		$pageNum = 5000;$page = 1;$errorNum = 0;
		$list = $model->selectPage($pageNum,$page);
		
		$task = new Task($taskType,'',$list['pageInfo']['totalNum']);
		while($list && $page < $list['pageInfo']['pageTotal']){
			foreach ($list['list'] as $item) {
				$where = array("sourceID"=>$item['sourceID']);
				if( !$modelSource->where($where)->find() ){
					$task->task['currentTitle'] = $errorNum .'个不存在';
					write_log(array($taskType,$item),'sourceClear');$errorNum ++;
					$model->where($where)->delete();
				}
				$task->update(1);
			}
			$page ++;$list = $model->selectPage($pageNum,$page);
		}
		$task->end();
	}
}