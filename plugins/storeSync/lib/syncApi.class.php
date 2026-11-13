<?php 

/**
 * 跨存储同步
 * 
 */
class syncApi {
    private $plugin;
    private $model;             // 任务主表
	private $modelScanQueue;    // 扫描队列（当前及待扫描目录）
	private $modelScanCache;    // 扫描结果（临时列表）
	private $modelJobQueue;     // 任务队列（各文件待操作记录）
	private $modelFile;         // 同步结果

    private $timeStart = 0; // 任务开始时间
    private $timeout = 30;  // 任务超时限制：30s；手动模式不限制
    private $maxRetryCnt = 3;  // 执行同步同一文件最大重试次数
    private $isManual = false; // 是否手动执行模式

    private $curTaskInfo = array(); // 当前任务
    private $syncID = 0;
    
	function __construct($plugin, $isManual = false){
        $this->plugin           = $plugin;
        $this->isManual         = $isManual;

		$this->model 		    = $this->plugin->model;
		$this->modelScanQueue	= $this->plugin->modelScanQueue;
		$this->modelScanCache 	= $this->plugin->modelScanCache;
		$this->modelJobQueue	= $this->plugin->modelJobQueue;
		$this->modelFile	    = $this->plugin->modelFile;

        if ($isManual) {
            $this->timeout = 0; // 手动模式不限时
            set_time_limit(0);
            ignore_user_abort(true);
        }

        // enable: 任务状态(0-停用,1-启用,2-暂停)
        // status: 运行状态(0-等待中,1-扫描A,2-扫描B,3-对比中,4-执行中,5-已完成,9-错误)
    }

    // 是否已超时（手动模式永不过期）
    private function isTimeout() {
        if ($this->timeout <= 0) return false;
        return timeFloat() - $this->timeStart >= $this->timeout;
    }

    // 同步任务更新保存
    private function syncSave($id, $data) {
        $this->model->syncEdit($id, $data);
    }

    // 赋值当前任务
    private function setCurTask($task) {
        $this->curTaskInfo = $task;
        $this->syncID = $task['syncID'];
    }
    // 获取当前任务
    private function getCurTask() {
        return $this->curTaskInfo;
    }

    // 获取重名处理方式
    private function getRptType() {
        $task = $this->getCurTask();
        $data = array(
            '0' => REPEAT_REPLACE,
            '1' => REPEAT_SKIP,
            '2' => REPEAT_RENAME,
        );
        $key = _get($task, 'rptType', '1');
        // 双向同步时禁止重命名
        if (_get($task,'isBidi') == '1' && $key == '2') {
            $key = '1';
        }
        return $data[$key];
    }

    // 检查存储目录是否为有效io路径——不限io
    private function pathCheck($path) {
        if (!$path) return false;
        $maxRetry = 2;  // 可重试一次，避免波动影响
        for ($i = 0; $i < $maxRetry; $i++) {
            if (IO::isFolder($path)) return true;
            if ($i < $maxRetry - 1) usleep(200000);
        }
        return false;
    }

    /**
     * 重置任务为新周期（status=5→0）
     * 清理扫描队列/缓存/执行队列，清空游标，保留 lastResult
     * @param int   $syncID
     * @param array &$task   任务信息
     */
    public function taskResetCycle($syncID, &$task) {
        $lastData = $task['lastSyncData'];
        if (!is_array($lastData)) $lastData = array();
        $lastData['progress'] = array(); // 只清空进度，保留 lastResult

        $this->modelScanQueue->removeAll($syncID);
        $this->modelScanCache->removeAll($syncID);
        $this->modelJobQueue->removeAll($syncID);

        // 清除上一轮指标等信息
        $update = array(
            'status'        => 0,
            'errMsg'        => '',
            'cursor'        => '',
            'lastSyncData'  => $lastData,
        );
        $this->syncSave($syncID, $update);
        foreach ($update as $k => $val) {
            $task[$k] = $val;
        }
    }

