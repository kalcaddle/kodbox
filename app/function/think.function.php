<?php

/**
 * 快速文件数据读取和保存 针对简单类型数据 字符串、数组
 * @param string $name 缓存名称
 * @param mixed $value 缓存值
 * @param string $path 缓存路径
 * @return mixed
 */
function think_var_cache($name, $value = '', $path = TEMP_PATH) {
    static $_cache = array();
    $filename = $path . $name . '.php';
    if ('' !== $value) {
        if (is_null($value)) {
            return false !== strpos($name, '*') ? array_map("unlink", glob($filename)) : unlink($filename);
        } else {
            $dir = dirname($filename);
            if (!is_dir($dir)){
                mkdir($dir, 0755, true);
            }
            $_cache[$name] = $value;
            return file_put_contents($filename,"<?php \n return " . var_export($value, true) . ";");
        }
    }
    if (isset($_cache[$name])){
        return $_cache[$name];
    }
    // 获取缓存数据
    if (is_file($filename)) {
        $value = include $filename;
        $_cache[$name] = $value;
    } else {
        $value = false;
    }
    return $value;
}

/**
 * 自定义异常处理
 * @param string $msg 异常消息
 * @param string $type 异常类型 默认为ThinkException
 * @param integer $code 异常代码 默认为0
 * @return void
 */
function think_exception($msg) {
	$error = is_object($msg) ? $msg->getMessage() : $msg;
	$trace = is_object($msg) ? $msg->getTrace() : debug_backtrace();
	if(is_object($msg) && is_array($trace)){
		$lastAt = array('file'=>$msg->getFile(),'line'=>$msg->getLine());
		$trace = array_merge(array($lastAt),$trace);
	}
	$trace = get_caller_trace($trace,false);
	write_log($error."\n".get_caller_msg_parse($trace)."\n".json_encode($GLOBALS['in']),'error');
    if(defined('GLOBAL_DEBUG') && GLOBAL_DEBUG ){$trace[] = $error;pr($trace);exit;}
    
	$desc  = is_array($trace) ? implode("\n",array_slice($trace,-2)) : '';
	$desc  = preg_replace("/{\w+}#/",'',$desc);
	think_error_parse($error);
	$error = "<div class='desc'>".$error."</div>";
	$error = $error.'<div style="border-top:1px dotted #eee;"><code>'.$desc.'</code></div>';
	$error = $error.'<div style="color:#ccc;font-size:12px;"><code>['.think_system_info().']</code></div>';
	show_tips($error,'',0,'',false);
}

function think_error_parse(&$error){
    $errList = array(
        'using password'        => LNG('ERROR_DB_PWD'),
        'timed out'             => LNG('ERROR_DB_TIMEOUT'),
        'connection refused'    => LNG('ERROR_DB_CONN_RFS'),
        'getaddrinfo failed'    => LNG('ERROR_DB_ADR'),
        '_NOT_SUPPORT_'         => LNG('ERROR_DB_NOT_SUPPORT'),
        'Access denied'         => LNG('ERROR_DB_XS_DENNIED'),
        'Unknown database'      => LNG('ERROR_DB_NOT_EXIST'),
    );
    $errMsg = LNG('explorer.systemError');
    // $errMsg = '数据库操作异常，请检查对应服务及相关配置。';
    foreach($errList as $key => $msg) {
        if(stripos($error, $key) !== false) {
            $errMsg = $msg; break;
        }
    }
    $error .= '<br/>' . $errMsg;
}

// 加入 系统版本/php版本/数据库类型;
function think_system_info(){
	$version = php_uname('s').' '.php_uname('r');
	$dbType  = _get($GLOBALS['config'],'database.DB_TYPE','');
	if($dbType == 'pdo'){
		$dsn = explode(':',_get($GLOBALS['config'],'database.DB_DSN',''));
		$dbType = 'pdo.'.$dsn[0];
	}
	$kodVersion = KOD_VERSION.'.'.KOD_VERSION_BUILD;
	return $version.'/'.PHP_VERSION.'/'.$dbType.'/'.$kodVersion;
}

/**
 * 获取和设置语言定义(不区分大小写)
 */
function think_lang($name = null, $value = null) {
    return $name;
}

/**
 * 取得对象实例 支持调用类的静态方法
 * @param string $name 类名
 * @param string $method 方法名，如果为空则返回实例化对象
 * @param array $args 调用参数
 * @return object
 */
