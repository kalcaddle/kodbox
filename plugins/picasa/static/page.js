define(function(require, exports) {
	var getImageArr = function(filePath,name){
		var imageList = kodApp.imageList;
		kodApp.imageList = false;
		if(!imageList) {
			imageList = {items:[{
				src:core.pathImage(filePath,1200),
				msrc:core.pathImage(filePath,250),
				trueImage:core.pathImage(filePath,false),
				title:htmlEncode(name || ''),
			}],index:0};
		}
		var items  = [];
		_.each(imageList.items,function(item){
			var parse = $.parseUrl(item.src);
			var title = item.title || _.get(parse,'params.name') || pathTools.pathThis(item.src);
			items.push([
				[
					item.msrc || item.src,
					item.src,
					item.trueImage || item.src
				],
				htmlEncode(title),[0,0],''
			]);
		});
		return {items:items,index:imageList.index};
	};
	
	//播放幻灯片时，删除图片.
	var removeImageRequest = function(path,callback){
		callback();
	};
	var removeImage = function(){
		var index = parseInt($('#PV_Control #PV_Items .current').attr('number'));
		var path = myPicasa.arrItems[index][0][2];
		removeImageRequest(path,function(){
			if(myPicasa.arrItems.length <=1){
				return myPicasa.close();
			}
			myPicasa.arrItems.splice(index,1);
			if(index >= myPicasa.arrItems.length -1){
				index = myPicasa.arrItems.length -1
			}
			myPicasa.play(myPicasa.arrItems,index);
		});
	}
	var imageRotate = function(rotate){
		var index = parseInt($('#PV_Control #PV_Items .current').attr('number'));
		var path = myPicasa.arrItems[index][0][2];
		ui.pathOperate.imageRotate(path,rotate,function(){
			var imgSrc = function(img){
				var str = '&picture='+UUID();
				return img.indexOf('?') == -1 ? img+'?a=1'+str : img+str
			}
			var $img = $('[data-path='+pathHashEncode(path)+']').find('img');
			var imageSmall = imgSrc(myPicasa.arrItems[index][0][0]);
			var imgageBig = imgSrc(myPicasa.arrItems[index][0][1]);
			
			$("#PV_Items .current img").attr('src',imageSmall);
			$img.attr('src',imageSmall);
			$img.attr('data-original',imageSmall);
			myPicasa.resetImage(imgageBig,imageSmall);
		});
	}
	var loadImageBefore = function(){
	    var index = parseInt($('#PV_Control #PV_Items .current').attr('number'));
		var path = myPicasa.arrItems[index][0][2];
		var $action = $("#PV_rotate_Left,#PV_rotate_Right,#PV_Btn_Remove");
		if(path.substr(0,4) == 'http'){
		    $action.addClass('hidden');
		}else{
		    $action.removeClass('hidden');
		}
	};
	
	return function(path,ext,name,appStatic){
		requireAsync([
			appStatic+'picasa/style/style.css',
			appStatic+'picasa/picasa.js'
		],function(){
			if(!window.myPicasa){
				myPicasa = new Picasa();
				myPicasa.removeImage = removeImage;
				myPicasa.imageRotate = imageRotate;
				myPicasa.loadImageBefore = loadImageBefore;
			}
			var images = getImageArr(path,name);
			myPicasa.play(images.items,images.index);
			$("#PV_Btn_Open").remove();
			setTimeout(function(){
				$('#PicasaView').attr('tabindex','10').focus();
			},100);
		});
	};
	
});

