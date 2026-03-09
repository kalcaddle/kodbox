ClassBase.define({
	init: function (param) {},
    initView: function () {
        // 添加导入按钮
        this.$el = $('.admin-page .storage-page');
        var $toolbar = this.$('.store-list-box .toolbar');
        if (!$toolbar.children('.right').length) {
            $toolbar.append('<div class="right"><button class="kui-btn import">'+LNG['storeImport.meta.name']+'</button></div>');
        }
        $toolbar.undelegate('button.import','click');
        $toolbar.delegate('button.import', 'click', _.bind(this.showDialog, this));

        this.notifyList = {};
    },

    showDialog: function() {
        var option = {
            id: 'store-import-dg',
            ico: '<i class="font-icon ri-folder-transfer-line"></i>',
            title: LNG['storeImport.meta.name'],
            width:840,height:600,
            okVal: LNG['common.ok'],
        };
        var formData = {
			formStyle:{
                className: "form-box-title-block",
                tabs:{
                    import:"desc,pathFrom,pathTo",
                    logList:"logList",
                },
                tabsName:{
                    import:LNG['storeImport.meta.name'],
                    logList:LNG['common.history'],
                }
            },
			desc: {
				type: "html",
				display: LNG['admin.setting.recDesc'],
				value:
				"<div class='info-alert info-alert-blue p-10 align-left can-select can-right-menu'>"+LNG['storeImport.main.importDesc']+"</div>"
			},
			pathFrom: {
				type: "fileSelect",
				value: "",
				display: LNG['storeImport.main.ioFromPath'],
				info:{
					title:LNG['storeImport.main.selectPath'],
					single:true,
					type:'folder',
					pathOpen:'{io:1}/',
					valueKey:'path',
                    valueShowKey:'pathDisplay', //显示的key;
				    valueShow:'',//显示的key的值;
                    pathTree:'{block:driver}/',
				},
				attr: {"placeholder":LNG['admin.storage.localStore']},
                desc: LNG['storeImport.main.ioFromPathDesc'],
                require:1
			},
			pathTo: {
                type: "fileSelect",
				value: "",
				display: LNG['storeImport.main.ioToPath'],
				info:{
                    title:LNG['storeImport.main.selectPath'],
                    single:true,
					type:'folder',
					pathOpen:'{source:1}/',
					valueKey:'path',
                    valueShowKey:'pathDisplay', //显示的key;
                    valueShow:'',//显示的key的值;
                    pathTree:'{block:files}/',
				},
				attr: {"placeholder":LNG['storeImport.main.rootGroup']},
                desc: LNG['storeImport.main.ioToPathDesc'],
                require:1
			},
            logList:{
                type:"table",
                info:{
                    // TODO 可以加个删除，尤其是失败项
                },
                row:{
                    userID:{display:LNG['common.user'],template:'{{userInfo.nickName || userInfo.name}}'},
                    pathFrom:{display:LNG['common.detail'],template:'<code>{{pathFrom}} => {{pathTo}}</code>'},
                    status:{display:LNG['common.result'],template:'<span class="color-label icon-dot {{stateInfo.color}}-normal" title="{{stateInfo.text}}" title-timeout="200">{{taskInfo.taskFinished}} / {{taskInfo.taskTotal}}</span>'},
                    time:{display:LNG['admin.backup.timeTaken'],template:'{{window.timeShow((modifyTime - createTime) || 1)}}'},
                    createTime:{display:LNG['common.operateTime'],template:'{{window.dateFormat(createTime,"Y-m-d H:i:s")}}'}
                },
            },
		};
        var self = this;
        this.formMaker = new kodApi.formMaker({parent:this,formData:formData});
        this.formMaker.renderDialog(option, function (data) {
            self.doImport(_.pick(data, ['pathFrom','pathTo']));
            return false;
        });
        this.initLogList(); // TODO 切换时才获取，避免数据量大导致加载慢
        // this.bind('onRemove',function(){
        //     self.formMaker && self.formMaker.objectRemove();
		// });
    },
    onRemove: function () {
        // TODO 触发不了，原因未知
        this.formMaker && this.formMaker.objectRemove();
    },

    // 初始化（刷新）历史记录
    initLogList: function(){
        var self = this;
        kodApi.requestSend('plugin/storeImport/logGet',{},function(result) {
            if (!result || !result.code) return;
            self.formMaker.setValue('logList', result.data);
		});
    },

    // 导入提交
    doImport: function(data) {
        if (!_.startsWith(data.pathFrom, '{io:')) {
            return Tips.tips(LNG['storeImport.main.ioFromPathErr'], 'warning');
        }
        if (!_.startsWith(data.pathTo, '{source:')) {
            return Tips.tips(LNG['storeImport.main.ioToPathErr'], 'warning');
        }
        var key  = md5('store_import_'+jsonEncode(data));
        var notify = this.notifyList[key] || null;
        if (notify) return Tips.tips(LNG['storeImport.task.subErr'], 'warning');
        data.taskId = key + '|' + timeFloat()+roundString(4);

        var self = this;
        var tips = Tips.loadingMask();
        this.formDisable(true);
        var ajax = kodApi.requestSend('plugin/storeImport/start',data,function(result) {    // &hash='+hash
            tips && tips.close();
            checkDelay && clearTimeout(checkDelay);
            Tips.close(result);
            self.formDisable(false);
            if (!result || !result.code) return;
            self.showNotify(data, result);  // 需要显示导入情况——本地导入或文件很少时，来不及获取进度
		});
        var checkDelay = setTimeout(function() {
            tips && tips.close();
            if (ajax) ajax.abort();
            self.showNotify(data, false);
            self.impProcess(data);
        },2000);
    },
    // 导入进度
    impProcess: function (param) {
        var self = this;
        var data = $.extend({}, param, {process: 1});
        kodApi.requestSend('plugin/storeImport/start',data,function(result){
            self.showNotify(param, result);
            // if (!result.code || !result.data || _.get(result,'info') == '1') return;
            if (_.get(result,'info') != '0') return;
            return self._delay(function(){
                self.impProcess(param);
            }, 1000);
		});
    },
    // 导入进度提示
    showNotify: function (param, result) {
        var self = this;
        var data = _.pick(param, ['pathFrom','pathTo']);
        var key  = md5('store_import_'+jsonEncode(data));
        var tips = this.notifyList[key] || null;
        // 1.新建notify
        if (!tips || result === false) {
            this.notifyList[key] = tips = Tips.notify({
                title: LNG['storeImport.meta.name'],
                icon:'loading',
                // process: {text: '0 / 0', process: 0},
                process: {text: LNG['storeImport.task.getting'], process: 0},
                onClose: function(){
                    if (!self.notifyList[key]) return;  // 非手动关闭
                    var data = $.extend({}, param, {process: 1, kill: 1});
                    kodApi.requestSend('plugin/storeImport/start', data,function(result){
                        // self.notifyList[key] = 'killed';
                        _.unset(self.notifyList, key);
                        self.formDisable(false);
                    });
                }
            });
            if (!result || !_.has(result,'info')) return;
        }
        // 2.更新状态
        // 2.1 已关闭
        if (!tips || tips == 'killed') return;
        // 2.2 异常。进度：code=true：获取进度中，info=1：完成；info=0：进行中——部分无法获取到最终task，导致data=false，原因未知，暂不处理
        // show_tips错误(html)，该值为undefined；此时任务不一定结束，仍然关闭（kill）——实际为后端shutdown kill
        if (_.isUndefined(result) || !result.code || !_.has(result, 'info')) {
            var text = _.get(result, 'data', LNG['storeImport.task.reqErr']);
            if (!_.isString(text)) text = LNG['storeImport.task.taskErr'];
            tips.icon('error').content(text).processHide().close(5000);
            this.formDisable(false);
            return;
        }
        // TODO 2.3 尚未获取到 ——也可能任务被意外结束
        if (!result.data) return;
        // 2.4 更新进度
        var percent = _.get(result.data, 'taskPercent') || 0;
        var text = (_.get(result,'data.taskFinished') || 0) + ' / ' + (_.get(result,'data.taskTotal') || 0);
        tips.content(_.get(result, 'data.currentTitle') || '').process({text: text, process: percent});
        // 2.5 完成
        if (_.get(result,'info') == '1') { // percent=1
            var msg     = _.get(result, 'data.desc') || '';
            if(msg) msg = '<span class="fl-right">'+msg+'</span>';
            var icon    = 'success';
            var title   = LNG['storeImport.task.importEnd'];
            var status  = _.get(result, 'data.status') || '';
            if (status == 'kill' || status == 'error') {
                icon    = 'error';
                title   = LNG['storeImport.task.taskEnd'];
                percent = 1;
                tips.$main.find('.process-add').css('background-color', '#ff4d4f');
            }
            if (percent < 1) {
                icon = 'warning';
                tips.content(LNG['storeImport.task.partDesc']);
                // tips.$main.find('.process-add').css('background-color', '#f7ba29');
            }
            tips.icon(icon).title(title).process({text: text + msg, process: percent});
            this.formDisable(false);
            this._delay(function(){
                _.unset(self.notifyList, key);
                tips.close();
            }, 5000);
        }
    },

    // 禁/启用dialog中的操作
    formDisable: function (status) {
        var $form = this.formMaker.dialog.$main;
        var buttons = [
            '.aui-dialog .form-row.item-pathFrom button',
            '.aui-dialog .form-row.item-pathTo button',
            '.aui-dialog .aui-footer button',
        ];
        $form.find(_.join(buttons)).prop('disabled', status);

        // 更新日志记录
        if (!status) this.initLogList();
    }

});