function think_get_instance_of($name, $method = '', $args = array()) {
    static $_instance = array();
    $identify = empty($args) ? $name . $method : $name . $method . think_guid($args);
    if (!isset($_instance[$identify])) {
        if (class_exists($name)) {
            $o = new $name();
            if (method_exists($o, $method)) {
                if (!empty($args)) {
                    $_instance[$identify] = call_user_func_array(array(&$o, $method), $args);
                } else {
                    $_instance[$identify] = $o->$method();
                }
            } else {
                $_instance[$identify] = $o;
            }
        } else {
            think_exception(think_lang('_CLASS_NOT_EXIST_') . ':' . $name);
        }
    }
    return $_instance[$identify];
}

/**
 * 根据PHP各种类型变量生成唯一标识号
 * @param mixed $mix 变量
 * @return string
 */
function think_guid($mix) {
    if (is_object($mix) && function_exists('spl_object_hash')) {
        return spl_object_hash($mix);
    } elseif (is_resource($mix)) {
        $mix = get_resource_type($mix) . strval($mix);
    } else {
        $mix = serialize($mix);
    }
    return md5($mix);
}

/**
 * 获取和设置配置参数 支持批量定义
 * @param string|array $name 配置变量
 * @param mixed $value 配置值
 * @return mixed
 */
function think_config($name = null, $value = null) {
    static $_config = array();
    // 无参数时获取所有
    if (empty($name)) {
        if (!empty($value) && $array = think_cache('c_' . $value)) {
            $_config = array_merge($_config, array_change_key_case($array));
        }
        return $_config;
    }
    // 优先执行设置获取或赋值
    if (is_string($name)) {
        if (!strpos($name, '.')) {
            $name = strtolower($name);
            if (is_null($value))
                return isset($_config[$name]) ? $_config[$name] : null;
            $_config[$name] = $value;
            return;
        }
        // 二维数组设置和获取支持
        $name = explode('.', $name);
        $name[0] = strtolower($name[0]);
        if (is_null($value))
            return isset($_config[$name[0]][$name[1]]) ? $_config[$name[0]][$name[1]] : null;
        $_config[$name[0]][$name[1]] = $value;
        return;
    }
    // 批量设置
    if (is_array($name)) {
        $_config = array_merge($_config, array_change_key_case($name));
        if (!empty($value)) {// 保存配置值
            think_cache('c_' . $value, $_config);
        }
        return;
    }
    return null; // 避免非法参数
}

/**
 * 字符串命名风格转换
 * type 0 将Java风格转换为C的风格 1 将C风格转换为Java的风格
 * @param string $name 字符串
 * @param integer $type 转换类型
 * @return string
 */
function think_parse_name($name, $type = 0) {
    if ($type) {
		$result = preg_replace_callback("/_([a-zA-Z])/", function($matches) {
			return strtoupper($matches[1]);
		},$name);
		return ucfirst($result);
        // return ucfirst(@preg_replace("/_([a-zA-Z])/e", "strtoupper('\\1')", $name));
    } else {
        return lcfirst($name);//数据库字段以小驼峰命名方式
        // return strtolower(trim(preg_replace("/[A-Z]/", "_\\0", $name), "_"));
    }
}

/**
 * 设置和获取统计数据
 * 使用方法:
 * <code>
 * think_action_status('db',1); // 记录数据库操作次数
 * think_action_status('read',1); // 记录读取次数
 * echo think_action_status('db'); // 获取当前页面数据库的所有操作次数
 * echo think_action_status('read'); // 获取当前页面读取次数
 * </code> 
 * @param string $key 标识位置
 * @param integer $step 步进值
 * @return mixed
 */
function think_action_status($key, $step = 0, $save = false) {
    static $_num = array();
    if (!isset($_num[$key])) {
        $_num[$key] = (false !== $save) ? think_cache('N_' . $key) : 0;
    }
    if (empty($step)){
		return $_num[$key];
	}else{
		$_num[$key] = $_num[$key] + (int) $step;
	}
    if (false !== $save) { // 保存结果
        think_cache('N_' . $key, $_num[$key], $save);
    }
}

/**
 * 缓存管理
 * @param mixed $name 缓存名称，如果为数组表示进行缓存设置
 * @param mixed $value 缓存值
 * @param mixed $options 缓存参数
 * @return mixed
 */
function think_cache($name, $value = '', $options = null) {
	static $_cache = array();
	$timeout = isset($options['expire']) ? $options['expire'] : false;
	// 超时时间为0,则代表内存缓存;
	if($timeout === false ){
		if ($value === '') { 
			return $_cache[$name];
		} elseif (is_null($value)) {
			return $_cache[$name] = null;
		} else {
			return $_cache[$name] = $value;
		}
	}
	
    if ($value === '') { 
        return Cache::get($name);
    } elseif (is_null($value)) {
        return Cache::remove($name);
    } else {
        return Cache::set($name,$value,$timeout);
    }
}

