ClassBase.define({
	init: function (param) {
        this.ntcNotifyList = {};
    },

    // 显示异常信息
    showTips: function(){
        var self = this;
        var userID = _.get(G, 'user.userID');
        if (!userID) return;

        // 获取消息
        var getNotice = function(){
            kodApi.requestSend('plugin/msgWarning/notice', {}, function(result){
                self._delay(function(){getNotice();}, 1000 * 60);   // 每分钟请求一次
                if (!result || !result.code) return;
                if (!result.data) return;
                _.each(result.data, function(item, key){
                    if (!item) return true;
                    var func = 'show'+_.upperFirst(key);
                    if (self[func]) self[func](item);
                });
            });
        }
        getNotice();
    },

    // 显示系统信息
    showKtips: function(data){
        var self = this;
        var showNotify = function(item, event){
            var icon = 'info';
            if (item.level > 1) {
                // icon = item.level == 4 ? 'error' : 'warning';
                icon = 'warning ntc-level-'+item.level;
            }
            // var text = item.message.join('<br/>');
            var text = item.message[0]; // 只取第1行信息
            if (!self.ntcNotifyList) self.ntcNotifyList = {};
            if (self.ntcNotifyList[event]) {
                // 更新内容
                self.ntcNotifyList[event].icon(icon).content(text);
                return;
            }
            self.ntcNotifyList[event] = Tips.notify({
                icon:icon,
                // title:item.title,
                content:text,
                onClose:function(){
                    _.unset(self.ntcNotifyList, event);
                }
            });
        }
        // 循环显示所有消息
        var idx = 0;
        data = this.sortByLevel(data, true);   // 降序，严重的先显示
        _.each(data, function(item, event){
            // Tips.notify.tips(msg[0], 'warning');
            idx++;
            self._delay(function(){
                showNotify(item, event);
            }, idx*100);
        });
    },

    // 显示系统通知
    showKwarn: function(data){
        // 事件等级颜色
        var levels = {
            level1: 'blue',
            level2: 'yellow',
            level3: 'orange',
            level4: 'red',
        };
        var html = '';
        data = this.sortByLevel(data, true);   // 降序，严重的先显示
        _.each(data, function(item, event){
            // var text = item.message.join('<br/>');
            var text = item.message[0];
            html += '<div class="list-item">\
                        <span class="title"><i class="bg-'+levels['level'+item.level]+'-normal"></i>'+item.title+'</span><br/>\
                        <span class="content">'+text+'</span>\
                    </div>';
        });
        if (!html) return;
        html = '<div class="list-item time">## '+dateFormate(time(),'H:i')+'</div>'+html;

        // 没有关闭则追加到消息列表前面
        var dgId = 'msgWarning-kwarn-dialog';
        if ($.dialog.list[dgId]) {
            $.dialog.list[dgId].$main.find('.kwarn-content').prepend(html);
            return;
        }
        $.dialog({
            id: dgId,
            title:LNG['msgWarning.main.sysNotice'],
            // className:'',
            ico:"<i class='font-icon ri-volume-up-fill'></i>",
            width:'480px',height:'220px',
            lock:true,
            opacity:0.1,
            resize:false,
            content:'<div class="kwarn-content">'+html+'</div>',
            ok:function(){
                //
            }
        });

    },

    // 按等级排序
    sortByLevel: function(data, desc){
        var entries = Object.keys(data).map(function(key) {
            return [key, data[key]];
        });
        entries.sort(function(a, b) {
            if (desc) return b[1].level - a[1].level;
            return a[1].level - b[1].level;
        });
        var result = {};
        entries.forEach(function(item) {
            result[item[0]] = item[1];
        });
        return result;
    },

    onRemove: function () {
        // ntcNotifyList没必要销毁
    }

});