<?php

/**
 * 存储同步：跨存储目录同步
 */

class storeSyncPlugin extends PluginBase{
	protected $_manualLockCleanupSyncID;
	function __construct(){
		parent::__construct();
		$this->loadModel();
	}
	public function regist(){
		$this->hookRegist(array(
			'user.commonJs.insert' => 'storeSyncPlugin.echoJs',
		));
	}
	public function echoJs(){
		$this->echoFile('static/main.js');
	}

	public function driver($path){
		$parse	= KodIO::parse($path);
		$id = $parse['id'];
		static $drvClass = array();
		if (!$drvClass[$id]) {
			$store = Model('Storage')->driverInfo($id);
			include_once(PLUGIN_DIR . 'storeImport/lib/driver/DriverBase.class.php');
			$drvClass[$id] = new impDriver($store);
		}
		return $drvClass[$id];
	}

	// 检查存储编辑权限
	private function checkAuth(){
		// $this->config['ADMIN_ALLOW_IO'] != '1'
		$check = Action('user.authRole')->authCan('admin.storage.edit');
		if(!$check){show_json(LNG('explorer.noPermissionAction'),false);}
	}

	// 禁用启用;启用时检测;
	public function onChangeStatus($status){
		$syncAct = Action($this->pluginName . "Plugin.index.index");
		$syncAct->updateTask($status);
		$syncAct->initDbTable($status);
	}
	// 插件更新-新增表字段
	public function onUpdate(){
		KodUser::checkLogin();
		// TODO 更新需要清除缓存，否则db缓存会导致查询结果异常
		if (!$this->alterTblCol('manualLock') || 
			!$this->alterTblCol('relPath', 'scan_queue')) {
			show_json(LNG('storeSync.main.alterTblErr'), false);
		}
		return true;
	}
	// 追加表字段
    private function alterTblCol($key, $table=''){
		$table  = 'plugin_storesync' . ($table ? '_'.$table : '');	// 直接用$this->model->getTableName/getDbFields报错
		$fields = Model($table)->getDbFields();
		if (in_array($key, $fields)) return true;
		try {
			Model($table)->execute("ALTER TABLE `{$table}` ADD COLUMN {$key} TEXT");
		} catch (Exception $e) {
			$msg = $e->getMessage();
			if (stripos($msg, 'Duplicate column') !== false) return true;	// 获取的字段列表可能有缓存
			write_log("追加表字段失败：{$key}, ".$msg, 'storesync');
            return false;
		}
        return true;
	}

	public $model;
	public $modelScanQueue;
	public $modelScanCache;
	public $modelJobQueue;
	public $modelFile;
	public function loadModel(){
		if(strtolower(MOD) != 'plugin' || strtolower(ST) != 'storesync') return;
		if(strtolower(ACT) == 'echojs') return;
		$this->loadModelNow();
	}
	private function loadModelNow(){
		if($this->model) return;
		include($this->pluginPath.'lib/syncModel.class.php');
		include($this->pluginPath.'lib/syncModelScan.class.php');
		include($this->pluginPath.'lib/syncModelJob.class.php');
		include($this->pluginPath.'lib/syncModelFile.class.php');

		$this->model 			= new syncModel($this);
		$this->modelScanQueue  	= new syncModelScanQueue($this);
		$this->modelScanCache  	= new syncModelScanCache($this);
		$this->modelJobQueue 	= new syncModelJobQueue($this);
		$this->modelFile 		= new syncModelFile($this);
	}

	public function api($isManual = false){
		static $syncApi = null;
		static $syncApiManual = null;
		if ($isManual) {
			if (!$syncApiManual) {
				include_once($this->pluginPath.'lib/syncApi.class.php');
				$syncApiManual = new syncApi($this, true);
			}
			return $syncApiManual;
		}
		if (!$syncApi) {
			include_once($this->pluginPath.'lib/syncApi.class.php');
			$syncApi = new syncApi($this);
		}
		return $syncApi;
	}

