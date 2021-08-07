(function(){
    // 给页面添加样式
    var pageStyle = function(){
        // 移动端
        if($.isWap || $(window.document).width() < 768) {
            var padding = '10px';
        }else{
            $(".page-box").css({
                'width': '80%',
                'margin': '0px auto',
                'min-height': '360px',
            });
            var padding = '100px 140px';
        }
        $('.page-box #output').css({'padding': padding});
        $('.page-box #output img').css({'max-width': '100%'});
    }
    page.getFileInfo(function(file){
        mammoth.convertToHtml({arrayBuffer: file.content})
        .then(function(result){
            $("#output").html(result.value);
            $('body').addClass('page-loaded');
            pageStyle();
        }).catch(function(err){
            console.log('error: ', err);
            $('body').addClass('page-loaded');
            page.showTips('解析失败，请检查文件是否正常！');
        }).done();
    });
})();