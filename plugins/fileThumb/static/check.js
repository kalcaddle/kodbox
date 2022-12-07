(function(){
	$('body').delegate('.check-psd-server','click',function(){
		var url = G.kod.appApi + 'plugin/fileThumb/check';
		var options = {width:640,height:420}
		core.openDialog(url,'',LNG['fileThumb.check.title'],false,options);
	});
	$('body').delegate('.convert-stop-all','click',function(){
		var url = G.kod.appApi + 'plugin/fileThumb/check&action=stopAll';
		var options = {width:640,height:420}
		core.openDialog(url,'',"Stop All Task",false,options);
	});
	$('body').delegate('.check-psd-help','click',function(){
		if(G.systemOS == 'linux'){
			window.open('http://doc.kodcloud.com/vip/#/psd/linux');
		}else{
			window.open('http://doc.kodcloud.com/vip/#/psd/win');
		}
	});
})();