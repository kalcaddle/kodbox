<?php 
/**
 * 存储文件批量导入
 * 250w（100w文件+150w文件夹）：耗时2小时；内存峰值2GB
 */
class batchImport {

    private $sModel;
    private $fModel;

    private $impTask;

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

    // 诊断统计（只用于日志输出，不参与业务流程；整个请求内累计，cleanup() 不重置）
    private $stat = array(
        'fileSeen'           => 0,   // 扫描到的文件条目数（来自原目录列表）
        'fileBuffered'       => 0,   // 进入文件缓冲区（准备写库）的数量
        'skipEmptyRel'       => 0,   // 因相对路径为空跳过
        'skipPathLen'        => 0,   // 因 io 路径超 255 字符跳过
        'skipBadCharset'     => 0,   // 因编码非法 / 4 字节字符跳过（数据库非 utf8mb4 时）
        'skipNoParent'       => 0,   // 因父目录无法建立跳过
        'fileRows'           => 0,   // 实际写入 io_file 的行数
        'sourceRows'         => 0,   // 实际写入 io_source 的文件行数
        'folderExisted'      => 0,   // 目录已存在（复用）
        'folderCreated'      => 0,   // 目录本次新建
        'folderFailed'       => 0,   // 目录创建/父级解析失败（原为静默跳过）
        'folderFailedSample' => array(),
        'batchFail'          => 0,   // 失败批次计数
    );

    // 数据库sql长度
    private $dbPacketValue  = 0;

    /**
     * 异常明细收集（供导入结束后写“文件导入明细”）
     * 明细只保留前 $errItemsMax 条，超出的只计数（避免“整棵子树不可读”之类把内存吃光）
     */
    private $errItems    = array();   // [['type'=>..,'obj'=>..,'reason'=>..], ...]
    private $errCount    = array();   // type => 条数
    private $errDropped  = array();   // type => 只计数、未保留明细的条数
    private $errItemsMax = 50000;

    // 数据库字符集：非 utf8mb4 时 4 字节字符（emoji 等）无法入库，必须在写库前拦下
    private $dbUtf8mb4 = false;
    private $dbCharset = 'utf8';

    /**
     * 耗时归因（只用于日志）：把墙钟时间拆成“PHP 收集”和各个 SQL 块
     * 排查“导入变慢”时先看这一行，再对照 MySQL 侧 performance_schema 的语句耗时，即可判断慢在 PHP 还是 SQL
     * 1.查已存在文件/写目录 => 查库/索引问题；
     * 2.取回fileID/写io_source => 批次写入问题；
     * 3.未归类(PHP) => 文件数×路径解析的纯 PHP 成本（比如路径特别深、特别长）；
     * 4.若所有项都很小但墙钟很大 => 框架层（比如think_trace）
     */
    private $prof = array();
    private function profAdd($key, $sec) {
        if (!isset($this->prof[$key])) $this->prof[$key] = 0;
        $this->prof[$key] += $sec;
    }
    /**
     * 一行「耗时归因」汇总（只在导入结束时打一条）
     * 重要约定：**这里只能列"叶子桶"**（互不包含的步骤）。
     * 现在列表里都是互不重叠的叶子，剩下的（逐条解析父目录、缓冲/组装 SQL、收尾等）统一落到"未归类(PHP)"。
     */
    private function profLine($wall) {
        $names = array(
            'file.outPath' => '框架getPathOuter', 'file.exist' => '查已存在文件', 'file.preload' => '预加载父目录',
            'file.dup'     => '重名判定',      'file.insIo'  => '写io_file',   'file.ids'     => '取回fileID',
            'file.insSrc'  => '写io_source',   'file.link'   => '引用计数',     'file.updFid'  => '回写fileID',
            'dir.analyze'  => '目录分析',      'dir.query'   => '查已存在目录', 'dir.ins'      => '写目录',
            'dir.backfill' => '目录ID回填',
        );
        $sum = 0; $parts = array();
        foreach ($names as $k => $label) {
            $v = _get($this->prof, $k, 0);
            $sum += $v;
            if ($v >= 0.05) $parts[] = $label . ' ' . round($v, 1) . 's';
        }
        if ($wall > 0) $parts[] = '未归类(PHP) ' . round(max(0, $wall - $sum), 1) . 's';
        return '【耗时归因】' . ($parts ? implode('；', $parts) : '无') . '　合计墙钟 ' . round($wall, 1) . 's';
    }

    // 批量插入 io_source 后，该语句生成的第一个自增ID（用于回填新建文件夹的 sourceID 映射）
    private $lastSourceInsID = 0;

