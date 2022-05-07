define(function(require, exports) {
	var playStart = function(vedioInfo){
		var $target = createDialog(vedioInfo.name);
		var typeArr = {
			'f4v' : 'flv',
			'f4a' : 'flv',
			'm4a' : 'mp3',
			'aac' : 'mp3',
			'ogg' : 'oga',
		};
		var type = typeArr[vedioInfo.ext] || vedioInfo.ext;
		var playerOption = {
			container:$target.get(0),
			preload: 'none',
			theme:'#f60',
			loop: false,
			autoplay:true,
			lang: 'zh-cn',
			//flv仅支持 H.264+AAC编码 https://github.com/Bilibili/flv.js/issues/47
			video: {
				url:vedioInfo.url,
				type:type
			},
			pluginOptions: {
				flvjs: {},
				webtorrent: {},
				flv:{
					mediaDataSource:{},
					config:{}
				}
			},
			contextmenu: [
				{
					text: 'kodcloud官网',
					link: 'https://kodcloud.com/'
				}
			]
		};
		loadSubtitle(playerOption,vedioInfo);
		var player = new DPlayer(playerOption);
		player.play();
		$target.find('video').attr('autoplay','autoplay').removeAttr('muted');
		if(window.kodApp && kodApp.mediaAutoPlay === false){player.pause();}
		
		var dialog = $target.parents('.dplayer-dialog').data('artDialog');
		var playerResize = playerDialogResize($target,dialog,false);
		player.on('loadeddata',playerResize);
		$(player.container).trigger('click');// 自动焦点; 视频操作快捷键响应;
		
		//移动端;微信,safari等屏蔽了自动播放;首次点击页面触发播放;
		// $target.find('.dplayer-video-wrap').one("touchstart mousedown",play);
	}

	/**
	 * 根据视频尺寸,自动调整窗口尺寸及位置,确保视频能够完整显示
	 * 
	 * 1. 对话框全屏后才加载完成, 不处理尺寸;
	 * 2. 对话框全屏后才加载完成,再次还原窗口时处理视频尺寸; 
	 */
	 var playerDialogResize = function($player,dialog,animate){
		var isReset  = false;
		if(!dialog) return;
		
		// 已经重置过尺寸的不再重置, 视频未加载完成不重置, 最大化时不重置;
		var resetSize = function(fileLoad){
			if(isReset && !fileLoad) return;
			if(!dialog.$main || dialog.$main.hasClass('dialog-max')) return;
			
			isReset 	= true;
			var $video  = $player.find('video');
			var vWidth  = $video.width();
			var vHeight = $video.height();
			var wWidth  = $(window).width()  * 0.9;
			var wHeight = $(window).height() * 0.9;
			if(vHeight >= wHeight){
				vWidth  = (wHeight * vWidth) / vHeight;
				vHeight = wHeight;
			}
			if( vWidth >= wWidth ){
				vHeight = (wWidth * vHeight) / vWidth;
				vWidth  = wWidth;
			}
			var left = ($(window).width()  - vWidth) / 2;
			var top  = ($(window).height() - vHeight) / 2;
			if(animate){
				var maxClass = 'dialog-change-max';
				dialog.$main.removeClass(maxClass).addClass(maxClass);
				setTimeout(function(){dialog.$main.removeClass(maxClass);},350);
			}
			dialog.size(vWidth,vHeight).position(left,top);
			// console.error(202,[vWidth,vHeight],[left,top],dialog.$main.attr('class'));
		}

		var clickMaxBefore = _.bind(dialog._clickMax,dialog);
		dialog._clickMax = function(){
			clickMaxBefore();
			setTimeout(function(){
				if(dialog.$main.hasClass('dialog-max')) return;
				resetSize();
			},350); //尺寸调整动画完成后处理;
		}
		return function(){resetSize(true);};
	};
	
	var loadSubtitle = function(playerOption,vedioInfo){
		var pathModel = _.get(window,'kodApp.pathAction.pathModel');
		// console.log(101,subtitle,vedioInfo,pathModel);
		if(vedioInfo.autoSubtitle != '1' || !pathModel) return;
		
		var fileName = vedioInfo.name+'.vtt'
		var subtitle = pathModel.fileOutBy(vedioInfo.path,fileName);
		playerOption.subtitle = {
			url: subtitle,
			type: 'webvtt',
			fontSize: '20px',
			bottom: '10%',
			color: '#b7daff',
		};
	}
	
	var createDialog = function(title,ext){
		var size  = {width:'70%',height:'60%'};
		if(ext == 'mp3'){
			size  = {width:'320px',height:'420px'};
		}
		var dialog = $.dialog({
			simple:true,
			ico:core.icon('mp4'),
			title:title,
			width:size.width,
			height:size.height,
			content:'<div class="Dplayer"></div>',
			resize:true,
			padding:0,
			fixed:true,
			close:function(){}
		});
		dialog.DOM.wrap.addClass('dplayer-dialog');
		return dialog.DOM.wrap.find(".Dplayer");
	}
	
	// 磁力链接支持播放
	var playVedioStart = function(vedioInfo){
		if(vedioInfo.ext != 'magnet'){
			return playStart(vedioInfo);;
		}
		// 磁力链接支持播放; magnet文件扩展名; 内容为磁力链接url;
		$.get(vedioInfo.url,function(data){
			if(data && _.startsWith(data,'magnet:')){
				vedioInfo.url = data;
				vedioInfo.ext = 'webtorrent';
				playStart(vedioInfo);
			}
		});
	}

	var playReady = function(appStatic,vedioInfo){
		var tips = Tips.loadingMask();
		requireAsync([
			appStatic+'DPlayer/lib/flv.min.js',
			appStatic+'DPlayer/lib/hls.min.js',
			appStatic+'DPlayer/lib/webtorrent.min.js',
			appStatic+'DPlayer/lib/dash.all.min.js',
			appStatic+'DPlayer/DPlayer.min.css',
			appStatic+'DPlayer/DPlayer.min.js',
		],function(){
			tips.close();
			playVedioStart(vedioInfo);
		});
	}
	return playReady;
});
