var page = {
    // 读取二进制流文件内容，转换成html
    getFileInfo: function(callback){
        var tipsLoading = Tips.loadingMask(false,'加载中',0.5);
        var xhr = new XMLHttpRequest();
        xhr.open('GET', FILE_INFO.fileUrl);
        xhr.responseType = "arraybuffer";
        xhr.addEventListener("progress", function (evt) {   //监听进度事件
            if (evt.lengthComputable) {
                var percent = evt.loaded / evt.total;
                var title = percent == 1 ? '正在解析' : Math.round(percent*100)+'%';
                tipsLoading.title(title);
            }
        }, false);
        xhr.onload = function (e) {
            // var data = new Uint8Array(xhr.response);
            var data = xhr.response;
            if(!data){tipsLoading.close();tipsLoading = false;return;};
            var file = {name: FILE_INFO.fileName, ext: FILE_INFO.fileExt, content: data};
            callback(file, tipsLoading);
        };
        xhr.send();

        var self = this;
        xhr.onreadystatechange = function(){    // 请求状态
            if(xhr.readyState==4){
                if (xhr.status < 200 || (xhr.status > 300 && xhr.status != 304)) {
                    tipsLoading.close();tipsLoading = false;
                    self.showTips('请求失败，检查文件是否正常！');
                }
            }
        };
    },
    // 错误提示
    showTips: function(msg){
        $("#msgbox #message").html(msg);
        $("#msgbox").removeClass('hidden');
        $(".page-box").addClass('hidden');
        // $("body").addClass('page-loaded');
    }
}