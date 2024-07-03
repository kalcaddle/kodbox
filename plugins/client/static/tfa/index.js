ClassBase.define({
	init: function (param) {
        this.$el = this.parent.$('.login-form');
        this.checkInput = _.get(this,'checkInput.check');
        if (typeof _.get(this,'checkInput.check') != 'function') this.checkInput = false;
    },

    // 后台更新登录状态，前端调用登录成功事件；如果存在二次验证，则先验证
    needTfa: function (tfaIn,tfaInfo) {
        this.tfaIn = tfaIn;
        if (_.get(tfaInfo,'tfaOpen') != 1) {
            var userID  = _.get(tfaInfo,'userID') || '';
            return this.doVerify({userID: userID, wiotTfa: 1}); // 更新登录状态
        }
        this.showDialog(tfaInfo);   // 二次验证
    },

    // 显示二次验证弹窗
    showDialog: function (tfaInfo) {
        // 去除登录页加载样式
        NProgress.done();
        $("body .loading-msg-mask").remove();
        this.$('.submit-button.login').removeClass('disable-event');

        var self = this;
        var userID  = _.get(tfaInfo,'userID') || '';
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
                var data = self.getInputData(tfaInfo, typeArr);
                if (!data) return false;
                var $code = self.dialog.$main.find('input[name="code"]');
                var code = $.trim($code.val());
                if (!code) {$code.focus();return false;}
                data.userID = userID;
                data.code = code;

                self.doVerify(data);
                return false;
            },
            // cancel: false,
            // close:false
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
            var data = self.getInputData(tfaInfo, typeArr);
            if (!data) return false;
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
                self.sendAfter(data.type, $btn);
            });
        });
    },
    sendAfter: function (type, $button) {
        // 发送成功,button倒计时
        var time = type == 'email' ? 60 : 90;
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
    getInputData: function (tfaInfo,typeArr) {
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
        _.each(typeArr, function(tType){
            if (self.checkInput && self.checkInput.check(input, tType)) {
                check = tType;
                return false;
            }
        });
        if (!check) {
            $input.select();
            var name = typeArr.length > 1 ? LNG['client.tfa.phoneNumEmail'] : LNG['common.'+typeArr[0]];
            Tips.tips(LNG['client.tfa.inputValid']+name,'warning');
            return false;
        }
        return {type: check, input: input, default: 0};
    },

    // 提交二次验证/更新登录状态
    doVerify: function (data) {
        var self = this;
        data.action = 'tfaVerify';
        if (!_.get(data, 'wiotTfa')) {
            var tips = Tips.loadingMask();
        }
        this.request(data, function(result){
            tips && tips.close();
            Tips.close(result);
            if (!result.code) return false;
            self.dialog && self.dialog.close();
            if (_.get(self,'parent.loginSuccess')) {
                self.parent.withTfa = true;
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
        data['tfaIn'] = this.tfaIn;
        this.tfaRequest.requestSend('plugin/client/tfa', data, function(result){
            callback(result);
        });
    },
});