# ACE editor
- 下载：https://github.com/ajaxorg/ace-builds
- 插件：https://github.com/ajaxorg/ace/wiki/Extensions
- js压缩: https://www.css-js.com/ (uglifyjs压缩,不勾选es6到es5)   https://tool.lu/js/
- 更新日志: https://github.com/ajaxorg/ace/blob/master/CHANGELOG.md


## 版本：`Version 1.43.4`
### 优化修改
- iframe鼠标选中超出失去焦点; ace初始化之前hook处理;
- ext-emmet.js添加快捷键注释快捷键异常(混合js,css,html,php),非html代码片段中屏蔽补全; ace初始化之前hook处理;
- 搜索,移动端复制菜单等多语言;ace初始化之前hook处理
- 单行高亮限制:  ace.js 修改 MAX_TOKEN_COUNT=2000 为 4000; // 太多会导致卡顿;
- 鼠标中键多光标选择支持;
    ```
    `ace.define("ace/mouse/multi_select_handler` 方法 onMouseDown: 中if最前加入;
    if(button == 1 || e.altKey){alt = true;button=0;};//add by warlee;
    ```
- 移动端复制粘贴操作菜单;不支持剪贴板读取时使用内部复制数据
    ```
    需要https或127.0域名,才能访问navigator.clipboard 剪贴板; 不支持时内部自动记录复制数据;
    `ace.define("ace/mouse/touch_handler"`
    clipboard && [... => (ace.mobileCopyText || clipboard) && [...

    if(!clipboard){ // if (action == "paste")...  后; 
        if(ace.mobileCopyText){editor.insert(ace.mobileCopyText);}
        return;
    }
    ace.mobileCopyText = editor.getSelectedText(); //if (action == "cut" || action == "copy") 前
    ```

- 特殊字符,光标异常情况处理
```
    //ace.js 方法: `Text.prototype.$renderToken`
    // 中文匹配正则追加 |[\u2100-\u28ff\ue700-\ue7ff\u0930]
    if (cjk) {
        screenColumn += 1;
        var span = this.dom.createElement("span");
        
        // add by warlee  字符宽度处理(中文2字符宽度,不足1字符宽的字符强制1字符宽); 
        // [\u2100-\u28ff\ue700-\ue7ff\u0930];
        // 2字符宽度: [\u2474-\u24e9\u278a-\u2797\u2160-\u2180\ue7c7\ue7c8\u22a5\u222f\u2230\u0930]
        var charWidth = self.session.isFullWidth(cjk.charCodeAt(0)) ? 2:1;
        span.style.width = (self.config.characterWidth * charWidth) + "px";
        //span.style.width = (self.config.characterWidth * 2) + "px";
        ...
        
    //ace.js 方法: `function isFullWidth(c)`   // 前面添加判断;
    function isFullWidth(c) {
        if(
            // add by warlee;
            c >= 0x2474 && c <= 0x24e9 ||
            c >= 0x278a && c <= 0x2797 ||
            c >= 0x2160 && c <= 0x2180 ||
            c == 0xe7c7 || c == 0xe7c8 || c == 0x22a5 || c == 0x222f|| c == 0x2230
        ){return true;}
```


- 中文自动换行过早问题(当成了单词)
    ```
    方法: `EditSession.prototype.$computeWrapSplits`
    `while (displayLength - lastSplit >` 前加入定义: var oldLength = 0;
    if (split > minSplit) {
        addSplit(++split);
        continue;
    } 
    改为==>  
    //add by warlee 
    if (split > minSplit) {
    	//避免死循环
        if(oldLength == displayLength - lastSplit){break;}
        oldLength = displayLength - lastSplit;
        if(tokens[split] == CHAR_EXT || tokens[split-1] == CHAR_EXT){
        	addSplit(split++)
        }else{
        	addSplit(++split);
        }
        continue;
    }
    
    方法: `EditSession.prototype.$getDisplayTokens` if(0x1100 && isFullWidth(c) 后面
    arr.push(CHAR, CHAR_EXT); 改为==> arr.push(SPACE, CHAR_EXT);
    ```
- 重写搜索控件: ext-searchboxKod.js
- 重命名php相关文件(部分服务器防火墙拦截): 
    ``` 
    mode-php.js     =>  mode-phhp.js, 
    mode-php_laravel_blade.js     =>  mode-phhp_laravel_blade.js, 
    worker-php.js   =>  worker-phhp.js
    snippets/php.js =>  phhp.js
    snippets/php_laravel_blade.js =>  phhp_laravel_blade.js
    ```


## 优化修改 (ace.js 1.5.0)
- iframe鼠标选中超出失去焦点; ace初始化之前hook处理;
- ext-emmet.js添加快捷键注释快捷键异常(混合js,css,html,php),非html代码片段中屏蔽补全; ace初始化之前hook处理;
- 搜索,移动端复制菜单等多语言;ace初始化之前hook处理
- 单行高亮限制:  ace.js 修改 MAX_TOKEN_COUNT=2000 为 5000; // 太多会导致卡顿;
- 鼠标中键多光标选择支持;同下1.4.12;
- 移动端复制粘贴操作菜单;不支持剪贴板读取时使用内部复制数据;同下1.4.12;
- 中文自动换行过早问题(当成了单词);同下1.4.12;


## 优化修改 (ace.js 1.4.12)
- iframe鼠标选中超出失去焦点; ace初始化之前hook处理;
- ext-emmet.js添加快捷键注释快捷键异常(混合js,css,html,php);ace初始化之前hook处理;
- 多光标输入中文丢失光标问题; 1.4.12之后已解决;
- ios输入中文异常问题; 1.4.12之后已解决;
- 搜索,移动端复制菜单等多语言;ace初始化之前hook处理
- 单行高亮限制:  ace.js 修改 MAX_TOKEN_COUNT=2000 为 5000; // 太多会导致卡顿;
- 鼠标中键多光标选择支持;
    ```
    ace.define("ace/mouse/multi_select_handler ...; 方法 onMouseDown: 中加入;
    if(button == 1 || e.altKey){alt = true;button=0;};//add by warlee;
    ```
- 含未知字符时会导致光标位置错误; 形如:123123  (1.5.0未发现)
    ```
    ace.define("ace/layer/text"... 方法 this.$renderToken
    多处修改: $renderToken 修改reg匹配定义; 修改cjk匹配后字符所在宽度;
    ```
- 移动端复制粘贴操作菜单;不支持剪贴板读取时使用内部复制数据
    ```
    需要https或127.0域名,才能访问navigator.clipboard 剪贴板; 不支持时内部自动记录复制数据;
    ace.define("ace/mouse/touch_handler"... 
    clipboard && [... => (ace.mobileCopyText || clipboard) && [...

    if(!clipboard){ // if (action == "paste")...  后; 
        if(ace.mobileCopyText){editor.insert(ace.mobileCopyText);}
        return;
    }
    ace.mobileCopyText = editor.getSelectedText(); //if (action == "cut" || action == "copy") 前
    ```

- 中文自动换行过早问题(当成了单词)
    ```
    ace.define("ace/layer/text"... 
    方法: this.$computeWrapSplits  
    if (split > minSplit) {
        addSplit(++split);
        continue;
    } 
    改为==>  
    //addy by warlee 
    if (split > minSplit) {
    	//避免死循环
        if(oldLength == displayLength - lastSplit){break;}
        oldLength = displayLength - lastSplit;
        if(tokens[split] == CHAR_EXT || tokens[split-1] == CHAR_EXT){
        	addSplit(split++)
        }else{
        	addSplit(++split);
        }
        continue;
    }
    while 前加入定义: var oldLength = 0;
    
    方法: this.$getDisplayTokens = function; if(0x1100 && isFullWidth(c) 后面
    arr.push(CHAR, CHAR_EXT); 改为==> arr.push(SPACE, CHAR_EXT);
    ```
-----

- php格式化扩展: ext-beautify.js 修改解决关键字语法错误;升级时保留(1.5以后无需处理)
- emmet.min.js 的_. 方法与loash冲突；修改代码，进行闭包处理；导出全局变量 emmet;
- 重写搜索控件: ext-searchboxKod.js
- 重命名php相关文件(部分服务器防火墙拦截): 
    ``` 
    mode-php.js     =>  mode-phhp.js, 
    worker-php.js   =>  worker-phhp.js
    snippets/php.js =>  phhp.js
    ```