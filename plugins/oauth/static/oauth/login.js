ClassBase.define({
	init: function (param) { 
        this.loginWith = _.split(param.load.loginWith, ',');
        if (!this.loginWith) return;    // 登录项为空，直接返回
        this.thirdItems = param.load.thirdItems;
        this.Bind = new param.Bind();

        this.initView();
    },

    initView: function(){
        var self = this;
        var opt = '';
        _.each(this.loginWith, function(type){
            var name = self.thirdItems[type];
            var title = _.replace(LNG['oauth.main.loginWith'], '[0]', name);
                opt += '<span class="box-'+type+' third-item" data-type='+type+' title="'+title+'" title-timeout="200"></span>';
        });
        var html = '<div class="text-muted text-center more-login-area">\
                        <span class="more-login-words">'+LNG['user.moreLogin']+'</span>\
                    </div>\
                    <div class="login-third-box">'+opt+'</div>';

        var $form = this.$('.login-form form');
        if ($form.children('.url-link').length) {   // 注册链接
            $form.children('.url-link').before(html);
            $form.children('div').last().remove();
        } else {
            $form.children('div').last().before(html).remove();
        }
        // 点击登录
        this.$el.delegate('.login-third-box .third-item', 'click', function(e){
            if (_.get(G, 'user.info')) { // 已登录的直接跳转至主页面
                return location.href = './';
            }
			var type = $(e.currentTarget).attr("data-type");
            if (type != 'weixin') Tips.loadingMask();
			self.Bind.bind(type);
		});
    }

});