    /**
     * 列表执行同步任务（计划任务入口）
     * @return void
     */
    public function syncTask() {
        // 1.获取所有同步任务列表
        $list = $this->model->syncList();
        if (empty($list)) return;

        // 2.检测并更新任务状态
        $done = 0;
        foreach ($list as $i => $task) {
            // 任务未启用/暂停，或运行错误
            if ($task['enable'] != '1' || $task['status'] == '9') {
                unset($list[$i]);
                continue;
            }
            if ($task['status'] == '5') {$done++;}
        }
        if (!$list) return true;

        // 全部为已完成状态（5），说明上一周期结束，全部重置为等待中（0）
        if (count($list) == $done) {
            foreach ($list as &$task) {
                $this->taskResetCycle($task['syncID'], $task);
            };unset($task);
        }

        // 3.循环执行各同步任务
        $this->timeStart = timeFloat();   // 整个任务中全部任务总执行限制30s——diff列表和单文件复制可能超时，忽略
        foreach ($list as $task) {
            if ($task['status'] == '5') continue;
            if ($this->isTimeout()) break;

            // 手动执行中则跳过
            $lock = $this->parseManualLock($task);
            if ($lock) {
                // 手动执行仍在进行中（60分钟内），跳过
                if (time() - $lock['time'] < 3600) {
                    continue;
                }
                // 手动锁过期（>60分钟视为异常退出），清除锁
                $this->syncSave($task['syncID'], array('manualLock' => ''));
            }
            $this->runSyncTask($task);   // 执行任务
        }

        return true;
    }

    /**
     * 手动执行单个同步任务（无超时限制）
     * @param int $syncID
     * @return bool
     */
    public function syncTaskManual($syncID) {
        $task = $this->model->syncList($syncID);
        if (!$task) return false;

        $this->timeStart = timeFloat();
        $this->runSyncTask($task);
        return true;
    }

    // 解析手动执行锁，返回锁信息或null
    private function parseManualLock($task) {
        $lock = json_decode($task['manualLock'], true);
        if (empty($lock) || _get($lock, 'status') != 'running') return null;
        return $lock;
    }

