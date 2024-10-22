<?php 

/**
 * 服务器信息
 * CPU、内存使用率——mac未实现
 */
class ServerInfo {
    function __construct() {
        $this->sysOs = strtoupper(substr(PHP_OS, 0,3)) === 'WIN' ? 'win' : 'linux';
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
            return file_get_contents($file);
        }
        return shell_exec('cat '.$file);
    }

    /**
     * cpu使用率-linux
     * @return void
     */
    public function cpuUsageLinux(){
        $filePath = '/proc/stat';
        $stat1  = self::getFileContent($filePath);
        if (!$stat1) return 0;
        sleep(1);
        $stat2  = self::getFileContent($filePath);
        $info1  = explode(" ", preg_replace("!cpu +!", "", $stat1));
        $info2  = explode(" ", preg_replace("!cpu +!", "", $stat2));
        $dif    = array();
        $dif['user']    = floatval($info2[0]) - floatval($info1[0]);
        $dif['nice']    = floatval($info2[1]) - floatval($info1[1]);
        $dif['sys']     = floatval($info2[2]) - floatval($info1[2]);
        $dif['idle']    = floatval($info2[3]) - floatval($info1[3]);
        $total  = array_sum($dif);
        return round(($total - $dif['idle']) / $total, 3);
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
        $str = shell_exec('powershell "Get-CimInstance -ClassName Win32_Processor | Select-Object -ExpandProperty LoadPercentage"');
        return round(floatval($str), 1)/100;
        // return trim($str) . '%';
    }
    
    /**
     * 内存使用率-win
     * @return void
     */
    public function memUsageWin(){
        $str = shell_exec('powershell "Get-CimInstance Win32_OperatingSystem | FL TotalVisibleMemorySize, FreePhysicalMemory"');
        $list = explode("\n", trim($str));
        $list = array_filter($list);
        
        $data = array();
        foreach($list as $value) {
            $tmp = explode(':', $value);
            $data[] = floatval(trim($tmp[1]));
        }
        $data = array(
            'total' => $data[0] * 1024,
            'used' => ($data[0] - $data[1]) * 1024,
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
                $memInfo = 0;
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
                    if (isset($memInfo['Buffers'], $memInfo['Cached'])) {
                        return $memInfo['MemTotal'] - $memInfo['MemFree'] - $memInfo['Buffers'] - $memInfo['Cached'];
                    }
                    return $memInfo['MemTotal'] - $memInfo['Buffers'];
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
}