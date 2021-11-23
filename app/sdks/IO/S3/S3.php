<?php

class S3 {

	// ACL flags
	const ACL_PRIVATE = 'private';
	const ACL_PUBLIC_READ = 'public-read';
	const ACL_PUBLIC_READ_WRITE = 'public-read-write';
	const ACL_AUTHENTICATED_READ = 'authenticated-read';
	const STORAGE_CLASS_STANDARD = 'STANDARD';
	const STORAGE_CLASS_RRS = 'REDUCED_REDUNDANCY';
	const STORAGE_CLASS_STANDARD_IA = 'STANDARD_IA';
	const SSE_NONE = '';
	const SSE_AES256 = 'AES256';
	const AMZ_SEEK_TO = 'seekTo';
	const AMZ_LENGTH = 'length';
	const MAX_PART_NUM = 1000;
	const MIN_PART_SIZE = 10485760; // 最小分片10M
	const MAX_PART_SIZE = 5368709120; // 最大分片5G
	const UPLOAD_RETRY = 3; // 分片、整合失败,重试3次

	private $__accessKey = null;
	private $__secretKey = null;
	public $defDelimiter = null;
	public $endpoint = 's3.amazonaws.com';
	public $region = '';
	public $proxy = null;
	public $useSSL = false; // Connect using SSL?
	public $useSSLValidation = true; // Use SSL validation?
	public $useSSLVersion = CURL_SSLVERSION_TLSv1; // Use SSL version
	public $useExceptions = false;
	private $__timeOffset = 0;
	public $sslKey = null;
	public $sslCert = null;
	public $sslCACert = null;
	private $__signingKeyPairId = null;
	private $__signingKeyResource = false;
	public $progressFunction = null;
	public $signVer = 'v4';

	// s3 request相关
	// private $endpoint;			// AWS URI.
	private $verb;					// Verb.
	private $bucket;				// S3 bucket name.
	private $uri;					// Object URI.
	private $resource = '';			// Final object URI.
	private $parameters = array();	// Additional request parameters.
	private $amzHeaders = array();	// Amazon specific request headers.
	private $headers = array(		// HTTP request headers.
		'Host' 			=> '', 
		'Date'			=> '', 
		'Content-MD5'	=> '', 
		'Content-Type'	=> '',
	);
	public $fp = false;				// Use HTTP PUT?
	public $size = 0;				// PUT file size.
	public $data = false;			// PUT post fields.
	public $response;				// S3 request respone.

	/**
	 * Constructor - if you're not using the class statically.
	 * @param string $accessKey Access key
	 * @param string $secretKey Secret key
	 * @param bool   $useSSL    Enable SSL
	 * @param string $endpoint  Amazon URI
	 */
	public function __construct($accessKey = null, $secretKey = null, $useSSL = false, $endpoint = 's3.amazonaws.com', $region = '') {
		if ($accessKey !== null && $secretKey !== null) {
			$this->setAuth($accessKey, $secretKey);
		}
		$this->useSSL = $useSSL;
		$this->setSSL(false, false);

		$this->endpoint = $endpoint;
		$this->region = $region;
	}

	/**
	 * Set the service endpoint.
	 * @param string $host Hostname
	 */
	public function setEndpoint($host) {
		$this->endpoint = $host;
	}

	/**
	 * Set the service region.
	 * @param string $region
	 */
	public function setRegion($region) {
		$this->region = $region;
	}

	/**
	 * Get the service region.
	 * @return string $region
	 */
	public function getRegion() {
		$region = $this->region;

		// parse region from endpoint if not specific
		if (empty($region)) {
			if (preg_match("/s3[.-](?:website-|dualstack\.)?(.+)\.amazonaws\.com/i", $this->endpoint, $match) !== 0 && strtolower($match[1]) !== 'external-1') {
				$region = $match[1];
			}
		}

		return empty($region) ? 'us-east-1' : $region;
	}

	/**
	 * Set AWS access key and secret key.
	 * @param string $accessKey Access key
	 * @param string $secretKey Secret key
	 */
	public function setAuth($accessKey, $secretKey) {
		$this->__accessKey = $accessKey;
		$this->__secretKey = $secretKey;
	}

	/**
	 * Check if AWS keys have been set.
	 * @return bool
	 */
	public function hasAuth() {
		return $this->__accessKey !== null && $this->__secretKey !== null;
	}

	/**
	 * Set SSL on or off.
	 * @param bool $enabled  SSL enabled
	 * @param bool $validate SSL certificate validation
	 */
	public function setSSL($enabled, $validate = true) {
		$this->useSSL = $enabled;
		$this->useSSLValidation = $validate;
	}

	/**
	 * Set SSL client certificates (experimental).
	 * @param string $sslCert   SSL client certificate
	 * @param string $sslKey    SSL client key
	 * @param string $sslCACert SSL CA cert (only required if you are having problems with your system CA cert)
	 */
	public function setSSLAuth($sslCert = null, $sslKey = null, $sslCACert = null) {
		$this->sslCert = $sslCert;
		$this->sslKey = $sslKey;
		$this->sslCACert = $sslCACert;
	}

	/**
	 * Set the error mode to exceptions.
	 * @param bool $enabled Enable exceptions
	 */
	public function setExceptions($enabled = true) {
		$this->useExceptions = $enabled;
	}

	/**
	 * Set AWS time correction offset (use carefully).
	 * This can be used when an inaccurate system time is generating
	 * invalid request signatures.  It should only be used as a last
	 * resort when the system time cannot be changed.
	 *
	 * @param string $offset Time offset (set to zero to use AWS server time)
	 */
	public function setTimeCorrectionOffset($offset = 0) {
		if ($offset == 0) {
			$rest = $this->s3Request('HEAD');
			$rest = $rest->getResponse();
			$awstime = $rest->headers['date'];
			$systime = time();
			$offset = $systime > $awstime ? -($systime - $awstime) : ($awstime - $systime);
		}
		$this->__timeOffset = $offset;
	}

	/**
	 * Set signing key.
	 * @param string $keyPairId  AWS Key Pair ID
	 * @param string $signingKey Private Key
	 * @param bool   $isFile     Load private key from file, set to false to load string
	 *
	 * @return bool
	 */
	public function setSigningKey($keyPairId, $signingKey, $isFile = true) {
		$this->__signingKeyPairId = $keyPairId;
		if (($this->__signingKeyResource = openssl_pkey_get_private($isFile ?
			file_get_contents($signingKey) : $signingKey)) !== false) {
			return true;
		}
		$this->__triggerError('S3->setSigningKey(): Unable to open load private key: ' . $signingKey, __FILE__, __LINE__);

		return false;
	}

	/**
	 * Set Signature Version.
	 * @param string $version of signature ('v4' or 'v2')
	 */
	public function setSignatureVersion($version = 'v2') {
		$this->signVer = $version;
	}

	/**
	 * Free signing key from memory, MUST be called if you are using setSigningKey().
	 */
	public function freeSigningKey() {
		if ($this->__signingKeyResource !== false) {
			openssl_free_key($this->__signingKeyResource);
		}
	}

	/**
	 * Set progress function.
	 * @param function $func Progress function
	 */
	public function setProgressFunction($func = null) {
		$this->progressFunction = $func;
	}

	/**
	 * Internal error handler.
	 * @internal Internal error handler
	 * @param string $message Error message
	 * @param string $file    Filename
	 * @param int    $line    Line number
	 * @param int    $code    Error code
	 */
	private function __triggerError($message, $file, $line, $code = 0) {
		if ($this->useExceptions) {
			throw new S3Exception($message, $file, $line, $code);
		} else {
			trigger_error($message, E_USER_WARNING);
		}
	}

	/**
	 * Process CURL response
	 * @param type $rest
	 * @param type $function
	 * @param type $noBody
	 * @param type $params
	 * @param type $code
	 * @return boolean
	 */
	private function __execReponse($rest, $function, $noBody = 0, $params = array(), $code = 200) {
		if ($rest->error === false && $rest->code !== $code) {
			$rest->error = array('code' => $rest->code, 'message' => 'Unexpected HTTP status');
		}
		if ($rest->error !== false) {
			$param = implode(',', $params);
			$this->__triggerError(sprintf('S3->' . $function . '('.$param.'): [%s] %s', $rest->error['code'], $rest->error['message']), __FILE__, __LINE__);

			return false;
		}
		if($noBody) return true;

		if (is_string($rest->body)) {
			$body = xml2json($rest->body);
		} else {
			$body = json_decode(json_encode($rest->body), true);
		}
		return $body;
	}

