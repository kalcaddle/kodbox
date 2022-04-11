<?php

/**
getID3 获取图片,音频,视频信息 https://www.getid3.org/
修改:
	0. getid3/getid3.php  openfile方法474行后面,加入:
		$this->info['filepath']='';$this->info['filenamepath'] = $filename; //add by warlee;
 	1. getid3/module.audio-video.flv.php  Analyze方法311行后加入
		if($found_video && $found_audio && $found_meta){break;}//add by warlee; 找到后停止;
		搜索:$info['playtime_seconds'] = $Duration / 1000;
	2. getid3/module.graphic.gif.php  Analyze方法93行后加入: return; // add by warlee;
	3. getid3/module.archive.rar.php  26行改为true: public $option_use_rar_extension = true; //add by warlee;
 */
class GetInfo{
	private static $fileTypeArray;
	public static function get($file){
		$info = IO::info($file);
		return self::infoAdd($info);
	}
	public static function check(){}
	public static function infoAdd(&$info){
		static $obj;
		if (!$obj) {
			require SDK_DIR.'/getID3/getid3/getid3.php';
			$obj = new getID3();			
		}
		if(!$info || $info['type'] != 'file') return;
		if(!self::support($info['ext']) || isset($info['fileInfoMore']) ) return;

		$theFile = 'kodio://'.$info['path']; // 地址处理;
		$fileType = $info['ext'];
		try {
			if($info['ext'] == 'psd'){
				$fileInfo = self::psdParse($theFile);
				$fileType = 'image';
			}else if($info['ext'] == 'pdf'){
				$fileInfo = self::pdfParse($theFile);
			}else{
				$fileType = self::$fileTypeArray['extType'][$info['ext']];
				$keyArray = self::$fileTypeArray['support'][$fileType]['keyMap'];
				$getInfo  = @$obj->analyze($theFile,$info['size'],$info['name']);
				$fileInfo = self::parseData($getInfo,$keyArray,$info);
				// pr($theFile,$getInfo,$fileInfo,exif_read_data($info['path']));exit;
				// $filePath = 'data://image/jpeg;base64,'.base64_encode(StreamWrapperIO::read($theFile,0,1024*64));
				// pr(exif_read_data($filePath),exif_read_data($info['path']),exif_read_data($theFile),$getInfo);exit;
			}
		} catch (Exception $e){
			$fileInfo = array('fileType'=>$fileType);
		}
		
		if(!$fileInfo) return;
		
		$fileInfo = self::arrayValueLimit($fileInfo);//长度限制处理;
		$fileInfo['fileType'] = $fileType;
		$info['fileInfoMore'] = $fileInfo;
		return $info;
	}
	public static function support($ext){
		if(!self::$fileTypeArray){
			self::$fileTypeArray = self::fileTypeParse();
		}
		$ext = strtolower($ext);
		$support = array('pdf','psd');
		if(in_array($ext,$support)) return true;
		if(isset(self::$fileTypeArray['extType'][$ext])) return true;
		return false;			
	}
	
	// 长度限制处理;
	public static function arrayValueLimit($fileInfo,$maxLength=5000){
		if(!is_array($fileInfo)) return $fileInfo;
		foreach($fileInfo as $key=>$value){
			if(is_string($value) && strlen($value) > $maxLength){
				$fileInfo[$key] = substr($value, 0, $maxLength);
			}else if(is_array($value)){
				$fileInfo[$key] = self::arrayValueLimit($value,$maxLength);
			}
		}
		return $fileInfo;
	}

	
	// 数据解析处理;
	private static function parseData($getInfo,$keyArray,$fileInfo){
		$ext = $getInfo['fileformat'];
		$result = array();
		
		foreach ($keyArray as $key => $matchKeyArray){
			foreach($matchKeyArray as $matchKey){
				if(is_array($matchKey)){
					$value = self::parseData($getInfo,$matchKeyArray,$fileInfo);
					if($value){$result[$key] = $value;}
					break;
				}				
				$matchKeyTrue = str_replace('@',$ext,$matchKey);
				$value = _get($getInfo,$matchKeyTrue);
				$value = is_array($value) ? $value[0] : $value;
				if($value){break;}
			}			
			if($value || $value === 0 || $value === false){
				$value = self::valueReset($matchKey,$value,$key);
				if($value !== null){
					$result[$key] = $value;
				}
			}
		}
		$result = self::valueResetAll($result,$fileInfo);
		return $result;
	}
	

