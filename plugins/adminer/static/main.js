kodReady.push(function(){
	var name = '{{package.name}}';
	Events.bind('main.menu.loadBefore',function(listData){ //添加到左侧菜单栏
		listData['{{package.id}}'] = {
			name:name,
			url:'{{pluginHost}}adminer/',
			subMenu:'{{config.menuSubMenu}}',
			target:'{{config.openWith}}',
			icon:'ri-database-fill-2 bg-blue-7'
		}
	});
	Router.mapIframe({page:name,title:name,url:'{{pluginHost}}adminer/',ignoreLogin:false});
});
