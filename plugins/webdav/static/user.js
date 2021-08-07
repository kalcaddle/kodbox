ClassBase.define({
	init: function(param){
		this.webdavPath = G.webdavOption.host;
		this.initParentView(param);
		this.initFormView(this.formData());
		this.initPath();
	},
	
	initPath: function(){
		if(G.webdavOption.pathAllow == 'self'){
			this.$('.item-openMore').remove();
			return;
		}

		var tpl = '{{each dataList item}}\
		<div class="row-item mb-10">\
			<span class="title" style="display:inline-block;width:100px;">{{item.title}}:</span>\
			<input type="text" value="{{item.value}}" readonly="" style="width:45%;" class="span-title-right">\
			<span class="input-title input-title-right kui-btn" action="copy">\
				<i class="font-icon ri-file-copy-line-2"></i>{{LNG["explorer.copy"]}}</span>\
		</div>\
		{{/each}}';
		var data = [
			{
				title:LNG['explorer.toolbar.myDocument'],
				value:this.webdavPath + urlEncode(LNG['explorer.toolbar.myDocument'])+'/'
			},
			{
				title:LNG['explorer.toolbar.myGroup'],
				value:this.webdavPath + urlEncode(LNG['explorer.toolbar.myGroup'])+'/'
			}
		];

		var $content = this.$('.item-pathAllowMore .info-alert');
		this.renderHtml(tpl,{dataList:data},$content);
		$content.find('[action]').bind('click',function(){
			var value = $(this).prev().val();
			$.copyText(value);
			Tips.tips(LNG['explorer.share.copied']);
		});
	},

	formData:function(){
		var pluginApi = API_HOST+'plugin/webdav/download';
		return {
			"formStyle":{"hideSave":"1",className:"form-box-title-block "},
			"detailAddress":{
				"type":"html",
				"display":"<b>webdav "+LNG['common.address']+"</b>",
				"value":"<input type='text' value='"+this.webdavPath+"' readonly style='width:70%;' class='span-title-right'/>\
				<span class='input-title input-title-right kui-btn' action='copy'><i class='font-icon ri-file-copy-line-2'></i>"+LNG['explorer.copy']+"</span>"
			},
			"openMore":{
				"type":"button",
				"className":"form-button-line",//横线腰线
				"value":"",//默认值；同checkbox
				"info":{
					"1":{ //按钮名称
						"display":LNG['webdav.user.morePath']+" <b class='caret'></b>",
						"className":"btn-default btn-sm",
					}
				},
				"switchItem":{"1":"pathAllowMore"}
			},
			"pathAllowMore":{
				"display":"",
				"value":"<div class='info-alert info-alert-grey p-10 align-left'></div><hr/>",
			},
			"help":{
				"display":"<b>"+LNG['webdav.help.title']+"</b>","value":
				"<div class='info-alert info-alert-green align-left can-select can-right-menu p-10 pl-30'>\
				<h6><i class='ri-windows-fill font-icon mr-5'></i>"+LNG['webdav.help.windows']+".\
				<p class='info-alert info-alert-green align-left mt-10'>"+LNG['webdav.help.windowsTips']+
				";  <a href='"+pluginApi+"' target='_blank' class='btn btn-sm btn-default' style='border-radius:3px;padding:2px 10px 1px 10px;'>"+LNG['common.download']+"</a></p></h6><hr/>\
				<h6><i class='ri-apple-fill font-icon mr-5'></i>"+LNG['webdav.help.mac']+"</h6>\
				<h6><i class='ri-ubuntu-fill font-icon mr-5'></i>"+LNG['webdav.help.others']+"</h6>\
				</div>"
			},
			
			"detail":{
				"display":"<b>"+LNG['common.tipsDesc']+"</b>","value":
				"<div class='info-alert info-alert-grey p-10 align-left can-select can-right-menu'>\
				<li>"+LNG['webdav.meta.desc']+"</li><hr/>\
				<li>"+LNG['webdav.tips.uploadUser']+"</li>\
				<li>"+LNG['webdav.tips.auth']+"\
				</div>"
			},
		}
	}
});