<?php
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/

// 自动类加载路径添加；$addPath为false则不添加；
function import($path=false){
	static $_autoLoaderPath;
	if(empty($_autoLoaderPath)) {
		$_autoLoaderPath = array(
			MODEL_DIR,
			CORER_DIR,
			CLASS_DIR,
			SDK_DIR,
			CORER_DIR.'Cache/',
			CORER_DIR.'DB/',
			CORER_DIR.'IO/',
			CORER_DIR.'Task/',
			CORER_DIR.'Backup/',
		);
		if(!is_dir(MODEL_DIR)){
			$_autoLoaderPath = array(CLASS_DIR,SDK_DIR);
		}
	}
	if($path){
		if(is_dir($path)){
			$_autoLoaderPath[] = rtrim($path,'/').'/';
		}else if( is_file($path) ){		
			return include_once $path;
		}
	}	
	return $_autoLoaderPath;
}
// 类自动加载；通过autoLoaderPath的路径；
import(SDK_DIR.'archiveLib/');

function myAutoloader($name) {
	static $_cacheError = array();
	if(isset($_cacheError[$name])){
		return $_cacheError[$name];
	}
	$find   = import();
	$result = false;
	foreach ($find as $path) {
		$result = classPathAuto($path,$name);
		if($result) break;
	}
	if($result){
		include_once ($result);
		return true;
	}
	$_cacheError[$name] = false;
	// write_log(array($name,$result,$find),'test');
	return false;
}
spl_autoload_register('myAutoloader', true, true);

// 自动获取类文件名；兼容文件路径大小写敏感的情况（linux环境）
function classPathAuto($path,$name,$fileLast = '.class.php'){
	$path = rtrim($path,'/').'/';
	$nameUpper = strtoupper(substr($name,0,1)).substr($name,1);	
	$nameLower = strtolower($name);
	$result = false;
	// pr($path.$name.$fileLast);
	if(file_exists($path.$name.$fileLast)){
		$result = $path.$name.$fileLast;
	}else if(file_exists($path.$nameLower.$fileLast)){
		$result = $path.$nameLower.$fileLast;
	}else if(file_exists($path.$nameUpper.$fileLast)){
		$result = $path.$nameUpper.$fileLast;
	}
	return $result;
}


/**
 * 文本字符串转换
 */
function mystr($str){
	$from = array("\r\n", " ");
	$to = array("<br/>", "&nbsp");
	return str_replace($from, $to, $str);
} 

// 清除多余空格和回车字符
function strip($str){
	return preg_replace('!\s+!', '', $str);
}
function timeFloat(){
	return microtime(true);
}

// 删除字符串两端的字符串
function str_trim($str,$remove){
	return str_rtrim(str_ltrim($str,$remove),$remove);
}
function str_ltrim($str,$remove){
	if(!$str || !$remove) return $str;
	while(substr($str,0,strlen($remove)) == $remove){
		$str = substr($str,strlen($remove));
	}
	return $str;
}
function str_rtrim($str,$remove){
	if(!$str || !$remove) return $str;
	while(substr($str,-strlen($remove)) == $remove){
		$str = substr($str,0,-strlen($remove));
	}
	return $str;
}

/**
 * 根据id获取短标识; 
 * 加入时间戳避免猜测;id不可逆
 * 
 * eg: 234==>4JdQ9Lgw;  100000000=>4LyUC2xQ
 */
function short_id($id){
	$id = intval($id) + microtime(true)*10000;
	$id = pack('H*',base_convert($id,10,16));
	$base64  = base64_encode($id);
    $replace = array('/'=>'_','+'=>'-','='=>'');
    return strtr($base64, $replace);
}

/**
 * 获取精确时间
 */
function mtime(){
	$t= explode(' ',microtime());
	$time = $t[0]+$t[1];
	return $time;
}

function guid(){
	return md5(microtime(true).rand_string(20));
}

/**
 * 过滤HTML
 * 
 * eg: </script><script>alert(1234)</script>
 * 允许url中字符;
 */
function clear_html($html, $br = true){
	$html = $html === null ? "" : $html;
	$replace = array('<','>','"',"'");
	$replaceTo = array('&lt;','&gt;','&quot;','&#39;');
	return str_replace($replace,$replaceTo,$html);
}
function clear_quote($html){
	$html = $html === null ? "" : $html;
	$replace = array('"',"'",'</script');
	$replaceTo = array('\\"',"\\'","<\/script");	
	return str_ireplace($replace,$replaceTo,$html);
}

// 反序列化攻击防护,不允许类对象;
function unserialize_safe($str){
	if(!$str) return false;
	if(preg_match("/O:(\d+):/",$str,$match)) return false;
	return unserialize($str);
}

/**
 * 过滤js、css等 
 */
function filter_html($html){
	$find = array(
		"/<(\/?)(script|i?frame|style|html|body|title|link|meta|\?|\%)([^>]*?)>/isU",
		"/(<[^>]*)on[a-zA-Z]+\s*=([^>]*>)/isU",
		"/javascript\s*:/isU",
	);
	$replace = array("＜\\1\\2\\3＞","\\1\\2","");
	return preg_replace($find,$replace,$html);
}

function equal_not_case($str,$str2) {
	return strtolower($str) === strtolower($str2);
}
function in_array_not_case($needle, $haystack) {
	return in_array(strtolower($needle),array_map('strtolower',$haystack));
}

/**
 * 将obj深度转化成array
 * 
 * @param  $obj 要转换的数据 可能是数组 也可能是个对象 还可能是一般数据类型
 * @return array || 一般数据类型
 */
function obj2array($obj){
	if (is_array($obj)) {
		foreach($obj as &$value) {
			$value = obj2array($value);
		};unset($value);
		return $obj;
	} elseif (is_object($obj)) {
		$obj = get_object_vars($obj);
		return obj2array($obj);
	} else {
		return $obj;
	}
}


// 主动输出内容维持检测;(用户最终show_json情况; 文件下载情况不适用); 
// 没有输出时,php-fpm情况下,connection_aborted判断不准确
function check_abort_echo(){
	static $lastTime = 0;
	if(isset($GLOBALS['ignore_abort']) && $GLOBALS['ignore_abort'] == 1) return;
	
	// 每秒输出2次; 
	if(timeFloat() - $lastTime >= 0.5){
		ob_end_flush();echo str_pad('',1024*5);flush();
		$lastTime = timeFloat();
	}
	// write_log(connection_aborted().';'.connection_status(),'check_abort');
	if(connection_aborted()){write_log(get_caller_msg(),'abort');exit;}
}

