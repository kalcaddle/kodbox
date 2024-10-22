ClassBase.define({
	init: function (param) {}, 

    bindEvent (param) {
        var _this = param.parent;
        var type = param.type;
        if (type == 'menu' && !_this.$('.menuBar .menu-dropdown-user .client-download').length) {
            var html = '<li class="client-download ripple-item" target="_blank">\
                    <i class="font-icon ri-download-fill-2"></i>\
                    '+LNG['client.down.client']+'\
                </li>';
            _this.$('.menuBar .menu-dropdown-user li.copyright-show').after(html);
        }
        if (type == 'login' && !_this.$('.login-form form .client-download').length) {
            var html = '<span class="client-download">\
                    <a class="url-link" href="javascript:void(0);">'+LNG['client.down.client']+'</a>\
                </span>';
            _this.$('.login-form form').append(html);
        }
        var self = this;
        _this.$el.delegate('.client-download','click',function(e){
            self.showDownDg();
        });
        _this.on('onRemove', function(){
            var dgs = $.dialog.list;
            _.each(dgs,function(dialog){
                if (dialog && (dialog.$main.hasClass('dialog-client-download') || dialog.$main.hasClass('client-down-qrcode-dg'))) {
                    dialog.close();
                }
            });
        });
        Events.trigger('client.down.link.loaded',_this,type);   // 菜单链接
    },

    showDownDg: function(){
        var html = '<div class="dialog-copyright-content">\
                <div class="title">\
                    <div class="logo logo-image hidden">'+LNG.logo('copyright')+'</div>\
                    <div class="logo-text client-down-text">'+LNG['client.down.client']+'</div>\
                    <div class="info hidden">'+(LNG['common.copyright.nameDesc'] || '')+'</div>\
                </div>\
                <div class="content size16">\
                    <div>'+LNG['client.down.client']+'</div>\
                    <div class="line"></div>\
                    <div>\
                        <span class="btn" app="win"><i class="ri-windows-fill"></i>Windows</span>\
                        <span class="btn" app="mac"><i class="ri-apple-fill"></i>Mac</span>\
                    </div>\
                    <div class="mt-15">'+LNG['client.down.app']+'</div>\
                    <div class="line"></div>\
                    <div>\
                        <span class="btn qrcode" app="android"><i class="ri-android-fill"></i>Android</span>\
                        <span class="btn qrcode" app="ios"><i class="ri-apple-fill"></i>IOS</span>\
                    </div>\
                </div>\
            </div>';
		var dialog = $.dialog({
            id:"dialog-client-download",
			bottom:0,right:0,
			simple:true,
			resize:false,
			disableTab:true,
			className:"dialog-blur",
			title:LNG['client.down.client'],
			width:425,
			padding:0,
			fixed:true,
			content:html
		});
        $('.dialog-client-download.artDialog').addClass('dialog-copyright');
        this.clientLink(dialog.$main);
	},

    clientLink: function ($dialog) {
        var setLink = function (result) {
            Events.trigger('client.down.dialog.loaded',$dialog,result);
            if (!result.code || !result.data) {
                var html = '<div class="info-alert info-alert-yellow mt-50 size14">'+LNG['client.down.apiErr']+'</div>';
                $dialog.find('.content').html(html);
                return;
            }
            var data = result.data;
            $dialog.delegate('.content .btn', 'click', function () {
                var app = $(this).attr('app');
                if (!app || !data[app]) return;
                var link = data[app].link;
                if (!link) return Tips.tips(LNG['client.down.linkErr'], 'warning', 3000);
                if (!$(this).hasClass('qrcode')) {
                    return window.open(link);
                } 
                var dg = core.qrcode(link);
                if (dg && dg.$main) {
                    dg.$main.addClass('client-down-qrcode-dg');
                    dg.$main.find('.aui-content>div').prepend('<p class="mb-5">'+LNG['client.down.webScan']+'</p>');
                }
            });
        }
        var key  = 'kodbox.client.link';
        var result = LocalData.get(key);
            result = jsonDecode(result);
        if (result && result.time && result.time > time()) {
            return setLink(result);
        }
        $.ajax({
            url: 'https://api.kodcloud.com/?app/version',
            dataType:'jsonp',
            success:function(result){
                var tmpTime = 3600*2;
                if(!result || !result.data) tmpTime = 60*5;
                result.time = time()+tmpTime;  // 过期时间：正常2小时，失败5分钟
                LocalData.set(key, jsonEncode(result));
                setLink(result);
            }
        });
    }

});