	/**
	 * Get a list of buckets.
	 * @param bool $detailed Returns detailed bucket list when true
	 * @return array | false
	 */
	public function listBuckets($detailed = false) {
		$rest = $this->s3Request('GET', '', '', $this->endpoint);
		$rest = $rest->getResponse();
		if (!$body = $this->__execReponse($rest, __FUNCTION__))
			return false;

		$results = array();
		if (!isset($body['Buckets'])) {
			return $results;
		}
		if(isset($body['Buckets']['Bucket']['Name'])){
			$body['Buckets']['Bucket'] = array($body['Buckets']['Bucket']);
		}
		
		if (!$detailed) {
			foreach ($body['Buckets']['Bucket'] as $bkt) {
				$results[] = $bkt['Name'];
			}
			return $results;
		}
		// 详细信息
		if (isset($body['Owner'], $body['Owner']['ID'])) {
			$results['owner'] = array(
				'id' => $body['Owner']['ID'],
			);
			if (isset($body['Owner']['DisplayName'])) {
				$results['owner']['name'] = $body['Owner']['DisplayName'];
			}
		}
		$results['buckets'] = array();
		foreach ($body['Buckets']['Bucket'] as $bkt) {
			$results['buckets'][] = array(
				'name'	 => $bkt['Name'],
				'time'	 => strtotime($bkt['CreationDate'])
			);
		}

		return $results;
	}
	
	/**
	 * Get contents for a bucket.
	 * If maxKeys is null this method will loop through truncated result sets
	 * @param string $bucket               Bucket name
	 * @param string $prefix               Prefix
	 * @param string $marker               Marker (last file listed)
	 * @param string $maxKeys              Max keys (maximum number of keys to return)
	 * @param string $delimiter            Delimiter
	 * @param bool   $returnCommonPrefixes Set to true to return CommonPrefixes
	 *
	 * @return array | false
	 */
	public function getBucket($bucket, $prefix = null, $marker = null, $maxKeys = null, $delimiter = null, $returnCommonPrefixes = false) {
		$results = array();
		$nextMarker = null;
		do{
			$rest = $this->s3Request('GET', $bucket, '', $this->endpoint);
			if ($maxKeys == 0) {
				$maxKeys = null;
			}
			// $rest->setParameter('list-type', 2);		// 与marker冲突
			if ($prefix !== null && $prefix !== '') {
				$rest->setParameter('prefix', $prefix);
			}
			if ($marker !== null && $marker !== '') {
				$rest->setParameter('marker', $marker);
			}
			if ($maxKeys !== null && $maxKeys !== '') {
				$rest->setParameter('max-keys', $maxKeys);
			}
			if ($delimiter !== null && $delimiter !== '') {
				$rest->setParameter('delimiter', $delimiter);
			} elseif (!empty($this->defDelimiter)) {
				$rest->setParameter('delimiter', $this->defDelimiter);
			}
			if ($nextMarker !== null && $nextMarker !== '') {
				$rest->setParameter('marker', $nextMarker);
			}
			$response = $rest->getResponse();

			if (!$body = $this->__execReponse($response, __FUNCTION__))
				return false;

			if(isset($body['Contents'])){
				if(isset($body['Contents']['Key'])){
					$body['Contents'] = array($body['Contents']);
				}
				foreach ($body['Contents'] as $c){
					$results[$c['Key']] = array(
						'name'	 => $c['Key'],
						'time'	 => strtotime($c['LastModified']),
						'size'	 => (int) $c['Size'],
						'hash'	 => substr($c['ETag'], 1, -1),
					);
					$nextMarker = $c['Key'];
				}
			}

			if ($returnCommonPrefixes && isset($body['CommonPrefixes'])) {
				if(isset($body['CommonPrefixes']['Prefix'])){
					$body['CommonPrefixes'] = array($body['CommonPrefixes']);
				}
				foreach ($body['CommonPrefixes'] as $c) {
					$results[$c['Prefix']] = array('prefix' => $c['Prefix']);
				}
			}
			if (isset($body['NextMarker'])) {
				$nextMarker = $body['NextMarker'];
			}
		}while(($maxKeys == null && $body && $nextMarker != null && $body['IsTruncated'] == 'true'));

		return $results;
	}

	/**
	 * Put a bucket.
	 * @param string   $bucket   Bucket name
	 * @param constant $acl      ACL flag
	 * @param string   $location Set as "EU" to create buckets hosted in Europe
	 * @return bool
	 */
	public function putBucket($bucket, $acl = self::ACL_PRIVATE, $location = false) {
		$rest = $this->s3Request('PUT', $bucket, '', $this->endpoint);
		$rest->setAmzHeader('x-amz-acl', $acl);

		if ($location === false) {
			$location = $this->getRegion();
		}

		if ($location !== false && $location !== 'us-east-1') {
			$dom = new DOMDocument();
			$createBucketConfiguration = $dom->createElement('CreateBucketConfiguration');
			$locationConstraint = $dom->createElement('LocationConstraint', $location);
			$createBucketConfiguration->appendChild($locationConstraint);
			$dom->appendChild($createBucketConfiguration);
			$rest->data = $dom->saveXML();
			$rest->size = strlen($rest->data);
			$rest->setHeader('Content-Type', 'application/xml');
		}
		$rest = $rest->getResponse();
		
		return $this->__execReponse($rest, __FUNCTION__, 1, array($bucket, $acl, $location));
	}

	/**
	 * Delete an empty bucket.
	 * @param string $bucket Bucket name
	 * @return bool
	 */
	public function deleteBucket($bucket) {
		$rest = $this->s3Request('DELETE', $bucket, '', $this->endpoint);
		$rest = $rest->getResponse();
		$code = $rest->code == '200' ? 200 : 204;
		return $this->__execReponse($rest, __FUNCTION__, 1, array($bucket), $code);
	}

	/**
	 * get cors of bucket
	 * @param [type] $bucket
	 * @return void
	 */
	public function getBucketCors($bucket) {
		$rest = $this->s3Request('GET', $bucket, '', $this->endpoint);
		$rest->setParameter('cors', '');
		$rest = $rest->getResponse();

		if (!$body = $this->__execReponse($rest, __FUNCTION__, 0, array($bucket)))
			return false;
		
		return isset($body['CORSRule']) ? $body['CORSRule'] : false;
	}

	/**
	 * set cors of bucket
	 * @param [type] $bucket
	 * @return void
	 */
	public function setBucketCors($bucket) {
		$xmlStr = "<?xml version='1.0' encoding='UTF-8'?><CORSConfiguration>"
					. "<CORSRule>"
					. "<AllowedOrigin>*</AllowedOrigin>"
					. "<AllowedMethod>GET</AllowedMethod>"
					. "<AllowedMethod>PUT</AllowedMethod>"
					. "<AllowedMethod>POST</AllowedMethod>"
					. "<AllowedMethod>DELETE</AllowedMethod>"
					. "<AllowedMethod>HEAD</AllowedMethod>"
					. "<MaxAgeSeconds>600</MaxAgeSeconds>"
					. "<ExposeHeader>ETag</ExposeHeader>"
					. "<AllowedHeader>*</AllowedHeader>"
					. "</CORSRule>"
				. "</CORSConfiguration>";
		$xml = new SimpleXMLElement($xmlStr);
		$body = $xml->asXML();

		$rest = $this->s3Request('PUT', $bucket, '', $this->endpoint);
		$rest->setHeader('Content-Type', 'application/xml');
		$rest->setHeader('Content-MD5', $this->__base64(md5($body)));
		$rest->setHeader('Content-Length', strlen($body));
		$rest->setParameter('cors', '');
		$rest->setBody($body);
		$rest = $rest->getResponse();

		return $this->__execReponse($rest, __FUNCTION__, 1);
	}

	/**
	 * Create input info array for putObject().
	 * @param string $file   Input file
	 * @param mixed  $md5sum Use MD5 hash (supply a string if you want to use your own)
	 * @return array | false
	 */
	public function inputFile($file, $md5sum = true) {
		if (!file_exists($file) || !is_file($file) || !is_readable($file)) {
			$this->__triggerError('S3->inputFile(): Unable to open input file: ' . $file, __FILE__, __LINE__);

			return false;
		}
		clearstatcache(false, $file);

		return array(
			'file'		 => $file,
			'size'		 => filesize($file),
			'md5sum'	 => $md5sum !== false ? (is_string($md5sum) ? $md5sum : base64_encode(md5_file($file, true))) : '',
			'sha256sum'	 => hash_file('sha256', $file),
		);
	}

