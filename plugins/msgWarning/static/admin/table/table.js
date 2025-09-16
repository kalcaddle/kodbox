ClassBase.define({
    init: function () {
        this.ntcEvntListAll = {}; // 通知事件列表
        this.ntcTypeListAll = {}; // 通知类型列表
    },

    make: function (data) {
        return new kodApi.componment.table({ parent: this, config: this[data.table+'Form'](data) });
    },

    // 通知列表
    ntcEvntForm: function (data) {
        var self = this;
        var items = [
            { field: 'title', title: LNG['common.name'], formatter: function (value) {
                return value;
            }},
            { field: 'class', title: LNG['msgWarning.ntc.class'], formatter: function (value) {
                return self.ntcEvntClass(value);
            } },
            { field: 'level', title: LNG['msgWarning.ntc.level'], formatter: function (value) {
                return self.ntcEvntLevel(value);
            } },
            { field: 'notice', title: LNG['msgWarning.ntc.method'], formatter: function (value) {
                return self.ntcEvntType(value.method);
            }},
            { field: 'desc', title: LNG['msgWarning.main.desc'], formatter: function (value) {
                return value;
            }},
            { field: 'status', title: LNG['common.status'], formatter: function (value, idx, data) {
                var checked = value == '1' ? 'checked="checked"' : '';
                var disable = (data[idx].system == '1' && data[idx].level >= 3) ? 'switch-not-allowed' : '';
                return '<label class="disable-ripple '+disable+'">\
                            <input type="checkbox" class="kui-checkbox-ios size-small" name="status" '+checked+' /><em></em>\
                        </label>';
            }},
            { field: 'event', title: LNG['common.action'], attr: {class: 'setting'}, formatter: function (value, idx, data) {
                // TODO 详情（通知记录）
                var html = '<input type="text" value="'+value+'" class="hidden" />\
                            <li data="'+value+'" data-action="edit" class="do-action ripple-item">'+LNG['common.edit']+'</li>';
                if (data[idx].system != '1') {
                    html += '<li data="'+value+'" data-action="remove" class="do-action ripple-item">'+LNG['common.delete']+'</li>';
                }
                return html;
            }},
        ];
        return this.getTblConfig(data, items);
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
    // 通知方式
    ntcEvntType: function (type) {
        var data = [];
        var self = this;
        _.each(_.split(type, ','), function(val) {
            data.push(self.getNtcTypeIcon(val, 24));
        });
        return data.join('');
    },

    // 获取通知方式图标
    getNtcTypeIcon: function (type, size) {
        var icon = 'ntc-icon '+type+' sz-'+size;  // sz-32
        if (type == 'ktips') {
            icon += ' font-icon ri-notification-fill-2 bg-blue-normal';
        } else if (type == 'kwarn') {
            icon += ' font-icon ri-volume-up-fill bg-orange-normal';
        }
        var title = LNG['msgWarning.type.'+type];
        return '<i class="'+icon+'" title="'+title+'" title-timeout="200"></i>';
    },

    // -------------------------------------------------------------- 分隔线 --------------------------------------------------------------

    // 通知网关
    ntcTypeForm: function (data) {
        var self = this;
        var items = [
            { field: 'name', title: LNG['msgWarning.main.tabType'], formatter: function (value, idx, data) {
                var type = data[idx].type;
                return self.getNtcTypeIcon(type, 32)+'<span>'+ value+'</span>';
            }},
            { field: 'desc', title: LNG['msgWarning.main.desc'], formatter: function (value) {
                return value;
            } },
            { field: 'data', title: LNG['msgWarning.main.setDtl'], formatter: function (value, idx, data) {
                return self.ntcTypeData(value, data[idx].type);
            } },
            { field: 'status', title: LNG['common.status'], formatter: function (value, idx, data) {
                var checked = value == '1' ? 'checked="checked"' : '';
                var disable = !_.includes(['weixin', 'dding'], data[idx].type) ? 'switch-not-allowed' : '';   // 仅企业微信和钉钉支持开启/关闭，其他默认开启
                return '<label class="disable-ripple '+disable+'">\
                            <input type="checkbox" class="kui-checkbox-ios size-small" name="status" '+checked+' /><em></em>\
                        </label>';
            } },
            { field: 'type', title: LNG['common.action'], attr: { class: 'setting' }, formatter: function (value, idx, data) {
                if (_.includes(['ktips', 'kwarn'], data[idx].type)) return '';
                return '<input type="text" value="'+value+'" class="hidden" /><li data-action="edit" class="do-action ripple-item">'+LNG['msgWarning.main.set']+'</li>';
            }},
        ];
        return this.getTblConfig(data, items);
    },
    ntcTypeData: function (data, type) {
        var color = 'grey';
        var text = LNG['msgWarning.main.unset'];
        if (data == '1') {
            color = 'green';
            text = LNG['msgWarning.main.setted'];
        } else {
            if (_.includes(['email', 'sms'], type)) {
                text += ' ('+LNG['common.systemDefault']+')';
                if (type == 'sms' && _.get(G, 'kod.versionType') == 'A') {
                    text = LNG['msgWarning.main.unset'];
                }
            }
        }
        if (_.includes(['ktips', 'kwarn'], type)) {
            color = 'blue';
            text = LNG['common.systemDefault'];
        }
        return '<span class="label label-'+color+'-normal">'+text+'</span>';
    },

    // -------------------------------------------------------------- 分隔线 --------------------------------------------------------------

    // 通知日志
    ntcLogsForm: function (data) {
        var self = this;
        var items = [
            { field: 'title', title: LNG['msgWarning.ntc.event'],formatter: function (value) {
                return value;
            }},
            { field: 'userInfo', title: LNG['msgWarning.ntc.target'],formatter: function (value) {
                var user = (value && _.size(value)) ? value : {name: LNG['common.unknow']};
                return self.formatUser(user);
            }},
            { field: 'method', title: LNG['msgWarning.ntc.method'],formatter: function (value) {
                return self.ntcEvntType(value);
            }},
            { field: 'target', title: LNG['msgWarning.ntc.contact'],formatter: function (value) {
                return value;
            }},
            { field: 'desc', title: LNG['msgWarning.ntc.result'], formatter: function (value, idx, data) {
                var text = LNG['msgWarning.ntc.success'];
                var colr = 'green';
                if (data[idx].status != '1') {
                    text = LNG['msgWarning.ntc.failed'];
                    colr = 'red';
                }
                var title = '<span class="label label-'+colr+'-normal">'+text+'</span>';
                if (value && data[idx].status != '1') {
                    title += '<span class="ml-10" title="'+value+'" title-timeout="200">'+value+'</span>';
                }
                return title;
            }},
            { field: 'createTime', title: LNG['msgWarning.ntc.time'], formatter: function (value) {
                return dateFormat(value,'timeMinute');
            }},
        ];
        return this.getTblConfig(data, items);
        // var config = this.getTblConfig(data, items);
        //     config.order = [5, 'down']; // createTime
        // return config;
    },
    // 用户信息
    formatUser: function(user){
        var avatar = user.avatar || STATIC_PATH+'images/common/default-avata.png';
        var name = user.nickName || user.name;
		if(user.userID == window.G.user.userID){
			name = LNG['common.me'];
        }
        var icon = '<i class="path-ico"><img src="'+avatar+'"></i>';
        var html = '<span class="user-info" data-user-id="'+user.userID+'" title="'+htmlEncode(user.name)+'">'+icon+'\
                <span class="name">'+name+'</span>\
            </span>';
		return html;
    },

    // 获取表格配置
    getTblConfig: function(data, items){
        var self = this;
        var type = _.toLower(_.replace(data.table, 'ntc', ''));
        var config = {
            container: '.ntc-'+type+'-page',     // 目标节点
            data: data,	// 请求参数
            request: function (data, callback) {
                var param = _.clone(data);
                param.tab = type;
                delete param.table;
                kodApi.requestSend('plugin/msgWarning/table',param,function(result){
                    self.parseListAll(type, result.data);
                    callback(result.data);
                });
            },
            items: items,
            toolbar: [],
            // order: [2, 'down'], // createTime
        };
        return config;
    },

    // 解析列表
    parseListAll: function (type, data) {
        if (type == 'logs') return;
        var temp = {};
        var ikey = type == 'type' ? 'type' : 'event';
        var list = _.get(data, 'list', []);
        _.each(list, function(item){
            temp[item[ikey]] = item;
        });
        this['ntc'+_.upperFirst(type)+'ListAll'] = temp;
    }
});