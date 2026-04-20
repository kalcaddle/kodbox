<?php 
/**
 * 存储文件批量导入
 * 250w（100w文件+150w文件夹）：耗时2小时；内存峰值2GB
 */
class batchImport {

    private $sModel;
    private $fModel;

    private $targetType;
    private $targetID;
    private $targetLevel;
    private $ioType;
    private $ioDriver;
    private $hashMd5 = 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';  // 占位，后续更新

    // 核心映射（按需加载，部分可清理）
    private $folderMap      = array();  // relPath => sourceID
    private $levelMap       = array();  // sourceID => parentLevel (含自己)
    private $fileBuffer     = array();

    // 缓存系统（改进的清理策略）
    private $pathCache      = array();  // path => fileID（可安全清理）
    private $existingPaths  = array();  // 已存在的完整路径缓存 [relPath => sourceID] - 仅在文件夹创建阶段使用
    private $parentChildMap = array();  // 父目录ID => [name => sourceID] (用于文件查重) - 仅在文件夹创建阶段使用
    private $parentFileCache= array();  // parentID => array(name => fileInfo)（按LRU清理）
    private $parentFileNames= array();  // parentID => array(name => true) （按LRU清理）
    private $newFolderIDs   = array();  // 本次导入新建的文件夹ID

    // 重名处理缓存（改进的清理策略）
    private $renameCache = array(); // parentID_baseName => [可用名称列表] - 仅在当前批次使用

    // 缓存管理
    private $parentCacheLRU = array(); // parentID的访问顺序，用于LRU清理
    private $cacheSizeLimit = 1000; // 父目录缓存的大小限制

    // 统计
    private $importedCount  = 0;
    private $startTime      = 0;
    private $totalFiles     = 0;
    private $totalFolders   = 0;

    // 导入选项
    private $options = array(
        'duplicateMode'     => REPEAT_RENAME,   // 重名处理模式：REPEAT_RENAME/REPEAT_SKIP/REPEAT_REPLACE
        'folderBatchSize'   => 8000,            // 文件夹批次大小（减小以降低内存占用）
        'fileBatchSize'     => 30000,           // 文件批次大小（减小以降低内存占用）
        'subBatchSize'      => 15000,           // 子批次大小（减小以降低内存占用）
        'preloadBatchSize'  => 1500,            // 预加载批次大小
        'safeCacheCleanThreshold' => 150000,    // 安全缓存清理阈值
        'preGenerateNames'  => 10,              // 预生成重名数量
        'enableSafeCleanup' => true,            // 启用安全清理
    );

    // 内存监控
    private $lastMemoryCheck = 0;

    // 导入任务ID（用于日志）
    private $taskId;
    private $tmpFileCnt = 0;    // 临时文件数量（用于任务进度）

    // 路径长度超出文件
    private $errPathFiles   = array();
    // 数据库sql长度
    private $dbPacketValue  = 0;

    public function __construct($task) {
        $this->impTask = $task;
        $this->taskId = substr($task->task['id'], 0, 6);

        $repeat = Model('UserOption')->get('fileRepeat');
        if ($repeat) $this->options['duplicateMode'] = $repeat;

        // 数据库优化配置
        $this->prepareDatabase();
	}
    public function __destruct() {
        // 数据库恢复配置
        $this->restoreDatabase();
    }

    /**
     * 存储导入 - 主入口方法
     */
    public function import($pathFrom, $pathTo, $chunkList = array()) {
        if (!$chunkList) {
            $this->writeLog('原始目录列表为空');
            return;
        }

        $this->startTime = microtime(true);

        // // 数据库优化配置
        // $this->prepareDatabase();

        $this->sModel = Model('Source');
        $this->fModel = Model('File');

        // 解析路径，获取目标目录信息
        $pathFrom = rtrim($pathFrom, '/');
        $parse    = KodIO::parse($pathFrom);
        $this->ioType   = $parse['id'];
        $this->ioDriver = IO::init($pathFrom);
        $parse    = KodIO::parse($pathTo);
        $targetID = $parse['id'];
        $info = $this->sModel->where(array('sourceID'=>$targetID))->field('targetType,targetID,parentLevel,isFolder')->find();
        if (!$info || !$info['isFolder']) {
            return $this->writeLog("目标目录不存在: {$pathTo}", false);
        }
        $this->targetType   = $info['targetType'];
        $this->targetID     = $info['targetID'];
        $this->targetLevel  = $info['parentLevel'] . $targetID . ',';

        // 初始化根目录映射
        $this->folderMap[''] = $targetID;
        $this->levelMap[$targetID] = $this->targetLevel;

        try {
            // 执行导入
            $this->importWithFullPathCache($pathFrom, $targetID, $chunkList);

            // 处理剩余缓冲区
            $this->flushBuffer(true);

            // 收集异常文件信息（path>255）
            $this->check255Path(KodIO::make($targetID), true);

            // 统计信息
            $elapsed = round(microtime(true) - $this->startTime);
            $speed   = $elapsed > 0 ? round($this->importedCount / $elapsed) : 0;

            $data = "导入成功！总文件数：{$this->importedCount}，总文件夹数：{$this->totalFolders}；";
            $data .= "总耗时：{$elapsed} 秒（" . round($elapsed/60, 1) . " 分钟)，平均速度：{$speed} 文件/秒";
            $this->writeLog($data);

        } catch (Exception $e) {
            $this->writeLog("导入失败，已处理文件数：{$this->importedCount}；当前批次大小：" . count($this->fileBuffer));

            $this->flushBuffer(true);
            // $this->restoreDatabase();

            $this->writeLog("导入失败！错误信息：" . $e->getMessage(), false);
        }

        // // 恢复数据库配置
        // $this->restoreDatabase();

        // 清理内存
        $this->cleanup();

        return true;    // TODO 可以返回更多信息
    }

