ClassBase.define({
	init: function(param){
		this.tab = 'ntcType';
		this.$el = param.form.$('.ntc-type-page');

		// 编辑表单数据
		this.dgFormData = param.dgFormData[this.tab];

		var self = this;
		// this.listenTo(this.parent.tblView, 'ntc.table.action', function(tab, action){
		this.listenTo(this.parent, 'ntc.table.action.'+this.tab, function(action, $dom){
			self.doAction(action, $dom);
		});
	},

	doAction: function(action, $dom){
		switch(action){
			case 'status':
				this.typeOpen($dom);
				break;
			case 'edit':
				this.typeEdit($dom);
				break;
		}
	},
	// 启用通知方式
	typeOpen: function($dom){
		var self = this;
		var type = $dom.parents('.list-col').siblings('.setting').find('input').val();
	    var value = $dom.prop('checked') ? 1 : 0; 
		this.saveConfig(type, {status: value}, function(){
			self.tableRefresh();
		});
	},
	// 配置通知方式
	typeEdit: function($dom){
		var self = this;
	    var type = $dom.parents('.list-col').find('input').val();
		var typeList = {
			email: 	LNG['common.email'],
			sms: 	LNG['common.sms'],
			weixin: LNG['msgWarning.type.weixin'],
			dding: 	LNG['msgWarning.type.dding'],
		};
		var formData = this.typeConfigGet(type);
		this[type+'Form'] = new kodApi.formMaker({parent:this,formData:formData});
		this[type+'Form'].renderDialog({
			id: 'ntc-type-set-dg',
			title: typeList[type]+LNG['msgWarning.main.set'],
			ico: '<i class="font-icon ri-settings-line-3 mr-5"></i>',
			width: 640,height:450,padding: 0,
			resize: false, fixed: true,cancel:true,
			opacity: 0.1,lock: true,
		},function(data){
			if (type == 'sms') return true;	// 无需保存
			if (type == 'email') {
				if (!data.type == '1' && !data.tested) {
					Tips.tips(LNG['msgWarning.type.emailTestFirst'], false);
					return false;
				}
			}
			self.saveConfig(type, data);
			return false;
		});
		this.typeConfigSet(type);

		if (type != 'email') return;
		this[type+'Form'].$el.delegate('[data-action="emailTest"]', 'click', function(){
			self.emailTest();
		});
		this[type+'Form'].bind('onChange',function(key,value){
			if (_.includes(['type','tested'], key)) return;
			self[type+'Form'].setValue('tested', 0);
		});
	},

	// 通知方式form配置
	typeConfigGet: function(type){
		return _.cloneDeep(this.dgFormData[type]);
	},

	// 通知方式form赋值
	typeConfigSet: function(type){
		var self = this;
		if (_.includes(['sms','weixin','dding'], type)) {
			var typeInfo = this.getTypeInfo(type);
			var key = 'status';
			var val = typeInfo.status;
			if (type == 'sms') {
				key = 'type';
				val = typeInfo.data;	// 有配置则为自定义
			}
			self[type+'Form'].setValue(key, val);
			return;
		}
		// email: 获取配置信息
		this.request({action: 'getConfig', type: type}, function(result){
			if (!result || !result.code) return;
			// 初始有值则默认已通过检测
			if (_.get(result, 'data.host')) {
				_.set(result, 'data.tested', 1);
			}
			self[type+'Form'].setValue(result.data);
		});
	},

	// 通知方式配置保存
	saveConfig: function (type, data) {
		var self = this;
		var callback = arguments[2] || null;
		this.request({action: 'setConfig', type: type, data: data}, function(result){
			Tips.close(result);
			if (callback) {return callback(result);}
			if (!result || !result.code) return;
			// 关闭弹窗，刷新列表
			self[type+'Form'] && self[type+'Form'].dialog.close();
			self.tableRefresh();
		});
	},

	// 发送邮件测试——需同后端保持一致
	emailTest: function(){
		if (!this.emailForm) return;
		var self = this;
		var data = this.emailForm.getValue();
			data.address = data.email;
		var check = true;
		_.each(['host','email','password'], function(key){
			if (!data[key]) {
				var msg = self.emailForm.$('input[name="'+key+'"]').attr('placeholder') || '';
				Tips.tips(msg, 'warning');
				check = false;
				return false;
			}
		});
		if (!check) return false;

		var self = this;
		var tips = Tips.loadingMask();
		this.adminModel.mailTest(data, function(result){
			tips.close();
			if(result && result.code){
				var msg = htmlEncode(_.get(result,'data','')) + '! '+LNG['admin.setting.emailGoToTips']
					+' ['+htmlEncode(data.email)+'] '+LNG['admin.setting.emailCheckTips'];
				Tips.tips(msg, true, 3000);
			}else{
				Tips.close(result);
				self.emailForm.setValue('tested', 1);
			}
		});
	},

	// 获取通知事件信息
	getTypeInfo: function (type) {
		return this.parent.ntcTypeList()[type];
	},

	tableRefresh: function(){
	    this.parent.ntcTableRefresh('ntcType');
	},
	request: function(data, callback){
		data.tab = 'type';
		var tips = Tips.loadingMask();
	    kodApi.requestSend('plugin/msgWarning/action', data, function(result){
			tips.close();
	        callback && callback(result);
		});
	}

});