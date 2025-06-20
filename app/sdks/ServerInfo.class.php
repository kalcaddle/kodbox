<?php 

/**
 * 服务器信息
 * CPU、内存使用率
 */
class ServerInfo {
    private $sysOs;
    function __construct() {
        $phpos = strtoupper(PHP_OS);    // PHP_OS_FAMILY
        if (substr($phpos, 0, 3) === 'WIN') {
            $this->sysOs = 'win';
        } elseif (substr($phpos, 0, 6) === 'DARWIN') {
            $this->sysOs = 'mac';
        } else {
            $this->sysOs = 'linux';
        }
    }

    public function cpuUsage(){
        $action = 'cpuUsage'.ucfirst($this->sysOs);
        return $this->$action();
    }

    public function memUsage(){
        $action = 'memUsage'.ucfirst($this->sysOs);
        return $this->$action();
    }

    // 获取/proc/xx文件内容
    private static function getFileContent($file){
        static $readList = array();
        if (!isset($readList[$file])) {
            $readList[$file] = @is_readable($file);
        }
        if ($readList[$file]) {
            return @file_get_contents($file) ?: '';
        }
        return shell_exec('cat '.$file) ?: '';
    }

    /**
     * cpu使用率-linux
     * @return void
     */
    public function cpuUsageLinux(){
        $filePath = '/proc/stat';
        $stat1  = self::getFileContent($filePath);
        if (!$stat1) return 0;
        $info1  = $this->parseCpuInfo($stat1);
        sleep(1);
        $stat2  = self::getFileContent($filePath);
        $info2  = $this->parseCpuInfo($stat2);

        // 确保有足够数据（至少4个字段：user, nice, system, idle）
        if (count($info1) < 4 || count($info2) < 4) return 0;

        $dif = array(
            'user' => $info2[0] - $info1[0],
            'nice' => $info2[1] - $info1[1],
            'sys'  => $info2[2] - $info1[2],
            'idle' => $info2[3] - $info1[3],
        );
        $total  = array_sum($dif);
        return $total > 0 ? round(($total - $dif['idle']) / $total, 3) : 0;
    }
    private function parseCpuInfo($stat) {
        $statLines = explode("\n", $stat);
        $cpuLine = $statLines[0]; // 第一行是总CPU信息
        return preg_split('/\s+/', trim(substr($cpuLine, 3))); // 去除"cpu"前缀
    }
    
    /**
     * 内存使用率-linux
     * @return void
     */
    public function memUsageLinux(){
        $data = array(
            'total' => self::getMemoryUsage('MemTotal'),
            'used'  => self::getMemoryUsage('MemRealUsage'),
        );
        return $data;
    }

    /**
     * cpu使用率-win
     * @return void
     */
    public function cpuUsageWin(){
        $str = shell_exec('powershell "Get-CimInstance Win32_Processor | Measure-Object -Property LoadPercentage -Average | Select-Object -ExpandProperty Average"');
        $str = trim($str);
        return $str != '' ? round(floatval($str)/100, 3) : 0;
        // return trim($str) . '%';
    }
    
