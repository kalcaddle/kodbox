<?php

/**
 * webdav 客户端
 */
class webdavClient {
	public function __construct($options = array()) {
		$this->options 	= $options;
		$this->header  	= array();
		
		$this->basePath = isset($options['basePath']) ? '/'.trim($options['basePath'],'/').'/' : '/';
		$this->basePath = '/'.ltrim(KodIO::clear($this->basePath),'/');
		$this->baseUrl  = rtrim($options['host'],'/').'/';
		$urlInfo = parse_url($this->baseUrl);
		$this->baseUrlPath = '/'.ltrim(KodIO::clear($urlInfo['path']),'/');
		
		$this->basicAuth= "Basic ".base64_encode($options['user'].":".$options['password']);
		$this->plugin 	= Action('webdavPlugin');
		$GLOBALS['requestFrom'] = 'webdav';
	}
	
	public function check(){
		$data = $this->propfind('/');
		if(is_array($data['data'])){
			$data['status'] = array_key_exists('response',$data['data']);
		}else{
			$data['status'] = false;
		}
		
		// 不同CURLOPT_HTTPAUTH 账号密码加解密处理; CURLAUTH_DIGEST
		$authHeader = _get($data,'header.WWW-Authenticate');$authCheck  = 'Digest';
		if(!$data['status'] && substr($authHeader,0,strlen($authCheck)) == $authCheck){
			$this->lastRequest = false;
			$this->options['authType'] = 'Digest';
			$data = $this->propfind('/');
			
			if(is_array($data['data'])){
				$data['status'] = array_key_exists('response',$data['data']);
			}else{
				$data['status'] = false;
			}
			$GLOBALS['in']['config'] = json_encode($this->options,true);
		}

		// trace_log(array($data,$this->options('/'),$this->options));
		$this->patchCheck();		
		return $data;
	}
	// 存储options返回头DAV字段;(标记支持项)
	private function patchCheck(){
		$data = $this->options('/');
		if(!$data['header']['dav']) return;
		$this->options['dav'] = $data['header']['dav'];
		$GLOBALS['in']['config'] = json_encode($this->options,true);// 修改配置;
	}

	/**
	 * Overwrite已存在处理; 
	 * F=skip跳过,已存在时不执行copy或move; 
	 * T=覆盖;已存在则继续执行; 默认为T
	 */
	public function mkdir($path){
		$this->setHeader('Overwrite','F');
		$data = $this->send('MKCOL',$this->makeUrl($path));
		return $data['status'];
	}
	public function move($from,$to,$lockToken=''){
		$this->setHeader('Destination',$this->makeUrl($to));
		$this->setHeader('Overwrite','T');
		if($lockToken){
			$this->setHeader('IF','<'.$lockToken .'>');
		}
		return $this->send('MOVE',$this->makeUrl($from));
	}
	public function copy($from,$to){
		$this->setHeader('Destination',$this->makeUrl($to));
		$this->setHeader('Overwrite','T');
		return $this->send('COPY',$this->makeUrl($from));
	}
	
	public function delete($path,$lockToken=''){
		if($lockToken){
			$this->setHeader('IF','<'.$lockToken .'>');
		}
		$data = $this->send('DELETE',$this->makeUrl($path));
		return $data['status'];
	}
	public function propfind($path,$depth='1'){
		$this->setHeader('Depth',$depth);//遍历深度
		$this->setHeader('Content-type','text/xml; charset=UTF-8');
		$body = '<D:propfind xmlns:D="DAV:"><D:allprop /></D:propfind>';
		return $this->send('PROPFIND',$this->makeUrl($path),$body);
	}
	public function options($path){
		return $this->send('OPTIONS',$this->makeUrl($path));
	}

	public function get($path,$localFile,$range=''){
		$getFileInfo = array('path'=>$localFile,'range'=>$range);
		$data = $this->send('GET',$this->makeUrl($path),false,false,$getFileInfo);
		return $data['status'];
	}
	public function put($path,$localFile,$lockToken=''){
		if($lockToken){
			$this->setHeader('IF','<'.$lockToken .'>');
		}
		$this->setHeader('Overwrite','T');
		$putFileInfo = array('name'=>get_path_this($path),'path'=>$localFile);
		return $this->send('PUT',$this->makeUrl($path),false,$putFileInfo);
	}
	