	/**
	 * Create input array info for putObject() with a resource.
	 * @param string $resource   Input resource to read from
	 * @param int    $bufferSize Input byte size
	 * @param string $md5sum     MD5 hash to send (optional)
	 * @return array | false
	 */
	public function inputResource(&$resource, $bufferSize = false, $md5sum = '') {
		if (!is_resource($resource) || (int) $bufferSize < 0) {
			$this->__triggerError('S3->inputResource(): Invalid resource or buffer size', __FILE__, __LINE__);

			return false;
		}

		// Try to figure out the bytesize
		if ($bufferSize === false) {
			if (fseek($resource, 0, SEEK_END) < 0 || ($bufferSize = ftell($resource)) === false) {
				$this->__triggerError('S3->inputResource(): Unable to obtain resource size', __FILE__, __LINE__);

				return false;
			}
			fseek($resource, 0);
		}

		$input = array('size' => $bufferSize, 'md5sum' => $md5sum);
		$input['fp'] = &$resource;

		return $input;
	}

	/**
	 * Put an object.
	 * @param mixed    $input                Input data
	 * @param string   $bucket               Bucket name
	 * @param string   $uri                  Object URI
	 * @param constant $acl                  ACL constant
	 * @param array    $metaHeaders          Array of x-amz-meta-* headers
	 * @param array    $requestHeaders       Array of request headers or content type as a string
	 * @param constant $storageClass         Storage class constant
	 * @param constant $serverSideEncryption Server-side encryption
	 * @return bool/array
	 */
	public function putObject($input, $bucket, $uri, $acl = self::ACL_PRIVATE, $metaHeaders = array(), $requestHeaders = array(), $storageClass = self::STORAGE_CLASS_STANDARD, $serverSideEncryption = self::SSE_NONE) {
		if ($input === false) {
			return false;
		}
		$rest = $this->s3Request('PUT', $bucket, $uri, $this->endpoint);

		if (!is_array($input)) {
			$input = array(
				'data'		 => $input,
				'size'		 => strlen($input),
				'md5sum'	 => base64_encode(md5($input, true)),
				'sha256sum'	 => hash('sha256', $input),
			);
		}

		// Data
		if (isset($input['fp'])) {
			$rest->fp = &$input['fp'];
		} elseif (isset($input['file'])) {
			$rest->fp = @fopen($input['file'], 'rb');
		} elseif (isset($input['data'])) {
			$rest->data = $input['data'];
		}

		// Content-Length (required)
		if (isset($input['size']) && $input['size'] >= 0) {
			$rest->size = $input['size'];
		} else {
			if (isset($input['file'])) {
				clearstatcache(false, $input['file']);
				$rest->size = filesize($input['file']);
			} elseif (isset($input['data'])) {
				$rest->size = strlen($input['data']);
			}
		}

		// Custom request headers (Content-Type, Content-Disposition, Content-Encoding)
		if (is_array($requestHeaders)) {
			foreach ($requestHeaders as $h => $v) {
				strpos($h, 'x-amz-') === 0 ? $rest->setAmzHeader($h, $v) : $rest->setHeader($h, $v);
			}
		} elseif (is_string($requestHeaders)) { // Support for legacy contentType parameter
			$input['type'] = $requestHeaders;
		}

		// Content-Type
		if (!isset($input['type'])) {
			if (isset($requestHeaders['Content-Type'])) {
				$input['type'] = &$requestHeaders['Content-Type'];
			} elseif (isset($input['file'])) {
				$input['type'] = $this->__getMIMEType($input['file']);
			} else {
				$input['type'] = 'application/octet-stream';
			}
		}

		if ($storageClass !== self::STORAGE_CLASS_STANDARD) { // Storage class
			$rest->setAmzHeader('x-amz-storage-class', $storageClass);
		}

		if ($serverSideEncryption !== self::SSE_NONE) { // Server-side encryption
			$rest->setAmzHeader('x-amz-server-side-encryption', $serverSideEncryption);
		}

		// We need to post with Content-Length and Content-Type, MD5 is optional
		if ($rest->size >= 0 && ($rest->fp !== false || $rest->data !== false)) {
			$rest->setHeader('Content-Type', $input['type']);
			if (isset($input['md5sum'])) {
				$rest->setHeader('Content-MD5', $input['md5sum']);
			}

			if (isset($input['sha256sum'])) {
				$rest->setAmzHeader('x-amz-content-sha256', $input['sha256sum']);
			}

			$rest->setAmzHeader('x-amz-acl', $acl);
			foreach ($metaHeaders as $h => $v) {
				$rest->setAmzHeader('x-amz-meta-' . $h, $v);
			}
			$rest = $rest->getResponse();
			if(!$this->__execReponse($rest, __FUNCTION__, 1)){
				return false;
			}
			return $rest->headers;
		}
		
		return false;
	}

	/**
	 * Put an object from a file (legacy function).
	 * @param string   $file        Input file path
	 * @param string   $bucket      Bucket name
	 * @param string   $uri         Object URI
	 * @param constant $acl         ACL constant
	 * @param array    $metaHeaders Array of x-amz-meta-* headers
	 * @param string   $contentType Content type
	 * @return bool
	 */
	public function putObjectFile($file, $bucket, $uri, $acl = self::ACL_PRIVATE, $metaHeaders = array(), $contentType = null) {
		return $this->putObject($this->inputFile($file), $bucket, $uri, $acl, $metaHeaders, $contentType);
	}

	/**
	 * Put an object from a string (legacy function).
	 * @param string   $string      Input data
	 * @param string   $bucket      Bucket name
	 * @param string   $uri         Object URI
	 * @param constant $acl         ACL constant
	 * @param array    $metaHeaders Array of x-amz-meta-* headers
	 * @param string   $contentType Content type
	 * @return bool
	 */
	public function putObjectString($string, $bucket, $uri, $acl = self::ACL_PRIVATE, $metaHeaders = array(), $contentType = null) {
		return $this->putObject($string, $bucket, $uri, $acl, $metaHeaders, $contentType);
	}

	/**
	 * Get uploadId [Multipart upload/copy].
	 * @param type $bucket
	 * @param type $uri
	 * @return bool
	 */
	public function getUploadId($bucket, $uri, $metaHeaders = array(), $requestHeaders = array()) {
		$rest = $this->s3Request('POST', $bucket, $uri, $this->endpoint);
		$rest->setParameter('uploads', '');
		foreach ($requestHeaders as $h => $v) {
			strpos($h, 'x-amz-') === 0 ? $rest->setAmzHeader($h, $v) : $rest->setHeader($h, $v);
		}
		foreach ($metaHeaders as $h => $v) {
			$rest->setAmzHeader('x-amz-meta-' . $h, $v);
		}

		$rest = $rest->getResponse();
		if (!$body = $this->__execReponse($rest, __FUNCTION__, 0, array($bucket, $uri)))
			return false;
		
		return isset($body['UploadId']) ? $body['UploadId'] : false;
	}

	/**
	 * Chunk copy.
	 * @param type $srcBucket
	 * @param type $file
	 * @param type $bucket
	 * @param type $uri
	 * @return type
	 */
	public function multiCopyObject($srcBucket, $file, $bucket, $uri, $metaHeaders = array(), $requestHeaders = array()) {
		$uploadId = $this->getUploadId($bucket, $uri, $metaHeaders, $requestHeaders);
		if (!$uploadId) return false;
		$info = $this->getObjectInfo($srcBucket, $file);
		if (!$info) return false;
		$fileSize = $info['size'];

		$uploadPosition = 0;
		$pieces = $this->__generateParts($fileSize, self::MIN_PART_SIZE);
		$partList = array();
		foreach ($pieces as $i => $piece) {
			$fromPos = $uploadPosition + (int) $piece[self::AMZ_SEEK_TO];
			$toPos = (int) $piece[self::AMZ_LENGTH] + $fromPos - 1;
			$requestHeaders = array(
				'x-amz-copy-source'			 => sprintf('/%s/%s', $srcBucket, rawurlencode($file)),
				'x-amz-copy-source-range'	 => "bytes={$fromPos}-{$toPos}",
			);
			$tryCnt = 0;
			do {
				$tryCnt++;
				$etag = $this->uploadPart($bucket, $uri, ($i + 1), $uploadId, $requestHeaders);
			} while (!$etag && $tryCnt < self::UPLOAD_RETRY);
			if (!$etag) return false;
			$partList[] = array('PartNumber' => ($i + 1), 'ETag' => $etag);
		}
		if (!empty($partList)) {
			$tryCnt = 0;
			do {
				$tryCnt++;
				$complete = $this->completeMultiUpload($bucket, $uri, $uploadId, $partList);
			} while (!$complete && $tryCnt < self::UPLOAD_RETRY);
			return $complete;
		}

		return false;
	}

