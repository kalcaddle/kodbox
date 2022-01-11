<?php
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/

/**
 * 系统函数：				filesize(),file_exists(),pathinfo(),rname(),unlink(),filemtime(),is_readable(),is_wrieteable();
 * 获取文件详细信息		file_info($fileName)
 * 获取文件夹详细信息		path_info($dir)
 * 递归获取文件夹信息		path_info_more($dir,&$fileCount=0,&$pathCount=0,&$size=0)
 * 获取文件夹下文件列表	path_list($dir)
 * 路径当前文件[夹]名		get_path_this($path)
 * 获取路径父目录			get_path_father($path)
 * 删除文件				del_file($file)
 * 递归删除文件夹			del_dir($dir)
 * 递归复制文件夹			copy_dir($source, $dest)
 * 创建目录				mk_dir($dir, $mode = 0777)
 * 文件大小格式化			size_format($bytes, $precision = 2)
 * 判断是否绝对路径		path_is_absolute( $path )
 * 扩展名的文件类型		ext_type($ext)
 * 文件下载				file_download($file)
 * 文件下载到服务器		file_download_this($from, $fileName)
 * 获取文件(夹)权限		get_mode($file)  //rwx_rwx_rwx [文件名需要系统编码]
 * 上传文件(单个，多个)	upload($fileInput, $path = './');//
 * 获取配置文件项			get_config($file, $ini, $type="string")
 * 修改配置文件项			update_config($file, $ini, $value,$type="string")
 * 写日志到LOG_PATH下		write_log('dd','default|.自建目录.','log|error|warning|debug|info|db')
 */

// 传入参数为程序编码时，有传出，则用程序编码，
// 传入参数没有和输出无关时，则传入时处理成系统编码。
function iconv_app($str){
	global $config;
	$result = iconv_to($str,$config['systemCharset'], $config['appCharset']);
	return $result;
}
function iconv_system($str){
	//去除中文空格UTF8; windows下展示异常;过滤文件上传、新建文件等时的文件名
	//文件名已存在含有该字符时，没有办法操作.
	$char_empty = "\xc2\xa0";
	if(strpos($str,$char_empty) !== false){
		$str = str_replace($char_empty," ",$str);
	}

	global $config;
	$result = iconv_to($str,$config['appCharset'], $config['systemCharset']);
	$result = path_filter($result);
	return $result;
}
function iconv_to($str,$from,$to){
	if (strtolower($from) == strtolower($to)){
		return $str;
	}
	if (!function_exists('iconv')){
		return $str;
	}
	//尝试用mb转换；android环境部分问题解决
	if(function_exists('mb_convert_encoding')){
		$result = @mb_convert_encoding($str,$to,$from);
	}else{
		$result = @iconv($from, $to, $str);
	}
	if(strlen($result)==0){ 
		return $str;
	}
	return $result;
}
function path_filter($path){
	if(strtoupper(substr(PHP_OS, 0,3)) != 'WIN'){
		return $path;
	}
	$notAllow = array('*','?','"','<','>','|');//去除 : D:/
	return str_replace($notAllow,' ', $path);
}