	public function uriToPath($uri){
		if(substr($uri,0,strlen($this->baseUrl)) == $this->baseUrl){
			$path = substr($uri,strlen($this->baseUrl));
		}else{
			$path = substr($uri,strlen($this->baseUrlPath));
		}
		return '/'.ltrim(rawurldecode($path),'/');
	}
	public function makeUrl($path){
		$path = KodIO::clear($path);
		$path = $this->encodeUrl($path);
		return $this->baseUrl.ltrim($path,'/');
	}
	public function encodeUrl($path){
		if(!is_string($path) || !$path) return '';
		$arr = explode('/',$path);
		for ($i=0; $i < count($arr); $i++) { 
			$arr[$i] = rawurlencode($arr[$i]);
		}
		$path = implode('/',$arr);
		return $path;
	}
	public function setHeader($key,$value=false){
		if($value === false){
			$this->header[] = $key;
		}else{
			$this->header[] = $key.': '.$value;
		}
	}
	
	// 缓存连续请求内容一致的内容;PROPFIND
	public function send($method,$requestUrl,$body=false,$putFileInfo=false,$getFileInfo=false,$timeout=3600){
		$lastRequest = $method.';'.$requestUrl;
		if($method == 'PROPFIND' && $this->lastRequest == $lastRequest){
			$this->header = array();
			return $this->lastRequestData;
		}

		$this->lastRequest = $lastRequest;
		$result = $this->_send($method,$requestUrl,$body,$putFileInfo,$getFileInfo,$timeout);
		$this->lastRequestData = $result;
		return $result;
	}
	
	private function _send($method,$requestUrl,$body=false,$putFileInfo=false,$getFileInfo=false,$timeout=3600){
		$this->cookieSet();
		if($body){$body = '<?xml version="1.0" encoding="UTF-8" ?>'.$body;}
		if(!request_url_safe($requestUrl)){$this->header = array();return false;}
		$ch = curl_init($requestUrl);
		if($this->options['authType'] == 'Digest'){
			curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC | CURLAUTH_DIGEST);
			curl_setopt($ch, CURLOPT_USERPWD, $this->options['user'].":".$this->options['password']);
		}else{
			$this->setHeader('Authorization',$this->basicAuth);
			curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
		}
		
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($ch, CURLOPT_HEADER,1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
		curl_setopt($ch, CURLINFO_HEADER_OUT, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_NOPROGRESS, false);
		curl_setopt($ch, CURLOPT_PROGRESSFUNCTION,'curl_progress');curl_progress_start($ch);
		curl_setopt($ch, CURLOPT_USERAGENT,'webdav/kodbox 1.0');
		curl_setopt($ch, CURLOPT_REFERER,get_url_link($requestUrl));
		curl_setopt($ch, CURLOPT_TIMEOUT,$timeout);
		if($getFileInfo){$this->getFile($ch,$getFileInfo);}
		if($putFileInfo){$this->putFile($ch,$putFileInfo);}
		curl_setopt($ch, CURLOPT_HTTPHEADER, $this->header);
		$response = curl_exec($ch);curl_progress_end($ch,$response);
		
		$headerSize 	= curl_getinfo($ch,CURLINFO_HEADER_SIZE);
		$responseInfo 	= curl_getinfo($ch);
		$responseBody 	= substr($response, $headerSize);
		$responseHeader = substr($response, 0, $headerSize);
		$responseHeader = parse_headers($responseHeader);

		$headerSet = $this->header;$this->header = array();
		$code = $responseInfo['http_code'];
		if($code == 0){
			$errorMessage = curl_error($ch);
			$errorMessage = $errorMessage ? "\n".$errorMessage : 'Network error!';
			return $this->parseResult(0,$errorMessage,$responseInfo,$headerSet);
		}

		curl_close($ch);
		$result = $this->parseResult($code,$responseBody,$responseHeader,$headerSet);
		$this->sendLog($result,$method,$requestUrl,$headerSet);
		$this->cookieSave($result);
		// trace_log([$requestUrl,$method,$result]);
		
		return $result;
	}
	
