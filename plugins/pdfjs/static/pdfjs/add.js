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

var pdfLoaded = function(){
	// console.log(33313,fileName,PDFViewerApplication,PDFViewerApplication.eventBus);
	var isLoad = false;
	PDFViewerApplication.eventBus.on('pagerender',function(){
		if(isLoad) return;
		isLoad = true;
		document.title = fileName;
		setTimeout(function(){document.title = fileName;},100);
		
		// 自适应界面; bug: 缩放后文字选中异常;
		if($.isWindowTouch() && $.isWindowSmall() ){
			PDFViewerApplication.pdfViewer._setScale("page-fit"); // 全屏;
		}
	});	
}

$(document).ready(function (){
	$('.print,.download').hide();
	var checkTimer = setInterval(function(){
		if(!window.PDFViewerApplication || !PDFViewerApplication.eventBus) return;

		clearInterval(checkTimer);
		pdfLoaded();
		enablePinchZoom();
		
		setTimeout(function(){
			if(pdfOptions.canDownload == '1'){
				$('.print,.download').show();
				return;
			}
			PDFViewerApplication.eventBus._listeners['print'] = [];
			PDFViewerApplication.eventBus._listeners['afterprint'] = [];
			PDFViewerApplication.eventBus._listeners['beforeprint'] = [];
			PDFViewerApplication.supportsPrinting = false;
			window.print = function(){}
			$('.print,.download').remove();
		},200);
	},50);
});

// / Pinch Zoom
function enablePinchZoom(){
	var startX = 0; 
	var	startY = 0;
	var pinchOffset = 0;
	var pinchScale = 1;
	var $viewer = $('#viewer');
	var $container = $('#viewerContainer');
	var pdfViewer  = PDFViewerApplication.pdfViewer;
	var reset = function(){
		startX = startY = pinchOffset = 0; 
		pinchScale = 1; 
	};
	$viewer.bind('touchstart',function(e){
		if (e.touches.length > 1) {
			startX = (e.touches[0].pageX + e.touches[1].pageX) / 2;
			startY = (e.touches[0].pageY + e.touches[1].pageY) / 2;
			pinchOffset = Math.hypot((e.touches[1].pageX - e.touches[0].pageX), (e.touches[1].pageY - e.touches[0].pageY));
		} else {
			pinchOffset = 0;
		}
	}).bind('touchmove',function(e){
		if(e.touches.length < 2) return;
		if (pinchOffset <= 0 || e.touches.length < 2) return;
		var pinchDistance = Math.hypot((e.touches[1].pageX - e.touches[0].pageX), (e.touches[1].pageY - e.touches[0].pageY));
		var originX = startX + $container[0].scrollLeft;
		var originY = startY + $container[0].scrollTop;
		pinchScale = pinchDistance / pinchOffset;
		$viewer.css({
			transform:"scale("+pinchScale+")",
			transformOrigin:originX+"px "+originY+"px"
		});		
	}).bind('touchend',function(e){
		if(pinchOffset <= 0) return;
		
		$viewer.css({transform:"none",transformOrigin:"unset"});					
		var toScale = pdfViewer.currentScale * pinchScale;
		toScale = toScale < 0.3 ? 0.3:toScale;
		toScale = toScale > 5 ? 5:toScale;
		pdfViewer.currentScale = toScale;
		// pdfViewer._setScale(toScale,true);

		var rect = $container[0].getBoundingClientRect();
		var dx = startX - rect.left;
		var dy = startY - rect.top;
		$container[0].scrollLeft += dx * (pinchScale - 1);
		$container[0].scrollTop += dy * (pinchScale - 1);
		
		$container.hide();//避免缩放后闪烁;
		setTimeout(function(){
			$container.show();
		},10);
		reset();
	}).bind('dblclick',function(e){
		if( $.isWindowTouch() ){
			pdfViewer._setScale("page-fit"); // 全屏;
		}
	});
}