    /**
     * 导入主逻辑（使用完整路径缓存，优化内存占用）
     */
    private function importWithFullPathCache($pathFrom, $targetID, $list=array()) {
        // 统计文件数量
        $fileCount = array_reduce($list, function($cnt, $item) {
            return $cnt + (!$item['folder'] ? 1 : 0);
        }, 0);
        $this->totalFiles = $fileCount;
        $this->impTask->task['taskTotal'] += $fileCount;    // 仅统计文件

        $this->writeLog('正在分析文件夹结构...');
        // 1. 收集所有需要创建的文件夹路径
        $allFolders = $this->collectAllFolderPaths($list, $pathFrom);

        // 立即释放部分内存
        gc_collect_cycles();

        $this->impTask->task['currentTitle'] = LNG('storeImport.task.impFolder');
        $this->impTask->update(0,true);
        if (empty($allFolders)) {
            $this->writeLog('没有发现文件夹');
        } else {
            $this->totalFolders = count($allFolders);
            $this->writeLog("发现{$this->totalFolders}个唯一文件夹路径");

            // 2. 一次性查询目标目录下所有已存在的文件夹
            $this->writeLog('正在查询已存在的文件夹...');
            $this->loadExistingFoldersCache($targetID);

            // 3. 批量创建文件夹（使用缓存）
            $this->writeLog('正在批量创建文件夹...');
            $this->createFoldersWithFullPathCache($allFolders, $targetID);
        }

        // step1：文件夹创建完成后，释放不再需要的缓存
        $this->writeLog('文件夹创建完成，释放相关缓存...');
        $this->existingPaths = array();    // 不再需要，因为已转换为 $folderMap
        $this->parentChildMap = array();   // 不再需要，文件夹关系已建立

        // 释放文件夹列表内存
        unset($allFolders);

        gc_collect_cycles();
        $this->writeLog('内存清理后：'.sprintf("%.3fM",memory_get_usage()/(1024*1024)));

        // 4.收集并创建文件
        $this->writeLog('正在收集文件信息...');
        $this->impTask->task['currentTitle'] = LNG('storeImport.task.impFile');
        $this->impTask->update(0,true);
        $this->collectFilesFromFlatList($list, $pathFrom);

        // step2：文件收集完成后，可以释放部分列表缓存
        unset($list); // 原始文件列表很大，可以释放
        gc_collect_cycles();

        $this->writeLog('文件夹和文件信息收集完成');
    }

    /**
     * 收集所有需要创建的文件夹路径（优化内存占用）
     */
    private function collectAllFolderPaths($list, $rootPath) {
        $rootLen = strlen($rootPath) + 1;
        $allFolders = array();

        // 使用生成器来逐步处理文件夹路径，减少内存占用
        $folderGenerator = function() use ($list, $rootLen) {
            foreach ($list as $item) {
                $full = $this->ioDriver->getPathOuter($item['path']);   // /a/b/c => {io:x}/b/c
                $rel  = ltrim(substr($full, $rootLen), '/');
                if ($rel === '' || $rel === false) continue;
                
                if ($item['folder']) {
                    yield $rel;
                    // 同时添加所有父级路径
                    $parts = explode('/', $rel);
                    if (count($parts) > 1) {
                        $current = '';
                        foreach ($parts as $i => $part) {
                            if ($i === count($parts) - 1) break;
                            $current = $current === '' ? $part : "$current/$part";
                            yield $current;
                        }
                    }
                } else {
                    // 文件路径：提取所有父目录
                    $dir = dirname($rel);
                    if ($dir !== '.' && $dir !== '') {
                        $parts = explode('/', $dir);
                        $current = '';
                        foreach ($parts as $part) {
                            if ($part === '') continue;
                            $current = $current === '' ? $part : "$current/$part";
                            yield $current;
                        }
                    }
                }
            }
        };

        // 将生成器的结果去重后返回
        foreach ($folderGenerator() as $folderPath) {
            $allFolders[$folderPath] = true;
        }
        return array_keys($allFolders);
    }

    /**
     * 一次性查询目标目录下所有已存在的文件夹
     */
    private function loadExistingFoldersCache($targetID) {
        $where = array(
            'isFolder'      => 1,
            'isDelete'      => 0,
            'parentLevel'   => array('like', $this->targetLevel . '%'),
        );
        $timeStart = microtime(true);
        $list = $this->sModel->where($where)->field('sourceID,name,parentID,parentLevel')->select();
        if (!$list) $list = array();
        $timeQuery = microtime(true) - $timeStart;
        $this->writeLog("查询已存在文件夹完成，找到" . (count($list)) . "个文件夹，耗时：".round($timeQuery*1000, 1) . "ms");
        if (empty($list)) return;

        // 构建路径缓存
        $idToPath = array(); // sourceID => 相对路径
        $idToPath[$targetID] = '';

        // 首先，按深度排序（通过parentLevel的长度）
        usort($list, function($a, $b) {
            $depthA = substr_count($a['parentLevel'], ',');
            $depthB = substr_count($b['parentLevel'], ',');
            return $depthA - $depthB;
        });

        // 逐个处理，构建路径
        foreach ($list as $item) {
            $sourceID = $item['sourceID'];
            $parentID = $item['parentID'];
            $name = $item['name'];

            // 构建相对路径
            if (isset($idToPath[$parentID])) {
                $parentPath = $idToPath[$parentID];
                $relPath = $parentPath === '' ? $name : "$parentPath/$name";
                $idToPath[$sourceID] = $relPath;

                // 存入完整路径缓存
                $this->existingPaths[$relPath] = $sourceID;

                // 存入父目录子项缓存
                if (!isset($this->parentChildMap[$parentID])) {
                    $this->parentChildMap[$parentID] = array();
                }
                $this->parentChildMap[$parentID][$name] = $sourceID;
            }
            $this->levelMap[$sourceID] = $item['parentLevel'] . $sourceID . ',';
        }

        // 最大内存占用项
        $this->writeLog("路径缓存构建完成，共" . count($this->existingPaths) . "个路径缓存，当前内存：".sprintf("%.3fM",memory_get_usage()/(1024*1024)));
    }

    /**
     * 使用完整路径缓存创建文件夹
     */
    private function createFoldersWithFullPathCache($allFolders, $targetID) {
        // 按深度分组
        $maxDepth = 0;
        $depthBuckets = array();
        foreach ($allFolders as $relPath) {
            $depth = substr_count($relPath, '/');
            $depthBuckets[$depth][] = $relPath;
            if ($depth > $maxDepth) {
                $maxDepth = $depth;
            }
        }

        // 按深度处理
        $totalCreated = 0;
        $batchData = array();
        for ($depth = 0; $depth <= $maxDepth; $depth++) {
            if (!isset($depthBuckets[$depth])) continue;
            // 分批次插入
            $thisDepthFolders = $depthBuckets[$depth];
            foreach ($thisDepthFolders as $relPath) {
                // 检查是否已存在（使用缓存）
                if (isset($this->existingPaths[$relPath])) {
                    $sourceID = $this->existingPaths[$relPath];
                    $this->folderMap[$relPath] = $sourceID;
                    continue;
                }
                // 检查是否已经创建过（在本次导入中）
                if (isset($this->folderMap[$relPath])) {
                    continue;
                }

                $parts  = explode('/', $relPath);
                $name   = array_pop($parts);
                $parentRel = implode('/', $parts);
                $parentID = $parentRel === '' ? $targetID : _get($this->folderMap,$parentRel,null);
                if (!$parentID) {
                    $parentID = $this->findOrCreateParentWithCache($parentRel, $targetID);
                    if (!$parentID) continue;
                }
                // 批量插入
                $batchData[] = array(
                    'relPath'   => $relPath,
                    'name'      => $name,
                    'parentID'  => $parentID
                );
                if (count($batchData) >= $this->options['folderBatchSize']) {
                    $createdInBatch = $this->batchInsertFoldersWithCache($batchData);
                    $totalCreated += $createdInBatch;
                    $batchData = array();
                    if ($totalCreated % 10000 == 0) {
                        $this->writeLog("已创建文件夹：{$totalCreated}/{$this->totalFolders}");
                    }
                }
            }
            // 处理剩余的批次数据
            if (!empty($batchData)) {
                $createdInBatch = $this->batchInsertFoldersWithCache($batchData);
                $totalCreated += $createdInBatch;
                $batchData = array();
            }
        }

        $this->writeLog("文件夹创建完成，共创建：{$totalCreated}个，跳过" . ($this->totalFolders - $totalCreated) . "个，当前内存：".sprintf("%.3fM",memory_get_usage()/(1024*1024)));
    }

