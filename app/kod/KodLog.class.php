<?php 

/**
 * 用户相关信息处理
 */
class KodLog{
	public static function replace($staus=true){self::log('',$staus ? 'replace':'reset');}
	public static function enable ($staus=true){self::log('',$staus ? 'enable':'disable');}
	public static function log($log,$replaceType=''){
		static $replace = false;
		static $disableOut = false;
		if($replaceType){
			if($replaceType == 'disable'){$disableOut = true;return;}
			if($replaceType == 'enable' ){$disableOut = false;return;}
			if($disableOut){return;}
			$replaceNow = $replaceType == 'replace';
			$hasChange  = (!$replace && $replaceNow) || ($replace && !$replaceNow);
			$replace 	= $replaceNow;
			if($hasChange){echoLog('',$replace);}
			return;
		}
		write_log($log,'log');
		if($disableOut){return;}
		check_abort_now();
		echoLog($log,$replace);
	}
	
	public static function logTimeShow($timeStart,$index,$total,$logPre='',$logAfter=''){
		$logPre   = $logPre   ? $logPre.' ':'';
		$logAfter = $logAfter ? ';'.$logAfter:'';
		self::log($logPre.self::timeShow($timeStart,$index,$total).$logAfter);
	}	
	// 进度及时间显示;  
	public static function timeShow($timeStart,$index,$total){
		$timeNeed  		= self::timeNeed($timeStart,$index,$total);
		$timeUse 		= timeFloat() - $timeStart;
		
		$charCount 		= 20;$char = '=';
		$pencent 		= $index / $total;
		$pencent 		= $pencent >= 1 ? 1:$pencent;
		$charFinished 	= intval($pencent * $charCount);
		$pencentView 	= '['.str_repeat($char,$charFinished).'>'.str_repeat('&nbsp;',$charCount - $charFinished).']';
		$logIndex 		= str_repeat('&nbsp;',strlen($total.'') - strlen($index.'')).$index;
		$logOut 		= $logIndex.'/'.$total.' '.$pencentView.sprintf(" %.2f",$pencent*100).'%';
		
		$speed   = $index / $timeUse;
		$speed   = $speed < 10 ? round($speed,2) : intval($speed);
		if($speed > 0){$logOut .= '('.$speed.' '.LNG('common.item').'/'.LNG('common.second').')';}
		if($pencent >= 1){
			$timeUsePre  = LNG('admin.backup.timeTaken');
			$timeUseShow = $timeUsePre.self::timeFormat($timeUse);
			if($timeUse < 10){$timeUseShow = $timeUsePre.round($timeUse,2).LNG('common.second');}
			if($timeUse <= 0.2){return $logIndex.'/'.$total.'; '.$timeUseShow;} // 完成时间过短,不显示进度及速度;

			$logOut .= ';'.$timeUseShow;$timeNeed = '';
		}
		if($timeNeed){$logOut .= '; '.LNG('common.task.timeNeed').$timeNeed;}
		return $logOut;
	}
	
	// 计算所需时间;
	public static function timeNeed($timeStart,&$index,$total){
		// $index = $index + 1;
		if($index >= $total){$index = $total;}
		if($index <= 0){return '';}
		$timeRow  = (timeFloat() - $timeStart) / $index;
		return self::timeFormat($timeRow * ($total - $index));
	}
	
	// 时间格式化显示;
	public static function timeFormat($seconds){
		if(!$seconds){return '';}		
		$sep = ' ';$sep1 = ',';
		if(substr(I18n::getType(),0,2) == 'zh'){$sep = '';$sep1 = '';}
		$d = floor($seconds / 86400);$dt = $d.$sep.LNG('common.day');
		$h = floor(($seconds % 86400) / 3600);$ht = $h.$sep.LNG('common.hour');
		$m = floor(($seconds % 3600) / 60);$mt = $m.$sep.LNG('common.minute');
		$s = ceil(fmod($seconds,60));$st = $s.$sep.LNG('common.second');

		if($d){$result = $dt.$sep1.$ht.$sep1.$mt;}
		if(!$d && $h){$result = $ht.$sep1.$mt;}
		if(!$d && !$h && $m){$result = $mt.$sep1.$st;}
		if(!$d && !$h && !$m){$result = $st;}
		return $sep.$result;
	}
}