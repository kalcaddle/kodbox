(function(){
	$('body').delegate('.check-libreoffice','click',function(){
		var soffice = $(this).parents('.setting-content').find('[name=lbSoffice]').val();
		if(soffice == '') return $(this).parents('.setting-content').find('[name=lbSoffice]').focus();
		var url = G.kod.appApi + 'plugin/officeViewer/libreOffice/index/check&soffice=' + soffice;
		var options = {width:640,height:420}
		core.openDialog(url,'','服务器检测',false,options);
	});
})();