    /**
     * 批量插入文件夹（使用缓存）
     */
    private function batchInsertFoldersWithCache($folders) {
        if (empty($folders)) return 0;

        $db = $this->sModel->db();
        $time = time();
        try {
            $db->startTrans();

            // 批量插入
            $insertData = array();
            foreach ($folders as $folder) {
                $insertData[] = array(
                    'sourceHash'  => $this->getSourceHash(),
                    'targetType'  => $this->targetType,
                    'targetID'    => $this->targetID,
                    'createUser'  => USER_ID,
                    'modifyUser'  => USER_ID,
                    'isFolder'    => 1,
                    'name'        => $folder['name'],
                    'fileType'    => '',
                    'parentID'    => $folder['parentID'],
                    'parentLevel' => $this->getParentLevel($folder['parentID']),
                    'fileID'      => 0,
                    'isDelete'    => 0,
                    'size'        => 0,
                    'createTime'  => $time,
                    'modifyTime'  => $time,
                    'viewTime'    => $time,
                );
            }
            $this->batchInsertSourceDirect($insertData);

            // 获取插入的ID并更新缓存
            $this->updateFolderCacheAfterInsert($folders);

            $db->commit();
            return count($folders);
        } catch (Exception $e) {
            $db->rollback();
            $this->writeLog("批量插入文件夹失败: " . $e->getMessage(), false);
        }
    }

    /**
     * 插入后更新文件夹缓存
     */
    private function updateFolderCacheAfterInsert($folders) {
        if (empty($folders)) return;

        // 查询最后插入的一批数据
        $conditions = array();
        foreach ($folders as $folder) {
            $conditions[] = array(
                'parentID'  => $folder['parentID'],
                'name'      => $folder['name']
            );
        }

        // 分批查询
        $batchSize = 1000;  // 注意：设置过大会非常慢
        $allResults = array();
        for ($i = 0; $i < count($conditions); $i += $batchSize) {
            $batch = array_slice($conditions, $i, $batchSize);

            $orWhere = array();
            foreach ($batch as $item) {
                $orWhere[] = array('parentID' => $item['parentID'], 'name' => $item['name']);
            }
            $orWhere['_logic'] = 'OR';
            $where = array(
                'isFolder' => 1, 
                'isDelete' => 0, 
                $orWhere
            );
            $list = $this->sModel->where($where)->field('sourceID,name,parentID,parentLevel')->select();
            if ($list) {
                $allResults = array_merge($allResults, $list);
            }
        }

        // 更新缓存和映射
        foreach ($allResults as $item) {
            foreach ($folders as $folder) {
                if ($folder['parentID'] == $item['parentID'] && $folder['name'] == $item['name']) {
                    $relPath = $folder['relPath'];
                    $sourceID = $item['sourceID'];

                    // 更新映射
                    $this->folderMap[$relPath] = $sourceID;
                    $this->levelMap[$sourceID] = $item['parentLevel'] . $sourceID . ',';
                    // 更新缓存——似乎也可以不要
                    $this->existingPaths[$relPath] = $sourceID;
                    // 记录新建文件夹ID
                    $this->newFolderIDs[] = $sourceID;

                    if (!isset($this->parentChildMap[$item['parentID']])) {
                        $this->parentChildMap[$item['parentID']] = array();
                    }
                    $this->parentChildMap[$item['parentID']][$item['name']] = $sourceID;
                    break;
                }
            }
        }
    }

    /**
     * 查找或创建父目录（使用缓存）
     */
    private function findOrCreateParentWithCache($relPath, $targetID) {
        if ($relPath === '') return $targetID;

        // 检查缓存
        if (isset($this->existingPaths[$relPath])) {
            return $this->existingPaths[$relPath];
        }
        // 检查本次导入已创建的
        if (isset($this->folderMap[$relPath])) {
            return $this->folderMap[$relPath];
        }

        $parts = explode('/', $relPath);
        $name = array_pop($parts);
        $parentRel = implode('/', $parts);
        $parentID = $this->findOrCreateParentWithCache($parentRel, $targetID);
        if (!$parentID) return null;

        // 创建父目录
        $folderID = $this->createSingleFolderWithCache($name, $parentID, time());
        $this->folderMap[$relPath] = $folderID;
        $parentLevel = $this->getParentLevel($parentID);
        $this->levelMap[$folderID] = $parentLevel . $folderID . ',';

        // 更新缓存
        $this->existingPaths[$relPath] = $folderID;
        if (!isset($this->parentChildMap[$parentID])) {
            $this->parentChildMap[$parentID] = array();
        }
        $this->parentChildMap[$parentID][$name] = $folderID;
        
        return $folderID;
    }

    /**
     * 单个创建文件夹（更新缓存）
     */
    private function createSingleFolderWithCache($name, $parentID, $mtime) {
        $time = time();
        $data = array(
            'sourceHash'  => $this->getSourceHash(),
            'targetType'  => $this->targetType,
            'targetID'    => $this->targetID,
            'createUser'  => USER_ID,
            'modifyUser'  => USER_ID,
            'isFolder'    => 1,
            'name'        => $name,
            'fileType'    => '',
            'parentID'    => $parentID,
            'parentLevel' => $this->getParentLevel($parentID),
            'fileID'      => 0,
            'isDelete'    => 0,
            'size'        => 0,
            'createTime'  => $time,
            'modifyTime'  => $mtime,
            'viewTime'    => $time,
        );
        $this->sModel->add($data);
        $folderID = $this->sModel->getLastInsID();
        $this->newFolderIDs[] = $folderID;
        return $folderID;
    }

