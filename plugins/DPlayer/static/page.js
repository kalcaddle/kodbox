define(function(require, exports){
	var playStart = function(videoInfo){
		var $target = createDialog(videoInfo.name);
		var typeArr = {
			'f4v' : 'flv',
			'f4a' : 'flv',
			'm4a' : 'mp3',
			'aac' : 'mp3',
			'ogg' : 'oga',
		};
		var type = typeArr[videoInfo.ext] || videoInfo.ext;
		var playerOption = {
			container:$target.get(0),
			preload: 'none',
			theme:'#f60',
			loop: false,
			autoplay:true,
			lang: 'zh-cn',
			//flv仅支持 H.264+AAC编码 https://github.com/Bilibili/flv.js/issues/47
			video: {url:videoInfo.url,type:type},
			videoPath:videoInfo.path,
			airplay:true,
			chromecast:false,
			mutex:false, //是否互斥, true=同时只播放一个视频
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
		loadSubtitle(playerOption,videoInfo);
		
		if(window.kodApp && kodApp.videoLoadSmall){
			playerOption.video = {
				quality: [
					{url:videoInfo.url,type:'mp4',name:LNG['fileThumb.video.normal']},
					{url:videoInfo.url,type:type,name:LNG['fileThumb.video.before']},
				],
				defaultQuality: 1,
				thumbnails:API_HOST+'plugin/fileThumb/videoPreview&path='+urlEncode(videoInfo.path),
			};
		};
		if(window.G && window.G.lang && G.lang.indexOf('zh') == -1){playerOption.lang = 'en';}
		
		var player = new DPlayer(playerOption);
		player.play();window.dplayer = player;
		$target.data('dplayer',player).attr('tabindex','1');
		$target.find('video').attr('autoplay','autoplay').removeAttr('muted');
		
		var noticeDelay = false;
		functionHook(player,'seek',function(setTime){//console.error(1112,arguments)
			var change = (Math.abs(player.video.currentTime - setTime)).toFixed(0);
			if(change == '0'){
				player.disableNotice = true;clearTimeout(noticeDelay);
				noticeDelay = setTimeout(function(){player.disableNotice = false;},500);
			}
		});
		functionHook(player,'notice',function(){//console.error(111,arguments)
			if(player.disableNotice) return false;
			if(arguments[1] == -1){arguments[1] = 500;}
			return arguments;
		});
		
		// 速度切换状态标记;
		$target.find('.dplayer-setting-speed-item[data-speed="1"]').addClass('selected');
		functionHook(player,'speed',function(speed){
			$target.find('.dplayer-setting-speed-item.selected').removeClass('selected');
			$target.find('.dplayer-setting-speed-item[data-speed="'+speed+'"]').addClass('selected');
			return arguments;
		});
		
		// 拖动进度条过程中,一直显示时间及预览图(不仅限hover在进度条上, 简单处理方式:加大进度条容器高度)
		$target.find('.dplayer-bar-wrap').bind('mousedown touchstart',function(e){
			var $bar = $(this);
			$bar.addClass('on-draging').css({height:$target.height()+'px'});
			$(document).one('mouseup touchend',function(){
				$bar.removeClass('on-draging').css({height:''});
			});
		});
		
		if(window.kodApp && kodApp.mediaAutoPlay === false){player.pause();}
		if(window.kodApp && kodApp.videoLoadSmall){
			$target.find('.dplayer-quality').hide();
			kodApp.videoLoadSmall(videoInfo.path,kodApp,$target,function(normalSrc,size,autoPlayFast){
				if(!normalSrc){return;}
				// console.error(111,arguments);
				$target.find('.dplayer-quality-item[data-index="0"]').attr('title',size.now);
				$target.find('.dplayer-quality-item[data-index="1"]').attr('title',size.before).addClass("selected");
				$target.find('.dplayer-quality-item').attr('title-timeout','100');
				$target.find('.dplayer-quality').show();
				player.options.video.quality[0].url = normalSrc;
				player.disableNotice = true;clearTimeout(noticeDelay);
				noticeDelay = setTimeout(function(){player.disableNotice = false;},2000);
				
				// 是否自动切换为流畅模式;ie11 切换视频黑屏;
				if(autoPlayFast){player.switchQuality(0);}
			});
			
			// 视频预览处理;
			var previewWidth = 150;var pickCount = 300;var rowCount = 10;
			var previewMove  = player.controller.thumbnails.move;	
			player.controller.thumbnails.move = function(offsetX){
				var imageHeight = (player.video.videoHeight / player.video.videoWidth) * previewWidth;
				var totalTime   = player.video.duration || 0;
				if(player.thumbNeedRotate){
					imageHeight = (player.video.videoWidth / player.video.videoHeight) * previewWidth;
				}
				if(!totalTime || !imageHeight || totalTime <= 30){
					$(this.container).css({display:'none'});return;
				}
				
				imageHeight = Math.ceil(imageHeight); // 处理为偶数;
				if(imageHeight % 2 != 0){imageHeight = imageHeight - 1;}
								
				// pickCount = parseInt(totalTime); //每秒生成1张图;
				var hoverTime = player.video.duration * (offsetX / player.template.playedBarWrap.offsetWidth);
				hoverTime = Math.ceil(hoverTime);
				var index = parseInt(hoverTime / (totalTime / pickCount));
				var pose  = ((index % rowCount) * previewWidth) + 'px -'+ (parseInt(index / rowCount) * imageHeight) + 'px';
				var style = {
					'background-size':'auto','background-color':'transparent',
					'background-position':pose,'border-radius':'2px',
					// 'box-shadow:':'0px 5px 20px rgba(0,0,0,0.5)',
					left:Math.min(Math.max(offsetX - this.container.offsetWidth / 2, -10), this.barWidth - 150) + 'px',
					width:previewWidth+'px',
					height:parseInt(imageHeight)+'px',
					top:parseInt(-imageHeight + 2) + 'px',
				};
				
				// 缩略图方向旋转处理;
				if(player.thumbNeedRotate){
					style.width 	= parseInt(previewWidth)+'px';
					style.height	= parseInt(imageHeight)+'px';
					style.left		= Math.min(Math.max(offsetX - this.container.offsetWidth / 2, -10), this.barWidth - 150) + 'px',
					style.top		= parseInt(-imageHeight + 2 - 0.5*(previewWidth - imageHeight)) + 'px';
					style.transform = 'rotate(90deg)';
					if($target.find('.dplayer-bar-wrap').hasClass('on-draging')){//拖拽时处理;
						style['margin-bottom'] = 0.5 * (previewWidth - imageHeight) + 'px';
					}
				}
				
				$(this.container).css(style);
				// console.error(123,[hoverTime,totalTime,index,(totalTime / pickCount)],style,this);
			}
		}
		
		// 切换视频时,销毁之前播放器;
		functionHook(player.template.videoWrap,'removeChild',function(dom){
			videoDestory(dom,player);
		});
		player.on('quality_start',function(){
			var index = player.qualityIndex;
			$target.find('.dplayer-quality-item').removeClass('selected');
			$target.find('.dplayer-quality-item[data-index="'+index+'"]').addClass('selected');
		});
		
		var dialog = $target.parents('.dplayer-dialog').data('artDialog');
		var playerResize = playerDialogResize($target,dialog,player,false);
		player.on('loadeddata',playerResize);
		$(player.container).trigger('click');// 自动焦点; 视频操作快捷键响应;
		
		// 自动显示隐藏标题栏;
		$(player.container).bind('click',function(e){
			setTimeout(function(){
				if($('.dplayer').hasClass('dplayer-hide-controller')){
					$('.dplayer-dialog').addClass('hide-controller');
				}else{
					$('.dplayer-dialog').removeClass('hide-controller');
				}
			},100);
		});		
		//移动端;微信,safari等屏蔽了自动播放;首次点击页面触发播放;
		// $target.find('.dplayer-video-wrap').one("touchstart mousedown",play);
	}
	
	// 切换视频或关闭播放器,销毁处理, 避免继续网络请求;
	var videoDestory = function(video,player){
		if(!video || !video.pause) return;
		video.pause();video.src="";video.load();player.play();
	}

	/**
	 * 根据视频尺寸,自动调整窗口尺寸及位置,确保视频能够完整显示
	 * 
	 * 1. 对话框全屏后才加载完成, 不处理尺寸;
	 * 2. 对话框全屏后才加载完成,再次还原窗口时处理视频尺寸; 
	 */
	var playerDialogResize = function($player,dialog,player,animate){
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
			dialog._width = vWidth;dialog._height = vHeight;
			// console.error(202,[vWidth,vHeight],[left,top],dialog,dialog.$main.attr('class'));
		}

		var clickMaxBefore = _.bind(dialog._clickMax,dialog);
		dialog._clickMax = function(){
			clickMaxBefore();
			setTimeout(function(){
				if(dialog.$main.hasClass('dialog-max')) return;
				resetSize();
			},350); //尺寸调整动画完成后处理;
		}
		return function(){
			playHistoryKeep(player,false); // 首次加载-定位到上次播放位置
			thumbDirection($player,player);
			resetSize(true);
		};
	};
	
	// 缩略图方向处理; 跟随视频方向矫正;
	var thumbDirection = function($player,player){
		var src = player.options.video.thumbnails;
		var dom = '<img src="'+src+'" />';
		if(!src || player.thumbDirectionFinished) return;
		
		player.thumbDirectionFinished = true;
		var isEqal  = function(a,b){
			return Math.abs(a - b) <= 0.01 ? true : false;
		}
		$(dom).bind('load',function(){
			var width = this.width,height = this.height;
			var iWidth = width / 10, iHeight = height / 30;	
			var vWidth  = player.video.videoWidth;
			var vHeight = player.video.videoHeight;
			//console.log(222,[vWidth,vHeight],[iWidth,iHeight],player);
			
			if(isEqal(vWidth / vHeight,iWidth / iHeight) ) return;
			if(!isEqal(vWidth / vHeight,iHeight / iWidth)) return;// 旋转后比例相等;
			player.thumbNeedRotate = true;
		});
	}
	
	// 关闭时记录播放位置; 或首次载入时自动跳转到上次播放位置;
	var playHistoryKeep = function(player,isSave){
		var videoPath = player.options.videoPath || '';
		if(!videoPath || !player.video || !player.video.duration) return;
		if(isSave){
			var totalTime = parseInt(player.video.duration);
			var setTime   = parseInt(player.video.currentTime);
			setTime = (setTime <= 5 || setTime > (totalTime - 5) ) ? 0 : setTime;
			// console.log('playSave:',setTime);
			return storeList(videoPath,setTime);
		}
		
		// 播放上次关闭时的进度;
		var setTime = storeList(videoPath,false);
		if(!setTime || player.playHistoryKeepFinished) return;
		player.playHistoryKeepFinished = true;
		player.seek(setTime);
		// console.log('playSeek:',setTime);
		setTimeout(function(){player.notice(timeShow(setTime));},300);
	}
	
	// 播放进度记录或获取; setTime=false时为取出进度; setTime为0则删除该条数据,大于0则保存进度;
	var storeList = function(videoPath,setTime){
		var storeKey  = 'dPlayerPlayList',maxNumber = 100;
		var listData  = LocalData.getConfig(storeKey,[]);
		var theItem   = _.find(listData,{path:videoPath});
		if(setTime === false){return theItem ? theItem.time : false;} // 获取;
		
		// 记录;已存在则先删除该项,再添加到最后;
		if(theItem){ _.remove(listData,theItem);}
		if(listData.length >= maxNumber){
			listData = listData.slice(listData.length - maxNumber + 1);
		}
		if(setTime && setTime > 0){listData.push({path:videoPath,time:setTime});}
		LocalData.setConfig(storeKey,listData);
		// console.error('storeList:',listData);
	};
	
	var loadSubtitle = function(playerOption,videoInfo){
		var pathModel = _.get(window,'kodApp.pathAction.pathModel');
		// console.log(101,videoInfo,pathModel);
		if(videoInfo.autoSubtitle != '1' || !pathModel) return;
		
		var fileName = videoInfo.name+'.vtt'
		var subtitle = pathModel.fileOutBy(videoInfo.path,fileName);
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
			close:function(){
				var player = dialog.DOM.wrap.find(".Dplayer").data('dplayer');
				if(player){
					playHistoryKeep(player,true);
					videoDestory(player.video,player);player.destroy();
				}
				if(window.kodApp){kodApp.trigger('vedioOnClose');}
			}
		});
		dialog.DOM.wrap.addClass('dplayer-dialog');
		return dialog.DOM.wrap.find(".Dplayer");
	}
	
	// 磁力链接支持播放
	var playVedioStart = function(videoInfo){
		if(videoInfo.ext != 'magnet'){
			return playStart(videoInfo);;
		}
		// 磁力链接支持播放; magnet文件扩展名; 内容为磁力链接url;
		$.get(videoInfo.url,function(data){
			if(data && _.startsWith(data,'magnet:')){
				videoInfo.url = data;
				videoInfo.ext = 'webtorrent';
				playStart(videoInfo);
			}
		});
	}

	var playReady = function(appStatic,videoInfo){
		var tips = Tips.loadingMask();
		requireAsync([
			appStatic+'DPlayer/lib/flv.min.js',
			appStatic+'DPlayer/lib/hls.min.js',
			appStatic+'DPlayer/lib/webtorrent.min.js',
			appStatic+'DPlayer/lib/dash.all.min.js',
			appStatic+'DPlayer/DPlayer.min.css?v=1.39',
			appStatic+'DPlayer/DPlayer.min.js',
		],function(){
			tips.close();
			playVedioStart(videoInfo);
		});
	}
	return playReady;
});
