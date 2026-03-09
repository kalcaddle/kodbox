kodReady.push(function(){
	var staticPath = "{{pluginHost}}static/";
	var version = '?v={{package.version}}';
	LNG.set(jsonDecode(urlDecode("{{LNG}}")));

	var impPage = null;
	Events.bind('router.after.admin/storage/index', function(){
		var self = this;
		if (impPage) return impPage.initView();
		requireAsync(staticPath+'page/index.js'+version, function(ImpPage){
			impPage = new ImpPage({parent:self});
			impPage.initView();
		});
		// kodApi.requestSend('plugin/storeImport/fileHashSet',false,function(result){});
	});
	if($.hasKey('plugin.{{package.id}}.style')) return;
	requireAsync("{{pluginHost}}static/main.css");
});