    /**
     * 收集文件信息（优化内存占用）
     */
    private function collectFilesFromFlatList($list, $rootPath) {
        $rootLen = strlen($rootPath) + 1;
        $fileCount = 0;

        $time = time();
        $fileBufferSize = 0;
        foreach ($list as $item) {
            if ($item['folder']) continue;

            $full = $this->ioDriver->getPathOuter($item['path']);
            $rel  = ltrim(substr($full, $rootLen), '/');
            if ($rel === '' || $rel === false) {
                $this->writeLog("忽略导入：相对路径为空，path={$full}");
                continue;
            }

            // 检查是否超过255个字符（io_file.path长度）
            if (!$this->check255Path($full)) {
                // $this->writeLog("忽略导入：路径长度超出255个字符，path={$full}");
                continue;
            }

            $dir = dirname($rel);
            $dir = ($dir === '.' || $dir === '') ? '' : $dir;
            $parentID = _get($this->folderMap,$dir,null);
            // 如果父目录不存在，可能是没有显式的文件夹记录，尝试使用根目录
            if (!$parentID) {
                $parentID = $this->folderMap[''];
                if (!$parentID) {
                    $this->writeLog("忽略导入：找不到父目录（dir={$dir}），path={$full}");
                    continue;
                }
            }

            $name = get_path_this($full);
            $fileData = array(
                'path'       => $full,
                'name'       => $name,
                'size'       => $item['size'],
                'ext'        => get_path_ext($name),
                'mtime'      => _get($item, 'modifyTime', $time),
                'parentID'   => $parentID,
                'parentLevel'=> $this->getParentLevel($parentID),
            );

            // 检查内存使用情况，如果超过阈值则提前刷新缓冲区
            $this->checkMemoryUsage();

            $this->fileBuffer[] = $fileData;
            $fileCount++;
            $fileBufferSize++;
            if ($fileBufferSize >= $this->options['fileBatchSize']) {
                $this->flushBuffer();
                $fileBufferSize = 0;
            }
        }
        // 处理剩余文件
        if ($fileBufferSize > 0) {
            $this->flushBuffer();
        }

        $this->writeLog("收集文件完成：共 {$fileCount} 个文件");
    }

    /**
     * 检查内存使用情况，必要时清理内存
     */
    private function checkMemoryUsage() {
        $currentMemory = memory_get_usage();
        $currentTime = time();

        // 每1000个文件或每5秒检查一次内存
        if (count($this->fileBuffer) % 1000 !== 0 && $currentTime - $this->lastMemoryCheck < 5) {
            return;
        }
        $this->lastMemoryCheck = $currentTime;

        // 如果内存使用超过1.5GB，提前清理
        if ($currentMemory > 1.5 * 1024 * 1024 * 1024) {
            $this->writeLog("内存使用超过阈值，提前清理: " . sprintf("%.3fM", $currentMemory/(1024*1024)));
            $this->flushBuffer();
            gc_collect_cycles();
        }
    }

    // 检查路径长度
    private function check255Path($path, $save=false) {
        // 收集路径长度超出文件
        if (!$save) {
            if (mb_strlen($path) <= 255) return true;
            $this->errPathFiles[] = $path;
            return false;
        }
        // 保存至文件，并更新任务信息
        if (!$this->errPathFiles) return false;
        $path = IO::mkdir($path.LNG('storeImport.task.errLog'));  // {source:123}/导入失败文件(长度超255字符)
        $name = 'task-'.$this->impTask->task['id'].'.txt';   // task-xxx.txt
        $path = IO::mkfile($path.$name,'',REPEAT_SKIP);
        if (!$path) {
            $this->writeLog('超255字符记录文件创建失败，异常文件记录：'.count($this->errPathFiles));
            $this->errPathFiles = array();
            return false;
        }
        $content = IO::getContent($path);
        IO::setContent($path, $content . implode(PHP_EOL, $this->errPathFiles).PHP_EOL);
        $this->errPathFiles = array();
        return false;
    }

    /**
     * 批量处理文件缓冲区
     */
    private function flushBuffer($force = false) {
        if (empty($this->fileBuffer) && !$force) return;

        $db = $this->sModel->db();
        $currentBatch = count($this->fileBuffer);

        // 按子批次大小分块处理
        $chunks = array_chunk($this->fileBuffer, $this->options['subBatchSize']);
        foreach ($chunks as $batch) {
            $db->startTrans();
            try {
                $this->tmpFileCnt = 0;
                $this->processBatchWithCache($batch);
                // $this->impTask->update(count($batch));  // 没有严格判断是否导入成功
                $this->impTask->update((count($batch) - $this->tmpFileCnt));
                // $this->tmpFileCnt = 0;  // 没有必要
                $db->commit();
            } catch (Exception $e) {
                $db->rollback();
                $this->writeLog("文件批次处理失败: " . $e->getMessage(), false);
            }
        }

        $this->importedCount += $currentBatch;
        $this->fileBuffer = array();

        // 执行安全缓存清理（仅清理pathCache）
        if ($this->options['enableSafeCleanup']) {
            $this->safeCacheCleanup();
        }

        // 显示进度
        if ($this->importedCount % 10000 == 0 || $force) {
            $elapsed = microtime(true) - $this->startTime;
            $speed   = $elapsed > 0 ? round($this->importedCount / $elapsed) : 0;
            $percent = $this->totalFiles > 0 ? round(($this->importedCount / $this->totalFiles) * 100, 1) : 0;
            
            $msg = "进度: {$percent}% | 已导入: {$this->importedCount} | 速度: {$speed} 文件/秒 | 用时: " . round($elapsed) . "s";
            $this->writeLog($msg);
        }
    }

