ClassBase.define({
	init: function(param){
		this.tab = 'ntcEvnt';
		this.$el = param.form.$('.ntc-evnt-page');

		// 编辑表单数据
		this.dgFormData = param.dgFormData[this.tab];

		// 监听页面点击事件
		var self = this;
		this.listenTo(this.parent, {
			['ntc.table.action.'+this.tab]: function(action, $dom){
				self.doAction(action, $dom);
			},
		});

		// 获取通知事件初始通知方式列表
		this.evntTypeList = {};
		this.request({action: 'getRawData'}, function(result){
			self.evntTypeList = result.data || {};
		});
	},

	doAction: function(action, $dom){
		switch(action){
			case 'status':
				this.evntOpen($dom);
				break;
			case 'edit':
				this.evntEdit($dom);
				break;
			case 'reset':
				this.resetConfig();
				break;
		}
	},
	// 启用通知事件
	evntOpen: function($dom){
		var self = this;
		var event = $dom.parents('.list-col').siblings('.setting').find('input').val();
	    var value = $dom.prop('checked') ? 1 : 0; 
		this.saveConfig(event, {status: value}, function(){
			self.tableRefresh();
		});
	},
	// 编辑通知事件
	evntEdit: function($dom){
		var self = this;
	    var event = $dom.parents('.list-col').find('input').val();
		var evntInfo = this.getEvntInfo(event);
		if (!evntInfo) return;

		var formData = _.cloneDeep(this.dgFormData);
		this.parseFormData(formData, evntInfo);
		this[event+'Form'] = new kodApi.formMaker({parent:this,formData:formData});
		this[event+'Form'].renderDialog({
			id: 'ntc-evnt-set-dg',
			title: evntInfo.title,
			ico: '<i class="font-icon ri-volume-up-fill mr-5"></i>',
			width: 640,height:450,padding: 0,
			// resize: false, 
			fixed: true,
			cancel:true,
			opacity: 0.1,lock: true,
		},function(data){
			var config = {
				status: data.status,
				policy: {},
				notice: {},
			};
			_.each(data, function(value, key){
				if (_.startsWith(key, 'pol')) {
					var key = _.lowerFirst(key.slice(3));
					config.policy[key] = value;
				} else if (_.startsWith(key, 'ntc')) {
					var key = _.lowerFirst(key.slice(3));
					config.notice[key] = value;
				}
			});
			self.saveConfig(event, config);
			return false;
		});
		// this[event+'Form'].bind('onChange', function(key, value){
		// 	//
		// });

		// 系统通知，且等级为预警级以上，禁止修改（取消）默认通知方式；
		if (evntInfo.system != '1' || evntInfo.level < 3) return;
		var initType = this.evntTypeList ? this.evntTypeList[event] : '';
		if (!initType) return;
		var $content = this[event+'Form'].$('.item-ntcMethod .setting-content');
		_.each(_.split(initType, ','), function(mthd){
			$content.find('label[data-value="'+mthd+'"]').addClass('disable-event');
		});
	},
	parseFormData: function (formData, evntInfo) {
		var self = this;

		// 1.通知设置：处理为form所需键名并赋值
		if (!evntInfo.notice) {evntInfo.notice = {};}
		_.each(evntInfo.notice, function(val, key){
			var formKey = 'ntc'+_.upperFirst(key);
			if (_.isUndefined(formData[formKey])) return true;
			formData[formKey].value = val;
		});
		// 2.表单赋值
		_.each(formData, function(item, key){
			if (_.startsWith(key, 'egType') && key != 'egType') {
				// item.value = '<div>'+evntInfo.message+'</div>';
				var type = _.lowerCase(_.replace(key, 'egType', ''));
				self.makeTypeMsg(item, evntInfo, type);
			}
			if (_.isUndefined(evntInfo[key])) return true;
			var value = evntInfo[key];
			if (key == 'level') {
				value = self.ntcEvntLevel(value);
			} else if (key == 'class') {
				value = self.ntcEvntClass(value);
			}
			item.value = value;
		});
		// 部分字段只读
		if (evntInfo.system != '1' || evntInfo.level < 3) {
			_.each(['status','ntcTimeFrom','ntcTimeTo'], function(key){
				formData[key].className = '';
			});
		}
		// 3.触发条件：键值对从后端获取，直接赋值，为空则为默认值（title）
		if (!_.isEmpty(evntInfo.policy)) {
			formData.formStyle.tabs.policy = '';
			delete formData.polTitle;
		} else {
			evntInfo.policy = {};
		}
		_.each(evntInfo.policy, function(item, key){
			var formKey = 'pol'+_.upperFirst(key);
			formData.formStyle.tabs.policy += ','+formKey;
			formData[formKey] = item;
		});
		// 4.1 通知设置：去掉未配置的项
		_.each(['cntMax','cntMaxDay','timeFreq','timeFrom','timeTo'], function(key){
			if (_.isUndefined(evntInfo.notice[key])) {
				var formKey = 'ntc'+_.upperFirst(key);
				delete formData[formKey];
			}
		});
		// 4.2 通知设置：获取有效的通知方式
		var typeList = this.getTypeList();
		_.each(typeList, function(item, key){
			if (item.status != '1') return true;
			formData.ntcMethod.info[key] = item.name;
		});

		// 是否通知所有人
		if (evntInfo.toAll == '1') {
			formData.ntcMethod.desc += '<br/>'+LNG['msgWarning.evnt.toAllDesc'];
		}
	},
	// 通知类型
    ntcEvntClass: function (cls) {
        var data = {
			"dev": 	LNG['msgWarning.ntc.clsDev'],
			"svr": 	LNG['msgWarning.ntc.clsSvr'],
			"sys": 	LNG['msgWarning.ntc.clsSys'],
			"app": 	LNG['msgWarning.ntc.clsApp'],
			"ops": 	LNG['msgWarning.ntc.clsOps'],
			"data": LNG['msgWarning.ntc.clsData'],
			"coll": LNG['msgWarning.ntc.clsColl'],
			"safe": LNG['msgWarning.ntc.clsSafe'],
		};
        return data[cls] || '';
    },
	// 通知等级
    ntcEvntLevel: function (lvl) {
        var data = {
            'level1': {text: LNG['msgWarning.ntc.level1'], color: 'blue'},
            'level2': {text: LNG['msgWarning.ntc.level2'], color: 'yellow'},
            'level3': {text: LNG['msgWarning.ntc.level3'], color: 'orange'},
            'level4': {text: LNG['msgWarning.ntc.level4'], color: 'red'},
        };
        var info = data['level'+lvl];
        return '<span class="label label-'+info.color+'-normal">'+info.text+'</span>';
    },

	// 生成通知示例
	makeTypeMsg: function (item, evntInfo, type) {
		var name = _.get(G, 'system.options.systemName', 'kodbox');	// 系统名称
		var icon = {
            'level1': 'blue',
            'level2': 'yellow',
            'level3': 'orange',
            'level4': 'red',
        };
		var clor = icon['level'+evntInfo.level] || 'red';
		var text = evntInfo.message;
		var html = '';
		if (type == 'ktips') {
			html = '<div class="eg-box">\
					<div class="icon"><i class="font-icon ri-error-warning-fill '+clor+'-normal"></i></div>\
					<div class="content">'+text+'</div>\
					</div>';
		} else if (type == 'kwarn') {
			html = '<div class="eg-box">\
					<div class="topbar"><i class="font-icon ri-volume-up-fill"></i> '+LNG['msgWarning.main.sysNotice']+'</div>\
					<div class="content">\
						<span><i class="bg-'+clor+'-normal"></i>'+evntInfo.title+'</span><br/>\
						<span>'+text+'</span>\
					</div>\
					<div class="botbar"><div class="kui-btn btn">'+LNG['common.ok']+'</div></div>\
					</div>';
		} else if (type == 'email') {
			var user = _.get(G,'user.info.nickName', G.user.info.name);
			var title = _.replace(LNG['admin.emailDear'], '%s', user);
			html = '<div class="eg-box">\
					<div class="content">\
						<div>'+title+'</div>\
						<div class="mt-10">'+text+'</div>\
						<div><span>'+name+'</span><br/><span>'+dateFormate(false,'Y-m-d')+'</span></div>\
					</div>\
					</div>';
		} else {
			var hide = type == 'weixin' ? 'hidden' : '';
			// var hide = '';
			var head = './static/images/icon/fav.png';
			if (_.get(G, 'kod.channel', '')) head = './static/images/common/default-avata.png';
			html = '<div class="eg-box">\
					<div class="icon"><i class="path-ico"><img src="'+head+'"></i></div>\
					<div class="content">\
						<div class="ml-10 '+hide+'">'+name+'</div>\
						<div><span class="title">'+evntInfo.title+'</span><br/><span>'+text+'</span></div>\
					</div>\
					</div>';
		}
		item.value = html;
	},

	// 通知方式配置保存
	saveConfig: function (event, data) {
		var self = this;
		var callback = arguments[2] || null;
		this.request({action: 'setConfig', event: event, data: data}, function(result){
			Tips.close(result);
			if (callback) {return callback(result);}
			if (!result || !result.code) return;
			// 关闭弹窗，刷新列表
			self[event+'Form'] && self[event+'Form'].dialog.close();
			self.tableRefresh();
		});
	},

	// 重置插件配置
	resetConfig: function () {
		var self = this;
		$.dialog.confirm(LNG['msgWarning.main.ifAppReset'],function(){
			self.request({app: 'msgWarning', value: 'reset'}, function(result){
				Tips.close(result);
				if (!result || !result.code) return;
				kodApi.requestSend('admin/autoTask/taskRestart');
				Router.refresh();
			}, 'admin/plugin/setConfig');
		});
	},

	// 获取通知事件信息
	getEvntInfo: function (event) {
		return this.parent.ntcEvntList()[event];
	},
	// 获取通知方式列表
	getTypeList: function () {
		return this.parent.ntcTypeList();
	},

	tableRefresh: function(){
	    this.parent.ntcTableRefresh('ntcEvnt');
	},
	request: function(data, callback){
		data.tab = 'evnt';
		var uri = arguments[2] || 'plugin/msgWarning/action';
		var tips = Tips.loadingMask();
	    kodApi.requestSend(uri, data, function(result){
			tips.close();
	        callback && callback(result);
		});
	}

});