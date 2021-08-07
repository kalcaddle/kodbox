kodReady.push(function(){
	Events.bind('main.menu.loadBefore',function(listData){ //添加到左侧菜单栏
		listData['{{package.id}}'] = {
			name:"{{package.name}}",
			url:'{{pluginHost}}adminer/',
			subMenu:'{{config.menuSubMenu}}',
			target:'{{config.openWith}}',
			icon:'ri-database-fill-2 bg-blue-7'
		}
	});
});
