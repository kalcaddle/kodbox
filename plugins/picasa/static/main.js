kodReady.push(function(){
	Events.bind('explorer.kodApp.before',function(appList){
		appList.push({
			name:"picasa",
			title:"{{LNG['admin.plugin.defaultPicasa']}}",
			ext:"{{config.fileExt}}",
			sort:"{{config.fileSort}}",
			icon:"x-item-icon x-png",
			callback:function(path,ext,name){
				var appStatic = "{{pluginHost}}static/";
				requireAsync(appStatic+'page.js',function(app){
					app(path,ext,name,appStatic)
				});
			}
		});
	});
});