	// psd文件信息;(尺寸获取)
	// 其他信息: https://github.com/hasokeric/php-psd/blob/master/PSDReader.php
	// getimagesize : https://www.runoob.com/php/php-getimagesize.html
	private static function psdParse($file){
		if(IO::fileSubstr($file,0,4)!='8BPS') return false;
		$info = getImageSize($file,$imageinfo);
		if(!$info) return;
		$result = array(
			'sizeWidth' 	=> $info[0],
			'sizeHeight' 	=> $info[1],
			// 'info' 			=> $info,
		);
		return $result;
	}
	private static function pdfParse($file){
		return FileParsePdf::parse($file);
	}
	private static function fileTypeParse(){
		$support = self::fileTypeArray();
		$extType = array();
		foreach ($support as $type => $item){
			$ext = _get($item,'ext','');
			$extArray = is_string($ext) ? explode(',',$ext) :$ext;
			if(!$extArray){continue;}
			foreach ($extArray as $ext){
				$ext = strtolower(trim($ext));
				if(!$ext) continue;
				$extType[$ext] = $type;
			}
			$support[$type]['ext']    = array_keys($extType);
			$support[$type]['keyMap'] = self::parseKeyMap($item['keyMap']);
		}
		return array('support'=>$support,'extType'=>$extType);
	}
	private static function parseKeyMap($keyMap){
		foreach ($keyMap as $theKey => $matchKeys) {
			if(is_array($matchKeys)){
				$value = self::parseKeyMap($matchKeys);
				if($value){$keyMap[$theKey] = $value;}
				continue;
			}
			$keyArray = explode(',',$matchKeys);
			$keyArrayResult = array();
			foreach ($keyArray as $matchKey){
				$matchKey = trim($matchKey);
				if(!$matchKey) continue;
				// 组合key自动转为数组处理; tags.[id3v1|id3v2|ape].title
				if(preg_match('/\[(.*)\]/',$matchKey,$matchs)){ // 组合key;
					$replaceArray = explode('|',$matchs[1]);
					foreach ($replaceArray as $addKey) {
						$keyArrayResult[] = str_replace($matchs[0],$addKey,$matchKey);
					}
				}else{
					$keyArrayResult[] = $matchKey;
				}
			}
			$keyMap[$theKey] = $keyArrayResult;
		}
		return $keyMap;
	}
	
	
	private static function valueResetAll($info,$fileInfo){
		if(isset($info['playtime'])){
			$time 	= ceil($info['playtime']);
			$hour 	= intval($time / 3600);
			$minute = intval(($time - $hour*3600) / 60);
			$second = $time % 60;
			$info['playtimeShow'] = sprintf('%02d:%02d:%02d',$hour,$minute,$second);
			if($hour == 0){
				$info['playtimeShow'] = sprintf('%02d:%02d',$minute,$second);
			}
		}
		
		// 音乐专辑封面处理;
		$audioImage = _get($info,'tags.image');
		if($audioImage){
			$cacheFile	=  IO_PATH_SYSTEM_TEMP . 'thumb/audio/'.KodIO::hashPath($fileInfo).'.jpg';
			$info['fileThumb'] = Action('toolsCommonPlugin')->pluginCacheFileSet($cacheFile,$audioImage);
			unset($info['tags']['image']);
		}
		
		// 编码处理;
		$audioTitle = _get($info,'tags.title','');
		$audioArtist= _get($info,'tags.artist','');
		$audioAlbum = _get($info,'tags.album','');
		$tagInfo 	= $audioTitle.$audioArtist.$audioAlbum;
		if($tagInfo && get_charset($tagInfo) != 'utf-8'){
			$info['tags']['title'] 	= iconv_to($info['tags']['title'],get_charset($tagInfo),'utf-8');
			$info['tags']['artist'] = iconv_to($info['tags']['artist'],get_charset($tagInfo),'utf-8');
			$info['tags']['album'] 	= iconv_to($info['tags']['album'],get_charset($tagInfo),'utf-8');
		}
		
		return $info;
	}
	
