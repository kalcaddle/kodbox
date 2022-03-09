define(function(require, exports) {
	var imageRemoveCallback = false;
	var getImageArr = function(filePath,name){
		var imageList = kodApp.imageList;
		imageRemoveCallback =  imageList.removeCallback || false;
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
				htmlEncode(title),[0,0],'',item
			]);
		});
		return {items:items,index:imageList.index};
	};
	
	//播放幻灯片时，删除图片.
	var removeImageRequest = function(imageItem,callback){
		if(!imageItem || !imageItem[4] || !imageRemoveCallback) return;
		imageRemoveCallback(imageItem[4],function(){
			callback && callback();
		});
	};
	var removeAllowCheck = function(){
		var $btn = $('#PV_Btn_Remove');
		imageRemoveCallback ? $btn.removeClass('hidden') : $btn.addClass('hidden');
	}
	var openImageAfter = function(){
		$("#PV_Btn_Open").remove();
		setTimeout(function(){
			removeAllowCheck();
			$('#PicasaView').attr('tabindex','10').focus();
		},100);
	};
	var removeImage = function(){
		var index = parseInt($('#PV_Control #PV_Items .current').attr('number'));
		var imageItem = myPicasa.arrItems[index];
		removeImageRequest(imageItem,function(){
			if(myPicasa.arrItems.length <=1){
				return myPicasa.close();
			}
			myPicasa.arrItems.splice(index,1);
			if(index >= myPicasa.arrItems.length -1){
				index = myPicasa.arrItems.length -1
			}
			myPicasa.play(myPicasa.arrItems,index);
			openImageAfter();
		});
	}
	
	
	var optionsList = function(storeKey,lengthMax){
		LocalData.values = LocalData.values || {};
		var values = LocalData.values[storeKey] || LocalData.getConfig(storeKey) || {};
		LocalData.values[storeKey] = values;
		var get = function(key,defaultValue){
			return values[key] || defaultValue;
		}
		var set = function(key,value){
			values[key] = value;
			if(value == null){delete values[key];}
			save();
		}
		var save = function(){
			if(!lengthMax) return;
			var keys = Object.keys(values);
			if(keys.length > lengthMax){
				var newValues = {};
				keys = keys.slice(keys.length - lengthMax);
				for(var i = 0; i < keys.length; i++) {
					newValues[keys[i]] = values[keys[i]];
				}
				values = newValues;
			}
			LocalData.setConfig(storeKey,values);
		};
		var clear = function(){values = {};save();}
		return {set:set,get:get,clear:clear};
	}
	var imageRotateList = new optionsList('imageRotate',500);
	
	var imageRotate = function(rotate){
		var index = parseInt($('#PV_Control #PV_Items .current').attr('number'));
		var image = myPicasa.arrItems[index][0];
		// console.log(101,rotate,myPicasa.arrItems[index]);
		var radius = parseInt(imageRotateItem(image[1],'get')) + 90;
		imageRotateItem(image[1],radius,true);
	}
	var imageRotateItem = function(src,radius,isSave){
		if(!src) return;
		var $image = $('#PV_Picture');
		var style  = $image.attr('style') || '';
		var match  = style.match(/transform:\s*rotate\((\d+)deg\)/);
		if(radius == 'get'){return match ? match[1]:0;}

		var transform = radius ? 'rotate('+radius+'deg)' : '';
		if(isSave){
			$image.css('transition','all 0.3s');
			setTimeout(function(){$image.css('transition','');},310);
			if(radius % 360 == 0){radius = null;}
			imageRotateList.set(src,radius);
		}
		$image.css('transform',transform);
	};
	
	var timeoutHolder = false;
	var loadImageBefore = function(){
	    var index = parseInt($('#PV_Control #PV_Items .current').attr('number'));
		var src   = myPicasa.arrItems[index][0][1];
		var radius = imageRotateList.get(src,0);
		var image = myPicasa.arrItems[index][0];
		imageRotateItem(src,radius);
		
		clearTimeout(timeoutHolder);
		$('#PV_Picture_Temp').attr('src','');
		timeoutHolder = setTimeout(function(){
			$('#PV_Picture_Temp').attr('src',image[0]);
		},500);//延迟处理;
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
			openImageAfter();
		});
	};
});