function check_abort(){
	if(isset($GLOBALS['ignore_abort']) && $GLOBALS['ignore_abort'] == 1) return;
	if(connection_aborted()){write_log(get_caller_msg(),'abort');exit;}
}
function check_aborted(){
	// connection_aborted();
	exit;
}
function ignore_timeout(){
	$GLOBALS['ignore_abort'] = 1;
	@ignore_user_abort(true);
	set_timeout();
}

// 48 * 60 * 60
function set_timeout($timeout=172800){
	static $firstRun = false;
	if($firstRun) return; //避免重复调用; 5000次100ms;
	$firstRun = true;
	
	@ini_set("max_execution_time",$timeout);
	@ini_set('request_terminate_timeout',$timeout);
	@set_time_limit($timeout);
	@ini_set('memory_limit', '4000M');//4G;
}


function check_code($code){
	ob_clean();
	header("Content-type: image/png");
	$width = 70;$height=27;
	$fontsize = 18;$len = strlen($code);
	$im = @imagecreatetruecolor($width, $height) or die("create image error!");
	$background_color = imagecolorallocate($im,255, 255, 255);
	imagefill($im, 0, 0, $background_color);  
	for ($i = 0; $i < 2000; $i++) {//获取随机淡色            
		$line_color = imagecolorallocate($im, mt_rand(180,255),mt_rand(160, 255),mt_rand(100, 255));
		imageline($im,mt_rand(0,$width),mt_rand(0,$height), //画直线
			mt_rand(0,$width), mt_rand(0,$height),$line_color);
		imagearc($im,mt_rand(0,$width),mt_rand(0,$height), //画弧线
			mt_rand(0,$width), mt_rand(0,$height), $height, $width,$line_color);
	}
	$border_color = imagecolorallocate($im, 160, 160, 160);   
	imagerectangle($im, 0, 0, $width-1, $height-1, $border_color);//画矩形，边框颜色200,200,200
	for ($i = 0; $i < $len; $i++) {//写入随机字串
		$text_color = imagecolorallocate($im,mt_rand(30, 140),mt_rand(30,140),mt_rand(30,140));
		imagechar($im,10,$i*$fontsize+6,rand(1,$height/3),$code[$i],$text_color);
	}
	imagejpeg($im);//显示图
	imagedestroy($im);//销毁图片
}


/**
 * 计算N次方根
 * @param  $num 
 * @param  $root 
 */
function croot($num, $root = 3){
	$root = intval($root);
	if (!$root) {
		return $num;
	} 
	return exp(log($num) / $root);
} 

function add_magic_quotes($array){
	foreach ((array) $array as $k => $v) {
		if (is_array($v)) {
			$array[$k] = add_magic_quotes($v);
		} else {
			$array[$k] = addslashes($v);
		} 
	} 
	return $array;
} 
// 字符串加转义
function add_slashes($string){
	if (!$GLOBALS['magic_quotes_gpc']) {
		if (is_array($string)) {
			foreach($string as $key => $val) {
				$string[$key] = add_slashes($val);
			} 
		} else {
			$string = addslashes($string);
		} 
	} 
	return $string;
} 

/**
 * hex to binary
 */
if (!function_exists('hex2bin')) {
	function hex2bin($hexdata)	{
		return pack('H*', $hexdata);
	}
}

if (!function_exists('gzdecode')) {
	function gzdecode($data){
		return gzinflate(substr($data,10,-8));
	}
}

function xml2json($decodeXml){
	$data = simplexml_load_string($decodeXml,'SimpleXMLElement', LIBXML_NOCDATA);
	return json_decode(json_encode($data),true);
}

/**
 * XML编码
 * @param mixed $data 数据
 * @param string $root 根节点名
 * @param string $item 数字索引的子节点名
 * @param string $attr 根节点属性
 * @param string $id   数字索引子节点key转换的属性名
 * @param string $encoding 数据编码
 * @return string
 */
function xml_encode($data, $root = 'think', $item = 'item', $attr = '', $id = 'id', $encoding = 'utf-8') {
    if (is_array($attr)) {
        $_attr = array();
        foreach ($attr as $key => $value) {
            $_attr[] = "{$key}=\"{$value}\"";
        }
        $attr = implode(' ', $_attr);
    }
    $attr = trim($attr);
    $attr = empty($attr) ? '' : " {$attr}";
    $xml = "<?xml version=\"1.0\" encoding=\"{$encoding}\"?>";
    $xml .= "<{$root}{$attr}>";
    $xml .= data_to_xml($data, $item, $id);
    $xml .= "</{$root}>";
    return $xml;
}

/**
 * 数据XML编码
 * @param mixed  $data 数据
 * @param string $item 数字索引时的节点名称
 * @param string $id   数字索引key转换为的属性名
 * @return string
 */
function data_to_xml($data, $item = 'item', $id = 'id') {
    $xml = $attr = '';
    foreach ($data as $key => $val) {
        if (is_numeric($key)) {
            $id && $attr = " {$id}=\"{$key}\"";
            $key = $item;
        }
        $xml .= "<{$key}{$attr}>";
        $xml .= (is_array($val) || is_object($val)) ? data_to_xml($val, $item, $id) : $val;
        $xml .= "</{$key}>";
    }
    return $xml;
}

/**
 * 数组key连接的值设置
 * $arr  data.list.order  <==> $arr['data']['list']['order']
 */
function array_set_value(&$array,$key,$value){
	if(!is_array($array)) {
		$array = array();
	}
	$keyArr  = explode(".",$key);
	$tempArr = &$array;
	$count   = count($keyArr);
	for ($i=0; $i < $count; $i++) {
		$k = $keyArr[$i];
		if($i+1 == $count ){
			$tempArr[$k] = $value;
			return true;
		}
		if(!isset($tempArr[$k])){
			$tempArr[$k] = array();
		}
		$tempArr = &$tempArr[$k];
		if(!is_array($tempArr)){//有值并且不是数组的情况
			return false;
		}
	}
	return false;
}

function _get($array,$key,$default=null){
	return array_get_value($array,$key,$default);
}

