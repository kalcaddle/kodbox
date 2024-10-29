kodReady.push(function(){
	var staticPath = "{{pluginHost}}static/";
	var version = '?v={{package.version}}';

	var impPage = null;
	Events.bind('router.after.admin/storage/index', function(){
		var self = this;
		if (impPage) return impPage.initView();
		requireAsync(staticPath+'page/index.js'+version, function(ImpPage){
			impPage = new ImpPage({parent:self});
			impPage.initView();
		});
	});
	if($.hasKey('plugin.{{package.id}}.style')) return;
	var style = "\
		.storage-page .store-list-box .toolbar{display: flex;}\
		.storage-page .store-list-box .toolbar>div{flex: 1;}\
		.storage-page .store-list-box .toolbar>div.right{text-align: right;}";
	$.addStyle(style);
});