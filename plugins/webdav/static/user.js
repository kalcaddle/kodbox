ClassBase.define({
	init: function(param){
		this.webdavPath = G.webdavOption.host;
		this.initParentView(param);
		this.initFormView(this.formData());
		this.initPath();
	},
	
	initPath: function(){
		var self = this;
		if(G.webdavOption.pathAllow == 'self'){
			this.$('.item-openMore').remove();
			return;
		}

		var tpl = '{{each dataList item}}\
		<div class="row-item mb-10" tabindex="0">\
			<span class="title" style="display:inline-block;width:100px;">{{item.title}}:</span>\
			<input type="text" value="{{item.value}}" readonly="" style="width:45%;" class="span-title-right">\
			<span class="input-title input-title-right kui-btn" action="copy">\
				<i class="font-icon ri-file-copy-line-2"></i>{{LNG["explorer.copy"]}}</span>\
		</div>\
		{{/each}}';
		var data = [
			{
				title:LNG['explorer.file.address']+"(all)",
				value:this.webdavPath
			},
			{
				title:LNG['explorer.toolbar.fav'],
				value:this.webdavPath+urlEncode(LNG['explorer.toolbar.fav'])+'/'
			},
			{
				title:LNG['explorer.toolbar.myGroup'],
				value:this.webdavPath + urlEncode(LNG['explorer.toolbar.myGroup'])+'/'
			}
		];

		var $input   = this.$('.item-detailAddress input');
		var $content = this.$('.item-pathAllowMore .info-alert');
		$input.val(this.webdavPath + 'my/');
		this.renderHtml(tpl,{dataList:data},$content);
		this.$('[action]').bind('click',function(){
			var $btn   = $(this);
			var action = $btn.attr('action');
			switch (action) {
				case 'copy':
					$.copyText($btn.prev().val());
					Tips.tips(LNG['explorer.share.copied']);
					break;
				case 'selectPath':self.pathSelect();break;	
				default:break;
			}
		});
		this.$('.item-openMore .btn').trigger('click');
	},
	
	pathSelect:function(){
		var allowType = [
			LNG['explorer.toolbar.fav'],
			LNG['explorer.toolbar.rootPath'],
			LNG['explorer.toolbar.myGroup'],
			LNG['explorer.toolbar.shareToMe'],
		];
		var allowPath = ['{block:files}','{shareToMe}',_.trim(G.user.myhome,'/'),'{userFav}'];
		var allowCheck = function(pathInfo){
			var pathParse = kodApp.pathData.parse(pathInfo.path);
			var pathView  = pathInfo.pathDisplay || pathInfo.path;
			var pathViewArr = _.trim(pathView,'/').split('/');
			if(allowPath.indexOf(_.trim(pathInfo.path,'/')) != -1) return true;
			if(pathParse.type == '{shareItem}') return true;

			//console.log(1232,pathView,pathViewArr,pathParse,pathInfo);
			if(allowType.indexOf(pathViewArr[0]) === -1){return false;}
			return true;
		};
		var selectFinish = function(pathInfo){
			if(!allowCheck(pathInfo)){return Tips.tips(LNG['explorer.pathNotSupport'],'warning',4000);}
			var pathParse = kodApp.pathData.parse(pathInfo.path);
			var pathView  = pathInfo.pathDisplay || pathInfo.path;
			if(pathParse.type == '{shareItem}'){
				pathView = LNG['explorer.toolbar.shareToMe'] + '/' + _.trim(pathView,'/');
			}
			var pathViewArr = _.trim(pathView,'/').split('/');
			if(pathViewArr[0] == allowType[1]){pathViewArr[0] = 'my';}
			if(_.trim(pathInfo.path,'/') == '{block:files}'){pathViewArr = [];}
			var url = G.webdavOption.host + urlEncode(pathViewArr.join('/')).replace(/%2F/g,'/');
			Tips.tips(LNG['explorer.share.copied']+';<br/>'+url);
			$.copyText(url);
		};
		new kodApi.pathSelect({
			type:'folder',single:true,
			title:LNG['explorer.selectFolder']+'(support:'+allowType.join(',')+')',
			pathOpen:G.user.myhome,pathTree:'{block:files}/',
			//pathCheckAllow:function(pathInfo){},
			callback:selectFinish
		});
	},

	formData:function(){
		var pluginApi = API_HOST+'plugin/webdav/download';
		return {
			"formStyle":{"hideSave":"1",className:"form-box-title-block "},
			"detailAddress":{
				"type":"html",
				"display":"<b>webdav "+LNG['common.address']+"</b> ("+LNG['explorer.toolbar.rootPath']+")",
				"value":"<input type='text' value='"+this.webdavPath+"' readonly style='width:70%;' class='span-title-right'/>\
				<span class='input-title input-title-right kui-btn' action='copy' tabindex='0'><i class='font-icon ri-file-copy-line-2'></i>"+LNG['explorer.copy']+"</span>\
				<span class='input-title kui-btn input-title-right ml-10' action='selectPath' tabindex='0' \
				style='margin-left: 0;border-radius:4px;border-color:rgba(150,150,150,0.2);border-left-width: 1px;'>\
				<i class='font-icon ri-folder-line-3'></i>"+LNG['explorer.selectFolder']+"</span>"
			},
			"openMore":{
				"type":"button",
				"className":"form-button-line",//横线腰线
				"value":"",//默认值；同checkbox
				"info":{
					"1":{ //按钮名称
						"display":LNG['webdav.user.morePath']+" <b class='caret'></b>",
						"className":"btn-default btn-sm btn",
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