	/**
	 * Chunk upload.
	 * @param type  $file
	 * @param type  $bucket
	 * @param type  $uri
	 * @param type  $metaHeaders
	 * @param array $requestHeaders
	 * @return bool|string
	 */
	public function multiUploadObject($file, $bucket, $uri, $metaHeaders = array(), $requestHeaders = array()) {
		$uploadId = $this->getUploadId($bucket, $uri, $metaHeaders, $requestHeaders);
		if (!$uploadId) return false;
		$fileSize = filesize($file);

		$pieces = $this->__generateParts($fileSize, self::MIN_PART_SIZE);

		$partList = array();
		foreach ($pieces as $i => $piece) {
			$chunkData = array(
				'file'	 => $file,
				'offset' => (int) $piece[self::AMZ_SEEK_TO],
				'length' => (int) $piece[self::AMZ_LENGTH]
			);
			$requestHeaders = array(
				'Content-Type'	 => 'application/octet-stream',
				'Content-Length' => $chunkData['length'],
			);
			$tryCnt = 0;
			do {
				$tryCnt++;
				$etag = $this->uploadPart($bucket, $uri, ($i + 1), $uploadId, $requestHeaders, $chunkData);
			} while (!$etag && $tryCnt < self::UPLOAD_RETRY);
			if (!$etag) return false;
			$partList[] = array('PartNumber' => ($i + 1), 'ETag' => $etag);
		}
		if (!empty($partList)) {
			$tryCnt = 0;
			do {
				$tryCnt++;
				$complete = $this->completeMultiUpload($bucket, $uri, $uploadId, $partList);
			} while (!$complete && $tryCnt < self::UPLOAD_RETRY);
			return $complete;
		}

		return false;
	}

	/**
	 * Complete multipart upload object.
	 * @param type $bucket
	 * @param type $uri
	 * @param type $uploadId
	 * @param type $data
	 * @param type $metaHeaders
	 * @param type $requestHeaders
	 * @return bool
	 */
	public function completeMultiUpload($bucket, $uri, $uploadId, $data, $metaHeaders = array(), $requestHeaders = array()) {
		$xmlStr = "<?xml version='1.0' encoding='UTF-8'?><CompleteMultipartUpload>";
		foreach ($data as $part) {
			$xmlStr .= '<Part>'
				. "<PartNumber>{$part['PartNumber']}</PartNumber>"
				. "<ETag>{$part['ETag']}</ETag>"
				. '</Part>';
		}
		$xmlStr .= '</CompleteMultipartUpload>';
		$xml = new SimpleXMLElement($xmlStr);
		$body = $xml->asXML();

		$rest = $this->s3Request('POST', $bucket, $uri, $this->endpoint);
		$rest->setHeader('Content-Type', 'application/octet-stream');
		$rest->setHeader('Content-MD5', $this->__base64(md5($body)));
		$rest->setHeader('Content-Length', strlen($body));
		$rest->setParameter('uploadId', $uploadId);
		$rest->setBody($body);
		foreach ($requestHeaders as $h => $v) {
			strpos($h, 'x-amz-') === 0 ? $rest->setAmzHeader($h, $v) : $rest->setHeader($h, $v);
		}
		foreach ($metaHeaders as $h => $v) {
			$rest->setAmzHeader('x-amz-meta-' . $h, $v);
		}
		$rest = $rest->getResponse();

		return $this->__execReponse($rest, __FUNCTION__, 1);
	}

	/**
	 * Upload part.
	 * @param type $bucket
	 * @param type $uri
	 * @param type $partNumber
	 * @param type $uploadId
	 * @param type $requestHeaders
	 * @param type $data
	 * @return bool|\S3Request
	 */
	public function uploadPart($bucket, $uri, $partNumber, $uploadId, $requestHeaders = array(), $data = array()) {
		$rest = $this->s3Request('PUT', $bucket, $uri, $this->endpoint);

		if (isset($data['offset'])) {
			if($this->signVer == 'v4'){
				$chunk = $this->__getFileData($data['file'], $data['offset'], ($data['offset'] + $data['length'] - 1));
				$rest->setBody($chunk);
			}else{
				if (!$rest->fp = @fopen($data['file'], 'rb')) {
					return false;
				}
				fseek($rest->fp, $data['offset']);
				$rest->size = $data['length'];
			}
		}
		$rest->setParameter('partNumber', $partNumber);
		$rest->setParameter('uploadId', $uploadId);
		foreach ($requestHeaders as $h => $v) {
			strpos($h, 'x-amz-') === 0 ? $rest->setAmzHeader($h, $v) : $rest->setHeader($h, $v);
		}
		
		$rest = $rest->getResponse();
		// upload part
		if (!empty($data)) {
			if(!$this->__execReponse($rest, __FUNCTION__, 1, array($bucket, $uri))){
				return false;
			}
			return !empty($rest->headers['hash']) ? $rest->headers['hash'] : false;
		}
		// upload part copy
		if (!$body = $this->__execReponse($rest, __FUNCTION__, 0, array($bucket, $uri)))
			return false;
		
		return !empty($body['ETag']) ? trim($body['ETag'], '"') : false;
	}

	/**
	 * Get upload part list
	 * @param type $bucket
	 * @param type $uri
	 * @param type $uploadId
	 * @return boolean
	 */
	public function listParts($bucket, $uri, $uploadId) {
		$rest = $this->s3Request('GET', $bucket, $uri, $this->endpoint);
		$rest->setParameter('uploadId', $uploadId);

		$response = $rest->getResponse();
		if (!$body = $this->__execReponse($response, __FUNCTION__, 0, array($bucket, $uri)))
			return false;
		
		$list = array();
		if (isset($body['Part']['PartNumber'])) {
			$body['Part'] = array($body['Part']);
		}
		foreach ($body['Part'] as $part) {
			$list[] = array(
				'PartNumber' => $part['PartNumber'],
				'ETag'		 => trim($part['ETag'], '"'),
			);
		}
		return $list;
	}

	/**
	 * Get upload parts.
	 * @param type $file_size
	 * @param type $partSize
	 * @return type
	 */
	private function __generateParts($file_size, $partSize = 10485760) {
		$i = 0;
		$size_count = $file_size;
		$values = array();
		if ($file_size / $partSize > self::MAX_PART_NUM) {
			$partSize = ($size_count - $size_count % (self::MAX_PART_NUM - 1)) / (self::MAX_PART_NUM - 1);
			$partSize = ceil($partSize/1024/1024)*1024*1024;	// 取整
		} else {
			$partSize = $this->__computePartSize($partSize);
		}
		while ($size_count > 0) {
			$size_count -= $partSize;
			$values[] = array(
				self::AMZ_SEEK_TO	 => ($partSize * $i),
				self::AMZ_LENGTH	 => (($size_count > 0) ? $partSize : ($size_count + $partSize)),
			);
			$i++;
		}

		return $values;
	}

	/**
	 * Get part size.
	 * @param type $partSize
	 * @return type
	 */
	private function __computePartSize($partSize) {
		$partSize = (int) $partSize;
		if ($partSize <= self::MIN_PART_SIZE) {
			$partSize = self::MIN_PART_SIZE;
		} elseif ($partSize > self::MAX_PART_SIZE) {
			$partSize = self::MAX_PART_SIZE;
		}

		return $partSize;
	}
	
	/**
	 * Get file data
	 * @param type $filename
	 * @param type $from_pos
	 * @param type $to_pos
	 * @return string
	 */
	private function __getFileData($filename, $from_pos = null, $to_pos = null) {
        if (!file_exists($filename) || false === $fh = fopen($filename, 'rb')) {
            return '';
        }
		if($from_pos === null || $to_pos === null){
			return @file_get_contents($filename);
		}
		
		$total_length = $to_pos - $from_pos + 1;
        $buffer = 8192;
		$left_length = $total_length;

		$data = '';
        fseek($fh, $from_pos);
        while (!feof($fh)) {
			$read_length = $left_length >= $buffer ? $buffer : $left_length;
            if ($read_length <= 0) {
                break;
            }
			$data .= fread($fh, $read_length);
			$left_length = $left_length - $read_length;
        }
        fclose($fh);
		
		return $data;
    }

	/**
	 * Get an object.
	 * @param string $bucket Bucket name
	 * @param string $uri    Object URI
	 * @param mixed  $saveTo Filename or resource to write to
	 * @return mixed
	 */
	public function getObject($bucket, $uri, $requestHeaders = array(), $saveTo = false) {
		$rest = $this->s3Request('GET', $bucket, $uri, $this->endpoint);
		foreach ($requestHeaders as $h => $v) {
			strpos($h, 'x-amz-') === 0 ? $rest->setAmzHeader($h, $v) : $rest->setHeader($h, $v);
		}
		if ($saveTo !== false) {
			if (is_resource($saveTo)) {
				$rest->fp = &$saveTo;
			} elseif (($rest->fp = @fopen($saveTo, 'wb')) !== false) {
				$rest->file = realpath($saveTo);
			} else {
				$rest->response->error = array('code' => 0, 'message' => 'Unable to open save file for writing: ' . $saveTo);
			}
		}
		if ($rest->response->error === false) {
			$rest->getResponse();
		}

		if ($rest->response->error === false && $rest->response->code !== 200 && $rest->response->code !== 206) {
			$rest->response->error = array('code' => $rest->response->code, 'message' => 'Unexpected HTTP status');
		}
		if ($rest->response->error !== false) {
			$this->__triggerError(sprintf("S3->getObject({$bucket}, {$uri}): [%s] %s", $rest->response->error['code'], $rest->response->error['message']), __FILE__, __LINE__);

			return false;
		}

		return isset($rest->response->body) ? $rest->response->body : '';
	}

