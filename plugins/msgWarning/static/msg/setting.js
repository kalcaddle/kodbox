ClassBase.define({
	init: function (param) {
		this.initParentView(param);
        var package = this.formData();
		var form = this.initFormView(package); // parent: form.parent;
        form.setValue(G.msgWarningOption);

        var self = this;
        this.request = new kodApi.request({parent: this});
        // 系统消息赋值
        this.request.requestSend('plugin/msgWarning/message', {}, function(result){
            if (!result || !result.code) return;
            var msg = self.getMessage(result.data);
            // form.setValue({sysMsg: msg});    // html类型调用此方法无效
            var desc = LNG['msgWarning.config.sysNtcDesc'];
            form.$('.form-row.item-sysMsg .setting-content').html(desc+msg);
        });
        // 发送方式赋值
		this.request.requestSend('plugin/msgWarning/sendType',{},function(result){
			var data = [];
			var type = _.get(G, 'msgWarningOption.sendType');
				type = type ? _.split(type, ',') : [];
			_.each(result.data, function(val, key){
				if (val != '1') {
					form.$('.item-sendType input[value="'+key+'"]').parent().addClass('disabled');
				} else {
					if (_.includes(type, key)) data.push(key);
				}
			});
			form.setValue('sendType', _.join(data, ','));
		});
		// // 短信模板ID
		// form.$("[name=sendType][value=sms]").bind('change', function () {
		// 	var func = $(this).is(':checked') ? 'removeClass' : 'addClass';	// slideDown/Up
		// 	form.$('.item-smsCode')[func]('hidden');
		// });
	},
	saveConfig: function(data){
        G.msgWarningOption = data;
		if(!data) return false;
		Tips.loading(LNG['explorer.loading']);
		this.adminModel.pluginConfig({app:'msgWarning',value:data},Tips.close);
	},

    // 获取系统消息
    getMessage: function(data){
        if (_.isString(data)) {
            return '<div class="info-alert info-alert-success">'+data+'</div>';
        }
        var msg = [];
        _.each(data, function(item, k) {
            if (msg.length && item.length) {
                msg.push('<hr style="border-color:#fff;margin:0.4em 0;">');
            }
            _.each(item, function(val) {
                var value = '<li>'+val+'</li>';
                if (k == 'user' && _.startsWith(val, '<a')) {
                    value = val;
                }
                msg.push(value);
            });
        });
        var status = 'error';
        if (!msg.length) {
            status = 'success';
            msg = [LNG['msgWarning.main.msgSysOK']];
        }
        return '<div class="info-alert info-alert-'+status+'">'+_.join(msg,'')+'</div>';
    },
	formData:function(){
        return {
            'sep001':'<h4>'+LNG['msgWarning.config.sysNtc']+'</h4>',
            'sysMsg': {
                'type':'html',
                'value':'',
                'display':''
            },
            'sep002':'<h4>'+LNG['msgWarning.config.setNtc']+'</h4>',
            'enable':{
                'type':'switch',
                'value':0,
                'className':'row-inline',
                'display':LNG['msgWarning.config.openNtc'],
                'desc':LNG['msgWarning.config.openNtcDesc'],
                'switchItem':{'0':'','1':'warnType,useRatio,useTime,sendType,target'}
            },
            'warnType':{
                'type':'checkbox',
                'value':'cpu,mem,store',
                'display':LNG['msgWarning.config.warnType'],
                'info':{
                    'cpu':LNG['msgWarning.config.warnTypeCpu'],'mem':LNG['msgWarning.config.warnTypeMem']
                },
                'require':1
            },
            'useRatio':{
                'type':'number',
                'value':'80',
                'titleRight':'&nbsp;%&nbsp;',
                'display':LNG['msgWarning.config.useRatio'],
                'desc':LNG['msgWarning.config.useRatioDesc'],
                'require':1
            },
            'useTime':{
                'type':'number',
                'value':'20',
                'display':LNG['msgWarning.config.useTime'],
                'desc':LNG['msgWarning.config.useTimeDesc'],
                'require':1
            },
            'sendType':{
                'type':'checkbox',
                'value':'email',
                'display':LNG['msgWarning.config.sendType'],
                'info':{
					'dingTalk':LNG['msgWarning.config.dingTalk'],
					'weChat':LNG['msgWarning.config.weChat'],
					// 'sms':'短信',
					'email':LNG['msgWarning.config.email'],
				},
                'require':1
            },
            // 'smsCode': {
            //     'type': 'input',
            //     'value': '',
			// 	'display': '短信模板ID',
			// 	'desc': '变量格式：\${file}、\${content}；模板申请参考【短信网关】插件，<a href="https://dysmsnext.console.aliyun.com/overview" target="_blank" style="position: relative;">短信服务平台</a>',
			// 	'attr':{'placeholder': 'SMS_14535****', 'style':'width:245px;'}
            // },
            'target':{
                'type':'user',
                'value':'1',
                'display':LNG['msgWarning.config.target'],
                'selectType':'mutil',
                'desc':LNG['msgWarning.config.targetDesc'],
                'require':1
            }
        }
    }
});