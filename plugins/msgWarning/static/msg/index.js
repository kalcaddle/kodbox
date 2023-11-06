ClassBase.define({
	init: function (param) { 
        this.request = new kodApi.request({parent: this});
    },

    // 显示异常信息
    showTips: function(){
        var keyTimeout = 'msgwarning_ignore_timeout';
        var lastTime   = parseInt(LocalData.get(keyTimeout)) || 0;
        if (lastTime > time()) return;
        if ($('.kui-notify .msg-warning-tips').length) return;
		if (!this.request) return;
        var self = this;
        this.request.requestSend('plugin/msgWarning/message', {}, function(result){
            if (!result || !result.code || _.isString(result.data)) return;
            var msg = self.getMsg(result.data);
            if (!msg.length) return;
            var btnIgnore = '<button class="kui-btn tips-ignore size12 ml-10">'+LNG['msgWarning.main.ignoreTips']+'</button>';
            var tips = Tips.notify({
                title: LNG['msgWarning.main.tipsTitle'] + btnIgnore,
                icon: "warning",
                className: "msg-warning-tips",
                content: '<div class="info-alert align-left info-alert-error mt-5">'+_.join(msg,'')+'</div>',
                delayClose: 0
            });
            var height = $(document).height();
            tips.$main.find('.kui-notify-content-message').css({'max-height': (height - 100)+'px', 'overflow-y':'scroll'});
            tips.$main.delegate('.tips-ignore', 'click', function() {
                LocalData.set(keyTimeout, time() + 3600*24*5); // 5天内不再提示
                tips.close();
            });
        });
    },

    // 拼接异常信息
    getMsg: function(data) {
        var msg = [];
        _.each(data, function(item, k) {
            if (msg.length && item.length) {
                msg.push('<hr style="border-color:#fff;margin:0.4em 0;">');
            }
            _.each(item, function(val) {
                var value = '<li>'+val+'</li>';
                if (k == 'user' && _.startsWith(val, '<a')) {
                    value = val;
                }
                msg.push(value);
            });
        });
        return msg;
    }
});