$(function(){
    var isWap = function(){
        return $(window.document).width() < 768;
    }
    var pptxToHtml = function(fileUrl){
        $("#output").pptxToHtml({
            pptxFileUrl: fileUrl,
            fileInputId: "",
            slideMode: true,
            keyBoardShortCut: true,
            mediaProcess: false,
            slideModeConfig: {  //on slide mode (slideMode: true)
                first: 1, 
                nav: true, /** true,false : show or not nav buttons*/
                // nav: true, /** true,false : show or not nav buttons*/
                navTxtColor: "white", /** color */
                navNextTxt:"&#8250;", //">"
                navPrevTxt: "&#8249;", //"<"
                showPlayPauseBtn: true,/** true,false */
                keyBoardShortCut: true, /** true,false */
                showSlideNum: true, /** true,false */
                showTotalSlideNum: true, /** true,false */
                autoSlide: 2, /** false or seconds (the pause time between slides) , F8 to active(keyBoardShortCut: true) */
                // randomAutoSlide: false, /** true,false ,autoSlide:true */ 
                // loop: false,  /** true,false */
                background: false, /** false or color*/
                // transition: "default", /** transition type: "slid","fade","default","random" , to show transition efects :transitionTime > 0.5 */
                // transitionTime: 1 /** transition time in seconds */
            }
        });
    }
    // 开始载入pptx文件
    try{
        var wap = 'in-wap';
        if(!isWap()) {
            wap = 'not-in-wap';
            $('.page-box #output').html('<div id="slide-bar"></div>');
        }
        $('.page-box #output').addClass(wap);
        pptxToHtml(FILE_INFO.fileUrl);
    }catch(err){
        page.showTips('解析失败，请检查文件是否正常！');
    }

    // pptx文件加载完成
    page.functionHook($,'attr',false,function(res,args){
        // console.log(res,args)
        var id = args[0].id || '';
        if(id != 'all_slides_warpper') return res;

        // 主区域宽度、padding
        $('#all_slides_warpper').addClass('all-slides-warpper');
        // 各页复制到左侧栏，并显示——非移动端分栏显示
        if(!isWap()) {
            $('#output').css({'padding': '40px 20px'});
            $("#all_slides_warpper .slide").each(function(){
                $(this).clone(true).appendTo("#slide-bar");
            });
            $("#slide-bar, #slide-bar .slide").show();
        }else{
            $('#output').css({'padding': '40px 10px 20px'});
            // $('#all_slides_warpper>.slide div.content').css({width: 'auto'});
        }
        // 加载图标
        $('body').addClass('page-loaded');
        // 按钮加提示
        var imgBtn = {
            'slides-prev': '上一页',
            'slides-play-pause': '播放/暂停',
            'slides-full-screen': '全屏',
            'slides-next': '下一页'
        };
        for(var id in imgBtn) {
            $('#' + id).attr('title', imgBtn[id]);
        }
    });

    // 全屏
    var isFullScreen = false;
    $(document).on('fullscreenchange', function(){
        isFullScreen = !isFullScreen;
        if(isFullScreen === true) {
            $("#slide-bar").hide();
            $(".slides-toolbar>span").hide();
            $('.slides-toolbar').css({'background-color': '#000', 'border-bottom': 'none'});
            $('#all_slides_warpper').removeClass('all-slides-warpper')
            .addClass('all-slides-warpper-fscreen');
            $('#all_slides_warpper>.slide').addClass('main-slide-fscreen');
            // 全屏时margin-top
            var height = $(window).height() - $('.main-slide-fscreen').height();
            height = height > 40 ? parseInt(height / 2) : 20;
            $('.main-slide-fscreen').css({top: height + 'px'});
        }else{
            $("#slide-bar").show();
            $(".slides-toolbar>span").show();
            $('.slides-toolbar').css({'background-color': '#fff', 'border-bottom': '1px solid #eee'});
            $('#all_slides_warpper').addClass('all-slides-warpper')
            .removeClass('all-slides-warpper-fscreen');
            $('#all_slides_warpper>.slide').removeClass('main-slide-fscreen');
            // $('#all_slides_warpper>.slide').css({top: ''});
        }
    });
});