<?php

/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/

class Downloader {
	static function start($url,$saveFile,$timeout = 10) {
		if(!request_url_safe($url)) return array('code'=>false,'data'=>'url error!');
		
		$dataFile = $saveFile . '.download.cfg';
		$saveTemp = $saveFile . '.downloading';		
		//header:{'url','length','name','supportRange'}
		if(is_array($url)){
			$fileHeader = $url;
		}else{
			$fileHeader = url_header($url);
		}
		$url = $fileHeader['url'];
		if(!$url){
			return array('code'=>false,'data'=>'url error!');
		}
		//默认下载方式if not support range
		if(!$fileHeader['supportRange'] || 
			$fileHeader['length'] == 0 ){
			@unlink($saveTemp);@unlink($saveFile);
			$result = self::fileDownloadFopen($url,$saveFile,$fileHeader['length']);
			if($result['code']) {
				return $result;
			}else{
				@unlink($saveTemp);@unlink($saveFile);
				$result = self::fileDownloadCurl($url,$saveFile,false,0,$fileHeader['length']);
				@unlink($saveTemp);
				return $result;
			}
		}

		$existsLength  = is_file($saveTemp) ? filesize_64($saveTemp) : 0;
		$contentLength = intval($fileHeader['length']);
		if( file_exists($saveTemp) &&
			time() - filemtime($saveTemp) < 3) {//has Changed in 3s,is downloading 
			return array('code'=>false,'data'=>'downloading');
		}
		
		$existsData = array();
		if(is_file($dataFile)){
			$tempData = file_get_contents($dataFile);
			$existsData = json_decode($tempData, 1);
		}
		// exist and is the same file;
		if( file_exists($saveFile) && $contentLength == filesize_64($saveFile)){
			@unlink($saveTemp);
			@unlink($dataFile);
			return array('code'=>true,'data'=>'exist');
		}

		// check file is expire
		if ($existsData['length'] != $contentLength) {
			$existsData = array('length' => $contentLength);
		}
		if($existsLength > $contentLength){
			@unlink($saveTemp);
		}
		// write exists data
		file_put_contents($dataFile, json_encode($existsData));
		$result = self::fileDownloadCurl($url,$saveFile,true,$existsLength,$contentLength);
		if($result['code']){
			@unlink($dataFile);
		}
		return $result;
	}
	

	// fopen then download
	static function fileDownloadFopen($url, $fileName,$headerSize=0){
		@ini_set('user_agent','Mozilla/5.0 (Windows NT 6.2; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/27.0.1453.94 Safari/537.36');

		$fileTemp = $fileName.'.downloading';
		set_timeout();
		@unlink($fileTemp);
		if ($fp = @fopen ($url, "rb")){
			if(!$downloadFp = @fopen($fileTemp, "wb")){
				return array('code'=>false,'data'=>'open_downloading_error');
			}
			while(!feof($fp)){
				if(!file_exists($fileTemp)){//删除目标文件；则终止下载
					fclose($downloadFp);
					return array('code'=>false,'data'=>'stoped');
				}
				//对于部分fp不结束的通过文件大小判断
				clearstatcache();
				if( $headerSize>0 &&
					$headerSize==filesize(iconv_system($fileTemp))
					){
					break;
				}
				fwrite($downloadFp, fread($fp, 1024 * 8 ), 1024 * 8);
			}
			//下载完成，重命名临时文件到目标文件
			fclose($downloadFp);
			fclose($fp);

			self::checkGzip($fileTemp);
			if(!@rename($fileTemp,$fileName)){
				usleep(round(rand(0,1000)*50));//0.01~10ms
				@unlink($fileName);
				$res = @rename($fileTemp,$fileName);
				if(!$res){
					return array('code'=>false,'data'=>'rename error![open]');
				}
			}
			return array('code'=>true,'data'=>'success');
		}else{
			return array('code'=>false,'data'=>'url_open_error');
		}
	}

	// curl 方式下载
	// 断点续传 http://www.linuxidc.com/Linux/2014-10/107508.htm
	static function fileDownloadCurl($url, $fileName,$supportRange=false,$existsLength=0,$length=0){
		$fileTemp = $fileName.'.downloading';
		set_timeout();
		$fp = @fopen ($fileTemp, "a");
		if(!$fp) return array('code'=>false,'data'=>'file create error');
		$ch = curl_init($url);
		if($supportRange){
			curl_setopt($ch, CURLOPT_RANGE, $existsLength."-");
		}
		curl_setopt($ch, CURLOPT_FILE, $fp);
		curl_setopt($ch, CURLOPT_REFERER,get_url_link($url));
		curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_NOPROGRESS, false);
		curl_setopt($ch, CURLOPT_PROGRESSFUNCTION,'curl_progress');curl_progress_start($ch);
		curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (Windows NT 6.2; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/27.0.1453.94 Safari/537.36');
		$res = curl_exec($ch);curl_progress_end($ch);
		curl_close($ch);
		fclose($fp);

		$filesize = filesize_64(iconv_system($fileTemp));
		if($filesize < $length && $length!=0){
			return array('code'=>false,'data'=>'downloading');
		}
		if($res && filesize_64($fileTemp) != 0){
			self::checkGzip($fileTemp);
			if(!@rename($fileTemp,$fileName)){
				@unlink($fileName);
				$res = @rename($fileTemp,$fileName);
				if(!$res){
					return array('code'=>false,'data'=>'rename error![curl]');
				}
			}
			return array('code'=>true,'data'=>'success');
		}
		return array('code'=>false,'data'=>'curl exec error!');
	}

	static function checkGzip($file){
		$char = "\x1f\x8b";
		$str  = file_sub_str($file,0,2);
		if($char != $str) return;

		ob_start();   
		readgzfile($file);   
		$out = ob_get_clean();
		file_put_contents($file,$out);
	}
}