	/**
	 * 获取任务列表
	 * @return void
	 */
	public function get(){
		$list = $this->model->syncList();
		if (!$list) $list = array();
		foreach ($list as &$info) {
			$info['pathAInfo'] = $this->pathInfoParse($info['pathA']);
			$info['pathBInfo'] = $this->pathInfoParse($info['pathB']);
		};unset($info);
		show_json($list);
	}

	/**
	 * 获取任务信息
	 * @return void
	 */
	public function getInfo() {
		$syncID = Input::get('syncID', 'int');
		$info = $this->model->syncList($syncID);
		if (!empty($info)) {
			$info['pathAInfo'] = $this->pathInfoParse($info['pathA']);
			$info['pathBInfo'] = $this->pathInfoParse($info['pathB']);
		}
		show_json($info);
	}
	private function pathInfoParse($path){
		$parse = KodIO::parse($path);
		if ($parse['type'] == KodIO::KOD_IO) {
			$info = array('path' => $path);
			Action('explorer.listDriver')->parsePathIO($info);
			if ($info && empty($info['icon'])) {	// 非根目录有部分缺失
				$info['name'] = get_path_this($info['path']);
				$info['icon'] = 'io-'.strtolower($info['ioDriver']);
			}
		// } else if (in_array($parse['type'], array(KodIO::KOD_USER_SHARE, KodIO::KOD_USER_SHARE_LINK, KodIO::KOD_USER_SHARE_TO_ME, KodIO::KOD_SHARE_ITEM))) {
		// 	$shareInfo = Model('Share')->getInfo($parse['id']);
		// 	$sourceInfo = Action('explorer.userShare')->_shareItemeParse($info, $shareInfo);
		// 	$info['name'] = _get($sourceInfo, 'pathDisplay', $path);
		// 	$info['icon'] = 'folder';
		} else {
			$info = IO::infoFull($path);
			if ($info) $info['icon'] = 'folder';
		}
		if (!$info) $info = array('path' => $path, 'name' => get_path_this($path), 'icon' => 'folder');
		return $info;
	}