    /**
     * 处理一个批次的数据
     */
    private function processBatchWithCache($batch) {
        // $this->writeLog("文件批处理开始，共".count($batch)."条记录");
        $ioFileInsert = array();
        $sourceInsert = array();
        $linkInc      = array();
        $linkDec      = array();
        $forceFileID  = array();

        $duplicateMode = $this->options['duplicateMode'];

        // 1. 预加载所有需要的缓存
        // 1.1 批量查询已存在的物理文件（按路径）
        $paths = array_to_keyvalue($batch, '', 'path');
        $existFiles = $this->getExistFilePaths($paths); // 耗时

        // 1.2 批量查询当前批次中所有父目录下的文件（用于查重）
        $parentIds = array_to_keyvalue($batch, '', 'parentID');
        $parentIds = array_unique($parentIds);
        $this->preloadFilesForParents($parentIds);  // 耗时

        // 2. 批量处理重名文件（关键点）
        $batch = $this->batchResolveDuplicateNames($batch, $duplicateMode, $existFiles, $linkInc, $linkDec, $forceFileID);

        // 3. 处理新文件
        foreach ($batch as $item) {
            $finalName = $item['name'];
            $pathKey = $item['path'];
            $parentID = $item['parentID'];

            // 跳过已处理的覆盖文件
            if (isset($item['processed']) && $item['processed'] === 'skip') {
                continue;
            }
            // 如果是覆盖模式且已处理过
            if (isset($item['processed']) && $item['processed'] === 'replace') {
                // 已在批量处理中处理过
                continue;
            }
            // 处理新文件
            if (isset($existFiles[$pathKey])) {
                $fileID = $existFiles[$pathKey];
                $linkInc[$fileID] = _get($linkInc,$fileID,0) + 1;
            } else {
                $ioFileInsert[] = array(
                    'name'       => $item['name'],
                    'size'       => $item['size'],
                    'ioType'     => $this->ioType,
                    'path'       => $pathKey,
                    'hashSimple' => '',
                    'hashMd5'    => $this->hashMd5,
                    'linkCount'  => 1,
                    'modifyTime' => $item['mtime'],
                );
                $fileID = 'PENDING:' . (count($ioFileInsert) - 1);
            }
            $sourceInsert[] = array(
                'sourceHash'   => $this->getSourceHash(),
                'targetType'   => $this->targetType,
                'targetID'     => $this->targetID,
                'createUser'   => USER_ID,
                'modifyUser'   => USER_ID,
                'isFolder'     => 0,
                'name'         => $finalName,
                'fileType'     => $item['ext'],
                'parentID'     => $parentID,
                'parentLevel'  => $item['parentLevel'],
                'fileID'       => $fileID,
                'isDelete'     => 0,
                'size'         => $item['size'],
                'modifyTime'   => $item['mtime'],
            );

            // 更新父目录文件缓存（这是关键缓存，不清除）
            if (!isset($this->parentFileNames[$parentID])) {
                $this->parentFileNames[$parentID] = array();
            }
            $this->parentFileNames[$parentID][$finalName] = true;
        }
        // $this->writeLog("文件批处理进行中，待插入io_file: " . count($ioFileInsert) . "，io_source: " . count($sourceInsert));

        // 4. 批量插入io_file记录
        $pathToNewId = array();
        if ($ioFileInsert) {
            $this->batchInsertFileDirect($ioFileInsert);

            // 批量获取新插入的fileID
            $newPaths = array_to_keyvalue($ioFileInsert, '', 'path');
            $pathToNewId = $this->getFileIdsByPaths($newPaths);
        }

        // 5. 回填 PENDING 的 fileID
        $this->replacePendingFileIDs($sourceInsert, $ioFileInsert, $pathToNewId);
        $this->replacePendingForceFileIDs($forceFileID, $ioFileInsert, $pathToNewId);

        // 6. 批量插入io_source记录
        if ($sourceInsert) {
            $this->batchInsertSourceDirect($sourceInsert);
            $this->updateTaskCnt(count($sourceInsert));

            // 更新文件名缓存（这是关键缓存，不清除）
            foreach ($sourceInsert as $item) {
                if (!isset($this->parentFileCache[$item['parentID']])) {
                    $this->parentFileCache[$item['parentID']] = array();
                }
                $this->parentFileCache[$item['parentID']][$item['name']] = array(
                    'sourceID'  => 'NEW',   // NEW_FILE
                    'fileID'    => $item['fileID']
                );
            }
        }
        
        // 7. 更新被覆盖的文件
        $this->batchUpdateFileIDs($forceFileID);

        // 8. 更新文件引用计数
        $this->updateLinkCounts($linkInc, $linkDec);

        // $this->writeLog("文件批处理完成");
    }

    // 更新任务进度
    private function updateTaskCnt($cnt) {
        $cnt = floor($cnt / 4);    // 4处调用，所以每次仅更新1/4
        $this->tmpFileCnt += $cnt;
        $this->impTask->update($cnt, true);
    }

    /**
     * 批量处理重名文件（核心优化）
     */
    private function batchResolveDuplicateNames(&$batch, $duplicateMode, $existFiles, &$linkInc, &$linkDec, &$forceFileID) {
        $result = array();

        if ($duplicateMode === 'skip' || $duplicateMode === 'replace') {
            // 对于skip和replace模式，只需过滤或标记
            foreach ($batch as $item) {
                $parentID = $item['parentID'];
                $name = $item['name'];
                if (isset($this->parentFileNames[$parentID][$name])) {
                    if ($duplicateMode === 'skip') {
                        // 跳过
                        $item['processed'] = 'skip';
                    } elseif ($duplicateMode === 'replace') {
                        // 标记为覆盖
                        $item['processed'] = 'replace';
                        $this->handleReplaceDuplicate($item, $existFiles, $linkInc, $linkDec, $forceFileID);
                    }
                }
                $result[] = $item;
            }
        } else if ($duplicateMode === 'rename') {
            // 重命名模式：批量生成新名称
            $renameGroups = array();

            // 先分组，相同父目录和基础名称的放在一起处理
            foreach ($batch as $index => $item) {
                $parentID = $item['parentID'];
                $name = $item['name'];
                if (isset($this->parentFileNames[$parentID][$name])) {
                    $base = pathinfo($name, PATHINFO_FILENAME);
                    $ext = pathinfo($name, PATHINFO_EXTENSION);
                    $ext = $ext ? ".$ext" : '';

                    $groupKey = $parentID . '_' . $base . '_' . $ext;
                    if (!isset($renameGroups[$groupKey])) {
                        $renameGroups[$groupKey] = array(
                            'base' => $base,
                            'ext' => $ext,
                            'parentID' => $parentID,
                            'indices' => array()
                        );
                    }
                    $renameGroups[$groupKey]['indices'][] = $index;
                }
            }
            // 为每个组批量生成新名称
            foreach ($renameGroups as $groupKey => $group) {
                $newNames = $this->generateMultipleNewNames(
                    $group['parentID'], 
                    $group['base'] . $group['ext'], 
                    count($group['indices'])
                );
                // 应用新名称
                foreach ($group['indices'] as $i => $index) {
                    if (isset($newNames[$i])) {
                        $batch[$index]['name'] = $newNames[$i];
                    }
                }
            }
            $result = $batch;
        } else {
            $result = $batch;
        }

        return $result;
    }
    
    /**
     * 处理覆盖重复文件
     */
    private function handleReplaceDuplicate($item, $existFiles, &$linkInc, &$linkDec, &$forceFileID) {
        $parentID = $item['parentID'];
        $name = $item['name'];
        $pathKey = $item['path'];

        $existingFile = $this->parentFileCache[$parentID][$name];
        // 旧文件引用-1
        if ($existingFile['fileID'] > 0) {
            $linkDec[$existingFile['fileID']] = _get($linkDec,$existingFile['fileID'],0) + 1;
        }
        // 新文件处理
        if (isset($existFiles[$pathKey])) {
            $newFileID = $existFiles[$pathKey];
            $linkInc[$newFileID] = _get($linkInc,$newFileID,0) + 1;
        } else {
            // 将在后面统一处理
            $newFileID = 'PENDING_REPLACE:' . $pathKey;
        }

        // 记录需要更新的sourceID
        $forceFileID[$existingFile['sourceID']] = $newFileID;
    }
    
