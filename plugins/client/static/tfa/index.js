ClassBase.define({
	init: function (param) {
        this.$el = this.parent.$('.login-form');
        this.checkInput = _.get(this,'checkInput.check');
        if (typeof _.get(this,'checkInput.check') != 'function') this.checkInput = false;
    },

    // 判断是否需要二次验证
    needTfa: function (data) {
        // 1.判断二次验证是否开启
        if (data === true) {
            var tfaOpen = _.get(G,'system.options.tfaOpen') || 0;
            var tfaType = _.get(G,'system.options.tfaType') || '';
            return (tfaOpen != '1' || !tfaType) ? false : true;
        }
        // 2.判断用户（已通过）二次验证信息
        if (data.isRoot || data.isTfa == 1) return false;
        this.showDialog(data);
    },

    // 显示二次验证弹窗
    showDialog: function (data) {
        // 去除登录页加载样式
        NProgress.done();
        $("body .loading-msg-mask").remove();
        this.$('.submit-button.login').removeClass('disable-event');

        var self = this;
        var userID  = _.get(data,'userID') || '';
        var tfaInfo = _.get(data,'tfaInfo') || {};
        var type    = _.get(tfaInfo,'type') || '';
        if (!type) {
            var tfaType = _.get(tfaInfo,'tfaType') || ''
            var typeArr = _.split(tfaType,',');
            type = typeArr.length > 1 ? typeArr : tfaType;
        }
        var text = _.isArray(type) ? LNG['common.input']+LNG['client.tfa.phoneNumEmail'] : LNG['user.input'+_.upperFirst(type)];
        var name = _.isArray(type) ? LNG['client.tfa.phoneEmail'] : LNG['common.'+type];
        var html = '<div class="inputs">\
                        <div class="input-item def">\
                            <p class="title">'+name+LNG['client.tfa.verify']+'</p>\
                            <p name="">'+LNG['client.tfa.sendTo']+' '+tfaInfo.input+'</p>\
                        </div>\
                        <div class="input-item">\
                            <input name="input" type="text" placeholder="'+text+'">\
                        </div>\
                        <div class="input-item check-code">\
                            <input name="code" type="text" placeholder="'+LNG['user.inputVerifyCode']+'">\
                            <button type="button" class="input-button">'+LNG['user.getCode']+'</button>\
                        </div>\
                    </div>';
        this.dialog = $.dialog({
            ico: '<i class="font-icon ri-lock-password-line"></i>',
            id: 'login-tfa-dialog',
            title: LNG['client.tfa.2verify'],
            width:320,
            height:100,
            padding: '15px 10px',
            resize:false,fixed:true,lock:true,
            background: "#000",opacity: 0.2,
            content: html,
            ok: function(e){
                var data = self.getInputData(tfaInfo, type);
                var $code = self.dialog.$main.find('input[name="code"]');
                var code = $.trim($code.val());
                if (!code) {$code.focus();return false;}
                data.userID = userID;
                data.code = code;

                self.doVerify(data);
                return false;
            },
            cancel: false,
            close:false
        });
        var $dialog = this.dialog.$main;
        if (tfaInfo.input) {
            $dialog.find('input[name="input"]').parent().hide();
        } else {
            $dialog.find('.input-item.def p[name]').hide();
        }
        var $submit = $dialog.find('button.aui-state-highlight');
        $submit.prop("disabled", true);

        // 获取验证码
        $dialog.delegate('.check-code button', 'click', function(){
            var $btn = $(this);
            var data = self.getInputData(tfaInfo, type);
            data.userID = userID;
            data.action = 'tfaCode';

            var tips = Tips.loadingMask();
            $btn.prop("disabled", true);
            self.request(data, function(result){
                tips.close();
                Tips.close(result);
                if (!result.code) {
                    $btn.prop("disabled", false);
                    return false;
                }
                $submit.prop("disabled", false);
                self.sendAfter($btn);
            });
        });
    },
    sendAfter: function ($button) {
        // 发送成功,button倒计时
        var time = 60;
        $button.text(time + 's');
        var timer = setInterval(function () {
            if (time > 0) {
                time--;
                $button.text(time + 's');
            } else {
                $button.text(LNG['user.getCode']);
                $button.prop("disabled", false);
                clearInterval(timer);
            }
        }, 1000);
    },

    // 获取发送方式信息
    getInputData: function (tfaInfo,type) {
        var self = this;
        // 原始数据：input存在时，type肯定存在
        if (tfaInfo.input) {
            return {type: tfaInfo.type, input: tfaInfo.input, default: 1};
        }
        // 输入数据
        var $input = this.dialog.$main.find('input[name="input"]');
        var input = $.trim($input.val());
        if (!input) {$input.focus();return false;}

        var check = '';
        _.each(type, function(tType){
            if (self.checkInput && self.checkInput.check(value, tType)) {
                check = tType;
                return false;
            }
        });
        if (!check) {
            $input.select();
            var name = (_.isArray(type) ? LNG['client.tfa.phoneNumEmail'] : LNG['common.'+type]);
            Tips.tips(LNG['client.tfa.inputValid']+name,'warning');
            return false;
        }
        return {type: check, input: input, default: 0};
    },

    // 提交二次验证
    doVerify: function (data) {
        var self = this;
        data.action = 'tfaVerify';
        var tips = Tips.loadingMask();
        this.request(data, function(result){
            tips.close();
            Tips.close(result);
            if (!result.code) return false;
            if (_.get(self,'parent.loginSuccess')) {
                self.parent.loginSuccess();
            } else {
                var link = _.get(Router,'queryBefore.link') || G.kod.APP_HOST;
                window.location.href = link;
            }
            return false;
        });
    },

    request: function(data, callback){
        if (!this.tfaRequest) {
            this.tfaRequest = new kodApi.request({parent: this});
        }
        this.tfaRequest.requestSend('plugin/client/tfa', data, function(result){
            callback(result);
        });
    },
});