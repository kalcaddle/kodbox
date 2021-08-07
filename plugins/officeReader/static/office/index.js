(function(){
    // 显示错误信息
    var showTips = function(msg){
        document.getElementById("message").innerHTML = msg;
        // if(info) document.getElementById("info").innerHTML = info;
        document.getElementById("msgbox").setAttribute('class', '');
    }
    // 获取url参数
    var getUrlParams = function(){
        var query = window.location.search.substring(1);
        var vars = decodeURIComponent(query).split("&");
        var data = {};
        for (var i=0;i<vars.length;i++) {
            var item = vars[i];
            var key = item.slice(0,item.indexOf('='));
            var value = item.slice(item.indexOf('=')+1);
            data[key] = value;
        }
        return data;
    }
    var param = getUrlParams();
    if(!param.path || !param.callback) return showTips('参数错误！');

    // 给表格增加序列栏
    var setTbHead = function(){
        var table = document.getElementsByTagName('tbody');
        // var tbody = table[0].tBodies;
        var rows = table[0].rows;
        for(var i=0;i<rows.length;i++){
            var td = rows[i].insertCell(0);  // -1是加到最后
            td.innerHTML = (i + 1);
        }
        var td = '';
        for(var i=0;i<rows[0].cells.length;i++) {
            var value = rows[0].cells[i].getAttribute('id') || '';
            if(value) {
                value = value.replace('sjs-', '');
                value = value.substr(0, value.length-1);
            }
            td += '<td>'+value+'</td>';
        }
        var tr = table[0].insertRow(0);
        tr.innerHTML = td;
    }

    // 读取二进制流文件内容，转换成html
    var readRequestFile = function(data){
        var xhr = new XMLHttpRequest();
        xhr.open('GET', data.callback + '&path=' + data.path);
        xhr.responseType = "arraybuffer";
        xhr.onload = function (e) {
            var content = xhr.response;
            var blob = new Blob([content]);
            var reader = new FileReader();
            reader.readAsArrayBuffer(blob);
            reader.onload = function () {
                try{
                    // mammothjs
                    if(data.app == 'mammothjs') {
                        mammoth.convertToHtml({arrayBuffer: content})
                            .then(function(result){
                                document.getElementById("output").innerHTML = result.value;
                                document.getElementById("pagebox").setAttribute('class', '');
                            }).done();
                        return false;
                    }
                    // sheetjs
                    var wb = XLSX.read(content, {type: 'buffer'});
                    //wb.SheetNames[0]是获取Sheets中第一个Sheet的名字
                    //wb.Sheets[Sheet名]获取第一个Sheet的数据
                    // var json = XLSX.utils.sheet_to_json(wb.Sheets[wb.SheetNames[0]]);
                    var html = XLSX.utils.sheet_to_html(wb.Sheets[wb.SheetNames[0]]);
                    // document.getElementById("output").innerHTML = JSON.stringify(json, null, "\t");
                    document.getElementById("output").innerHTML = html;
                    setTbHead();
                }catch(err){
                    showTips('解析失败，请检查文件是否正常！');
                }
            };
        };
        xhr.send();
    }
    readRequestFile(param);
})();