    /**
     * 内存使用率-win
     * @return void
     */
    public function memUsageWin(){
        $str = shell_exec('powershell "Get-CimInstance Win32_OperatingSystem | FL TotalVisibleMemorySize,FreePhysicalMemory"');
        $total = $free = 0;
        foreach (explode("\n", trim($str)) as $line) {
            if (preg_match('/^TotalVisibleMemorySize\s*:\s*(\d+)$/i', $line, $matches)) {
                $total = (float)$matches[1];
            } elseif (preg_match('/FreePhysicalMemory\s*:\s*(\d+)/i', $line, $matches)) {
                $free = (float)$matches[1];
            }
        }
        $data = array(
            'total' => $total * 1024,
            'used' => ($total - $free) * 1024,
        );
        // $data['percent'] = !$data['total'] ? '0%' : sprintf("%.1f",$data['used']/$data['total']*100).'%';
        return $data;
    }
    
    
    public static function getMemoryUsage($key){
        $key = ucfirst($key);
        static $memInfo = null;
        if (null === $memInfo) {
            $memInfoFile = '/proc/meminfo';
            $memInfo = self::getFileContent($memInfoFile);
            if (!$memInfo) {
                $memInfo = array();
                return 0;
            }
            $memInfo = str_replace(array(
                ' kB',
                '  ',
            ), '', $memInfo);
            $lines = array();
            foreach (explode("\n", $memInfo) as $line) {
                if (!$line) {
                    continue;
                }
                $line            = explode(':', $line);
                $lines[$line[0]] = (float)$line[1] * 1024;
            }
            $memInfo = $lines;
        }

        if (!isset($memInfo['MemTotal'])){
            return 0;
        }
        switch ($key) {
            case 'MemRealUsage':
                if (isset($memInfo['MemAvailable'])) {
                    return $memInfo['MemTotal'] - $memInfo['MemAvailable'];
                }
                if (isset($memInfo['MemFree'])) {
                    $buffers = _get($memInfo, 'Buffers', 0);
                    $cached  = _get($memInfo, 'Cached', 0);
                    $memFree = _get($memInfo, 'MemFree', 0);
                    return $memInfo['MemTotal'] - $memFree - $buffers - $cached;
                }
                return 0;
            case 'MemUsage':
                return isset($memInfo['MemFree']) ? $memInfo['MemTotal'] - $memInfo['MemFree'] : 0;
            case 'SwapUsage':
                if ( ! isset($memInfo['SwapTotal']) || ! isset($memInfo['SwapFree'])) {
                    return 0;
                }
                return $memInfo['SwapTotal'] - $memInfo['SwapFree'];
        }
        return isset($memInfo[$key]) ? $memInfo[$key] : 0;
    }

    /**
     * cpu使用率-mac
     * @return float
     */
    public function cpuUsageMac() {
        $cmd = "top -l 1 | grep -E '^CPU'";
        $output = shell_exec($cmd);
        if (preg_match('/(\d+\.\d+)% idle/', $output, $matches)) {
            $idlePercent = floatval($matches[1]);
            return round((100 - $idlePercent) / 100, 3);
        }
        return 0;
    }

    /**
     * 内存使用率-mac
     * @return array
     */
    public function memUsageMac() {
        $data = array(
            'total' => 0,
            'used' => 0,
        );
        // 获取总内存
        $cmd = "/usr/sbin/sysctl -n hw.memsize";
        $totalMemory = intval(shell_exec($cmd));
        if ($totalMemory <= 0) {return $data;}

        // 获取内存统计
        $output = shell_exec("vm_stat");
        if (!$output) {return $data;}

        // 获取页面大小
        preg_match('/page size of (\d+) bytes/i', $output, $matches);
        $pageSize = !empty($matches[1]) ? intval($matches[1]) : 4096; // 默认页面大小
        // 解析内存统计信息
        $stats = array();
        foreach (explode("\n", $output) as $line) {
            if (preg_match('/^(Pages\s+(?:wired|active|inactive|speculative|free|throttled|compressed|purgeable))[^:]*:\s*([\d,]+)/i', $line, $matches)) {
                $key = strtolower(preg_replace('/\s+/', '_', $matches[1]));
                $stats[$key] = (int) str_replace(',', '', $matches[2]);
            }
        }

        // 计算已使用内存
        $wired      = _get($stats, 'pages_wired', 0);
        $compressed = _get($stats, 'pages_compressed', 0);
        $active     = _get($stats, 'pages_active', 0);
        $inactive   = _get($stats, 'pages_inactive', 0);
        $purgeable  = _get($stats, 'pages_purgeable', 0);
        // 计算实际使用量
        $appMemory  = ($active + $inactive - $purgeable);
        $usedPages  = $wired + $compressed + $appMemory;
        return array(
            'total' => $totalMemory,
            'used'  => $usedPages * $pageSize
        );
    }
}