	private function parseResult($code,$body,$header,$headerSet){
		$status = $code >= 200 && $code <= 299;
		$result = array('code'=>$code,'status'=>$status,'header'=>$header,'data'=>$body);
		if($code == 0){$result['error'] = $body;}
		if(!$body) return $result;

		$error = $status ? '':$header['0'];
		if(strstr($header['content-type'],'/json')){
			$result['data']  = @json_decode($body,true);
			if( !$status && is_array($result['data']) && 
				array_key_exists('code',$result['data']) &&
				array_key_exists('data',$result['data']) &&
				$result['data']['code'] != true ){
				$error = _get($result['data'],'data','');
			}
		}
		if(!is_array($result['data'])){
			$result['data'] = webdavClient::xmlParse($body);
			if( !$status && is_array($result['data']) && 
				array_key_exists('exception',$result['data']) ){
				$exception = _get($result['data'],'exception','');
				$message   = _get($result['data'],'message','');
				$error = $exception ? $exception.';'.$message : $message;
			}
		}
		if(is_array($result['data'])){$result['_data'] = $body;}
		if($error){$result['error'] = $error;}
		return $result;
	}
	// 请求日志; 
	private function sendLog($result,$method,$requestUrl,$headerSet){
		// $this->plugin->clientLog(array($method,$requestUrl,$headerSet,$result));
		$message = $result['status'] ? $result['header']['0'] : $result['error'];
		$this->plugin->clientLog($method.':'.$requestUrl.';'.$message);
		if(!$result['status'] && $method != 'PROPFIND'){
			IO::errorTips('[webdav error] '.$result['error']);
		}
	}
	
	private function getFile($curl,$fileInfo){
		if(isset($fileInfo['range']) && $fileInfo['range']){
			$this->setHeader('Range',$fileInfo['range']);
		}
		$fp = fopen ($fileInfo['path'],'w+');
		curl_setopt($curl, CURLOPT_HTTPGET,1);
		curl_setopt($curl, CURLOPT_HEADER,0);//不输出头
		curl_setopt($curl, CURLOPT_FILE, $fp);
		curl_setopt($curl, CURLOPT_TIMEOUT,3600*10);
	}
	private function putFile($curl,$fileInfo){
		if(!$fileInfo['path']){
			curl_setopt($curl, CURLOPT_PUT,true);
			return;
		}
		$postData 	= array();$key = 'UPLOAD_FILE';
		$filename 	= $fileInfo['name'];
		$path 		= $fileInfo['path'];		
		$mime 		= get_file_mime(get_path_ext($filename));
		if (class_exists('\CURLFile')){
			$postData[$key] = new CURLFile(realpath($path),$mime,$filename);
		}else{
			$postData[$key] = "@".realpath($path).";type=".$mime.";filename=".$filename;
		}
		curl_setopt($curl, CURLOPT_PUT,true);
		curl_setopt($curl, CURLOPT_INFILE,@fopen($path,'r'));
		curl_setopt($curl, CURLOPT_INFILESIZE,@filesize($path));
		curl_setopt($curl, CURLOPT_POSTFIELDS,$postData);
		curl_setopt($curl, CURLOPT_TIMEOUT,3600*10);
		if(class_exists('\CURLFile')){
			curl_setopt($curl, CURLOPT_SAFE_UPLOAD, true);
		}else if(defined('CURLOPT_SAFE_UPLOAD')) {
			curl_setopt($curl, CURLOPT_SAFE_UPLOAD, false);
		}
	}
	
	// 请求前,设置上次保存的cookie;
	private function cookieSet(){
		$key = 'webdav_cookie_'.md5($this->basicAuth);
		$cookie = Cache::get($key);
		if(!$cookie || !is_array($cookie)) return;

		$cookieItem = array();
		foreach($cookie as $key => $val){
			$cookieItem[] = $key.'='.rawurlencode($val);
		}
		$setCookie = implode('; ',$cookieItem);
		if(!$cookieItem || !$setCookie) return;
		$this->setHeader('Cookie',$setCookie);
	}
	
