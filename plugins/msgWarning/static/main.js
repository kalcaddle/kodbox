kodReady.push(function(){
	var staticPath = "{{pluginHost}}static/";
	var version = '?v={{package.version}}';
	LNG.set(jsonDecode(urlDecode("{{LNG}}")));
	LNG['admin.menu.notice'] = LNG['msgWarning.main.kodNotice'];	// 原通知管理=>站内消息
	G.msgWarningOption = jsonDecode(urlDecode("{{config}}"));

	// 后台菜单
	Events.bind("admin.leftMenu.before",function(menuList){
		menuList.push({
			title:"{{package.name}}",
			icon:"ri-volume-up-fill",
			link:"admin/setting/warning",			
			after:'admin/setting/notice',//after/before; 插入菜单所在位置;
			fileSrc:'{{pluginHost}}static/admin/index.js',
		});
	});

	// 页面通知
	var ntcView = null;
	var showNtc = function(self){
		if (ntcView) return ntcView.showTips();
		requireAsync([
			staticPath+'msg/index.js'+version,
			staticPath+'msg/index.css'+version,
		], function(NtcView){
			ntcView = new NtcView({parent:self});
			ntcView.showTips();
		});
	}
	Events.bind('admin.leftMenu.after', function(_this){
		showNtc(_this);
	});
	Events.bind('router.after.explorer',function(_this){
		showNtc(_this);
	});
	Events.bind('router.after.desktop',function(_this){
		showNtc(_this);
	});
	// 会触发2次
	var isLoad=false;
	Events.bind('router.after.setting/user/index',function(_this){
		if (isLoad) return;
		isLoad=true;
		showNtc(_this);
	});
	// Events.bind('router.after', function(){
	// });

	if($.hasKey('plugin.msgWarning.style')) return;
	// requireAsync("{{pluginHost}}static/main.css");
});