	/**
	 * Get object information.
	 * @param string $bucket     Bucket name
	 * @param string $uri        Object URI
	 * @param bool   $returnInfo Return response information
	 * @return mixed | false
	 */
	public function getObjectInfo($bucket, $uri, $returnInfo = true) {
		$rest = $this->s3Request('HEAD', $bucket, $uri, $this->endpoint);
		$rest = $rest->getResponse();

		if ($rest->error === false && ($rest->code !== 200 && $rest->code !== 404)) {
			$rest->error = array('code' => $rest->code, 'message' => 'Unexpected HTTP status');
		}
		if ($rest->error !== false) {
			$this->__triggerError(sprintf("S3->getObjectInfo({$bucket}, {$uri}): [%s] %s", $rest->error['code'], $rest->error['message']), __FILE__, __LINE__);

			return false;
		}

		return $rest->code == 200 ? $returnInfo ? $rest->headers : true : false;
	}

	/**
	 * Copy an object.
	 *
	 * @param string   $srcBucket      Source bucket name
	 * @param string   $srcUri         Source object URI
	 * @param string   $bucket         Destination bucket name
	 * @param string   $uri            Destination object URI
	 * @param constant $acl            ACL constant
	 * @param array    $metaHeaders    Optional array of x-amz-meta-* headers
	 * @param array    $requestHeaders Optional array of request headers (content type, disposition, etc.)
	 * @param constant $storageClass   Storage class constant
	 *
	 * @return mixed | false
	 */
	public function copyObject($srcBucket, $srcUri, $bucket, $uri, $acl = self::ACL_PRIVATE, $metaHeaders = array(), $requestHeaders = array(), $storageClass = self::STORAGE_CLASS_STANDARD, $returnBody = false) {
		$rest = $this->s3Request('PUT', $bucket, $uri, $this->endpoint);
		$rest->setHeader('Content-Length', 0);
		foreach ($requestHeaders as $h => $v) {
			strpos($h, 'x-amz-') === 0 ? $rest->setAmzHeader($h, $v) : $rest->setHeader($h, $v);
		}
		foreach ($metaHeaders as $h => $v) {
			$rest->setAmzHeader('x-amz-meta-' . $h, $v);
		}
		if ($storageClass !== self::STORAGE_CLASS_STANDARD) { // Storage class
			$rest->setAmzHeader('x-amz-storage-class', $storageClass);
		}
		$rest->setAmzHeader('x-amz-acl', $acl);
		$rest->setAmzHeader('x-amz-copy-source', sprintf('/%s/%s', $srcBucket, rawurlencode($srcUri)));
		if (sizeof($requestHeaders) > 0 || sizeof($metaHeaders) > 0) {
			$rest->setAmzHeader('x-amz-metadata-directive', 'REPLACE');
		}

		$rest = $rest->getResponse(true);
		if (!$body = $this->__execReponse($rest, __FUNCTION__, 0, array($srcBucket, $srcUri, $bucket, $uri)))
			return false;
		if($returnBody) return $body;
		return isset($body['LastModified'], $body['LastModified']) ? true : false;
	}

	/**
	 * Get object or bucket Access Control Policy.
	 *
	 * @param string $bucket Bucket name
	 * @param string $uri    Object URI
	 *
	 * @return mixed | false
	 */
	public function getAccessControlPolicy($bucket, $uri = '') {
		$rest = $this->s3Request('GET', $bucket, $uri, $this->endpoint);
		$rest->setParameter('acl', null);
		$rest = $rest->getResponse();
		
		if (!$body = $this->__execReponse($rest, __FUNCTION__, 0, array($bucket, $uri)))
			return false;

		return isset($body['AccessControlList']['Grant']['Permission']) ? $body['AccessControlList']['Grant']['Permission'] : false;
	}

	/**
	 * Delete an object.
	 *
	 * @param string $bucket Bucket name
	 * @param string $uri    Object URI
	 *
	 * @return bool
	 */
	public function deleteObject($bucket, $uri) {
		$rest = $this->s3Request('DELETE', $bucket, $uri, $this->endpoint);
		$rest = $rest->getResponse();
		$code = $rest->code == '200' ? 200 : 204;
		return $this->__execReponse($rest, __FUNCTION__, 1, array(), $code);
	}

	/**
	 * Delete objects.
	 *
	 * @param type $bucket
	 * @param type $data
	 *
	 * @return bool
	 */
	public function deleteObjects($bucket, $data) {
		$xml = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><Delete/>');
		// add the objects
		foreach ($data as $object) {
			$xmlObject = $xml->addChild('Object');
			$node = $xmlObject->addChild('Key');
			$node[0] = $object;
		}
		$xml->addChild('Quiet', true);
		$body = $xml->asXML();

		$rest = $this->s3Request('POST', $bucket, '', $this->endpoint);
		$rest->setHeader('Content-Type', 'application/octet-stream');
		$rest->setHeader('Content-MD5', $this->__base64(md5($body)));
		$rest->setHeader('Content-Length', strlen($body));
		$rest->setParameter('delete', '');
		$rest->setBody($body);
		$rest = $rest->getResponse();

		return $this->__execReponse($rest, __FUNCTION__, 1);
	}

	/**
	 * Get a query string authenticated URL.
	 * https://oos-cn.ctyunapi.cn/docs/oos/S3%E5%BC%80%E5%8F%91%E8%80%85%E6%96%87%E6%A1%A3-v6.pdf
	 * @param string $bucket     Bucket name
	 * @param string $uri        Object URI
	 * @param int    $lifetime   Lifetime in seconds
	 *
	 * @return string
	 */
	public function getAuthenticatedURL($bucket, $uri, $lifetime, $subResource = array()){
		// $expires = $this->__getTime() + $lifetime;
		$expires = strtotime(date('Ymd 23:59:59')); // kodbox：签名链接有效期，改为当天有效
		$uri = str_replace(array('%2F', '%2B'), array('/', '+'), rawurlencode($uri));
		$ext = http_build_query($subResource);
		$url = sprintf(
			'%s/%sAWSAccessKeyId=%s&Expires=%u&Signature=%s', 
			$this->endpoint, 
			$uri . '?' . $ext . ($ext ? '&' : ''),
			$this->__accessKey, 
			$expires, 
			urlencode($this->__getHash("GET\n\n\n{$expires}\n/{$bucket}/{$uri}".($ext ? '?' . urldecode($ext) : '')))
		);
		return $url;
	}

	/**
	 * Get object authenticated url v4
	 * @param type $access_key
	 * @param type $secret_key
	 * @param type $bucket
	 * @param type $canonical_uri
	 * @param type $expires
	 * @param type $region
	 * @param type $extra_headers
	 * @param type $preview
	 * @return string
	 */
	public function getObjectUrl($access_key, $secret_key, $bucket, $canonical_uri, $expires = 0, $region = 'us-east-1', $extra_headers = array(), $preview = true, $paramsAdd=array()) {
		$encoded_uri = '/' . str_replace('%2F', '/', rawurlencode($canonical_uri));
		$signed_headers = array();
		foreach ($extra_headers as $key => $value) {
			$signed_headers[strtolower($key)] = $value;
		}
		if (!array_key_exists('host', $signed_headers)) {
			$res = parse_url($this->endpoint);
			$signed_headers['host'] = $res['host'];
		}
		ksort($signed_headers);
		$header_string = '';
		foreach ($signed_headers as $key => $value) {
			$header_string .= $key . ':' . trim($value) . "\n";
		}
		$signed_headers_string = implode(';', array_keys($signed_headers));
		$date_text = gmdate('Ymd');
		// $time_text = gmdate('Ymd\THis\Z');

		$time_text = gmdate('Ymd\T000000\Z');
		$expires = 3600*24;	// kodbox：签名链接有效期，改为当天有效

		$algorithm = 'AWS4-HMAC-SHA256';
		$scope = "$date_text/$region/s3/aws4_request";
		$x_amz_params = array(
			'response-content-disposition'	 => $preview ? 'inline' : 'attachment',
			'X-Amz-Algorithm'				 => $algorithm,
			'X-Amz-Credential'				 => $access_key . '/' . $scope,
			'X-Amz-Date'					 => $time_text,
			'X-Amz-SignedHeaders'			 => $signed_headers_string,
		);
		$x_amz_params = array_merge($x_amz_params,$paramsAdd);
		if ($expires > 0) {
			$x_amz_params['X-Amz-Expires'] = $expires;
		}
		ksort($x_amz_params);
		$query_string_items = array();
		foreach ($x_amz_params as $key => $value) {
			$query_string_items[] = rawurlencode($key) . '=' . rawurlencode($value);
		}
		$query_string = implode('&', $query_string_items);
		$canonical_request = "GET\n$encoded_uri\n$query_string\n$header_string\n$signed_headers_string\nUNSIGNED-PAYLOAD";
		$string_to_sign = "$algorithm\n$time_text\n$scope\n" . hash('sha256', $canonical_request, false);
		$signing_key = hash_hmac('sha256', 'aws4_request', hash_hmac('sha256', 's3', hash_hmac('sha256', $region, hash_hmac('sha256', $date_text, 'AWS4' . $secret_key, true), true), true), true);
		$signature = hash_hmac('sha256', $string_to_sign, $signing_key);

		$host = $this->endpoint;
		$url = $host . $encoded_uri . '?' . $query_string . '&X-Amz-Signature=' . $signature;

		return $url;
	}

