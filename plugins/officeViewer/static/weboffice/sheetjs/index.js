(function(){
    // v0.17.0
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
    // 解析文件
    page.getFileInfo(function(file){
        // var ppt = PPT.readFile(file.content, {type: 'buffer'});
        // var data = PPT.utils.to_text(ppt);
        try{
            var wb = XLSX.read(file.content, {type: 'buffer'});
            //wb.SheetNames[0]是获取Sheets中第一个Sheet的名字
            //wb.Sheets[Sheet名]获取第一个Sheet的数据
            // var json = XLSX.utils.sheet_to_json(wb.Sheets[wb.SheetNames[0]]);
            var html = XLSX.utils.sheet_to_html(wb.Sheets[wb.SheetNames[0]]);
            // document.getElementById("output").innerHTML = JSON.stringify(json, null, "\t");
            document.getElementById("output").innerHTML = html;
            setTbHead();
            // $('body').addClass('page-loaded');
        }catch(err){
            page.showTips('文件损坏，或包含不支持的内容格式！');
        }
    });
})();