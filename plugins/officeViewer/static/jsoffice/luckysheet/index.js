$(function(){
    // 根据数据绘制表格
    var loadLuckySheet = function(exportJson){
        // 获得转化后的表格数据后，使用luckysheet初始化，或者更新已有的luckysheet工作簿
        // 注：luckysheet需要引入依赖包和初始化表格容器才可以使用
        luckysheet.create({
            container: 'output', // 容器id
            data:exportJson.sheets,
            // plugins: ['chart'],
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
    }

    // 读取文件内容，生成luckysheet配置参数——导入
    page.getFileInfo(function(file){
        // 1.xlsx，直接luckyexcel读取
        if(file.ext == 'xlsx') {
            setLuckySheet(file.content, function(content){
                LuckyExcel.transformExcelToLucky(content, function(exportJson, luckysheetfile){
                    setLuckySheet(exportJson, function(exportJson){
                        loadLuckySheet(exportJson);
                    });
                });
            });
            return;
        }
        var sheet = utils.getLuckySheet();
        // 2.csv字符串解析为data，只能读一个sheet
        if(file.ext == 'csv'){
            utils.ab2str(file.content, function(str){
                var _sheet = JSON.parse(JSON.stringify(sheet));
                _sheet.name = 'Sheet1';
                _sheet.index = _sheet.order = 0;
                _sheet.data = utils.csvToLuckySheet(str);
                setLuckySheet({sheets: [_sheet]}, function(exportJson){
                    loadLuckySheet(exportJson);
                });
            });
            return;
        }
        // 3.xls通过SheetJs获取数据
        var wb = XLSX.read(file.content, {type: 'buffer'});
        var sheets = [];
        for(var i in wb.SheetNames) {
            var name = wb.SheetNames[i];
            var _sheet = JSON.parse(JSON.stringify(sheet));
            _sheet.name = name;
            _sheet.index = _sheet.order = parseInt(i);
            _sheet.data = utils.xlsToLuckySheet(wb.Sheets[name]);
            sheets.push(_sheet);
        }
        setLuckySheet({sheets: sheets}, function(exportJson){
            loadLuckySheet(exportJson);
        });
    });
    var setLuckySheet = function(data, callback){
        try{
            callback(data);
        }catch(err){
            page.showTips('文件损坏，或包含不支持的内容格式！');
        }
    }
});