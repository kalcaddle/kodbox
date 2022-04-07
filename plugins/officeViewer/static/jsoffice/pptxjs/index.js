$(function(){
    // get min.js: node_modules/.bin/uglifyjs pptxjs.kod.js -c > pptxjs.kod.min.js
    var isWap = function(){
        return $(window.document).width() < 768;
    }
    var pptxToHtml = function(fileUrl){
        $("#output").pptxToHtml({
            pptxFileUrl: fileUrl,
            fileInputId: "",
            slideMode: true,
            keyBoardShortCut: false,
            mediaProcess: false,
            slideModeConfig: {  //on slide mode (slideMode: true)
                first: 1, 
                nav: true, /** true,false : show or not nav buttons*/
                // nav: true, /** true,false : show or not nav buttons*/
                navTxtColor: "white", /** color */
                navNextTxt:"&#8250;", //">"
                navPrevTxt: "&#8249;", //"<"
                showPlayPauseBtn: true,/** true,false */
                keyBoardShortCut: false, /** true,false */
                showSlideNum: true, /** true,false */
                showTotalSlideNum: true, /** true,false */
                autoSlide: 2, /** false or seconds (the pause time between slides) , F8 to active(keyBoardShortCut: true) */
                // randomAutoSlide: false, /** true,false ,autoSlide:true */ 
                // loop: false,  /** true,false */
                background: false, /** false or color*/
                transition: "fade", /** transition type: "slid","fade","default","random" , to show transition efects :transitionTime > 0.5 */
                transitionTime: 0 /** transition time in seconds */
            }
        });
    }
    // 开始载入pptx文件
    try{
        var tipsLoading = Tips.loadingMask(false,'加载中',0.5);
        $('.page-box').addClass(isWap() ? 'is-in-wap' : 'not-in-wap');
        $('.page-box.not-in-wap #output').html('</div><div id="left_slides_bar"></div>');
        pptxToHtml(FILE_INFO.fileUrl);
        window.onerror = function (message, url, line, column, error) {
            console.error(message, url, line, column, error);
            page.showTips('文件损坏，或包含不支持的内容格式！');
        }
    }catch(err){
        if(tipsLoading){tipsLoading.close();tipsLoading = false;}
        console.error(err);
        page.showTips('文件损坏，或包含不支持的内容格式！');
    }

    // 页面缩放比例，(当前宽/高) / (原始宽/高)，原始宽高比通常为960/720、1280/720
    var pageRatio = function(wap){
        // 左侧栏
        if(arguments[1] !== undefined) {
            return 225 / dfWidth;
        }
        // 移动端，固定以宽为基准
        if(wap) {
            return $("#output").width() / dfWidth;
        }
        var pgWidth = $("#all_slides_warpper").width();
        var pgHeight = $("#all_slides_warpper").height();
        // 当前宽高比>原始宽高比，说明宽度较大，以高度为基准，否则相反
        if((pgWidth / pgHeight) > (dfWidth / dfHeight)) {
            return pgHeight / dfHeight;
        }
        return pgWidth / dfWidth;
    }
    // 文件加载完成，重置页面尺寸样式
    var dfWidth = 0;
    var dfHeight = 0;
    utils.functionHook($,'attr',false,function(res,args){
        // console.log(res,args)
        var id = args[0].id || '';
        if(id != 'all_slides_warpper') return res;  // convertToHtml结束

        // 1.添加页码栏——移动端不添加（获取不到滚动事件）
        if(!$('#output .slide-page-toolbar').length) {
            $('#output').prepend('\
                <div class="slide-page-toolbar">'
                    + '<span class="page-cur-num">' 
                    + $('#slides-slide-num').html()
                    + '</span> / <span class="page-total-num">' 
                    + $('#slides-total-slides-num').html() 
                    + '</span>\
                </div>\
                <div class="slide-left-icon btn"></div>\
                <div class="slide-right-icon btn"></div>\
            ');
            utils.setLtRtIcon();
        }

        // 2.主区域宽度、padding
        $('#all_slides_warpper').addClass('all-slides-warpper');
        // 没有获取到位置尺寸的图片，为避免覆盖整个页面，直接隐藏
        // $("#all_slides_warpper .slide img").each(function(){
        //     var pStyle = $(this).parent()[0].style;
        //     if (!pStyle.width && !pStyle.height) {
        //         // $(this).attr('style', 'width: fit-content;');
        //         $(this).addClass('hidden');
        //     }
        // });
        // 隐藏<#>
        $("#all_slides_warpper .slide .block.v-mid.content .text-block").each(function(){
            if ($(this).text() == '‹#›') $(this).addClass('hidden');
        });

        // 3.各页复制到左侧栏，并显示——非移动端分栏显示
        dfWidth = $("#all_slides_warpper .slide:eq(0)").width();
        dfHeight = $("#all_slides_warpper .slide:eq(0)").height();

        // var wap = isWap();   // 这里宽度会变化，以首次为准
        var wap = $('#output #left_slides_bar').length ? false : true;
        if(!wap) {
            $('#output').css({'padding': '40px 20px'});
            // 复制slide到左侧栏
            $("#all_slides_warpper .slide").clone(true).appendTo("#left_slides_bar");
            // 每个slide加个父级元素——scale消除空白区域，显示所有slide
            $("#output .slide").wrap('<div class="slide-box"></div>').show();
            // 主区域隐藏非第一个slide-box
            $('#all_slides_warpper .slide-box:not(:first)').hide();
            // 左侧栏添加播放图标
            $("#left_slides_bar .slide-box").append('<div class="ri-play-circle-fill slide-play-btn"></div>');
            // 左侧栏显示
            $("#left_slides_bar").show();

            // 左侧列表尺寸，以固定宽度为基准
            utils.initPageSize(pageRatio(wap, false), false);
        }else{
            // $('#output').css({'padding': '40px 10px 20px'});
            // $('#all_slides_warpper .slide div.content').css({width: 'auto'});
            $("#output .slide").wrap('<div class="slide-box"></div>');
        }
        // 4.初始化主区域子节点尺寸
        utils.initPageSize(pageRatio(wap));

        // // 5.加载图标
        // $('body').addClass('page-loaded');

        // 选中的边框
        $('#left_slides_bar .slide-box:eq(0)').addClass('total-page-point');

        // 结束
        if(tipsLoading){tipsLoading.close();tipsLoading = false;}
    });
    // 页面尺寸随窗口变化
    var setPgHeight = function(wap){
        var height = $(window).height();
        $('#output').css({
            height: (height - 16 - 40 * 2) + 'px', 
            padding: !wap ? '40px 20px' : '40px 10px',
            width: 'auto'
        });
    }
    $(window).resize(function(){
        var wap = isWap();
        setPgHeight(wap);
        // 这里可以改成阶段性变化，而不是实时变
        var ratio = pageRatio(wap);
        utils.changePageSize(ratio, 'all_slides_warpper');
    });
    setPgHeight(isWap());


    // slide显示播放图标
    $("body").on("mouseenter", "#left_slides_bar .slide-box", function() {
        $(this).find('.slide-play-btn').show();
    });
    $("body").on("mouseleave", "#left_slides_bar .slide-box", function() {
        $(this).find('.slide-play-btn').hide();
    });
    // slide播放——全屏
    $('body').on('click', '#left_slides_bar .slide-box .slide-play-btn', function(event){
        event.preventDefault();
        $('#slides-full-screen').trigger('click');
    })
    // slide点击选中
    $("body").on("click", "#left_slides_bar .slide", function() {
        var index = $('#left_slides_bar .slide').index(this);
        utils.gotoSlide(index + 1);
    });
    // 滚动翻页
    // $('body').on("mousewheel DOMMouseScroll", ':not(#left_slides_bar)', function (e) {
    $('body').on("mousewheel DOMMouseScroll", function (e) {
        var delta = (e.originalEvent.wheelDelta && (e.originalEvent.wheelDelta > 0 ? 1 : -1)) ||  // chrome & ie
                    (e.originalEvent.detail && (e.originalEvent.detail > 0 ? -1 : 1));  // firefox
        var type = delta > 0 ? 'sub' : 'add';
        utils.nextSlide(type);
    });
    // 左右点击翻页
    $('body').on("click", "#output>.btn", function() {
        var type = $(this).hasClass('slide-left-icon') ? 'sub' : 'add';
        utils.nextSlide(type);
    });
    // 快捷键翻页
    $('body').on('keydown', function(event){
        event.preventDefault();
        var key = event.keyCode;
        if($.inArray(key, [37,38,39,40]) == -1) return;
        var type = (key == 37 || key == 38) ? 'sub' : 'add';
        utils.nextSlide(type);
    });

    // 全屏
    var isFullScreen = false;
    $(document).on('fullscreenchange', function(){
        isFullScreen = !isFullScreen;
        if(isFullScreen === true) {
            $("#left_slides_bar").hide();
            $('#all_slides_warpper').removeClass('all-slides-warpper').addClass('all-slides-warpper-fscreen');
            $('#all_slides_warpper .slide').addClass('main-slide-fscreen');
            $('#output').addClass('output-fscreen');

            // resize先执行，导致获取的尺寸不对，这里再执行一次
            var ratio = pageRatio(false);
            utils.changePageSize(ratio, 'all_slides_warpper');
        }else{
            $("#left_slides_bar").show();
            $('#all_slides_warpper').addClass('all-slides-warpper').removeClass('all-slides-warpper-fscreen');
            $('#all_slides_warpper .slide').removeClass('main-slide-fscreen');
            $('#output').removeClass('output-fscreen');
        }
    });
});