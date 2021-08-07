(function(){
	var ua 	= navigator.userAgent;
	$.browserIS = {
		ie: !!(window.ActiveXObject || "ActiveXObject" in window), //ie;ie6~11
		ie8: this.ie && parseInt($.browser.version) <= 8,//ie8
		wap:ua.match(/(iPhone|iPod|Android|ios|MiuiBrowser)/i),

		trident: ua.indexOf('Trident') > -1, //IE内核 
		presto: ua.indexOf('Presto') > -1, //opera内核 
		webKit: ua.indexOf('AppleWebKit') > -1, //苹果、谷歌内核 
		gecko: ua.indexOf('Gecko') > -1 && ua.indexOf('KHTML') == -1,//火狐内核 
		mobile: !!ua.match(/AppleWebKit.*Mobile.*/), //是否为移动终端  
		ios: !!ua.match(/\(i[^;]+;( U;)? CPU.+Mac OS X/), //ios终端  
		android: ua.indexOf('Android') > -1 || ua.indexOf('Adr') > -1, //android终端 
		iPhone: ua.indexOf('iPhone') > -1, //是否为iPhone
		iPad: ua.indexOf('iPad') > -1, //是否iPad 
		webApp: ua.indexOf('Safari') == -1, //是否web应该程序，没有头部与底部 
		weixin: ua.indexOf('MicroMessenger') > -1, //是否微信
		qq: ua.match(/\sQQ/i) == " qq" //是否QQ  
	};
	$.isIE 	= $.browserIS.ie;
	$.isIE8 = $.browserIS.ie8;
	$.isWap = $.browserIS.wap;
	$.isWindowSmall = function(){
		return $(window).width() < 769;//769 
	};
	var isTouch = (('ontouchstart' in window) || window.DocumentTouch && document instanceof DocumentTouch);
	$.isWindowTouch = function(){
		var res = isTouch || $.browserIS.iPad || $.browserIS.android || $.browserIS.mobile;
		return !!res;
	};
})();

// / Pinch Zoom
function enablePinchZoom(){
	var startX = 0; 
	var	startY = 0;
	var pinchOffset = 0;
	var pinchScale = 1;
	var $content = $('#root');
	var $viewer = $('#MainPanelContent');
	var reset = function(){
		startX = startY = pinchOffset = 0; 
		pinchScale = 1; 
	};
	$content.bind('touchstart',function(e){
		e = e.originalEvent;
		if (e.touches.length > 1) {
			startX = (e.touches[0].pageX + e.touches[1].pageX) / 2;
			startY = (e.touches[0].pageY + e.touches[1].pageY) / 2;
			pinchOffset = Math.hypot((e.touches[1].pageX - e.touches[0].pageX), (e.touches[1].pageY - e.touches[0].pageY));
		} else {
			pinchOffset = 0;
		}
	}).bind('touchmove',function(e){
		e = e.originalEvent;
		if(e.touches.length < 2) return;
		if (pinchOffset <= 0 || e.touches.length < 2) return;
		var pinchDistance = Math.hypot((e.touches[1].pageX - e.touches[0].pageX), (e.touches[1].pageY - e.touches[0].pageY));
		var originX = startX + $viewer[0].scrollLeft;
		var originY = startY + $viewer[0].scrollTop;
		pinchScale = pinchDistance / pinchOffset;
		$viewer.css({
			transform:"scale("+pinchScale+")",
			transformOrigin:originX+"px "+originY+"px"
		});		
	}).bind('touchend',function(e){
		if(pinchOffset <= 0) return;
		
		$viewer.css({transform:"none",transformOrigin:"unset"});
		var toScale = OfdCore.painter.zoom * pinchScale;
		toScale = toScale < 0.3 ? 0.3:toScale;
		toScale = toScale > 5 ? 5:toScale;
		OfdCore.setZoom(toScale);
		reset();
	});
}

$(document).ready(function (){
	touch.config.pinch = false;	
	enablePinchZoom();
	$("#printButton").next().remove();
	$('.anticon-folder-open').parent().addClass('toolbar-file-open');
	$('.anticon-folder-open').parent().find('span').remove();
	$("#more").remove();

	var $goFirst = $("#PageNumberInput").prev().prev();
	var $goEnd = $("#PageNumberInput").next().next().next();
	var $turnLeft = $goEnd.next().next();
	var $turnRight = $turnLeft.next();
	var $turnFull = $turnRight.next();
	$turnLeft.addClass('view-turn-left');
	$turnRight.addClass('view-turn-right');
	$turnFull.addClass('view-turn-full');
	
	$goFirst.remove();
	$goEnd.remove();
	
	setTimeout(function(){
		if(ofdReaderParams.canDownload == '1') return;
		window.print = function(){};
		OfdCore.print = function(){};
		$('#printButton,.toolbar-file-open').remove();
	},10);
	
	// 自适应界面; bug: 缩放后文字选中异常;
	window.addEventListener("OfdFileOpened",function(){
		if($.isWindowTouch()){
			$('#root').addClass('app-window-touch');
		}
		if($.isWindowSmall()){
			$('#root').addClass('app-window-small');
		}
		
		// if($.isWindowTouch() && $.isWindowSmall() ){
		// 	OfdCore.setZoom(-1); //自适应宽度; 浏览器会异常;
		// }else{
		// 	// OfdCore.setZoom(1.5);
		// }
	});
});