	/**
	 * Get upload POST parameters for form uploads.
	 *
	 * @param string   $bucket          Bucket name
	 * @param string   $uriPrefix       Object URI prefix
	 * @param constant $acl             ACL constant
	 * @param int      $lifetime        Lifetime in seconds
	 * @param int      $maxFileSize     Maximum filesize in bytes (default 5MB)
	 * @param string   $successRedirect Redirect URL or 200 / 201 status code
	 * @param array    $amzHeaders      Array of x-amz-meta-* headers
	 * @param array    $headers         Array of request headers or content type as a string
	 * @param bool     $flashVars       Includes additional "Filename" variable posted by Flash
	 *
	 * @return object
	 */
	public function getHttpUploadPostParams($bucket, $uriPrefix = '', $acl = self::ACL_PRIVATE, $lifetime = 3600, $maxFileSize = 10485760, $successRedirect = '201', $amzHeaders = array(), $headers = array(), $flashVars = false) {
		// Create policy object
		$policy = new stdClass();
		$policy->expiration = gmdate('Y-m-d\TH:i:s.000\Z', ($this->__getTime() + $lifetime));
		$policy->conditions = array();
		$obj = new stdClass();
		$obj->bucket = $bucket;
		array_push($policy->conditions, $obj);
		$obj = new stdClass();
		$obj->acl = $acl;
		array_push($policy->conditions, $obj);

		$obj = new stdClass(); // 200 for non-redirect uploads
		if (is_numeric($successRedirect) && in_array((int) $successRedirect, array(200, 201))) {
			$obj->success_action_status = (string) $successRedirect;
		} else { // URL
			$obj->success_action_redirect = $successRedirect;
		}
		array_push($policy->conditions, $obj);

		if ($acl !== self::ACL_PUBLIC_READ) {
			array_push($policy->conditions, array('eq', '$acl', $acl));
		}

		array_push($policy->conditions, array('starts-with', '$key', $uriPrefix));
		if ($flashVars) {
			array_push($policy->conditions, array('starts-with', '$Filename', ''));
		}
		foreach (array_keys($headers) as $headerKey) {
			array_push($policy->conditions, array('starts-with', '$' . $headerKey, ''));
		}
		foreach ($amzHeaders as $headerKey => $headerVal) {
			$obj = new stdClass();
			$obj->{$headerKey} = (string) $headerVal;
			array_push($policy->conditions, $obj);
		}
		array_push($policy->conditions, array('content-length-range', 0, $maxFileSize));
		$policy = base64_encode(str_replace('\/', '/', json_encode($policy)));

		// Create parameters
		$params = new stdClass();
		$params->AWSAccessKeyId = $this->__accessKey;
		$params->key = $uriPrefix . '${filename}';
		$params->acl = $acl;
		$params->policy = $policy;
		unset($policy);
		$params->signature = $this->__getHash($params->policy);
		if (is_numeric($successRedirect) && in_array((int) $successRedirect, array(200, 201))) {
			$params->success_action_status = (string) $successRedirect;
		} else {
			$params->success_action_redirect = $successRedirect;
		}
		foreach ($headers as $headerKey => $headerVal) {
			$params->{$headerKey} = (string) $headerVal;
		}
		foreach ($amzHeaders as $headerKey => $headerVal) {
			$params->{$headerKey} = (string) $headerVal;
		}

		return $params;
	}

	/**
	 * Get MIME type for file.
	 *
	 * To override the putObject() Content-Type, add it to $requestHeaders
	 *
	 * To use fileinfo, ensure the MAGIC environment variable is set
	 *
	 * @internal Used to get mime types
	 *
	 * @param string &$file File path
	 *
	 * @return string
	 */
	private function __getMIMEType(&$file) {
		return get_file_mime(get_path_ext($file));

		static $exts = array(
			'jpg'	 => 'image/jpeg', 'jpeg'	 => 'image/jpeg', 'gif'	 => 'image/gif',
			'png'	 => 'image/png', 'ico'	 => 'image/x-icon', 'pdf'	 => 'application/pdf',
			'tif'	 => 'image/tiff', 'tiff'	 => 'image/tiff', 'svg'	 => 'image/svg+xml',
			'svgz'	 => 'image/svg+xml', 'swf'	 => 'application/x-shockwave-flash',
			'zip'	 => 'application/zip', 'gz'	 => 'application/x-gzip',
			'tar'	 => 'application/x-tar', 'bz'	 => 'application/x-bzip',
			'bz2'	 => 'application/x-bzip2', 'rar'	 => 'application/x-rar-compressed',
			'exe'	 => 'application/x-msdownload', 'msi'	 => 'application/x-msdownload',
			'cab'	 => 'application/vnd.ms-cab-compressed', 'txt'	 => 'text/plain',
			'asc'	 => 'text/plain', 'htm'	 => 'text/html', 'html'	 => 'text/html',
			'css'	 => 'text/css', 'js'	 => 'text/javascript',
			'xml'	 => 'text/xml', 'xsl'	 => 'application/xsl+xml',
			'ogg'	 => 'application/ogg', 'mp3'	 => 'audio/mpeg', 'wav'	 => 'audio/x-wav',
			'avi'	 => 'video/x-msvideo', 'mpg'	 => 'video/mpeg', 'mpeg'	 => 'video/mpeg',
			'mov'	 => 'video/quicktime', 'flv'	 => 'video/x-flv', 'php'	 => 'text/x-php',
		);

		$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
		if (isset($exts[$ext])) {
			return $exts[$ext];
		}

		// Use fileinfo if available
		if (extension_loaded('fileinfo') && isset($_ENV['MAGIC']) &&
			($finfo = finfo_open(FILEINFO_MIME, $_ENV['MAGIC'])) !== false) {
			if (($type = finfo_file($finfo, $file)) !== false) {
				// Remove the charset and grab the last content-type
				$type = explode(' ', str_replace('; charset=', ';charset=', $type));
				$type = array_pop($type);
				$type = explode(';', $type);
				$type = trim(array_shift($type));
			}
			finfo_close($finfo);
			if ($type !== false && strlen($type) > 0) {
				return $type;
			}
		}

		return 'application/octet-stream';
	}

	/**
	 * Get the current time.
	 *
	 * @internal Used to apply offsets to sytem time
	 *
	 * @return int
	 */
	public function __getTime() {
		return time() + $this->__timeOffset;
	}

	/**
	 * Generate the auth string: "AWS AccessKey:Signature".
	 *
	 * @internal Used by s3Request->getResponse()
	 *
	 * @param string $string String to sign
	 *
	 * @return string
	 */
	public function __getSignature($string) {
		return 'AWS ' . $this->__accessKey . ':' . $this->__getHash($string);
	}

	/**
	 * Creates a HMAC-SHA1 hash.
	 *
	 * This uses the hash extension if loaded
	 *
	 * @internal Used by __getSignature()
	 *
	 * @param string $string String to sign
	 *
	 * @return string
	 */
	private function __getHash($string) {
		return base64_encode(extension_loaded('hash') ?
			hash_hmac('sha1', $string, $this->__secretKey, true) : pack('H*', sha1(
					(str_pad($this->__secretKey, 64, chr(0x00)) ^ (str_repeat(chr(0x5c), 64))) .
					pack('H*', sha1((str_pad($this->__secretKey, 64, chr(0x00)) ^
							(str_repeat(chr(0x36), 64))) . $string)))));
	}

	private function __base64($str) {
		$ret = '';
		for ($i = 0; $i < strlen($str); $i += 2) {
			$ret .= chr(hexdec(substr($str, $i, 2)));
		}

		return base64_encode($ret);
	}