    /**
     * 生成多个新名称
     */
    private function generateMultipleNewNames($parentID, $baseName, $count) {
        $cacheKey = $parentID . '_' . $baseName;

        // 检查缓存（不清除，除非导入结束）
        if (isset($this->renameCache[$cacheKey]) && count($this->renameCache[$cacheKey]) >= $count) {
            $names = array_splice($this->renameCache[$cacheKey], 0, $count);
            return $names;
        }

        // 生成新名称
        $base = pathinfo($baseName, PATHINFO_FILENAME);
        $ext = pathinfo($baseName, PATHINFO_EXTENSION);
        $ext = $ext ? ".$ext" : '';

        $names = array();
        $i = 1;
        $maxTries = 1000; // 防止无限循环
        while (count($names) < $count && $i <= $maxTries) {
            $newName = "{$base} ({$i}){$ext}";
            if (!isset($this->parentFileNames[$parentID][$newName])) {
                $names[] = $newName;
            }
            $i++;
        }

        // 缓存剩余的名称（不清除）
        $this->renameCache[$cacheKey] = $names;
        return $names;
    }

    /**
     * 预加载父目录下的所有文件缓存
     */
    private function preloadFilesForParents($parentIds) {
        if (empty($parentIds)) return;

        // 过滤掉已经加载过的父目录和新创建的目录
        $newFoldersMap = array_flip($this->newFolderIDs);
        $needLoad = array();
        foreach ($parentIds as $pid) {
            if (!isset($this->parentFileCache[$pid]) && !isset($newFoldersMap[$pid])) {
                $needLoad[] = $pid;
            } else if (isset($this->parentFileCache[$pid])) {
                // 更新LRU
                $this->updateParentCacheLRU($pid);
            }
        }
        unset($newFoldersMap);
        if (empty($needLoad)) return;

        $totalToLoad = count($needLoad);

        // 使用更高效的批量查询
        $batchSize = $this->options['preloadBatchSize'];
        for ($i = 0; $i < $totalToLoad; $i += $batchSize) {
            $batch = array_slice($needLoad, $i, $batchSize);

            $where = array(
                'parentID'  => array('in', $batch),
                'isDelete'  => 0,
                'isFolder'  => 0
            );
            $list = $this->sModel->where($where)->field('sourceID,fileID,name,parentID')->select();
            if (!$list) $list = array();
            $this->updateTaskCnt(count($batch));

            // 构建缓存
            foreach ($list as $file) {
                $pid = $file['parentID'];
                $name = $file['name'];

                if (!isset($this->parentFileCache[$pid])) {
                    $this->parentFileCache[$pid] = array();
                    $this->parentFileNames[$pid] = array();
                }
                $this->parentFileCache[$pid][$name] = array(
                    'sourceID' => $file['sourceID'],
                    'fileID'   => $file['fileID']
                );
                $this->parentFileNames[$pid][$name] = true;
            }
            unset($list);

            // 确保所有查询的父目录都在缓存中有记录（即使为空）
            foreach ($batch as $pid) {
                if (!isset($this->parentFileCache[$pid])) {
                    $this->parentFileCache[$pid] = array();
                    $this->parentFileNames[$pid] = array();
                }
                // 更新LRU
                $this->updateParentCacheLRU($pid);
            }

            // 清理超出限制的缓存
            $this->cleanupParentCache();

            // 释放内存
            if ($i % 20000 == 0) {
                gc_collect_cycles();
            }
        }
    }

    /**
     * 更新父目录缓存的LRU顺序
     */
    private function updateParentCacheLRU($parentID) {
        // 移除旧位置
        $key = array_search($parentID, $this->parentCacheLRU);
        if ($key !== false) {
            unset($this->parentCacheLRU[$key]);
        }
        // 添加到末尾（最近使用）
        $this->parentCacheLRU[] = $parentID;
    }

    /**
     * 清理超出限制的父目录缓存
     */
    private function cleanupParentCache() {
        if (count($this->parentCacheLRU) <= $this->cacheSizeLimit) {
            return;
        }

        // 计算需要清理的数量
        $toClean = count($this->parentCacheLRU) - $this->cacheSizeLimit;
        // 清理最久未使用的缓存
        for ($i = 0; $i < $toClean; $i++) {
            $oldestParentID = array_shift($this->parentCacheLRU);
            unset($this->parentFileCache[$oldestParentID]);
            unset($this->parentFileNames[$oldestParentID]);
        }
    }

    /**
     * 批量查询已存在的文件路径
     */
    private function getExistFilePaths($paths) {
        $result = array();
        $pathsToQuery = array();

        // 使用缓存机制
        foreach ($paths as $path) {
            if (isset($this->pathCache[$path])) {
                if ($this->pathCache[$path] !== null) {
                    $result[$path] = $this->pathCache[$path];
                }
            } else {
                $pathsToQuery[] = $path;
            }
        }
        if (!empty($pathsToQuery)) {
            $pathsToQuery = array_unique($pathsToQuery);
            $batchSize = 5000;
            for ($i = 0; $i < count($pathsToQuery); $i += $batchSize) {
                $batch = array_slice($pathsToQuery, $i, $batchSize);
                // ALTER TABLE io_file ADD INDEX ioType_path (ioType, path(200));   // 效果不理想
                $where = array(
                    'ioType' => $this->ioType,
                    'path'   => array('in', $batch)
                );
                $list = $this->fModel->where($where)->field('fileID,path')->select();
                $this->updateTaskCnt(count($batch));
                if ($list) {
                    foreach ($list as $v) {
                        $result[$v['path']] = $v['fileID'];
                        // 缓存找到的文件路径（可安全清理）
                        $this->pathCache[$v['path']] = $v['fileID'];
                    }
                }

                // 释放内存
                unset($list, $batch);
                if ($i % 20000 == 0) {
                    gc_collect_cycles();
                }
            }
        }
        return $result;
    }
    
    /**
     * 通过路径获取文件ID（批量）
     */
    private function getFileIdsByPaths($paths) {
        if (empty($paths)) return array();

        $result = array();
        $batchSize = 5000;
        for ($i = 0; $i < count($paths); $i += $batchSize) {
            $batch = array_slice($paths, $i, $batchSize);
            $where = array(
                'ioType' => $this->ioType,
                'path'   => array('in', $batch)
            );
            $list = $this->fModel->where($where)->field('fileID,path')->select();
            if (!$list) $list = array();
            $this->updateTaskCnt(count($batch));
            foreach ($list as $row) {
                $result[$row['path']] = $row['fileID'];
            }
        }
        return $result;
    }
    
