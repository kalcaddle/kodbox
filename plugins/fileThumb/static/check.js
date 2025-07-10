(function(){
	$('body').delegate('.check-psd-server,.check-imgnry-server','click',function(){
		var type = $(this).hasClass('check-imgnry-server') ? '&type=imgnry' : '';
		var $dialog = $(this).parents('.app-config-fileThumb');
		$dialog.find('[name=pluginSaveKeepOpen]').val(1);
        $dialog.find('.aui-footer .aui-state-highlight').click();
		_.delay(function(){
            $dialog.find('[name=pluginSaveKeepOpen]').val(0);
			var url = G.kod.appApi + 'plugin/fileThumb/check'+type;
			var options = {width:640,height:420}
			core.openDialog(url,'',LNG['fileThumb.check.title'],false,options);
		},200);
	});
	$('body').delegate('.convert-stop-all','click',function(){
		var url = G.kod.appApi + 'plugin/fileThumb/check&action=stopAll';
		var options = {width:640,height:420}
		core.openDialog(url,'',"Stop All Task",false,options);
	});
	$('body').delegate('.check-psd-help','click',function(){
		if(_.get(G,'kod.systemOS') == 'linux'){
			window.open('http://doc.kodcloud.com/vip/#/psd/linux');
		}else{
			window.open('http://doc.kodcloud.com/vip/#/psd/win');
		}
	});
	$('body').delegate('.check-imgnry-help','click',function(){
		// https://github.com/h2non/imaginary
		window.open('https://docs.kodcloud.com/setup/thumbnail/');
	});

	// 查看日志
	var openWindow = function(url,title,width,height){
		title  = title?title:LNG['common.tips'];
		width  = width?width:'80%';
		height = height?height:'70%';
		if($.isWap){
			width = "100%";height = "100%";
		}
		var dialog = $.dialog.open(url,{
			ico:"",title:title,
			fixed:true,resize:true,
			width:width,height:height,
			className: 'dialog-no-title'
		});
		return dialog;
	};
	$('body').delegate('.view-log','click',function(){
		var timeShow = dateFormat(time(),"20y_m_d");
		var file = "./data/temp/log/filethumb/"+timeShow+"__log.php";
		var param = "dataArr="+ jsonEncode([{"type":"file","path":file}]);
			param += '&CSRF_TOKEN='+Cookie.get('CSRF_TOKEN');
		$.ajax({
			url:G.kod.appApi + 'explorer/index/pathInfo',
			type:'POST',
			dataType:'json',
			data:param,
			success: function(result){
				if(! result.code){
					Tips.tips('今日暂无日志！','warning');
				}else{
					openWindow("./#fileView&path="+result.data.path);
				}
			}
		});
	});	
})();