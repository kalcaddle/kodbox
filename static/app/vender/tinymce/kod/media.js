if(!tinymce.pluginResetAdd){tinymce.pluginResetAdd = [];}
tinymce.pluginResetAdd.push(function(editor){
    kodApi.pathSelectBind = function($btn,$input){
        $btn.bind('click',function(){
            var param = {
                type: 'file',
                title: $btn.attr('title'),
        		allowExt:$btn.attr('ext'),
        		makeUrl: true,// 默认获取文件外链;
        		callback:function(result,options){
        		    var link = result.downloadPath;
        		    $input.val(link);
        		}
            };
            var view = new kodApi.pathSelect(param);
        });
    };
    
    var thePlugin = editor.plugins.media;
    var mediaRuleAdd = [
        /// 添加youku,bilibili,qq视频; iqiyi由于url加密暂未添加;
		/**
		 * 
		 * 测试: 
		 * http://vimeo.com/2342345?start=good
		 * https://www.bilibili.com/video/BV1H54y1i7P8/?spm=df
		 * http://bilibili.com/video/BV1H54y1i7P8
		 * 
		 * https://v.qq.com/x/cover/mzc002009a3e8xj/o0034z52rj3.html
		 */
	    {
            regex:/v\.youku\.com\/v_show\/id_(\w+)=*\.html/,
            type: 'iframe',w: 510,h: 498,allowFullscreen: true,
			url: 'player.youku.com/embed/$1',
        },
        {
            regex:/v\.qq\.com.*?vid=(.+)/,
            type: 'iframe',w: 500,h: 310,allowFullscreen: true,
            url:'v.qq.com/txp/iframe/player.html?vid=$1&amp;auto=0'
        },
        {
            regex:/v\.qq\.com\/x?\/?(page|cover).*?\/([^\/]+)\.html\??.*/,
            type: 'iframe',w: 500,h: 310,allowFullscreen: true,
            url:'v.qq.com/txp/iframe/player.html?vid=$2&amp;auto=0'
        },
        {
            regex:/bilibili\.com\/video\/([0-9a-zA-Z]+)/,
            type: 'iframe',w: 600,h: 320,allowFullscreen: true,
            url:'player.bilibili.com/player.html?bvid=$1'
        },
        {
            regex:/instagram\.com\/p\/(.[a-zA-Z0-9_-]*)/,
            type: 'iframe',w: 612,h: 710,allowFullscreen: true,
            url:'instagram.com/p/{{1}}/embed/'
        },
        {
            regex:/vine\.co\/v\/([a-zA-Z0-9]+)/,
            type: 'iframe',w: 640,h: 360,allowFullscreen: true,
            url:'$0/embed/simple'
        },
        {
            regex:/facebook\.com\/([^\/]+)\/videos\/([0-9]+)/,
            type: 'iframe',w: 560,h: 301,allowFullscreen: true,
            url:'www.facebook.com/plugins/video.php?href={{$1}}&show_text=0&width=560'
        }
    ];
    if(thePlugin.urlPatterns && !thePlugin.urlPatternsInit){
        _.each(mediaRuleAdd,function(item){
            thePlugin.urlPatterns.push(item);
        })
        thePlugin.urlPatternsInit = true;
    }

    var resetView  = function($dialog){
        var $tab = $dialog.find('.tox-dialog__body-nav-item--active');
        if($tab.index() != 0 ) return;
        
        var $field = $dialog.find('.tox-form .tox-form__group').first();
        var text = '视频地址(视频文件url或网络视频)'
        var desc = '<i class="vedio-desc">网络视频支持: youku,qq视频,bilibili; yutube,vimeo,daily,instagram,vine,facebook</i>';
        if($field.find('.pathSelect').length) return;
        
        
        $field.find('.tox-label').html(text);
        $field.addClass('vedio-field');
        $(desc).appendTo($field);
        
        // 添加文件选择按钮;
        var selectBtn = '<i class="btn btn-default btn-sm pathSelect font-icon ri-folder-fill-3"\
            ext="mov|mp4|m4v|ogg|webm|ogv" \
            title="从网盘选择视频" title-timeout="100"></i>';
        var $selectBtn = $(selectBtn).appendTo($field.find('.tox-control-wrap'));
        kodApi.pathSelectBind($selectBtn,$field.find('.tox-textfield'));
    }
    
    var showDialog = thePlugin.showDialog;
    thePlugin.showDialog = function(){
        showDialog();
        var $dialog = $('.tox-dialog__body');
        if($dialog.length == 0) return;
        
        resetView($dialog);
        $dialog.find('.tox-dialog__body-nav-item').first().bind('click',function(){
            setTimeout(function() {
                resetView($dialog);
            },0);
        });
    };
    editor.addCommand('mceMedia',thePlugin.showDialog);
});


tinymce.pluginResetAdd.push(function(editor){
    var thePlugin = editor.plugins.image;
    thePlugin.afterShowDialog = function(){
        setTimeout(resetView,0);
    }
    
    var resetView = function(){
        var $dialog = $('.tox-dialog__body');
        if($dialog.length == 0) return;
        
        var $field = $dialog.find('.tox-form .tox-form__group').first();
        if($field.find('.pathSelect').length) return;

        $field.addClass('image-field');
        var selectBtn = '<i class="btn btn-default btn-sm pathSelect font-icon ri-folder-fill-3"\
            ext="png|jpg|gif|jpeg|ico|svg" \
            title="从网盘选择图片" title-timeout="100"></i>';
        var $selectBtn = $(selectBtn).appendTo($field.find('.tox-control-wrap'));
        kodApi.pathSelectBind($selectBtn,$field.find('.tox-textfield'));
        
        var $tab = $dialog.find('.tox-dialog__body-nav-item').first();
        $tab.unbind('click');
        $tab.bind('click',function(){
            setTimeout(resetView,0);
        });
    };
});



