var page = {
    functionHook: function(target,method,beforeFunc,afterFunc){
        var context 	= target || window;
        var _theMethod 	= context[method];
        if(!context || !_theMethod) return console.error('method error!',method);
    
        context[method] = function(){
            var args = arguments;
            if(beforeFunc){
                var newArgs = beforeFunc.apply(this,args);
                if( newArgs === false ) return;
                args = newArgs === undefined ? args : newArgs; 	//没有返回值则使用结果;
            }
            var result = _theMethod.apply(this,args);
            if( afterFunc ){
                var newResult = afterFunc.apply(this,[result,args]);
                result = newResult === undefined ? result : newResult;//没有返回值则使用结果
            }
            return result;
        }
    },
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
    }
}