    // 单个执行同步任务
    public function runSyncTask($task) {
        $this->setCurTask($task);   // 赋值当前任务
        if (!$task || $task['enable'] != '1') return false;

        // 1.初始化任务信息
        $this->writeLog("开始同步【{$task['title']}】");
        // 目录信息
        $pathA = $task['pathA'];
        $pathB = $task['pathB'];
        $pathErr = false;
        if (!$this->pathCheck($pathA)) {
            $pathErr = $pathA;
        } else if (!$this->pathCheck($pathB)) {
            $pathErr = $pathB;
        }
        if ($pathErr !== false) {
            $update = array(
                'status' => 9,
                'errMsg' => LNG('storeSync.main.errPath').': '.$pathErr,
            );
            $this->syncSave($task['syncID'], $update);
            $this->writeLog("结束同步【{$task['title']}】");
            return false;
        }
        $task['pathA'] = trim($pathA,'/').'/';
        $task['pathB'] = trim($pathB,'/').'/';

        // 初始化统计信息
        if (!isset($task['lastSyncData']['lastResult'])) {
            $task['lastSyncData']['lastResult'] = array();
        }
        if (!isset($task['lastSyncData']['progress'])) {
            $task['lastSyncData']['progress'] = array();
        }
        $task['lastSyncData']['progress'] = array_merge(
            array('scan'=>array(),'comp'=>array(),'exec'=>array()),
            $task['lastSyncData']['progress']
        );

        // 2.分步执行任务
        $syncID = $task['syncID'];
        // 记录同步开始时间（仅在开始新周期时）
        if ($task['status'] == '0') {
            $task['lastSyncData']['progress']['syncStartTime'] = time();
        }
        while (!$this->isTimeout()) {
            $isStepDone = false; // 标记当前步骤是否已彻底完成
            // 检测手动接管，避免手动清表后计划任务继续写入脏数据
            if (!$this->isManual) {
                // $lockRow = Model('plugin_storesync')->where(array('syncID' => $syncID))->find();
                $lockRow = $this->model->syncInfo($syncID,false);
                if ($lockRow && !empty($lockRow['manualLock'])) {
                    $lock = json_decode($lockRow['manualLock'], true);
                    if (!empty($lock) && _get($lock, 'status') == 'running' && (time() - _get($lock, 'time', 0)) < 3600) {
                        $this->writeLog("cron 检测到手动接管，退出当前任务");
                        break;
                    }
                }
            }
            $stepStatus = $task['status'];
            switch ($stepStatus) {
                case '0':   // 等待中
                    $isStepDone = true;
                    $status = 1;
                    break;
                case '1':   // 扫描A
                    $this->writeLog('step1：开始扫描目录A');
                    $isStepDone = $this->runStepScan($task, 'A');
                    $status = 2;
                    break;
                case '2':   // 扫描B
                    $this->writeLog('step2：开始扫描目录B');
                    $isStepDone = $this->runStepScan($task, 'B');
                    $status = 3;
                    break;
                case '3':   // 对比
                    $this->writeLog('step3：开始对比目录');
                    $isStepDone = $this->runStepComp($task);
                    $status = 4;
                    break;
                case '4':   // 执行
                    $this->writeLog('step4：开始执行同步');
                    $isStepDone = $this->runStepExec($task);
                    $status = 5;
                    break;
                default :
                    $this->writeLog("结束同步【{$task['title']}】，status={$stepStatus}");
                    return; // status=5/9，直接退出，进入下一个任务
            }

            // 当前阶段已彻底完成，更新状态到下一步
            if ($isStepDone) {
                $update = array('status' => $status);
                if ($status == 5) { // 下一步是完成，则记录结束时间和统计
                    $now = time();
                    $startTime = _get($task['lastSyncData']['progress'], 'syncStartTime', $now);
                    $task['lastSyncData']['lastResult'] = array(
                        'startTime' => $startTime,
                        'endTime'   => $now,
                        'duration'  => $now - $startTime,
                        'scan'      => $task['lastSyncData']['progress']['scan'],
                        'comp'      => $task['lastSyncData']['progress']['comp'],
                        'exec'      => $task['lastSyncData']['progress']['exec'],
                    );
                    $update['lastSyncTime'] = $now;
                    $update['lastSyncData'] = $task['lastSyncData'];
                }
                $this->syncSave($syncID, $update);
                $task['status'] = $status;

                // 根据刚刚完成的阶段，执行清理操作
                if ($stepStatus == '3') { // 对比阶段(3)完成，即将进入执行阶段(4)，清理扫描缓存
                    $this->modelScanCache->removeAll($syncID);
                }
                // 继续 while 循环，尝试在本次任务时间内执行下一步操作
            } else {
                // 当前阶段未完成（通常是超时——失败status=9），跳出循环
                $this->writeLog("当前阶段未完成，status={$task['status']}");
                break;
            }
        }

        $this->writeLog("结束同步【{$task['title']}】，status={$task['status']}");
    }

