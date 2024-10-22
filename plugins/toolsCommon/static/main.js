kodReady.push(function(){

    // 支付回调
    Events.bind("router.after", function () {   // router.before
        if (!$.isWap) return;
        var payLink = _.get(Router, 'query.payLink') || '';
        payLink = jsonDecode(base64Decode(urlDecode(payLink)));
        if (!payLink || !payLink.link || !payLink.text) return;
        var data = _.pick(payLink, ['type','orderNo','code']);  // sign
        if (_.keys(data).length != 3) return;

        // 获取订单信息，触发前端事件并跳转
        $.dialog.confirm(payLink.text,function(){
            $.ajax({
                url: payLink.link,
                data: $.extend({}, data, {query: 1, language: G.lang}),
                dataType:'jsonp',
                success:function(result){
                    Events.trigger('pay.finished',result);  // 监听执行后自行处理跳转
                }
            });
        },function(){
            var parse = $.parseUrl(window.location.href);
			window.location.href = parse.urlPath + '#' + Router.hash;
        });
    }, this);
});
