ClassBase.define({
    init:function(){
        this.request = new kodApi.request({parent: this});
    },
    // 绑定
    bindAcc:function(type,action,client){
		action = action == undefined ? 'login' : action;
		client = client == undefined ? 1 : client;
        if (this.notifyTips) {
            return Tips.tips(LNG['oauth.main.loginRpt'], 'warning');
        }
        // 判断是否在微信浏览器
        this.platform = $.browserIS.weixin ? 'mp' : 'open';  // 公众平台or开放平台

		var self = this, tips = false;
        var param = {method: 'oauth', type: type, action: action, client: client, state: this.platform};
		if(type != 'weixin'){tips = Tips.loadingMask(false, LNG['oauth.main.loading']);}
        this.request.requestSend('plugin/oauth/bind', param, function(result){
			if(tips){tips.close();}
            if(!result.code) {
                Tips.tips(result.data, false,5000);
                return false;
            }
            self.bindSubmit(type, base64Decode(result.data), result.info);
        });
    },
    // 解绑
    unbindAcc: function (type, parent) {
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
    bindSubmit: function (type,param,appid) {
		appid = appid || '';
        var directUrl = G.system.settings.kodApiServer + 'plugin/platform/&' + param;
        switch (type) {
            case 'weixin':
                this.weixinBind(directUrl, appid);
                break;
            case 'qq':
            case 'github':
            case 'google':
            case 'facebook':
                if (type != 'qq') this.showTips(type);
                window.top.location.href = directUrl;
                break;
            default: break;
        }
    },
    wxMsgHandler: function(event){
        var parse = $.parseUrl(event.origin);
        if (!parse || parse.host != 'api.kodcloud.com') return;
        var $iframe = this.wxQrdialog.$main.find('#wxqrcode>iframe');
        if (!$iframe || !$iframe.length) return;
        // 回复——兼容旧版
        $iframe[0].contentWindow.postMessage({from:'kodbox',event:'weixin'}, '*');
        // 跳转
        var data = _.get(event, 'data', {});
        if (data.from == 'kodapi' && data.url) {
            $iframe.attr('src', data.url);
        }
        window.removeEventListener('message', this.wxMsgHandler);   // 移除监听
    },
    weixinBind: function (url, appid) {  // 微信绑定
        // 接收api回调信息并跳转
        var self = this;
        window.addEventListener('message', function(event){self.wxMsgHandler(event);});
        this.bind('onRemove',function(){
            window.removeEventListener('message', self.wxMsgHandler);   // 移除监听
        });

        // 微信内打开，使用公众号接口授权登录
        // https://developers.weixin.qq.com/doc/service/guide/h5/auth.html
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
        // https://developers.weixin.qq.com/doc/oplatform/Website_App/WeChat_Login/Wechat_Login.html
        var connect = _.startsWith(G.lang, 'zh') ? '' : ' ';
        this.wxQrdialog = $.dialog({
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
    },

    // 跳转加载慢，显示提示信息
    showTips: function(type){
        var self = this;
        var tips = _.replace(LNG['oauth.main.loadTips'], '[0]', _.upperFirst(type));
        this.notifyTips = Tips.notify({
            icon:"loading",
            title:LNG['common.tips'],
            content:"<div style='line-height:20px;'>"+tips+"（https://"+type+".com）</div>",
            onClose:function(){
                self.notifyTips = null;
                window.top.location.reload();
                return false;
            }
        });
    },
});