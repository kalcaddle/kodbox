kodReady.push(function(){
	if( !$.supportCanvas() )return;
	Events.bind('explorer.kodApp.before',function(appList){
		appList.push({
			name:'{{package.id}}',
			title:'{{package.name}}',
			ext:"{{config.fileExt}}",
			sort:"{{config.fileSort}}",
			icon:'x-item-icon x-odt',
			callback:function(){
				core.openFile('{{pluginApi}}',"{{config.openWith}}",_.toArray(arguments));
			}
		});
	});
});