kodReady.push(function(){
	G.clientOption = jsonDecode(urlDecode("{{config}}"));
	LNG.set(jsonDecode(urlDecode("{{LNG}}")));
	Events.bind("admin.leftMenu.before",function(menuList){
		menuList.push({
			title:LNG['client.menu'],
			icon:"ri-computer-line",
			link:"admin/storage/client",
			after:'admin/storage/share',//after/before; 插入菜单所在位置;
			sort:10,
			fileSrc:'{{pluginHost}}static/clientOpen.js',
		});
	});
	
	// web端已登录,App登录界面,可以通过扫码登录App(web端个人中心-二维码)
	requireAsync('{{pluginHost}}/static/style.css');
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
	var qrcodeDialog = function(urlPre,title,desc){
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
					var url = urlPre + data.data;
					var qrCodeImage = G.kod.APP_HOST+'?user/view/qrcode&url='+urlEncode(url);
					// console.log(111,url);$.copyText(url);
					$view.html('<img src="'+qrCodeImage+'"/>');
					$view.animate({opacity:1},300);
				}
			});
		}
		var makeQrcode = function(){
			$view.html('').css('opacity','0.01');
			requireAsync('{{pluginHost}}static/qrcode.min.js',render);
		}
		$view.bind('click',makeQrcode);
		makeQrcode();
		setTimeout(function(){
			dialog.$main.find('.disable-event').removeClass('disable-event')
		},1000);
		return dialog;
	}
		
	// 当前已登录; 扫码url访问切换账号;(token未登录则不处理,token对于账号和当前一致不处理;)
	var checkWebLoginAfter = function(){
		var token = _.get(Router,'query.loginAppID','');
		if(!token) return;

		var apiUrl = G.kod.APP_HOST+'?plugin/client/loginApp';
		Router.setParam({loginAppID:''});Router.query.loginAppID = '';
		$.ajax({
			url:apiUrl,dataType:'json',
			type:'POST',data:{token:token},
			success:function(data){
				if(!data || !data.code) return Tips.tips(data,'warning',3000);
				if(data.info == G.user.userID) return;// 同一用户则不处理;
				Tips.tips(LNG['common.loginSuccess'],true);
				setTimeout(function(){window.location.reload();},1000);
			}
		});
	}
	var checkWebLoginBefore = function(viewLogin){
		var theLink  = _.get(Router,'query.link','');
		var matchKey = '#?loginAppID=';
		if(!theLink || theLink.indexOf(matchKey) < 1 ) return;
		
		var token  = theLink.substr(theLink.indexOf(matchKey) + matchKey.length);
		var apiUrl = G.kod.APP_HOST+'?plugin/client/loginApp';
		Router.setParam({link:''});Router.query.link = '';
		$.ajax({
			url:apiUrl,dataType:'json',
			type:'POST',data:{token:token},
			success:function(data){
				if(!data || !data.code) return Tips.tips(data,'warning',5000);
				Tips.tips(LNG['common.loginSuccess'],true,3000);
				setTimeout(function(){viewLogin.loginSuccess();},1500);
				setTimeout(function(){Router.setParam({loginAppID:''});},2000);
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
			qrcodeDialog(urlPre,LNG['client.app.loginApp'],desc);
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
			if(G.clientOption.appLoginWeb == '0') return;
			checkWebLoginBefore(this);
			showLoginWebView(this);
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
		var dialog = qrcodeDialog(urlPre,LNG['client.app.loginWeb'],desc);
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
	}
});