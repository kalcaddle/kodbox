ClassBase.define({
	init: function(param){
		this.tab = 'ntcLogs';
		this.$el = param.form.$('.ntc-logs-page');

		var self = this;
		// this.listenTo(this.parent.tblView, 'ntc.table.action', function(tab, action){
		this.listenTo(this.parent, 'ntc.table.action.'+this.tab, function(action, $dom){
			self.doAction(action, $dom);
		});
	},

	doAction: function(action, $dom){
	    // 
	}

});