	/**
	 * 检查目录对是否构成冲突（死循环/互相覆盖）
	 * 规则：
	 *   1. A/B 同路径或嵌套 → 同一任务内死循环
	 *   2. 与已有任务重复
	 *   3. 共享写入端 → 互相覆盖，始终重复同步
	 *   4. 循环依赖 → A→B→A 无限同步
	 * @param string $pathA
	 * @param string $pathB
	 * @param bool   $isBidi  新任务是否双向
	 * @return bool true=有冲突
	 */
	private function isPathConflict($pathA, $pathB, $isBidi = false) {
		$pathA = trim($pathA, '/') . '/';
		$pathB = trim($pathB, '/') . '/';

		// 1.同路径或嵌套 → 自身死循环
		if ($pathA === $pathB || $this->pathOverlap($pathA, $pathB)) return true;

		// 新任务的写入端和读取端
		// 单向A→B: 写入={B}, 读取={A}; 双向: 写入=读取={A,B}
		$newWrites  = $isBidi ? array($pathA, $pathB) : array($pathB);
		$newSources = $isBidi ? array($pathA, $pathB) : array($pathA);

		// IO::infoFull 缓存（pathOverlap 内复用，避免重复请求）
		$infoCache = array();

		// 与已有任务比较
		$existing = $this->model->syncList();
		foreach ($existing as $task) {
			$existA = trim($task['pathA'], '/') . '/';
			$existB = trim($task['pathB'], '/') . '/';
			$existBidi = _get($task, 'isBidi', 0) == '1';

			// 2.与已有任务重复（正反序）
			if (($existA === $pathA && $existB === $pathB) ||
				($existA === $pathB && $existB === $pathA)) {
				return true;
			}

			$existWrites  = $existBidi ? array($existA, $existB) : array($existB);
			$existSources = $existBidi ? array($existA, $existB) : array($existA);

			// 3.共享写入端：两个任务写同一区域 → 互相覆盖
			if ($this->pathsOverlapAny($newWrites, $existWrites, $infoCache)) return true;

			// 4.循环依赖：新写入端→旧读取端 且 旧写入端→新读取端
			if ($this->pathsOverlapAny($newWrites, $existSources, $infoCache) &&
				$this->pathsOverlapAny($existWrites, $newSources, $infoCache)) {
				return true;
			}
		}
		return false;
	}
	/**
	 * 判断两组路径中是否存在任意一对重叠
	 * @param array  $paths1
	 * @param array  $paths2
	 * @param array  &$infoCache  路径→infoFull缓存（可选，避免重复IO调用）
	 * @return bool
	 */
	private function pathsOverlapAny($paths1, $paths2, &$infoCache = array()) {
		foreach ($paths1 as $p1) {
			foreach ($paths2 as $p2) {
				if ($this->pathOverlap($p1, $p2, $infoCache)) return true;
			}
		}
		return false;
	}
	/**
	 * 判断两个路径是否有嵌套关系（一方包含另一方）
	 * 支持物理路径、{io:id}、{source:id}（通过parentLevel判断）
	 * @param string $pathA  已 trim 并带尾随 /
	 * @param string $pathB
	 * @param array  &$infoCache  路径→infoFull缓存
	 * @return bool
	 */
	private function pathOverlap($pathA, $pathB, &$infoCache = array()) {
		if ($pathA === $pathB) return true;
		// 字符串前缀（覆盖物理路径、{io:id}、{source:id}/x/y 多层级路径）
		if (strpos($pathB, $pathA) === 0 || strpos($pathA, $pathB) === 0) return true;

		// {source:id} 路径嵌套：字符串前缀无法判断 {source:3} 与 {source:5} 的父子关系
		// 通过 parentLevel 判断：将路径的完整层级链拼成 ",0,1,2,3," 格式，用 strpos 判断包含
		$infoA = $this->pathInfoCached($pathA, $infoCache);
		$infoB = $this->pathInfoCached($pathB, $infoCache);
		if (!$infoA || !$infoB) return false;
		if (empty($infoA['parentLevel']) || empty($infoB['parentLevel'])) return false;

		$levelA = $infoA['parentLevel'] . _get($infoA, 'sourceID', 0) . ',';
		$levelB = $infoB['parentLevel'] . _get($infoB, 'sourceID', 0) . ',';
		if (strpos($levelA, $levelB) === 0 || strpos($levelB, $levelA) === 0) return true;
		return false;
	}
	/**
	 * 获取路径的 infoFull（带缓存）
	 * @param string $path
	 * @param array  &$infoCache
	 * @return array|false
	 */
	private function pathInfoCached($path, &$infoCache) {
		if (!isset($infoCache[$path])) {
			$infoCache[$path] = IO::infoFull($path);
		}
		return $infoCache[$path];
	}

	/**
	 * 新增任务
	 * @return void
	 */
	public function add(){
		$this->checkAuth();
		$data = Input::getArray(array(
			'title'		=> array('default' => ''),
			'pathA'		=> array('check' => 'require'),
			'pathB'		=> array('check' => 'require'),
			'isBidi'	=> array('default' => null),
			'rptType'	=> array('default' => 1),
			'enable'	=> array('default' => 1),
		));
		foreach (array('pathA', 'pathB') as $key) {
			if (!IO::isFolder($data[$key])) {
				show_json(LNG('storeSync.main.invalidPath').'-'.$key, false);
			}
		}
		// 防止同步死循环：同路径、父子目录、重复任务对、共享写入端、循环依赖
		if ($this->isPathConflict($data['pathA'], $data['pathB'], $data['isBidi'] == '1')) {
			show_json(LNG('storeSync.main.errLoopPath'), false);
		}

		$res = $this->model->syncAdd($data);
		$this->showJson($res);
	}

	/**
	 * 编辑任务
	 * @return void
	 */
	public function edit(){
		$this->checkAuth();
		$data = Input::getArray(array(
			'title'		=> array('default' => null),
			'isBidi'	=> array('default' => null),
			'rptType'	=> array('default' => null),
			'enable'	=> array('default' => null),
		));
		$syncID = Input::get('syncID', 'int');

		$res = $this->model->syncEdit($syncID, $data);
		$this->showJson($res);
	}

