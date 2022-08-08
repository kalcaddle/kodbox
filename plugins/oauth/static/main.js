kodReady.push(function(){
    var staticPath = "{{pluginHost}}static/";
	var version    = '?v={{package.version}}';

    LNG.set(jsonDecode(urlDecode("{{LNG}}")));
    var thirdItems = {
        "qq":       "QQ",
        "weixin":   LNG['common.wechat'],
        "github":   "GitHub",
        "google":   "Google",
        "facebook": "Facebook"
    };
    // 获取第三方登录项
    var getLoginWith = function(){
        if (!_.isUndefined(G.system.options.loginWith)) {
            return G.system.options.loginWith;
        }
        return _.join((_.get(G, 'system.options.loginConfig.loginWith') || []), ',');
    }

    // 1.后台设置
    Events.bind("admin.setting.initViewBefore",function(formData, options, self){
        var loginWith = getLoginWith();
        // 无法插入到指定属性（openRegist）之前，只能在定义处加初始值
        formData.loginWith = {
			"type":     "checkbox",
			"value":    loginWith,
			"display":  LNG['admin.setting.thirdLogin'],
			"desc":     LNG['admin.setting.thirdLoginDesc'],
			"info":     thirdItems
		};
        formData.sep401 = '<hr>';
	});

    // 2.插件设置
    Events.bind("plugin.config.formBefore", function(formData, options, self){
        if (_.get(options, 'id') != 'app-config-{{pluginName}}') return;
        var list = [];
        var loginWith = getLoginWith();
        var logins = loginWith ? _.split(loginWith, ',') : [];
        _.each(thirdItems, function(title, type){
            var opt = "";
            var web = "";
            if (_.includes(['google', 'facebook'], type)) {
                if (type == 'google') {
                    opt = "<hr><span class='fq-desc mb-10'>"+LNG['oauth.config.fqDesc']+"</span>";
                }
                web = "<span class='web-url'>（https://"+type+".com）</span>";
            }
            var icon = type == 'weixin' ? 'wechat' : type;
            var checked = _.includes(logins, type) ? 'checked="checked"' : '';
            opt += "<p class='mb-5'><i class='font-icon ri-"+icon+"-fill with-color'></i><span>"+title+web+"</span>\
                <input type='checkbox' name='type' value='"+type+"' class='kui-checkbox' "+checked+">\
                </p>";
            list.push(opt);
        });
        formData.list.value = "<div class='type-list'>"+(_.join(list,''))+"</div>";
        formData.loginWith.value = loginWith;
    });
    Events.bind("plugin.config.formAfter", function(self){
        var formMaker = self['form{{pluginName}}'];
        if (!formMaker) return;
        // 可能保存失败，暂不做处理
        formMaker.bind('onSave', function(result){
            var loginWith = [];
            formMaker.$el.find("input[name=type]").each(function(){
                if ($(this).is(":checked")) {
                    loginWith.push($(this).val());
                }
            });
            result.loginWith = _.join(loginWith, ',');
            G.system.options.loginConfig.loginWith = loginWith;
            G.system.options.loginWith = result.loginWith;
        });
    });

    // 3.个人中心设置
    Events.bind('user.account.initViewAfter', function(self){
        var loginWith = getLoginWith();
        if (!loginWith) return;
        requireAsync([
            staticPath+'oauth/user.js' + version,
            staticPath+'oauth/bind.js' + version,
        ],function(User, Bind){
            new User({parent:self, load: {thirdItems: thirdItems, loginWith: loginWith}, Bind: Bind});
        });
    });

    // 4.登录页
    // Events.bind("router.after.user/login",function(){
    Events.bind("user.login.initViewAfter",function(self){
        var loginWith = getLoginWith();
        if (!loginWith) return;
		requireAsync([
            staticPath+'oauth/login.js' + version,
            staticPath+'oauth/bind.js' + version,
        ],function(Login, Bind){
            new Login({parent:self, load: {thirdItems: thirdItems, loginWith: loginWith, pluginApi: "{{pluginApi}}"}, Bind: Bind});
        });
	});

    if($.hasKey('plugin.{{package.id}}.event')) return;

    // 5.日志列表解析
    ClassBase.extendHook({
		hookMatch:'dataParseHtmlItem,dataParseOthers,dataParseUsers',
		dataParseUsers:function(){
			var type = arguments[0];
			var desc = arguments[1];
            if (_.startsWith(type, 'user.bind')) {
                var title = thirdItems[desc.type] || '';
                return [LNG['common.type'] + ':' + title];
            }
			return this.__dataParseUsers.apply(this,arguments);
		},
        normalActionGet: function(){
            var item = arguments[0];
            if (_.startsWith(item.type, 'user.bind')) {
                var actions = {
                    'user.bind.bind':   ['user', 'user'],
			        'user.bind.unbind': ['user', 'user-unbind']
                };
                return actions[item.type] || ['', ''];
            }
            return this.__normalActionGet.apply(this,arguments);
        }
	});

    if($.hasKey('plugin.{{package.id}}.style')) return;
	requireAsync("{{pluginHost}}static/main.css");
});