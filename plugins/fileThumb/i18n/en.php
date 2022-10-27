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
	
	
	'fileThumb.check.title'				=> 'Check Server',
	'fileThumb.check.ing'				=> 'Checking...',
	'fileThumb.check.tips'				=> 'Please check the server information and try again after correct configuration!',
	'fileThumb.check.ok'				=> 'Congratulations, everything is OK!',
	'fileThumb.check.faild'				=> 'Abnormal running environment!',
	'fileThumb.check.notFound'			=> 'Software not found, please install and try again',
	'fileThumb.check.error'				=> 'Call failed. Check whether the software is installed or whether you have execution permission',

	
	'fileThumb.video.normal'			=> 'Normal',
	'fileThumb.video.before'			=> 'Original',
	'fileThumb.video.title'				=> 'Video Transcoding',
	'fileThumb.video.STATUS_SUCCESS'	=> 'Transcoding succeeded,Switched to standard definition mode',
	'fileThumb.video.STATUS_IGNORE'		=> 'The current video does not need transcoding',
	'fileThumb.video.STATUS_ERROR'		=> 'Running error(ffmpeg,shell_exec,proc_open)',
	'fileThumb.video.STATUS_RUNNING'	=> 'Transcoding',
	'fileThumb.video.STATUS_LIMIT'		=> 'The task in progress exceeds the limit. Please try again later',
);