/**
 * 数组key连接的值获取
 * $arr  data.list.order  <==> $arr['data']['list']['order']
 */
function array_get_value(&$array,$key,$default=null){
	if(!is_array($array)) return $default;
	if(isset($array[$key])) return $array[$key];
	if(strpos($key,'.') === false){
		return isset($array[$key]) ? $array[$key] : $default;
	}
	
	$keyArr  = explode(".",$key);
	$tempArr = $array;
	$total   = count($keyArr);
	for ($i=0; $i < $total; $i++) {
		$k = $keyArr[$i];
		if(!isset($tempArr[$k])) return $default;
		if( $i+1 == $total ){
			return $tempArr[$k];
		}
		$tempArr = $tempArr[$k];
	}
	return $default; 
}

/**
 * 二维数组按照指定的键值进行排序，默认升序
 * 
 * @param  $keys 根据键值
 * @param  $type 升序降序 默认升序
 * @return array 
 * $array = array(
 * 		array('name'=>'手机','brand'=>'诺基亚','price'=>1050),
 * 		array('name'=>'手表','brand'=>'卡西欧','price'=>960)
 * );
 * $out = array_sort_by($array,'price');
 * $out = array_sort_by($array,'info.count');
 */
function array_sort_by($records, $field, $sortDesc=false){
	if(!is_array($records) || !$records) return array();
	$sortDesc = $sortDesc?SORT_DESC:SORT_ASC;
	$recordsPicker = array();
	foreach ($records as $item) {
		$recordsPicker[] = isset($item[$field]) ? $item[$field]: _get($item,$field,0);
	}
	array_multisort($recordsPicker,$sortDesc,$records);
	return $records;
}

if (!function_exists('array_column')) {
    function array_column($array, $column_key, $index_key = null) {
        $column_key_isNumber = (is_numeric($column_key)) ? true : false;
        $index_key_isNumber  = (is_numeric($index_key)) ? true : false;
        $index_key_isNull    = (is_null($index_key)) ? true : false;
        $result = array();
        foreach((array)$array as $key=>$val){
            if($column_key_isNumber){
                $tmp = array_slice($val, $column_key, 1);
                $tmp = (is_array($tmp) && !empty($tmp)) ? current($tmp) : null;
            } else {
                $tmp = isset($val[$column_key]) ? $val[$column_key] : null;
            }
            if(!$index_key_isNull){
                if($index_key_isNumber){
                    $key = array_slice($val, $index_key, 1);
                    $key = (is_array($key) && !empty($key)) ? current($key) : null;
                    $key = is_null($key) ? 0 : $key;
                }else{
                    $key = isset($val[$index_key]) ? $val[$index_key] : 0;
                }
            }
            $result[$key] = $tmp;
        }
        return $result;
    }
}


/**
 * 遍历数组，对每个元素调用 $callback，假如返回值不为假值，则直接返回该返回值；
 * 假如每次 $callback 都返回假值，最终返回 false
 * 
 * @param  $array 
 * @param  $callback 
 * @return mixed 
 */
function array_try($array, $callback){
	if (!$array || !$callback) {
		return false;
	} 
	$args = func_get_args();
	array_shift($args);
	array_shift($args);
	if (!$args) {
		$args = array();
	} 
	foreach($array as $v) {
		$params = $args;
		array_unshift($params, $v);
		$x = call_user_func_array($callback, $params);
		if ($x) {
			return $x;
		} 
	} 
	return false;
} 

// 取出数组中第n项
function array_get_index($array,$index){
	foreach($array as $k=>$v){
		$index--;
		if($index<0) return array($k,$v);
	}
}

/**
 * 根据数组子项去重
 */
function array_unique_by_key($array,$key){
	$array = array_to_keyvalue($array,$key);
	return array_values($array);
}

// 多条数组筛选子元素：获取多条数组中项的某个字段构成值数组
function array_field_values($array,$field){
	$result = array();
	if(!is_array($array) || !$array) return $result;
	foreach ($array as $val) {
		if(is_array($val) && isset($val[$field])){
			$result[] = $val[$field];
		}		
	}
	return $result;
}

// 单条数据筛选key：只筛选返回数组中，指定key的部分
function array_field_key($array,$field){
	$result = array();
	if(!is_array($array) || !$array) return $result;
	foreach ($field as $val) {
		if(isset($array[$val])){
			$result[$val] = $array[$val];
		}
	}
	return $result;
}

// 多条数组筛选：筛选项中key为$field,值为$field的部分；
function array_filter_by_field($array,$field,$value){
	$result = array();
	if(!is_array($array) || !$array) return $result;
	foreach ($array as $key => $val) {
		if($val[$field] == $value){
			if(is_int($key)){
				$result[] = $val;
			}else{
				$result[$key] = $val;
			}
		}
	}
	return $result;
}
function array_find_by_field($array,$field,$value){
	if(!is_array($array) || !$array) return null;
	foreach ($array as $val) {
		if($val[$field] == $value) return $val;
	}
	return null;
}

function array_page_split($array,$page=false,$pageNum=false){
	$page 		= intval($pageNum ? $pageNum : $GLOBALS['in']['page']);
	$page		= $page <= 1 ? 1 : $page;
	$pageMax    = 5000;
	$pageNum 	= intval($pageNum ? $pageNum : $GLOBALS['in']['pageNum']);
	$pageNum	= $pageNum <= 5 ? 5 : ($pageNum > $pageMax ? $pageMax: $pageNum);
	
	$array 		= is_array($array) ? $array :array();
	$pageTotal	= ceil(count($array) / $pageNum);
	$page		= $page <= 1 ? 1  : ($page >= $pageTotal ? $pageTotal : $page);
	$result     = array(
		'pageInfo' => array(
			'totalNum'	=> count($array),
			'pageNum'	=> $pageNum,
			'page'		=> $page,
			'pageTotal'	=> $pageTotal,
		),
		'list' => array_slice($array,($page-1) * $pageNum,$pageNum)
	);
	return $result;	
}

/**
 * 删除数组子项的特定key的数据
 * $keyArray 单个key字符串，或多个key组成的数组；
 * eg: [{a:1,b:2},{a:33,b:44}]  ==> [{a:1},{a:33}]
 */
