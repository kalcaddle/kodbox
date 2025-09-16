ClassBase.define({
    init: function (param) {
        this.Table = new param.Table({ parent: this });
        if (param.Toolbar) this.Toolbar = new param.Toolbar();

        var self = this;
        // this.showTable('ntcEvnt');
        this.listenTo(param.parent, 'ntc.tab.change', function(tab){
            self.showTable(tab);
        });
        this.listenTo(param.parent, 'ntc.table.refresh', function(tab){
            self.refreshTable(tab);
        });
    },

    showTable: function(tab){
        var self = this;
        if(!_.isUndefined(this[tab+'Table'])) return;
        var tblpage = '.'+_.replace(_.toLower(tab),'ntc','ntc-')+'-page';   // .ntc-evnt-page

        var data = this.queryData(tab);
        this[tab+'Table'] = this.Table.make(data);
        this.listenTo(this[tab+'Table'], 'after.table.make', function () {
            // 
        });
        // 列表项操作；[tab+'Table'].$el为.admin-main-content，直接绑定会影响所有table
        this[tab+'Table'].$(tblpage).delegate('.list-table .list-col.setting .do-action', 'click', function(){
            var action = $(this).attr('data-action');
            // var data = base64Decode($(this).attr('data'));    // json
            self.doAction(tab, action, $(this));
        });
        this[tab+'Table'].$(tblpage).delegate('.list-table .list-col input', 'change', function(){
            var action = $(this).attr('name');
            self.doAction(tab, action, $(this));
        });

        // 表头菜单
        if (tab == 'ntcType') return;
        var config = this.tabToolbar(tab);
        this[tab+'Toolbar'] = new kodApi.formMaker({parent:this,formData:config});
        this[tab+'Toolbar'].renderTarget(this[tab+'Table'].$(tblpage+' .toolbar .left')); // 不加tab-list-page会覆盖前一个tab的toolbar
        this[tab+'Toolbar'].$("[name]").bind('change', function () {
            // // 暂不需要
            // if (tab == 'ntcEvnt' && $(this).attr('name') == 'addBtn') {
            //     self.doAction(tab, 'add', $(this));
            //     return;
            // }
            self.refreshTable(tab);
		});
        if (tab == 'ntcEvnt') {
            this[tab+'Table'].$(tblpage+' .toolbar .left .item-status').appendTo(this[tab+'Table'].$(tblpage+' .toolbar .right'));
            this[tab+'Table'].$(tblpage+' .toolbar .right').removeClass('right').addClass('align-right');
        }
    },
    refreshTable: function (tab) {
        var data = this.queryData(tab);
		this[tab+'Table'].config.data = data;
		this[tab+'Table'].refresh();
	},
    tabToolbar: function(tab){
        var config = $.objClone(this.Toolbar.config());
        var delOpt = ['time', 'timeFrom', 'timeTo'];
        if (tab != 'ntcEvnt') {
            delOpt = ['addBtn', 'status', 'class', 'level'];
        }
        delOpt.push('addBtn');  // 暂不需要
        return _.omit(config, delOpt);
    },

    // 获取搜索条件
	queryData: function(tab){
        var query = {table: tab};
        if(!_.isUndefined(this[tab+'Toolbar'])) {
			var self = this;
			this[tab+'Toolbar'].$("[name]").each(function(){
				var key = $(this).attr('name');
                var val = self[tab+'Toolbar'].getValue(key);
                query[key] = val;
			});
            // dom移动到.right，需要单独获取
            if (tab == 'ntcEvnt') {
                query.status = this[tab+'Toolbar'].$el.parents('.toolbar').find('input[name=status]').val();
            }
		}
		return query;
	},

    onRemove: function(){
        var self = this;
        _.each(['ntcEvnt', 'ntcType', 'ntcLogs'], function(tab){
            self[tab+'Toolbar'] && self[tab+'Toolbar'].objectRemove();
            self[tab+'Table'] && self[tab+'Table'].objectRemove();
        });
        _.each($.dialog.list, function(dg){dg && dg.close();});
    },

    // 列表项操作
    doAction: function (tab, action) {
        this.trigger('ntc.table.action', tab, action, arguments[2]);
    },
    ntcEvntList: function () {
        return this.Table.ntcEvntListAll;
    },
    ntcTypeList: function () {
        return this.Table.ntcTypeListAll;
    },

});