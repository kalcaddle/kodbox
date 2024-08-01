(function(){
    // 比较版本号：7.1.19 >= 5.3
    var compareVers = function (ver1, ver2) {
        ver1 = ver1.toString();
        ver2 = ver2.toString();
        const pVer1 = ver1.split('.').map(Number);
        const pVer2 = ver2.split('.').map(Number);
        const mxLen = Math.max(pVer1.length, pVer2.length);
        for (var i = 0; i < mxLen; i++) {
            const v1 = pVer1[i] || 0;
            const v2 = pVer2[i] || 0;
            if (v1 > v2) return 1;
            if (v1 < v2) return -1;
        }
        return 0;
    }
    // 环境检测项赋值
    var envView = function(){
        var errList = [];
        var chkList = ['path_writable', 'php_version', 'allow_url_fopen'];  // 检查项
        var $table = $(".step-box.env .env-table");
        request('install/index/env', {}, function(result){
            tips && tips.close();
            if (!result || !result.code) {
                Tips.tips(LNG['admin.install.envReqErr'], 'warning', 3000);
                $table.find('.row-state .icon').removeClass().addClass('icon icon-warn ri-more-fill');
                return false;
            }
            var data = result.data;
            $table.find('.env-item-list>p').each(function(){
                var key = $(this).attr('class');
                var val = data[key];
                var txt = '';
                switch (key) {
                    case 'path_writable':
                        if (val !== true) {
                            txt = '<span title-timeout="100" title="sudo chmod -Rf 777 '+val+'">'+val+'</span>';
                            val = false;
                        }
                        break;
                    case 'php_version':
                        txt = val;
                        val = compareVers(val, 5.3) > 0 ? true : false;
                        break;
                    case 'php_bit':
                        txt = val;
                        val = val == 64 ? true : false;
                        break;
                    default:break;
                }
                if (txt) $(this).find('.row-value').html(txt);
                var state = val === true ? 'success' : 'error';
                $(this).find('.row-state .icon').removeClass().addClass('icon icon-'+state);
                if (val !== true) errList.push(key);
            });
            var button = errList.length ? LNG['common.skip'] : LNG['common.nextStep'];
            $table.find(".form-target-save").removeClass('hidden');
            $table.find(".form-save-button").text(button);
            // 使用帮助
            if(errList.length) $table.find("a.help").removeClass('hidden');
        });
        // 绑定点击事件
        $table.delegate('.form-save-button', 'click', function(){
            var errs = _.intersection(errList, chkList);
            if(!errs.length) return stepNext(this, 1);
            var errMsg = [LNG['admin.install.ensureNoError']];
            _.each(errs, function(value, i){
                errMsg.push((i+1)+'.'+$table.find("."+value+">span:eq(0)").text());
            });
            Tips.tips(errMsg.join('<br/>'), false, 3000);
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
        $(".step-box.db .form-save-button").text(LNG['common.ok']);
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
                        data.del = 1;
                        dbSaveSet(_this, data);
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
     * 数据库配置（删除旧数据）提交
     * @param {*} _this 
     * @param {*} data 
     */
    var dbSaveSet = function(_this, data){
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
     * 管理员账号提交
     * @param {*} formMaker 
     * @returns 
     */
    var userSave = function (formMaker) {
        var data = formMaker.getValue();
        Events.trigger('install.userSetStart',data);
        if(!data || !data.name || !data.password || !data.password2) return false;
        if (data.password != data.password2) {
            formMaker.setValue('password2', '');
            Tips.tips(LNG['user.rootPwdEqual'], 'warning');
            return false;
        }
        delete data.password2;

        // var _this = this;
        var _this = '.step-box.user .form-save-button'; // 为了和旧版统一，实际没有必要
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
    }

    /**
     * 数据库、缓存配置
     */
    var dbView = function(){
        var package = './app/controller/install/static/package.html'
        requireAsync(package, function(FormData){
            // 获取json数据
            FormData = FormData.replace(/\n/g,"").replace(/\r/g,""); //去掉字符串中的换行符
            FormData = FormData.replace(/\n/g,"").replace(/\s|\xA0/g,""); //去掉字符串中的所有空格
            FormData = eval('(' + FormData + ')'); //将字符串解析成json对象
            FormData.redisMore.info.openMore.display = LNG['common.more']+' <b class="caret"></b>';
            FormData.redisMore.info.openMore.className = 'btn btn-default btn-sm';
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
     * 管理员账号配置
     */
    var name = '';
    var password = '';
    var userView = function(fast){
        var auto = $('.install-box .install-fast').attr('auto');
        var formData = {
            "name":{
                "type":"input",
                "value":auto || 'admin',
                // "display":"<i class='font-icon ri-user-line-3'></i>",
                "display":LNG['user.account'],
                "attr":{"placeholder":LNG['user.inputName']},
                "desc":LNG['user.rootName'],
                "require":"1"
            },
            "password":{
                "type":"password",
                "value":'',
                // "display":"<i class='font-icon ri-key-line'></i>",
                "display":LNG['common.password'],
                "attr":{"placeholder":LNG['user.inputPwd']},
                "desc":LNG['user.rootPwd'],
                "require":"1"
            },
            "password2":{
                "type":"password",
                "value":'',
                // "display":"<i class='font-icon ri-key-line'></i>",
                "display":LNG['common.password'],
                "attr":{"placeholder":LNG['user.inputPwd']},
                "desc":LNG['user.rootPwdRepeat'],
                "require":"1"
            },
        };
        var formMaker = new kodApi.formMaker({formData:formData });
        formMaker.renderTarget($(".step-box.user .user-table"));
		Events.trigger('install.userSetReady',formMaker);
        tips && tips.close();
        $(".step-box.user .form-save-button").text(LNG['common.ok']);
        $('.step-box.user .form-box input').keyEnter(function(){userSave(formMaker);});
        $(".step-box.user .form-save-button").click(function(){userSave(formMaker);});
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
        if(update) $(".step-box.msg .title").text(LNG['admin.install.updateSuccess']);
        var text = LNG['user.account']+": "+name+"&nbsp;&nbsp;&nbsp;&nbsp;"+LNG['common.password']+": "+password;
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
    var tips = window.Tips ? window.Tips.loadingMask() : null;
    Events.bind('windowReady',function(){
        try {
            // 检测是否为一键安装，一键安装直接展示账号界面
            var fast = parseInt($('.install-box .install-fast').attr('fast'));
            if(!fast) {
                envView();   // 1.环境配置
                dbView();    // 2.数据库配置
            }
            userView(fast);  // 3.管理员账号配置
            new kodApi.copyright();
            $(".content-main-message .body").perfectScroll();
        } catch(e) {
            tips && tips.close();
            console.error(e);
            var msg = LNG['admin.install.pageError']+'<hr>'+e;
            Tips.notify({icon:"error",title:LNG['common.tips'],content:msg});
            _.delay(function(){
                if ($('.progress-box>.active:last').attr('data') == 'env') {
                    var $env = $(".step-box.env");
                    if (!$env.hasClass('hidden') && $env.find('.form-target-save').hasClass('hidden')) {
                        $env.find('.env-table .row-state .icon').removeClass().addClass('icon icon-warn ri-more-fill');
                    }
                }
            }, 1000);
        }
    });
})();