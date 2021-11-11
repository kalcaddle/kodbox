var utils = {
    functionHook: function(target,method,beforeFunc,afterFunc){
        var context 	= target || window;
        var _theMethod 	= context[method];
        if(!context || !_theMethod) return console.error('method error!',method);
    
        context[method] = function(){
            var args = arguments;
            if(beforeFunc){
                var newArgs = beforeFunc.apply(this,args);
                if( newArgs === false ) return;
                args = newArgs === undefined ? args : newArgs; 	//没有返回值则使用结果;
            }
            var result = _theMethod.apply(this,args);
            if( afterFunc ){
                var newResult = afterFunc.apply(this,[result,args]);
                result = newResult === undefined ? result : newResult;//没有返回值则使用结果
            }
            return result;
        }
    },

    // 初始化主页面尺寸
    initPageSize: function(ratio){
        var divId = arguments[1] === undefined ? 'all_slides_warpper' : 'left_slides_bar';
        return this.changePageSize(ratio, divId);
    },
    // 变更主页面尺寸
    changePageSize: function(ratio, divId){
        $('#'+divId+' .slide').css({'-webkit-transform': 'scale('+ratio+')'});
        var width = $('#'+divId+' .slide').width() * ratio + 'px';
        var height = $('#'+divId+' .slide').height() * ratio + 'px';    // 使用scale后获取到的是原始尺寸，因此需要*ratio
        $('#'+divId+' .slide-box').css({'width': width, 'height': height});
    },

    // 前后翻页
    nextSlide: function(type){
        if(!$('#left_slides_bar').length) return;
        var index = parseInt($('.slide-page-toolbar .page-cur-num').text());
        var total = parseInt($('.slide-page-toolbar .page-total-num').text());
        if((index == 1 && type == 'sub') || (index == total && type == 'add')) return;
        var page = type == 'sub' ? index - 1 : index + 1;
        this.gotoSlide(page);
    },
    // 页码变更
    gotoSlide: function(page){
        // 0.设置页码显示
        $('.slide-page-toolbar .page-cur-num').html(page);
        // 1.主区域显示
        $('#all_slides_warpper .slide-box').hide();
        $('#all_slides_warpper .slide-box').eq(page - 1).show();
        // 2.左右翻页图标显示
        this.setLtRtIcon();
        // 3.左侧选中样式变更
        $('#left_slides_bar .slide-box').removeClass('total-page-point');
        $('#left_slides_bar .slide-box').eq(page - 1).addClass('total-page-point');
        // 左侧选中项滚动到当前区域，滚轮停止后计算
        setTimeout(function(){
            var top = $(".total-page-point").offset().top;
            var height = $(".total-page-point").height();
            // 选中区在可视范围内时不滚动。margin+padding=8+10
            if(top >= 18 && (top + height - 8) < $("#left_slides_bar").height()) {
                return false;
            }
            // 滚动高度为选中区前所有兄弟元素的(高+mb)之和，理论上应该再+18
            var prevTop = (height + 10) * (page - 1);
            $("#left_slides_bar").scrollTop(prevTop + 10);
        }, 200);
    },
    // 左右翻页图标显示和隐藏
    setLtRtIcon: function(){
        var index = parseInt($('.slide-page-toolbar .page-cur-num').text());
        var total = parseInt($('.slide-page-toolbar .page-total-num').text());
        var funcLt = index == 1 ? 'hide' : 'show';
        var funcRt = index == total ? 'hide' : 'show';
        $('.slide-left-icon.btn')[funcLt]();
        $('.slide-right-icon.btn')[funcRt]();
    }
}