    // step1：扫描目录
    private function runStepScan(&$task, $side){
        $syncID = $task['syncID'];
        // 初始化统计信息
        if (empty($task['lastSyncData']['progress']['scan'])) {
            $task['lastSyncData']['progress']['scan'] = array(
                'A' => array('dirCnt'=>0,'fileCnt'=>0,'fileSize'=>0),
                'B' => array('dirCnt'=>0,'fileCnt'=>0,'fileSize'=>0),
            );
        }
        $queModel = $this->modelScanQueue;

        // 1.从队列中读取列表，为空则添加根目录
        $hasQueue = $queModel->getList($syncID, $side, 1);
        if (!$hasQueue) {
            $pathBase = $task['path'.$side];
            $data = array(
                'path'      => $pathBase, // 实际IO路径（用于IO操作）
                'relPath'   => './',      // 相对路径（用于计算子项相对路径）
            );
            $id = $queModel->add($syncID,$side,$data);
            if (!$id) {
                $this->writeLog("扫描{$side}-根目录添加队列失败");
                return false; // 不更新status=9，下次任务继续
            }
        }

        // 2. 循环执行扫描，直到超时或队列为空
        while (!$this->isTimeout()) {
            $queList = $queModel->getList($syncID, $side);
            if (!$queList) {
                return true; // 队列为空，扫描阶段完成
            }
            $isDone = $this->_scanPath($task, $side, $queList);
            if (!$isDone) {
                return false; // 扫描阶段未完成，结束本次任务
            }
            // 本批次处理完成，且未超时，取下一批继续
        }
        // 超时后，检查是否还有剩余队列
        $hasQueue = $queModel->getList($syncID, $side, 1);
        if (!$hasQueue) {
            return true;
        }
        return false;
    }
    private function _scanPath(&$task,$side,$queList) {
        $syncID     = $task['syncID'];
        // $pathBase   = $task['path'.$side]; // 基础目录
        $syncDataScan = $task['lastSyncData']['progress']['scan'][$side];  // 同步统计信息

        $queModel   = $this->modelScanQueue;

        // 1.循环队列，扫描目录
        $queCnt = count($queList); // 队列数量
        foreach ($queList as $queue) {
            if ($this->isTimeout()) break;

            // 获取指定目录列表，写入缓存表
            $absPath  = $queue['path'];     // 实际IO路径
            $relPath  = $queue['relPath'] ? $queue['relPath'] : './'; // 前目录的相对路径；兼容旧数据/SQLite NULL
            // TODO 对象存储应该获取平级列表
            try {
                $list = IO::listPath($absPath);
            } catch (Exception $e) {
                $this->writeLog("扫描{$side}-读取目录失败：{$absPath} ({$e->getMessage()})");
                return false; // 本次任务失败，但任务状态不置为9，下次可重试
            }

            // 1.获取相对路径列表（通过父目录相对路径+文件名拼接，兼容各种路径类型）
            $dirs = $data = array();
            foreach ($list['folderList'] as $file) {
                $name = $file['name'];
                $fileRelPath = $relPath . $name . '/'; // 目录相对路径，末尾加/
                // 路径过长跳过（>500 会触发唯一键前缀截断，导致同步遗漏）
                if (strlen($fileRelPath) > 500) {
                    $this->writeLog("扫描{$side}-路径过长(>500字符)，跳过：{$fileRelPath}");
                    continue;
                }
                $dirs[] = array(
                    'path'    => $file['path'],      // 实际IO路径（用于后续扫描）
                    'relPath' => $fileRelPath,        // 相对路径
                );
                $data[] = array(
                    'path'      => $fileRelPath,
                    'isFolder'  => 1,
                    'size'      => 0,
                    'mtime'     => _get($file, 'modifyTime', 0),
                );
            }
            foreach ($list['fileList'] as $file) {
                $name = $file['name'];
                $fileRelPath = $relPath . $name; // 文件相对路径，无末尾/
                // 路径过长跳过
                if (strlen($fileRelPath) > 500) {
                    $this->writeLog("扫描{$side}-路径过长(>500字符)，跳过：{$fileRelPath}");
                    continue;
                }
                $data[] = array(
                    'path'      => $fileRelPath,
                    'isFolder'  => 0,
                    'size'      => intval($file['size']),
                    'mtime'     => _get($file, 'modifyTime', 0),
                );
            }
            $dirCnt  = count($dirs);
            $fileCnt = count($data) - $dirCnt;

            // 2.当前目录移出队列，子目录加入队列——三步在同一事务中保证原子性
            $queModel->model->startTrans();
            $res1 = $queModel->remove($queue['id']);
            $res2 = $res3 = 1;
            if ($res1 && !empty($dirs)) {
                $res2 = $queModel->addAll($syncID,$side,$dirs);
            }
            // 3.文件/夹写入临时表
            if ($res1 && $res2 && !empty($data)) {
                $res3 = $this->modelScanCache->addAll($syncID,$side,$data);
            }
            // 判断结果
            if ($res1 && $res2 && $res3) {
                $queModel->model->commit();
            } else {
                $queModel->model->rollback();
                if (!$res1) {
                    $msg = "扫描{$side}-移出队列失败：{$relPath}";
                } elseif (!$res2) {
                    $msg = "扫描{$side}-添加队列失败：{$relPath}=>{$dirCnt}";
                }else {
                    $msg = "扫描{$side}-添加临时文件失败：{$relPath}";
                }
                $this->writeLog($msg);
                // 不更新status=9，下次继续
                return false;
            }

            // 统计信息
            $syncDataScan['dirCnt']  += $dirCnt; 
            $syncDataScan['fileCnt'] += $fileCnt; 
            $syncDataScan['fileSize'] += array_sum(array_column($data, 'size'));

            $queCnt--;
        }
        $task['lastSyncData']['progress']['scan'][$side] = $syncDataScan;
        $update = array('lastSyncData' => $task['lastSyncData']);
        $this->syncSave($syncID, $update); // 实时保存统计信息

        // 目录队列有剩余，说明执行超时
        if ($queCnt > 0) {
            $this->writeLog('扫描目录超时：'.(timeFloat() - $this->timeStart));
            return false; // 本批次未执行完——超时
        }
        return true;    // 本批次完成
    }

