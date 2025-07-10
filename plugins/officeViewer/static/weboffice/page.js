var page = {
    // 读取二进制流文件内容，转换成html
    getFileInfo: function(callback){
        var tipsLoading = Tips.loadingMask(false,kodSdkConfig.LNG['explorer.wordLoading'],0.5);
        var xhr = new XMLHttpRequest();
        xhr.timeout = 1000*30;  // 超时时间
        xhr.open('GET', FILE_INFO.link);
        xhr.responseType = "arraybuffer";
        xhr.addEventListener("progress", function (evt) {   //监听进度事件
            if (evt.lengthComputable) {
                var percent = evt.loaded / evt.total;
                var title = percent == 1 ? kodSdkConfig.LNG['officeViewer.webOffice.parsing'] : Math.round(percent*100)+'%';
                tipsLoading.title(title);
            }
        }, false);
        xhr.onload = function (e) {
            // var data = new Uint8Array(xhr.response);
            var data = xhr.response;
            if(!data){tipsLoading.close();tipsLoading = false;return;};
            var file = {name: FILE_INFO.name, ext: FILE_INFO.ext, content: data};
            callback(file, tipsLoading);
        };
        xhr.send();

        var self = this;
        xhr.onreadystatechange = function(){    // 请求状态
            if(xhr.readyState==4){
                if (xhr.status < 200 || (xhr.status > 300 && xhr.status != 304)) {
                    tipsLoading.close();tipsLoading = false;
                    self.showTips(kodSdkConfig.LNG['officeViewer.webOffice.reqErrPath']);
                }
            }
        };
        xhr.ontimeout = function() {
            self.showTips(kodSdkConfig.LNG['officeViewer.webOffice.reqErrNet']);
        }
    },
    // 错误提示
    showTips: function(msg){
        $("#msgbox #message").html(msg);
        $("#msgbox").removeClass('hidden');
        $(".page-box").addClass('hidden');
        // $("body").addClass('page-loaded');
    },
    // 设置用wb预览时的提示信息
    wbAlert: function(){
        var $dom = $('body.weboffice-page.loaded');
        if (!arguments[0]) $dom = $dom.find('#output');
        if (!$dom.length) return;
        $dom.attr("data-content", kodSdkConfig.LNG['officeViewer.webOffice.warning']);
        // // 10s关闭提示
        // _.delay(function(){
        //     // $dom.attr("data-content", '');
        //     // $dom.removeClass('active');
        //     $dom.addClass('tip-hide');
        // }, 10000);
    }
}