    public function __construct($task) {
        $this->impTask = $task;
        $this->taskId = substr($task->task['id'], 0, 6);

        // 性能关键：先关掉框架的 SQL 调试记录，再开始任何查询
        $this->disableSqlTrace();

        $repeat = Model('UserOption')->get('fileRepeat');
        if ($repeat) $this->options['duplicateMode'] = $repeat;

        // 数据库字符集（与框架 explorer::pathAllowCheck() 同一判据）：非 utf8mb4 时 4 字节字符不能入库
        $charset = '';
        if (isset($GLOBALS['config']['database']['DB_CHARSET'])) {
            $charset = $GLOBALS['config']['database']['DB_CHARSET'];
        } elseif (function_exists('think_config')) {
            $charset = think_config('DB_CHARSET');
        }
        if (!$charset) $charset = 'utf8';
        $this->dbCharset = strtolower($charset);
        $this->dbUtf8mb4 = ($this->dbCharset === 'utf8mb4');

        // 数据库优化配置
        $this->prepareDatabase();
	}
    public function __destruct() {
        // 数据库恢复配置
        $this->restoreDatabase();
    }

    /**
     * 关闭 SQL 调试记录（database.DB_SQL_LOG）
     *
     * DB_SQL_LOG 为 true 时，框架 Db::debug() 会对「每一条 SQL」调用 think_trace()，
     * 而 think_trace() 会用 get_caller_info() 把调用栈里每一帧的每个实参依次做 json_encode → json_decode → array_parse_deep(深拷贝) → 再 json_encode。
     * 导入时调用栈里挂着「1.5 万 ~ 3 万行」的文件/文件夹数组，于是每条 SQL 都要把这些数组完整序列化好几遍：
     *   实测 4 层栈各挂 3 万行数组 → 单条 SELECT 1 要 3595~6165ms；
     *   同一调用在 DB_SQL_LOG=false 时为 0.4ms。
     */
    private function disableSqlTrace() {
        if (!defined('GLOBAL_DEBUG') || !GLOBAL_DEBUG ) return;
        if (!function_exists('think_config')) return;
        if (!think_config('DB_SQL_LOG')) return;
        think_config('DB_SQL_LOG', false);
        $this->writeLog('[性能] 检测到框架 SQL 调试记录 DB_SQL_LOG 为开启，已在本请求内关闭，避免影响执行效率');
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

            // 统计信息
            $elapsed = round(microtime(true) - $this->startTime);
            $speed   = $elapsed > 0 ? round($this->importedCount / $elapsed) : 0;

            $data = "导入成功！总文件数：{$this->importedCount}，总文件夹数：{$this->totalFolders}；";
            $data .= "总耗时：{$elapsed} 秒（" . round($elapsed/60, 1) . " 分钟)，平均速度：{$speed} 文件/秒";
            $this->writeLog($data);
            $this->logStat('本批');   // 对账明细
            $this->writeLog($this->profLine(microtime(true) - $this->startTime));

        } catch (Exception $e) {
            $this->writeLog("导入失败，已处理文件数：{$this->importedCount}；当前批次大小：" . count($this->fileBuffer));
            $this->logStat('失败时');   // 失败也要留下对账数据，否则无法判断丢了多少

            // 再冲一次缓冲区，尽量保住已收集的数据；这一步本身也可能抛异常，原来没有保护 → 会直接变成致命错误，连“导入失败！错误信息”都来不及记
            try {
                $this->flushBuffer(true);
                // $this->restoreDatabase();
            } catch (Exception $e2) {
                $this->writeLog("异常中止：缓冲区收尾刷新失败（剩余 " . count($this->fileBuffer) . " 条）：" . $e2->getMessage());
            }

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
        $t = microtime(true);
        $allFolders = $this->collectAllFolderPaths($list, $pathFrom);
        $this->profAdd('dir.analyze', microtime(true) - $t);

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
            $t = microtime(true);
            $this->loadExistingFoldersCache($targetID);
            $this->profAdd('dir.query', microtime(true) - $t);

            // 3. 批量创建文件夹（使用缓存）
            $this->writeLog('正在批量创建文件夹...');
            $this->createFoldersWithFullPathCache($allFolders, $targetID);
        }

        // step1：文件夹创建完成后，释放不再需要的缓存
        $this->writeLog('文件夹创建完成，释放相关缓存...');
        $this->existingPaths = array();    // 不再需要，因为已转换为 $folderMap

        // 释放文件夹列表内存
        unset($allFolders);

