kodReady.push(function(){
	var staticPath	= "{{pluginHost}}static/";
	var version		= '?v={{package.version}}';
	G.clientOption  = {
		appLoginApp: "1",
		appLoginAppConfirm: "0",
		appLoginWeb: "1",

		backupOpen: "1",
		fileOpenSupport: "1",
		backupAuth: '{"all":"1","user":"","group":"","role":""}',
		fileOpen: '[{"ext":"doc,docx,ppt,pptx,xls,xlsx,dwg,dxf,dwf","sort":"10000"}]',
	};
	_.extend(G.clientOption,jsonDecode(urlDecode("{{config}}")));
	
	LNG.set(jsonDecode(urlDecode("{{LNG}}")));
	Events.bind("admin.leftMenu.before",function(menuList){
		menuList.push({
			title:LNG['client.menu'],
			icon:"ri-computer-line",
			link:"admin/storage/client",
			after:'admin/storage/share',//after/before; 插入菜单所在位置;
			sort:10,
			fileSrc:staticPath+'clientOpen.js',
		});
	});

	// web端已登录,App登录界面,可以通过扫码登录App(web端个人中心-二维码)
	requireAsync(staticPath+'style.css');
	ClassBase.extendHook({
		hookMatch:'menuItemMaxNumber,initMenuList,changeLanguage',	
		bindDarkMode:function(){
			this.__bindDarkMode.apply(this,arguments);
			if(G.clientOption.appLoginApp == '0') return;
			showLoginAppView($('.menuBar .user-info'));
			checkWebLoginAfter();
			checkWebLoginWebAfter();
		}
	});
	ClassBase.extendHook({
		hookMatch:'addMenuMake,menuItem,bindLocation',	
		bindLocation:function(){
			this.__bindLocation.apply(this,arguments);
			if(G.clientOption.appLoginApp == '0') return;
			showLoginAppView($('#app .setting-page .user-head'));
		}
	});
	
	var checkUrlAllow = function(url) {
		var urlInfo = $.parseUrl(url);
		if( urlInfo.host == 'localhost' || 
			urlInfo.host == '127.0.0.1'){
			Tips.tips(LNG['client.app.scanNotIP'],'warning',5000);
			return false;
		}
		return true;
	}
	var qrcodeDialog = function(urlPre,title,desc,urlAdd){
		if($('.qr-login-user-dialog').length) return;
		if(!checkUrlAllow(G.kod.APP_HOST)) return;
		
		var dialog = $.dialog({
			id:'qr-login-user-dialog',
			ico:"<i class='font-icon ri-qr-scan-line-2'></i>",
			width:'300px',height:'300px',fixed: true,
			padding:'20px 20px',title:title,
			content:'<div class="box disable-event"><div class="code"></div><div class="desc">'+desc+'</div></div>',
		});
		var $view = dialog.$main.find('.code').addClass('can-not-select');
		var render = function(){
			$.ajax({
				url:G.kod.APP_HOST+'?plugin/client/qrcodeToken',
				type:'POST',data:{t:time()},
				dataType:'json',
				success:function(data){
					if(!data || !data.code) return Tips.tips(data);
					var url = urlPre + data.data+ (urlAdd || '');
					var qrCodeImage = G.kod.APP_HOST+'?user/view/qrcode&url='+urlEncode(url);
					$view.html('<img src="'+qrCodeImage+'"/>');
					$view.animate({opacity:1},300);
					// console.log(url);$.copyText(url);
					// $.copyText(urlDecode($.parseUrl($('.qr-login-user-dialog img').attr('src')).params.url));
				}
			});
		}
		var makeQrcode = function(){
			$view.html('').css('opacity','0.01');
			requireAsync(staticPath+'qrcode.min.js',render);
		}
		$view.bind('click',makeQrcode);
		makeQrcode();
		setTimeout(function(){
			dialog.$main.find('.disable-event').removeClass('disable-event')
		},100);
		return dialog;
	}
		
	// 当前已登录; 扫码url访问切换账号;(token未登录则不处理,token对于账号和当前一致不处理;)
	var checkWebLoginAfter = function(){
		if(!_.get(Router,'query.loginAppID')) return;
		$.ajax({
			url:G.kod.APP_HOST+'?plugin/client/loginApp',
			dataType:'json',type:'POST',data:{token:Router.query.loginAppID},
			success:function(data){
				if(!data || !data.code) return Tips.tips(data,'warning',3000);
				var needReload = data.info == G.user.userID ? false:true;// 同一用户则刷新页面;
				loginLinkTo(Router.query._page,needReload);
			}
		});
	}
	
	var loginLinkTo = function(page,needReload){
		Tips.tips(LNG['common.loginSuccess'],true,3000);
		setTimeout(function(){
			if(page){Router.go(page);}
			if(needReload){window.location.reload();}
		},800);
	};
	var checkWebLoginBefore = function(viewLogin){
		var hash = $.parseUrlHash(_.get(Router,'query.link',''));
		if(!hash || !hash.query || !hash.query.loginAppID ) return;
		
		Router.setParam({link:''});Router.query.link = '';
		$.ajax({
			url:G.kod.APP_HOST+'?plugin/client/loginApp',dataType:'json',
			type:'POST',data:{token:hash.query.loginAppID},
			success:function(data){
				if(!data || !data.code) return Tips.tips(data,'warning',5000);
				viewLogin.userApi.reloadMainView().then(function(){
					loginLinkTo(hash.query._page);
				});
			}
		});
	};
	var showLoginAppView = function($parent){
		if(!$parent.length) return;
		if($parent.find('.qr-login-user').length) return;
		var icon  = "<i class='font-icon ri-qr-code-line'></i>";
		var title = LNG['client.app.loginApp'];
		var html  = "<div class='qr-login-user ripple-item' title='"+title+"' title-timeout='100'>"+icon+"</div>";
		var $view = $(html).appendTo($parent);
		$view.bind('mouseup',function(e){
			showLoginAppDialog();
			$('body').trigger('mouseup');
			return stopPP(e);
		});
		$view.bind('click',function(e){
			$('body').trigger('click');
			return stopPP(e);
		});
		setTimeout(function(){$view.addClass('animate');},300);
	};
	var showLoginAppDialog = function(){
		if(!checkUrlAllow(G.kod.APP_HOST)) return;
		var desc = LNG['client.app.loginAppDesc']+'<br/>'+LNG['client.app.scanVersion'];
		var urlPre  = G.kod.APP_HOST + '#?loginAppID=';
		var dialog  = false;
		var success = function(){
			var urlAdd = '&_page='+urlEncode($.parseUrl().hash);
			qrcodeDialog(urlPre,LNG['client.app.loginApp'],desc,urlAdd);
			dialog && dialog.close();
		};
		var checkPass = function(){
			var pass = _.trim($input.val());
			if(!pass) return error();
			var sign = roundString(5);
			$.ajax({
				url:G.kod.APP_HOST+'?plugin/client/checkPass',
				type:'POST',data:{sign:sign,pass:authCrypt.encode(pass,sign)},
				dataType:'json',
				success:function(data){
					Tips.tips(data);
					if(!data || !data.code) return error();
					success();
				}
			});
			return false;
		};
		var error = function(){
			dialog.$main.shake();$input.val('');
			setTimeout(function(){$input.focus();},300);
			return false;
		}

		if(G.clientOption.appLoginAppConfirm == '0'){return success();}
		dialog = $.dialog.prompt(LNG['client.app.loginAppConfirmTips'],function(){return checkPass();});
		var $box = dialog.$main.find('.prompt-input');
		$box.html('<input type="password" style="padding:5px 10px">');
		var $input = $box.find('input').focus();
		$input.keyEnter(function(e){checkPass();})
	};
	
	// ===============================================================
	// App已登录,扫描web端登录界面二维码登录web(App个人中心-扫描)
	ClassBase.extendHook({
		hookMatch:'login,bindAutoLogin,autoSetAccount,loginSuccess',	
		bindEvent:function(){
			this.__bindEvent.apply(this,arguments);
			showLoginTfaView(this);
			if(G.clientOption.appLoginWeb == '1'){
				checkWebLoginBefore(this);
				showLoginWebView(this);
			}
		},
		loginSuccess: function(){
			var _this = this;
			var _args = arguments;
			// 判断是否需要二次验证，不需要则继续提交
			if (this.withTfa || !_.has(tfaIn,'withTfa') || !_this.tfaPage) {
				return _this.__loginSuccess.apply(_this,_args);
			}
			_this.tfaPage.needTfa(tfaIn,tfaInfo);
		}
	});
		
	var showLoginWebView = function(viewLogin){
		var $parent = $('.login-form');
		if($parent.find('.qr-login-user').length) return;
		var icon  = "<i class='font-icon ri-qr-code-line'></i>";
		var title = LNG['client.app.loginWeb'];
		var html  = "<div class='qr-login-user ripple-item' title='"+title+"' title-timeout='100'>"+icon+"</div>";
		var $view = $(html).appendTo($('.login-form'));
		$view.bind('mouseup',function(e){
			showLoginWebDialog(viewLogin);
		});
		setTimeout(function(){$view.addClass('animate');},300);
	};
	var showLoginWebDialog = function(viewLogin){
		var desc = LNG['client.app.loginAppDesc']+'<br/>'+LNG['client.app.scanVersion'];
		var urlPre = G.kod.APP_HOST + '#?loginWebID=';
		var dialog = qrcodeDialog(urlPre,LNG['client.app.loginWeb'],desc,'');
		if(!dialog) return;

		// 自动检查是否已经登录;
		var checkLogin = function(){
			$.getJSON(G.kod.APP_HOST+'?plugin/client/check',function(data){
				if(!checkTimter) return;
				checkTimter = setTimeout(checkLogin,1000);
				if(!data || !data.code){
					if(_.isObject(data.data) && data.data.status == 'cancel'){
						Tips.tips(LNG['common.cancel'],'warning',3000);
						dialog.$main.find('.code').trigger('click');//已取消,刷新;
					}
					return;
				}

				dialog.$main.find('.code').addClass('code-success');
				Tips.tips(LNG['common.loginSuccess'],true,3000);
				checkFinished();
				setTimeout(function(){viewLogin.loginSuccess();},2000);
			});
		};
		var checkFinished = function(){clearTimeout(checkTimter);checkTimter = false;}
		var checkTimter = setTimeout(checkLogin,1000);
		viewLogin.bind('onRemove',function(){dialog.close();});
		dialog.config.closeBefore = function(){
			checkFinished();
		};
	};
	
	// App扫码登录web端; 支持浏览器或微信等扫描访问;
	var checkWebLoginWebAfter = function(){
		var token = _.get(Router,'query.loginWebID','');
		if(!token) return;
		Router.setParam({loginWebID:''});Router.query.loginWebID = '';

		var confirmDesc = LNG['client.app.confirmTips']+"("+htmlEncode(G.user.info.name)+")";
		var content = "\
		<div class='main'><div class='box'>\
		<i class='font-icon ri-computer-line'></i>\
		<i class='title'>"+LNG['client.app.confirmTitle']+"</i>\
		<i class='desc'>"+confirmDesc+"</i>\
		</div>\
		<div class='kui-btn btn-confirm kui-btn-blue'>"+LNG['client.app.confirmOk']+"</div>\
		<div class='kui-btn btn-cancel'>"+LNG['common.cancel']+"</div></div>";
		var dialog = $.dialog({
			id:'qr-login-confirm-dialog',
			ico:"<i class='font-icon ri-login-circle-line'></i>",
			title:LNG['client.app.confirmTitle'],
			content:content,
			width:'300px',height:'400px',fixed: true,
			closeBefore:function(){loginConfirm();}
		});

		var post   = {token:token},isConfirm = false;
		var apiUrl = G.kod.APP_HOST+'?plugin/client/loginWeb';
		var requestSend = function(){
			$.ajax({
				url:apiUrl,type:'POST',data:post,dataType:'json',
				success:function(data){
					if(post.status != '1') return;
					Tips.tips(data);
				}
			});dialog.close();
		}
		var loginConfirm = function(status){
			if(isConfirm) return;
			isConfirm = true;
			if(!status) return requestSend();

			$.dialog.confirm(confirmDesc,function(){
				post.status = '1';
				requestSend();
			},function(){requestSend();},LNG['client.app.confirmOk']);
		}
		dialog.$main.find('.btn-confirm').bind('click',function(){loginConfirm(true);});
		dialog.$main.find('.btn-cancel').bind('click',function(){loginConfirm();});
	};

	// 后台：二次验证设置
	Events.bind("admin.setting.initViewBefore",function(formData, options, self){
		if (formData.tfaOpen) return;
		// 插入tabs键名
		var safe = _.split(_.get(formData,'formStyle.tabs.safe'),',');
		var idex = _.findIndex(safe,function(val){return val == 'needCheckCode';});
		safe.splice(idex+1, 0, 'tfaOpen','tfaType');
		_.set(formData,'formStyle.tabs.safe', _.join(safe,','));

		// 追加数据，无法实现插入到指定位置
		formData.tfaOpen = {
			"type":"switch",
			"value":_.get(G,'system.options.tfaOpen') || '0',
			"display":LNG['client.tfa.tfaOpen'],
			"desc":LNG['client.tfa.tfaOpenDesc'],
			"switchItem":{"0":"","1":"tfaType"}
		};
		formData.tfaType = {
			// "type":"radio",
			"type":"checkbox",
			"value":_.get(G,'system.options.tfaType') || "",
			"display":LNG['client.tfa.tfaType'],
			"desc":LNG['client.tfa.tfaTypeDesc'],
			"info":{"email":LNG['client.tfa.email'],"phone":LNG['common.phoneNumber']}
		};
	});
	// 移动追加项位置
	Events.bind('admin.setting.initViewAfter',function(_this){
		if (!_this.$('.form-row.item-tfaOpen').length) return;
		$('.form-row.item-needCheckCode').after($('.form-row.item-tfaOpen,.form-row.item-tfaType'));
		if (_.get(G, 'kod.versionType') == 'A') {
			$('.form-row.item-tfaType input[value=phone]').prop("checked", false).prop('disabled',true);
		}
	});

	// 登录页追加二次验证项；相较于router.after.user/login事件，这个只会触发一次，故不做重复拦截
	var showLoginTfaView = function(_this) {
		var callback = arguments[1] || null;
		requireAsync([
			staticPath+'tfa/index.js'+version,
			staticPath+'tfa/index.css'+version,
		],function(LoginTfa){
			_this.tfaPage = new LoginTfa({parent:_this});
			callback && callback();
		});
	}
	var tfaIn = null;
	var tfaInfo = null;
	Events.bind('RequestAfter[login]',function(result,param){
		if (!result || !result.code) return;
		// if (!_.has(result,'data.tfaOpen') && _.get(result,'info')) {
		if (!_.get(result,'data.tfaOpen')) {
			tfaIn = null; tfaInfo = null;
			return;
		}
		tfaIn = param;
		tfaInfo = result.data;
	});
	Events.bind('RequestBefore[login]',function(param){
		param.withTfa = false;
	});

	if($.hasKey('plugin.client.event')) return;
	// 客户端下载；js文件为异步加载，hook不能放在js文件中调用
	var clientDown = null;
	var clientDownload = function(_this, type){
		var data = {parent:_this, pluginApi: "{{pluginApi}}", type: type};
		if (clientDown) return clientDown.bindEvent(data);
		requireAsync([
			staticPath+'down/index.js',
			staticPath+'down/index.css',
		],function(Down){
			clientDown = new Down({parent:self});
			clientDown.bindEvent(data);
		});
	}
	// 左下角菜单
	ClassBase.extendHook({
		hookMatch:'initMenuList,loadFooter,changeLanguage',
		hookInitAfter:function(){
			clientDownload(this, 'menu');
		},
	});
	// 登录界面
	if(window.kodWeb) return;
	ClassBase.extendHook({
		hookMatch:'loginSave,loginSuccess',
		init:function(){
			this.__init.apply(this,arguments);
			clientDownload(this, 'login');
		}
	});
});