$(function(){
    // 根据数据绘制表格
    var loadLuckySheet = function(exportJson){
        try{
            // 获得转化后的表格数据后，使用luckysheet初始化，或者更新已有的luckysheet工作簿
            // 注：luckysheet需要引入依赖包和初始化表格容器才可以使用
            luckysheet.create({
                container: 'output', // 容器id
                data:exportJson.sheets,
                lang: 'zh',
                // title:exportJson.info.name,
                // userInfo:exportJson.info.name.creator,
                // showinfobar: false,
                // allowCopy: false, // 是否允许拷贝
                showtoolbar: false, // 是否显示工具栏——edit
                showinfobar: false, // 是否显示顶部信息栏
                // showsheetbar: false, // 是否显示底部sheet页按钮
                // showstatisticBar: false, // 是否显示底部计数栏
                sheetBottomConfig: false, // sheet页下方的添加行按钮和回到顶部按钮配置
                allowEdit: false, // 是否允许前台编辑——edit
                enableAddRow: false, // 允许增加行
                enableAddCol: false, // 允许增加列
                // userInfo: false, // 右上角的用户信息展示样式
                // showRowBar: false, // 是否显示行号区域
                // showColumnBar: false, // 是否显示列号区域
                // sheetFormulaBar: false, // 是否显示公式栏
                enableAddBackTop: false,//返回头部按钮
                // functionButton: '<button id="" class="btn btn-primary" style="padding:3px 6px;font-size: 12px;margin-right: 10px;">下载</button>',  // 需要显示信息栏
            });
            $('body').addClass('page-loaded');
        }catch(err){
            $('body').addClass('page-loaded');
            page.showTips('解析失败，请检查文件是否正常！');
        }
    }
    // 读取文件内容，生成luckysheet配置参数——导入
    page.getFileInfo(function(file){
        if(file.ext == 'xlsx') {
            LuckyExcel.transformExcelToLucky(file.content, function(exportJson, luckysheetfile){
                loadLuckySheet(exportJson);
            });
            return;
        }
        // xls通过SheetJs获取数据
        var sheet = utils.getLuckySheet();
        var sheets = [];
        var wb = XLSX.read(file.content, {type: 'buffer'});
        for(var i in wb.SheetNames) {
            var name = wb.SheetNames[i];
            var data = utils.xlsxToLuckySheet(wb.Sheets[name]);
            var tmpSheet = JSON.parse(JSON.stringify(sheet));
            tmpSheet.name = name;
            tmpSheet.index = parseInt(i);
            tmpSheet.order = parseInt(i);
            tmpSheet.data = data;
            sheets.push(tmpSheet);
        }
        loadLuckySheet({sheets: sheets});
    });
});