/**
 * kod处理;
 * https://github.com/mozilla/pdf.js

 * 图片显示模糊，问题修复：https://github.com/mozilla/pdf.js/pull/13698/files
 * 版本更新：（v2.10.377->v2.5.207）：v2.5.207以上在钉钉中显示乱码
 * 版本更新：v5.3.31，修复pdf放大模糊问题
 */
var pdfLoaded = function(){
	// console.log(33313,fileName,PDFViewerApplication,PDFViewerApplication.eventBus);
	var isLoad = false;
	// PDFViewerApplication.preferences.set('disableAutoFetch',true);// 关闭全部下载,按需加载文件;
	PDFViewerApplication.eventBus.on('pagerender',function(){
		if(isLoad) return;
		isLoad = true;
		document.title = fileName;
		setTimeout(function(){document.title = fileName;},100);
		
		// 自适应界面; bug: 缩放后文字选中异常;
		if($.isWindowTouch() && $.isWindowSmall() && _.get(PDFViewerApplication, 'pdfViewer._setScale')){
			PDFViewerApplication.pdfViewer._setScale("page-fit"); // 全屏;
		}
	});
	// 监听错误信息
	if (!$('#errorMessage').length) {
		$('<div id="errorMessage" class="hidden"></div>').appendTo('#viewerContainer');
	}
	PDFViewerApplication.eventBus.on('documenterror', function (event) {
		$('#errorMessage').text('PDF Error: ' + event.message).removeClass('hidden');
	});
	PDFViewerApplication.eventBus.on('documentloaded', function () {
		$('#errorMessage').text('').addClass('hidden');
	});
}

$(document).ready(function (){
	$('#editorStampButton').parent().hide();	// 编辑或添加图像
	$('.hiddenMediumView,.visibleMediumView #secondaryPrint,.visibleMediumView #secondaryDownload').hide();
	var checkTimer = setInterval(function(){
		if(!window.PDFViewerApplication || !PDFViewerApplication.eventBus) return;

		clearInterval(checkTimer);
		pdfLoaded();
		searchAuto();
		setTimeout(function(){
			if(canDownload == '1'){
				$('.hiddenMediumView,.visibleMediumView #secondaryPrint,.visibleMediumView #secondaryDownload').show();

				// 修复: disableAutoFetch为true时，未浏览的页面未加载pdfPage，导致pageViewsReady为false，打印会报"此PDF未完成加载"错误
				// 在打印前预加载所有未加载的页面。
				var _originalPrint = window.print;
				var _printLoading = false;
				window.print = function(){
					var pdfViewer = PDFViewerApplication.pdfViewer;
					var pdfDocument = PDFViewerApplication.pdfDocument;
					if(!pdfViewer || !pdfDocument || pdfViewer.pageViewsReady){
						return _originalPrint();
					}
					if(_printLoading) return; // 防止重复触发
					_printLoading = true;

					// 获取每页视图对象，如果存在但尚未加载实际页面数据，则获取并绑定到视图
					var pagesCount = pdfViewer.pagesCount;
					var promises = [];
					for(var i = 0; i < pagesCount; i++){
						(function(idx){
							var pageView = pdfViewer.getPageView(idx);
							if(pageView && !pageView.pdfPage){
								promises.push(
									pdfDocument.getPage(idx + 1).then(function(pdfPage){
										if(!pageView.pdfPage){
											pageView.setPdfPage(pdfPage);
										}
									})
								);
							}
						})(i);
					}
					if(promises.length === 0){
						_printLoading = false;
						return _originalPrint();
					}
					// 等待页面加载完成
					var $mask = $('<div style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.4);z-index:99999;display:flex;align-items:center;justify-content:center;color:#fff;font-size:14px;pointer-events:none;">正在准备打印，请稍候...</div>').appendTo('body');
					Promise.all(promises).then(function(){
						$mask.remove();
						_printLoading = false;
						_originalPrint();
					}).catch(function(e){
						$mask.remove();
						_printLoading = false;
						console.error('Failed to load pages for printing:', e);
						_originalPrint();
					});
				};
				return;
			}
			// PDFViewerApplication.supportsPrinting = false;	// 无效，因为supportsPrinting是只读getter
			PDFViewerApplication.download = function(){};
			window.print = function(){};
			$('.hiddenMediumView,.visibleMediumView #secondaryPrint,.visibleMediumView #secondaryDownload').remove();
			$('.pdfViewer').addClass('no-select');
		},500);
	},50);
	
	var searchAuto = function(){
		var args = $.getUrlParam('args') || '';
		args = jsonDecode(urlDecode(args));
		if(!args || typeof(args) != 'object' || !args.search) return;
		
		$("#viewFindButton").trigger('click');
		$("#findInput").val(args.search);
		$('[for="findHighlightAll"]').trigger('click');
		setTimeout(function(){$("#findInput").focus();},500);
	}
	
	var changeFullscreen = function(change){
		var doc = document.documentElement;
		var isFullScreen = document.fullScreen || document.mozFullScreen || document.webkitIsFullScreen || document.msFullscreenElement;
		var exitFullscreen = document.exitFullscreen || document.msExitFullscreen || document.mozCancelFullScreen || document.webkitCancelFullScreen;
		var startFullscreen = doc.requestFullscreen || doc.mozRequestFullScreen || doc.webkitRequestFullscreen || doc.msRequestFullscreen;
		if(!exitFullscreen) return;
		if(change === true){!isFullScreen && startFullscreen.apply(doc,[]);}else{isFullScreen && exitFullscreen.apply(document,[]);}
	}
	$('<div class="exit-fullscreen">Esc</div>').appendTo('#viewerContainer');
	$('.exit-fullscreen').bind('click', function(){
		changeFullscreen(false);
	});
	$(document).bind('keyup',function(e){
		if(e.key == "Escape"){changeFullscreen(false);}
	});
});