    /**
     * 替换PENDING的fileID
     */
    private function replacePendingFileIDs(&$sourceInsert, $ioFileInsert, $pathToNewId) {
        foreach ($sourceInsert as &$s) {
            if (strpos($s['fileID'], 'PENDING:') === 0) {
                $idx = intval(substr($s['fileID'], 8));
                $path = $ioFileInsert[$idx]['path'];
                $s['fileID'] = _get($pathToNewId,$path,0);
            }
        }
    }
    
    /**
     * 替换覆盖文件的PENDING fileID
     */
    private function replacePendingForceFileIDs(&$forceFileID, $ioFileInsert, $pathToNewId) {
        foreach ($forceFileID as &$fid) {
            if (is_string($fid) && strpos($fid, 'PENDING_REPLACE:') === 0) {
                $path = substr($fid, 16);
                $fid = _get($pathToNewId,$path,0);
            }
        }
    }

    /**
     * 批量更新fileID（用于覆盖模式）
     */
    private function batchUpdateFileIDs($forceFileID) {
        if (!$forceFileID) return;
        $updateData = array();
        foreach ($forceFileID as $sid => $fid) {
            $updateData[] = array(
                'sourceID' => $sid,
                'fileID'   => $fid
            );
        }
        // 分批更新
        $batchSize = 2000;
        for ($i = 0; $i < count($updateData); $i += $batchSize) {
            $batch = array_slice($updateData, $i, $batchSize);
            $this->executeBatchUpdateFileIDs($batch);
        }
    }

    /**
     * 执行批量更新
     */
    private function executeBatchUpdateFileIDs($updateData) {
        $cases = array();
        $ids = array();
        foreach ($updateData as $data) {
            $cases[] = "WHEN " . intval($data['sourceID']) . " THEN " . intval($data['fileID']);
            $ids[] = intval($data['sourceID']);
        }
        $idsStr = implode(',', $ids);
        $casesStr = implode(' ', $cases);
        $sql = "UPDATE io_source 
                SET fileID = CASE sourceID 
                    {$casesStr}
                END 
                WHERE sourceID IN ({$idsStr})";

        $timeStart = microtime(true);
        $this->sModel->execute($sql);
        $timeQuery = microtime(true) - $timeStart;

        $this->writeLog("批量更新" . count($updateData) . "条记录，耗时: " . round($timeQuery*1000, 1) . "ms");
    }

    /**
     * 更新文件引用计数
     */
    private function updateLinkCounts($linkInc, $linkDec) {
        if (!empty($linkInc)) {
            $this->executeBatchLinkCountUpdate($linkInc, '+');
        }
        if (!empty($linkDec)) {
            $this->executeBatchLinkCountUpdate($linkDec, '-');
        }
    }
    
    /**
     * 执行批量更新引用计数
     */
    private function executeBatchLinkCountUpdate($linkUpdates, $operation) {
        if (!$linkUpdates) return;

        // // MySQLi可用saveAll（包括addAll），但PDO为单条插入，为保持统一，改用原生SQL
        // $update[] = array(
        //     'fileID',$fid,
        //     // 'linkCount',array('exp', "linkCount + {$n}")
        //     // 'linkCount',array('exp', "GREATEST(linkCount - {$n}, 0)")	// <0时报错
        //     // 'linkCount',array('exp', "IF(linkCount >= {$n}, linkCount - {$n}, 0)")	// sqlite不支持
        //     'linkCount',array('exp', "CASE WHEN linkCount >= {$n} THEN linkCount - {$n} ELSE 0 END")
        // );

        $cases = array();
        $ids = array();
        foreach ($linkUpdates as $fid => $n) {
            $ids[] = intval($fid);
            if ($operation === '+') {
                $cases[] = "WHEN {$fid} THEN linkCount + {$n}";
            } else {
                // 使用CASE防止负数
                $cases[] = "WHEN {$fid} THEN CASE WHEN linkCount >= {$n} THEN linkCount - {$n} ELSE 0 END";
            }
        }
        $idsStr = implode(',', $ids);
        $casesStr = implode(' ', $cases);
        $sql = "UPDATE io_file 
                SET linkCount = CASE fileID 
                    {$casesStr}
                END 
                WHERE fileID IN ({$idsStr})";

        $start = microtime(true);
        $this->fModel->execute($sql);
        $time = microtime(true) - $start;

        $this->writeLog("批量更新引用数，共" . count($linkUpdates) . "条记录，耗时: " . round($time*1000, 1) . "ms");
    }

    /**
     * 安全缓存清理（改进的清理策略）
     */
    private function safeCacheCleanup() {
        static $processedCount = 0;
        $processedCount += $this->options['subBatchSize'];

        if ($processedCount >= $this->options['safeCacheCleanThreshold']) {
            $processedCount = 0;

            $this->writeLog("开始安全缓存清理...");

            $beforeMemory = memory_get_usage();

            // 1. 清理路径缓存
            if (count($this->pathCache) > 100000) {
                $this->writeLog("清理pathCache，从 " . count($this->pathCache) . " 条清理到 50000 条");
                $this->pathCache = array_slice($this->pathCache, -50000, null, true);
            }

            // 2. 清理重命名缓存（仅保留当前批次可能用到的）
            if (count($this->renameCache) > 1000) {
                $this->writeLog("清理renameCache，从 " . count($this->renameCache) . " 条清理到 500 条");
                $this->renameCache = array_slice($this->renameCache, -500, null, true);
            }

            // 3. 清理文件缓冲区（如果过大）
            if (count($this->fileBuffer) > $this->options['fileBatchSize'] * 2) {
                $this->writeLog("清理部分fileBuffer，减少内存占用");
                $this->flushBuffer();
            }

            // 4. 清理父目录缓存（使用LRU策略）
            $this->cleanupParentCache();

            // 5. 强制垃圾回收
            gc_collect_cycles();

            $afterMemory = memory_get_usage();
            $saved = ($beforeMemory - $afterMemory) / (1024 * 1024);

            $this->writeLog("安全缓存清理完成，释放 " . round($saved, 2) . "M 内存，当前内存: " . 
                sprintf("%.3fM", memory_get_usage()/(1024*1024)) . 
                "，峰值: " . sprintf("%.3fM", memory_get_peak_usage()/(1024*1024)));
        }
    }