function array_remove_key(&$array, $keyArray){
	if(!is_array($array) || !$array) return array();
	if(!is_array($keyArray)) $keyArray = array($keyArray);
	foreach ($array as &$item) {
		foreach ($keyArray as $key) {
			if(isset($item[$key])){
				unset($item[$key]);
			}
		}
	};unset($item);
	return $array;
}

// 删除数组某个值
function array_remove_value($array, $value){
	if(!is_array($array) || !$array) return array();
	$isNumericArray = true;
	foreach ($array as $key => $item) {
		if ($item === $value) {
			if (!is_int($key)) {
				$isNumericArray = false;
			}
			unset($array[$key]);
		}
	}
	if ($isNumericArray) {
		$array = array_values($array);
	}
	return $array;
}

// 获取数组key最大的值
function array_key_max($array){
	if(count($array)==0){
		return 1;
	}
	$idArr = array_keys($array);
	rsort($idArr,SORT_NUMERIC);//id从高到底
	return intval($idArr[0]);
}

/**
 * 将纯数组的元素的某个key作为key生成新的数组
 * [{"id":34,"name":"dd"},{"id":78,"name":"dd"}]

 * (arr,'id')=>{34:{"id":34,"name":"dd"},78:{"id":78,"name":"dd"}}  //用元素key的值作为新数组的key
 * (arr,'id','name')=>{34:"dd",78:"name"}	//用元素key的值作为新数组的key，只取对应$contentKey的值作为内容；
 * (arr,'','name')=>["dd","name"]  			//只取数组元素的特定项构成新的数组
 */
function array_to_keyvalue($array,$key='',$contentKey=false){
	$result = array();
	if(!is_array($array) || !$array) return $result;
	foreach ($array as $item) {
		$theValue = $item;
		if($contentKey){
			$theValue = $item[$contentKey];
		}
		if($key){
			$result[$item[$key].''] = $theValue;
		}elseif(!is_null($theValue)){
			$result[] = $theValue;
		}
	}
	return $result;
}

/**
 * 根据parentid获取家谱树列表
 * @param [type] $array
 * @param [type] $id
 * @param string $idKey		// id名称
 * @param string $pidKey	// pid名称
 * @return void
 */
function array_tree($array, $id, $idKey = 'id', $pidKey = 'parentid'){
	$tree = array();
	while($id != '0'){
		foreach ($array as $v) {
			if(!isset($v[$pidKey])) $v[$pidKey] = '0';
			if($v[$idKey] == $id){
				$tree[] = $v;
				$id = $v[$pidKey];
				break;
			}
		}
	}
	return $tree;
}

/**
 * 迭代获取子孙树
 * @param [type] $rows
 * @param string $pid
 * @return void
 */
function array_sub_tree($rows, $pid = 'parentid', $id = 'id') {
	$rows = array_column ( $rows, null, $id );
	foreach ( $rows as $key => $val ) {
		if ( $val[$pid] ) {
			if ( isset ( $rows[$val[$pid]] )) {
				$rows[$val[$pid]]['children'][$val[$id]] = &$rows[$key];
			}
		}
	}
	foreach ( $rows as $key => $val ) {
		if ( $val[$pid] ) unset ( $rows[$key] );	// 根元素的parentid需为空
	}
	return $rows;
}

/**
 * 将数组的元素的某个key作为key生成新的数组;值为数组形式；
 * [{"id":34,"type":1},{"id":78,"type":2},{"id":45,"type":1}]

 * (arr,'type')=>{1:[{"id":34,"name":"dd"},{"id":45,"type":1}],2:[{"id":78,"name":"dd"}]}
 * (arr,'name','name')=>{1:[34,45],2:[78]}	//保留对应可以的内容
 */
function array_to_keyvalue_group($array,$key='',$contentKey=false){
	$result = array();
	if(!is_array($array) || !$array) return $result;
	foreach ($array as $item) {
		$theValue = $item;
		if($contentKey){
			$theValue = $item[$contentKey];
		}
		if(!isset($result[$item[$key]])){
			$result[$item[$key]] = array();
		}
		$result[$item[$key]][] = $theValue;
	}
	return $result;
}

/**
 * 合并两个数组，保留索引信息;
 */
function array_merge_index($array,$array2){
	$result = $array;
	if(!is_array($array) || !$array) return $result;
	foreach ($array2 as $key => $value) {
		$result[$key] = $value;
	}
	return $result;
}

/**
 * 二维数组，根据键值向前、后移动
 * @param string $swap	prev next
 * @return void
 */
function array_swap_keyvalue($array, $key, $value, $swap = 'next'){
    foreach ($array as $idx => $item) {
        if(isset($item[$key]) && $item[$key] == $value){
            $index = $idx;
            break;
        }
    }
    if(!isset($index)) return $array;
    $swap == 'prev' && $index = $index -1;

    $before = array_slice($array,0,$index);
    $middle = array_reverse(array_slice($array,$index,2));
    $after = array_slice($array,$index + 2);
    return array_merge($before,$middle,$after);
}

//set_error_handler('errorHandler',E_ERROR|E_PARSE|E_CORE_ERROR|E_COMPILE_ERROR|E_USER_ERROR);
register_shutdown_function('fatalErrorHandler');
function errorHandler($err_type,$errstr,$errfile,$errline){
	if (($err_type & E_WARNING) === 0 && ($err_type & E_NOTICE) === 0) {
		return false;
	}
	$arr = array(
		$err_type,
		$errstr,
		//" in [".$errfile.']',
		" in [".get_path_this(get_path_father($errfile)).'/'.get_path_this($errfile).']',
		'line:'.$errline,
	);
	$str = implode("  ",$arr)."<br/>";
	show_tips($str);
}

//捕获fatalError
function fatalErrorHandler(){
	$e = error_get_last();
	if(!$e) return;
	switch($e['type']){
		case E_ERROR:
		case E_PARSE:
		case E_CORE_ERROR:
		case E_COMPILE_ERROR:
		case E_USER_ERROR:
			errorHandler($e['type'],$e['message'],$e['file'],$e['line']);
			break;
		case E_NOTICE:break;
		default:break;
	}
}

