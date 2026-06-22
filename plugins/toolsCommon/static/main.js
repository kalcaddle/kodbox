kodReady.push(function(){

    // 支付回调——WAP端从支付平台跳回时，URL携带payLink参数
    Events.bind("router.after", function () {
        if (!$.isWap) return;
        var payLink = _.get(Router, 'query.payLink') || '';
        if (!payLink) return;
        // Router.go(Router.hash); // 去掉参数，防止重复执行
        // 使用 replaceState 清理 URL 参数，避免页面重新渲染
        var url = location.href.replace(/[&?]payLink=[^&]*/, '');
        if (window.history && window.history.replaceState) {
            window.history.replaceState(null, '', url);
        }
        payLink = jsonDecode(base64Decode(urlDecode(payLink)));
        if (!payLink) return;
        var result = _.pick(payLink, ['code','data','info','sign']);
        if (_.keys(result).length < 3) return;

        // 验证回调签名
        var callbackSignKey = md5('kodPayCallback2024');
        var verifyCallbackSign = function(result){
            var data = result.data;
            var str = data.orderNo + '|' + data.payType + '|' + result.info + '|' + data.status;
            return md5(str + callbackSignKey).toUpperCase() === result.sign;
        };
        if (!result.sign || !verifyCallbackSign(result)) return;

        // 等待 kodPayForm 就绪后触发事件，pay.js 负责 UI 展示
        var triggerPayFinished = function(fallback){
            Events.trigger('pay.finished', result);
            if (fallback) {
                var icon = 'warning';
                if (_.get(result,'info') == 2) {
                    icon = 'succeed';
                    kodApi.requestSend('user/license&resetNow=1');
                }
                var text = _.get(result,'data.title','') +'<br/>'+_.get(result,'data.text','');
                $.dialog.alert(text, function () {
                    _.delay(function(){Router.refresh();}, 500);
                }, icon);
            }
        };
        if (window.kodPayForm) {
            triggerPayFinished();
        } else {
            // 尚未加载则轮询等待，最多等 5 秒，超时使用 fallback 展示 UI
            var waited = 0;
            var timer = setInterval(function(){
                waited += 200;
                if (window.kodPayForm) {
                    clearInterval(timer);
                    triggerPayFinished();
                } else if (waited >= 5000) {
                    clearInterval(timer);
                    triggerPayFinished(true);
                }
            }, 200);
        }
    }, this);
});
