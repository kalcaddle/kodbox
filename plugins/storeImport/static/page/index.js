ClassBase.define({
	init: function (param) {},
    initView: function () {
        // 添加导入按钮
        this.$el = $('.admin-page .storage-page');
        var $toolbar = this.$('.store-list-box .toolbar');
        if (!$toolbar.children('.right').length) {
            $toolbar.append('<div class="right"><button class="kui-btn import">数据导入</button></div>');
        }
        $toolbar.undelegate('button.import','click');
        $toolbar.delegate('button.import', 'click', _.bind(this.impDialog, this));

        this.notifyList = {};
    },

    impDialog: function() {
        var option = {
            id: 'store-import-dg',
            ico: '<i class="font-icon ri-folder-transfer-line"></i>',
            title: '数据导入',
            // width:680,height:565,
            width:720,height:600,
            okVal: LNG['common.ok'],
        };
        var formData = {
			formStyle:{className: "form-box-title-block"},
			desc: {
				type: "html",
				display: LNG['admin.setting.recDesc'],
				value:
				"<div class='info-alert info-alert-blue p-10 align-left can-select can-right-menu'>\
					<h5>通过对磁盘或对象存储进行扫描，自动构建索引，实现文件快速导入到网盘。</h5>\
					<div class='mt-10 mb-20'>\
                        <li>1. 网盘文件需通过存储路径访问，<u>因此在导入之前，需将待导入的原始数据目录（或父级目录）添加为存储</u>；</li>\
                        <li>2. 此操作不会对原始数据做改动，只构建文件索引映射到网盘，<u>在导入之后，不能对原始数据做任何改动，避免索引失效</u>；</li>\
                        <li>3. 导入数据与网盘默认存储数据的路径结构不同，建议将导入数据的存储作为附加存储（而非系统默认存储）使用，以维护数据结构统一；</li>\
                        <li>4. 导入之前建议对数据库进行备份，以免出现意外。</li>\
					</div>\
                    <div>注意：<u>文件路径长度超过256个字符会被限制导入</u>，相关日志放在网盘存放目录下的“导入失败日志-长度超256字符”文件夹中，可在导入完成后自行查看并处理。</div>\
				</div>"
			},
			pathFrom: {
				type: "fileSelect",
				value: "",
				display: '原始数据目录',
				info:{
					title:'选择目录',
					single:true,
					type:'folder',
					pathOpen:'{io:1}/',
					valueKey:'path',
                    valueShowKey:'pathDisplay', //显示的key;
				    valueShow:'',//显示的key的值;
                    pathTree:'{block:driver}/',
				},
				attr: {"placeholder":'本地存储'},
                desc: '需要导入的原始数据目录，必须为存储下的目录',
                require:1
			},
			pathTo: {
                type: "fileSelect",
				value: "",
				display: '网盘存放目录',
				info:{
                    title:'选择目录',
                    single:true,
					type:'folder',
					pathOpen:'{source:1}/',
					valueKey:'path',
                    valueShowKey:'pathDisplay', //显示的key;
                    valueShow:'',//显示的key的值;
                    pathTree:'{block:files}/',
				},
				attr: {"placeholder":'企业网盘'},
                desc: '需要存放的网盘系统目录，如个人空间、企业网盘或其子目录',
                require:1
			},
            // checkHash: {
            //     type:"switch",
            //     value:0,
            //     display:"计算文件哈希（仅限本地存储）",
            //     desc:"为导入的文件计算哈希值，后续上传同文件时可实现秒传。计算哈希比较耗时，文件较多时不建议开启。",
            //     className:"hidden"
            // },
            cli: {
                type:"html",
				display:' ',
				value: '<div class="mt-10">\<div>数据量很大时，可能会因为超时导致任务中止，可选择在终端执行以下命令进行导入：<span class="kui-btn btn btn-sm" style="vertical-align:bottom">'+LNG['explorer.copy']+'</span></div>\
                <code style="overflow-wrap:break-word;white-space:normal;display:block;padding:6px 10px!important;margin-top:5px;border-radius:4px;"></code>\
                <div class="mt-5 desc">注意：此命令使用 `sudo -u nginx` 是为了确保 PHP 进程以正确的用户身份执行，避免文件权限问题。如果你的 Web 服务器用户不是 `nginx`，请根据你的服务器配置将 `nginx` 替换为适当的用户，如 root、www-data/apache 等。</div>\
                </div>'
            }
		};
        var self = this;
        this.formMaker = new kodApi.formMaker({parent:this,formData:formData});
        this.formMaker.renderDialog(option, function (data) {
            // var hash = data.checkHash == '1' ? 1 : 0;
            self.doImport(_.pick(data, ['pathFrom','pathTo']));
            return false;
        });
        // this.listenTo(this.formMaker,'onChange',function(key,value,$row){});
        this.formMaker.bind('onChange',function(key,value) {
            self.formMaker.$('.item-cli').hide();
            if (!value) return;
            var pathFrom = key == 'pathFrom' ? value : self.formMaker.getValue('pathFrom');
            var pathTo = key == 'pathTo' ? value : self.formMaker.getValue('pathTo');
            if (!pathFrom || !pathTo || !_.startsWith(pathFrom, '{io:') || !_.startsWith(pathTo, '{source:')) return;

            var cmd = "sudo -u nginx php "+G.kod.BASIC_PATH+"index.php 'admin/storage/import&accessToken="+G.kod.accessToken+"' --pathFrom-'"+pathFrom+"' --pathTo-'"+pathTo+"'";
            self.formMaker.$('.item-cli code').html(cmd);
            self.formMaker.$('.item-cli').show();
		});
        this.formMaker.$el.delegate('.item-cli .btn', 'click', function(){
            $.copyText($(this).parents('.setting-content').find('code').text());
			Tips.tips(LNG['explorer.share.copied']);
        });
    },
    onRemove: function () {
        // TODO 
        this.formMaker && this.formMaker.objectRemove();
    },

    // 导入提交
    doImport: function(data) {
        if (!_.startsWith(data.pathFrom, '{io:')) {
            return Tips.tips('原始数据目录错误，必须为存储目录！', 'warning');
        }
        if (!_.startsWith(data.pathTo, '{source:')) {
            return Tips.tips('网盘存放目录错误，必须为网盘系统目录！', 'warning');
        }
        var key  = md5('store_import_'+jsonEncode(data));
        var notify = this.notifyList[key] || null;
        if (notify) return Tips.tips('任务进行中，请勿重复提交！', 'warning');

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
        var key  = md5('store_import_'+jsonEncode(param));
        var tips = this.notifyList[key] || null;
        // 1.新建notify
        if (!tips || result === false) {
            this.notifyList[key] = tips = Tips.notify({
                title: '数据导入',
                icon:'loading',
                // process: {text: '0 / 0', process: 0},
                process: {text: '读取中，请稍候', process: 0},
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
        // if (!result.code || !result.data || !_.has(result, 'info')) {
        if (!result.code || !_.has(result, 'info')) {
            var text = _.get(result, 'data') || '请求失败，或任务终止！';
            if (!_.isString(text)) text = '任务异常！';
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
            var title   = '导入完成！';
            var status  = _.get(result, 'data.status') || '';
            if (status == 'kill' || status == 'error') {
                icon    = 'error';
                title   = '任务结束！';
                percent = 1;
                tips.$main.find('.process-add').css('background-color', '#ff4d4f');
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
    }

});