function show_tips($message,$url= '', $time = 3,$title = ''){
	ob_get_clean();
	header('Content-Type: text/html; charset=utf-8');
	$goto = "content='$time;url=$url'";
	$info = "{$time}s 后自动跳转, <a href='$url'>立即跳转</a>";
	if ($url == "") {//是否自动跳转
		$goto = "";$info = "";
	}
	if($title == ''){$title = "出错了! (warning!)";}
	//移动端；报错输出
	if(isset($_REQUEST['HTTP_X_PLATFORM'])){
		show_json($message,false);
	}
	if(is_array($message) || is_object($message)){
		$message = json_encode_force($message);
		$message = htmlspecialchars($message);
		$message = str_replace(array('\n','\/'),array("<br/>",'/'),stripslashes($message));
		$message = "<pre>".$message.'</pre>';
	}else{
		$message = filter_html(nl2br($message));
	}
	$template = TEMPLATE.'common/showTips.html';
	if(file_exists($template)){
		include($template);exit;
	}
	echo<<<END
<html>
	<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover, shrink-to-fit=no" />
	<meta http-equiv='refresh' $goto charset="utf-8">

	<style>
	body{background: #f6f6f6;padding:0;margin:0; display:flex;align-items: center;justify-content: center;
		position: absolute;left: 0;right: 0;bottom: 0;top: 0;}
	#msgbox{box-shadow: 0px 10px 40px rgba(0, 0, 0, 0.1);border-radius: 5px;border-radius: 5px;background: #fff;
    font-family: "Lantinghei SC","Hiragino Sans GB","Microsoft Yahei",Helvetica,arial,sans-serif;line-height: 1.5em;
	color:888;margin:0 auto;margin-top:10px;margin-bottom:10px;width:500px;font-size:13px;color:#666;word-wrap: break-word;word-break: break-all;max-width: 90%;box-sizing: border-box;max-height: 90%;overflow: auto;padding:30px 30px;}
	#msgbox #info{margin-top: 10px;color:#aaa;}
	#msgbox #title{color: #333;border-bottom: 1px solid #eee;padding: 10px 0;margin:0 0 15px;font-size:22px;font-weight:200;}
	#msgbox #info a{color: #64b8fb;text-decoration: none;padding: 2px 0px;border-bottom: 1px solid;}
	#msgbox a{text-decoration:none;color:#2196F3;}
	#msgbox a:hover{color:#f60;border-bottom:1px solid}
	#msgbox .desc{padding: 10px 0;color: #faad14;}
	#msgbox pre{word-break: break-all;word-wrap: break-word;white-space: pre-wrap;
		background: #002b36;padding:1em;color: #839496;border-left: 6px solid #8e8e8e;border-radius: 3px;}
	</style>
	</head>
	
	<body>
	<div id="msgbox">
		<div id="title">$title</div>
		<div id="message">$message</div>
		<div id="info">$info</div>
	</div>
	</body>
</html>
END;
	exit;
}

function get_caller_trace($trace) {//return array();
	$trace = is_array($trace) ? $trace : array();
	$traceText = array(); //var_dump($trace);exit;
	$maxLoad = 50; //数据过多自动丢失后面内容;
	if(count($trace) > $maxLoad){
		$trace = array_slice($trace,count($trace) - $maxLoad);
	}
	foreach($trace as $i=>$call){
		if (isset($call['object']) && is_object($call['object'])) {
			$call['object'] = get_class_name($call['object']); 
		}else if(isset($call['class'])){
			$call['object'] = $call['class'];
		}
        
		$file = !isset($call['file']) ? null : str_replace(BASIC_PATH,'',$call['file']);
		$traceText[$i] = !isset($call['line']) ? null : $file.'['.$call['line'].'] ';
		$traceText[$i].= empty($call['object'])? '': $call['object'].$call['type']; 
		if( $call['function']=='show_json' || 
			$call['function'] =='think_trace'){
			$traceText[$i].= $call['function'].'(args)';
		}else{
			$args  = (isset($call['args']) && is_array($call['args'])) ? $call['args'] : array();
			$param = json_encode(array_parse_deep($args));
			$param = str_replace(array('\\/','\/','\\\\/','\"'),array('/','/','/','"'),$param);
			if(substr($param,0,1) == '['){
				$param = substr($param,1,-1);
			}
			$maxLength = 200;//参数最长长度
			if(strlen($param) > $maxLength) {
				$param = mb_substr($param,0,$maxLength).'...';
			}
			$traceText[$i].= $call['function'].'('.rtrim($param,',').')';
		}
	}
	unset($traceText[0]);
	$traceText = array_reverse($traceText);
	return $traceText;
}

function get_caller_info() { 
	$trace = debug_backtrace();
	return get_caller_trace($trace);
}
function get_caller_msg($trace = false) { 
	$msg = get_caller_info();
	$msg = array_slice($msg,0,count($msg) - 1);
	$msg['memory'] = sprintf("%.3fM",memory_get_usage()/(1024*1024));
	if($trace){
		$msg['trace']  = think_trace('[trace]');
	}
	$msg = json_encode_force($msg);
	$msg = str_replace(array('\\/','\/','\\\\/','\"'),array('/','/','/','"'),$msg);
	return $msg;
}



// 去除json中注释部分; json允许注释
// 支持 // 和 /*...*/注释 
function json_comment_clear($str){
	$result = '';
	$inComment = false;
	$commentType = '//';// /*,//
	$quoteCount  = 0;
	$str = str_replace(array('\"',"\r"),array("\\\0","\n"),$str);

	for ($i=0; $i < strlen($str); $i++) {
		$char = $str[$i];
		if($inComment){
			if($commentType == '//' && $char == "\n"){
				$result .= "\n";
				$inComment = false;
			}else if($commentType == '/*' && $char == '*' && $str[$i+1] == '/'){
				$i++;
				$inComment = false;
			}
		}else{
			if($str[$i] == '/'){
				if($quoteCount % 2 != 0){//成对匹配，则当前不在字符串内
					$result .= $char;
					continue;
				}	
				if($str[$i+1] == '*'){
					$inComment = true;
					$commentType = '/*';
					$i++;
					continue;
				}else if($str[$i+1] == '/'){
					$inComment = true;
					$commentType = '//';
					$i++;
					continue;
				}
			}else if($str[$i] == '"'){
				$quoteCount++;
			}
			$result .= $char;
		}
	}
	$result = str_replace("\\\0",'\"',$result);
	$result = str_replace("\n\n","\n",$result);
	return $result;
}
function json_space_clear($str){
	$result = '';
	$quoteCount  = 0;
	$str = str_replace(array('\"',"\r"),array("\\\0","\n"),$str);
	for ($i=0; $i < strlen($str); $i++) {
		$char = $str[$i];
		//忽略不在字符串中的空格 tab 和换行
		if( $quoteCount % 2 == 0 &&
			($char == ' ' || $char == '	' || $char == "\n") ){
			continue;
		}
		if($char == '"'){
			$quoteCount ++;
		}
		$result .= $char;
	}
	$result = str_replace("\\\0",'\"',$result);
	return $result;
}