    // 获取相对路径——通过前缀剥离，仅适用于{io:N}/和物理路径，扫描阶段已改用名称拼接方式，此方法仅作兼容保留
    private function getRelPath($path,$pathBase) {
        return './'.trim(substr($path, strlen($pathBase)),'/');
    }
    // 获取绝对路径
    private function getAbsPath($path,$pathBase) {
        // ltrim($path,'./') 导致.开头的文件异常
		if (strpos($path,'./') === 0) {
			$path = str_replace('./','',$path);
		}
        return $pathBase . $path;
    }
    private function checkTruePath(&$srcPath,&$destPath,&$pathTo){
        static $cache = array(); // 同一轮执行中缓存IO::infoFull结果
        $infoFull = function($path) use (&$cache) {
            if (!isset($cache[$path])) {
                $cache[$path] = IO::infoFull($path);
            }
            return $cache[$path];
        };
        // 源文件
        $info1 = $infoFull($srcPath);
        if ($info1 === false) return false;
        $srcPath = $info1['path'];
        // 目标目录
        $info3 = $infoFull($pathTo);
        if ($info3 === false) return false;
        $pathTo = $info3['path'];

        // // 比较两侧文件md5是否相同——应该在mtime/size相同的前提下再比较MD5
        // $info2 = IO::infoFull($destPath);
        // if ($info2 !== false) {
        //     $destPath = $info2['path'];
        //     $hash1 = _get($info1, 'fileInfo.hashMd5', '');
        //     $hash2 = _get($info2, 'fileInfo.hashMd5', '');
        //     if ($hash1 && $hash1 == $hash2) {
        //         return true;
        //     }
        // }
        return null;
    }

