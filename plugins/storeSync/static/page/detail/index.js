ClassBase.define({
	init: function(param){
        this.dtlTpl = param.dtlTpl;
		this.syncInfo = {};
	},

    initView:function(info){
		this.syncInfo = info;

		var self = this,$main = $();
		var resize = _.throttle(function(){
			var isSizeSmall = $main.width() < 769;
			if(self.currentView){self.currentView.trigger('resize');}
			$main.setClass('app-wap-small',isSizeSmall);
		},50);
		this.getLastData(info);
		var assign = {
			pathFormat: _.bind(this.pathFormat, this),
			stepStatus: _.bind(this.stepStatus, this),
			stepIcon:	_.bind(this.stepIcon, this),
			info: info
		};
		this.dialog = $.dialog({
			// id:'io-sync-dg',
			title:info.title,
            className:"dialog-storesync",  // dialog-no-title
			ico:'<i class="font-icon ri-folder-transfer-line"></i>',
			fixed:true,resize:true,
            lock:true,opacity:0.2,
            // padding:0,width:680,height:420,
            // padding:0,width:768,height:480,
            // padding:0,width:828,height:540,
            padding:0,width:828,height:530,
			content:'<div class="sync-dtl-content">'+this.renderHtml(self.dtlTpl,assign,false)+'</div>',
			// cancel:true,ok:false,
			resizeCallback:resize,
			closeBefore: function(){
				self.parent.initView(true);	// 刷新父页面
			},
			close:function(){
				self.dialog = false;
				self.execTable = false;
				// self.objectRemove();
			},
		});
		$main = this.dialog.$main;
		this.$el = $main.find('.sync-dtl-content');
		// this.$el.html('');
		// // 默认tab页选中
		// var tabActive = this.$('.sync-stepper .active').attr('data') || 'tab-scan';
		// this.$('#'+tabActive).addClass('active');

		this.bindEvent();
	},

	// 获取任务数据——用于展示
	getLastData: function(info){
		// 运行中读当前进度，闲置/完成读上轮结果
		var isRunning = info.enable == '1' && info.status != '0' && info.status != '5' && info.status != '9';
		var lastData  = _.get(info, 'lastSyncData.'+(isRunning ? 'progress' : 'lastResult'), {});
		// 扫描
		var scan = {};
		_.each(['A','B'],function(side){
			scan[side] = {};
			var dataSide = _.get(lastData, 'scan.'+side, {});
			_.each(['dirCnt','fileCnt','fileSize'],function(key){
				var val = _.get(dataSide, key, 0);
				if (key == 'fileSize') {
					val = pathTools.fileSize(val);
				}
				scan[side][key] = val;
			});
		});
		// 对比
		var dataComp = _.get(lastData, 'comp', {});
		var comp = {
			copy2B:	_.get(dataComp, 'CREATE_A_TO_B_cnt', 0) + _.get(dataComp, 'UPDATE_A_TO_B_cnt', 0),
			copy2A: _.get(dataComp, 'CREATE_B_TO_A_cnt', 0) + _.get(dataComp, 'UPDATE_B_TO_A_cnt', 0),
			delete: _.get(dataComp, 'DELETE_FROM_A_cnt', 0) + _.get(dataComp, 'DELETE_FROM_B_cnt', 0),
			conflict: _.get(dataComp, 'CONFLICT_RESOLVE_cnt', 0),
		};
		// 进度
		var taskInfo = this.processData(info);
		info.lastData = {scan: scan, comp: comp, taskInfo: taskInfo};
	},
	processData: function(info){
		var isRunning = info.enable == '1' && info.status != '0' && info.status != '5' && info.status != '9';
		var lastData  = _.get(info, 'lastSyncData.'+(isRunning ? 'progress' : 'lastResult'), {});
		var getNum = this.parent.getNumber;
		var data = {
			leftCnt: 0,	// 剩余待处理项：job.total
			percent: 0	// 进度百分比
		};
		var status = _.get(info, 'status', 0);
		if (status < 3) {
			// 扫描阶段不计算剩余项
		} else if (status == 3) {
			var comp = _.get(lastData, 'comp', {});
			data.leftCnt = _.sum(_.map(_.values(comp), function(v){return getNum(v);}));
		} else if (status == 4 || status == 5) {
			var exec = _.get(lastData, 'exec', {});
			var exed = getNum(exec.success) + getNum(exec.fail);
			// if (status == 5) {
			// 	data.leftCnt = 0;
			// 	data.percent = 100; // 已完成
			// } else {
			// 	data.leftCnt = getNum(exec.total) - exed;
			// 	data.percent = ((getNum(exec.total) <= 0 ? 1 : (exed / getNum(exec.total))) * 100).toFixed(1);
			// }
			data.leftCnt = getNum(exec.total) - exed;
			data.percent = ((getNum(exec.total) <= 0 ? 1 : (exed / getNum(exec.total))) * 100).toFixed(1);
		}
		// status=9 保持默认 0/0，失败时进度无意义
		return data;
	},

	bindEvent: function(){
		var self = this;
		var refreshTimer = null;

		// tab3：获取进度
		var syncDataLoop = function(){
			if (!self.dialog) return;
			if (document.hidden) {
                self._delay(function(){syncDataLoop();}, 1000);
                return;
            }
			self.syncRequest('getInfo',{},function(result){
				if (!result || !result.code || !result.data) return;
				self.syncInfo = result.data;
				self.refreshView(self.syncInfo);	// 刷新页面
				if (self.isSyncing()) {
					self._delay(function(){syncDataLoop();}, 1000);
				}
			});
		}
		if (this.isSyncing()) {
			this._delay(function(){syncDataLoop();}, 1000);
		}

		// 获取执行中的文件列表
		var clearFileLoop = function(){
			if (refreshTimer !== null) {
				clearTimeout(refreshTimer)   // _.delay 返回的是普通 timerId，可用 clearTimeout
				refreshTimer = null;
			}
		}
		var syncFileLoop = function(){
			clearFileLoop();
			if (self.isSyncing() && self.$('#tab-exec').hasClass('active')) {
				refreshTimer = self._delay(function(){
					// status=4/5 的任务才获取文件列表
					var status = _.get(self.syncInfo,'status');
					if (_.includes(['4','5'], status)) {
						self.tableFilter.$('[name=status]').trigger('change');
					}
					syncFileLoop();
				},1000 * 5);
			}
		}

		// 主页tab切换
		this.$el.undelegate('.step-item','click');
        this.$el.delegate('.step-item', 'click', function(){
			// if ($(this).hasClass('active')) return;
			$(this).siblings().removeClass('active');
			$(this).addClass('active');
			
			var tabId = $(this).attr('data');
			self.$('.sync-content .tab-panel').removeClass('active');
			self.$('.sync-content .tab-panel#'+tabId).addClass('active');

			// 载入同步文件列表
			if (tabId == 'tab-exec') {
				if (!self.execTable) {self.tableInit();}
				syncFileLoop();	// 循环获取列表
			}
		});
		// 默认选中第一个tab
		if (this.$('.step-item.done').length == 3 || 
			(!this.$('.step-item.done').length && !this.$('.step-item.active').length)) {
			this.$('.step-item').eq(0).addClass('active');
		}
		this.$('.step-item.active').click();
	},

	// 判断任务是否执行中
	isSyncing: function(){
		var info = this.syncInfo;
		return (info.enable == '1' && info.status != '9') ? true : false;	// 打开弹窗时，任务为进行中且非异常，始终获取
		// if (info.enable != '1' || info.status > 4) return false;	// 5/9
		// return true;
	},

	// 刷新统计界面
	refreshView: function(info) {
		this.getLastData(info);
		var lastData = _.get(info, 'lastData', {});
		if (!_.keys(lastData).length) return;
		var self = this;

		// 顶部标签
		var idx = 0;
		this.$('.step-item').each(function(){
			idx++;
			var step = $(this).attr('data').replace('tab-', '');
			var state = self.stepStatus(info, step);	// 状态有变更时，更新样式和标识
			if (!$(this).hasClass(state)) {
				$(this).removeClass().addClass('step-item');
				if (state != 'active') $(this).addClass(state);	// active为选中
				var icon = self.stepIcon(info, step, idx);
				$(this).find('.step-icon').text(icon);
			}
		});

		// 扫描
		var scan = _.get(lastData, 'scan', {});
		_.each(['A','B'],function(side){
			var $opt = self.$('#ui-col-'+side+'-scan');
			var dirCnt = _.get(scan, side+'.dirCnt', 0);
			var fileCnt = _.get(scan, side+'.fileCnt', 0);
			var fileSize = _.get(scan, side+'.fileSize', 0);
			$opt.find('.scan-num').html(numberShow(dirCnt+fileCnt));
			$opt.find('.scan-meta').html(LNG['common.file']+': '+numberShow(fileCnt)+' | '+LNG['explorer.file.size']+': '+fileSize);
		});
		// 对比
		var comp = _.get(lastData, 'comp', {});
		if (info.isBidi != '1') {
			var data = ['copy2B','delete'];
		} else {
			var data = ['copy2B','copy2A','delete','conflict'];
		}
		_.each(data, function(act,idx){
			self.$('#tab-comp .cc-num').eq(idx).html(_.get(comp,act,0));
		});

		// 底部进度
		var leftCnt = _.get(lastData,'taskInfo.leftCnt',0);
		var percent = _.get(lastData,'taskInfo.percent',0);
		this.$('.sync-footer strong').html(leftCnt);
		this.$('.sync-footer .sync-prog-inner').css('width',percent+'%');
		this.$('.sync-footer .sync-prog-text').html(percent+'%');
	},

	// 文件图标
	pathFormat: function(pathInfo) {
        return this.parent.pathFormat(pathInfo);
    },

	// 阶段图标状态
	stepStatus: function(info,step){
		if (info.enable == '0') return '';
		var status = parseInt(info.status);
		if (_.includes([0,9],status)) return '';
		if (status == 5) return 'done';

		var state = '';
		switch (step) {
			case 'scan':
				state = status < 3 ? 'active' : 'done';
				break;
			case 'comp':
				if (status == 3) {
					state = 'active';
				} else if (status > 3) {
					state = 'done';
				}
				break;
			case 'exec':
				if (status == 4) state = 'active';
				break;
		}
		return state;
	},
	stepIcon: function(info,step,idx){
		var state = this.stepStatus(info,step);
		return state == 'done' ? '✓' : idx;
	},

	syncStatus: function(action, status){
		var data = this.parseAction(action);
		var icon = 'close';
		var state= data.act;
		if (data.act != 'del') {
			state= 'to-'+_.toLower(data.side);
			icon = 'arrow-' + (data.side == 'A' ? 'left' : 'right');
		}
		var html = '<span class="action-dir dir-'+state+'"><i class="font-icon ri-'+icon+'-line"></i></span>';
		var data = {
			'0': {state: 'wait', title: LNG['storeSync.task.waiting']},
			'1': {state: 'ing',  title: LNG['storeSync.task.running']},
			'2': {state: 'done', title: LNG['storeSync.task.done']},
			'3': {state: 'err',  title: LNG['common.fail']},
			'9': {state: 'err',  title: LNG['common.fail']},
		};
		var opts = data[status];
		return html + '<br/><span class="action-status status-'+opts.state+'">'+opts.title+'</span>';
	},
	parseAction: function(action){
		var data = {
			side:'', // 被执行（覆盖/删除）的一侧
			act:''	 // 执行操作（覆盖/删除）
		};
		switch (action) {
			case 'CREATE_A_TO_B':
			case 'UPDATE_A_TO_B':
			case 'CONFLICT_A_WINS':
			case 'MKDIR_A_TO_B':
				data.side = 'B';
				data.act = 'wrt';
				break;
			case 'CREATE_B_TO_A':
			case 'UPDATE_B_TO_A':
			case 'CONFLICT_B_WINS':
			case 'MKDIR_B_TO_A':
				data.side = 'A';
				data.act = 'wrt';
				break;
			case 'DELETE_FROM_A':
			case 'RMDIR_FROM_A':
				data.side = 'A';
				data.act = 'del';
				break;
			case 'DELETE_FROM_B':
			case 'RMDIR_FROM_B':
				data.side = 'B';
				data.act = 'del';
				break;
		}
		return data;
	},

	// 同步文件列表
	tableOptions:function(param){
		var self = this;
		var pathFomat = function(fileInfo,side) {
			var path = _.get(fileInfo,'path','');
			var type = _.includes(fileInfo.action, 'DIR') ? 'folder' : pathTools.pathExt(path);
			var data = self.parseAction(fileInfo.action);

			var cls = '';
			// 当前为被执行侧，且为覆盖，置灰
			if (data.side == side) {
				if (data.act != 'del') {cls = 'file-missing';}
			} else { // 当前不为被执行侧，且为删除（说明已删除），划线
				if (data.act == 'del') {cls = 'file-missing file-del';}
			}
			return '<div class="'+cls+'">'+self.pathFormat({type: type}) + ' ' + _.trim(path,'./')+'</div>';
		}
		return {
			container:'.exec-grid',data:param,
			request:function(param,callback) {
				self.syncRequest('fileList',param,function(result){
					if (!result || !result.data) result = {data:[]};
					callback(result.data);
				});
			},
			items:[
				{field:'path',title:'path A',attr:{class:'pathA'},formatter:function(value,index,data) {
					return pathFomat(data[index], 'A');
				}},
				{field:'action',title:LNG['storeSync.main.run'],attr:{class:'align-center'},formatter:function(value,index,data){
					return self.syncStatus(value, data[index].status);
				}},
				{field:'path',title:'path B',attr:{class:'pathB'},formatter:function(value,index,data){
					return pathFomat(data[index], 'B');
				}},
			],
			pageNum:20,
			pageNumKeep:'storesync_filelist',
			// pageSizeArray:[10,20,50,100,200,500,1000],
		};
	},
	tableRefresh:function(){
		var status = this.tableFilter.getValue('status');
		// var pageNow = this.table.pageing.currentPathPage;
		// this.table.refresh({status: status},pageNow);
		this.execTable.config.data.syncID = this.syncInfo.syncID;
		this.execTable.config.data.status = status;
		this.execTable.refresh();
	},
	tableInit: function(){
		var self = this;
		var info = this.syncInfo;
		var options = this.tableOptions({status:0});
		this.execTable = new kodApi.componment.table({parent:this,config:options});
		this.listenTo(this.execTable, 'after.table.make', function () {
			self.execTable.$('.kodTable .content').perfectScroll();
		});
		this.execTable.$('.list-row.title [field=path]:nth-child(1)').html(this.pathFormat(info.pathAInfo));
		this.execTable.$('.list-row.title [field=path]:nth-child(3)').html(this.pathFormat(info.pathBInfo));

		// 列表菜单
		var config = {
			"formStyle":{className:"form-inline"},
			"status":{
				"type":"segment",
				"value":"1",
				"info":{"1":LNG['common.task.running'], "":LNG['common.all'], "0":LNG['storeSync.task.waiting'], "2":LNG['storeSync.task.done'], "3,9":LNG['common.fail']},
			},
		};
		this.tableFilter = new kodApi.formMaker({parent:this,formData:config});
		this.tableFilter.renderTarget(this.execTable.$('.toolbar .left'));
		this.tableFilter.bind('onChange',_.debounce(_.bind(this.tableRefresh,this),100));
	},

	syncRequest: function(act,data,callback){
		data = $.extend({},{syncID:this.syncInfo.syncID},data);
		kodApi.requestSend('plugin/storeSync/'+act,data,function(result){
            callback && callback(result);
        });
	},


});