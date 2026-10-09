<?php 

/**
 * 图片处理类-Imaginary 服务
 * https://github.com/h2non/imaginary
 */
class KodImaginary {
    private $imgFormats = array(
        // 'bmp'  => 'image/(bmp|x-bitmap)',    // Unsupported media type
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',   // 支持，但没必要
        'heic' => 'image/heic',
        'heif' => 'image/heif',
        'tif'  => 'image/tiff',
        'tiff' => 'image/tiff',
        'webp' => 'image/webp',
        // 'svg'   => 'image/svg+xml',
        'pdf'  => 'application/pdf',
        'ai'   => 'application/illustrator'
    );

    private $plugin;
    private $apiUrl;
    private $apiKey;
    private $urlKey;
    private $concurrency;
    private $defQuality = 85;
    private $defFormat = 'jpeg';    // jpg不支持；png支持，但大小为jpeg的10+倍
    private $image;

    public function __construct($plugin){
        $this->plugin = $plugin;
        $this->initData();
    }
    // 初始化服务参数
    private function initData(){
        $config = $this->plugin->getConfig();
        $this->apiUrl = rtrim($config['imgnryHost'], '/');
        $this->apiKey = $config['imgnryApiKey']; // -key
        $this->urlKey = $config['imgnryUrlKey']; // -url-signature-key
        $this->concurrency = intval(_get($config,'imgnryConc',10)); // -concurrency
    }

    // 检查服务状态
    public function status(){
        // $this->initData();  // 刷新配置参数
        $rest = $this->imgRequest('/health', array(), array(), '', 5, 'GET');
        return $rest ? true : false;
    }

    // 格式是否支持
    public function isSupport($ext){
        return isset($this->imgFormats[strtolower($ext)]);
    }

    /**
     * 图片生成缩略图
     * @param string $file
     * @param string $cacheFile
     * @param int    $maxSize
     * @param string $ext
     * @return void
     */
    public function createThumb($file,$cacheFile,$maxSize,$ext) {
        if (request_url_safe($file)) {
            $url = $file;
        } else {
            if (!file_exists($file)) {return false;}
        }
        if (!$this->isSupport($ext)) return false;
        $this->image = $file;

        $data = array(
            'width'         => $maxSize,
            'height'        => 0, // 关键：高度设为 0 触发自适应
            'type'          => $this->defFormat, // 可选：输出格式（默认保持原格式）
            // 'quality'       => $this->defQuality,     // 可选：质量（默认自动）
            // 'smartcrop'     => 'true', // 智能裁剪（默认false），结合/thumbnail使用
            // 'nocrop'        => 'true', // 禁用裁剪（默认false）
            // 'norotation'    => 'true', // 禁用自动旋转（默认false）
            'stripmeta'     => 'true',  // 去除元数据
            'trace'         => 'true',
            'debug'         => 'true',
        );
        $post = array('file' => '@'.$file);
        if (isset($url)) {
            $post = array('url' => $this->parsePathUrl($url)); // 网络文件，需启用 -enable-url-source
        }
        $path = '/resize';      // 精确控制比例
        // $path = '/pipeline';    // 为图像执行系列操作，形成一个处理管道
        // $path = '/thumbnail';   // 方形/固定比例；/thumbnail+smartcrop=true
        $content = $this->imgRequest($path, $data, $post, $ext);
        if (!$content) return false;
        file_put_contents($cacheFile, $content);
        return true;
    }
    
    /**
     * 获取图片信息，类getimagesize格式
     * @param string $file
     * @return void
     */
    public function getImgSize($file, $ext='') {
        if (request_url_safe($file)) {
            $url = $file;
        } else if (file_exists($file)) {
            $url = Action('explorer.share')->link($file);   // localhost/127不支持访问，暂不处理
        }
        if (!$url) {return false;}
        if ($ext && !$this->isSupport($ext)) return false;
        $this->image = $file;

        // 需要是imaginary能访问的url
        $post = array('url' => $this->parsePathUrl($url));
        $info = $this->imgRequest('/info', array(), $post, $ext);
        if (!$info) return false;
        return array(
            $info['width'],
            $info['height'],
            'channels'	=> _get($info,'channels',4),
            'bits'		=> 8,
            // 'mime'		=> 'image/'.$info['type'],
        );
    }

    /**
     * 图片处理请求
     * @param string $path
     * @param array $data
     * @param boolean $post
     * @return array
     */
    private function imgRequest($path, $data, $post=array(), $ext='', $timeout=3600, $method='POST') {
        $this->checkRateLimit(); // 并发限制

        // key必须作为url参数传递，否则报错：Invalid or missing API key
        if (!empty($this->apiKey)) {
            $data['key'] = $this->apiKey;
        }
        if (isset($post['url']) && $method == 'POST') {
            $method = 'GET';
            $data['url'] = $post['url'];
            $post = array();
        }
        $query = http_build_query($data);
        $query = $this->signUrl($path, $query);
        $url = $this->apiUrl . $path . '?' . $query;
        $rest = url_request($url, $method, $post, false, false, false, $timeout);
        // pr($url,$rest,$ext,$post);exit;
        if(!$rest || !isset($rest['data'])){
			$this->log("imaginary {$path} error: [{$this->image}] request failed");
            return false;
		}
        $data = json_decode($rest['data'],true);
        if (!$rest['status'] || $rest['code'] != 200) {
            if (!$data && $rest['data']) {
                $msg = trim($rest['data']);
            } else {
                $msg = _get($data, 'message', 'unknown error');
            }
            $msg = "[{$rest['code']}] ".$msg;
            $this->log("imaginary {$path} error: [{$this->image}] " . $msg);
            return false;
        }
        return $data ? $data : $rest['data'];
    }