function json_decode_force($str){
	$str = trim($str,'﻿');
	$str = json_comment_clear($str);
	$str = json_space_clear($str);

	//允许最后一个多余逗号(todo:字符串内)
	$str = str_replace(array(',}',',]',"\n","\t"),array('}',']','',' '),$str);
	$result = json_decode($str,true);
	if(!$result){
		//show_json($result,false);
	}
	return $result;
}
function json_encode_force($json){
	if(defined('JSON_PRETTY_PRINT')){
		$jsonStr = json_encode($json,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
	}else{
		$jsonStr = json_encode($json);
	}
	if($jsonStr === false){
		$json = array_parse_deep($json);
		$parse = new Services_JSON();
		$jsonStr = $parse->encode($json);
	}
	return $jsonStr;
}

function array_parse_deep($arr){
	if(!is_array($arr)) return $arr;
	foreach ($arr as $key => $obj) {
		if(is_resource($obj) || is_object($obj)){ // 资源无法json_encode;
			$arr[$key] = get_class_name($obj);
		}else if(is_array($obj)){
			$arr[$key] = array_parse_deep($obj);
		}
	}
	return $arr;
}

function get_class_name($obj){
	if(is_resource($obj)){ // 资源无法json_encode;
		ob_start();echo $obj;$id = ob_get_clean();
		$id = str_replace('Resource id #','',$id);
		$result = '{'.$id."}@".get_resource_type($obj);
	}else if(is_object($obj)){
		$id = substr(md5(spl_object_hash($obj)),0,3);
		$result = '{'.$id."}#".get_class($obj);
	}
	return $result;
}


/**
 * 打包返回AJAX请求的数据
 * @params {int} 返回状态码， 通常0表示正常
 * @params {array} 返回的数据集合
 */
function show_json($data=false,$code = true,$info=''){
	if(!isset($GLOBALS['showJsonTimeStart'])){
		$GLOBALS['showJsonTimeStart'] = TIME_FLOAT;
	}
	$result  = array(
		'code'		=> $code,
		'timeUse'	=> sprintf('%.4f',mtime() - $GLOBALS['showJsonTimeStart']),
		'timeNow'	=> sprintf('%.4f',mtime()),
		'data'	 	=> $data
	);
	if ($info != '') {$result['info'] = $info;}
	// 有值且为true则返回，清空输出并返回数据结果
	if( isset($GLOBALS['SHOW_JSON_NOT_EXIT']) && $GLOBALS['SHOW_JSON_NOT_EXIT'] == 1 ){
		// 保留第一个show_json调用输出;ob_get_clean 后该次置空; 
		if(!ob_get_length()){echo json_encode_force($result);}
		return;
	}

	$temp = Hook::trigger("show_json",$result);
	if(is_array($temp)){$result = $temp;}
	if(defined("GLOBAL_DEBUG") && GLOBAL_DEBUG==1){
		// $result['in']   = $GLOBALS['in'];
		$result['memory'] = sprintf("%.3fM",memory_get_usage()/(1024*1024));
		$result['call']   = get_caller_info();
		$result['trace']  = think_trace('[trace]');
	}
	check_abort(); // hook之后检测处理; task缓存保持;
	
	ob_get_clean();
	if(!headers_sent()){
		header("X-Powered-By: kodbox.");
		header('Content-Type: application/json; charset=utf-8');
		if(!$code){header("X-Request-Error: 1");}
	}
	$json = json_encode_force($result);
	if( isset($_GET['callback']) && $GLOBALS['config']['jsonpAllow'] ){
		if(!preg_match("/^[0-9a-zA-Z_.]+$/",$_GET['callback'])){
			die("calllback error!");
		}
		echo $_GET['callback'].'('.$json.');';
	}else{
		echo $json;
	}
	exit;
}

function show_trace(){
	echo '<pre>';
	echo json_encode_force(get_caller_info());
	echo '</pre>';
	exit;
}

function file_sub_str($file,$start=0,$len=0){
	$size = filesize_64($file);
	if($start < 0 ){
		$start = $size + $start;
		$len = $size - $start;
	}
	if($len <= 0) return '';
    $fp = fopen($file,'r');
    fseek_64($fp,$start);
    $res = fread($fp,$len);
    fclose($fp);
    return $res;
}
function str2hex($string){
	$hex='';
	for($i=0;$i<strlen($string);$i++){
		$hex .= sprintf('%02s ',dechex(ord($string[$i])));
	}
	$hex = strtoupper($hex);
	return $hex;
}

function hex2str($hex){
	$hex = str_replace(" ",'',$hex);
	$string='';
	for ($i=0; $i < strlen($hex)-1; $i+=2){
		$string .= chr(hexdec($hex[$i].$hex[$i+1]));
	}
	return $string;
}

// if(!function_exists('json_encode')){
// 	function json_encode($data){
// 		$json = new Services_JSON();
// 		return $json->encode($data);
// 	}
// 	function json_decode($json_data,$toarray =false) {
// 		$json = new Services_JSON();
// 		$array = $json->decode($json_data);
// 		if ($toarray) {
// 			$array = obj2array($array);
// 		}
// 		return $array;
// 	}
// }

/**
 * 去掉HTML代码中的HTML标签，返回纯文本
 * @param string $document 待处理的字符串
 * @return string 
 */
function html2txt($document){
	$search = array ("'<script[^>]*?>.*?</>'si", // 去掉 javascript
		"'<[\/\!]*?[^<>]*?>'si", // 去掉 HTML 标记
		"'([\r\n])[\s]+'", // 去掉空白字符
		"'&(quot|#34);'i", // 替换 HTML 实体
		"'&(amp|#38);'i",
		"'&(lt|#60);'i",
		"'&(gt|#62);'i",
		"'&(nbsp|#160);'i",
		"'&(iexcl|#161);'i",
		"'&(cent|#162);'i",
		"'&(pound|#163);'i",
		"'&(copy|#169);'i", 
		// "'&#(\d+);'e");
		"'&#(\d+);'"); // 作为 PHP 代码运行
	$replace = array ("",
		"",
		"",
		"\"",
		"&",
		"<",
		">",
		" ",
		chr(161),
		chr(162),
		chr(163),
		chr(169),
		"chr(\\1)");
	$text = preg_replace_callback ($search, function(){return $replace;}, $document);
	return $text;
} 

// 获取内容第一条
function match_text($content, $preg){
	$preg = "/" . $preg . "/isU";
	preg_match($preg, $content, $result);
	return is_array($result) ? $result[1]:false;
} 
// 获取内容,获取一个页面若干信息.结果在 1,2,3……中
function match_all($content, $preg){
	$preg = "/" . $preg . "/isU";
	preg_match_all($preg, $content, $result);
	return $result;
}

if(!function_exists('mb_substr')){
    function mb_substr($str,$start,$len,$charset='') {   
        return preg_replace('#^(?:[\x00-\x7F]|[\xC0-\xFF][\x80-\xBF]+){0,'.$start.'}'.
			'((?:[\x00-\x7F]|[\xC0-\xFF][\x80-\xBF]+){0,'.$len.'}).*#s','$1',$str);
    }
}
if(!function_exists('mb_strlen')){
    function mb_strlen($str,$charset='') {   
        preg_match_all("/./u", $str, $ar); 
        return count($ar[0]); 
    }
}

/**
 * 字符串截取自动修复;(仅utf8 字符串); 去除前后不足一个字符的字节
 * 截取多余部分用replace替换;
 * 
 * $str = substr("我的中国心",1,-2); 
 * $str = utf8Repair($str);
 * 
 * 1 1-128
 * 2 192-223, 128-191
 * 3 224-239, 128-191, 128-191
 * 4 240-247, 128-191, 128-191, 128-191
 */
function utf8Repair($str,$replace=''){
	$length   = strlen($str);
	$charByte = 0;$start = 0;$end = $length;
	$char = ord($str[$start]);
	while($char >= 128 && $char <= 191 && $start <= 5){
		$start ++;
		$char = ord($str[$start]);
	}
	
	for($i = $start; $i < $length; $i++){
		$char = ord($str[$i]);
		if($char <= 128) continue;
		if($char > 247){return $str;}
		else if ($char > 239) {$charByte = 4;}
		else if ($char > 223) {$charByte = 3;}
		else if ($char > 191) {$charByte = 2;}
		else {return $str;}
		if (($i + $charByte) > $length){
			$end = $i;break;
		}
		while ($charByte > 1) {
			$i++;$char = ord($str[$i]);
			if ($char < 128 || $char > 191){return $str;}
			$charByte--;
		}
	}
	
	$charStart = '';$charEnd = '';
	if($start == 0 && $end == $length) return $str;
	if($replace && $start){$charStart = str_repeat($replace,$start);}
	if($replace && $end != $length){$charEnd = str_repeat($replace,$length - $end);}
	
	// pr($start,$end,$length,$charStart,$charEnd);exit;
	return $charStart.substr($str,$start,$end - $start).$charEnd;
}

/**
 * 字符串截取，支持中文和其他编码
 * 
 * @param string $str 需要转换的字符串
 * @param string $start 开始位置
 * @param string $length 截取长度
 * @param string $charset 编码格式
 * @param string $suffix 截断显示字符
 * @return string 
 */
function msubstr($str, $start = 0, $length, $charset = "utf-8", $suffix = true){
	if (function_exists("mb_substr")) {
		$i_str_len = mb_strlen($str);
		$s_sub_str = mb_substr($str, $start, $length, $charset);
		if ($length >= $i_str_len) {
			return $s_sub_str;
		} 
		return $s_sub_str . '...';
	} elseif (function_exists('iconv_substr')) {
		return iconv_substr($str, $start, $length, $charset);
	} 
	$re['utf-8'] = "/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|[\xe0-\xef][\x80-\xbf]{2}|[\xf0-\xff][\x80-\xbf]{3}/";
	$re['gb2312'] = "/[\x01-\x7f]|[\xb0-\xf7][\xa0-\xfe]/";
	$re['gbk'] = "/[\x01-\x7f]|[\x81-\xfe][\x40-\xfe]/";
	$re['big5'] = "/[\x01-\x7f]|[\x81-\xfe]([\x40-\x7e]|\xa1-\xfe])/";
	preg_match_all($re[$charset], $str, $match);
	$slice = join("", array_slice($match[0], $start, $length));
	if ($suffix) return $slice . "…";
	return $slice;
}

// -----------------变量调试-------------------
/**
 * 格式化输出变量，或者对象
 * 
 * @param args; 
 * 默认自动退出；最后一个参数为false时不退出
 */

function pr_replace_callback($matches){
	return "\n".str_repeat(" ",strlen($matches[1])*2).$matches[2];
}
function pr_trace(){
	$result = func_get_args();
	$result['memory'] = sprintf("%.3fM",memory_get_usage()/(1024*1024));
	$result['call'] = get_caller_info();
	$result['trace']  = think_trace('[trace]');
	call_user_func('pr',$result);
}
function pr_trace_exit(){pr_trace();exit;}

function pr(){
	ob_start();
	$style = '<style>
	pre.debug{margin:10px;font-size:14px;color:#222;font-family:Consolas ;line-height:1.2em;background:#f6f6f6;
		border-left:5px solid #444;padding:10px;width:95%;word-break:break-all;white-space:pre-wrap;word-wrap: break-word;}
	pre.debug b{font-weight:400;}
	.debug .debug_keywords{font-weight:200;color:#888;}
	.debug .debug_tag{color:#222 !important;}
	.debug .debug_var{color:#f60;}
	.debug .debug_var_str,.debug #debug_var_str .debug_keywords{color:#f44336;}
	.debug .debug_set{color:#0C9CAE;}</style>';

	$trace = debug_backtrace();
	ob_start();
	
	$callFile = $trace[0];$callAt = $trace[1];
	if(isset($trace[2]) && $trace[2]['function'] == 'pr_trace'){
	    $callFile = $trace[2];$callAt = $trace[3];
	}
	$method = $callAt['function'];
	if(isset($callAt['class'])){
	    $method = $callAt['class'].'->'.$callAt['function'];
	}
	$fileInfo = get_path_this($callFile['file']).'; '.$method.'()';
	if( !isset($GLOBALS['debugLastTime']) ){
		$time = mtime() - TIME_FLOAT;
	}else{
		$time = mtime() - $GLOBALS['debugLastTime'];
	}
	$GLOBALS['debugLastTime'] = mtime();

	$time = sprintf("%.5fs",$time);
	echo "<i class='debug_keywords'>".$fileInfo.";[line-".$callFile['line']."];{$time}</i><br/>";
	$arg = func_get_args();
	$num = func_num_args();
	for ($i=0; $i < $num; $i++) {
		var_dump($arg[$i]);
	}
	$out = ob_get_clean(); //缓冲输出给$out 变量
	$out = preg_replace('/=\>\n\s+/',' => ',$out); //高亮=>后面的值
	$out = preg_replace_callback('/\n(\s*)([\}\[])/','pr_replace_callback',$out); //高亮=>后面的值

	$out = preg_replace('/"(.*)"/','<b class="debug_var_str">"\\1"</b>', $out); //高亮字符串变量
	$out = preg_replace('/\[(.*)\]/','<b class="debug_tag">[</b><b class="debug_var">\\1</b><b class="debug_tag">]</b>', $out); //高亮变量
	$out = str_replace(array('=>',"\n\n"), array('<b id="debug_set">=></b>',"\n"), $out);
	$keywords = array('array','int','string','object','null','float','bool'); //关键字高亮
	$keywords_to = $keywords;
	foreach($keywords as $key => $val) {
		$keywords_to[$key] = '<b class="debug_keywords">' . $val . '</b>';
	}
	$out = str_replace($keywords, $keywords_to, $out);
	// $out = stripslashes($out);
	$out = str_replace(array('\n','\/'),array("<br/>",'/'),$out);
	echo $style.'<pre class="debug">'.$out.'</pre>';
}
function dump(){call_user_func('pr',func_get_args());}
function debug_out(){call_user_func('pr',func_get_args());}

/**
 * 取$from~$to范围内的随机数,包含$from,$to;
 */
function rand_from_to($from, $to){
	return mt_rand($from,$to);
	// return $from + mt_rand(0, $to - $from);
} 

/**
 * 产生随机字串，可用来自动生成密码 默认长度6位 字母和数字混合
 * 
 * @param string $len 长度
 * @param string $type 字串类型：0 字母 1 数字 2 大写字母 3 小写字母  4 中文  
 * 其他为数字字母混合(去掉了 容易混淆的字符oOLl和数字01，)
 * @param string $addChars 额外字符
 * @return string 
 */
function rand_string($len = 4, $type='checkCode'){
	$str = '';
	switch ($type) {
		case 1://数字
			$chars = '0123456789';
			break;
		case 2://大写字母
			$chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
			break;
		case 3://小写字母
			$chars = 'abcdefghijklmnopqrstuvwxyz';
			break;
		case 4://大小写中英文
			$chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
			break;
		default: 
			// 默认去掉了容易混淆的字符oOLl和数字01，要添加请使用addChars参数
			$chars = 'ABCDEFGHIJKMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
			break;
	}
	if ($len > 10) { // 位数过长重复字符串一定次数
		$chars = $type == 1 ? str_repeat($chars, $len) : str_repeat($chars, 5);
	} 
	if ($type != 4) {
		$chars = str_shuffle($chars);
		$str = substr($chars, 0, $len);
	} else {
		// 中文随机字
		for($i = 0; $i < $len; $i ++) {
			$str .= msubstr($chars, floor(mt_rand(0, mb_strlen($chars, 'utf-8') - 1)), 1);
		} 
	} 
	return $str;
} 

/**
 * 生成自动密码
 */
function make_password(){
	$temp = '0123456789abcdefghijklmnopqrstuvwxyz'.
			'ABCDEFGHIJKMNPQRSTUVWXYZ~!@#$^*)_+}{}[]|":;,.'.time();
	for($i=0;$i<10;$i++){
		$temp = str_shuffle($temp.substr($temp,-5));
	}
	return md5($temp);
}

/**
 * php DES解密函数
 * 
 * @param string $key 密钥
 * @param string $encrypted 加密字符串
 * @return string 
 */
function des_decode($key, $encrypted){
	$encrypted = base64_decode($encrypted);
	$td = mcrypt_module_open(MCRYPT_DES, '', MCRYPT_MODE_CBC, ''); //使用MCRYPT_DES算法,cbc模式
	$iv = mcrypt_create_iv(mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
	$ks = mcrypt_enc_get_key_size($td);

	mcrypt_generic_init($td, $key, $key); //初始处理
	$decrypted = mdecrypt_generic($td, $encrypted); //解密
	
	mcrypt_generic_deinit($td); //结束
	mcrypt_module_close($td);
	return pkcs5_unpad($decrypted);
} 
/**
 * php DES加密函数
 * 
 * @param string $key 密钥
 * @param string $text 字符串
 * @return string 
 */
function des_encode($key, $text){
	$y = pkcs5_pad($text);
	$td = mcrypt_module_open(MCRYPT_DES, '', MCRYPT_MODE_CBC, ''); //使用MCRYPT_DES算法,cbc模式
	$ks = mcrypt_enc_get_key_size($td);

	mcrypt_generic_init($td, $key, $key); //初始处理
	$encrypted = mcrypt_generic($td, $y); //解密
	mcrypt_generic_deinit($td); //结束
	mcrypt_module_close($td);
	return base64_encode($encrypted);
} 
function pkcs5_unpad($text){
	$pad = ord($text[strlen($text)-1]);
	if ($pad > strlen($text)) return $text;
	if (strspn($text, chr($pad), strlen($text) - $pad) != $pad) return $text;
	return substr($text, 0, -1 * $pad);
} 
function pkcs5_pad($text, $block = 8){
	$pad = $block - (strlen($text) % $block);
	return $text . str_repeat(chr($pad), $pad);
} 
// 检测语言,只分中文、英语
function check_lang($word){
	$language = 'zh-cn';
	if (!preg_match('/^[a-z]{2}(?:_[a-zA-Z]{2})?$/', $word)) {
		$language = 'en';
	}
	return $language;
}