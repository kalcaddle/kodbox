ClassBase.define({
	init: function(param){
		this.initParentView(param);
		var package = this.formData();
		var form = this.initFormView(package); // parent: form.parent;
		form.setValue(G.clientOption);
	},
	
	saveConfig: function(data){
		if(!data) return false;
		Tips.loading(LNG['explorer.loading']);
		this.adminModel.pluginConfig({app:'client',value:data},Tips.close);
	},
	formData:function(){
		return{
		formStyle:{
			className:"form-box-title-block dialog-form-style-simple",
			tabs:{
				backup:"sep002,backupOpen,backupTipsOpen,backupTipsClose,backupAuth",
				open:"sep003,fileOpenSupport,fileOpen"
			},
			tabsName:{backup:LNG['admin.setting.sync'],open:LNG['explorer.sync.openLocal']}
		},
		backupOpen:{
			type:"switch",
			value:'1',
			display:LNG['client.option.backupOpen'],
			desc:LNG['client.option.backupOpenDesc'],
			switchItem:{"1":"backupAuth,backupTipsOpen","0":"backupTipsClose"},
		},
		backupTipsOpen:"<div class='info-alert info-alert-green'>"+LNG['client.option.backupTipsOpen']+"</div>",
		backupTipsClose:"<div class='info-alert info-alert-yellow'>"+LNG['client.option.backupTipsClose']+"</div>",
		backupAuth:{
			type:"userSelect",
			value:{"all":1},
			display:LNG['client.option.backupAuth'],
			desc:LNG['admin.plugin.authDesc'],
		},
		fileOpenSupport:{
			type:"switch",
			value:'1',
			display:LNG['client.option.fileOpenSupport'],
			desc:LNG['client.option.fileOpenSupportDesc'],
			switchItem:{"1":"fileOpen"},
		},
		fileOpen:{
			type:"table",
			info:{formType:'inline',removeConfirm:0},
			display:LNG['client.option.fileOpen'],
			desc:"<div class='info-alert mt-10 mb-10'>"+LNG['client.option.fileOpenDesc']+"<br/>\
			eg: doc,docx,ppt,pptx,xls,xlsx,pdf,ofd  sort:100</div>",
			children:{
				ext:{type:"textarea","display":LNG['client.option.fileOpenExt'],attr:{style:"width:100%;height:32px"}},
				sort:{type:"number","display":LNG['client.option.fileOpenSort'],attr:{style:"width:100px;"},value:"500"},
			}
		},
	}}
});