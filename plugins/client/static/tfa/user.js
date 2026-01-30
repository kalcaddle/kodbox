ClassBase.define({
	init: function (param) {
		this.request = new kodApi.request({parent: this});
		this.initView();
	},
	initView: function () {
		var self = this;
		this.request.requestSend('plugin/client/tfa', {action: 'getBindInfo'}, function (result) {
			if (!result || !result.code) return;
			self.renderRow(result.data.isBind);
		});
	},
	renderRow: function (isBind) {
		var self = this;
		var title = LNG['client.tfa.google'];
		var content = isBind ? LNG['user.binded'] : '<a class="bind-tfa" href="javascript:void(0)">' + LNG['user.clickBind'] + '</a>';
		var action = isBind ? '<span class="col-action"><a class="unbind-tfa" href="javascript:void(0)">' + LNG['user.unbind'] + '</a></span>' : '';
		
		var html = 
			'<div class="acc-row item-tfa-google">' +
				'<span class="col-title">' +
					'<i class="font-icon ri-shield-user-line"></i>' +
					'<span>' + title + '</span>' +
				'</span>' +
				'<span class="col-content">' + content + '</span>' + action +
			'</div>';
		
		var $pwdRow = this.$('.account-page .user-set .form-row.item-change-password');
		this.$('.item-tfa-google').remove();
		if ($pwdRow.length) {
			$pwdRow.after(html);
		} else {
			this.$('.account-page .user-set').append(html);
		}
		this.bindEvent();
	},
	bindEvent: function () {
		var self = this;
		this.$('.item-tfa-google').unbind('click').bind('click', function (e) {
			e.stopPropagation();
		});
		this.$('.item-tfa-google .bind-tfa').unbind('click').bind('click', function (e) {
			e.stopPropagation();
			self.showBindDialog();
		});
		this.$('.item-tfa-google .unbind-tfa').unbind('click').bind('click', function (e) {
			e.stopPropagation();
			$.dialog.confirm(LNG['user.unbind'] + '?', function () {
				self.request.requestSend('plugin/client/tfa', {action: 'unbindGoogle'}, function (result) {
					Tips.close(result);
					if (result.code) self.initView();
				});
			});
		});
	},
	showBindDialog: function () {
		var self = this;
		this.request.requestSend('plugin/client/tfa', {action: 'initGoogle'}, function (result) {
			if (!result || !result.code) return Tips.tips(result);
			var data = result.data;
			var html = 
				'<div class="tfa-bind-box" style="text-align:center;padding:20px;">' +
					'<div class="qrcode" style="margin-bottom:15px;"><img src="' + data.qrCode + '" style="width:200px;height:200px;"/></div>' +
					'<div class="desc" style="margin-bottom:15px;color:#888;">' + LNG['client.tfa.scanCode'] + '</div>' +
					'<div class="input-item">' +
						'<input type="text" name="code" placeholder="' + LNG['user.inputVerifyCode'] + '" style="width:200px;height:30px;padding:0 10px;border:1px solid #ddd;border-radius:3px;text-align:center;"/>' +
					'</div>' +
				'</div>';
			
			$.dialog({
				id: 'tfa-bind-dialog',
				title: LNG['client.tfa.bind'],
				content: html,
				width: 300,
				fixed: true,
				ok: function () {
					var code = $.trim($('.tfa-bind-box input[name="code"]').val());
					if (!code) {
						$('.tfa-bind-box input[name="code"]').focus();
						return false;
					}
					self.request.requestSend('plugin/client/tfa', {
						action: 'bindGoogle',
						secret: data.secret,
						code: code
					}, function (res) {
						Tips.close(res);
						if (res.code) {
							self.initView();
							$.dialog.list['tfa-bind-dialog'].close();
						}
					});
					return false;
				},
				cancel: true
			});
		});
	}
});