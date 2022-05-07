kodReady.push(function(){
    var staticPath = "{{pluginHost}}static/";
	var version    = '?v={{package.version}}';

    LNG.set(jsonDecode(urlDecode("{{LNG}}")));
    var thirdItems = {
        "qq":       "QQ",
        "weixin":   LNG['common.wechat'],
        "github":   "GitHub",
        // "google":   "Google",
        // "facebook": "Facebook",
    };
    // 后台设置
    var loginWith = _.join((_.get(G, 'system.options.loginConfig.loginWith') || []), ',');
    Events.bind("admin.setting.initViewBefore",function(formData, options, self){
        // 无法插入到指定属性（openRegist）之前，只能在定义处加初始值
        formData.loginWith = {
			"type":     "checkbox",
			"value":    loginWith,
			"display":  LNG['admin.setting.thirdLogin'],
			"desc":     LNG['admin.setting.thirdLoginDesc'],
			"info":     thirdItems,
		};
        formData.sep401 = '<hr>';
	});

    // 个人中心设置
    Events.bind('user.account.initViewAfter', function(self){
        if (!loginWith) return;
        requireAsync([
            staticPath+'oauth/user.js' + version,
            staticPath+'oauth/bind.js' + version,
        ],function(User, Bind){
            new User({parent:self, load: {thirdItems: thirdItems, loginWith: loginWith}, Bind: Bind});
        });
    });

    // 登录页
    // Events.bind("router.after.user/login",function(){
    Events.bind("user.login.initViewAfter",function(self){
        if (!loginWith) return;
		requireAsync([
            staticPath+'oauth/login.js' + version,
            staticPath+'oauth/bind.js' + version,
        ],function(Login, Bind){
            new Login({parent:self, load: {thirdItems: thirdItems, loginWith: loginWith, pluginApi: "{{pluginApi}}"}, Bind: Bind});
        });
	});

    // 日志列表解析
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