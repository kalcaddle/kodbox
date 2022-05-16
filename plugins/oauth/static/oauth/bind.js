ClassBase.define({
    init: function () {
        this.request = new kodApi.request({parent: this});
    },
    // 绑定
    bind: function (type, action = 'login', client = 1) {
        var self = this;
        // 判断是否在微信浏览器
        this.platform = $.browserIS.weixin ? 'mp' : 'open';  // 公众平台or开放平台

        var param = {method: 'oauth', type: type, action: action, client: client, state: this.platform};
        this.request.requestSend('plugin/oauth/bind', param, function(result){
            if (!result.code) {
                Tips.tips(result.data, false);
                return false;
            }
            self.bindSubmit(type, result.data, result.info);
        });
    },
    // 解绑
    unbind: function (type, parent) {
        var self = this;
        $.dialog({
            id: 'dialog-unbind-confirm',
            fixed: true,//不跟随页面滚动
            // icon: 'question',
            padding: 30,
            width: 250,
            lock: true,
            background: "#000",
            opacity: 0.2,
            content: LNG['user.ifUnbind'],
            ok: function () {
                self.request.requestSend('plugin/oauth/bind', {method: 'unbind', type: type}, function(result){
                    Tips.tips(result.data, result.code);
                    if (!result.code) return;
                    parent.$("#" + type + ">span:nth-child(2), #" + type + ">span:nth-child(3)").remove();
                    var html = '<span class="w50"><a class="bind" href="javascript:void(0)">' + LNG['user.clickBind'] + '</a></span>';
                    parent.$("#" + type).append(html);
                });
            },
            cancel: true
        });
    },
    // (第三方)账号绑定提交
    bindSubmit: function (type, param, appid='') {
        var directUrl = G.system.settings.kodApiServer + 'plugin/platform/&' + param;
        switch (type) {
            case 'weixin':
                this.weixinBind(directUrl, appid);
                break;
            case 'qq':
            case 'github':
            case 'google':
            case 'facebook':
                window.top.location.href = directUrl;
                break;
            default: break;
        }
    },
    weixinBind: function (url, appid) {  // 微信绑定
        // 微信内打开，使用公众号接口授权登录
        if(this.platform == 'mp') {
            var param = [
                'appid='+appid,
                'redirect_uri='+urlEncode(url),
                'response_type=code',
                'scope=snsapi_userinfo',
                'state='+this.platform
            ];
            window.location.href = 'https://open.weixin.qq.com/connect/oauth2/authorize?'+param.join('&')+'#wechat_redirect';
            return false;
        }
        // 微信外，使用开放平台接口授权
        var connect = _.startsWith(G.lang, 'zh') ? '' : ' ';
        $.dialog({
            id: 'bindlogin',
            title: LNG['user.bind'] + connect + LNG['common.wechat'],
            ico: '',
            width: 300,
            height: 400,    // 300
            lock: true,
            background: '#fff',
            opacity: 0.1,
            resize: true,
            fixed: true,
            content: '<div id="wxqrcode"></div>'
        });
        requireAsync("//res.wx.qq.com/connect/zh_CN/htmledition/js/wxLogin.js", function(){
            new WxLogin({
                self_redirect: true,
                id: "wxqrcode",
                appid: appid,
                scope: "snsapi_login",
                redirect_uri: urlEncode(url),
                state: "",
                style: "",
                href: ""
            });
        });
    }
});