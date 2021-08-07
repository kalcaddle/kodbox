## 版本：`Version 1.4.12`
- 下载：https://github.com/ajaxorg/ace-builds/tags
- 插件：https://github.com/ajaxorg/ace/wiki/Extensions
- js压缩: https://www.css-js.com/   https://tool.lu/js/

## 优化修改 (ace.js)
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
- 含未知字符时会导致光标位置错误; 形如:123123
    ```
    ace.define("ace/layer/text"... 方法 this.$renderToken
    多处修改: $renderToken 修改reg匹配定义; 修改cjk匹配后字符所在宽度;
    ```
- 移动端复制粘贴操作菜单;不支持剪贴板读取时使用内部复制数据
    ```
    需要https或127.0域名,才能访问navigator.clipboard 剪贴板; 不支持时内部自动记录复制数据;
    ace.define("ace/mouse/touch_handler"... 
    
    ace.mobileCopyText = '';//add by warlee;exports.addTouchListeners前
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
    
    方法: this.$getDisplayTokens; if(0x1100 && isFullWidth(c) 后面
    arr.push(CHAR, CHAR_EXT); 改为==> arr.push(SPACE, CHAR_EXT);
    ```
-----

- php格式化扩展: ext-beautify.js 修改解决关键字语法错误;升级时保留
- emmet.min.js 的_. 方法与loash冲突；修改代码，进行闭包处理；导出全局变量 emmet;
- 重写搜索控件: ext-searchboxKod.js
- 重命名php相关文件(部分服务器防火墙拦截): 
    ``` 
    mode-php.js     =>  mode-phhp.js, 
    php_worker.js   =>  phhp_worker.js
    snippets/php.js =>  phhp.js
    ```