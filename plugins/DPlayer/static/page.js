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
		resetSize(player,$target);
		$(player.container).trigger('click');// 自动焦点; 视频操作快捷键响应;
		
		//移动端;微信,safari等屏蔽了自动播放;首次点击页面触发播放;
		// $target.find('.dplayer-video-wrap').one("touchstart mousedown",play);
	}
	
	var resetSize = function(player,$player){
		var reset = function(){
			var vWidth  = $player.width();
			var vHeight = $player.height();
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
			
			var dialog = $player.parents('.dplayer-dialog').data('artDialog');
			var left = ($(window).width()  - vWidth) / 2;
			var top  = ($(window).height() - vHeight) / 2;
			// console.log(22,[vWidth,vHeight],[left,top]);
			if(!dialog) return;
			dialog.size(vWidth,vHeight).position(left,top);
		}
		// $player.css({position:'absolute'});
		player.on('loadeddata',reset);
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
		requireAsync([
			appStatic+'DPlayer/lib/flv.min.js',
			appStatic+'DPlayer/lib/hls.min.js',
			appStatic+'DPlayer/lib/webtorrent.min.js',
			appStatic+'DPlayer/lib/dash.all.min.js',
			appStatic+'DPlayer/DPlayer.min.css',
			appStatic+'DPlayer/DPlayer.min.js',
		],function(){
			playVedioStart(vedioInfo);
		});
	}
	return playReady;
});
