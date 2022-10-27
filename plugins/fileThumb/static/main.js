// imageExifPlugin
kodReady.push(function(){
	//打开方式关联; fileShowView 预览文件处理掉;
	LNG.set(jsonDecode(urlDecode("{{LNG}}")));
	Events.bind('explorer.kodApp.before',function(appList){
		appList.push({
			name:'{{package.id}}',
			title:'{{package.name}}',
			ext:"{{config.fileExt}}",
			sort:"{{config.fileSort}}",
			icon:'x-item-icon x-psd',
			callback:function(path,ext,name){
				var url = '{{pluginApi}}cover&size=1200&path='+urlEncode(path)+'&name=/'+name;
				kodApp.open(url,'png',name);
			}
		});
		if("{{config.videoConvert}}" == '1'){kodApp.videoLoadSmall = videoLoadSmall;}
	});
	Events.bind("admin.leftMenu.before",function(menuList){
		menuList.push({
			title:LNG['fileThumb.meta.name']+'&'+LNG['fileThumb.video.title'],
			icon:"ri-movie-line-2",
			link:"admin/storage/fileThumb",
			after:'admin/storage/share',//after/before; 插入菜单所在位置;
			sort:50,
			pluginName:"{{pluginName}}",
		});
	});
	
	var status = {
		STATUS_SUCCESS	:2,	//已转码成功; 播放标清视频
		STATUS_IGNORE	:3,	//忽略转换,大小格式等不符合; 提示并播放原视频
		STATUS_ERROR	:4,	//转换失败,ffmepg不存在等原因; 提示并播放原视频
		STATUS_RUNNING	:5,	//转换进行中; 提示并播放原视频; 可以切换到原视频; 转换完成可以切换为标清;
		STATUS_LIMIT	:6,	//任务数达到上限,忽略该任务. 提示并播放原视频
	};
	var autoPlayFast = "{{config.videoPlayType}}" == 'normal';
	var videoLoadSmall = function(filePath,kodApp,$target,success){
		var timeout = 1000,delay = false;
		var api = '{{pluginApi}}videoSmall&noOutput=1&path='+filePath;
		var lastAjax = false;
		var request  = function(){
			lastAjax = $.ajax({url:api,dataType:'json',success:function(data){
				// console.log(111,arguments);
				if(!data){delay = setTimeout(request,timeout);return;}
				if(data && (!data.data || !data.code)) return;
				
				var runInfo = data.data;
				if(runInfo.status == status.STATUS_SUCCESS){
					if(autoPlayFast){tips(runInfo.msg);}
					success(runInfo.data,runInfo.size,autoPlayFast);return;
				}
				if(runInfo.status == status.STATUS_RUNNING){
					tipsProgress(runInfo.msg,runInfo.data);
					delay = setTimeout(request,timeout);return;
				}
				if(runInfo.status == status.STATUS_IGNORE){runInfo.msg = '';}
				tips(runInfo.msg,runInfo);
				success(false);
			}});
		};

		var onClose = function(){
		    if(delay){clearTimeout(delay);}
		    if(lastAjax){lastAjax.abort();}
			kodApp.unbind('vedioOnClose',onClose);
		};
		kodApp.bind('vedioOnClose',onClose);
		
		var $progress = false;
		var tips = function(message,runInfo){
			if($progress){
				$progress.hide().fadeOut(200,function(){
					$progress.remove();$progress = false;
				});
				onClose();
			}
			if(!message) return;
			
			var tipsTime = 2500;
			var $tips = $('<div class="video-show-tips"></div>').insertAfter($target);
			if(runInfo && runInfo.status == status.STATUS_ERROR){
				$tips.css({'background':'rgb(255,100,100,0.5)'});
				tipsTime = 6000;console.warn(message);
			}
			$tips.html(message).hide().fadeIn(300);
			$tips.delay(tipsTime).fadeOut(300,function(){$tips.remove();});
		};
		var tipsProgress = function(title,info){
			if(!$progress){
				$progress = $('<div class="video-show-tips show-progress"></div>').insertAfter($target);
				$progress.hide().fadeIn(200);
			}
			var needTime = LNG['explorer.upload.needTime'] + parseInt(info.timeNeed) + 's';
			var percent  = (info.taskPercent * 100).toFixed(2) + '%; ';
			if(!info || !info.timeNeed){needTime = '';}
			if(!info || !info.taskPercent){percent = '';}
			var message = title + ' ' + percent+needTime;
			$progress.html(message);
		}
		request();
	}
	
	

	var loadStyle = function(){
		var extArr 	= 'mov,webm,mp4,m4v,mkv,f4v,flv,ogv'.split(',');
		var style 	= '';
		var styleIcon = [],styleIconHover = [],styleIconList=[],styleIconSplit=[];
		_.each(extArr,function(ext){
			styleIcon.push('.file .path-ico.path-icon-'+ext+' .picture:before');
			styleIconHover.push('.file.hover .path-ico.path-icon-'+ext+' .picture:before');
			styleIconList.push('.file-list-list .file .path-ico.path-icon-'+ext+' .picture:before');
			styleIconSplit.push('.file-list-split .file .path-ico.path-icon-'+ext+' .picture:before');
		});
		// -webkit-backdrop-filter: blur(10px);backdrop-filter: blur(10px);\
		style += styleIcon.join(',') + "{\
			content:'\\f00a';font-family: 'remixicon';\
			position: absolute;margin: auto;z-index: 5;\
			left: 0;top: 0;right: 0;bottom: 0;\
			transition: 0.2s all;\
			width: 28px;height: 28px;line-height: 28px; font-size: 20px;\
			border-radius:50%;color: rgba(255,255,255,0.9);\
		}\
		.file .path-ico .picture:before{background: rgba(0,0,0,0.4);}\
		.file.hover .path-ico .picture:before{background: rgba(0,0,0,.6);color:#fff;}\
		.video-show-tips{\
			position:absolute;bottom:45px;right:20px;z-index:100;\
			background:rgba(0,0,0,0.4);color:#fff;pointer-events: none;\
			padding:4px 10px;border-radius:3px;opacity: 0.8;\
		}";
		style += styleIconList.join(',') +','+ styleIconSplit.join(',') + "{width:15px;height:15px;line-height:15px;font-size:14px;}";
		$.addStyle(style);
	}
	if(!$.hasKey('plugin.fileThumb.style')){loadStyle();}
});

