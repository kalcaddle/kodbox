var utils = {
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

    xlsxToLuckySheet: function(sheet){
        var arr = sheet['!ref'].split(':');
        var cols = this.getCellNum(arr[1], true);
        cols = this.stringToNum(cols);
        cols = cols > 26 ? cols : 26;   // 列，字母，不足的填充
        var rows = this.getCellNum(arr[1]);
        rows = rows > 84 ? rows : 84;   // 行，数字
        
        var self = this;
        var obj = []
        for(var i=1; i<=rows; i++) {
            var row = [];
            for(var j=1; j<=cols; j++) {
                var key = self.numToString(j) + i;
                var cell = null;
                if(sheet[key]) {
                    var value = sheet[key].v;
                    cell = {
                        m: value,
                        ct: {fa: 'General', t: 'g'},
                        v: value
                    }
                }
                row.push(cell);
            }
            obj.push(row)
        }
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
            "config": {}, 
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
    }
}