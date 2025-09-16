ClassBase.define({
	init: function(param){
		this.initParentView(param);
        // 主页tab
		var package = this.formData();
		var form = this.initFormView(package); // parent: form.parent;
		form.setValue(G.clientOption);
        form.$('.form-target-save').hide();
        // 主页表格
        this.initTable(form); // 初始化表格
	},

	formData:function(){
		return {
            formStyle:{
                className:"form-box-title-block dialog-form-style-simple sys-notice-page",
                tabs:{
                    ntcEvnt:"ntcEvntPage",
                    ntcType:'ntcTypePage',
                    ntcLogs:'ntcLogsPage'
                },
                tabsName:{
                    ntcEvnt:LNG['msgWarning.main.tabEvnt'],
                    ntcType:LNG['msgWarning.main.tabType'],
                    ntcLogs:LNG['msgWarning.main.tabLogs'],
                }
            },
            ntcEvntPage:{
                type:"html",
                value:'<div class="ntc-evnt-page"></div>',
                display:'',
                desc:'',
            },
            ntcTypePage:{
                type:"html",
                value:'<div class="ntc-type-page"></div>',
                display:'',
                desc:'',
            },
            ntcLogsPage:{
                type:"html",
                value:'<div class="ntc-logs-page"></div>',
                display:'',
                desc:'',
            },
        }
    },

    // 初始化table
	initTable: function(form){
		var self = this;
        // 主页表格
		var admPath = './plugins/msgWarning/static/admin/';
		requireAsync([
			admPath + 'table/list.js',
			admPath + 'table/table.js',
			admPath + 'table/toolbar.js',
			admPath + 'index.css',
		],function(TblView, TblList, TblBar){
			self.tblView = new TblView({parent: self, Table: TblList, Toolbar: TblBar});
            self.listenTo(self.tblView, {
                'ntc.table.action': function(tab, action, $dom){
                    self.trigger('ntc.table.action.'+tab, action, $dom);
                },
            });
            self.trigger('ntc.tab.change', 'ntcEvnt');
            self.trigger('ntc.tab.change', 'ntcType');  // 获取通知方式列表，用于事件详情
		});
        // 主页附加（详情功能）
        requireAsync([
			admPath + 'extra/evnt.js',
			admPath + 'extra/type.js',
			admPath + 'extra/logs.js',
			admPath + 'extra/package.js',
		],function(NtcEvnt, NtcType, NtcLogs, formData){
            new NtcEvnt({parent: self, form:form, dgFormData: formData});
            new NtcType({parent: self, form:form, dgFormData: formData});
            new NtcLogs({parent: self, form:form});
		});

        // 切换tab菜单
        form.$el.delegate('.tab-group-line .tab-item','click',function(){
            var tabName = $(this).attr('tab-name');
            self.trigger('ntc.tab.change', tabName);
        });
        
        this.addResetBtn(form);
	},

    // 添加重置按钮
    addResetBtn: function(form){
        var self = this;
        var html = '<div class="reset-app-btn"><span class="kui-btn btn"><i class="font-icon ri-restart-line"></i> '+LNG['msgWarning.main.appReset']+'</span></div>';
        form.$('.tab-group[role=tablist]').after(html);
        form.$el.delegate('.reset-app-btn','click',function(){
            var tab = 'ntcEvnt';   // 在事件文件中执行
            self.trigger('ntc.table.action.'+tab, 'reset', $(this));
        });
    },

    // 通知方式列表
    ntcEvntList: function(){
        return this.tblView.ntcEvntList();
    },
    // 通知方式列表
    ntcTypeList: function(){
        return this.tblView.ntcTypeList();
    },
    // 刷新列表
    ntcTableRefresh: function(tab){
        this.trigger('ntc.table.refresh', tab);
    }
});