	/**
	 * Generate the headers for AWS Signature V4.
	 *
	 * @internal Used by s3Request->getResponse()
	 *
	 * @param array  $aheaders amzHeaders
	 * @param array  $headers
	 * @param string $method
	 * @param string $uri
	 * @param string $data
	 *
	 * @return array $headers
	 */
	public function __getSignatureV4($aHeaders, $headers, $method = 'GET', $uri = '', $data = '') {
		$service = 's3';
		$region = $this->getRegion();

		$algorithm = 'AWS4-HMAC-SHA256';
		$amzHeaders = array();
		$amzRequests = array();

		$amzDate = gmdate('Ymd\THis\Z');
		$amzDateStamp = gmdate('Ymd');

		// amz-date ISO8601 format ? for aws request
		$amzHeaders['x-amz-date'] = $amzDate;

		// CanonicalHeaders
		foreach ($headers as $k => $v) {
			$amzHeaders[strtolower($k)] = trim($v);
		}
		foreach ($aHeaders as $k => $v) {
			$amzHeaders[strtolower($k)] = trim($v);
		}
		$x_amz_content_sha256 = isset($amzHeaders['x-amz-content-sha256']) ? $amzHeaders['x-amz-content-sha256'] : hash('sha256', $data);
		unset($amzHeaders['x-amz-content-sha256']);
		uksort($amzHeaders, 'strcmp');

		// payload
		// $payloadHash = isset($amzHeaders['x-amz-content-sha256']) ? $amzHeaders['x-amz-content-sha256'] : hash('sha256', $data);
		$payloadHash = $x_amz_content_sha256;

		// parameters
		$parameters = array();
		if (strpos($uri, '?')) {
			list($uri, $query_str) = @explode('?', $uri);
			parse_str($query_str, $parameters);
		}

		// CanonicalRequests
		$amzRequests[] = $method;
		$uriQmPos = strpos($uri, '?');
		$amzRequests[] = ($uriQmPos === false ? $uri : substr($uri, 0, $uriQmPos));
		ksort($parameters);
		$amzRequests[] = str_replace('+','%20',http_build_query($parameters));	// 空格会被转义为+，导致签名错误
		// add header as string to requests
		foreach ($amzHeaders as $k => $v) {
			$amzRequests[] = $k . ':' . $v;
		}
		// add a blank entry so we end up with an extra line break
		$amzRequests[] = '';
		// SignedHeaders
		$amzRequests[] = implode(';', array_keys($amzHeaders));
		// payload hash
		$amzRequests[] = $payloadHash;
		// request as string
		$amzRequestStr = implode("\n", $amzRequests);

		// CredentialScope
		$credentialScope = array();
		$credentialScope[] = $amzDateStamp;
		$credentialScope[] = $region;
		$credentialScope[] = $service;
		$credentialScope[] = 'aws4_request';

		// stringToSign
		$stringToSign = array();
		$stringToSign[] = $algorithm;
		$stringToSign[] = $amzDate;
		$stringToSign[] = implode('/', $credentialScope);
		$stringToSign[] = hash('sha256', $amzRequestStr);
		// as string
		$stringToSignStr = implode("\n", $stringToSign);

		// Make Signature
		$kSecret = 'AWS4' . $this->__secretKey;
		$kDate = hash_hmac('sha256', $amzDateStamp, $kSecret, true);
		$kRegion = hash_hmac('sha256', $region, $kDate, true);
		$kService = hash_hmac('sha256', $service, $kRegion, true);
		$kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);

		$signature = hash_hmac('sha256', $stringToSignStr, $kSigning);

		$authorization = array(
			'Credential=' . $this->__accessKey . '/' . implode('/', $credentialScope),
			'SignedHeaders=' . implode(';', array_keys($amzHeaders)),
			'Signature=' . $signature,
		);

		$authorizationStr = $algorithm . ' ' . implode(',', $authorization);

		$resultHeaders = array(
			'X-AMZ-DATE'	 => $amzDate,
			'Authorization'	 => $authorizationStr,
		);
		if (!isset($aHeaders['x-amz-content-sha256'])) {
			$resultHeaders['x-amz-content-sha256'] = $payloadHash;
		}

