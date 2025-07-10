<?php
return array(
    'fileThumb.meta.name' 	=> 'Media file preview',
    'fileThumb.meta.title' 	=> 'Media file preview, thumbnail generation tool',
    'fileThumb.meta.desc' 	=> '<b>Preview of files such as PSD&AI:</b> Generate preview images, and support direct opening <br/>
	<b>File cover of video pdf:</b> pdf, video thumbnail generation, picture EXIF acquisition; Automatic correction of the direction of pictures taken by mobile phones; PHP extension imagick is required <br/>
	<b>Video transcoding:</b> When playing a video, the video is automatically transcoded to generate a smooth version and cached. When playing it again later, the smooth version is automatically played by default, and you can switch to the original painting mode',
	
    'fileThumb.Config.missLib' 				=> "WARNING: Lack of php extension imagick, please try again after installation",
    'fileThumb.Config.fileThumbExt' 		=> "file thumbnail",
    'fileThumb.Config.fileThumbExtDesc' 	=> "imagick support thumbnail generated file type",
	
	'fileThumb.config.file'					=> 'File Association Settings',
	'fileThumb.config.use'					=> 'File thumbnail generation',
	'fileThumb.config.test'					=> 'Check test',
	'fileThumb.config.help'					=> 'Service Deployment Manual',
	'fileThumb.config.convertTips'			=> 'Automatic transcoding when playing video',
	'fileThumb.config.videoOpen'			=> 'Enable video transcoding',
	'fileThumb.config.videoOpenDesc'		=> 'Automatic transcoding when playing video after opening',
	'fileThumb.config.videoSizeLimit'		=> 'Minimum transcoding file size',
	'fileThumb.config.videoSizeLimitDesc'	=> 'No transcoding if less than this value',
	'fileThumb.config.videoSizeLimitTo'		=> 'Maximum transcoding file',
	'fileThumb.config.videoSizeLimitToDesc'	=> 'This value of the file will not be transcoded. If it is 0, there is no restriction',
	'fileThumb.config.videoTaskLimit'		=> 'Concurrency limit',
	'fileThumb.config.videoTaskLimitDesc'	=> 'Maximum number of transcoding tasks allowed at the same time',
	'fileThumb.config.videoTypeLimit'		=> 'File format',
	'fileThumb.config.videoTypeLimitDesc'	=> 'Transcoding only when the file format is specified',
	'fileThumb.config.playType'				=> 'Default quality',
	'fileThumb.config.playTypeDesc'			=> 'When Normal mode is selected, video transcoding must be completed',
	'fileThumb.config.imageSizeLimit' 		=> 'Supported maximum image',
	'fileThumb.config.imageSizeLimitDesc' 	=> 'Image files larger than this value will not generate thumbnails (thumbnail generation will take up a lot of server resources, it is recommended to manually add cover images for large images)',

	'fileThumb.config.svcType' 				=> 'Service mode',
	'fileThumb.config.igryOpen' 			=> 'Enable service',
	'fileThumb.config.igryOpenDesc' 		=> 'Enable imaginary service',
	'fileThumb.config.igryDesc' 			=> '1.imaginary is a high-performance image processing service based on HTTP, which can significantly improve the efficiency of thumbnail generation and reduce the risk of server memory overflow;<br/>
												2.imaginary only supports the processing of some image formats. The generation of more formats and file cover images (such as Office) also relies on ImageMagick/FFmpeg services;<br/>
												3. When installing the service, you need to enable URL processing (-enable-url-source)',
	'fileThumb.config.igryHost' 			=> 'Service address',
	'fileThumb.config.igryApiKey' 			=> 'API key',
	'fileThumb.config.igryApiKeyDesc' 		=> 'API key (-key)',
	'fileThumb.config.igryUrlKey' 			=> 'URL key',
	'fileThumb.config.igryUrlKeyDesc' 		=> 'URL signature key (-url-signature-key), at least 32 characters',
	'fileThumb.config.igryNotMust' 			=> 'Not required',

	'fileThumb.check.title'				=> 'Check Service',
	'fileThumb.check.ing'				=> 'Checking...',
	'fileThumb.check.tips'				=> 'Please check the service information and try again after correct configuration!',
	'fileThumb.check.ok'				=> 'Congratulations, everything is OK!',
	'fileThumb.check.faild'				=> 'Abnormal running environment!',
	'fileThumb.check.notFound'			=> 'Software not found, please install and try again',
	'fileThumb.check.error'				=> 'Call failed. Check whether the software is installed or whether you have execution permission',
	'fileThumb.check.svcOk' 			=> 'Service is normal',
	'fileThumb.check.svcErr' 			=> 'Service is abnormal',
	
	'fileThumb.video.normal'			=> 'Normal',
	'fileThumb.video.before'			=> 'Original',
	'fileThumb.video.title'				=> 'Video Transcoding',
	'fileThumb.video.STATUS_SUCCESS'	=> 'Transcoding succeeded,Switched to standard definition mode',
	'fileThumb.video.STATUS_IGNORE'		=> 'The current video does not need transcoding',
	'fileThumb.video.STATUS_ERROR'		=> 'Running error(ffmpeg,shell_exec,proc_open)',
	'fileThumb.video.STATUS_RUNNING'	=> 'Transcoding',
	'fileThumb.video.STATUS_LIMIT'		=> 'The task in progress exceeds the limit. Please try again later',

	"fileThumb.config.debug"			=> "Debug Mode",
	"fileThumb.config.debugDesc"		=> "Will write the log. <button class='btn btn-sm btn-default ml-20 view-log'>view log</button>",
);