	/**
	 * 数值处理
	 * 时间处理;  时间戳:大圣归来.mp4;  负数:3G2 Video.3g2; 
	 * 音乐封面图片加入系统缓存处理; hashSimple=> img=>url;
	 */
	private static function valueReset($key,$value,$newKey){
		$timeFormate = 'Y-m-d H:i:s';
		switch ($key) {
			case '@.exif.EXIF.ColorSpace':$value = $value == '1'?'sRGB':'RGB';break;
			case 'audio.channels':$value = $value ? $value:null;break;
			// case 'audio.bitrate':
			// case 'video.bitrate':$value = round($value / 1000) .' kbps';break;
			default:break;
		}
		
		switch ($newKey) {
			case 'frameRate':$value = round($value,2);break;
			case 'createTime':
			case 'modifyTime':
				if($value < 0){
					$value = null;
				}else if(is_numeric($value)){
					$value = date($timeFormate,$value);
				}
				break;
			default:break;
		}
		
		return $value;
	}
		
	// 数据整理;
	private static function fileTypeArray(){
		return array(
		// @:代表替换为扩展名; eg: @.exif.IFD0.Software 在png下代表 png.exif.IFD0.Software
		// , 冒号代表多个取值时从前到后依次获取直到满足; eg: 
		// [aa|bb] 自动展开占位;  eg: tags.[id3v1|id3v2].title => tags.id3v1.title,tags.id3v2.title
		
		// 没有key则不取该值; 冒号依次向后取到key为止; 取得值为数组则取第一个值;
		// exif详解: https://www.cnblogs.com/billgore/p/4301622.html
		// https://m.sojson.com/image/exif.html
		'image'	=> array(
			'ext'	=> 'jpg,jpeg,png,gif,bmp,tiff,tif,psd,pcd,svg,efax,webp,'.			// exif解析处理;TODO;
					   'cr2,erf,kdc,dcr,dng,nrw,nef,orf,rw2,pef,srw,arw,sr2',
			'keyMap'	=> array(
				'sizeWidth' 	=> 'video.resolution_x',
				'sizeHeight' 	=> 'video.resolution_y',
				'resolutionX' 	=> '@.exif.IFD0.XResolutionx,@.exif.IFD0.XResolution',		// 分辨率 dpi
				'resolutionY' 	=> '@.exif.IFD0.XResolutiony,@.exif.IFD0.YResolution',		// 分辨率 dpi
				'createTime' 	=> '@.exif.IFD0.DateTime,@.EXIF.DateTimeOriginal,
									xmp.xmp.CreateDate,tags.@.datetime', 					// 拍摄日期
				'modifyTime' 	=> '.xmp.xmp.ModifyDate', 									// 修改日期		
				'orientation' 	=> '@.exif.IFD0.Orientation', 								// 方向
				'software' 		=> '@.exif.IFD0.Software,
									tags.@.Software,tags.@.software,
									xmp.xmp.CreatorTool',						// 内容创建者
				'bitPerSample' 	=> 'video.bits_per_sample',						// 位深度
				'bitsPerPixel'  => '@.header.bits_per_pixel,@.header.raw.bits_per_pixel',//位深度
				
				// 色彩空间;色彩描述文件;
				'colorSpace'	=> '@.exif.EXIF.ColorSpace,@.sRGB.header.type_text,
									@.IHDR.color_type,@.header.compression',
				
				// gif; https://blog.csdn.net/yuejisuo1948/article/details/83617359
				'colorSize'		=> '@.header.global_color_size',
				'animated'		=> '@.animation.animated',
				
				// gps
				'gps' 			=> array(//经度,东西N/E;纬度,南北纬S/N;海拔
					'longitude'		=> '@.exif.GPS.computed.longitude',
					'latitude'		=> '@.exif.GPS.computed.latitude',
					'altitude'		=> '@.exif.GPS.computed.altitude',
				),
				'deviceMake' 	=> '@.exif.IFD0.Make,tags.@.make', 							// 设备制造商
				'deviceType' 	=> '@.exif.IFD0.Model,tags.@.model',						// 设备型号
				'imageDesc' 	=> '@.exif.IFD0.ImageDescription,tags.@.imagedescription',	// 图片说明
				'imageArtist' 	=> '@.exif.IFD0.artist,tags.@.artist',						// 图片作者
				
				// 拍摄信息; https://blog.csdn.net/dreamboycx/article/details/40591875
				'camera' => array(
					'ApertureFNumber'		=>'@.exif.COMPUTED.ApertureFNumber',		// 光圈数; f/2.2
					'ApertureValue'			=>'@.exif.EXIF.ApertureValue',				// 光圈值; 2.275
					'ShutterSpeedValue'		=>'@.exif.EXIF.ShutterSpeedValue',			// 快门速度; 5.05
					'ExposureTime'			=>'@.exif.EXIF.ExposureTime',				// 曝光时间s; 0.033  转换为:1/33
					'FocalLength'			=>'@.exif.EXIF.FocalLength',				// 焦距 mm; eg:4.15					
					'FocalLengthIn35mmFilm'	=>'@.exif.EXIF.FocalLengthIn35mmFilm',		// 等价35mm焦距 mm; eg:29
					'FocusDistance'			=>'@.exif.COMPUTED.FocusDistance',			// 对焦距离
					'ISOSpeedRatings'		=>'@.exif.EXIF.ISOSpeedRatings',			// ISO感光度
					'WhiteBalance'			=>'@.exif.EXIF.WhiteBalance',				// 白平衡    1-手动;0-自动
					'ExposureMode'			=>'@.exif.EXIF.ExposureMode',				// 曝光模式  1-手动;0-自动
					'ExposureBiasValue'		=>'@.exif.EXIF.ExposureBiasValueEV',		// 曝光补偿
				),
			),
		),
		'audio'	=> array(
			'ext'	=> 'aac,adts,au,amr,avr,bonk,dsf,dss,ff,dts,flac,la,lpac,midi,mac,it,xm,s3m,'.
					   'mpc,mp3,ofr,rkau,shn,tak,wav,wma,m4a,ogg,vqf,wv,voc,tta',
			'keyMap' => array(
				'playtime' 		=> 'playtime_seconds',									// 时长,向上取整;
				'createTime' 	=> 'quicktime.timestamps_unix.create.moov mvhd',		// 内容创建时间
				'modifyTime' 	=> 'quicktime.timestamps_unix.modify.moov mvhd',		// 内容修改时间
				'software' 		=> 'audio.matroska.encoder,
									tags.matroska.[encoder|writingapp,handler_name],
									tags.quicktime.[software|encoding_tool],
									audio.encoder',							// 编码软件					
				'rate' 			=> 'audio.sample_rate',						// 采样速率
				'channel' 		=> 'audio.channels',						// 音频声道; 为1则不显示;
				'dataformat' 	=> 'audio.codec',							// 编解码器 h264
				'bitPerSimple' 	=> 'audio.bits_per_sample',					// 每个样本的位数
				'bitrate' 		=> 'audio.bitrate',							// 比特率 kbps
				
				// 'channelmode' 	=> 'audio.channelmode',					// 
				'tags' 	=> array(
					'title' 		=> '[id3v1|id3v2|ape|vorbiscomment|quicktime].title',			// 标题
					'artist' 		=> '[id3v1|id3v2|ape|vorbiscomment|quicktime].artist',			// 作者/艺术家
					'album' 		=> '[id3v1|id3v2|ape|vorbiscomment|quicktime].album',			// 专辑
					'genre' 		=> '[id3v1|id3v2|ape|vorbiscomment|quicktime].genre',			// 风格
					'year' 			=> '[id3v1|id3v2|ape|vorbiscomment|quicktime].year',			// 年代
					'track'			=> '[id3v1|id3v2|ape|vorbiscomment|quicktime].track_number',	// 音轨
					'image' 		=> 'comments.picture.0.data',									// 专辑封面
				),
			)
		),
		'video'	=> array(
			'ext'	=> 'mp4,flv,f4v,wmv,ogv,mov,avi,rmvb,mkv,rm,webm,m4v,real,swf,'.
					   '3g2,3gp,asf,bink,ivf,nsv,ts,ts,wtv,mts,mpe,mpeg,mpg,vob', //m2ts
			'keyMap'	=> array( //TODO; 编解码器;
				'sizeWidth' 	=> '@.meta.onMetaData.width,video.resolution_x',
				'sizeHeight' 	=> '@.meta.onMetaData.height,video.resolution_y',
				'playtime' 		=> 'playtime_seconds',									// 时长,向上取整;
				'createTime' 	=> 'quicktime.timestamps_unix.create.moov mvhd,tags.matroska.creation_time',// 内容创建时间
				'modifyTime' 	=> 'quicktime.timestamps_unix.modify.moov mvhd,tags.matroska.modify_time',  // 内容修改时间
				'frameRate' 	=> 'video.frame_rate',						// 帧率	向上取整;
				'bitrate' 		=> '@.video.raw.bitrate,video.bitrate',		// 数据比特率 kbps

				'dataformat' 	=> 'video.fourcc_lookup,
									video.streams.[01|1].dataformat,
									video.streams.[01|1].fourcc,
									video.streams.[01|1].codec,
									quicktime.video.codec_fourcc_lookup,
									video.fourcc,video.codec',			// 编解码器 h264
				'software' 		=> 'tags.matroska.[writingapp|handler_name|encoder],
									@.meta.onMetaData.[encoder|metadatacreator|creator],
									tags.[riff|quicktime].software,
									tags.@.encodingsettings,
									@.comments.encodingsettings,
									tags.quicktime.encoding_tool',		// 编码软件
				'audio' 	=>	array(
					'channel' 		=> 'audio.channels',				// 音频声道; 1 (单声道),2 (立体声),3+ (多声道)
					'channelmode' 	=> 'audio.channelmode',				// 音频模式
					'rate' 			=> 'audio.sample_rate',				// 声音.采样速率
					'bitrate' 		=> 'audio.bitrate',					// 声音.比特率 kbps
					'dataformat' 	=> 'audio.codec,audio.dataformat',	// 音频 编码译码器;
					'bitPerSimple' 	=> 'audio.bits_per_sample',			// 每个样本的位数
				),
			),
		),
		'archive' 	=> array(
			// 'ext'	 => 'zip,tar,rar,gz,7z,tar,xz,par2,szip,cue,iso,hpk',
			'keyMap' => array(
				'unzipSize' 			=> 'uncompressed_size',			// 解压后大小
				'childrenCount' 		=> 'entries_count',				// 字内容数
			)
		),
		'torrent' 	=> array(
			// 'ext'	=> 'torrent',
			'keyMap' => array()
		));
	}
}
// exif_read_data 读取streamwrapper的文件会异常;
// 参考: https://github.com/avalanche123/Imagine/issues/728
function exif_read_data_io($file,$sections = null,$asArray=false,$readThumbnail=false){
	$fileStr  = StreamWrapperIO::read($file,0,1024*64);
	$filePath = 'data://image/jpeg;base64,'.base64_encode($fileStr);
	return exif_read_data($filePath,$sections,$asArray,$readThumbnail);
}
function getimagesize_io($filePath,&$info=false){
	$fileStr  = StreamWrapperIO::read($filePath,0,1024*64);
	$filePath = 'data://image/jpeg;base64,'.base64_encode($fileStr);
	return getimagesize($filePath,$info);
}