var parseVideo = function(url){
    if(!url) return '';
    //解析参考: https://github.com/summernote/summernote/blob/develop/src/js/base/module/VideoDialog.js
    var map = {
        youtube:{
            regex:/\/\/(?:(?:www|m)\.)?(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=))([\w|-]{11})(?:(?:[\?&]t=)(\S+))?$/,
            attr:"width='640' height='360'",
            src:'{{schema}}www.youtube.com/embed/{{id}}',idIndex:1,
            srcAdd:function(match){
                var start = 0;
                if(!match[2]) return '';
                
                var matchStart = match[2].match(/^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?$/);
                if (matchStart) {
                    var arr = [3600, 60, 1];
                    for (arr, i = 0, r = arr.length; i < r; i++) {
                        if(matchStart[i + 1]){
                            start += arr[i] * parseInt(matchStart[i + 1], 10);
                        }
                    }
                }
                if(!start) return '';
                return '?start='+start;
            }
        },
        instagram:{
            regex:/(?:www\.|\/\/)instagram\.com\/p\/(.[a-zA-Z0-9_-]*)/,
            attr:"width='612' height='710' allowtransparency='true'",
            src:'https://instagram.com/p/{{id}}/embed/',idIndex:1
        },
        vine:{
            regex:/\/\/vine\.co\/v\/([a-zA-Z0-9]+)/,
            attr:"width='600' height='600'",
            src:'{{id}}/embed/simple',idIndex:0
        },
        vimeo:{
            regex:/\/\/(player\.)?vimeo\.com\/([a-z]*\/)*(\d+)[?]?.*/,
            attr:"width='640' height='360' webkitallowfullscreen mozallowfullscreen allowfullscreen",
            src:'{{schema}}player.vimeo.com/video/{{id}}',idIndex:3
        },
        dailymotion:{
            regex:/.+dailymotion.com\/(video|hub)\/([^_]+)[^#]*(#video=([^_&]+))?/,
            attr:"width='640' height='360'",
            src:'{{schema}}www.dailymotion.com/embed/video/{{id}}',idIndex:2
        },
        facebook:{
            regex:/(?:www\.|\/\/)facebook\.com\/([^\/]+)\/videos\/([0-9]+)/,
            attr:"width='560' height='301' allowtransparency='true' ",
            src:'https://www.facebook.com/plugins/video.php?href={{id}}&show_text=0&width=560',
            idIndex:0,idParse:encodeURIComponent
        },
        
        youku:{
            regex:/\/\/v\.youku\.com\/v_show\/id_(\w+)=*\.html/,
            attr:"width='510' height='498' webkitallowfullscreen mozallowfullscreen allowfullscreen",
            src:'{{schema}}player.youku.com/embed/{{id}}',idIndex:1
        },
        qq:{
            regex:/\/\/v\.qq\.com.*?vid=(.+)/,
            attr:"width='500' height='310' webkitallowfullscreen mozallowfullscreen allowfullscreen",
            src:'https://v.qq.com/txp/iframe/player.html?vid={{id}}&amp;auto=0',idIndex:1
        },
        qqPage:{
            regex:/\/\/v\.qq\.com\/x?\/?(page|cover).*?\/([^\/]+)\.html\??.*/,
            attr:"width='500' height='310' webkitallowfullscreen mozallowfullscreen allowfullscreen",
            src:'https://v.qq.com/txp/iframe/player.html?vid={{id}}&amp;auto=0',idIndex:2
        },
        bilibili:{
            regex:/(?:www\.|\/\/)bilibili\.com\/video\/([0-9a-zA-Z]+)/,
            attr:"width='640' height='360' framespacing='0' allowfullscreen='true'",
            src:'https://player.bilibili.com/player.html?bvid={{id}}',idIndex:1
        },
        /* // 不能直接获取,可以通过复制iframe粘贴到内嵌;
        iqiyi:{
            regex:/\/\/www\.iqiyi\.com\/(.+)\.html\??/,
            attr:"width='640' height='360' framespacing='0' allowfullscreen='true'",
            src:'http://open.iqiyi.com/developer/player_js/coopPlayerIndex.html?vid={{id}}',idIndex:0
        },*/
        
        file:{
            regex:/^.+\.(mp4|m4v|flv|ogg|ogv|webm)$/,
            attr:"width='640' height='360'"
        }
    };
    
    var videoType = '',videoAttr = '',videoSrc  = url;
    var schema    = $.parseUrl(url).protocol + '://';
    for(var key in map){
        var item  = map[key];
        var match = url.match(item.regex);
        if(!match) continue;
        
        if(key == 'file'){
            videoType = key;
            videoAttr = item.attr;
            break;
        }
        if(!match[item.idIndex].length) continue;
        
        var matchID = match[item.idIndex];
        if(item.idParse){
            matchID = item.idParse(matchID);
        }
        videoType = key;
        videoAttr = item.attr;
        videoSrc  = item.src.replace('{{id}}',matchID).replace('{{schema}}',schema);
        if(item.srcAdd){
            videoSrc += item.srcAdd(match);
        }
    }
    if(!videoType) return false;
    
    videoAttr = 'src="'+videoSrc+'" class="frame-vedio vedio-type-'+videoType+'" ' + videoAttr;
    if(videoType == 'file'){
        return '<video '+videoAttr+' controls></video>';
    }
    return '<iframe '+videoAttr+' scrolling="no" border="0" frameborder="0"></iframe>';
}