ClassBase.define({
	init: function(param){
		this.$el = $('.admin-page .storage-page .tab-content.sync');
        // 获取同步任务列表，载入页面
        this.syncPkg = param.pagePkg;
        this.syncTpl = param.pageTpl;

        this.expObjList = [];  // 文件管理弹窗
        this.initView();

		// 同步详情
		this.syncDtl = new param.SyncDtl({parent: this, dtlTpl: param.dtlTpl});
	},

	// 初始化/刷新主页面
    initView: function(refresh=false){
        if (!refresh && this.$('.io-sync-page').length) return;
        var self = this;
		this.syncRequest('get',{},function(result){
			var data = _.get(result, 'data', false);
            if (!_.isArray(data)) {
                Tips.tips(LNG['explorer.dataError']+data, 'warning');
                data = [];
            }
            var assign = {
                syncStatus: _.bind(self.syncStatus, self),
                pathFormat: _.bind(self.pathFormat, self),
                syncAction: _.bind(self.syncAction, self),
                lastSyncInfo: _.bind(self.lastSyncInfo, self),
                lastScanInfo: _.bind(self.lastScanInfo, self),
                dataList: data
            };
            self.renderHtml(self.syncTpl,assign,self.$el);
			// if (_.get(G, 'user.isRootAllowIO', 1) === 0) {
			// 	// 
			// }
		});
        this.bindEvent();
    },

    bindEvent: function(){
        var self = this;

		// 点击事件
		this.$el.undelegate('.io-sync-page [action-data]','click');
        this.$el.delegate('.io-sync-page [action-data]', 'click', function(e){
            var action = $(this).attr('action-data');
			if (!action) return stopPP(e);
            if (action == 'add') {
                var info = {syncID: 0};
            } else {
                var data = $(this).parents('.opt-body').attr('data');
                var info = jsonDecode(urlDecode(data));
                if (data && !info) {
					Tips.tips(LNG['storeSync.main.syncInfoErr'], 'warning');
					return stopPP(e);
				}
            }
			var syncID = _.get(info, 'syncID', 0);
			switch (action) {
				case 'dtl':
					self.syncDtl.initView(info);	// 任务详情：同步进度信息
					break;
				case 'add':
				case 'edit':
					self.syncEdit(syncID, info);
					break;
				case 'remove':
					self.syncRemove(syncID);
					break;
				case 'reset':
					self.syncReset(syncID);
					break;
				case 'start':
					self.syncStart(syncID);
					break;
				default:
					if (_.startsWith(action, 'enable')) {
						self.syncEnable(syncID, action);
					}
					break;
			}
			return stopPP(e);
        });

        // 打开目录
		this.$el.undelegate('.opt-info .data-path','click');
        this.$el.delegate('.opt-info .data-path', 'click', function(e){
            var thePath  = $(this).attr('file-path');
            self.folderSelect(thePath);
			return stopPP(e);
        });
        
    },

    syncStart: function(syncID){
		var self = this;
		$.dialog.confirm(LNG['storeSync.main.ifStartSync'], function(){
			self.syncSave('start', {syncID: syncID});
		});
	},
    syncReset: function(syncID){
		var self = this;
		$.dialog.confirm(LNG['storeSync.main.ifResetTask'], function(){
			self.syncSave('reset', {syncID: syncID});
		});
	},
	syncEnable: function(syncID, action){
		var tmp = _.split(action, '-');
		var status = _.parseInt(tmp[1]);
		this.syncSave('edit', {syncID: syncID, enable: status});
	},
	syncRemove: function(syncID){
		var self = this;
		$.dialog.confirm2(LNG['storeSync.main.ifDelTask'], function(){
			self.syncSave('remove', {syncID: syncID});
		});
	},
	syncEdit: function(syncID, info){
		// 新增
		this.showDialog(syncID);
		if (!syncID) {
			// this.formMaker.$('.item-checkNum').removeClass('hidden');
			return;
		}
		// 编辑
		// if (info.enable != '0') info.enable = '1';	// 暂停置为启用状态，会影响保存
		this.formMaker.setValue(info);
		this.formMaker.$('.item-pathA,.item-pathB').find('.setting-content').addClass('disable-event');
	},
    showDialog: function(syncID){
		var self = this;
		var option = {
            id: 'store-sync-dg',
            ico: '<i class="font-icon ri-folder-transfer-line"></i>',
            title: (syncID ? LNG['common.edit'] : LNG['common.add']) + LNG['storeSync.main.sync'],
            width:520,height:520,
            okVal: LNG['common.ok'],
        };
		this.formMaker = new kodApi.formMaker({parent:this,formData:this.formData()});
        this.formMaker.renderDialog(option, function (data) {
			data.syncID = syncID;
			var action = syncID ? 'edit' : 'add';
			self.syncSave(action, data);
            return false;
        });
		// 双向同步时禁止重命名
		this.formMaker.bind('onChange',function(key,value,$row){
			if (key != 'isBidi') return true;
			self._delay(function(){
				var $rename = self.formMaker.$('.item-rptType [data-value=2]');
				if (value == '1') {
					var rptType = self.formMaker.getValue('rptType');
					if (rptType == '2') {	// rename
						self.formMaker.setValue('rptType', '1');	// skip
					}
					$rename.addClass('disable-event');
				} else {
					$rename.removeClass('disable-event');
				}
			}, 50);
		});
	},
    // 新增、编辑提交保存
	syncSave: function(action, data){
		var self = this;
		this.syncRequest(action, data, function(result){
			Tips.close(result);
			if (!result || !result.code) return;
            self.initView(true);    // 刷新页面
			if (self.formMaker && self.formMaker.objectRemoveViewFinished !== true) {
				self.formMaker.dialog.close();
			}
		});
	},

	syncRequest: function(action,data,callback){
		kodApi.requestSend('plugin/storeSync/'+action,data,function(result){
			callback && callback(result);
		});
	},

    formData: function(){
        return _.cloneDeep(this.syncPkg);
    },

	// icon+path，io/file/folder
    pathFormat: function(pathInfo) {
		var icon = '';
		if (!arguments[1]) {
			var key = '';	// 存储默认值：io-store
			_.each(['icon','ext','type'], function(tKey){
				if (_.has(pathInfo, tKey)) {
					key = tKey; return false;
				}
			});
			var icon = _.get(pathInfo, key, '');
			if (key == 'icon') icon = 'folder';	// 存储统一替换为目录，屏蔽该行可获得存储图标
			icon = kodApi.componment.pathIcon(icon); // pathData.pathIcon
			if (key != 'icon') return icon;	// 文件/夹直接返回图标
		}
        var title = htmlEncode(pathInfo.pathDisplay || pathInfo.name);
        return '<span class="data-path ripple-item" file-path="'+pathInfo.path+'">'+icon+' <span>'+title+'</span></span>';
    },

	// 任务状态：运行中、已暂停、已禁用、异常
	syncStatus: function(task) {
		var data = {	// 任务状态
			'0': {title: LNG['common.disabled'], color: 'grey'},
			'1': {title: LNG['storeSync.main.running'], color: 'green'},
			'2': {title: LNG['admin.paused'], color: 'blue'},
		};
		var info = data[task.enable];
		if (task.enable == '1' && task.status == '9') {
			var info = {title: LNG['storeSync.main.errTask'], color: 'red', msg: _.get(task, 'errMsg', 'Unknown error.')};
		}
		var title = info.msg ? 'title="'+info.msg+'" title-timeout="200"' : '';
		// return '<span class="label label-'+info.color+'-light" '+title+'>'+info.title+'</span>';
		var reset = '';
		if (task.status == '9') {
			reset = '<i class="font-icon ri-restart-line btn-reset" action-data="reset" title="'+LNG['common.reset']+'" title-timeout="200"></i>';
		}
		var start = '<i class="font-icon ri-exchange-line btn-start" action-data="start" title="'+LNG['storeSync.main.manSync']+'" title-timeout="200"></i>';
		return start + reset + '<span class="color-label '+info.color+'-normal" '+title+'>'+info.title+'</span>';
	},

	// 上次同步结果信息
	// 上次同步：2026-05-01 23:00:00，变更数：23 => 新增：13，更新：99；删除：323
	lastSyncInfo: function(info){
		if (info.lastSyncTime == '0') {
			return LNG['storeSync.main.syncTime'] + ': ' + LNG['admin.backup.notYet'];
		}

		var makeHtml = function(text){
			var title = arguments[1] || text;
			// return '<span title="'+'上次同步：'+title+'" titile-timeout="200">'+text+'</span>';
			return '<span title="'+title+'" titile-timeout="200">'+text+'</span>';
		}

		// 基本信息
		var lastData = _.get(info, 'lastSyncData.lastResult', {});
		var exec  = _.get(lastData, 'exec', {});
		var total = _.parseInt(_.get(exec, 'total', 0));
		if (_.isNaN(total)) total = 0;
		var useTime = timeShow(_.get(lastData, 'duration', 0));
		var text = [
			LNG['storeSync.main.syncTime'] + ': ' + dateShow(info.lastSyncTime),
			LNG['storeSync.main.usedTime'] + ': ' + useTime,
			LNG['storeSync.main.changed'] + ': ' + total
		];
		text = _.join(text, '; ');
		if (total <= 0) {return makeHtml(text);}

		// 详情
		var data = {add:0,update:0,delete:0};
		_.each(exec, function(val,key){
			var preKey = key.slice(0, 6);
			switch (preKey) {
				case 'CREATE':
				case 'MKDIR_':
					data.add += val;
					break;
				case 'UPDATE':
				case 'CONFLI':	// CONFLICT_X_WINS
					data.update += val;
					break;
				case 'DELETE':
				case 'RMDIR_':
					data.delete += val;
					break;
			}
		});
		var more = [];
		_.each(data, function(val,key){
			more.push(LNG['common.'+key]+': '+val);
		});
		var title = text + '<br/>'+LNG['storeSync.main.syncDtl']+': ' + _.join(more, '; ');
		text += '<i class="font-icon ri-information-line dtl"></i>';
		return makeHtml(text, title);
	},
	lastScanInfo: function(info, side){
		var self = this;
		var lastData = _.get(info, 'lastSyncData.lastResult', {});
		var dirCnt	 = _.get(lastData, 'scan.'+side+'.dirCnt', 0);
		var fileCnt  = _.get(lastData, 'scan.'+side+'.fileCnt', 0);
		var fileSize = _.get(lastData, 'scan.'+side+'.fileSize', 0);
		var cnt = this.getNumber(dirCnt) + this.getNumber(fileCnt);
		return {cnt: numberShow(cnt), size: pathTools.fileSize(fileSize)};

		// // 追加当前变更：可以修改个数，但无法获取大小
		// var add = 0, del = 0;
		// var exec = _.get(lastData, 'exec', {});
		// if (side == 'A') {
		// 	_.each(['CREATE_B_TO_A_cnt', 'MKDIR_B_TO_A_cnt'], function(key){
		// 		add += self.getNumber(_.get(exec,key,0));
		// 	});
		// 	_.each(['DELETE_FROM_A_cnt', 'RMDIR_FROM_A_cnt'], function(key){
		// 		del += self.getNumber(_.get(exec,key,0));
		// 	});
		// } else {
		// 	_.each(['CREATE_A_TO_B_cnt', 'MKDIR_A_TO_B_cnt'], function(key){
		// 		add += self.getNumber(_.get(exec,key,0));
		// 	});
		// 	_.each(['DELETE_FROM_B_cnt', 'RMDIR_FROM_B_cnt'], function(key){
		// 		del += self.getNumber(_.get(exec,key,0));
		// 	});
		// }

	},
	getNumber: function(num){
		num = _.parseInt(num);
		return !_.isNaN(num) ? num : 0;
	},

	// 任务操作按钮
	syncAction: function(info) {
		// 任务状态：0-停用,1-启用,2-暂停
		var data = [
			[{action: 'edit', title: LNG['common.edit'], icon: 'ri-edit-box-line'}],
			{	// 任务状态
				'0': {action: '', title: LNG['common.disabled'], icon: 'ri-stop-circle-line'},
				'1': {action: 'enable-2', title: LNG['storeSync.main.toPause'], icon: 'ri-pause-circle-line'},
				'2': {action: 'enable-1', title: LNG['storeSync.main.toPlay'], icon: 'ri-play-circle-line'},
			},
			{
				'0': {action: 'enable-1', title: LNG['common.enable'], icon: 'ri-checkbox-circle-line'},
            	'1': {action: 'enable-0', title: LNG['common.disable'], icon: 'ri-close-circle-line'},
				'2': {action: 'enable-1', title: LNG['common.enable'], icon: 'ri-checkbox-circle-line'},
			},
			[{action: 'remove', title: LNG['common.delete'], icon: 'ri-delete-bin-line-6'}],
		];
		var html = [];
		_.each(data, function(item, i){
			var key = _.isArray(item) ? 0 : info.enable;
			// // 任务因异常而暂停
			// if (i === 1 && info.status == '9' && key == '1') {
			// 	key = '2';
			// }
			var opt = item[key];
			var evt = !opt.action ? 'disable-event' : '';	// 禁用状态下不可切换为运行
			html.push('<span class="opt-icon '+evt+'" title="'+opt.title+'" title-timeout="200" action-data="'+opt.action+'">\
						<i class="font-icon '+opt.icon+'"></i></span>');
		});
		return _.join(html,'\n');
	},

    // 打开文件夹
	folderSelect:function(thePath){
		this.expObjList.push(new kodApi.explorer({
			pathOpen: thePath,
			pathTree: '{block:driver}/',
			// closeBefore:function(){
			// 	explorer = null;
			// },
		}));
	},	

    onRemove: function(){
        _.each(this.expObjList, function(obj){
            obj.dialog && obj.dialog.close();
            obj.objectRemove();
        });
        this.expObjList = [];
    },

});