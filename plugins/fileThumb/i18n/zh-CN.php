<?php
return array(
	'fileThumb.meta.name'			=> '文件封面生成',
	'fileThumb.meta.title'			=> 'PSD文件预览、pdf,视频缩略图生成工具,视频文件转码',
	'fileThumb.meta.desc' 			=> 
	'<b>PSD&AI等文件预览:</b>生成预览图,支持直接打开<br/>
	<b>视频pdf的文件封面:</b>pdf,视频缩略图生成,图片EXIF获取;手机拍摄图片方向自动校正；需要php扩展 imagick;<br/>
	<b>视频转码:</b> 播放视频时,自动对视频进行转码,生成流畅版并缓存,后续再次播放时自动默认播放流畅版本,可以切换到原画模式.',
	
	'fileThumb.Config.missLib'      	=> "缺少php扩展imagick,请安装后再试",
	'fileThumb.Config.fileThumbExt' 	=> "文件缩略图",
	'fileThumb.Config.fileThumbExtDesc' => "imagick支持生成缩略图的文件类型",
	
	'fileThumb.config.file'					=> '文件关联设置',
	'fileThumb.config.use'					=> '等文件缩略图生成',
	'fileThumb.config.test'					=> '连接测试',
	'fileThumb.config.help'					=> '服务部署手册',
	'fileThumb.config.convertTips'			=> '播放视频时自动转码',
	'fileThumb.config.videoOpen'			=> '开启视频转码',
	'fileThumb.config.videoOpenDesc'		=> '开启后播放视频时自动转码',
	'fileThumb.config.videoSizeLimit'		=> '最小转码文件大小',
	'fileThumb.config.videoSizeLimitDesc'	=> '小于此值不进行转码',
	'fileThumb.config.videoSizeLimitTo'		=> '最大转码文件',
	'fileThumb.config.videoSizeLimitToDesc'	=> '大于此值不进行转码,为0则不限制',
	'fileThumb.config.videoTaskLimit'		=> '并发限制',
	'fileThumb.config.videoTaskLimitDesc'	=> '最大允许同时进行转码的任务数',
	'fileThumb.config.videoTypeLimit'		=> '文件格式',
	'fileThumb.config.videoTypeLimitDesc'	=> '指定文件格式才转码',
	'fileThumb.config.videoTypeLimitDesc'	=> '指定文件格式才转码',
	'fileThumb.config.playType'				=> '视频播放默认画质',
	'fileThumb.config.playTypeDesc'			=> '选择流畅模式时,需要视频已经转码完成',
	
	'fileThumb.check.title'				=> '服务器检测',
	'fileThumb.check.ing'				=> '环境检测中',
	'fileThumb.check.tips'				=> '请核对服务器信息，配置正确后再试！',
	'fileThumb.check.ok'				=> '恭喜，一切正常！',
	'fileThumb.check.faild'				=> '运行环境异常！ ',
	'fileThumb.check.notFound'			=> '软件未找到，请安装后再试',
	'fileThumb.check.error'				=> '调用失败，检测是否安装该软件，或是否有执行权限',

	'fileThumb.video.normal'			=> '流畅',
	'fileThumb.video.before'			=> '原画',
	'fileThumb.video.title'				=> '视频转码',
	'fileThumb.video.STATUS_SUCCESS'	=> '转码成功,已切换到流畅模式',
	'fileThumb.video.STATUS_IGNORE'		=> '当前视频无需转码',
	'fileThumb.video.STATUS_ERROR'		=> '运行错误,检测是否安装该软件,或是否有执行权限(ffmpeg,shell_exec,proc_open)',
	'fileThumb.video.STATUS_RUNNING'	=> '正在转码',
	'fileThumb.video.STATUS_LIMIT'		=> '进行中的任务超出限制,请稍后再试',
);