	// 请求完成保存cookie;
	private function cookieSave($data){
		if(!is_array($data['header'])) return;
		if(!is_array($data['header']['set-cookie'])) return;

		$cookie = $data['header']['set-cookie'];
		$data   = array();
		foreach ($cookie as $item) {
			$value = substr($item,0,strpos($item,';'));
			if(!$value || !strpos($value,'=')) continue;
			$keyValue = explode('=',trim($value));
			if(count($keyValue) != 2) continue;
			if(!$keyValue[0] || !$keyValue[1]) continue;
			if($keyValue[1] == 'deleted') continue;
			$data[$keyValue[0]] = rawurldecode($keyValue[1]);
		}
		if(count($data) == 0) return;
		$key = 'webdav_cookie_'.md5($this->basicAuth);
		Cache::set($key,$data);
	}
	
	
	public static function xmlParse($xml){
		$doc = new DOMDocument();
		$doc->loadXML($xml);
		$root = $doc->documentElement;
		$result = self::domNodeToArray($root);
		$result = self::xmlParseKey($result);
		return $result;
	}
	public static function domNodeToArray($node){
		$output = array();
		switch ($node->nodeType) {
			case 4: // XML_CDATA_SECTION_NODE
			case 3: // XML_TEXT_NODE
				$output = trim($node->textContent);
				break;
			case 1: // XML_ELEMENT_NODE
				for ($i = 0, $m = $node->childNodes->length; $i < $m; $i++) {
					$child = $node->childNodes->item($i);
					$v = self::domNodeToArray($child);
					if (isset($child->tagName)) {
						$t = $child->tagName;
						if (!isset($output[$t])) {
							$output[$t] = array();
						}
						if (is_array($v) && empty($v)) {$v = '';}
						$output[$t][] = $v;
					} elseif ($v || $v === '0') {
						$output = (string) $v;
					}
				}
				if ($node->attributes->length && !is_array($output)) { // has attributes but isn't an array
					$output = array('@content' => $output); // change output into an array.
				}
				if (is_array($output)) {
					if ($node->attributes->length) {
						$a = array();
						foreach ($node->attributes as $attrName => $attrNode) {
							$a[$attrName] = (string) $attrNode->value;
						}
						$output['@attributes'] = $a;
					}
					foreach ($output as $t => $v) {
						if ($t !== '@attributes' && is_array($v) && count($v) === 1) {
							$output[$t] = $v[0];
						}
					}
				}
				break;
		}
		return $output;
	}
	private static function xmlParseKey($arr){
		$result = array();
		foreach ($arr as $key => $value) {
			if(is_string($key) && strstr($key,':')){
				$keyArr = explode(':',$key);
				$key = $keyArr[count($keyArr) - 1];
			}
			if(is_array($value)){$value = self::xmlParseKey($value);}
			$result[$key] = $value;
		}
		return $result;
	}
	
	

	public static function xmlParse2($xml){
		$parse = simplexml_load_string($xml);
		if($parse === false) return array();
		$namespace = $parse->getNamespaces(true);
		if(!$namespace){$namespace = array();}
		$namespace[''] = '';
		$result = array();
		foreach($namespace as $key=>$v){
			// $children = $parse->children($key,true); // 多个命名空间会丢失部分的情况
			$children = $parse->children($v);
			self::objectToArr($children,$result);
		}
		//$parse->children($v) 第一项多一层情况处理;
		if(isset($result['response'][0][0])){ 
			unset($result['response'][0]);
			$result['response'] = array_values($result['response']);
		}
		return $result;
	}
	private static function objectToArr($obj,&$arr){
		$arrCount = 0; // 相同key,重复出现则归并为数组;
		foreach($obj as $key => $val){
			if(count($val) == 0){$arr[$key] = (string)$val;continue;}
			if(!isset($arr[$key])){$arr[$key] = array();}
			
			if(!$arr[$key]){self::objectToArr($val,$arr[$key]);continue;}
			if(!$arrCount){$arr[$key] = array($arr[$key]);$arrCount++;}
			if(!isset($arr[$key][$arrCount])){$arr[$key][$arrCount] = array();}
			self::objectToArr($val,$arr[$key][$arrCount]);$arrCount++;
		}
	}
}