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
});
