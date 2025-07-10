<?php 

/**
 * 图片处理类-Imaginary服务
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
    private $defQuality = 85;
    private $defFormat = 'jpeg';    // jpg不支持；png支持，但大小为jpeg的10+倍

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
    }

    // 检查服务状态
    public function status(){
        // $this->initData();  // 刷新配置参数
        $rest = $this->imgRequest('/health', array());
        return $rest ? true : false;
    }

    // 格式是否支持
    public function isSupport($ext){
        return isset($this->imgFormats[strtolower($ext)]);
    }

    /**
     * 图片生成缩略图
     * @param [type] $file
     * @param [type] $cacheFile
     * @param [type] $maxSize
     * @param [type] $ext
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
     * @param [type] $file
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
     * @param [type] $path
     * @param [type] $data
     * @param boolean $post
     * @return void
     */
    private function imgRequest($path, $data, $post=array(), $ext='') {
        // key必须作为url参数传递，否则报错：Invalid or missing API key
        if (!empty($this->apiKey)) {
            $data['key'] = $this->apiKey;
        }
        $method = 'POST';
        if (isset($post['url'])) {
            $method = 'GET';
            $data['url'] = $post['url'];
            $post = array();
        }
        $query = http_build_query($data);
        $query = $this->signUrl($path, $query);
        $url = $this->apiUrl . $path . '?' . $query;
        $rest = url_request($url, $method, $post);
        // pr($url,$rest,$ext,$post);exit;
        if(!$rest || !isset($rest['data'])){
			$this->log("imaginary {$path} error: [{$this->image}] request failed");
            return false;
		}
        $data = json_decode($rest['data'],true);
        if (!$rest['status'] || $rest['code'] != 200) {
            $msg = _get($data, 'message', 'unknown error');
            $this->log("imaginary {$path} error: [{$this->image}] " . $msg);
            return false;
        }
        return $data ? $data : $rest['data'];
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