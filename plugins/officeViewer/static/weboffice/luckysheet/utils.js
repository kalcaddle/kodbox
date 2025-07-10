var utils = {
    isUTF8: function (bytes) {
        var i = 0;
        while (i < bytes.length) {
            if ((   // ASCII
                bytes[i] == 0x09 ||
                bytes[i] == 0x0A ||
                bytes[i] == 0x0D ||
                (0x20 <= bytes[i] && bytes[i] <= 0x7E)
            )) {
                i += 1;
                continue;
            }

            if ((// non-overlong 2-byte
                (0xC2 <= bytes[i] && bytes[i] <= 0xDF) &&
                (0x80 <= bytes[i + 1] && bytes[i + 1] <= 0xBF)
            )) {
                i += 2;
                continue;
            }

            if ((   // excluding overlongs
                bytes[i] == 0xE0 &&
                (0xA0 <= bytes[i + 1] && bytes[i + 1] <= 0xBF) &&
                (0x80 <= bytes[i + 2] && bytes[i + 2] <= 0xBF)
                ) || (  // straight 3-byte
                    ((0xE1 <= bytes[i] && bytes[i] <= 0xEC) ||
                        bytes[i] == 0xEE ||
                        bytes[i] == 0xEF) &&
                    (0x80 <= bytes[i + 1] && bytes[i + 1] <= 0xBF) &&
                    (0x80 <= bytes[i + 2] && bytes[i + 2] <= 0xBF)
                ) || (  // excluding surrogates
                    bytes[i] == 0xED &&
                    (0x80 <= bytes[i + 1] && bytes[i + 1] <= 0x9F) &&
                    (0x80 <= bytes[i + 2] && bytes[i + 2] <= 0xBF)
            )) {
                i += 3;
                continue;
            }

            if ((   // planes 1-3
                bytes[i] == 0xF0 &&
                (0x90 <= bytes[i + 1] && bytes[i + 1] <= 0xBF) &&
                (0x80 <= bytes[i + 2] && bytes[i + 2] <= 0xBF) &&
                (0x80 <= bytes[i + 3] && bytes[i + 3] <= 0xBF)
                ) || (  // planes 4-15
                    (0xF1 <= bytes[i] && bytes[i] <= 0xF3) &&
                    (0x80 <= bytes[i + 1] && bytes[i + 1] <= 0xBF) &&
                    (0x80 <= bytes[i + 2] && bytes[i + 2] <= 0xBF) &&
                    (0x80 <= bytes[i + 3] && bytes[i + 3] <= 0xBF)
                ) || (  // plane 16
                    bytes[i] == 0xF4 &&
                    (0x80 <= bytes[i + 1] && bytes[i + 1] <= 0x8F) &&
                    (0x80 <= bytes[i + 2] && bytes[i + 2] <= 0xBF) &&
                    (0x80 <= bytes[i + 3] && bytes[i + 3] <= 0xBF)
            )) {
                i += 4;
                continue;
            }
            return false;
        }
        return true;
    },

    // 读取cell的数字或字母
    getCellNum: function(str){
        var n = '';
        var isNum = !arguments[1];
        for(var i in str) {
            var val = parseInt(str[i]);
            var _isNaN = isNum ? !isNaN(val) : isNaN(val);
            if(_isNaN) n += str[i];
        }
        return isNum ? parseInt(n) : n;
    },
    // 表头字母转数字
    stringToNum: function(str){
        str=str.toLowerCase().split("");
        var al = str.length;
        var getCharNumber = function(charx){
            return charx.charCodeAt() -96;
        };
        var numout = 0;
        var charnum = 0;
        for(var i = 0; i < al; i++){
            charnum = getCharNumber(str[i]);
            numout += charnum * Math.pow(26, al-i-1);
        };
        return numout;
    },
    // 数字转字母
    numToString: function(numm){
        var stringArray = [];
        stringArray.length = 0;
        var numToStringAction = function(nnum){
            var num = nnum - 1;
            var a = parseInt(num / 26);
            var b = num % 26;
            stringArray.push(String.fromCharCode(64 + parseInt(b+1)));
            if(a>0){
                numToStringAction(a);
            }
        }
        numToStringAction(numm);
        return stringArray.reverse().join("");
    },
    // sheetjs.data转luckysheet.data
    xlsToLuckySheet: function(sheet, _sheet){
        var arr = (_.get(sheet, '!ref') || ':').split(':');
        var cols = this.getCellNum(arr[1], true);
        cols = this.stringToNum(cols);
        cols = cols > 26 ? cols : 26;   // 列，字母，不足的填充
        var rows = this.getCellNum(arr[1]);
        rows = rows > 84 ? rows : 84;   // 行，数字

        // 表格样式
        var _cols = _.get(sheet, '!cols') || {};
        var _rows = _.get(sheet, '!rows') || {};
        var _merges = _.get(sheet, '!merges') || {};

        var obj = [];
        var self = this;
        for(var i=1; i<=rows; i++) {
            var row = [];
            for(var j=1; j<=cols; j++) {
                var key = self.numToString(j) + i;
                var cell = null;
                if(sheet[key]) {
                    // https://dream-num.github.io/LuckysheetDocs/zh/guide/cell.html#基本单元格
                    var value = sheet[key].v || '';
                    var style = sheet[key].s || {};
                    var bgColor = _.get(style, 'fgColor.rgb');  // 前景色
                    // var ftColor = _.get(style, 'ftColor.rgb');
                    cell = {
                        m: value,   // 显示值，是否显示还取决于ct.fa
                        v: value,   // 原始值
                        ct: {fa: sheet[key].z || 'General', t: sheet[key].t || 'g'},
                        // bg: bgColor ? '#'+bgColor : '',
                        // bl: _.get(style, 'patternType') == 'bold' ? 1 : 0,
                        tb: 1,   // 0:截断;1:溢出;2:换行
                    }
                    if (bgColor) cell.bg = '#'+bgColor;
                    self.xlsxCellFormat(cell);
                }
                row.push(cell);
                _sheet.config.columnlen[j-1] = _cols[j-1] ? _cols[j-1].wpx : 73;    // 默认列宽73px
            }
            obj.push(row)
            _sheet.config.rowlen[i-1] = _rows[i-1] ? _rows[i-1].hpt * 4 / 3 : 19;   // 本来有参数hpx，但其值和hpt一样；默认值行高19px
        }
        // 合并单元格
        // https://dream-num.github.io/LuckysheetDocs/zh/guide/sheet.html#初始化配置
        _.each(_merges, function(opt){
            var r = opt.s.r;    // sheet[!merges] = [{e:{r:,c:},s:{r:,c:}}]
            var c = opt.s.c;    // s:start,e:end
            _sheet.config.merge[r+'_'+c] = {
                r: r,
                c: c,
                rs: opt.e.r - r + 1,
                cs: opt.e.c - c + 1,
            };
        });
        return obj;
    },

    // 单个sheet初始配置
    getLuckySheet: function(){
        return {
            "name": "Sheet1", 
            "color": "", 
            "status": 1, 
            "order": 0, 
            "data": [   // data直接替换，这里就不写null填充了
                [null],
                [null],
            ], 
            "config": {
                rowlen: {},     // 表格行高
                columnlen: {},  // 表格行宽
                merge: {},      // 合并单元格
            }, 
            "index": 0, 
            // "jfgird_select_save": [], 
            "luckysheet_select_save": [], 
            "visibledatarow": [], 
            "visibledatacolumn": [], 
            // "ch_width": 4560, 
            // "rh_height": 1760, 
            "luckysheet_selection_range": [], 
            "zoomRatio": 1, 
            "celldata": [], 
            // "load": "1", 
            "scrollLeft": 0, 
            "scrollTop": 0
        };
    },

    // 重置由LuckyExcel获取的数据格式
    luckysheetDataFormat: function(exportJson){
        if (!exportJson || !exportJson.sheets) return;
        var self = this;
        _.each(exportJson.sheets, function(sheet, idx){
            if (!sheet.celldata) return true;
            _.each(sheet.celldata, function(row, i){
                if (row && _.get(row, 'v.ct.fa')) {
                    self.xlsxCellFormat(row.v);
                }
            });
        });
    },

    // xls自定义数字格式（如0!.0,"万"），显示为原始值
    xlsxCellFormat: function (cell) {
        if (!cell || !cell.v) return false;
        var format = _.get(cell, 'ct.fa', '');
        // !. => \\.
        if (format && format.includes('\\.')) {
            cell.m = cell.v;
            cell.ct.fa = 'General';
            cell.ct.t = 'g';
            return true;
        }
        return false;
    },
    // 解析xls自定义数字格式（如0!.0,"万"），此format已由luckyexcel/sheetjs解析，!.被转换为了\\.
    // https://www.excelhome.net/5651.html
    xlsxNumFormat: function (value, format) {
        if (!value || !format) return false;

        var scaleFactor = 1;    // 缩放因子
        var decimalPlaces = 0;  // 小数位数
        var hasExclamationDot = format.includes('\\.'); // '!.'
        if (!hasExclamationDot) return false;
        var hasPercent = format.includes('%');

        // 1. 万位缩放处理（如 !.00 表示除以 10000，保留两位小数）
        if (hasExclamationDot) {
            value /= 10000;
            // var decimalPart = format.split('!.')[1] || '';
            var decimalPart = format.split('\\.')[1] || '';
            var decimalZeros = decimalPart.match(/^0+/)?.[0] || '';
            decimalPlaces = decimalZeros.length;
        }

        // 2. 处理逗号缩放（如 0,, 表示再除以 1,000,000）
        var trailingCommas = (format.match(/,+$/)?.[0] || '');
        scaleFactor = Math.pow(1000, trailingCommas.length);
        value /= scaleFactor;

        // 3. 处理百分比（如 0.0% 表示先乘以 100）
        if (hasPercent) value *= 100;

        // 4. 提取静态文本
        var staticText = format.match(/"([^"]*)"/)?.[1] || '';
    
        // 5. 格式化为字符串
        var result = value.toFixed(decimalPlaces);
        if (hasPercent) result += '%';

        return result + staticText;
    },

    // 显式插入图片：onlyoffice粘贴、插入（包括link）的默认无法显示
    xlsxImageShow: function(data){
        var self = this;
        var workbook = new ExcelJS.Workbook();
        workbook.xlsx.load(data).then(workbook => {
            workbook.eachSheet((sheet, id) => {
                var idx = id - 1; // 工作表下标
                sheet.getImages().forEach((image, index) => {
                    if (image.range.tl && image.range.br) return true; // 有范围则跳过
                    var imageId = image.imageId;
                    var imageData = workbook.getImage(imageId);
                    if (imageData) {
                        // var sheetName = sheet._name;
                        var row = image.range.tl.nativeRow || 0;
                        var col = image.range.tl.nativeCol || 0;
                        // 将图片插入到指定位置，需要先激活才能插入到指定位置
                        luckysheet.setSheetActive(idx, {
                            success: function () {
                                luckysheet.insertImage('data:image/png;base64,' + self.arrayBufferToBase64(imageData.buffer), {
                                    order:    idx,    // 工作表下标
                                    rowIndex: row,
                                    colIndex: col,
                                    success: () => {
                                        console.log('图片插入成功',idx,row,col);
                                    },
                                    fail: function(error){
                                        console.log('图片插入失败',error);
                                    }
                                });
                            }
                        });
                    }
                });
             });
            //  luckysheet.setSheetActive(0);
        });
    },
    arrayBufferToBase64: function (buffer) {
        let binary = '';
        const bytes = new Uint8Array(buffer);
        const len = bytes.byteLength;
        for (let i = 0; i < len; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return window.btoa(binary);
    }
    
}