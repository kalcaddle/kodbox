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
	'fileThumb.config.imageSizeLimit'		=> '支持的最大图片',
	'fileThumb.config.imageSizeLimitDesc'	=> '大于此值的图片文件不生成缩略图（缩略图生成会占用大量服务器资源，大图建议手动添加封面图）',

	'fileThumb.config.svcType'				=> '服务方式',
	'fileThumb.config.igryOpen'				=> '启用服务',
	'fileThumb.config.igryOpenDesc'			=> '启用imaginary服务',
	'fileThumb.config.igryDesc'				=> '1.imaginary是基于HTTP的高性能图像处理服务，它能显著提高缩略图生成效率，并降低服务器内存溢出风险；<br/>
												2.imaginary仅支持部分图片格式处理，更多格式及文件封面图（如Office）生成，还依赖于ImageMagick/FFmpeg服务；<br/>
												3.安装服务时，需启用允许URL处理（-enable-url-source）',
	'fileThumb.config.igryHost'				=> '服务地址',
	'fileThumb.config.igryApiKey'			=> 'API 密钥',
	'fileThumb.config.igryApiKeyDesc'		=> 'API密钥（-key）',
	'fileThumb.config.igryUrlKey'			=> 'URL密钥',
	'fileThumb.config.igryUrlKeyDesc'		=> 'URL签名密钥（-url-signature-key），至少32个字符',
	'fileThumb.config.igryNotMust'			=> '非必需',

	'fileThumb.check.title'				=> '服务检测',
	'fileThumb.check.ing'				=> '环境检测中',
	'fileThumb.check.tips'				=> '请核对服务信息，配置正确后再试！',
	'fileThumb.check.ok'				=> '恭喜，一切正常！',
	'fileThumb.check.faild'				=> '运行环境异常！',
	'fileThumb.check.notFound'			=> '软件未找到，请安装后再试',
	'fileThumb.check.error'				=> '调用失败，检测是否安装该软件，或是否有执行权限',
	'fileThumb.check.svcOk'				=> '服务正常',
	'fileThumb.check.svcErr'			=> '服务异常',

	'fileThumb.video.normal'			=> '流畅',
	'fileThumb.video.before'			=> '原画',
	'fileThumb.video.title'				=> '视频转码',
	'fileThumb.video.STATUS_SUCCESS'	=> '转码成功,已切换到流畅模式',
	'fileThumb.video.STATUS_IGNORE'		=> '当前视频无需转码',
	'fileThumb.video.STATUS_ERROR'		=> '运行错误,检测是否安装该软件,或是否有执行权限(ffmpeg,shell_exec,proc_open)',
	'fileThumb.video.STATUS_RUNNING'	=> '正在转码',
	'fileThumb.video.STATUS_LIMIT'		=> '进行中的任务超出限制,请稍后再试',

	"fileThumb.config.debug"			=> "调试模式",
	"fileThumb.config.debugDesc"		=> "生成相关日志. <button class='btn btn-sm btn-default ml-20 view-log'>查看日志</button>",	
);