/**
 * 记录和统计时间（微秒）和内存使用情况
 * 使用方法:
 * <code>
 * think_status('begin'); // 记录开始标记位
 * // ... 区间运行代码
 * think_status('end'); // 记录结束标签位
 * echo think_status('begin','end',6); // 统计区间运行时间 精确到小数后6位
 * echo think_status('begin','end','m'); // 统计区间内存使用情况
 * 如果end标记位没有定义，则会自动以当前作为标记位
 * 其中统计内存使用需要 MEMORY_LIMIT_ON 常量为true才有效
 * </code>
 * @param string $start 开始标签
 * @param string $end 结束标签
 * @param integer|string $dec 小数位或者m 
 * @return mixed
 */
function think_status($start, $end = '', $dec = 4) {
    static $_info = array();
    static $_mem = array();
    if (is_float($end)) { // 记录时间
        $_info[$start] = $end;
    } elseif (!empty($end)) { // 统计时间和内存使用
        if (!isset($_info[$end]))
            $_info[$end] = microtime(TRUE);
        if (MEMORY_LIMIT_ON && $dec == 'm') {
            if (!isset($_mem[$end]))
                $_mem[$end] = memory_get_usage();
            return number_format(($_mem[$end] - $_mem[$start]) / 1024);
        }else {
            return number_format(($_info[$end] - $_info[$start]), $dec);
        }
    } else { // 记录时间和内存使用
        $_info[$start] = microtime(TRUE);
        if (MEMORY_LIMIT_ON)
            $_mem[$start] = memory_get_usage();
    }
}

/**
 * 添加和获取页面Trace记录
 * @param string $value 变量
 * @param string $label 标签
 * @param string $level 日志级别 
 * @param boolean $record 是否记录日志
 * @return void
 */
function think_trace($value = '[think]', $label = '', $level = 'DEBUG', $record = false) {
	static $_trace = array();
	static $_logTemp = array();
	
	$info = ($label ? $label.':':'').print_r($value, true);
	if ($level == 'ERR') return think_exception($info);
	if (defined('GLOBAL_DEBUG') && !GLOBAL_DEBUG ) return;
    if ($value == '[trace]') return $_trace;
	if ($value == '[think]') return think_exception($_trace);
	if ($label == '_log_'){
		if(!$_trace[$level]){$_trace[$level] = array();$_logTemp[$level] = TIME_FLOAT;}
		if($value === false){$_trace[$level] = array();$_logTemp[$level] = TIME_FLOAT;return;}
		
		if(is_array($value)){$value = json_encode($value);}
		$timeNow  = timeFloat();$timeNowArr = explode('.',$timeNow.'000');
		$timeShow = date('i:s.',$timeNowArr[0]).substr($timeNowArr[1],0,4);
		$_trace[$level][] = $timeShow.': '.$value.'('.sprintf('%.4f',$timeNow - $_logTemp[$level]).'s)';
		$_logTemp[$level] = $timeNow;
		return;
	}

	if ($label == '_count_'){
		if(!$_trace[$level]){$_trace[$level] = array();}
		if(!$_trace[$level][$value]){$_trace[$level][$value] = 0;}
		$_trace[$level][$value] += 1;
		return;
	}

	$logMax 	= 50;//最多纪录前30条sql; 避免额外开销及内存不可控
	$level  	= strtoupper($level);
	$keyTrace 	= $level.'-trace';
	$keyList	= $level.'-list';
	$keyTime	= $level.'-allTime';
	if (!isset($_trace[$keyList])) {
		$_trace[$keyTrace]	= array();
		$_trace[$keyList]	= array();
		$_trace[$keyTime]  = 0.0;
	}
	
	$useTime = substr($info,strrpos($info,'[ RunTime:')+10,-5);
	$_trace[$keyTime]  = sprintf('%.5f',$_trace[$keyTime] + $useTime );
	if(count($_trace[$keyList]) < $logMax){
		$timeNow  = timeFloat();$timeNowArr = explode('.',$timeNow.'000');
		$timeShow = date('i:s.',$timeNowArr[0]).substr($timeNowArr[1],0,4);
		$index = count($_trace[$keyList]) + 1;
		$index = $index < 10 ? '0'.$index.'' : $index.'';
		$_trace[$keyList][$index] = $timeShow.'['.$useTime.'s]: '.substr($info,0,-21);
		
		$trace = array_slice(get_caller_info(),4,-1); // 5ms 每个;
		$_trace[$keyTrace][$index]= array_merge(array($timeShow.'['.$useTime.'s]: ',$info),$trace);
	}
	if ($record){write_log($info,$level);}
}
function trace_log($value,$key='_log'){  think_trace($value,'_log_',$key);}
function trace_count($value,$key='_count'){think_trace($value,'_count_',$key);}