		return $resultHeaders;
	}


	/**
	 * 重置原S3Request类的变量
	 * @return void
	 */
	public function resetVariable(){
		$this->endpoint = null;
		$this->verb = null;				// Verb.
		$this->bucket = null;			// S3 bucket name.
		$this->uri = null;				// Object URI.
		$this->resource = '';			// Final object URI.
		$this->parameters = array();	// Additional request parameters.
		$this->amzHeaders = array();	// Amazon specific request headers.
		$this->headers = array(			// HTTP request headers.
			'Host' 			=> '', 
			'Date'			=> '', 
			'Content-MD5'	=> '', 
			'Content-Type'	=> '',
		);
		$this->fp = false;				// Use HTTP PUT?
		$this->size = 0;				// PUT file size.
		$this->data = false;			// PUT post fields.
		$this->response = null;
	}

	/**
	 * 原为独立的curl请求类的构造方法，因静态属性在多次实例化时调用有问题，合并为一。2021-09-28
	 *
	 * @param string $verb     Verb
	 * @param string $bucket   Bucket name
	 * @param string $uri      Object URI
	 * @param string $endpoint AWS endpoint URI
	 *
	 * @return mixed
	 */
	public function s3Request($verb, $bucket = '', $uri = '', $endpoint = 's3.amazonaws.com') {
		$this->resetVariable();
		$this->endpoint = $endpoint;
		$this->verb = $verb;
		$this->bucket = $bucket;
		$this->uri = $uri !== '' ? '/' . str_replace('%2F', '/', rawurlencode($uri)) : '/';

		$this->headers['Host'] = get_url_domain($endpoint);
		if ($this->bucket !== '') {
			if ($this->__dnsBucketName($this->bucket)) {
				$this->resource = '/' . $this->bucket . $this->uri;
			} else {
				$this->uri = $this->uri;
				if ($this->bucket !== '') {
					$this->uri = '/' . $this->bucket . $this->uri;
				}
				$this->bucket = '';
				$this->resource = $this->uri;
			}
		} else {
			$this->resource = $this->uri;
		}

		$this->headers['Date'] = gmdate('D, d M Y H:i:s T');
		$this->response = new \STDClass();
		$this->response->error = false;
		$this->response->body = null;
		$this->response->headers = array();
		return $this;
	}

	/**
	 * Set request parameter.
	 *
	 * @param string $key   Key
	 * @param string $value Value
	 */
	public function setParameter($key, $value) {
		$this->parameters[$key] = $value;
	}

	/**
	 * Set request header.
	 *
	 * @param string $key   Key
	 * @param string $value Value
	 */
	public function setHeader($key, $value) {
		$this->headers[$key] = $value;
	}

	/**
	 * Set x-amz-meta-* header.
	 *
	 * @param string $key   Key
	 * @param string $value Value
	 */
	public function setAmzHeader($key, $value) {
		$this->amzHeaders[$key] = $value;
	}

	/**
	 * Set POST data.
	 *
	 * @param type $value
	 */
	public function setBody($value) {
		$this->data = $value;
	}

	/**
	 * Get the S3 response.
	 *
	 * @return object | false
	 */
	public function getResponse() {
		$query = '';
		if (sizeof($this->parameters) > 0) {
			$query = substr($this->uri, -1) !== '?' ? '?' : '&';
			foreach ($this->parameters as $var => $value) {
				if ($value == null || $value == '') {
					$query .= $var . '&';
				} else {
					$query .= $var . '=' . rawurlencode($value) . '&';
				}
			}
			$query = substr($query, 0, -1);
			$this->uri .= $query;

			if (array_key_exists('acl', $this->parameters) ||
				array_key_exists('delete', $this->parameters) ||
				array_key_exists('location', $this->parameters) ||
				array_key_exists('partNumber', $this->parameters) ||
				array_key_exists('torrent', $this->parameters) ||
				array_key_exists('uploadId', $this->parameters) ||
				array_key_exists('uploads', $this->parameters) ||
				array_key_exists('website', $this->parameters) ||
				array_key_exists('cors', $this->parameters) ||
				array_key_exists('logging', $this->parameters)) {
				$this->resource .= $query;
			}
		}
		$url = $this->endpoint . $this->uri;

		// Basic setup
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_USERAGENT, 'S3/php');
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);

		$proxy = $this->proxy;
		if ($proxy != null && isset($proxy['host'])) {
			curl_setopt($curl, CURLOPT_PROXY, $proxy['host']);
			curl_setopt($curl, CURLOPT_PROXYTYPE, $proxy['type']);
			if (isset($proxy['user'], $proxy['pass']) && $proxy['user'] != null && $proxy['pass'] != null) {
				curl_setopt($curl, CURLOPT_PROXYUSERPWD, sprintf('%s:%s', $proxy['user'], $proxy['pass']));
			}
		}

		// Headers
		$headers = array();
		$amz = array();
		foreach ($this->amzHeaders as $header => $value) {
			if (strlen($value) > 0) {
				$headers[] = $header . ': ' . $value;
			}
		}
		foreach ($this->headers as $header => $value) {
			if (strlen($value) > 0) {
				$headers[] = $header . ': ' . $value;
			}
		}

		// Collect AMZ headers for signature
		foreach ($this->amzHeaders as $header => $value) {
			if (strlen($value) > 0) {
				$amz[] = strtolower($header) . ':' . $value;
			}
		}

		// AMZ headers must be sorted
		if (sizeof($amz) > 0) {
			//sort($amz);
			usort($amz, array(&$this, '__sortMetaHeadersCmp'));
			$amz = "\n" . implode("\n", $amz);
		} else {
			$amz = '';
		}

		if ($this->hasAuth()) {
			// Authorization string (CloudFront stringToSign should only contain a date)
			if ($this->headers['Host'] == 'cloudfront.amazonaws.com') {
				$headers[] = 'Authorization: ' . $this->__getSignature($this->headers['Date']);
			} else {
				if ($this->signVer == 'v2') {
					$headers[] = 'Authorization: ' . $this->__getSignature(
							$this->verb . "\n" .
							$this->headers['Content-MD5'] . "\n" .
							$this->headers['Content-Type'] . "\n" .
							$this->headers['Date'] . $amz . "\n" .
							$this->resource
					);
				} else {
					$amzHeaders = $this->__getSignatureV4(
							$this->amzHeaders, $this->headers, $this->verb, $this->uri, $this->data
					);
					foreach ($amzHeaders as $k => $v) {
						$headers[] = $k . ': ' . $v;
					}
				}
			}
		}

		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, false);
		curl_setopt($curl, CURLOPT_WRITEFUNCTION, array(&$this, '__responseWriteCallback'));
		curl_setopt($curl, CURLOPT_HEADERFUNCTION, array(&$this, '__responseHeaderCallback'));
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);

		// Request types
		switch ($this->verb) {
			case 'GET': break;
			case 'PUT': case 'POST': // POST only used for CloudFront
				if ($this->fp !== false) {
					curl_setopt($curl, CURLOPT_PUT, true);
					curl_setopt($curl, CURLOPT_INFILE, $this->fp);
					if ($this->size >= 0) {
						curl_setopt($curl, CURLOPT_INFILESIZE, $this->size);
					}
				} elseif ($this->data !== false) {
					curl_setopt($curl, CURLOPT_HEADER, true);
					curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $this->verb);
					curl_setopt($curl, CURLOPT_POSTFIELDS, $this->data);
				} else {
					curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $this->verb);
				}
				break;
			case 'HEAD':
				curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'HEAD');
				curl_setopt($curl, CURLOPT_NOBODY, true);
				break;
			case 'DELETE':
				curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
				break;
			default: break;
		}
		// set curl progress function callback
		if ($this->progressFunction) {
			curl_setopt($curl, CURLOPT_NOPROGRESS, false);
			curl_setopt($curl, CURLOPT_PROGRESSFUNCTION, $this->progressFunction);
		}
		
		//add by warlee;
		$theCurl = $curl;
		curl_setopt($theCurl, CURLOPT_NOPROGRESS, false);
		curl_setopt($theCurl, CURLOPT_PROGRESSFUNCTION,'curl_progress');
		$theResult = curl_progress_start($theCurl);
		if(!$theResult){$theResult = curl_exec($theCurl);curl_progress_end($theCurl,$theResult);}
		$curl = $theCurl;$result = $theResult;
		
		// Execute, grab errors
		if ($result) {
			$this->response->code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		} else {
			$this->response->error = array(
				'code'		 => curl_errno($curl),
				'message'	 => curl_error($curl),
				'resource'	 => $this->resource,
			);
		}
		@curl_close($curl);

		// Parse body into XML
		if ($this->response->error === false && isset($this->response->headers['type']) &&
			$this->response->headers['type'] == 'application/xml' && isset($this->response->body)) {
			if (substr($this->response->body, 0, 4) == 'HTTP') {
				$temp = explode(PHP_EOL, $this->response->body);
				$body = array();
				foreach($temp as $value) {
					if(stripos($value, ':') === false) continue;
					$item = explode(':', trim($value));
					$body[$item[0]] = $item[1];
				}
				$this->response->body = $body;
			}else{
				if(stripos($this->response->body, '<?xml')) $this->response->body = stristr($this->response->body,'<?xml');
				$this->response->body = simplexml_load_string($this->response->body);
			}

			// Grab S3 errors
			if (!in_array($this->response->code, array(200, 204, 206)) &&
				isset($this->response->body->Code, $this->response->body->Message)) {
				$this->response->error = array(
					'code'		 => (string) $this->response->body->Code,
					'message'	 => (string) $this->response->body->Message,
				);
				if (isset($this->response->body->Resource)) {
					$this->response->error['resource'] = (string) $this->response->body->Resource;
				}
				unset($this->response->body);
			}
		}
		// Clean up file resources
		if ($this->fp !== false && is_resource($this->fp)) {
			fclose($this->fp);
		}

		return $this->response;
	}

	/**
	 * Sort compare for meta headers.
	 *
	 * @internal Used to sort x-amz meta headers
	 *
	 * @param string $a String A
	 * @param string $b String B
	 *
	 * @return int
	 */
	private function __sortMetaHeadersCmp($a, $b) {
		$lenA = strpos($a, ':');
		$lenB = strpos($b, ':');
		$minLen = min($lenA, $lenB);
		$ncmp = strncmp($a, $b, $minLen);
		if ($lenA == $lenB) {
			return $ncmp;
		}
		if (0 == $ncmp) {
			return $lenA < $lenB ? -1 : 1;
		}

		return $ncmp;
	}

	/**
	 * CURL write callback.
	 *
	 * @param resource &$curl CURL resource
	 * @param string   &$data Data
	 *
	 * @return int
	 */
	private function __responseWriteCallback(&$curl, &$data) {
		if (in_array($this->response->code, array(200, 206)) && $this->fp !== false) {
			return fwrite($this->fp, $data);
		} else {
			$this->response->body .= $data;
		}

		return strlen($data);
	}

	/**
	 * Check DNS conformity.
	 *
	 * @param string $bucket Bucket name
	 *
	 * @return bool
	 */
	private function __dnsBucketName($bucket) {
		if (strlen($bucket) > 63 || preg_match("/[^a-z0-9\.-]/", $bucket) > 0) {
			return false;
		}
		if (strstr($bucket, '-.') !== false) {
			return false;
		}
		if (strstr($bucket, '..') !== false) {
			return false;
		}
		if (!preg_match('/^[0-9a-z]/', $bucket)) {
			return false;
		}
		if (!preg_match('/[0-9a-z]$/', $bucket)) {
			return false;
		}

		return true;
	}

	/**
	 * CURL header callback.
	 *
	 * @param resource $curl CURL resource
	 * @param string   $data Data
	 *
	 * @return int
	 */
	private function __responseHeaderCallback($curl, $data) {
		if (($strlen = strlen($data)) <= 2) {
			return $strlen;
		}
		if (substr($data, 0, 4) == 'HTTP') {
			$this->response->code = (int) substr($data, 9, 3);
		} else {
			$data = trim($data);
			if (strpos($data, ': ') === false) {
				return $strlen;
			}
			list($header, $value) = explode(': ', $data, 2);
			if ($header == 'Last-Modified') {
				$this->response->headers['time'] = strtotime($value);
			} elseif ($header == 'Date') {
				$this->response->headers['date'] = strtotime($value);
			} elseif ($header == 'Content-Length') {
				$this->response->headers['size'] = (int) $value;
			} elseif ($header == 'Content-Type') {
				$this->response->headers['type'] = $value;
			// } elseif ($header == 'ETag') {
			} elseif (strtolower($header) == 'etag') {
				$this->response->headers['hash'] = $value[0] == '"' ? substr($value, 1, -1) : $value;
			} elseif (preg_match('/^x-amz-meta-.*$/', $header)) {
				$this->response->headers[$header] = $value;
			}
		}

		return $strlen;
	}

}

/**
 * S3 exception class.
 *
 * @see http://undesigned.org.za/2007/10/22/amazon-s3-php-class
 *
 * @version 0.5.0-dev
 */
class S3Exception extends \Exception {

	/**
	 * Class constructor.
	 *
	 * @param string $message Exception message
	 * @param string $file    File in which exception was created
	 * @param string $line    Line number on which exception was created
	 * @param int    $code    Exception code
	 */
	public function __construct($message, $file, $line, $code = 0) {
		parent::__construct($message, $code);
		$this->file = $file;
		$this->line = $line;
	}

}