    // step2：对比列表
    private function runStepComp(&$task){
        $syncID = $task['syncID'];
        $cursor = !empty($task['cursor']) ? $task['cursor'] : array();  // 比较进度游标
        $syncDataComp = $task['lastSyncData']['progress']['comp'];  // 同步统计信息

        // 对比类型，按顺序执行：新增，修改，删除，冲突
        $isBidi = _get($task,'isBidi',0) == '1';
        $compTypes = array(
            'CREATE_A_TO_B',
            'CREATE_B_TO_A',
            'UPDATE_A_TO_B',
            'UPDATE_B_TO_A',
            'DELETE_FROM_B',    // A端文件被删除，则B端也需删除
            'DELETE_FROM_A',
            'CONFLICT_RESOLVE', // 解决两端同时修改的冲突
            // 'CONFLICT_BOTH_NEW' // TODO 两边同时新增同名文件，概率较低，暂不处理
        );
        if (!$isBidi) { // 单向同步
            unset($compTypes[1],$compTypes[3],$compTypes[5]);
        }
        $jobModel = $this->modelJobQueue;
        foreach ($compTypes as $type) {
            // 检查当前类型是否对比完成。cursor.[type]_done = true 表示处理完成
            if (_get($cursor, $type.'_done', false)) continue;
            $lastId = _get($cursor, $type.'_lastId', 0);
            // 超时
            if ($this->isTimeout()) break;

            // 循环处理当前比对类型，直到没有更多记录或超时
            do {
                $prevLastId = $lastId;
                try {
                    switch ($type) {
                        case 'CREATE_A_TO_B':
                        case 'CREATE_B_TO_A':
                            $side = _get(explode('_',$type),'1');
                            $rest = $jobModel->sideCreate($syncID, $side, $lastId, 5000, $isBidi);
                            break;
                        case 'UPDATE_A_TO_B':
                        case 'UPDATE_B_TO_A':
                            $side = _get(explode('_',$type),'1');
                            $rest = $jobModel->sideUpdate($syncID, $side, $lastId);
                            break;
                        case 'DELETE_FROM_B':
                        case 'DELETE_FROM_A':
                            $side = _get(explode('_',$type),'2');
                            $rest = $jobModel->sideDelete($syncID, $side, $lastId);
                            break;
                        case 'CONFLICT_RESOLVE':
                            $rest = $jobModel->sideConflict($syncID, $isBidi, $lastId);
                            break;
                        default:
                            $rest = array('affect' => 0, 'lastId' => $lastId); // 未知类型
                            break;
                    }
                } catch (Exception $e) {
                    // 记录错误，更新任务状态为失败
                    $text = $e->getMessage();
                    $this->writeLog("数据对比异常, type {$type}, lastId {$lastId}: {$text}");
                    $update = array(
                        'status' => 9,
                        'errMsg' => "对比失败：{$text}"
                    );
                    $this->syncSave($syncID, $update);
                    return false; // 遇到错误立即退出
                }
                $affect = $rest['affect'];
                $lastId = $rest['lastId'];

                // 游标推进了才更新（无论本批次有无插入行）
                if ($lastId != $prevLastId) {
                    $cursor[$type . '_lastId'] = $lastId;
                }

                $typeCnt = $type . '_cnt';
                if (empty($syncDataComp[$typeCnt])) {
                    $syncDataComp[$typeCnt] = 0;
                }
                $syncDataComp[$typeCnt] += $affect;

                // lastId 未推进 → 没有更多记录
                if ($lastId == $prevLastId) {
                    $cursor[$type . '_done'] = true;
                }

                // 每批次立即保存游标，减少断点丢失窗口
                $task['lastSyncData']['progress']['comp'] = $syncDataComp;
                $this->syncSave($syncID, array(
                    'cursor'        => $cursor,
                    'lastSyncData'  => $task['lastSyncData']
                ));

                // 超时，跳出两层循环
                if ($this->isTimeout()) {
                    break 2;
                }
            } while (!_get($cursor, $type.'_done', false));
        }

        // 计算所有比对类型是否都已处理完毕
        $isDone = true;
        foreach ($compTypes as $type) {
            if (!_get($cursor, $type.'_done', false)) {
                $isDone = false;
                break;
            }
        }

        return $isDone;
    }
    
