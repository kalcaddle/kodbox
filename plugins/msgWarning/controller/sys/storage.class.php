<?php 
/**
 * 存储相关：通知任务检查、存储列表状态更新
 */
class msgWarningSysStorage extends Controller {
	protected $pluginName;
	protected $stCacheKey;
	public function __construct() {
		parent::__construct();
		$this->pluginName = 'msgWarningPlugin';
		$this->stCacheKey = 'io.error.list.get';
    }

	public function index() {
		// return array();
	}

	/**
	 * 检查存储列表，返回不可用存储
	 * @param boolean $task 是否为计划任务调用，如果是，则不使用缓存
	 * @return void
	 */
    public function checkStoreList ($task=false) {
        $cckey = $this->stCacheKey;
        $cache = Cache::get($cckey);
        if ($cache !== false && !$task) return $cache;

        // 获取有数据的存储
        $ids = Model('File')->field('ioType')->group('ioType')->select();
        $ids = array_to_keyvalue($ids, '', 'ioType');

        // 判断存储是否可访问
        $model = Model('Storage');
        $data = array();
		$list = $model->driverListSystem();
		foreach($list as $item) {
            $id = $item['id'];
			$driver = strtolower($item['driver']);
			if (in_array($driver, array('baidu','onedrive'))) continue;	// 百度、onedrive不检查

			// 1.检查（网络）存储是否可访问
			$chks = $this->checkStoreUrl($item);
			// 2.实际检查存储是否可用
            if ($chks === true) {
                try {
                    $chks = $model->checkConfig($item,true);
                } catch (Exception $e) {$chks = false;}
            }
			if ($chks !== true) {
                unset($item['config']);
                $item['sysdata'] = in_array($id, $ids) ? 1 : 0; // 是否含有系统数据
                $data[$id] = $item;
            }
		}
		// TODO 如果去掉taskFreq（频率跟随计划任务），则此处需要调整
        Cache::set($cckey, $data); // 5分钟——不限定5分钟，由计划任务刷新（存储检查事件默认为5分钟）
        return $data;
    }

	// 检查（网络）存储是否可访问
	public function checkStoreUrl($info, $timeout = 10) {
		$url = _get($info, 'config.domain', '');	// 对象存储
		if (empty($url)) {
			$url = _get($info, 'config.server', '');// ftp
		}
		if (empty($url)) {
			$url = _get($info, 'config.host', '');	// webdav
		}
		if (empty($url)) return true;
		$res  = parse_url($url);
		$port = (empty($res["port"]) || $res["port"] == '80')?'':':'.$res["port"];
		$path = preg_replace("/\/+/","/",$res["path"]);
		$opt  = array();
		// ftp额外处理：name/pass、默认端口
		if (strtolower($info['driver']) == 'ftp') {
			if (!$port) $port = ':21';
			$opt[CURLOPT_USERPWD] = $info['config']['username'].':'.$info['config']['userpass'];
		}
		$url  = _get($res,'scheme',http_type())."://".$res["host"].$port.$path;
		return $this->url_request_check($url, $opt, $timeout);
	}
	// 检查地址是否可访问
	private function url_request_check($url, $opt=false, $timeout = 10) {
		if (!filter_var($url, FILTER_VALIDATE_URL)) {return false;}
		$ch = curl_init($url);
		$op = array(
			CURLOPT_NOBODY 			=> true,	// 不获取响应体
			CURLOPT_FOLLOWLOCATION 	=> false,	// 不跟随重定向——3xx
			CURLOPT_TIMEOUT 		=> $timeout,// 超时时间
			CURLOPT_CONNECTTIMEOUT 	=> $timeout,// 连接超时时间
			CURLOPT_RETURNTRANSFER 	=> true,	// 返回响应体
			CURLOPT_SSL_VERIFYPEER 	=> false,	// 忽略 SSL 验证
			CURLOPT_HEADER 			=> true,	// 返回响应头
			CURLOPT_USERAGENT 		=> 'URL Accessibility Checker',
		);
		if ($opt) $op = $opt + $op;
		curl_setopt_array($ch, $op);
		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		// $error = curl_error($ch);
		curl_close($ch);
		// 非 0 状态码表示服务器已响应，说明地址可访问（包括4xx、5xx）
		return ($response !== false && $httpCode > 0);
	}

	/**
	 * 存储管理（修改、删除）之后，清除缓存
	 * @param [type] $result
	 * @return void
	 */
    public function editAfter($result) {
        if (!$result || !$result['code']) return;
		$id = $this->in['id'];
		if (!$id) return;
		$cache = Cache::get($this->stCacheKey);
		if (!$cache || !isset($cache[$id])) return;
		// 删除当前存储的状态缓存
		unset($cache[$id]);
		Cache::set($this->stCacheKey, $cache);
    }

	/**
	 * 存储列表，检查状态
	 * @param [type] $list
	 * @return void
	 */
    public function listAfter($list) {
        // if (!isset($this->in['usage'])) return;  // 因为缓存的关系，并非每次都有该请求
        $cache = Cache::get($this->stCacheKey);
        if (!$cache) return;	// TODO 没有的时候也许应该重新获取——但是注意耗时问题
        foreach ($list as &$item) {
            if (isset($cache[$item['id']])) {
                $item['status'] = 0;
            }
        }
        return $list;
    }

}
