kodReady.push(function(){

    // 支付回调
    Events.bind("router.after", function () {   // router.before
        if (!$.isWap) return;
        var payLink = _.get(Router, 'query.payLink') || '';
        if (!payLink) return;
        Router.go(Router.hash); // 去掉参数，防止重复执行
        payLink = jsonDecode(base64Decode(urlDecode(payLink)));
        if (!payLink) return;
        var result = _.pick(payLink, ['code','data','info']);
        if (_.keys(result).length != 3) return;

        _.delay(function(){
            Events.trigger('pay.finished', result); // 滞后触发，避免事件尚未绑定
            var icon = 'warning';
            if (_.get(result,'info') == 2) {
                icon = 'succeed';
                kodApi.requestSend('user/license&resetNow=1');
            }
            var text = _.get(result,'data.title','') +'<br/>'+_.get(result,'data.text','');
            $.dialog.alert(text, function () {
                _.delay(function(){Router.refresh();}, 500);
            }, icon);
        }, 1000);
    }, this);
});
