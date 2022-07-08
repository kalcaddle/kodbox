ClassBase.define({
	init: function (param) { 
        this.loginWith = _.split(param.load.loginWith, ',');
        if (!this.loginWith) return;    // 登录项为空，直接返回
        this.thirdItems = param.load.thirdItems;
        this.Bind = new param.Bind();

        var self = this;
        this.request = new kodApi.request({parent: this});
        this.request.requestSend('plugin/oauth/bind', {method: 'bindInfo'}, function(result){
            self.initView(result.data);
        });
    },
    // 第三方账号操作项
    initView: function(data){
        var opt = '';
        var self = this;
        _.each(this.loginWith, function(type){
            var name    = self.thirdItems[type];
            var content = '<a class="bind" href="javascript:void(0)">'+LNG['user.clickBind']+'</a>';
            var right   = '';
            var isBind  = _.get(data.bind, type+'Bind') || 0;
            if (isBind) {
                content = LNG['user.binded'];
                right   = '<span class="col-action"><a class="unbind" href="javascript:void(0)">'+LNG['user.unbind']+'</a></span>';
            }
            opt += '<div id="'+type+'" class="acc-row">\
                        <span class="col-title">\
                            <i class="font-icon ri-'+(type == 'weixin' ? 'wechat' : type)+'-fill with-color"></i>\
                            <span>'+name+'</span>\
                        </span>\
                        <span class="col-content">'+content+'</span>'+right+'\
                    </div>';
        });
        var html = '<div class="third-set mt-40">\
                        <div class="acc-title">'+LNG['user.thirdAccount']+'</div>\
                        <div class="acc-line"></div>\
                        <div>'+opt+'</div>\
                    </div>';
        this.$('.account-page .user-set').after(html);

        // 用户密码为空时，去掉原始密码项
        this.emptyPwd = data.emptyPwd;
        var $pwd = this.$('.account-page .user-set .form-row.item-change-password');
        if (this.emptyPwd == '1') {
            $pwd.find('.acc-dtl .title').remove();
            var $title = $pwd.find('input[name=newpwd]').parent().prev();
            $title.parent().removeClass('mt-5').addClass('title');  // 添加title样式
            $title.html('<i class="font-icon ri-lock-unlock-fill size16"></i><span style="margin-left:4px;">'+LNG['common.password']+'</span>');
        }
        this.bindEvent();
    },
    // 绑定、解绑
    bindEvent: function(){
        var self = this;
        this.$el.delegate(".third-set .bind, .third-set .unbind", 'click', function(e){
            var target = $(e.currentTarget);
            var type = target.parents(".acc-row").attr("id");
            if (target.attr("class") == 'bind') {
                if (type != 'weixin') Tips.loadingMask(false, LNG['oauth.main.loading']);
                return self.Bind.bind(type, 'bind', 0);
            }
            if(self.emptyPwd == '1') {
                return Tips.tips(LNG['user.unbindWarning'],'warning',3000);
            }
            self.Bind.unbind(type, self);
        });
    }

});