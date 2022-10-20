kodReady.push(function(){
	var playerSupport = function(){
		var support = {
			wap:{//移动端
				music:['mp3','m4a','aac'],
				movie:['mp4','m4v','mov']
			},
			ie:{
				music:['mp3','m4a','aac'],
				movie:['mp4','m4v','mov' ,  'flv','f4v']
			},
			chrome:{//default chrome,firefox,edge
				music:['mp3','wav','aac',	'm4a','oga','ogg','webma','flac'],
				movie:['mp4','m4v','mov',	'f4v','flv','ogv','webm','webmv']
			}
			//safari 已经禁用了flash
		};
		var res = support.chrome;
		if($.isWap){
			res = support.wap;
		}else if(!!window.ActiveXObject || "ActiveXObject" in window){
			res = support.ie;
		}
		var inits = _.union(res.music, res.movie);
		var fileExt = "{{config.fileExt}}" ? "{{config.fileExt}}".split(",") : [];
		return _.intersection(inits, fileExt).join(',');
	}
	var myPlayer;
	var loadMyPlayer = function(callback){
		var appStatic = "{{pluginHost}}static/";
		var appStaticDefault = "{{pluginHostDefault}}static/";
		if(myPlayer){
			callback(myPlayer);
		}else{
			// var top = ShareData.frameTop();
			var top = window;
			top.requireAsync(appStatic+'page.js',function(app){
				if(!myPlayer){
					myPlayer = app;
					myPlayer.init(appStatic,appStaticDefault);
				}
				callback(myPlayer);
			});
		}
		
		if($.isWap && !window.jplayerInit){
			window.jplayerInit = true;
			$(".jPlayer-music .play-list .remove").trigger("click");
			$.addStyle('.music-player-dialog{visibility:visible;}');
		}
	};

	Events.bind('explorer.kodApp.before',function(appList){
		appList.push({
			name:'{{package.id}}',
			title:'{{package.name}}',
			sort:"{{config.fileSort}}",
			ext:playerSupport(),
			icon:'{{pluginHost}}static/images/icon.png',
			callback:function(path,ext,name){
				var music = ['mp3','wav','aac','m4a','oga','ogg','webma','m3u8a','m3ua','flac'];
				//移动端，非视频文件分享页面用跳转方式打开
				var link = core.path2url(path,true);
				if($.isWap && $.inArray(ext, music) == -1){ 
					return core.openWindow(link);
				}
				var list = [{
					url:link,path:path,ext:ext,
					name:name,//zip内文件播放
				}];
				loadMyPlayer(function(player){
					player.play(list);
				});
			}
		});
	});

	// 移动端安卓首次打开播放器不自动播放问题处理；
	if($.isWap){
		$.addStyle('.music-player-dialog{visibility:hidden;}');
	}

	//音效播放绑定
	Events.bind('playSound',function(url){
		loadMyPlayer(function(player){
			player.playSound(url);
		});
	});

	//多选含有音乐右键菜单
	var menuShow = function(menu,app){
		if(!menu.$menu.find('.playMedia').exists() ){
			$.contextMenu.menuAdd({
				'playMedia':{
					name:LNG['explorer.addToPlay'],
					className:"play-media hidden",
					icon:"x-item-icon x-mp3",
					accesskey: "p",
					callback:function(){
						var musicFiles = selectMusicFiles(app);
						loadMyPlayer(function(player){
							player.play(musicFiles);
						});
					},
				}
			},menu,'','.copy-to');
		}
		var musicFiles = selectMusicFiles(app);
		var method = _.isEmpty(musicFiles) ? 'menuItemHide':'menuItemShow';
		$.contextMenu[method](menu,'playMedia');
	}
	Events.bind({
		'rightMenu.beforeShow@.menu-path-more':menuShow,
		'rightMenu.beforeShow@.menu-toolbar-source-more':menuShow,
		'rightMenu.beforeShow@.menu-path-guest-more':menuShow,
		'rightMenu.beforeShow@.menu-toolbar-pathDefault-more':menuShow,
		'rightMenu.beforeShow@.menu-simple-more':menuShow,
		'rightMenu.beforeShow@.menu-toolbar-userRencent-more':menuShow,
	});

	var selectMusicFiles = function(app){
		var list = [];
		var listSelect = app.pathAction.makeParamSelect();
		var supportExt = 'mp3,aac,oga,ogg,webma,flac'.split(',');
		_.each(listSelect,function(item){
			if(!item.ext || !_.includes(supportExt,item.ext)) return; // 过滤非音乐文件;
			if(!app.pathAction.auth.canRead(item)) return; //必须有读取权限;
			list.push({
				url:core.path2url(item.path),
				name:item.name,path:item.path,
				ext:item.ext
			});
		});
		return list;
	}
});
