var page = {
    // 读取二进制流文件内容，转换成html
    getFileInfo: function(callback){
        var xhr = new XMLHttpRequest();
        xhr.open('GET', FILE_INFO.fileUrl);
        xhr.responseType = "arraybuffer";
        xhr.onload = function (e) {
            // var data = new Uint8Array(xhr.response);
            var data = xhr.response;
            var file = {name: FILE_INFO.fileName, ext: FILE_INFO.fileExt, content: data};
            callback(file);
            // var content = xhr.response;
            // var blob = new Blob([content]);
            // var reader = new FileReader();
            // reader.readAsArrayBuffer(blob);
            // reader.onload = function () {
            //     var file = {name: FILE_INFO.fileName, ext: FILE_INFO.fileExt, content: content};
            //     callback(file);
            // };
        };
        xhr.send();
    },
    // 错误提示
    showTips: function(msg){
        $("#msgbox #message").html(msg);
        $("#msgbox").removeClass('hidden');
        $(".page-box").addClass('hidden');
        $("body").addClass('page-loaded');
    }
}