    // 并发限制：跨进程滑动窗口 N req/s（对应 imaginary -concurrency）
    // 队列独立进程 + 前端同步请求（cover()）均共享此限制
    private function checkRateLimit(){
        $maxRate   = $this->concurrency > 0 ? $this->concurrency : 10;   // 每秒最大请求数
        $windowSec = 1.0;       // 滑动窗口长度
        $key       = 'imgnry:rate:slots';
        $lockKey   = $key.':lock';
        $maxRetry  = 60;        // 最多重试 60 次（约 60s），避免 cache 异常导致死循环

        for ($retry = 0; $retry < $maxRetry; $retry++) {
            $unlocked = false;
            $oldest   = 0;
            $now      = 0;
            try {
                CacheLock::lock($lockKey);
                $now   = timeFloat();
                $slots = Cache::get($key);
                $slots = is_array($slots) ? $slots : array();
                // 清理过期时间戳
                $slots = array_values(array_filter($slots, function($t) use ($now, $windowSec) {
                    return ($now - $t) < $windowSec;
                }));
                if (count($slots) < $maxRate) {
                    $slots[] = $now;
                    Cache::set($key, $slots, 2);
                    CacheLock::unlock($lockKey);
                    $unlocked = true;
                    return;     // 成功获取额度，直接返回
                }
                // 额度已满，记录最早时间戳后释放锁
                $oldest = min($slots);
                CacheLock::unlock($lockKey);
                $unlocked = true;
            } catch (Exception $e) {
                // Cache 异常时确保锁释放，然后继续下一轮重试
                if (!$unlocked) {
                    CacheLock::unlock($lockKey);
                }
                $this->log('checkRateLimit cache error: '.$e->getMessage());
                // 不抛出异常，继续下一次重试
                continue;
            }
            $sleepSec = $oldest + $windowSec - $now;
            if ($sleepSec < 0.001) $sleepSec = 0.001;
            if ($sleepSec > 1.0)   $sleepSec = 1.0;
            // 末尾加 0~maxRate 毫秒随机抖动，避免多进程同时醒来抢锁
            $jitterUs = mt_rand(0, $maxRate * 1000);
            usleep(intval($sleepSec * 1000000) + $jitterUs);
        }

        // 超时仍未通过：放行，由 imaginary 自身 503 兜底；避免阻塞PHP进程过久
        $this->log('checkRateLimit timeout, give up (maxRate='.$maxRate.')');
    }

    // 可用；等待时间不够精准，偶尔超限
    private function checkRateLimit2(){
        $maxRate = $this->concurrency > 0 ? $this->concurrency : 10;
        $key     = 'imgnry:rate:limit';
        $maxRetry = 60;
        for ($retry = 0; $retry < $maxRetry; $retry++) {
            if (Cache::limitCall($key, 1, $maxRate, false)) {
                return;   // 通过
            }
            // 未通过：随机等待 50~150ms 后重试
            usleep(mt_rand(50000, 150000));
        }
        $this->log('checkRateLimit timeout, give up (maxRate='.$maxRate.')');
    }

    /**
     * 为Imaginary URL生成签名
     */
    private function signUrl($path, $query) {
        if (empty($this->urlKey)) {return $query;}

        $toSign = $path . '?' . $query;
        $signature = base64_encode(hash_hmac('sha1', $toSign, $this->urlKey, true));
        $signature = rtrim(strtr($signature, '+/', '-_'), '=');
        return $query . '&signature=' . urlencode($signature);
    }

    // 记录日志
    public function log($msg) {
        $this->plugin->log($msg);
    }

    // 非同一服务器localhost访问自动适配；后续可增加外网访问适配
    public function parsePathUrl($url) {
        $parse1 = parse_url($url);
        $parse2 = parse_url($this->apiUrl);
        if (!$parse1 || !$parse2) return $url;
        if ($parse1['host'] != $parse2['host']) {
            if ($parse1['host'] == 'localhost') {
                $server = new ServerInfo();
                $ip = method_exists($server, 'getInternalIP') ? $server->getInternalIP() : false;   // get_server_ip();
                if ($ip) {
                    $port = $parse1['port'] && $parse1['port'] != 80 ? ':' . $parse1['port'] : '';
                    $url  = $parse1['scheme'] . '://' . $ip . $port . $parse1['path'] . (isset($parse1['query']) ? '?' . $parse1['query'] : '');
                }
            }
        }
        return $url;
    }

}