	/**
	 * 删除任务
	 * @return void
	 */
	public function remove(){
		$this->checkAuth();
		$syncID = Input::get('syncID','int');
		$res = $this->model->syncRemove($syncID);
		$this->showJson($res);
	}

	/**
	 * 重置任务
	 * @return void
	 */
	public function reset(){
		$this->checkAuth();
		$syncID = Input::get('syncID', 'int');
		$res = $this->model->syncReset($syncID);
		$this->showJson($res);
	}

	/**
	 * 手动执行同步任务
	 * @return void
	 */
	public function start(){
		$this->checkAuth();
		$syncID = Input::get('syncID', 'int');

		// 获取同步任务信息
		$info = $this->model->syncList($syncID);
		if (!$info) show_json(LNG('explorer.noPermissionAction'), false);

		// 任务未启用或有错误
		if ($info['enable'] != '1') {
			show_json(LNG('storeSync.main.taskDisabled'), false);
		}
		if ($info['status'] == '9') {
			show_json(LNG('storeSync.main.errTaskTips'), false);
		}

		// 检查手动执行锁——已在手动执行中
		$lock = json_decode($info['manualLock'], true);
		if (!empty($lock) && _get($lock, 'status') == 'running' && (time() - _get($lock, 'time', 0) < 3600)) {
			show_json(LNG('storeSync.main.taskRunning'), false);
		}

		// 无论计划任务是否在进行中，都置空任务重新开始
		// // 任务正在计划任务中执行（status=1~4），提示稍后
		// if (in_array($info['status'], array('1','2','3','4'))) {
		// 	show_json(LNG('storeSync.main.taskInCron'), false);
		// }
		// // status=5（已完成）时，重置为0开始新一轮
		// if ($info['status'] == '5') {
		// 	$this->api(true)->taskResetCycle($syncID, $info);
		// }

		// 先返回响应，再继续执行
		echo json_encode(array('code'=>true,'data'=>LNG('storeSync.main.syncStarted')));
		http_close();

		// 加锁
		$lockData = json_encode(array('status' => 'running', 'time' => time()));
		$this->model->syncEdit($syncID, array('manualLock' => $lockData));

		// 注册 shutdown 函数释放锁（兼容 PHP 5.3，无 try-finally）
		$this->_manualLockCleanupSyncID = $syncID;
		register_shutdown_function(array($this, '_manualLockCleanup'));

		// 重置任务
		$this->api(true)->taskResetCycle($syncID, $info);

		// 执行——手动模式，不限时
		$this->api(true)->syncTaskManual($syncID);

		// 正常退出，释放锁
		$this->model->syncEdit($syncID, array('manualLock' => ''));
		$this->_manualLockCleanupSyncID = null;
	}
	/**
	 * shutdown 时清理 manualLock（进程异常退出保护）
	 */
	private function _manualLockCleanup() {
		if ($this->_manualLockCleanupSyncID) {
			$this->model->syncEdit($this->_manualLockCleanupSyncID, array('manualLock' => ''));
		}
	}

	private function showJson($res) {
		$code = $res ? true : false;
		$data = $code ? LNG('explorer.success') : LNG('explorer.error');
		show_json($data, $code);
	}

	/**
	 * 计划任务
	 * @return void
	 */
	public function autoTask() {
		if (!KodUser::isRoot()) return;
		ignore_timeout();
		$this->loadModelNow();	// 计划任务可能没有触发——ACTION被拦截
		return $this->api()->syncTask();
	}

	/**
	 * 获取（各状态）执行文件列表
	 * @return void
	 */
	public function fileList(){
		$data = Input::getArray(array(
			'syncID' => array('check'	=> 'int'),
			'status' => array('default' => ''),
		));
		// 兼容失败状态：'3,9'
		$status = explode(',', $data['status']);
		if (count($status) > 1) { 
			$data['status'] = array('in',$status);
		}
		$list = $this->modelJobQueue->getList($data['syncID'],$data['status']);
		show_json($list);
	}

}