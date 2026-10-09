<?php

/**
 * 存储导入：扫描已存文件数据写入数据库
 */

class storeImportPlugin extends PluginBase{
	// 异常明细是否已写（一次导入只写一份：正常结束 / 异常捕获 / 致命错误收尾 三条路径共用）
	private $errDetailWritten = false;

	function __construct(){
		parent::__construct();
	}

	public function regist(){
		$this->hookRegist(array(
			'user.commonJs.insert' => 'storeImportPlugin.echoJs',
		));
	}
	public function echoJs(){
		$this->echoFile('static/main.js');
	}

	public function api($path){
		$parse	= KodIO::parse($path);
		$id = $parse['id'];
		static $drvClass = array();
		if (!$drvClass[$id]) {
			$store = Model('Storage')->driverInfo($id);
			include_once($this->pluginPath.'lib/driver/DriverBase.class.php');
			$drvClass[$id] = new impDriver($store);
		}
		return $drvClass[$id];
	}

	/**
	 * 检查原始目录文件数量
	 * @return void
	 */
	public function check(){
		KodUser::checkRoot();
		$pathFrom = Input::get('pathFrom','require');
		$parse = KodIO::parse($pathFrom);
		if ($parse['type'] != KodIO::KOD_IO) {
			show_json(LNG('storeImport.main.ioPathErr'), false);
		}
		$data = array('folder'=>0,'file'=>0);
		$list = $this->api($pathFrom)->listPathBatch($pathFrom, 200000);
		foreach ($list as $batch) {
			foreach ($batch as $item) {
				if ($item['folder']) {
					$data['folder']++;
				} else {$data['file']++;}
			}
		}
		show_json($data);
	}

	/**
	 * 数据导入（入口）
	 * @return void
	 */
	public function start(){
		KodUser::checkRoot();
		// 0.任务进度
		$this->getProcess();

		// 1.导入检查
		$pathFrom = Input::get('pathFrom','require');
		$pathTo	  = Input::get('pathTo','require');
		$pathFrom = trim($pathFrom, '/');
        $pathTo	  = trim($pathTo, '/');
		$taskId   = Input::get('taskId', 'require');
		// 免费版限制
		if (Model('SystemOption')->get('versionType') == 'A') {
			show_json(LNG('admin.restore.needVipTips'), false);
		}

		// 1.1 检查原始目录
		$parse = KodIO::parse($pathFrom);
		if ($parse['type'] != KodIO::KOD_IO) {
			show_json(LNG('storeImport.main.ioPathErr'), false);
		}
		$info = Model('Storage')->listData($parse['id']);
		if (!$info) {
			show_json(LNG('storeImport.main.ioStoreErr'), false);
		}
		$type = strtolower($info['driver']);
		$ioList = $this->api($pathFrom)->ioList;
		if (!in_array($type, $ioList['sg']) && !in_array($type, $ioList['s3'])) {
			show_json(LNG('storeImport.main.ioNotSupErr').$info['driver'], false);
		}
		$chks = Model('Storage')->checkConfig($info, true);
		if ($chks !== true) {
			show_json(LNG('storeImport.main.ioFromNetErr').$chks, false);
		}

		// 1.2 检查目标目录
		$parse = KodIO::parse($pathTo);
		if ($parse['type'] != KodIO::KOD_SOURCE) {
			show_json(LNG('storeImport.main.ioToErr'), false);
		}

		// 2. 开始导入
		$this->doImport($pathFrom, $pathTo);

		$data = Cache::get($taskId);
		show_json($data, true, 1);
	}

	// 导入进度
	private function getProcess(){
		$taskId = Input::get('taskId', 'require');
		$task = Task::get($taskId);	// 过期或未设置时，结果都为false
		if (!isset($this->in['process'])) {
			if ($task) {
				show_json(LNG('storeImport.task.rptErr'), false);
			}
			Cache::remove($taskId);
			return;
		}
		if ($this->in['kill']) {
			if (!$task) {$task = $this->taskGet($taskId);}
			if ($task) $this->taskKill($task);
			show_json('Task killed.');
		}
		$info  = 0;
		$cache = Cache::get($taskId);
		if ($cache) {
			$task = $cache; $info = 1;
			Cache::remove($taskId);
		}
		show_json($task, true, $info);
	}

