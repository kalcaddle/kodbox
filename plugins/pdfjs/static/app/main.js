kodReady.push(function(){
	if( !$.supportCanvas() ) return;
	Events.bind('explorer.kodApp.before',function(appList){
		appList.push({
			name:'{{package.id}}',
			title:'{{package.name}}',
			ext:"{{config.fileExt}}",
			sort:"{{config.fileSort}}",
			icon:'{{pluginHost}}static/app/images/icon.png',
			callback:function(){
				core.openFile('{{pluginApi}}',"{{config.openWith}}",_.toArray(arguments));
			}
		});
		$.addStyle(".x-item-icon.x-ofd{background-image:url('{{pluginHost}}static/ofd/img/icon.png');}");
		_.delay(function(){kodApp.remove('pdfView');},100);
	});
});