//filesize 解决大于2G 大小问题
//http://stackoverflow.com/questions/5501451/php-x86-how-to-get-filesize-of-2-gb-file-without-external-program
function get_filesize($path){
	if(PHP_INT_SIZE >= 8 ){ //64bit
		return (float)(abs(sprintf("%u",@filesize($path))));
	}
	
	$fp = fopen($path,"r");
	if(!$fp) return 0;	
	if (fseek($fp, 0, SEEK_END) === 0) {
		$result = 0.0;
		$step = 0x7FFFFFFF;
		while ($step > 0) {
			if (fseek($fp, - $step, SEEK_CUR) === 0) {
				$result += floatval($step);
			} else {
				$step >>= 1;
			}
		}
	}else{
		static $iswin;
		if (!isset($iswin)) {
			$iswin = (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN');
		}
		static $exec_works;
		if (!isset($exec_works)) {
			$exec_works = (function_exists('exec') && !ini_get('safe_mode') && @exec('echo EXEC') == 'EXEC');
		}
		if ($iswin && class_exists("COM")) {
			try {
				$fsobj = new COM('Scripting.FileSystemObject');
				$f = $fsobj->GetFile( realpath($path) );
				$size = $f->Size;
			} catch (Exception $e) {
				$size = null;
			}
			if (is_numeric($size)) {
				$result = $size;
			}
		}else if ($exec_works){
			$cmd = ($iswin) ? "for %F in (\"$path\") do @echo %~zF" : "stat -c%s \"$path\"";
			@exec($cmd, $output);
			if (is_array($output) && is_numeric($size = trim(implode("\n", $output)))) {
				$result = $size;
			}
		}else{
			$result = filesize($path);
		}
	}
	fclose($fp);
	return $result;
}

//文件是否存在，区分文件大小写
function file_exists_case( $fileName ){
	if(file_exists($fileName) === false){
		return false;
	}
	$status         = false;
	$directoryName  = dirname( $fileName );
	$fileArray      = glob( $directoryName . '/*', GLOB_NOSORT);
	if ( preg_match( "/\\\|\//", $fileName) ){
		$array    = preg_split("/\\\|\//", $fileName);
		$fileName = $array[ count( $array ) -1 ];
	}
	foreach($fileArray as $file ){
		if(preg_match("/{$fileName}/i", $file)){
			$output = "{$directoryName}/{$fileName}";
			$status = true;
			break;
		}
	}
	return $status;
}


function path_readable($path){
	$result = intval(is_readable($path));
	if($result){
		return $result;
	}
	$mode = get_mode($path);
	if( $mode && 
		strlen($mode) == 18 &&
		substr($mode,-9,1) == 'r'){// -rwx rwx rwx(0777)
		return true;
	}
	return false;
}
function path_writeable($path){
	$result = intval(is_writeable($path));
	if($result) return $result;

	$mode = get_mode($path);
	if( $mode && 
		strlen($mode) == 18 &&
		substr($mode,-8,1) == 'w'){// -rwx rwx rwx (0777)
		return true;
	}
	return false;
}

/**
 * 获取文件详细信息
 * 文件名从程序编码转换成系统编码,传入utf8，系统函数需要为gbk
 */
function file_info($path){
	$info = array(
		'name'			=> iconv_app(get_path_this($path)),
		'path'			=> iconv_app($path),
		'ext'			=> get_path_ext($path),
		'type' 			=> 'file',
		'mode'			=> get_mode($path),
		'atime'			=> @fileatime($path), //最后访问时间
		'ctime'			=> @filectime($path), //创建时间
		'mtime'			=> @filemtime($path), //最后修改时间
		'isReadable'	=> path_readable($path),
		'isWriteable'	=> path_writeable($path),
		'size'			=> get_filesize($path)
	);
	return $info;
}
/**
 * 获取文件夹细信息
 */
function folder_info($path){
	$info = array(
		'name'			=> iconv_app(get_path_this($path)),
		'path'			=> iconv_app(rtrim($path,'/').'/'),
		'type' 			=> 'folder',
		'mode'			=> get_mode($path),
		'atime'			=> @fileatime($path), //访问时间
		'ctime'			=> @filectime($path), //创建时间
		'mtime'			=> @filemtime($path), //最后修改时间
		'isReadable'	=> path_readable($path),
		'isWriteable'	=> path_writeable($path)
	);
	return $info;
}


/**
 * 获取一个路径(文件夹&文件) 当前文件[夹]名
 * test/11/ ==>11 test/1.c  ==>1.c
 */
function get_path_this($path){
	$path = str_replace('\\','/', rtrim($path,'/'));
	$pos = strrpos($path,'/');
	if($pos === false){
		return $path;
	}
	return substr($path,$pos+1);
}
/**
 * 获取一个路径(文件夹&文件) 父目录
 * /test/11/==>/test/   /test/1.c ==>/www/test/
 */
function get_path_father($path){
	$path = str_replace('\\','/', rtrim($path,'/'));
	$pos = strrpos($path,'/');
	if($pos === false){
		return $path;
	}
	return substr($path, 0,$pos+1);
}
/**
 * 获取扩展名
 */
function get_path_ext($path){
	$name = get_path_this($path);
	$ext = '';
	if(strstr($name,'.')){
		$ext = substr($name,strrpos($name,'.')+1);
		$ext = strtolower($ext);
	}
	if (strlen($ext)>3 && preg_match("/([\x81-\xfe][\x40-\xfe])/", $ext, $match)) {
		$ext = '';
	}
	return htmlspecialchars($ext);
}



//自动获取不重复文件(夹)名
//如果传入$file_add 则检测存在则自定重命名  a.txt 为a{$file_add}.txt
function get_filename_auto($path,$file_add = "",$same_file_type='replace'){
	if (is_dir($path) && $same_file_type!=REPEAT_RENAME_FOLDER) {//文件夹则忽略
		return $path;
	}
	//重名处理
	if (file_exists($path)) {
		if ($same_file_type== REPEAT_REPLACE) {
			return $path;
		}else if($same_file_type==REPEAT_SKIP){
			return false;
		}
	}

	$i=1;
	$father = get_path_father($path);
	$name =  get_path_this($path);
	$ext = get_path_ext($name);
	if(is_dir($path)){
		$ext = '';
	}
	if (strlen($ext)>0) {
		$ext='.'.$ext;
		$name = substr($name,0,strlen($name)-strlen($ext));
	}
	while(file_exists($path)){
		if ($file_add != '') {
			$path = $father.$name.$file_add.$ext;
			$file_add.='-';
		}else{
			$path = $father.$name.'('.$i.')'.$ext;
			$i++;
		}
	}
	return $path;
}

/**
 * 获取文件夹详细信息,文件夹属性时调用，包含子文件夹数量，文件数量，总大小
 */
function path_info($path){
	if (!file_exists($path)) return false;
	$pathinfo = _path_info_more($path);//子目录文件大小统计信息
	$folderinfo = folder_info($path);
	return array_merge($pathinfo,$folderinfo);
}

/**
 * 检查名称是否合法
 */
function path_check($path){
	$check = array('/','\\',':','*','?','"','<','>','|');
	$path = rtrim($path,'/');
	$path = get_path_this($path);
	foreach ($check as $v) {
		if (strstr($path,$v)) {
			return false;
		}
	}
	return true;
}

/**
 * 递归获取文件夹信息： 子文件夹数量，文件数量，总大小
 */
function _path_info_more($dir, &$fileCount = 0, &$pathCount = 0, &$size = 0){
	if (!$dh = @opendir($dir)) return array('fileCount'=>0,'folderCount'=>0,'size'=>0);
	while (($file = readdir($dh)) !== false) {
		if ($file =='.' || $file =='..') continue;
		$fullpath = $dir . "/" . $file;
		if (!is_dir($fullpath)) {
			$fileCount ++;
			$size += get_filesize($fullpath);
		} else {
			_path_info_more($fullpath, $fileCount, $pathCount, $size);
			$pathCount ++;
		}
	}
	closedir($dh);
	$pathinfo['fileCount'] = $fileCount;
	$pathinfo['folderCount'] = $pathCount;
	$pathinfo['size'] = $size;
	return $pathinfo;
}


/**
 * 获取多选文件信息,包含子文件夹数量，文件数量，总大小，父目录权限
 */
function path_info_muti($list,$timeType){
	$list = is_array($list) ? $list : array();
	if (count($list) == 1) {
		if ($list[0]['type']=="folder"){
			return path_info($list[0]['path'],$timeType);
		}else{
			return file_info($list[0]['path'],$timeType);
		}
	}
	$pathinfo = array(
		'fileCount'		=> 0,
		'folderCount'	=> 0,
		'size'			=> 0,
		'father_name'	=> '',
		'mod'			=> ''
	);
	foreach ($list as $val){
		if ($val['type'] == 'folder') {
			$pathinfo['folderCount'] ++;
			$temp = path_info($val['path']);
			$pathinfo['folderCount']	+= $temp['folderCount'];
			$pathinfo['fileCount']	+= $temp['fileCount'];
			$pathinfo['size'] 		+= $temp['size'];
		}else{
			$pathinfo['fileCount']++;
			$pathinfo['size'] += get_filesize($val['path']);
		}
	}
	$father_name = get_path_father($list[0]['path']);
	$pathinfo['mode'] = get_mode($father_name);
	return $pathinfo;
}

/**
 * 获取文件夹下列表信息
 * dir 包含结尾/   d:/wwwroot/test/
 * 传入需要读取的文件夹路径,为程序编码
 */
function path_list($dir,$listFile=true,$checkChildren=false){
	$dir = rtrim($dir,'/').'/';
	if (!is_dir($dir) || !($dh = @opendir($dir))){
		return array('folderList'=>array(),'fileList'=>array());
	}
	$folderList = array();$fileList = array();//文件夹与文件
	while (($file = readdir($dh)) !== false) {
		if ($file =='.' || $file =='..' || $file == ".svn") continue;
		$fullpath = $dir . $file;
		if (is_dir($fullpath)) {
			$info = folder_info($fullpath);
			if($checkChildren){
				$info['isParent'] = path_haschildren($fullpath,$listFile);
			}
			$folderList[] = $info;
		} else if($listFile) {//是否列出文件
			$info = file_info($fullpath);
			if($checkChildren) $info['isParent'] = false;
			$fileList[] = $info;
		}
	}
	closedir($dh);
	return array('folderList' => $folderList,'fileList' => $fileList);
}

// 判断文件夹是否含有子内容【区分为文件或者只筛选文件夹才算】
function path_haschildren($dir,$checkFile=false){
	$dir = rtrim($dir,'/').'/';
	if (!$dh = @opendir($dir)) return false;
	while (($file = readdir($dh)) !== false){
		if ($file =='.' || $file =='..') continue;
		$fullpath = $dir.$file;
		if ($checkFile) {//有子目录或者文件都说明有子内容
			if(@is_file($fullpath) || is_dir($fullpath.'/')){
				closedir($dh);
				return true;
			}
		}else{//只检查有没有文件
			if(@is_dir($fullpath.'/')){//解决部分主机报错问题
				closedir($dh);
				return true;
			}
		}
	}
	closedir($dh);
	return false;
}

/**
 * 删除文件 传入参数编码为操作系统编码. win--gbk
 */
function del_file($fullpath){
	if (!@unlink($fullpath)) { // 删除不了，尝试修改文件权限
		@chmod($fullpath, 0777);
		if (!@unlink($fullpath)) {
			return false;
		}
	} else {
		return true;
	}
}

/**
 * 删除文件夹 传入参数编码为操作系统编码. win--gbk
 */
function del_dir($dir){
	if(!file_exists($dir) || !is_dir($dir)) return true;
	if (!$dh = opendir($dir)) return false;
	set_timeout();
	while (($file = readdir($dh)) !== false) {
		if ($file =='.' || $file =='..') continue;
		$fullpath = rtrim($dir, '/') . '/' . $file;
		if (!is_dir($fullpath)) {
			if (!@unlink($fullpath)) { // 删除不了，尝试修改文件权限
				@chmod($fullpath, 0777);
				if (!@unlink($fullpath)) {
					return false;
				}
			}
		} else {
			if (!del_dir($fullpath)) {
				@chmod($fullpath, 0777);
				if (!del_dir($fullpath)) return false;
			}
		}
	}
	closedir($dh);
	if (rmdir($dir)) {
		return true;
	} else {
		return false;
	}
}


/**
 * 复制文件夹
 * eg:将D:/wwwroot/下面wordpress复制到
 *	D:/wwwroot/www/explorer/0000/del/1/
 * 末尾都不需要加斜杠，复制到地址如果不加源文件夹名，
 * 就会将wordpress下面文件复制到D:/wwwroot/www/explorer/0000/del/1/下面
 * $from = 'D:/wwwroot/wordpress';
 * $to = 'D:/wwwroot/www/explorer/0000/del/1/wordpress';
 */

function copy_dir($source, $dest){
	if (!$dest) return false;
	if (is_dir($source) && $source == substr($dest,0,strlen($source))) return false;//防止父文件夹拷贝到子文件夹，无限递归

	set_timeout();
	$result = true;
	if (is_file($source)) {
		if ($dest[strlen($dest)-1] == '/') {
			$__dest = $dest . "/" . basename($source);
		} else {
			$__dest = $dest;
		}
		$result = @copy($source,$__dest);
		@chmod($__dest, 0777);
	}else if(is_dir($source)) {
		if ($dest[strlen($dest)-1] == '/') {
			$dest = $dest . basename($source);
		}
		if (!is_dir($dest)) {
			@mkdir($dest,0777);
		}
		if (!$dh = opendir($source)) return false;
		while (($file = readdir($dh)) !== false) {
			if ($file =='.' || $file =='..') continue;
			$result = copy_dir($source . "/" . $file, $dest . "/" . $file);
		}
		closedir($dh);
	}
	return $result;
}

function move_file($source,$dest,$repeat_add,$repeat_type){
	if($source == $dest) return true;
	if ($dest[strlen($dest)-1] == '/') {
		$dest = $dest . "/" . basename($source);
	}
	if(file_exists($dest)){
		$dest = get_filename_auto($dest,$repeat_add,$repeat_type);//同名文件处理规则
	}
	$result = intval(@rename($source,$dest));
	if (! $result) { // windows部分ing情况处理
		$result = intval(@copy($source,$dest));
		if ($result) {
			@unlink($source);
		}
	}
	return $result;
}
function move_path($source,$dest,$repeat_add='',$repeat_type='replace'){
	if (!$dest || !file_exists($source)) return false;
	if ( is_dir($source) ){
		//防止父文件夹拷贝到子文件夹，无限递归
		if($source == substr($dest,0,strlen($source))){
			return false;
		}
		//地址相同
		if(rtrim($source,'/') == rtrim($dest,'/')){
			return false;
		}
	}

	set_timeout();
	if(is_file($source)){
		return move_file($source,$dest,$repeat_add,$repeat_type);
	}
	recursion_dir($source,$dirs,$files,-1,0);

	@mkdir($dest);
	foreach($dirs as $f){
		$path = $dest.'/'.substr($f,strlen($source));
		if(!file_exists($path)){
			mk_dir($path);
		}
	}
	$file_success = 0;
	foreach($files as $f){
		$path = $dest.'/'.substr($f,strlen($source));
		$file_success += move_file($f,$path,$repeat_add,$repeat_type);
	}
	foreach($dirs as $f){
		rmdir($f);
	}
	@rmdir($source);
	if($file_success == count($files)){
		del_dir($source);
		return true;
	}
	return false;
}

/**
 * 创建目录
 *
 * @param string $dir
 * @param int $mode
 * @return bool
 */
function mk_dir($dir, $mode = 0777){
	if (!$dir) return false;
	if (is_dir($dir) || @mkdir($dir, $mode)){
		return true;
	}
	if (!mk_dir(dirname($dir), $mode)){
		return false;
	}
	return @mkdir($dir, $mode);
}

/*
* 获取文件&文件夹列表(支持文件夹层级)
* path : 文件夹 $dir ——返回的文件夹array files ——返回的文件array
* $deepest 是否完整递归；$deep 递归层级
*/
function recursion_dir($path,&$dir,&$file,$deepest=-1,$deep=0){
	$path = rtrim($path,'/').'/';
	if (!is_array($file)) $file=array();
	if (!is_array($dir)) $dir=array();
	if (!is_dir($path)) return false;
	if (!$dh = opendir($path)) return false;
	while(($val=readdir($dh)) !== false){
		if ($val=='.' || $val=='..') continue;
		$value = strval($path.$val);
		if (is_file($value)){
			$file[] = $value;
		}else if(is_dir($value)){
			$dir[]=$value;
			if ($deepest==-1 || $deep<$deepest){
				recursion_dir($value."/",$dir,$file,$deepest,$deep+1);
			}
		}
	}
	closedir($dh);
	return true;
}
function dir_list($path){
	recursion_dir($path,$dirs,$files);
	return array_merge($dirs,$files);
}

/*
 * $search 为包含的字符串
 * is_content 表示是否搜索文件内容;默认不搜索
 * is_case  表示区分大小写,默认不区分
 */
function path_search($path,$search,$is_content=false,$file_ext='',$is_case=false){
	$result = array();
	$result['fileList'] = array();
	$result['folderList'] = array();
	if(!$path) return $result;

	$ext_arr = explode("|",$file_ext);
	recursion_dir($path,$dirs,$files,-1,0);
	$strpos = 'stripos';//是否区分大小写
	if ($is_case) $strpos = 'strpos';
	$result_num = 0;
	$result_num_max = 2000;//搜索文件内容，限制最多匹配条数
	foreach($files as $f){
		if($result_num >= $result_num_max){
			$result['error_info'] = $result_num_max;
			break;
		}
		
		//若指定了扩展名则只在匹配扩展名文件中搜索
		$ext = get_path_ext($f);
		if($file_ext != '' && !in_array($ext,$ext_arr)){
			continue;
		}

		//搜索内容则不搜索文件名
		if ($is_content) {
			if(!is_text_file($ext)) continue; //在限定中或者不在bin中
			$search_info = file_search($f,$search,$is_case);
			if($search_info !== false){
				$result_num += count($search_info['searchInfo']);
				$result['fileList'][] = $search_info;
			}
		}else{
			$path_this = get_path_this($f);
			if ($strpos($path_this,iconv_system($search)) !== false){//搜索文件名;
				$result['fileList'][] = file_info($f);
				$result_num ++;
			}
		}	
	}
	if (!$is_content && $file_ext == '' ) {//没有指定搜索文件内容，且没有限定扩展名，才搜索文件夹
		foreach($dirs as $f){
			$path_this = get_path_this($f);
			if ($strpos($path_this,iconv_system($search)) !== false){
				$result['folderList'][]= array(
					'name'  => iconv_app(get_path_this($f)),
					'path'  => iconv_app($f)
				);
			}
		}
	}
	return $result;
}

// 文件搜索；返回行及关键词附近行
function file_search($path,$search,$is_case){
	if(@filesize($path) >= 1024*1024*20) return false;
	$content = file_get_contents($path);
	$result  = content_search($content,$search,$is_case);
	unset($content);
	if(!$result) return false;

	$info = file_info($path);
	$info['searchInfo'] = $result;
	return $info;
}

// 文本搜索；返回行及关键词附近行
function content_search($content,$search,$is_case){
	$strpos = 'stripos';//是否区分大小写
	if( $is_case) $strpos = 'strpos';
	if( $strpos($content,"\0") > 0 ){// 不是文本文档
		return false;
	}
	$charset = get_charset($content);
	//搜索关键字为纯英文则直接搜索；含有中文则转为utf8再搜索，为兼容其他文件编码格式
	$notAscii = preg_match("/[\x7f-\xff]/", $search);
	if($notAscii && !in_array($charset,array('utf-8','ascii'))){
		$content = iconv_to($content,$charset,'utf-8');
	}
	//文件没有搜索到目标直接返回
	if ($strpos($content,$search) === false) return false;

	$pose = 0; 
	$fileSize = strlen($content);
	$arr_search = array(); // 匹配结果所在位置
	while ( $pose !== false) {
		$pose = $strpos($content,$search, $pose);
		if($pose !== false){
			$arr_search[] = $pose;
			$pose ++;
		}else{
			break;
		}
	}

	$arr_line = array();
	$pose = 0;
	while ( $pose !== false) {
		$pose = strpos($content, "\n", $pose);
		if($pose !== false){
			$arr_line[] = $pose;
			$pose ++;
		}else{
			break;
		}
	}
	$arr_line[] = $fileSize;//文件只有一行而且没有换行，则构造虚拟换行
	$result = array();//  [2,10,22,45,60]  [20,30,40,50,55]
	$len_search = count($arr_search);
	$len_line 	= count($arr_line);
	for ($i=0,$line=0; $i < $len_search && $line < $len_line; $line++) {
		while ( $arr_search[$i] <= $arr_line[$line]) {
			//行截取字符串
			$cur_pose = $arr_search[$i];
			$from = $line == 0 ? 0:$arr_line[$line-1];
			$to = $arr_line[$line];
			$len_max = 300;
			if( $to - $from >= $len_max){ //长度过长处理
				$from = $cur_pose - 20;
				$from = $from <= 0 ? 0 : $from;
				$to   = $from + $len_max;
				//中文避免截断；（向前 向后找到分隔符后终止）
				$token = array("\r","\n"," ","\t",",","/","#","_","[","]","(",")","+","-","*","/","=","&");
				while (!in_array($content[$from],$token) && $from >= 0) {
					$from -- ;
				}
				while (!in_array($content[$to],$token) && $to <= $fileSize) {
					$to ++ ;
				}
			}
			$line_str = substr($content,$from,$to - $from);
			if($strpos($line_str,$search) === false){ //截取乱码避免
				$line_str = $search;
			}

			$result[] = array('line'=>$line+1,'str'=>$line_str);
			if(++$i >= $len_search ){
				break;
			}
		}
	}
	return $result;
}


/**
 * 修改文件、文件夹权限
 * @param  $path 文件(夹)目录
 * @return :string
 */
function chmod_path($path,$mod=0777){
	if (!isset($mod)) $mod = 0777;
	if (!file_exists($path)) return false;
	if (is_file($path)) return @chmod($path,$mod);
	if (!$dh = @opendir($path)) return false;
	while (($file = readdir($dh)) !== false){
		if ($file =='.' || $file =='..') continue;
		$fullpath = $path . '/' . $file;
		chmod_path($fullpath,$mod);
		@chmod($fullpath,$mod);
	}
	closedir($dh);
	return @chmod($path,$mod);
}

/**
 * 文件大小格式化
 *
 * @param  $ :$bytes, int 文件大小
 * @param  $ :$precision int  保留小数点
 * @return :string
 */
function size_format($bytes, $precision = 2){
	if ($bytes == 0) return "0 B";
	$unit = array(
		'PB' => 1099511627776*1024,  // pow( 1024, 5)
		'TB' => 1099511627776,  	 // pow( 1024, 4)
		'GB' => 1073741824,			 // pow( 1024, 3)
		'MB' => 1048576,			 // pow( 1024, 2)
		'kB' => 1024,				 // pow( 1024, 1)
		'B ' => 1,					 // pow( 1024, 0)
	);
	foreach ($unit as $un => $mag) {
		if (doubleval($bytes) >= $mag)
			return round($bytes / $mag, $precision).' '.$un;
	}
}

/**
 * 判断路径是不是绝对路径
 * 返回true('/foo/bar','c:\windows').
 *
 * @return 返回true则为绝对路径，否则为相对路径
 */
function path_is_absolute($path){
	if (realpath($path) == $path)// *nux 的绝对路径 /home/my
		return true;
	if (strlen($path) == 0 || $path[0] == '.')
		return false;
	if (preg_match('#^[a-zA-Z]:\\\\#', $path))// windows 的绝对路径 c:\aaa\
		return true;
	return (bool)preg_match('#^[/\\\\]#', $path); //绝对路径 运行 / 和 \绝对路径，其他的则为相对路径
}


function is_text_file($ext){
	$extArray = array(
		'3ds','4th','_adb','a','abap','abc','ac','acl','ada','adb','adoc','ahk','alda','am','apex','apl','app','apple-app-site-association','applescript','aql','arcconfig','arclint','as','asc','asciidoc','asl','asm','asn','asn1','asp','aspx','ass','astylerc','atom','authors','aw',
		'b','babelrc','bak','bash','bash_history','bash_logout','bash_profile','bashrc','bat','bf','bib','brew_all_commands','bro','build','bzl',
		'c','c9search_results','cabal','cakefile','cbl','cc','cer','cf','cfg','cfm','cgi','changelog','changes','cirru','cl','classpath','clj','cljc','cljs','cljx','cls','cmake','cmake.in','cmd','cnf','cob','coffee','commit_editmsg','compile','component','conf','config','configure','container','contributing','copying','coveragerc','cpp','cpy','cql','cr','credits','cs','csd','cshtml','cson','csproj','css','csv','ctp','curly','cxx','cyp','cypher',
		'd','dae','darglint','dart','def','depcomp','description','desktop','di','diff','dist','dockerfile','dockerfile-dist','dockerfile-master','dockerignore','dot','dox','drl','dsl','dtd','dummy','dxf','dxf-check','dxfb-check','dyalog','dyl','dylan',
		'e','ecl','edi','editorconfig','edn','eex','ejs','el','elm','empty','epp','erb','erl','err','eslintignore','ex','example','exclude','exs',
		'f','f77','f90','f95','factor','feature','fetch_head','filters','fingerprint','for','forth','frag','frt','fs','fsi','fsl','fsscript','fsx','fth','ftl','fun','fx',
		'gbs','gcode','ge','gemfile','gemspec','gendocs_template','geojson','git-credentials','git-version-gen','gitattributes','gitconfig','gitflow_export','gitignore','gitignore_global','gitkeep','gitlog-to-changelog','gitmodules','glsl','gltf','gnumakefile','go','gql','gradle','groovy','gss','guardfile','guess','gunmakefile','gypi',
		'h','hacking','haml','handlebars','hbs','head','hgignore_global','hh','hjson','hlean','hpp','hrl','hs','hta','htaccess','htgroups','htm','html','html.eex','html.erb','htpasswd','http','hx','hxml','hxx',
		'i','iml','in','inc','inf','ini','ino','install','install-sh','installversion','intr','inx','io',
		'j2','jack','jade','java','ji','jinja','jinja2','jl','jq','js','jsdtscope','jshintrc','jsm','json','json-check','json5','jsonld','jsp','jssm','jssm_state','jsx',
		'key','keys','kml','ksh','kt','kts',
		'la','latex','latte','ldr','lean','less','lesshst','lgc','lhs','license','liquid','lisp','list','lnk','local','localized','lock','log','logic','lp','lql','lrc','ls','lsl','lsp','ltx','lua','lucene',
		'm','m4','magnet','mailcap','make','makefile','manifest','map','markdown','mask','master','mathml','matlab','mbox','mc','md','mediawiki','mel','meta','mf','mime','missing','mixal','mjs','mkd','ml','mli','mll','mly','mm','mml','mo','mod','module','mps','msc','mscgen','mscin','msgenny','mtl','mush','mustache','mvnw','mycli-history','myclirc','mymetadata','mysql','mysql_history','mz',
		'name','nb','nc','ncx','netrwhist','news','nginx','nim','nix','nj','njk','nmf','node_repl_history','npmignore','npmrc','nq','nsh','nsi','nt','nunjs','nunjucks','nut',
		'oak','obj','ocamlmakefile','oexe','opf','orc','orig_head','out','owners','oz',
		'p','p6','packed-refs','packs','page','pas','patch','pbxproj','pc','pch','pearrc','pem','pgp','pgsql','php','php3','php4','php5','php7','phps','phpt','phtml','pid','pig','pl','pl6','plantuml','plg','plist','plistpch','pls','plugins','ply','pm','pm6','pp','praat','praatscript','prefs','prettierrc','pri','prisma','pro','proc','project','prolog','properties','props','proto','ps1','psc','psd1','psm1','pub','pug','puml','pxd','pxi','py','pylintrc','pyw','pyx',
		'q','qml','qrc',
		'r','rake','rakefile','raku','rakumod','rakutest','rb','rd','rdf','readme','red','rediscli_history','reds','refs','reg','rels','repo','resx','rhtml','rkt','rng','rq','rs','rss','rst','ru',
		's','sample','sas','sass','sbt','scad','scala','schema','scheme','scm','sco','scss','servers','settings','sh','sh_history','sharedmimeinfo','shtml','sieve','sig','siv','sjs','skim','slim','sln','sm','smackspec','smarty','smithy','sml','snippets','sourcetreeconfig','soy','space','sparql','spec','sql','sqlite_history','sqlserver','srt','ss','st','status','stcommitmsg','stl','storyboard','str','strings','styl','stylus','sub','sublime-project','sum','supp','sv','svg','svh','swift','swig',
		't','targets','tcl','template','tern-project','terragrunt','tex','texi','text','textile','tf','tfvars','tgr','tld','todo','toml','tpl','trigger','ts','tsv','tsx','ttcn','ttcn3','ttcnpp','ttl','twig','txt','typed','types','typescript',
		'ui','url','using_foreign_code',
		'v','vala','values','vb','vbproj','vbs','vcproj','vcxproj','version','vert','vfp','vh','vhd','vhdl','viminfo','vm','vmx','vmxd','vmxf','vsixmanifest','vtl','vtt','vue',
		'wast','wat','we','webapp','webidl','webloc','wiki','wl','wlk','wls','wpgm','wpy','wsdl','wtest',
		'x3d','xaml','xbl','xcscheme','xhtml','xib','xml','xq','xquery','xsd','xsl','xslt','xu','xul','xy',
		'yaml','yml','ys','z80',
		'zeek','zsh','zsh-template','zsh-theme','zsh-update','zsh_history','zshrc','zshrc_self',
	);
	return in_array($ext,$extArray);
}

/**
 * 远程文件下载到服务器
 * 支持fopen的打开都可以；支持本地、url
 */
function file_download_this($from, $fileName,$headerSize=0){
	set_timeout();
	$fileTemp = $fileName.'.downloading';
	if ($fp = @fopen ($from, "rb")){
		if(!$downloadFp = @fopen($fileTemp, "wb")){
			return false;
		}
		while(!feof($fp)){
			if(!file_exists($fileTemp)){//删除目标文件；则终止下载
				fclose($downloadFp);
				return false;
			}
			//对于部分fp不结束的通过文件大小判断
			clearstatcache();
			if( $headerSize>0 &&
				$headerSize==get_filesize(iconv_system($fileTemp))
				){
				break;
			}
			fwrite($downloadFp, fread($fp, 1024 * 200 ), 1024 * 200);
		}
		//下载完成，重命名临时文件到目标文件
		fclose($downloadFp);
		fclose($fp);
		if(!@rename($fileTemp,$fileName)){
			unlink($fileName);
			return rename($fileTemp,$fileName);
		}
		return true;
	}else{
		return false;
	}
}

/**
 * 获取文件(夹)权限 rwx_rwx_rwx
 */
function get_mode($file){
	$Mode = @fileperms($file);
	$theMode = ' '.decoct($Mode);
	$theMode = substr($theMode,-4);
	$Owner = array();$Group=array();$World=array();
	if ($Mode &0x1000) $Type = 'p'; // FIFO pipe
	elseif ($Mode &0x2000) $Type = 'c'; // Character special
	elseif ($Mode &0x4000) $Type = 'd'; // Directory
	elseif ($Mode &0x6000) $Type = 'b'; // Block special
	elseif ($Mode &0x8000) $Type = '-'; // Regular
	elseif ($Mode &0xA000) $Type = 'l'; // Symbolic Link
	elseif ($Mode &0xC000) $Type = 's'; // Socket
	else $Type = 'u'; // UNKNOWN
	// Determine les permissions par Groupe
	$Owner['r'] = ($Mode &00400) ? 'r' : '-';
	$Owner['w'] = ($Mode &00200) ? 'w' : '-';
	$Owner['x'] = ($Mode &00100) ? 'x' : '-';
	$Group['r'] = ($Mode &00040) ? 'r' : '-';
	$Group['w'] = ($Mode &00020) ? 'w' : '-';
	$Group['e'] = ($Mode &00010) ? 'x' : '-';
	$World['r'] = ($Mode &00004) ? 'r' : '-';
	$World['w'] = ($Mode &00002) ? 'w' : '-';
	$World['e'] = ($Mode &00001) ? 'x' : '-';
	// Adjuste pour SUID, SGID et sticky bit
	if ($Mode &0x800) $Owner['e'] = ($Owner['e'] == 'x') ? 's' : 'S';
	if ($Mode &0x400) $Group['e'] = ($Group['e'] == 'x') ? 's' : 'S';
	if ($Mode &0x200) $World['e'] = ($World['e'] == 'x') ? 't' : 'T';
	$Mode = $Type.$Owner['r'].$Owner['w'].$Owner['x'].' '.
			$Group['r'].$Group['w'].$Group['e'].' '.
			$World['r'].$World['w'].$World['e'];
	return $Mode.'('.$theMode.')';
}

function path_clear($path){
	$path = str_replace('\\','/',trim($path));
	$path = preg_replace('/\/+/', '/', $path);
	if (strstr($path,'../')) {
		$path = preg_replace('/\/\.+\//', '/', $path);
	}
	return $path;
}
function path_clear_name($path){
	$path = str_replace('\\','/',trim($path));
	$path = str_replace('/','.',trim($path));
	return $path;
}

/**
 * 写日志
 * @param string $log   日志信息
 * @param string $type  日志类型 [system|app|...]
 * @param string $level 日志级别
 * @return boolean
 */
function write_log($log, $type = 'default', $level = 'log'){
	if(!defined('LOG_PATH')){
		return;
	}
	list($usec, $sec) = explode(' ', microtime());
	$now_time = date('[H:i:s.').substr($usec,2,3).'] ';
	$target   = LOG_PATH . strtolower($type) . '/';
	mk_dir($target);
	if (!path_writeable($target)){
		return;// 日志写入失败不处理;
		exit('path can not write! ['.$target.']');
	}
	$ext = '.php';//.php .log;
	$target .= date('Y_m_d').'__'.$level.$ext;
	//检测日志文件大小, 超过配置大小则重命名
	if (file_exists($target) && get_filesize($target) >= 1024*1024*5) {
		$fileName = substr(basename($target),0,strrpos(basename($target),$ext)).date('H-i-s').$ext;
		@rename($target, dirname($target) .'/'. $fileName);
	}
	if(!file_exists($target)){
		error_log("<?php exit;?>\n", 3,$target);
	}

	if(is_object($log) || is_array($log)){
		$log = json_encode_force($log);
		$log = str_replace(array('\\n','\\r','\\t','\\"','\\\'','\/'),array("\n","\r","\t",'"',"'",'/'),$log);
	}
	clearstatcache();
	return error_log("$now_time $log\n", 3, $target);
}