    // step3：执行复制
    private function runStepExec(&$task){
        $syncID     = $task['syncID'];
        $pathA      = $task['pathA'];
        $pathB      = $task['pathB'];
        $rptType    = $this->getRptType();  // 同名处理规则: 0-覆盖,1-跳过,2-重命名

        $jobModel   = $this->modelJobQueue;

        // 获取执行游标，用于断点续传
        $cursor = !empty($task['cursor']) ? $task['cursor'] : array();
        $lastId = _get($cursor, 'exec_lastId', 0); // 记录job_queue的id，从该id之后开始执行

        // 统计信息
        $syncDataExec = $task['lastSyncData']['progress']['exec'];
        if (empty($syncDataExec)) {
            $syncDataExec = array(
                'total'     => $jobModel->getJobCnt($syncID), 
                'success'   => 0, 
                'fail'      => 0,
                // …… 按任务类型追加
            );
        }

        // 1.依次执行各job文件
        $isDone = true; // 默认为完成，如果有任务未执行，则设置为false
        $limit  = 500;  // 每次处理500个任务
        do {
            $jobs = $jobModel->getJobList($syncID, $lastId, $limit); // 获取待执行的任务
            if (empty($jobs)) break; // 没有需要继续执行的job列表

            foreach ($jobs as $job) {
                if ($this->isTimeout()) {
                    $isDone = false; // 超时，本次未完成
                    break 2; // 跳出两层循环
                }
                $jobID      = $job['id'];
                $action     = $job['action'];
                $path       = $job['path'];
                $srcMtime   = $job['srcMtime'];
                $srcSize    = $job['srcSize'];

                // TODO 物理操作可以放到事务之外，否则失败会导致物理和db不一致，概率极低，暂不处理
                $jobModel->model->startTrans(); // 开始事务
                try {
                    $srcPath = '';
                    $destPath = '';
                    $targetSide = ''; // 更新file表时，需要知道是更新A侧还是B侧

                    $jobModel->edit($jobID, array('status' => 1));

                    // 1.开始执行复制等操作
                    switch ($action) {
                        case 'CREATE_A_TO_B':
                        case 'UPDATE_A_TO_B':
                        case 'CONFLICT_A_WINS':
                            $srcPath = $this->getAbsPath($path, $pathA);
                            $destPath = $this->getAbsPath($path, $pathB);
                            $targetSide = 'B';
                            // 复制
                            $pathTo = get_path_father($destPath);
                            $res = $this->checkTruePath($srcPath, $destPath, $pathTo);
                            if ($res !== null) break;
                            $res = IO::copy($srcPath, $pathTo, $rptType);
                            break;
                        case 'CREATE_B_TO_A':
                        case 'UPDATE_B_TO_A':
                        case 'CONFLICT_B_WINS':
                            $srcPath = $this->getAbsPath($path, $pathB);
                            $destPath = $this->getAbsPath($path, $pathA);
                            $targetSide = 'A';
                            // 复制
                            $pathTo = get_path_father($destPath);
                            $res = $this->checkTruePath($srcPath, $destPath, $pathTo);
                            if ($res !== null) break;
                            $res = IO::copy($srcPath, $pathTo, $rptType);
                            break;
                        case 'MKDIR_A_TO_B':
                        case 'MKDIR_B_TO_A':
                            $tArr = explode('_',$action);
                            $side = $targetSide = end($tArr);
                            $pathName = 'path'.$side;
                            $pathBase = ${$pathName};
                            $destPath = $this->getAbsPath($path, $pathBase);
                            $res = IO::mkdir($destPath);    // 支持多层级目录创建
                            break;
                        case 'DELETE_FROM_B':
                        case 'DELETE_FROM_A':   // 删除
                        case 'RMDIR_FROM_A':
                        case 'RMDIR_FROM_B':
                            $tArr = explode('_',$action);
                            $side = $targetSide = end($tArr);
                            $pathName = 'path'.$side;
                            $pathBase = ${$pathName};
                            $destPath = $this->getAbsPath($path, $pathBase);
                            $info = IO::infoFull($destPath);
                            if ($info === false) {
                                $res = true;
                            } else {
                                $destPath = $info['path'];
                                $res = IO::remove($destPath);
                            }
                            break;
                        default:
                            // 未知action，视为失败
                            $res = false;
                            $this->writeLog("未知操作指令: {$action} for path: {$path}");
                            break;
                    }

                    // 2. 判断操作结果
                    // 2.1 操作失败
                    if (!$res) {
                        // 记录失败日志
                        $errMsg = "{$action} {$path} 失败";
                        $this->writeLog($errMsg);

                        // 更新job_queue状态
                        $update = array();
                        $update['retry'] = intval($job['retry']) + 1;
                        if ($update['retry'] >= $this->maxRetryCnt) {
                            // 达到最大重试次数，标记为永久失败
                            $update['status'] = 9;
                            $this->writeLog($errMsg . " (达到最大重试次数)");
                        } else {
                            // 未达到最大重试次数，标记为失败待重试
                            $update['status'] = 3; // 失败待重试
                        }
                        $jobModel->edit($jobID, $update);
                        $syncDataExec['fail']++;
                        $jobModel->model->commit(); // 提交事务以保存失败状态
                        continue; // 继续下一个job
                    }

                    // 2.2 操作成功
                    // 统计各类操作成功数量
                    $actCnt = $action . '_cnt';
                    if (empty($syncDataExec[$actCnt])) {
                        $syncDataExec[$actCnt] = 0;
                    }
                    $syncDataExec[$actCnt]++;

                    // 更新job_queue状态为成功
                    $jobModel->edit($jobID, array('status' => 2)); // 2-成功
                    $syncDataExec['success']++;

                    // 3. 更新文件基线表 (plugin_storesync_file)
                    $this->_saveFileBase($action,$targetSide,$path,$destPath,$srcMtime,$srcSize);

                    $jobModel->model->commit(); // 提交事务
                } catch (Exception $e) {
                    $jobModel->model->rollback(); // 回滚事务
                    $text = $e->getMessage();
                    $this->writeLog("操作执行异常, jobID {$jobID}, action {$action}, path {$path}: {$text}");
                    // 标记job为失败，并增加重试次数
                    $update = array(
                        'status' => 3,
                        'retry'  => intval($job['retry']) + 1
                    );
                    $jobModel->edit($jobID, $update);
                    $syncDataExec['fail']++;
                    // 不中断整个任务，继续处理下一个job
                }
                // 只对 status=0 的新任务推进 lastId，status=3 的重试任务不参与分页
                if ($job['status'] == 0) {
                    $lastId = $jobID;
                }
            }

            $cursor['exec_lastId'] = $lastId; // 保存当前游标
            $task['lastSyncData']['progress']['exec'] = $syncDataExec;
            $this->syncSave($syncID, array(
                'cursor'        => $cursor,
                'lastSyncData'  => $task['lastSyncData']
            ));

        } while (!empty($jobs) && !$this->isTimeout());

        // 2.检查是否所有job都已完成（status=0或3的未完成任务）
        if ($isDone) {
            $leftCnt = $jobModel->getJobCnt($syncID, array('in', array(0,3)));
            if ($leftCnt > 0) {
                $isDone = false; // 还有未完成的job
            } else {
                // 所有job都已执行完毕
                $cursor['exec_lastId'] = 0; // 重置游标
                $task['lastSyncData']['progress']['exec'] = $syncDataExec;
                $this->syncSave($syncID, array(
                    'cursor'        => $cursor,
                    'lastSyncData'  => $task['lastSyncData']
                ));
            }
        }

        return $isDone;
    }