        gc_collect_cycles();
        $this->writeLog('内存清理后：'.sprintf("%.1fM",memory_get_usage()/(1024*1024)));

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
            }
            $this->levelMap[$sourceID] = $item['parentLevel'] . $sourceID . ',';
        }

        // 最大内存占用项
        $this->writeLog("路径缓存构建完成，共" . count($this->existingPaths) . "个路径缓存，当前内存：".sprintf("%.1fM",memory_get_usage()/(1024*1024)));
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
                // ★ 入库前预检：编码非法 / 4 字节字符（数据库非 utf8mb4 时无法入库）→ 跳过并记明细
                if (!$this->checkPathSafe($relPath)) {
                    $this->stat['folderFailed']++;
                    continue;
                }
                // 名称含框架不支持字符：仍创建，但提示出来
                $this->checkNameWarn($relPath, get_path_this($relPath));
                // 检查是否已存在（使用缓存）
                if (isset($this->existingPaths[$relPath])) {
                    $sourceID = $this->existingPaths[$relPath];
                    $this->folderMap[$relPath] = $sourceID;
                    $this->stat['folderExisted']++;
                    continue;
                }
                // 检查是否已经创建过（在本次导入中）
                if (isset($this->folderMap[$relPath])) {
                    $this->stat['folderExisted']++;
                    continue;
                }

                $parts  = explode('/', $relPath);
                $name   = array_pop($parts);
                $parentRel = implode('/', $parts);
                $parentID = $parentRel === '' ? $targetID : _get($this->folderMap,$parentRel,null);
                if (!$parentID) {
                    $parentID = $this->findOrCreateParentWithCache($parentRel, $targetID);
                    if (!$parentID) {
                        // 原为静默跳过：目录没建成，它的文件就会无处可去，必须记录
                        $this->stat['folderFailed']++;
                        if (count($this->stat['folderFailedSample']) < 20) {
                            $this->stat['folderFailedSample'][] = $relPath;
                        }
                        $this->writeLog("目录创建失败，已跳过：{$relPath}（父级：{$parentRel}）");
                        continue;
                    }
                }
                // 批量插入
                $batchData[] = array(
                    'relPath'   => $relPath,
                    'name'      => $name,
                    'parentID'  => $parentID
                );
                if (count($batchData) >= $this->options['folderBatchSize']) {
                    $createdInBatch = $this->batchInsertFoldersWithCache($batchData);
                    $totalCreated += intval($createdInBatch);
                    $this->stat['folderCreated'] += intval($createdInBatch);
                    $batchData = array();
                    if ($totalCreated % 10000 == 0) {
                        $this->writeLog("已创建文件夹：{$totalCreated}/{$this->totalFolders}");
                    }
                }
            }
            // 处理剩余的批次数据
            if (!empty($batchData)) {
                $createdInBatch = $this->batchInsertFoldersWithCache($batchData);
                $totalCreated += intval($createdInBatch);
                $this->stat['folderCreated'] += intval($createdInBatch);
                $batchData = array();
            }
        }

        // 原来只用 “总数-创建数” 表示“跳过”，把“已存在”和“创建失败”混为一谈
        $this->writeLog("文件夹创建完成：新建 {$this->stat['folderCreated']} 个，已存在复用 {$this->stat['folderExisted']} 个，"
            . "创建失败 {$this->stat['folderFailed']} 个，本批唯一路径 " . count($allFolders) . " 个，当前内存："
            . sprintf("%.1fM", memory_get_usage()/(1024*1024)));
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
            foreach ($folders as $i => $folder) {
                $hash = $this->getSourceHash();
                // 把本次生成的 sourceHash 留在 $folders 里，回填时用它精确判定“这一行是不是我们插入的”
                $folders[$i]['sourceHash'] = $hash;
                $insertData[] = array(
                    'sourceHash'  => $hash,
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
            // 失败要留下可用于定位的上下文（批次大小、样本路径），否则只看到一句 SQL 报错
            $sample = array();
            foreach (array_slice($folders, 0, 3) as $f) { $sample[] = $f['relPath']; }
            $this->stat['batchFail']++;
            $this->writeLog('文件夹批次插入失败：本批 ' . count($folders) . ' 个，父目录样本['
                . implode(' | ', $sample) . ']，错误：' . $e->getMessage());
            $this->writeLog("批量插入文件夹失败: " . $e->getMessage(), false);
        }
    }

    /**
     * 插入后更新文件夹缓存
     *
     * 【重要】不能再按 (parentID, name) 反查数据库来建立 relPath => sourceID 映射：
     * 目录名是数字字符串时，PHP 的 == 是按“数值”比较的（"2025.1" == "2025.10"、"9.3" == "9.30"），
     * 同一父目录下存在这类“数值相等”的目录名（如 2025.1/2025.10、9.3/9.30、3.1/3.10、12.3/12.30）时，
     * 反查结果会张冠李戴，导致：
     *   1) 其中一个目录拿不到映射 → 其文件回落写入目标根目录，其子目录被重复创建；
     *   2) 另一个目录被映射成错误的 sourceID → 文件被写进同名但不同目录的目录里。
     * 多值 INSERT 生成的自增ID是连续的，可用「首个自增ID + 行数」定位这批新记录；
     * 归属判定则用每行随机的 sourceHash（见 resolveInsertedFolderIDs()），定位与判定分离，既要快也要准。
     */
    private function updateFolderCacheAfterInsert($folders) {
        if (empty($folders)) return;

        $t0 = microtime(true);
        $ids = $this->resolveInsertedFolderIDs($folders);
        $this->profAdd('dir.backfill', microtime(true) - $t0);
        if ($ids === false) {
            // 极端情况（自增ID不连续/期间有并发写入）回退到严格反查
            $this->writeLog('新建文件夹自增ID推导失败，回退为严格反查（parentID+name）');
            $ids = $this->queryInsertedFolderIDs($folders);
        }
        if (count($ids) !== count($folders)) {
            // 映射不完整时宁可中止本批次（事务回滚），也不能让层级错乱
            $this->writeLog('新建文件夹映射回填不完整（'.count($ids).'/'.count($folders).'），已中止本批次', false);
        }

        foreach ($folders as $i => $folder) {
            $sourceID = intval(_get($ids, $i, 0));
            $relPath  = $folder['relPath'];
            $parentID = $folder['parentID'];

            // 更新映射
            $this->folderMap[$relPath]      = $sourceID;
            $this->levelMap[$sourceID]      = $this->getParentLevel($parentID) . $sourceID . ',';
            // 更新缓存——似乎也可以不要
            $this->existingPaths[$relPath]  = $sourceID;
            // 记录新建文件夹ID
            $this->newFolderIDs[]           = $sourceID;
        }
    }

    /**
     * 依据“多值INSERT的首个自增ID + 行数”定位新记录，并用每行的 sourceHash 精确判定归属
     *
     * 注意：自增ID只用于**定位候选行**，判定归属靠 sourceHash（本行随机生成、唯一）。
     * 这样即使环境存在主主复制（auto_increment_increment>1）、触发器插队、显式ID插入等情况，
     * 只要区间内容与预期不符就会返回 false，交由 queryInsertedFolderIDs() 严格反查兜底，
     * 不会出现“ID 对不上却写错映射”的情况。
     *
     * @return array|false 成功返回与 $folders 顺序一致的 sourceID 数组；无法确定时返回 false
     */
    private function resolveInsertedFolderIDs($folders) {
        $count   = count($folders);
        $firstID = intval($this->lastSourceInsID);
        if ($firstID <= 0) return false;

        $lastID = $firstID + $count - 1;
        $db   = $this->sModel->db();
        $rows = $db->query("SELECT sourceID,sourceHash FROM io_source
                            WHERE sourceID BETWEEN {$firstID} AND {$lastID} ORDER BY sourceID ASC");
        if (!is_array($rows) || count($rows) !== $count) return false;

        // 预期集合：sourceHash => 该目录在 $folders 中的下标
        $expect = array();
        foreach ($folders as $i => $folder) {
            $hash = _get($folder, 'sourceHash', '');
            if (!is_string($hash) || $hash === '') return false;   // 拿不到 hash 就不做推测，直接走回退
            $expect[$hash] = $i;
        }
        if (count($expect) !== $count) return false;               // hash 重复（理论不可能）时同样回退

        $ids = array();
        foreach ($rows as $row) {
            $hash = $row['sourceHash'];
            if (!isset($expect[$hash])) return false;              // 区间内混入了不是本次插入的行
            $ids[$expect[$hash]] = intval($row['sourceID']);
        }
        return count($ids) === $count ? $ids : false;
    }

    /**
     * 回退方案：按 (parentID,name) 反查（严格字符串比较；同键取最新插入的记录）
     */
    private function queryInsertedFolderIDs($folders) {
        $conditions = array();
        foreach ($folders as $folder) {
            $conditions[] = array('parentID' => $folder['parentID'], 'name' => $folder['name']);
        }

        $batchSize = 1000;  // 注意：设置过大会非常慢
        $keyToIds  = array();
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
            $list = $this->sModel->where($where)->field('sourceID,name,parentID')->order('sourceID desc')->select();
            if (!$list) continue;
            foreach ($list as $row) {
                $keyToIds[intval($row['parentID']) . "\0" . $row['name']][] = intval($row['sourceID']);
            }
        }

        $ids = array();
        foreach ($folders as $i => $folder) {
            $key = intval($folder['parentID']) . "\0" . $folder['name'];
            if (empty($keyToIds[$key])) continue;
            // 已按 sourceID 倒序，先取最新插入的那条
            $ids[$i] = array_shift($keyToIds[$key]);
        }
        return $ids;
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

        // 缓存未命中时先查库：该目录可能已经存在（上次导入已建、缓存曾被清理等），避免重复创建
        $exist = $this->getFolderByName($parentID, $name);
        if ($exist) {
            $folderID    = intval($exist['sourceID']);
            $parentLevel = $exist['parentLevel'];
            $this->folderMap[$relPath]     = $folderID;
            $this->existingPaths[$relPath] = $folderID;
            $this->levelMap[$folderID]     = $parentLevel . $folderID . ',';
            return $folderID;
        }

        // 创建父目录
        $folderID = $this->createSingleFolderWithCache($name, $parentID, time());
        $this->folderMap[$relPath] = $folderID;
        $parentLevel = $this->getParentLevel($parentID);
        $this->levelMap[$folderID] = $parentLevel . $folderID . ',';

        // 更新缓存
        $this->existingPaths[$relPath] = $folderID;

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
     * 按 (parentID, name) 精确查询已存在的文件夹
     * 用 BINARY 做严格（区分大小写、不看数值）比较，避免 '2025.1' 匹到 '2025.10' 这类误配
     */
    private function getFolderByName($parentID, $name) {
        $db   = $this->sModel->db();
        $sql  = "SELECT sourceID,parentLevel FROM io_source
                 WHERE parentID=" . intval($parentID) . "
                   AND isFolder=1 AND isDelete=0
                   AND BINARY name='" . $db->escapeString($name) . "'
                 ORDER BY sourceID ASC LIMIT 1";
        $rows = $db->query($sql);
        return (is_array($rows) && !empty($rows)) ? $rows[0] : false;
    }

    /**
     * 解析文件所属父目录：缓存优先，未命中则先查库、再按需创建——不再像原来那样在父目录缺失时静默落到目标根目录
     * @param string $relPath       相对路径（不以 / 开头、结尾；空串表示目标根目录）
     * @param int    $rootSourceID  目标根目录的 sourceID
     */
    private function resolveParentID($relPath, $rootSourceID) {
        if ($relPath === '' || $relPath === false) return $rootSourceID;
        if (isset($this->folderMap[$relPath]))     return $this->folderMap[$relPath];
        if (isset($this->existingPaths[$relPath])) return $this->existingPaths[$relPath];
        return $this->findOrCreateParentWithCache($relPath, $rootSourceID);
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
            $this->stat['fileSeen']++;

            $tOut = microtime(true);
            $full = $this->ioDriver->getPathOuter($item['path']);
            $this->profAdd('file.outPath', microtime(true) - $tOut);
            $rel  = ltrim(substr($full, $rootLen), '/');
            if ($rel === '' || $rel === false) {
                $this->stat['skipEmptyRel']++;
                $this->addErr('name_empty', $full, '相对路径为空');
                continue;
            }

            // ★ 入库前预检：编码非法 / 4 字节字符（数据库非 utf8mb4 时无法入库）→ 跳过并记明细
            if (!$this->checkPathSafe($full)) {
                $this->stat['skipBadCharset'] = _get($this->stat, 'skipBadCharset', 0) + 1;
                continue;
            }

            // 检查是否超过255个字符（io_file.path长度）
            if (!$this->check255Path($full)) {
                // 逐条记日志会把日志刷爆，明细统一写入目标目录下的“文件导入明细”
                $this->stat['skipPathLen']++;
                $this->addErr('path_too_long', $full, 'io 路径长度 ' . mb_strlen($full) . ' 字符（上限 255）');
                continue;
            }

            $dir = dirname($rel);
            $dir = ($dir === '.' || $dir === '') ? '' : $dir;
            // 【注意】父目录缺失时不能回落到目标根目录（原逻辑会静默把文件写到 {source:x} 根下）。
            // 这里改为：缓存优先 → 查库 → 按需创建；确实建不出来才跳过并记日志。
            $parentID = $this->resolveParentID($dir, $this->folderMap['']);
            if (!$parentID) {
                if ($this->stat['skipNoParent'] < 20) {
                    $this->writeLog("忽略导入：父目录无法建立（dir={$dir}），path={$full}");
                }
                $this->stat['skipNoParent']++;
                $this->addErr('parent_missing', $full, '父目录无法建立：' . $dir);
                continue;
            }

            $name = get_path_this($full);
            // 名称里含框架不支持的字符：仍导入，但提示出来（导入后在网盘内可能无法改名/复制/移动）
            $this->checkNameWarn($full, $name);
            $ext = get_path_ext($name);
            if (mb_strlen($ext) > 10) {
                $this->addErr('type_too_long', $full, '后缀「' . $ext . '」超过 10 字符，按无后缀处理（fileType 置空）');
                $ext = '';
            }
            $fileData = array(
                'path'       => $full,
                'name'       => $name,
                'size'       => $item['size'],
                'ext'        => $ext,
                'mtime'      => _get($item, 'modifyTime', $time),
                'parentID'   => $parentID,
                'parentLevel'=> $this->getParentLevel($parentID),
            );

            // 检查内存使用情况，如果超过阈值则提前刷新缓冲区
            $this->checkMemoryUsage();

            $this->fileBuffer[] = $fileData;
            $fileCount++;
            $this->stat['fileBuffered']++;
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
            $this->writeLog("内存使用超过阈值，提前清理: " . sprintf("%.1fM", $currentMemory/(1024*1024)));
            $this->flushBuffer();
            gc_collect_cycles();
        }
    }

    // 检查路径长度
    private function check255Path($path) {
        // io_file.path 为 varchar(255)：超过 255 个字符的文件无法入库
        return (mb_strlen($path) <= 255);
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
                // 失败要留下可用于定位的上下文：整块 data 都会回滚，本批文件等于没导入
                $sample = array();
                foreach (array_slice($batch, 0, 3) as $it) {
                    $sample[] = $it['path'] . '(parentID=' . $it['parentID'] . ')';
                }
                $this->stat['batchFail']++;
                $this->writeLog('文件批次失败（共 ' . count($batch) . ' 条，已回滚），样本：' . implode(' | ', $sample) . '，错误：' . $e->getMessage());
                // 记入异常明细：一条汇总 + 最多 50 条样本路径（便于对照处理）
                $this->addErr('batch_failed', $batch[0]['path'] . ' 等 ' . count($batch) . ' 条', '该批已回滚，未写入；错误：' . $e->getMessage());
                foreach (array_slice($batch, 0, 50) as $k => $it) {
                    $this->addErr('batch_failed', $it['path'], '同批回滚样本(' . ($k + 1) . '/' . count($batch) . ')');
                }
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
        $t = microtime(true);
        $existFiles = $this->getExistFilePaths($paths); // 耗时
        $this->profAdd('file.exist', microtime(true) - $t);

        // 1.2 批量查询当前批次中所有父目录下的文件（用于查重）
        $parentIds = array_to_keyvalue($batch, '', 'parentID');
        $parentIds = array_unique($parentIds);
        $t = microtime(true);
        $this->preloadFilesForParents($parentIds);  // 耗时
        $this->profAdd('file.preload', microtime(true) - $t);

        // 2. 批量处理重名文件（关键点）
        $t = microtime(true);
        $batch = $this->batchResolveDuplicateNames($batch, $duplicateMode, $existFiles, $linkInc, $linkDec, $forceFileID);
        $this->profAdd('file.dup', microtime(true) - $t);

        // 3. 处理新文件
        foreach ($batch as $item) {
            $finalName = $item['name'];
            $pathKey = $item['path'];
            $parentID = $item['parentID'];
            $processed = _get($item, 'processed', '');

            // 跳过已处理的覆盖文件
            if ($processed === 'skip') {
                continue;
            }
            // 覆盖模式：已存在的 io_source 行由 batchUpdateFileIDs() 更新 fileID，不再插入 source 记录；
            // 但物理文件若尚未登记到 io_file，仍需补插 io_file，否则回填 fileID 会得到 0（旧逻辑的漏洞）
            if ($processed === 'replace') {
                if (!isset($existFiles[$pathKey])) {
                    $ioFileInsert[] = $this->buildIoFileInsert($item, $pathKey);
                }
                continue;
            }
            // 处理新文件
            if (isset($existFiles[$pathKey])) {
                $fileID = $existFiles[$pathKey];
                $linkInc[$fileID] = _get($linkInc,$fileID,0) + 1;
            } else {
                $ioFileInsert[] = $this->buildIoFileInsert($item, $pathKey);
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
            $t = microtime(true);
            $pathToNewId = $this->getFileIdsByPaths($newPaths);
            $this->profAdd('file.ids', microtime(true) - $t);
        }

        // 5. 回填 PENDING 的 fileID
        $this->replacePendingFileIDs($sourceInsert, $ioFileInsert, $pathToNewId);
        $this->replacePendingForceFileIDs($forceFileID, $ioFileInsert, $pathToNewId);

        // 6. 批量插入io_source记录
        if ($sourceInsert) {
            $this->batchInsertSourceDirect($sourceInsert);
            $this->stat['sourceRows'] += count($sourceInsert);   // 对账用：实际写入的文件行数
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
        $t = microtime(true);
        $this->batchUpdateFileIDs($forceFileID);
        $this->profAdd('file.updFid', microtime(true) - $t);

        // 8. 更新文件引用计数
        $t = microtime(true);
        $this->updateLinkCounts($linkInc, $linkDec);
        $this->profAdd('file.link', microtime(true) - $t);

        // $this->writeLog("文件批处理完成");
    }

    /**
     * 构造 io_file 插入数据（一条物理文件记录）
     */
    private function buildIoFileInsert($item, $path) {
        return array(
            'name'       => $item['name'],
            'size'       => $item['size'],
            'ioType'     => $this->ioType,
            'path'       => $path,
            'hashSimple' => '',
            'hashMd5'    => $this->hashMd5,
            'linkCount'  => 1,
            'modifyTime' => $item['mtime'],
        );
    }

    // 更新任务进度
    private function updateTaskCnt($cnt) {
        $cnt = floor($cnt / 3);    // 4处调用，所以每次仅更新1/4 —— 更新为3，取消preloadFilesForParents下的调用
        $this->tmpFileCnt += $cnt;
        $this->impTask->update($cnt, true);
    }

    /**
     * 批量处理重名文件
     */
    private function batchResolveDuplicateNames(&$batch, $duplicateMode, $existFiles, &$linkInc, &$linkDec, &$forceFileID) {
        $result = array();

        if ($duplicateMode === 'skip' || $duplicateMode === 'replace') {
            // 对于skip和replace模式，只需过滤或标记
            $batchKeys = array();   // "parentID\0name" => $result 下标：本批次内将插入的同名文件
            foreach ($batch as $item) {
                $parentID = $item['parentID'];
                $name = $item['name'];
                $key  = $parentID . "\0" . $name;
                $existDup = isset($this->parentFileNames[$parentID][$name]);    // 与库中已有文件重名
                $batchDup = isset($batchKeys[$key]);                            // 与本批次内前面的文件重名

                $skipped = false;
                $replaced = false;
                if ($duplicateMode === 'skip') {
                    if ($existDup || $batchDup) {
                        // 跳过
                        $item['processed'] = 'skip';
                        $skipped = true;
                    }
                } else {    // replace
                    if ($batchDup) {
                        // 同批次内同名：后出现的生效，丢弃前面那条（原来同批次内的重名会双双插入）
                        $result[$batchKeys[$key]]['processed'] = 'skip';
                    }
                    if ($existDup) {
                        // 标记为覆盖
                        $item['processed'] = 'replace';
                        $this->handleReplaceDuplicate($item, $existFiles, $linkInc, $linkDec, $forceFileID);
                        $replaced = true;
                    }
                }
                // 仅记录“将要插入”的那条的落点下标
                if (!$skipped && !$replaced) {
                    $batchKeys[$key] = count($result);
                }
                $result[] = $item;
            }
        } else if ($duplicateMode === 'rename') {
            // 重命名模式：批量生成新名称
            $renameGroups = array();
            $batchUsed = array();   // "parentID\0name" => true：本批次内已占用的名称（原来同批次内的重名会漏改名）

            // 先分组，相同父目录和基础名称的放在一起处理
            foreach ($batch as $index => $item) {
                $parentID = $item['parentID'];
                $name = $item['name'];
                $key = $parentID . "\0" . $name;
                if (isset($this->parentFileNames[$parentID][$name]) || isset($batchUsed[$key])) {
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
                $batchUsed[$key] = true;
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

        // 保留已有的缓存名称，避免覆盖丢失
        $cached = isset($this->renameCache[$cacheKey]) ? $this->renameCache[$cacheKey] : array();
        $needed = $count - count($cached);

        $names = array();
        if ($needed > 0) {
            $i = 1;
            $maxTries = 1000; // 防止无限循环
            while (count($names) < $needed && $i <= $maxTries) {
                $newName = "{$base} ({$i}){$ext}";
                if (!isset($this->parentFileNames[$parentID][$newName])) {
                    $names[] = $newName;
                }
                $i++;
            }
        }

        // 合并已有缓存和新生成的名称，缓存本次未使用的剩余名称
        $merged = array_merge($cached, $names);
        $this->renameCache[$cacheKey] = array_slice($merged, $count);
        return array_slice($merged, 0, $count);
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
            // $this->updateTaskCnt(count($batch));

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
                $s['fileID'] = $path ? _get($pathToNewId,$path,0) : 0;
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
                $fid = $path ? _get($pathToNewId,$path,0) : 0;
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
            // 全部强制转 int 后再拼接：这两个值虽然都来自库内 id/计数，但不留裸插值的口子
            $fid = intval($fid);
            $n   = intval($n);
            if ($n <= 0) continue;
            $ids[] = $fid;
            if ($operation === '+') {
                $cases[] = "WHEN {$fid} THEN linkCount + {$n}";
            } else {
                // 使用CASE防止负数
                $cases[] = "WHEN {$fid} THEN CASE WHEN linkCount >= {$n} THEN linkCount - {$n} ELSE 0 END";
            }
        }
        if (!$cases) return;
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

            // 1. 清理路径缓存（各批次文件路径一般不重复，FIFO截断安全，最坏情况仅多一次DB查询）
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
                sprintf("%.1fM", memory_get_usage()/(1024*1024)) . 
                "，峰值: " . sprintf("%.1fM", memory_get_peak_usage()/(1024*1024)));
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
        // 记录本语句生成的第一个自增ID：多值 INSERT 的自增ID连续，供回填文件夹映射使用
        $this->lastSourceInsID = intval($this->sModel->getLastInsID());
        $timeQuery = microtime(true) - $timeStart;

        $this->profAdd($insertData[0]['isFolder'] == 1 ? 'dir.ins' : 'file.insSrc', $timeQuery);
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
        $this->stat['fileRows'] += count($insertData);   // 对账用：实际写入的 io_file 行数
        $timeQuery = microtime(true) - $timeStart;
        $this->profAdd('file.insIo', $timeQuery);

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
        // 注意：$this->stat 与 $this->errItems/$this->errCount/$this->errDropped 不在此重置，留到整轮导入结束后，由 app.php 写日志与“文件导入明细”
        $this->folderMap = array();
        $this->levelMap = array();
        $this->fileBuffer = array();
        $this->existingPaths = array();
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
        $this->lastMemoryCheck = 0;

        gc_collect_cycles();

        $afterMemory = memory_get_usage();
        $saved = ($beforeMemory - $afterMemory) / (1024 * 1024);

        $this->writeLog("缓存清理完成，释放 " . round($saved, 2) . "M 内存，当前内存: " . sprintf("%.1fM", memory_get_usage()/(1024*1024)));
    }

    /**
     * 异常类型定义：type => array(中文名, 级别)
     * 级别：skip=未导入（需处理后可重导）；fail=写入失败（该部分内容未入库）；warn=已导入但需留意
     * 说明：dir_unreadable / stat_failed / symlink_dir 三类由驱动（DriverLocal）采集，由 app.php 合并进明细
     */
    public function errTypeMap() {
        return array(
            'path_too_long'  => array('io路径超255字符', 'skip'),
            'invalid_utf8'   => array('文件名编码非法(非UTF-8)', 'skip'),
            'char_4byte'     => array('含4字节字符(数据库非utf8mb4)', 'skip'),
            'parent_missing' => array('父目录无法建立', 'skip'),
            'name_empty'     => array('相对路径为空', 'skip'),
            'dir_unreadable' => array('目录不可读(整棵子树被跳过)', 'fail'),
            'stat_failed'    => array('条目无法读取(stat失败)', 'fail'),
            'batch_failed'   => array('批次写入失败(该批已回滚)', 'fail'),
            'list_failed'    => array('列表接口失败(后续条目未导入)', 'fail'),
            'type_too_long'  => array('后缀超长(已按无后缀处理)', 'warn'),
            'char_forbidden' => array('含框架不支持字符', 'warn'),
            'symlink_dir'    => array('软链目录(内容会重复导入)', 'warn'),
        );
    }

    /**
     * 记一条异常明细（超出 $errItemsMax 后只计数）
     */
    private function addErr($type, $obj, $reason = '') {
        $this->errCount[$type] = _get($this->errCount, $type, 0) + 1;
        if (count($this->errItems) < $this->errItemsMax) {
            $this->errItems[] = array('type' => $type, 'obj' => $obj, 'reason' => $reason);
        } else {
            $this->errDropped[$type] = _get($this->errDropped, $type, 0) + 1;
        }
    }

    /**
     * 入库前预检：名字/路径能不能安全写进数据库（外部数据直接落库的防线）
     * @return bool false = 应跳过该条目（已计入异常明细）
     */
    private function checkPathSafe($path) {
        // 1) 非法 UTF-8（GBK 等字节文件名，常见于从 Windows/SMB 拷入）：严格模式下 MySQL 报 1366，整批失败
        if (!mb_check_encoding($path, 'UTF-8')) {
            $this->addErr('invalid_utf8', $path, '不是合法的 UTF-8 编码（常见于 Windows/SMB 拷入的文件名）');
            return false;
        }
        // 2) 4 字节字符（emoji 等）：数据库非 utf8mb4 时无法存储，会让整批 INSERT 失败
        if (!$this->dbUtf8mb4 && preg_match('/[\x{10000}-\x{10FFFF}]/u', $path)) {
            $this->addErr('char_4byte', $path, '含 4 字节字符（emoji 等），数据库字符集为 ' . $this->dbCharset . '，无法存储');
            return false;
        }
        return true;
    }

    /**
     * 名字里含框架不支持的字符（/ \ : * ? " < > |）：仍然导入，但记入明细提示
     * 这类条目在网盘里存在却不好操作（改名/复制/移动会被框架拒绝）
     */
    private function checkNameWarn($path, $name) {
        if (preg_match('/[\\\\\/:*?"<>|]/', $name)) {
            $this->addErr('char_forbidden', $path, '文件名含框架不支持字符（/ \\ : * ? " < > |），导入后在网盘内可能无法改名/复制/移动');
        }
        return true;
    }

    // 异常明细（供 app.php 写“文件导入明细”文件）
    public function getErrItems()   { return $this->errItems; }
    public function getErrCount()   { return $this->errCount; }
    public function getErrDropped() { return $this->errDropped; }
    public function getDbCharset()  { return $this->dbCharset; }
    public function hasErr()        { return !empty($this->errCount); }

    // 异常汇总文本（日志与明细文件表头用）
    public function errSummaryText() {
        $map = $this->errTypeMap();
        $parts = array();
        foreach ($map as $type => $info) {
            $n = _get($this->errCount, $type, 0);
            if (!$n) continue;
            $parts[] = $info[0] . ' ' . $n;
        }
        return $parts ? implode('；', $parts) : '无';
    }

    // 异常合计条数
    public function errTotal() {
        $n = 0;
        foreach ($this->errCount as $c) { $n += $c; }
        return $n;
    }

    /**
     * 输出对账统计（只读日志）：用于判断“少文件 / 少字节”究竟发生在哪一步
     * 数值在整个请求内累计（cleanup() 不重置本统计）
     */
    private function logStat($tag = '') {
        $s = $this->stat;
        $this->writeLog("【对账{$tag}】扫描到文件 {$s['fileSeen']}，进入缓冲区 {$s['fileBuffered']}，"
            . "写入 io_source {$s['sourceRows']} 行 / io_file {$s['fileRows']} 行；"
            . "跳过：相对路径为空 {$s['skipEmptyRel']}、超255字符 {$s['skipPathLen']}、父目录无法建立 {$s['skipNoParent']}；"
            . "目录：新建 {$s['folderCreated']}、复用 {$s['folderExisted']}、失败 {$s['folderFailed']}；"
            . "失败批次 {$s['batchFail']}");
        if (!empty($s['folderFailedSample'])) {
            $this->writeLog('目录创建失败样本（最多20条）：' . implode(' | ', $s['folderFailedSample']));
        }
        if (!empty($this->errCount)) {
            $this->writeLog('【对账' . $tag . '】异常明细：共 ' . $this->errTotal() . ' 条 —— ' . $this->errSummaryText());
        }
    }

    // 供 app.php 做整轮汇总
    public function getStat() {
        return $this->stat;
    }

    /**
     * 写入日志
     */
    private function writeLog($msg, $code=true) {
        $memory = sprintf("%.1fM", memory_get_usage()/(1024*1024));
        $peak = sprintf("%.1fM", memory_get_peak_usage()/(1024*1024));
        $logMsg = '['.$this->taskId.'][内存:'.$memory.'/峰值:'.$peak.'] '.$msg;

        write_log($logMsg, 'storeImport');
        // if (!$code) show_json($msg, false);
        if (!$code) throw new Exception($msg);
    }
}