(function(){
    // v.1.4.20
    // 1.get browser.js: run 'make setup' or read makefile;
    // 2.get browser.min.js: mammoth.js-master/node_modules/.bin/uglifyjs mammoth.browser.kod.js -c > mammoth.browser.kod.min.js
    // TODO 
    // \t缩进显示无效；多个空格标签为u，宽度无效；行高度；目录省略号；字体替换
    // 给页面添加样式
    var pageStyle = function(value){
        $('.page-box').addClass($.isWap ? 'is-in-wap' : 'not-in-wap');
        if(!value) return false;
        // // <p>下的图片不缩进
        // $('.page-box #output p img').each(function(){
        //     $(this).after('<span style="clear:both; display:inherit;"></span>');
        // });
        // 内容区域+padding>显示区，高度铺满——不随窗口变化
        if($('#output').css('padding')) {
            var padding = parseInt($('#output').css('padding').split(' ')[0]);
            if($('#output').height() + padding * 2 > $('.page-box').height()) {
                $('.page-box').addClass('fit-content');
            }
        }
        // 目录添加虚线
        $('#output .wd-catalog').parent().addClass('wd-catalog-line');
        $('#output .wd-catalog').before('<span class="dot"></span>');
    }

    var transformParagraph = function (element) {
        // console.log(element.alignment, element)
        // 单行占位，不分页时换行占位其实意义不大
        if(element.children.length === 0) {
            element.children.push({type: 'run', children: [{type: 'text', value: '[占位符]'}]});
            element.styleName = 'blank-line';
            return element;
        }
        return element;
    }
    var options = {
        transformDocument: mammoth.transforms.paragraph(transformParagraph),
        styleMap: [
            "u => u",   // 下划线
            "b => b",   // 粗体
            "i => i",   // 斜体
            // "strike => strike",   // 删除线
            "p[style-name='blank-line'] => p.blank-line:fresh",
            "p[style-name='indent-none'] => p.indent-none:fresh",   // numbering（ul/ol）的会有问题
            "table => table.docx-table:fresh",
            "r[style-name='wd-catalog'] => span.wd-catalog:fresh",
        ],
        convertImage: mammoth.images.imgElement(function(image) {
            // web支持显示的图片类型
            var imgTypeAll = {
                "image/png": true,
                "image/gif": true,
                "image/jpeg": true,
                "image/svg+xml": true,
                "image/tiff": true
            };
            return image.read("base64").then(function(imageBuffer) {
                if (!imgTypeAll[image.contentType]) {
                    // image.style += 'border:1px solid #eee;';
                    return {src: BASE_URL + 'weboffice/mammothjs/images/error.svg'}
                }
                return {src: "data:" + image.contentType + ";base64," + imageBuffer};
            });
        })
    };

    // 加载文件
    page.getFileInfo(function(file, tipsLoading){
        mammoth.convertToHtml({arrayBuffer: file.content}, options)
        .then(function(result){
            // console.log('转换结果',result)
            $("#output").html(result.value);
            // $('body').addClass('page-loaded');
            if(tipsLoading){tipsLoading.close();tipsLoading = false;}
            // 输出解析错误信息
            for(var i in result.messages) {
                console.warn(result.messages[i].message)
            }
            pageStyle(!!result.value);
        }).catch(function(err){
            if(tipsLoading){tipsLoading.close();tipsLoading = false;}
            page.showTips('文件损坏，或包含不支持的内容格式！');
            console.error(err);
        }).done();
    });
})();