    // 根据执行结果更新文件基线表
    // 只有复制、更新、创建文件/夹操作才需要更新，删除则直接删除
    private function _saveFileBase($action,$targetSide,$path,$destPath,$srcMtime,$srcSize) {
        if (!$targetSide) return;
        $syncID = $this->syncID;
        // 删除
        if (strpos($action, 'DELETE') !== false || strpos($action, 'RMDIR') !== false) {
            $this->modelFile->remove($syncID, $path);
            return;
        }

        // 复制、更新、创建文件/夹
        // 复制生成目标文件/夹后，mtime可能有变化，重新获取；原文件/夹取原始值（扫描获取）
        $destInfo = IO::infoSimple($destPath);
        $actMtime = _get($destInfo, 'modifyTime', 0);
        $actSize  = _get($destInfo, 'size', 0);

        $updateFile = array();
        $isFolder = (strpos($action, 'MKDIR') !== false) ? 1 : 0;

        // 根据targetSide更新相应mtime/size，同时更新源mtime/size
        if ($targetSide == 'A') {
            $updateFile['mtime_A'] = $actMtime;
            $updateFile['size_A']  = $actSize;
            $updateFile['mtime_B'] = $srcMtime; // 源文件mtime
            $updateFile['size_B']  = $srcSize;  // 源文件size
        } else { // 'B'
            $updateFile['mtime_B'] = $actMtime;
            $updateFile['size_B']  = $actSize;
            $updateFile['mtime_A'] = $srcMtime; // 源文件mtime
            $updateFile['size_A']  = $srcSize;  // 源文件size
        }
        // TODO 如果指定fileID，源文件需要重新获取

        // 如果文件不存在，则创建；存在则更新
        $fileExist = $this->modelFile->find($syncID, $path);
        if ($fileExist) {
            $this->modelFile->edit($syncID, $path, $updateFile);
        } else {
            $updateFile['path'] = $path;
            $updateFile['isFolder'] = $isFolder;
            $this->modelFile->add($syncID, $updateFile);
        }
    }

    // 添加日志
    private function writeLog($msg) {
        // if ($this->isManual) echoLog($msg);
        write_log("[task-{$this->syncID}] ". $msg, 'storesync');
    }


}