	// 杀死任务
	private function taskKill($task, $msg='') {
		if ($task['status'] == 1 || $task['taskPercent'] == 1) return;	// register_shutdown_function正常也会触发，无需kill
		if (in_array($task['status'], array('kill', 'error'))) return;	// 避免死循环

		// 杀掉任务
		$status = 'kill';
		$desc = LNG('storeImport.task.stopByUser');
		if ($msg) {
			$status = 'error';
			$desc = $msg;
		}
		$taskId = $task['id'];
		$task['taskPercent'] = 1;
		$task['status'] = $status;
		$task['desc'] = $desc;
		Cache::set($taskId, $task);
		Task::kill($taskId);

		// 更新日志记录
		// $info = Model('StoreImport')->findByKey('taskId', $taskId);
		$info = $this->taskGet($taskId, true);
		if (!empty($info['id'])) {
			$update = array('status' => 2, 'taskInfo' => $task);
			$this->logEdit($info['id'], $update);
		}
	}

	/**
	 * 存储导入
	 * @param string $pathFrom	{io:2}/oldpath	{io:2}=>/var/usr/data
	 * @param string $pathTo	{source:1}
	 * @return void
	 */
	public function doImport($pathFrom, $pathTo){
		ignore_timeout();
		// Hook::bind('show_json',array($this,'showJson'));	// 没有必要
		// （当前方法）正常调用结束、手动kill（？）、系统级错误等都会触发，注意后续处理逻辑
		register_shutdown_function(function () {
			$err = error_get_last();
			if (!$err) return; // 无错误，正常结束
			// 只处理致命级错误，避免 warning/notice 等误触发
			$fatalErrors = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR);
			if (!in_array($err['type'], $fatalErrors)) return;
			$msg = _get($err, 'message', '');
			$this->showJson(array('data' => '任务异常中止，错误信息：'.$msg, 'code' => false));
        });

		// 1. 开始任务
		$taskId = Input::get('taskId', 'require');
		$task	= new Task($taskId, 'storeImport', 0, LNG('storeImport.main.dataImport'));
		$this->writeLog('开始导入任务：from=>'.$pathFrom.'; to=>'.$pathTo);

		// 记录日志
		$logId = $this->logAdd($pathFrom, $pathTo, $task->task);

