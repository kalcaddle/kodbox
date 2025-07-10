$(function(){
    // v2.1.13
    // TODO 载入图表支持；底部白边调整
    // 根据数据绘制表格
    var loadLuckySheet = function(exportJson){
        var allowEdit = _.get(FILE_INFO, 'canWrite') == '1' ? true : false;
        // 获得转化后的表格数据后，使用luckysheet初始化，或者更新已有的luckysheet工作簿
        // 注：luckysheet需要引入依赖包和初始化表格容器才可以使用
        var lngMap = {
            'zh-CN': 'zh',
            'zh-TW': 'zh_tw',
            'en': 'en',
            'es': 'es',
        };
        var lang = lngMap[kodSdkConfig.lang] || 'en';
        luckysheet.create({
            container: 'output', // 容器id
            data:exportJson.sheets,
            // plugins: ['chart'],  // luckyexcel暂不支持导入图表——解析的数据没有chart相关内容
            lang: lang,
            // title:exportJson.info.name,
            // userInfo:exportJson.info.name.creator,
            // showinfobar: false,
            // allowCopy: false, // 是否允许拷贝
            showtoolbar: false, // 是否显示工具栏——edit
            showinfobar: false, // 是否显示顶部信息栏
            // showsheetbar: false, // 是否显示底部sheet页按钮
            // showstatisticBar: false, // 是否显示底部计数栏
            sheetBottomConfig: false, // sheet页下方的添加行按钮和回到顶部按钮配置
            allowEdit: allowEdit, // 是否允许前台编辑——edit
            enableAddRow: false, // 允许增加行
            enableAddCol: false, // 允许增加列
            // userInfo: false, // 右上角的用户信息展示样式
            // showRowBar: false, // 是否显示行号区域
            // showColumnBar: false, // 是否显示列号区域
            // sheetFormulaBar: false, // 是否显示公式栏
            enableAddBackTop: false,//返回头部按钮
            // functionButton: '<button id="" class="btn btn-primary" style="padding:3px 6px;font-size: 12px;margin-right: 10px;">下载</button>',  // 需要显示信息栏
            hook: {
                cellRenderBefore: function (cell, position, sheetFile, ctx) {
                    var fmt = _.get(cell, 'ct.fa', '');
                    if (fmt && utils.xlsxCellFormat(cell)) {
                        var val = utils.xlsxNumFormat(cell.v, fmt);
                        if (val) cell.m = val;
                    }
                },
                // cellRenderAfter: function (cell, position, sheetFile, ctx) {
                //     // 设置单元格值
                //     // luckysheet.setCellValue(position.r, position.c, {m:val}, {order: 0}); // 死循环
                //     // 插入图片（写入单元格）
                //     var img = new Image();
                //     img.src = '';
                //     img.onload = function () {
                //         ctx.drawImage(img, position.start_c, position.start_r, 200, 266);
                //     }
                // },
            }
        });
        // $('body').addClass('page-loaded');
        $('body').bind('keydown', function (e) {
            // ctrl+s
            if (e.keyCode == 83 && (e.ctrlKey || e.metaKey)) {
                Tips.tips(kodSdkConfig.LNG['officeViewer.webOffice.noEditTips'], 'warning', 3000);
                return false;
            }
        });
        $('body.weboffice-page').addClass('loaded');
        page.wbAlert();
    }

    // 读取文件内容，生成luckysheet配置参数——导入
    page.getFileInfo(function(file, tipsLoading){
        // 1.xlsx，直接luckyexcel读取
        if(file.ext == 'xlsx') {
            setLuckySheet(file.content, function(content){
                utils.xlsxImageShow(content); // 图片处理
                LuckyExcel.transformExcelToLucky(content, function(exportJson, luckysheetfile){
                    // utils.luckysheetDataFormat(exportJson);  // 处理特定格式数据，弃用；替换为cellRenderBefore中重写
                    setLuckySheet(exportJson, function(exportJson){
                        loadLuckySheet(exportJson);
                        if(tipsLoading){tipsLoading.close();tipsLoading = false;}
                    });
                });
            });
            return;
        }
        var sheet = utils.getLuckySheet();
        // 2.csv以字符串方式读取，区分编码
        if(file.ext == 'csv'){
            var data = new Uint8Array(file.content);
            var code = utils.isUTF8(data) ? 'utf-8' : 'gbk';
            var str = new TextDecoder(code).decode(data);
            var wb = XLSX.read(str, { type: "string" });
        }
        // 3.xls通过SheetJs获取数据
        if(_.isUndefined(wb)) {
            var wb = XLSX.read(file.content, {type: 'buffer', cellStyles: true}); // XLSX/XLS
        }
        var sheets = [];
        for(var i in wb.SheetNames) {
            var name = wb.SheetNames[i];
            var _sheet = JSON.parse(JSON.stringify(sheet));
            _sheet.name = name;
            _sheet.index = _sheet.order = parseInt(i);
            _sheet.data = utils.xlsToLuckySheet(wb.Sheets[name], _sheet);
            sheets.push(_sheet);
        }
        setLuckySheet({sheets: sheets}, function(exportJson){
            loadLuckySheet(exportJson);
            if(tipsLoading){tipsLoading.close();tipsLoading = false;}
        });
    });
    var setLuckySheet = function(data, callback){
        try{
            callback(data);
        }catch(err){
            webOfficeAutoChange(err);
        }
    }
});