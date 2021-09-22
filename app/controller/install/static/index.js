(function(){
    var lng = {};
    var initLng = function(){
        lng = {
            'dir_list':     LNG['admin.install.serverDir'], 
            'dir_right':    LNG['admin.install.dirRight'],
            'path_write':   LNG['admin.install.pathNeedWirte'],
            'php_version':  LNG['admin.install.phpVersionTips'],
            'php_bit':      LNG['admin.install.phpBitTips'],
            'php_bitdesc':  LNG['admin.install.phpBitDesc'],
            'sugst_open':   LNG['admin.install.suggestOpen'], 
            'sugst_close':  LNG['admin.install.suggestClose'],
            'must_open':    LNG['admin.install.mustOpen'],
            'next_step':    LNG['common.nextStep'],
            'ensure_ok':    LNG['admin.install.ensureNoError'],
            'update_ok':    LNG['admin.install.updateSuccess'],
            'input_name':   LNG['admin.install.setAdminName'],
            'input_pwd':    LNG['admin.install.setAdminPwd'],
            'text_version': LNG['common.version'],
            'text_phpbit':  LNG['common.sysVersion'],
            'text_extend':  LNG['common.extend'],
            'text_method':  LNG['common.method'],
            'text_skip':    LNG['common.skip'],
            'text_ok':      LNG['common.ok'],
            'text_account': LNG['user.account'],
            'text_pwd':     LNG['common.password'],
        };
    }
    var envOptSet = function(value, key){
        var opt = {};
        switch (key) {
            case 'path_writable':
                if(value !== true) {
                    opt.check = 'fail';
                    opt.icons = 'error';
                    opt.value = '<span title-timeout="100" title="sudo chmod -Rf 777 '+value+'">'+value+'</span>';
                }
                break;
            case 'php_version':
                if(value < 5.3) {
                    opt.check = 'fail';
                    opt.icons = 'error';
                }
                opt.value = value;
                break;
            case 'php_bit':
                if(value == 32) {
                    opt.check = 'fail';
                    opt.icons = 'error';
                }
                opt.value = value;
                break;
            default:break;
        }
        if(_.isBoolean(value) && !value){
            opt.check = 'fail';
            opt.icons = 'error';
            opt.value = 'close';
        }
        return opt;
    }
    /**
     * 环境检测项html
     * @param {*} data 
     */
    var envOptHtml = function(data){
        var tableList = {
            title: {
                'path_list': lng.dir_list, 
                'path_writable': lng.dir_right, 
                'php_version': 'PHP '+lng.text_version,
                'php_bit': 'PHP '+lng.text_phpbit,
                'shell_exec': 'shell_exec、exec '+lng.text_method
            },
            value: {
                'php_version': lng.php_version, 
                'php_bit': lng.php_bit+'<br/><span class="bit-desc">('+lng.php_bitdesc+')</span>'
            },
            texts: {
                'path_writable': lng.path_write, 
                'allow_url_fopen': lng.must_open, 
                'path_list': lng.sugst_close
            }
        };
        var html = '';
        _.each(data, function(value, key){
            var opt = {
                'check': 'ok',
                'value': 'check',
                'icons': 'success',
                'title': _.get(tableList.title, key) || _.toUpper(key)+' '+lng.text_extend,
                'texts': _.get(tableList.value, key) || (_.get(tableList.texts, key) || lng.sugst_open)
            };
            opt = $.extend({}, opt, envOptSet(value, key));
            if(_.includes(['check','close'], opt.value)){
                opt.value = '<i class="icon-'+opt.value+'"></i>';
            }
            html += '<p class="' + key + '">\
                <span class="w30 row-title">' + opt.title + '</span>\
                <span class="w20 row-value">' + opt.value + '</span>\
                <span class="w40 row-desc">' + opt.texts + '</span>\
                <span class="w10 ' + opt.check + '"><i class="icon icon-' + opt.icons + '"></i></span>\
                <span class="clear"></span>\
            </p>';
        });
        html = '<div>' + html + '</div>';
        var $table = $(".step-box.env .env-table");
        $table.html(html + $table.html());
        $table.find(".form-target-save").removeClass('hidden');
    }
    /**
     * 环境检测
     */
    var envCheck = function(){
        var mustOpenList = {
            'path_writable': lng.dir_right, 
            'php_version': lng.php_version, 
            'allow_url_fopen': lng.must_open, 
        };
        // 载入页面内容
        var errList = [];
        var $table = $(".step-box.env .env-table");
        request('install/index/env', {}, function(data){
            envOptHtml(data.data);
            $table.find(".fail").each(function(){
                errList.push($(this).parent('p').attr('class'));
            });
            var button = errList.length ? lng.text_skip : lng.next_step;
            $table.find(".form-save-button").text(button);
            // 使用帮助
            if(errList.length) $table.find("a.help").removeClass('hidden');
        });
        // 绑定点击事件
        $table.delegate('.form-save-button', 'click', function(){
            var errs = _.intersection(errList, _.keys(mustOpenList));
            if(!errs.length) return stepNext(this, 1);
            var errMsg = [lng.ensure_ok];
            _.each(errs, function(value, i){
                errMsg.push((i+1)+'.'+$table.find("."+value+">span:eq(0)").text());
            });
            Tips.tips(errMsg.join('<br/>'), false, 5000);
        });
    }

    // 数据库配置提交
    var dbSave = function(FormData, update){
        var update = update || false;
        var formMaker = new kodApi.formMaker({formData:FormData });
        formMaker.renderTarget($(".step-box.db .db-table"));
        if(update) {
            $(".step-box.db .db-table").find('button,input').prop('disabled', true);
            $(".step-box.db .info-alert").removeClass('hidden');
        }
        $(".step-box.db .form-save-button").text(lng.text_ok);
        $(".step-box.db .form-save-button").click(function(){
            var data = formMaker.getValue();
            if(!data) return false;
            var _this = this;
            data = $.extend({}, {action: 'db'}, data);
            var tips = Tips.loadingMask($('.content-main'),false,0.2);
            request('install/index/save', data, function(result){
                tips.close();
                // 是否删除已存在数据库
                if(result.info && result.info == '10001'){
                    $.dialog.confirm(result.data,function(){
                        data.del_table = 1;
                        dbSetSave(_this, data);
                    },function(){
                        Tips.close();
                    });
                    return false;
                }
                var delay = null;
                if(!result.code || (result.info && result.info == '10000')) delay = 5000;
                var msg = result.data || (LNG['explorer.error']+', '+LNG['admin.install.setPathWrt']);
                Tips.close(msg, result.code, delay);
                if(!result.code) return;
                stepNext(_this, 2);
            }, function(){
                tips.close();
            });
        });
    }
    /**
     * 数据库、缓存配置
     */
    var dbSet = function(){
        var package = './app/controller/install/static/package.html'
        requireAsync(package, function(FormData){
            // 获取json数据
            FormData = FormData.replace(/\n/g,"").replace(/\r/g,""); //去掉字符串中的换行符
            FormData = FormData.replace(/\n/g,"").replace(/\s|\xA0/g,""); //去掉字符串中的所有空格
            FormData = eval('(' + FormData + ')'); //将字符串解析成json对象
            request('install/index/env', {db: 1}, function(result){
                if(_.isEmpty(result.data)) return dbSave(FormData);
                _.each(FormData, function(value, key){
                    if(result.data[key]) value.value = result.data[key];
                });
                dbSave(FormData, true);
            });
        });
    }
    /**
     * 数据库配置（删除旧数据）提交
     * @param {*} _this 
     * @param {*} data 
     */
    var dbSetSave = function(_this, data){
        var tips = Tips.loadingMask($('.content-main'),false,0.2);
        request('install/index/save', data, function(result){
            tips.close();
            var delay = !result.code ? 5000 : null;
            var msg = result.data || (LNG['explorer.error']+', '+LNG['admin.install.setPathWrt']);
            Tips.close(msg, result.code, delay);
            if(!result.code) return;
            stepNext(_this, 2);
        }, function(){
            tips.close();
        });
    }

    /**
     * 管理员账号配置
     */
    var name = '';
    var password = '';
    var userSet = function(fast){
        var auto = $(".step-box.user input[name='auto-install']").val().split('|');
        var FormData = {
            "name":{
                "type":"input",
                "value":auto[0] || 'admin',
                "display":lng.text_account,
                "attr":{"placeholder":lng.input_name},
                "require":"1"
            },
            "password":{
                "type":"password",
                "value":auto[1] || '',
                "display":lng.text_pwd,
                "attr":{"placeholder":lng.input_pwd},
                "require":"1"
            },
        };
        userFormMaker = new kodApi.formMaker({formData:FormData });
        userFormMaker.renderTarget($(".step-box.user .user-table"));
        $(".step-box.user .form-save-button").text(lng.text_ok);
        $(".step-box.user .form-save-button").click(function(){
            var data = userFormMaker.getValue();
            if(!data) return false;
            var _this = this;
            data = $.extend({}, {action: 'user'}, data);
            var tips = Tips.loadingMask($('.content-main'),false,0.2);
            request('install/index/save', data, function(result){
                tips.close();
                var delay = !result.code ? 5000 : null;
                var msg = result.data || (LNG['explorer.error']+', '+LNG['admin.install.setPathWrt']);
                Tips.close(msg, result.code, delay);
                if(!result.code) return;
                // 显示admin账号密码
                name = data.name;
                password = data.password;
                LocalData.del('fileHistoryLastPath-1');
                var update = result.info || 0;
                stepLast(_this, update); // 安装成功，提示登录
            }, function(){
                tips.close();
            });
        });
        if(fast == 2) $(".step-box.user .form-save-button").click();
    }

    // 下一步
    var stepNext = function(_this, index){
        $(_this).parents('.check-result').find('.progress-box>div:eq('+index+')').addClass('active');
        $(_this).parents('.check-result').find('.step-box').addClass('hidden');
        $(_this).parents('.step-box').next().removeClass('hidden');
    }
    // 最后一步
    var stepLast = function(_this, update){
        $(_this).parents('.check-result').find('.title-box,.progress-box').addClass('hidden');
        $(_this).parents('.content-main').children('.link').removeClass('hidden');
        $(_this).parents('.check-result').find('.step-box').addClass('hidden');
        $(_this).parents('.step-box').next().removeClass('hidden');
        // 跳转登录
        if(update) $(".step-box.msg .title").text(lng.update_ok);
        var text = lng.text_account+": "+name+"&nbsp;&nbsp;&nbsp;&nbsp;"+lng.text_pwd+": "+password;
        $(".step-box.msg .desc").html(text);
        var count = 5;
        var timer = null;
        timer = setInterval(function () {
            if (count > 0) {
                count = count - 1;
                $('.content-main .link .delay').text(count);
            } else {
                clearInterval(timer);
                window.location.href = $('.content-main .link a').attr('href');
            }
        }, 1000);
    }

    var request = function(url, data, callback, callbackError){
		// 兼容处理: https://qastack.cn/programming/26261001/warning-about-http-raw-post-data-being-deprecated 
		data = data || {};
		data._installTime = time();
        $.ajax({
            url:API_HOST + url,
            data:data,
            type: 'POST',
            dataType:'json',
            error: function (xhr, textStatus, errorThrown) {
                if(callbackError) callbackError();
                var error = xhr.responseText;
                var dialog = $.dialog.list['ajaxErrorDialog'];
                if(error && !_.trim(error)) return;// 有内容,但内容为空白则不处理;
                Tips.close(LNG['explorer.systemError'], false);
                if (xhr.status == 0 && error == '') {
                    error = LNG['explorer.networkError'];
                }
                error = '<div class="ajaxError" style="font-size:14px;padding:40px;color:#FF9800;">' + error + '</div>';
                if (!dialog) {
                    $.dialog({
                        id: 'ajaxErrorDialog',
                        padding: 0,
                        width: '60%',
                        height: '65%',
                        fixed: true,
                        resize: true,
                        title: 'Ajax Error',
                        content: ''
                    });
                }
                $.iframeHtml($(".ajaxErrorDialog .aui-content"), error);
            },
            success: function(data) {
                callback(data);
            }
        });
    }
    Events.bind('windowReady',function(){
        initLng();
        // 检测是否为一键安装，一键安装直接展示账号界面
        var fast = parseInt($('.install-box .install-fast').text());
        if(!fast) {
            envCheck(); // 1.环境检测
            dbSet();    // 2.数据库配置
        }
        userSet(fast);  // 3.管理员账号配置
        new kodApi.copyright();
        $(".content-main-message .body").perfectScroll();
    });
})();