		// 2. 分块获取原目录下列表（生成器），并进行批量导入
		$this->writeLog('开始批量导入');
		include_once($this->pluginPath.'lib/batchImport.class.php');
		$import = new batchImport($task);	// 每次创建新的实例，避免内存累积——实际占用差别不大，改为共用
		// 致命错误（OOM 等）也要尽量把异常明细写出去：正常结束/异常捕获路径会先写，这里用标志位保证只写一份
		register_shutdown_function(function () use ($import, $pathFrom, $pathTo, $taskId) {
			$err = error_get_last();
			$fatalErrors = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR);
			if (!$err || !in_array($err['type'], $fatalErrors)) return;
			$this->flushErrDetail($pathFrom, $pathTo, $taskId, $import, '任务异常中止：' . _get($err, 'message', ''));
		});

		$idx = 1;
		$task->task['currentTitle'] = LNG('storeImport.task.readdir').'-'.$idx;
		$task->update(0,true);
		$list = $this->api($pathFrom)->listPathBatch($pathFrom, 200000);	// 500000反而慢
		foreach ($list as $i => $batch) {
			if (!$batch) continue;
			$this->writeLog('开始任务批次（'.$idx.'）');
			try {
				$res = $import->import($pathFrom, $pathTo, $batch);
			} catch (Exception $e) {
				// show_json 会 exit（finally 不会执行），所以这里先把异常明细落盘再上报
				$this->logListErrors($this->api($pathFrom));
				$this->flushErrDetail($pathFrom, $pathTo, $taskId, $import, '任务异常中止：' . $e->getMessage());
				$this->showJson(array('data' => $e->getMessage(), 'code' => false));
			}
			$this->writeLog('结束任务批次（'.$idx.'）');
			$idx++;
			$task->task['currentTitle'] = LNG('storeImport.task.readdir').'-'.$idx;
			$task->update(0,true);
		}
		$this->writeLog("完成批量导入，共导入文件：{$task->task['taskFinished']}/{$task->task['taskTotal']}");
		// 2.1 源目录遍历阶段的异常（原来全是静默跳过，日志里看不到）
		$this->logListErrors($this->api($pathFrom));

		// 更新日志
		$update = array('status' => 1, 'taskInfo' => $task->task);
		$this->logEdit($logId, $update);

		// 3. 写“文件导入明细”（每类异常一条，含总览表头；没有异常则不建目录）
		$errFile = $this->flushErrDetail($pathFrom, $pathTo, $taskId, $import, '正常完成');

		// 3.1 整轮对账汇总：扫描数 / 入库数 / 各类跳过，便于判断“少文件、少字节”出在哪一步
		$stat = $import->getStat();
		$skipCharset = _get($stat, 'skipBadCharset', 0);
		$this->writeLog("【导入汇总】扫描到文件 {$stat['fileSeen']}，写入 io_source {$stat['sourceRows']} 行 / io_file {$stat['fileRows']} 行；"
			. "跳过：超255字符 {$stat['skipPathLen']}、编码/4字节字符 {$skipCharset}、父目录无法建立 {$stat['skipNoParent']}、相对路径为空 {$stat['skipEmptyRel']}；"
			. "目录：新建 {$stat['folderCreated']}、复用 {$stat['folderExisted']}、失败 {$stat['folderFailed']}；失败批次 {$stat['batchFail']}");
		$this->writeLog("【导入汇总】异常明细共 " . $import->errTotal() . " 条：" . $import->errSummaryText()
			. ($errFile ? "；明细文件：" . $errFile : "；（无异常，未生成明细文件）"));
		if ($stat['fileSeen'] != $stat['fileBuffered'] + $stat['skipPathLen'] + $skipCharset + $stat['skipNoParent'] + $stat['skipEmptyRel']) {
			$this->writeLog("【导入汇总】注意：扫描数与“入库+跳过”不相等，差额 "
				. ($stat['fileSeen'] - $stat['fileBuffered'] - $stat['skipPathLen'] - $skipCharset - $stat['skipNoParent'] - $stat['skipEmptyRel'])
				. "，说明列表中可能有重复条目或统计口径差异，请结合日志核对");
		}

		// 4. 更新目标目录大小
		$this->writeLog('开始更新目录大小');
		$task->task['currentTitle'] = LNG('storeImport.task.updateSize');
		$task->update(0,true);
		$model = Model('Source');
		$parse = KodIO::parse($pathTo);
		$info = $model->pathInfo($parse['id'],true);
		$model->folderSizeResetChildren($info['sourceID']);
		if ($info['parentID']) {
			$model->folderSizeReset($info['parentID']);
		}
		$this->writeLog('完成更新目录大小');

		// 5. 结束任务
		// $task->task['taskPercent'] = 1;	// TODO 强制设为1；无效，保存前会重新计算；status=done也无效
		$task->task['currentTitle'] = LNG('storeImport.task.importEnd');
		// if ($logCnt) $task->task['desc'] = "有{$logCnt}个文件路径超长";
		if ($logCnt) $this->writeLog("{$pathTo}下有{$logCnt}个文件路径超出长度限制");
		$task->update(0,true);

		// 更新日志
		$update = array('status' => 1, 'taskInfo' => $task->task);
		$this->logEdit($logId, $update);

		Cache::set($taskId, $task->task);
		$task->end();

		// 6.更新MD5
		TaskQueue::add($this->pluginName.'Plugin.fileHashSet',array($pathFrom));

		$this->writeLog('结束导入任务：from=>'.$pathFrom.'; to=>'.$pathTo);
	}

	/**
	 * 输出源目录遍历阶段的异常（原来这些位置都是静默跳过，日志里完全看不到）
	 *   unreadable：目录不可读 → 整棵子树不会进入导入结果（“少文件/少字节”的头号嫌疑）
	 *   stat      ：条目 stat 失败（断链软链、权限不足）→ 该条丢失
	 *   symlink   ：软链接目录 → 同一份内容会按软链接路径再遍历一次（重复计数）；仅记录，不改变既有行为
	 */
	private function logListErrors($drv) {
		if (!is_object($drv) || empty($drv->errList) || !is_array($drv->errList)) return;
		$title = array(
			'unreadable' => '目录不可读（整棵子树被跳过，内容不会出现在导入结果中）',
			'stat'       => '条目无法 stat（已跳过，内容不会出现在导入结果中）',
			'symlink'    => '软链接目录（会按软链接路径再遍历一次，可能重复导入/重复计数）',
		);
		$max = 50;	// 每条类型最多打印 50 条明细，避免把日志刷爆
		foreach ($drv->errList as $type => $list) {
			$cnt = count($list);
			if (!$cnt) continue;
			$desc = isset($title[$type]) ? $title[$type] : $type;
			$this->writeLog("源目录列表异常-{$desc}：{$cnt} 条");
			foreach (array_slice($list, 0, $max) as $item) {
				$this->writeLog('    ' . $item);
			}
			if ($cnt > $max) $this->writeLog('    其余 ' . ($cnt - $max) . ' 条省略');
		}
	}


	/**
	 * 写“文件导入明细”
	 * 位置：目标目录下新建“文件导入明细”目录，每次导入生成一个文件
	 * 文件名：异常明细-YYYYmmdd-His-<taskId 的 md5 前 8 位>.txt（带时间便于多次导入区分；用 md5 避免把请求参数直接拼进文件名）
	 * 内容：表头（任务/时间/源与目标/结束状态/对账/分类汇总）+ TSV 明细（类型、级别、对象、原因）
	 * 覆盖：超长路径、编码非法、4 字节字符、扩展名超长、父目录无法建立、目录不可读 / stat 失败 / 软链目录、批次回滚等
	 * @return string|false 写入的文件路径
	 */
	private function flushErrDetail($pathFrom, $pathTo, $taskId, $import, $tail = '') {
		if ($this->errDetailWritten) return false;
		$this->errDetailWritten = true;

		$map   = $import->errTypeMap();
		$count = $import->getErrCount();
		$rows  = array();

		// 1) 导入过程收集到的异常
		foreach ($import->getErrItems() as $it) {
			$rows[] = $this->errRow($map, $it['type'], $it['obj'], $it['reason']);
		}
		// 2) 源目录遍历阶段的异常（驱动采集，如目录不可读导致整棵子树被跳过）
		$drv = $this->api($pathFrom);
		if (is_object($drv) && !empty($drv->errList) && is_array($drv->errList)) {
			$merge = array('unreadable' => 'dir_unreadable', 'stat' => 'stat_failed', 'symlink' => 'symlink_dir');
			foreach ($drv->errList as $k => $list) {
				if (empty($list)) continue;
				$type = isset($merge[$k]) ? $merge[$k] : $k;
				$count[$type] = _get($count, $type, 0) + count($list);
				foreach ($list as $line) {
					$parts = explode("\t", $line, 2);
					$rows[] = $this->errRow($map, $type, $parts[0], _get($parts, 1, ''));
				}
			}
		}
		// 3) 超出条数上限、只计数未列出的部分
		foreach ($import->getErrDropped() as $type => $n) {
			$rows[] = $this->errRow($map, $type, '另有 ' . $n . ' 条同类未列出', '超出单次明细条数上限');
		}

		if (!$count && !$rows) return false;	// 没有异常就不建目录

		// 表头
		$stat  = $import->getStat();
		$lines = array();
		$lines[] = '# storeImport 导入异常明细';
		$lines[] = "# 任务ID\t" . $this->errCell($taskId);
		$lines[] = "# 生成时间\t" . date('Y-m-d H:i:s');
		$lines[] = "# 源目录\t" . $this->errCell($pathFrom);
		$lines[] = "# 目标目录\t" . $this->errCell($pathTo);
		$lines[] = "# 结束状态\t" . $this->errCell($tail);
		$lines[] = "# 数据库字符集\t" . $import->getDbCharset();
		$lines[] = "# 对账\t扫描文件 " . $stat['fileSeen'] . '；写入 io_source ' . $stat['sourceRows']
			. ' 行 / io_file ' . $stat['fileRows'] . ' 行；目录 新建 ' . $stat['folderCreated']
			. '、复用 ' . $stat['folderExisted'] . '、失败 ' . $stat['folderFailed'] . '；失败批次 ' . $stat['batchFail'];
		$lines[] = "# 异常合计\t" . array_sum($count);
		$group = array('skip' => array(), 'fail' => array(), 'warn' => array());
		foreach ($count as $type => $n) {
			$info = isset($map[$type]) ? $map[$type] : array($type, 'warn');
			$lv = isset($group[$info[1]]) ? $info[1] : 'warn';
			$group[$lv][] = $info[0] . ' ' . $n;
		}
		foreach ($group as $lv => $arr) {
			if (!$arr) continue;
			$lines[] = '# ' . $this->errLevelName($lv) . "\t" . implode('；', $arr);
		}
		$lines[] = "# 说明\t「跳过」需要处理后重新导入；「失败」表示该部分内容未入库（多为整批回滚、整棵子树不可读）；「提示」表示已导入但需留意";
		$lines[] = "类型\t级别\t对象\t原因";

		$content = implode(PHP_EOL, $lines) . PHP_EOL;
		foreach ($rows as $row) { $content .= $row . PHP_EOL; }

		// 落盘：{目标目录}/文件导入明细/异常明细-<时间>-<md5前8>.txt
		$dirName = rtrim($pathTo, '/') . '/' . LNG('storeImport.task.errLog');
		$dirPath = IO::mkdir($dirName);
		if (!$dirPath) {
			$this->writeLog('异常明细目录创建失败：' . $pathTo . '（异常共 ' . $import->errTotal() . ' 条，详见日志）');
			return false;
		}
		$fileName = '异常明细-' . date('Ymd-His') . '-' . substr(md5((string)$taskId), 0, 8) . '.txt';
		$dispPath = $dirName . '/' . $fileName;	// {source:1}/文件导入明细/异常明细-xxx.txt，全路径写入日志，方便查看
		$file = IO::mkfile($dirPath . $fileName, '', REPEAT_REPLACE);
		if (!$file) {
			$this->writeLog('异常明细文件创建失败：' . $dispPath);
			return false;
		}
		IO::setContent($file, $content);
		$this->writeLog('异常明细已写入：' . $dispPath . '（明细 ' . count($rows) . ' 行；' . $import->errSummaryText() . '）');
		return $dispPath;
	}

	// 明细行（TSV；名字里的换行/制表符必须转义，否则会串行）
	private function errRow($map, $type, $obj, $reason) {
		$info = isset($map[$type]) ? $map[$type] : array($type, 'warn');
		return $this->errCell($info[0]) . "\t" . $this->errLevelName($info[1]) . "\t"
			. $this->errCell($obj) . "\t" . $this->errCell($reason);
	}
	private function errLevelName($lv) {
		$names = array('skip' => '跳过', 'fail' => '失败', 'warn' => '提示');
		return isset($names[$lv]) ? $names[$lv] : $lv;
	}
	// 单元格转义（外部文件名可能含换行/制表符/回车）
	private function errCell($s) {
		return str_replace(array("\r", "\n", "\t"), array('\\r', '\\n', '\\t'), (string)$s);
	}

	// 更新io_file.hash；io文件内容有变更时，会导致md5不匹配——忽略
	public function fileHashSet($pathFrom=''){
		// 1.获取本地存储——仅更新本地存储
		$data = array();
		if ($pathFrom) {
			$parse = KodIO::parse($pathFrom);
			if ($parse['type'] != KodIO::KOD_IO) return;
			$store = Model('Storage')->listData($parse['id']);
			if (strtolower($store['driver']) != 'local') return;
			$data[] = $store['id'];
		} else { // 前端请求时更新全部，暂不开启
			return;
			$list = Model('Storage')->listData();
			foreach ($list as $item) {
				if (strtolower($item['driver']) != 'local') continue;
				$data[] = $item['id'];
			}
			if (!$data) return;
		}

		// 2.获取待更新的文件列表
		$defMd5 = 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';	// 默认md5，占位
		$where = array(
			'ioType'	=> array('in', $data),
			'hashMd5'	=> array('eq', $defMd5),
		);
		if ($pathFrom) {
			$where['ioType'] = $parse['id'];
			$where['path'] = array('like', "{$pathFrom}%");
		}
		$model = Model('File');
		$list = $model->where($where)->field('fileID,path')->select();

		$title = $pathFrom ? "目录（{$pathFrom}）下的" : "全部";
		$this->writeLog("正在更新{$title}文件MD5，共：".count($list)."条记录");
		// 更新hash；每次重新获取判断，避免多任务重复
		foreach ($list as $item) {
			// 非全量请求不必单独查询
			$where = array(
				'fileID'	=> $item['fileID'],
			);
			// $info = $model->where($where)->field('path,hashMd5')->find();
			// if (!$info || !$info['path'] || $info['hashMd5'] != $defMd5) continue;
			$info = $item;
			$hashSimple = IO::hashSimple($info['path']);
			if (!$hashSimple) continue;
			$hashMd5 = IO::hashMd5($info['path']);
			if (!$hashMd5) continue;
			$update = array(
				'hashSimple'	=> $hashSimple,
				'hashMd5'		=> $hashMd5,
			);
			$model->where($where)->save($update);
		}
		// show_json('update '.count($list).' files.');
		$this->writeLog("完成更新{$title}文件MD5");
	}

	// 获取主任务show_json错误，更新任务
	public function showJson($result){
		if(!is_array($result)) return $result;
		if($result['code'] == true || $result['code'] == 1) return $result;

		$data = Input::getArray(array(
			'pathFrom'	=> array('default' 	=> ''),
			'pathTo'	=> array('default' 	=> ''),
			'taskId'	=> array('default' 	=> ''),
		));
		$errMsg = is_string($result['data']) ? $result['data'] : LNG('storeImport.task.stopErr');
		$taskId = $data['taskId'];

		// 正常结束、外部kill、系统级错误（应该是shutdown触发），此处均为false；throw err可获取——在此主动kill
		$task = Task::get($taskId);
		if (!$task) {$task = $this->taskGet($taskId);}
		$title = LNG('storeImport.task.importEnd');
		if ($task) {
			if ($task['status'] == '1' || $task['taskPercent'] == 1 || $task['currentTitle'] == $title) return;	// 正常结束
			$this->taskKill($task, $errMsg);
		}

		// $text = array(
		//     'from=>'.$data['pathFrom'].'; to=>'.$data['pathTo'] . '. error: ' . $errMsg,
		//     $this->in,
		//     $result,
		//     get_caller_info()
		// );
		// $this->writeLog($text);
		$desc = ($task['status'] == '1' || $task['currentTitle'] == $title) ? 'success.' : 'error: '.$errMsg;
		$this->writeLog('存储导入：from=>'.$data['pathFrom'].'; to=>'.$data['pathTo'] . '. 结果：' . $desc);
		return $result;
	}

	/**
	 * 写入日志
	 * @param string $msg
	 * @return void
	 */
	public function writeLog($msg) {
		$taskId = $this->in['taskId'] ? substr($this->in['taskId'], 0, 6) : '';
		$title	= $taskId ? "[{$taskId}]" : '';
		$data	= is_array($msg) ? array($title, $msg) : $title . $msg;
		write_log($data, $this->pluginName);
	}

	/**
	 * 导入历史记录
	 * @return void
	 */
	public function logGet($id=false){
		$list = Model('StoreImport')->listData($id);
		if ($id) return $list;

		$uids = array_to_keyvalue($list, '', 'userID');
		$userArray = Model('User')->userListInfo(array_unique($uids));
		$state = array(
			'0' => array('color' => 'grey',	 'text' => LNG('storeImport.task.notFinished')),
			'1' => array('color' => 'green', 'text' => LNG('storeImport.task.importOK')),
			'2' => array('color' => 'red',	 'text' => LNG('storeImport.task.importErr').LNG('storeImport.task.errDesc')),
			'-1'=> array('color' => 'orange','text' => LNG('storeImport.task.importEnd').LNG('storeImport.task.partDesc')),
		);
		foreach ($list as $i => &$item) {
			if (isset($userArray[$item['userID']])) {
				$item['userInfo'] = $userArray[$item['userID']];
			}
			if ($item['status'] == 1 && _get($item, 'taskInfo.taskPercent', 1) < 1) {
				$item['status'] = '-1';
			}
			$item['stateInfo'] = $state[$item['status']];
		};unset($item);

		show_json($list);
	}
	// 获取任务信息（或完整日志）
	public function taskGet($taskId, $log=false) {
		$info = Model('StoreImport')->findByKey('taskId', $taskId);
		return $log ? $info : _get($info, 'taskInfo', false);
	}
	public function logAdd ($pathFrom, $pathTo, $task) {
		$data = array(
			'userID' 		=> USER_ID,
			'pathFrom' 		=> $pathFrom,
			'pathTo'		=> $pathTo,
			'taskId'		=> $task['id'],
			'taskInfo'		=> $task,
			'status'		=> 0,	// 导入状态：0-未结束；1-完成；2-异常终止
			'createTime'	=> time(),
			'modifyTime'	=> 0
		);
		return Model('StoreImport')->add($data);
	}
	// 更新导入记录
	public function logEdit ($id, $update) {
		return Model('StoreImport')->edit($id, $update);
	}

}

/**
 * 存储导入记录
 */
class StoreImportModel extends ModelBaseLight{
	public $optionType 	= 'Store.importLogList';
	public $modelType	= "SystemOption";
	public $field		= array('userID','pathFrom','pathTo','taskInfo','status','taskId'); //value中的数据字段

	//默认正序
	public function listData($id=false,$sort='modifyTime',$sortDesc=true){
		return parent::listData($id,$sort,$sortDesc);
	}
	public function remove($id){ 
		return parent::remove($id);
	}
	public function add($data){
		return parent::insert($data);
	}
	public function edit($id,$data){
		return parent::update($id, $data);
	}
}