    /**
     * 数据库优化配置；sqlite不支持
     */
    private function prepareDatabase() {
        $db = Model()->db();
        try {
            $res = $db->query("SELECT @@SESSION.max_allowed_packet");
            $this->dbPacketValue = intval($res[0]['@@SESSION.max_allowed_packet']);
            if ($this->dbPacketValue < 1024*1024*16) {
                $db->execute("SET GLOBAL max_allowed_packet = 67108864");  // 64MB
            }
            $db->execute("SET SESSION unique_checks = 0");
            $db->execute("SET SESSION foreign_key_checks = 0");
            $db->execute("SET autocommit = 0");
        } catch (Exception $e) {
            $this->writeLog('数据库配置优化失败，错误信息：'.$e->getMessage(), false);
        }
    }

    /**
     * 恢复数据库配置
     */
    private function restoreDatabase() {
        $db = Model()->db();
        try {
            if ($this->dbPacketValue) {
                $db->execute("SET GLOBAL max_allowed_packet = {$this->dbPacketValue}");
            }
            $db->execute("SET SESSION unique_checks = 1");
            $db->execute("SET SESSION foreign_key_checks = 1");
            $db->execute("SET autocommit = 1");
        } catch (Exception $e) {
            // $this->writeLog('数据库配置恢复失败，错误信息：'.$e->getMessage(), false);
            $this->writeLog('数据库配置恢复失败，错误信息：'.$e->getMessage()); // 已执行完毕，无需抛异常
        }
    }

    /**
     * 直接批量插入到io_source（原生SQL）
     */
    private function batchInsertSourceDirect($insertData) {
        if (empty($insertData)) return false;

        $db = $this->sModel->db();
        $values = array();
        $time = time();
        foreach ($insertData as $data) {
            $values[] = sprintf(
                "('%s', %d, %d, %d, %d, %d, '%s', '%s', %d, '%s', %d, %d, %d, %d, %d, %d)",
                $db->escapeString($data['sourceHash']),
                $data['targetType'],
                $data['targetID'],
                $data['createUser'],
                $data['modifyUser'],
                $data['isFolder'] ? 1 : 0,
                $db->escapeString($data['name']),
                $db->escapeString(_get($data, 'fileType', '')),
                $data['parentID'],
                $db->escapeString($data['parentLevel']),
                _get($data, 'fileID', 0),
                _get($data, 'isDelete', 0),
                _get($data, 'size', 0),
                _get($data, 'createTime', $time),
                _get($data, 'modifyTime', $time),
                _get($data, 'viewTime', $time)
            );
        }
        // 15000条数据最大长度达10MB
        $sql = "INSERT INTO io_source 
                (sourceHash, targetType, targetID, createUser, modifyUser, isFolder, 
                    name, fileType, parentID, parentLevel, fileID, isDelete, size, 
                    createTime, modifyTime, viewTime) 
                VALUES " . implode(',', $values);

        $timeStart = microtime(true);
        $result = $db->execute($sql);
        $timeQuery = microtime(true) - $timeStart;

        $type = $insertData[0]['isFolder'] == 1 ? '文件夹' : '文件';
        $this->writeLog("批量插入{$type}（io_source），共".count($insertData)."条记录，总耗时: " . round($timeQuery*1000, 1) . "ms");

        return $result;
    }

    /**
     * 直接批量插入到io_file（原生SQL）
     */
    private function batchInsertFileDirect($insertData) {
        if (empty($insertData)) return false;

        $db = $this->fModel->db();
        $values = array();
        $time = time();
        foreach ($insertData as $data) {
            $values[] = sprintf(
                "('%s', %d, %d, '%s', '%s', '%s', %d, %d, %d)",
                $db->escapeString($data['name']),
                $data['size'],
                $data['ioType'],
                $db->escapeString($data['path']),
                $data['hashSimple'],
                $data['hashMd5'],
                $data['linkCount'],
                $time,
                $data['modifyTime']
            );
        }
        $sql = "INSERT INTO io_file 
                (name, size, ioType, path, hashSimple, hashMd5, linkCount, createTime, modifyTime) 
                VALUES " . implode(',', $values);

        $timeStart = microtime(true);
        $result = $db->execute($sql);
        $timeQuery = microtime(true) - $timeStart;

        $this->writeLog("批量插入文件（io_file），共".count($insertData)."条记录，总耗时: " . round($timeQuery*1000, 1) . "ms");
        return $result;
    }

    /**
     * 根据parentID获取当前parentLevel
     */
    private function getParentLevel($parentID){
        if (!isset($this->levelMap[$parentID])) {
            $parentInfo = $this->sModel->where(array('sourceID'=>$parentID))
                ->field('parentLevel')->find();
            $this->levelMap[$parentID] = _get($parentInfo, 'parentLevel', '');
        }
        return $this->levelMap[$parentID];
    }

    /**
     * 生成sourceHash
     */
    private function getSourceHash() {
        $id = substr(md5(microtime(true) . uniqid('', true) . rand_string(10)), 0, 16);
        // 参考short_id
		$base64  = base64_encode(pack('H*',$id));
		$replace = array('/'=>'_','+'=>'-','='=>'');
		return strtr($base64, $replace);
    }

    /**
     * 清理内存（仅在导入结束时调用）
     */
    private function cleanup() {
        $this->writeLog("导入完成，开始清理所有缓存...");

        $beforeMemory = memory_get_usage();

        // 清理所有缓存
        $this->folderMap = array();
        $this->levelMap = array();
        $this->fileBuffer = array();
        $this->existingPaths = array();
        $this->parentChildMap = array();
        $this->parentFileCache = array();  // 仅在导入结束时清理
        $this->parentFileNames = array();  // 仅在导入结束时清理
        $this->pathCache = array();
        $this->renameCache = array();      // 仅在导入结束时清理
        $this->newFolderIDs = array();
        $this->parentCacheLRU = array();

        // 重置统计
        $this->importedCount = 0;
        $this->totalFiles = 0;
        $this->totalFolders = 0;
        $this->tmpFileCnt = 0;
        $this->errPathFiles = array();
        $this->lastMemoryCheck = 0;

        gc_collect_cycles();

        $afterMemory = memory_get_usage();
        $saved = ($beforeMemory - $afterMemory) / (1024 * 1024);

        $this->writeLog("缓存清理完成，释放 " . round($saved, 2) . "M 内存，当前内存: " . sprintf("%.3fM", memory_get_usage()/(1024*1024)));
    }

    /**
     * 写入日志
     */
    private function writeLog($msg, $code=true) {
        $memory = sprintf("%.3fM", memory_get_usage()/(1024*1024));
        $peak = sprintf("%.3fM", memory_get_peak_usage()/(1024*1024));
        $logMsg = '['.$this->taskId.'][内存:'.$memory.'/峰值:'.$peak.'] '.$msg;

        write_log($logMsg, 'storeImport');
        // if (!$code) show_json($msg, false);
        if (!$code) throw new Exception($msg);
    }
}