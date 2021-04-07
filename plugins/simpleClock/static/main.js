kodReady.push(function(){
	if(!$.supportCss3() || $.isWap) return;
	
	//加载时钟挂件
	Events.bind('explorer.desktop.load',function(appList){
		$.artDialog.open("{{pluginApi}}",{
			title:"clock",
			top: 40,
			left:$(window).width() - 210,
			width:'200px',
			height:'200px',
			simple:true
		});
	});
});