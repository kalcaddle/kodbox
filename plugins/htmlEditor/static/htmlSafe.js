ClassBase.define({
	init:function(){
		this.bindMessage();
	},
	bindMessage:function(){
		if(window._htmlSafeHasBindMessage){return;}
		window._htmlSafeHasBindMessage = true;
		// html内容安全预览(确保安全,禁用当前站点cookie;支持内部js执行);
		window.addEventListener('message',function(e){
			//console.error('kod-recevice-message',e);
			if(!e || !_.isObject(e.data) || e.data.type != 'iframe.event' ){return;}
			// 页面点击等事件; {type:'iframe.event',event:'mousedown'}
			// 连接跳转: {type:'iframe.event',event:'iframeLinkHref',url:}
			// 内部嵌入iframe: {type:'iframe.event',event:'iframeChildLoad',url:,uuid:} kod层接收到消息进行处理,处理完成后通知到iframe窗口;
			$('iframe').each(function(){ // html文件预览,内部点击事件通知到该层(对话框置顶,右键菜单等处理)
				if($(this).get(0).contentWindow != e.source) return;
				$(this).trigger(e.data.event,e.data);
			});
		});
	},	
	support:function(){
		if(!window.fetch || !window.MutationObserver){return false;}
		var frame = document.createElement("iframe");
		if(!("sandbox" in frame) || !("srcdoc" in frame)){return false;}
		
		if($.browserIS.ios){// ios webview中不支持;Safari支持;
			var ua = navigator.userAgent.toLowerCase();
			if(ua.indexOf('quark')){return false;}
			if(ua.indexOf('weixin')){return false;}
		} 
		return true;
	},
	// 禁用cookie,去除 allow-same-origin;
	iframeAttr:function(){
		var allow   = "midi; geolocation; camera *; microphone; camera; display-capture; encrypted-media; clipboard-read; clipboard-write;";
		var sandbox = 'allow-modals allow-orientation-lock allow-forms allow-scripts allow-popups allow-pointer-lock allow-top-navigation-by-user-activation allow-downloads';
		return "sandbox='"+sandbox+"' allow='"+allow+"' allowfullscreen allowpaymentrequest";
	},
	iframe:function(){
		return '<iframe src="" '+this.iframeAttr()+' frameborder="0" style="width:100%;height:100%;border:0;"></iframe>';
	},
	
	loadContent:function($iframe,filePath,pathModel,contentSet){
		var self = this;
		var linkPre = pathModel.urlMake('fileOutBy','viewToken='+(G.kod.viewToken || ''));
		var apiHostTo = window.API_HOST;// chrome xhr跨域时,发送options请求进行预检处理;需要带上index.php,否则会被nginx拦截报错;
		if(apiHostTo.indexOf('/?') == apiHostTo.length - 2){
			apiHostTo = apiHostTo.replace('/?','/index.php?');
		}
		linkPre = linkPre.replace(window.API_HOST,apiHostTo);
		
		if(_.startsWith(filePath,G.kod.APP_HOST_LINK) && 
			_.includes(filePath,'explorer/share/fileOut')){
			var urlInfo = $.parseUrl(filePath); // 外链分享,文件嵌入情况;
			if(urlInfo.params.path){filePath = urlDecode(urlInfo.params.path);} 
		}
		
		var getTruePath = function(thePath,addPath,callback){
			$.ajax({
				url:pathModel.urlMake('fileOutBy'),
				type:'POST',dateType:'json',
				data:{path:thePath,add:addPath,type:'getTruePath'},
				success:function(data){
					if(!_.isObject(data) || !data.code || !data.data){return;}
					callback && callback(data.data,addPath);
				}
			});
		};
		
		// 页面跳转,相对路径处理(先获取,再以新的作为当前文件名)
		$iframe.unbind('iframeLinkHref').bind('iframeLinkHref',function(e,eventData){ 
			var thePath = $(this).attr('data-file-path');
			var addPath = eventData.url;
			if(!thePath){return;}
			getTruePath(thePath,addPath,function(truePath){
				reloadContent(truePath,addPath);
			});
		});
		
		// 页面嵌入子iframe; 数据处理,处理完成后通知到iframe的window;
		$iframe.unbind('iframeChildLoad').bind('iframeChildLoad',function(e,eventData){ 
			var thePath = $(this).attr('data-file-path');
			var addPath = eventData.url;
			if(!thePath){return;}
			getTruePath(thePath,addPath,function(truePath){
				pathModel.fileContent({path:truePath},function(content){
					var iframeWindow = $iframe.get(0).contentWindow;
					if(!iframeWindow){return;}
					iframeWindow.postMessage({
						type:'parent.event',event:'iframeChildLoadSuccess',
						url:truePath,uuid:eventData.uuid,
						content:self.parseContent(content,truePath,linkPre,addPath)
					},'*');
				});
			});
		});
		

		var reloadContent = function(thePath,addPath,theContent){
			$iframe.attr('data-file-path',thePath);
			$iframe.attr('srcdoc','').trigger('load');//$iframe.attr('src','about:blank');
			var setContent = function(content){
				var newContent = self.parseContent(content,thePath,linkPre,addPath);
				$iframe.attr('srcdoc',newContent); // iOS-微信中无效;
			};
			if(theContent){return setContent(contentSet);}
			pathModel.fileContent({path:thePath},setContent);
		};
		reloadContent(filePath,false,contentSet);
	},
	parseContent:function(content,filePath,linkPre,addPath){
		var self = this;
		var urlFilterCurrent = function(url,replaceType){ //与urlFilter逻辑一致,
			if(!self.isPathUrl(url)){return url;}
			
			var paramAdd = '';// 保持之前参数;
			if(url.indexOf('?') > 0){paramAdd = '&_s_=/&'+url.substr(url.indexOf('?')+1);}
			if(url.indexOf('#') > 0){paramAdd = '#'+url.substr(url.indexOf('#')+1);}
			
			url = self.pathUrlParse(url,basePath);
			var result = linkPre;
			if(url.indexOf('.wasm.js') != -1){replaceType = 'script-wasm';} // wasm 引入; "*.wasm" 将被自动替换;
			if(replaceType){result += '&replaceType='+replaceType;}
			return result += '&path='+urlEncode(filePath)+"&add="+urlEncode(url)+paramAdd;
		};
		var domSrcMap = [
			{tag:'script',key:'src',replaceType:'script-import',typeMatch:'module'},
			{tag:'link',key:'href',replaceType:'css-import'},
			{tag:'img',key:'src',cros:true,name:'image'},
			{tag:'source',key:'src',cros:true},
			{tag:'video',key:'src',cros:true},
			{tag:'audio',key:'src',cros:true}
		];
		var $dom = $((new DOMParser()).parseFromString(content,'text/html')); // 忽略报错
		var basePath = $dom.find('base').attr('href') || '';// 相对路径处理(跳转,引用都需要追加)
		var contentNew = this.htmlContentParse(content,domSrcMap,urlFilterCurrent);

		// html内容展示,src相对路径处理(url处理: './test.js','test.js','../img/test.js');
		var script = `
		(function(){
			var linkPre   = "${linkPre}",filePath ="${filePath}",basePath="${basePath}";
			window._hash  = "`+(addPath || '')+`";window._location_ = `+JSON.stringify(window.location)+`
			var domSrcMap =`+jsonEncode(domSrcMap)+`;
			var urlFilter =`+this.urlFilter.toString()+`;
			var isPathUrl =`+this.isPathUrl.toString()+`;
			var pathUrlParse =`+this.pathUrlParse.toString()+`;
			var htmlContentParse =`+this.htmlContentParse.toString()+`;
			(`+this.hookScript.toString()+`)();
			(`+this.hookScriptProfill.toString()+`)();
			(`+this.hookScriptEvent.toString()+`)();
		})();`;
		return '<script>'+script+'</script>'+contentNew;
	},

	// ================================================================
	// ====================以下为运行在iframe中的代码====================
	// ================================================================
	
	// 是否为绝对路径url(http:,https:,data:,blob:,#,/,javascript:), 其他则为相对路径url(../xxx,./xxx,xxx/a.html);
	isPathUrl:function(url){
		if(!url || typeof(url) != 'string'){return false;}
		var ignorePre = ['http:','https:','/','data:','blob:','#','javascript:'];
		for(var i = 0; i < ignorePre.length; i++){
			if(url.substr(0,ignorePre[i].length) == ignorePre[i]){return false;}
		}
		return true;
	},
	pathUrlParse:function(url,_basePath){ // 如果是路径,去除锚点及参数;
		if(url=='./'){url = './index.html';}
		if(url.substr(0,1) == '?'){url = './index.html'+url;}
			
		if(url.indexOf('?') >0){url = url.substr(0,url.indexOf('?'));}
		if(url.indexOf('#') >0){url = url.substr(0,url.indexOf('#'));}
		if(url.substr(-1,1) == '/'){url += 'index.html';} // 目录结尾自动处理为文件;
		url = url.replace(/\/+/g,'/');
		if(_basePath){url = _basePath + url;}
		return url;
	},
	urlFilter:function(url,replaceType,_isPathUrl,_pathUrlParse){
		// console.error(101,url,document.baseURI)
		var paramAdd = '',urlOld = url;// 保持之前参数;
		if( url && url.substr(0,4) == 'http' && 
			url.indexOf('&_s_=/') > 0 && url.indexOf('&_s_=/&') === -1){ // 根据url拼接出的url处理;
			url = url.substr(url.indexOf('&_s_=/') + '&_s_=/'.length);
		}
		if(!isPathUrl(url)){return url;}
		if(url.indexOf('?') > 0 && url.substr(0,4) != 'http'){paramAdd = '&_s_=/&'+url.substr(url.indexOf('?')+1);}
		if(url.indexOf('#') > 0 && url.substr(0,4) != 'http'){paramAdd = '#'+url.substr(url.indexOf('#')+1);}
		
		url = pathUrlParse(url,basePath);
		var result = linkPre;
		if(url.indexOf('.wasm.js') != -1){replaceType = 'script-wasm';} // wasm 引入; "*.wasm" 将被自动替换;
		if(replaceType){result += '&replaceType='+replaceType;}
		result += '&path='+encodeURIComponent(filePath)+"&add="+encodeURIComponent(url)+paramAdd;
		// console.error('urlFilter',[urlOld,url,result,paramAdd]);
		return result;
	},
	htmlContentParse:function(html,domSrcMap,_urlFilter){
		var contentNew = html; // 匹配字符串后替换;
		var doc = (new DOMParser()).parseFromString(html,'text/html'); // 忽略报错
		if(!doc || !doc.getElementsByTagName){return contentNew;}
		
		var escapeRegex = function(str){return str.replace(/[/\-\\^$*+?.()|[\]{}]/g, '\\$&');}
		domSrcMap.forEach(function(item){		
			var nodes = doc.getElementsByTagName(item.tag);
			if(!nodes || !nodes.length){return;}

			var nodeArr = Array.from(nodes);
			nodeArr.forEach(function(node){
				var url = node.getAttribute(item.key);
				if(!url){return;}
				var replaceType = item.replaceType || '';
				if(item.typeMatch){replaceType = node.getAttribute('type') == 'module' ? replaceType:'';}
				var urlNew = _urlFilter(url,replaceType);
				if(url == urlNew){return;}
				var reg = new RegExp(item.key+'\s*=\s*[\'"]'+escapeRegex(url)+'[\'"]','g');
				contentNew = contentNew.replace(reg,item.key+'="'+urlNew+'" _src_="'+url+'"');
				// if(item.cros){node.setAttribute("crossOrigin",'anonymous');}
			});
		});

		var getElementsByAttribute = function(attr,value,fromNode,result){
			if(!result){result = [];}
			if(fromNode && fromNode.hasAttribute && fromNode.hasAttribute(attr) && 
			   (value === false || fromNode.getAttribute(attr) == value)
			){result.push(fromNode);}
			if(!fromNode || !fromNode.children){return result;}
			
			var children = fromNode.children;
			for(var i = children.length; i--;){
				getElementsByAttribute(attr,value,children[i],result);
			}
			return result;
		};
		
		// html中iframe提前替换; 避免首次载入url错误的情况;
		var nodes = doc.getElementsByTagName('iframe');
		var nodeArr = Array.from(nodes);
		nodeArr.forEach(function(node){
			var url = node.getAttribute('src');
			if(!url || node.getAttribute('_src_')){return;}
			var reg = new RegExp('src\s*=\s*[\'"]'+escapeRegex(url)+'[\'"]','g');
			contentNew = contentNew.replace(reg,'src="" _src_="'+url+'"');
		});
		// console.error("htmlContentParse:",html,contentNew);
		
		// html中style background-image提前处理;
		var nodes = getElementsByAttribute('style',false,doc);
		nodes.forEach(function(node){
			var style = node.getAttribute('style');
			if(!style || !style.indexOf("url(") ){return;}			
			var styleNew = style.replace(/url\s*\(\s*(['"]*.*?['"]*)\s*\)/g,function(str,url){
				var q = url.substr(0,1);
				if(q != "'" && q != '"'){q = '';}
				if(q){url = url.substr(1,url.length -2);}
				return 'url('+q+_urlFilter(url)+q+')';
			});
			contentNew = contentNew.replace(style,styleNew);
		});
		return contentNew;
	},
	
	/**
	 * url相对路径引用
	 * 
	 * url处理(img.src,link.href,script.src; xhr,fetch; worker.src;worker中fetch; wasm.js)
	 * css处理: 图片引用处理,import支持处理
	 * js处理:  module/import支持;import内部再import自适应url;引入wasm处理;worker跨域处理;worker内部fetch url自适应处理;
	 * 
	 * 跳转处理: 支持[a点击,a设置href,window.open,reload];暂不支持[hash跳转,location.href(无法覆盖,无法setter)]
	 * iframe处理: 支持[内置;src,attr,插入html]; 仅支持两层;
	 * 不支持: window.location 系列处理(hash,href,pathname,replace; 浏览器做了限制;)
	 */
	hookScript:function(){
		// css中图片引用处理(运行时,js修改元素样式)
		var urlFilterImage = function(v){
			if(!v || !v.match(/url\s*\(\s*['"]*(.*?)['"]*\s*\)/g)){return v;}
			var result = v.replace(/url\s*\(\s*['"]*(.*?)['"]*\s*\)/g,function(str,url){
				if(!url){return str;}
				return 'url("'+urlFilter(url)+'")';
			});
			return result;
		};
		
		// import 'src.js'; import Stats from 'src.js';
		var urlFilterScriptImport = function(node){
			var html =  node.innerText || node.innerHTML;
			if(!html || html.indexOf('from') === -1){return;}
			var result = html.replace(/\s+from\s+(['"].*?['"])/g,function(str,url){
				var q = url.substr(0,1);
				return str.replace(url,q+urlFilter(url.substr(1,url.length-2),'script-import')+q);
			});
			result = result.replace(/import\s+(['"].*?['"])/g,function(str,url){
				var q = url.substr(0,1);
				return str.replace(url,q+urlFilter(url.substr(1,url.length-2),'script-import')+q);
			});
			node.innerHTML = result;
		};
		var urlFilterScriptImportMap = function(node){
			var html =  node.innerText || node.innerHTML;
			if(!html){return;}
			var result = html.replace(/['"]\s*:\s*(['"].*?['"])/g,function(str,url){
				var q = url.substr(0,1);
				return str.replace(url,q+urlFilter(url.substr(1,url.length-2),'script-import')+q);
			});
			node.innerHTML = result;
		};

		var domSrcMapObj = {};
		domSrcMap.forEach(function(item){domSrcMapObj[item.tag]  = item;});
		var nodeCheck = function(node){
			if(node.getAttribute && node.getAttribute('style')){ // style中图片处理;
				node.setAttribute('style',urlFilterImage(node.getAttribute('style')));
			}
			if(node.tagName == 'SCRIPT'){ // js代码中url引用处理;
				if(node.innerText){
					if(node.getAttribute('type') == 'module'){urlFilterScriptImport(node);}
					if(node.getAttribute('type') == 'importmap'){urlFilterScriptImportMap(node);}
				}else{
					setTimeout(function(){ // 节点添加过程中,未添加完成情况,内容获取为空的情况;
						if(node.getAttribute('type') == 'module'){urlFilterScriptImport(node);}
						if(node.getAttribute('type') == 'importmap'){urlFilterScriptImportMap(node);}
					},0);
				}
			}
			if(node.tagName == 'IFRAME'){
				iframeReset(node,['src',node.getAttribute('_src_') || node.getAttribute('src')]);
			}
			var mapItem = domSrcMapObj[(node.tagName || '').toLowerCase()];
			if(mapItem && node && node[mapItem.key]){
				var theUrl = node.getAttribute(mapItem.key);
				var newUrl = urlFilter(theUrl,mapItem.replaceType || '');;
				node[mapItem.key] = newUrl;
				if(node.tagName == 'SCRIPT' && theUrl != newUrl && !node.getAttribute('_src_')){
					node.setAttribute('_src_',theUrl); // 保留之前url; getter中重写;
				}
				if(mapItem.cros){node.crossOrigin = "anonymous";}
			}
			if(!node.childNodes || node.childNodes.length == 0){return;}
			node.childNodes.forEach(nodeCheck);// 子节点处理;通过html天假多层结构;
		};
		// dom对象创建监听(针对初始html内容, innerHTML修改内容等情况处理)
		var observer = new MutationObserver(function(mutationsList,mutationObserver){
			mutationsList.forEach(function(mutation){
				if(!mutation.addedNodes) return;
				mutation.addedNodes.forEach(nodeCheck);
			});
		});
		observer.observe(document,{childList:true,attributes:true,subtree:true});
		
		functionHookSetter = function(target,key,setterFunc,getterFunc){
			if(!window.Object || !Object.getOwnPropertyDescriptor){return;}
			var descriptor = Object.getOwnPropertyDescriptor(target,key);
			if(!descriptor){
				if(!target.setAttribute && !target.setProperty){console.error(this,key);return;}
				descriptor = {};
				if(setterFunc){
					descriptor.set = function(){
						var result = setterFunc.apply(this,arguments);
						if(result === false){return;} // 返回false不处理;
						if(this.setProperty){return this.setProperty(key,result[0]);}
						if(this.setAttribute){return this.setAttribute(key,result[0]);}
					};
				}
				if(getterFunc){
					descriptor.get = function(){
						var result = getterFunc.apply(this,arguments);
						if(result){return result;} //只要值,则作为返回值;
						if(this.getProperty){return this.getProperty(key,result[0]);}
						if(this.getAttribute){return this.getAttribute(key,result[0]);}
					};
				}
			}else{
				var _setter = descriptor["set"],_getter = descriptor["get"];
				if(setterFunc){
					descriptor["set"] = function(){
						var result = setterFunc.apply(this,arguments);
						if(result === false){return;} // 返回false不处理;
						return _setter.apply(this,result || arguments);
					}
				}
				if(getterFunc){
					descriptor["get"] = function(){
						var result = getterFunc.apply(this,arguments);
						if(result){return result;} //只要值,则作为返回值;
						return _getter.apply(this,arguments);
					}
				}
			}
			Object.defineProperty(target,key,descriptor);
		};
		functionHook = function(target,method,beforeFunc,afterFunc){
			if(!target || !method || !target[method]){return;}
			var _theMethod 	= target[method];
			target[method]  = function(){
				var args = arguments;
				if(beforeFunc){
					var newArgs = beforeFunc.apply(this,args);
					if( newArgs === false ) return newArgs;
					args = newArgs === undefined ? args : newArgs; 	//没有返回值则使用结果;
				}
				var result = _theMethod.apply(this,args);
				if( afterFunc ){
					var newResult = afterFunc.apply(this,[result,args]);
					result = newResult === undefined ? result : newResult;//没有返回值则使用结果
				}
				return result;
			}
		};

		domSrcMap.forEach(function(mapItem){
			var nodeName = mapItem.name || mapItem.tag;
			nodeName = nodeName.substr(0,1).toUpperCase() + nodeName.substr(1);
			var nodeElement = window['HTML'+nodeName+'Element'];
			if(!nodeElement){return;}

			functionHookSetter(nodeElement.prototype,mapItem.key,function(src){
				var replaceType = mapItem.replaceType || '';
				if(mapItem.typeMatch ){replaceType = this.getAttribute('type') == 'module' ? replaceType:'';}
				if(mapItem.cros){this.crossOrigin = "anonymous";}
				arguments[0] = urlFilter(arguments[0],replaceType);return arguments;
			},function(){
				// 获取src重写使用原始内容; // 兼容js文件内代码会通过document.currentScript获取到标签,获取url进行相关操作等;
				if(this.getAttribute('_src_')){return this.getAttribute('_src_');}
			});
		});		
		functionHookSetter(CSSStyleDeclaration.prototype,'background',function(src){
			arguments[0] = urlFilterImage(arguments[0]);return arguments;
		});
		functionHookSetter(CSSStyleDeclaration.prototype,'background-image',function(src){
			arguments[0] = urlFilterImage(arguments[0]);return arguments;
		});
		functionHook(CSSStyleDeclaration.prototype,'setProperty',function(k,v){
			arguments[1] = urlFilterImage(arguments[1]);return arguments;
		});
		functionHook(HTMLElement.prototype,'setAttribute',function(k,v){
			if(k == 'style'){arguments[1] = urlFilterImage(arguments[1]);return arguments;}
		});
		functionHookSetter(HTMLElement.prototype,'style',function(src){
			arguments[0] = urlFilterImage(arguments[0]);return arguments;
		});
		
		var has = function(str,find){
			if(!str || typeof(str) != 'string'){return false;}
			return str.indexOf(find) !== -1;
		};
		// 通过a标签获取url的情况支持;(aceEditor,worker; a标签设置js再获取url的情况)
		functionHookSetter(HTMLAnchorElement.prototype,'href',function(href){
			if(href && ( has(href,'.js') || has(href,'.css') )){
				arguments[0] = urlFilter(href);
			}
			return arguments;
		});
		
		// 通过a标签获取host等情况处理(angular,载入html白名单处理情况)
		var linkGetterHost = 'protocol,host,port,hostname'.split(',');
		linkGetterHost.forEach(function(key){
			functionHookSetter(HTMLAnchorElement.prototype,key,false,function(){
				if(!this.parentNode){return _location_[key];} // 如果a节点未添加到dom中,则使用默认url
			});
		});
		
		// iframe 处理(仅处理相对路径引用的文件; 当前页面发出消息(带上uuid,获取内容及路径信息; kod页面进行处理)
		functionHook(HTMLIFrameElement.prototype,'setAttribute',function(k,v){
			if(k == 'src'){arguments[1] = iframeReset(this,arguments);return arguments;}
		});
		functionHookSetter(HTMLIFrameElement.prototype,'src',function(src){
			arguments[0] = iframeReset(this,['src',src]);return arguments;
		});

		var iframeReset = function(node,args){
			if(!isPathUrl(args[1]) || args[1] == 'blank.html'){return args[1];}

			var url = pathUrlParse(args[1],basePath);
			node.setAttribute('_view_src',url);
			var uuid = node.getAttribute('_view_uuid');
			if(!uuid){
				uuid = Math.ceil(Math.random()*1000000);
				node.setAttribute('_view_uuid',uuid);
			}
			window.parent.postMessage({type:'iframe.event',event:'iframeChildLoad',url:url,uuid:uuid},'*');
			return '';
		};
		
		var _Worker = window.Worker,_SharedWorker = window.SharedWorker,_Audio = window.Audio;
		var requestText = function(url,callback){
			fetch(url).then(function(response){
				return response.text();
			}).then(function(text){
				callback && callback(text);
			});
		};
		var noop = function(){};
		// worker 请求中 fetch处理;
		var requestWorker = function(url,type,name){
			var url  = urlFilter(url,'script-import');
			var obj  = {onmessage:noop,postMessage:noop,onerror:noop,terminate:noop,addEventListener:noop};
			// return type == 'Worker'? (new _Worker(url)) : new _SharedWorker(url,name);
			
			var scriptAdd = `
			;(function(){
				var linkPre   = "${linkPre}",filePath ="${filePath}",basePath="${basePath}";
				var _location = `+JSON.stringify(_location_)+`
				var urlFilter =`+urlFilter.toString()+`;
				var isPathUrl =`+isPathUrl.toString()+`;
				var pathUrlParse =`+pathUrlParse.toString()+`;
				var htmlContentParse =`+htmlContentParse.toString()+`;
				var _fetch   = self.fetch,_ajaxOpen = XMLHttpRequest.prototype.open;
				self.fetch 	 = function(url){
					arguments[0] = urlFilter(arguments[0]);
					return _fetch.apply(this,arguments);
				};
				XMLHttpRequest.prototype.open = function(){
					arguments[1] = urlFilter(arguments[1]);
					return _ajaxOpen.apply(this,arguments);
				}
			})();`;
			requestText(urlFilter(url,'script-import'),function(text){
				var worker = false;
				var blob   = new Blob([scriptAdd+text],{type:'application/javascript'});
				var blobUrl = URL.createObjectURL(blob);
				// console.error('requestWorker:',blobUrl,url,text,scriptAdd);
				if(type == 'Worker'){worker = new _Worker(blobUrl);}
				if(type == 'SharedWorker'){worker = new _SharedWorker(blobUrl,name);}
				worker.onmessage = obj.onmessage;worker.onerror = obj.onerror; // 设置调用覆盖临时变量
				obj.postMessage  = function(){worker.postMessage.apply(worker,arguments);};
				obj.terminate 	 = function(){worker.terminate.apply(worker,arguments);};
				obj.addEventListener = function(){worker.addEventListener.apply(worker,arguments);};
			});
			return obj;//返回临时变量,worker构造后需要覆盖;
		};		
		window.Worker = function(url){return requestWorker(url,'Worker');};
		window.SharedWorker = function(url){return requestWorker(url,'SharedWorker',name);};
		window.Audio = function(url){
			var node = new _Audio(urlFilter(url));
			node.setAttribute("crossOrigin",'anonymous');
			return node;
		}
		
		var _fetch = window.fetch,_ajaxOpen = XMLHttpRequest.prototype.open;
		window.fetch = function(url){
		    arguments[0] = urlFilter(arguments[0]);
		    return _fetch.apply(this,arguments);
		};
		// chrome xhr跨域options目录预检处理;需要带上index.php
		XMLHttpRequest.prototype.open = function(){
			arguments[1] = urlFilter(arguments[1]);
			if(arguments[1] === _location_.href){// 获取url位自身时,采用当前文件名(document.baseURI的情况兼容)
				arguments[1] = linkPre + '&path='+encodeURIComponent(filePath+'/t.html')+'&add='; // 需要到上层;
			}
			
		    var result = _ajaxOpen.apply(this,arguments);
			this.withCredentials = false;
			return result;
		},

		// functionHookSetter(document,'baseURI',false,function(){return filePath;});		
		functionHook(document,'write',function(code){
			arguments[0] = htmlContentParse(code,domSrcMap,urlFilter);return arguments;
		});
		// 阻止事件冒泡调用标记处理;
		functionHook(UIEvent.prototype,'stopPropagation',function(code){
			this._stopEvent = true;return arguments;
		});
		functionHook(UIEvent.prototype,'preventDefault',function(code){
			this._stopEvent = true;return arguments;
		});
	},
	
	hookScriptProfill:function(){
		// 避免cookie,localStorage读写报错  history  replaceState
		Object.defineProperty(document,"cookie",{
			set:function(v){return true;},
			get:function(){return '';},
		});
		var noop = function(){};
		var _localData = {getItem:noop,setItem:noop,clear:noop,removeItem:noop,length:0};
		Object.defineProperty(window,"localStorage",{
			get: function(){return _localData;},
		});
		Object.defineProperty(window,"sessionStorage",{
			get: function(){return _localData;},
		});
		Object.defineProperty(window,"caches",{
			get: function(){return {'delete':noop,has:noop,keys:noop,match:noop,open:noop};},
		});

		// sandbox中不可用,直接禁用;
		Object.defineProperty(window,"indexedDB",{
			get:function(){return undefined;},
		});		
		window.history.replaceState = function(){};
	},
	hookScriptEvent:function(){
		window.addEventListener('mousedown',function(){
			window.parent.postMessage({type:'iframe.event',event:'mousedown'},'*');
		});
		window.addEventListener('mouseup',function(){
			window.parent.postMessage({type:'iframe.event',event:'mouseup'},'*');
		});
		window.addEventListener('message',function(e){
			if(!e || !e.data || e.data.type != 'parent.event'){return;}
			// 当前窗口收到kod窗口内容处理完成消息后处理;
			
			// console.error('iframe-message',e);
			if(e.data.event == 'iframeChildLoadSuccess'){
				var iframe = document.querySelector('[_view_uuid="'+e.data.uuid+'"]');
				if(!iframe){return;}
				iframe.setAttribute('data-file-path',e.data.url);
				iframe.src="";iframe.srcdoc=e.data.content;
			}
		});
		
		// url跳转拦截(a跳转,a.href修改; window.open; window.location.href==无法支持,无法setter)
		var gotoPage = function(url){
			if(url && url.substr(0,1)== '#'){window.location.hash = url;return true;} 
			if(!isPathUrl(url)){return false;}
			url = pathUrlParse(url,basePath);
			window.parent.postMessage({type:'iframe.event',event:'iframeLinkHref',url:url},'*');
			return true;
		}
		var stopPP = function(e){
			var evt = e || window.event;
			if(!evt){return;}
			if(evt.preventDefault){evt.preventDefault();}
			evt.returnValue = true;
		};
		var parentNodeFind = function(node,tag){
			if(!node || !node.parentNode || node == window || node == document){return false;}
			if(node.nodeName == tag){return node;}
			return parentNodeFind(node.parentNode,tag);
		};
		window.addEventListener('click',function(e){
			var node = parentNodeFind(e.target,'A');
			if(!node){return;}
			if(e && e._stopEvent){return stopPP(e);} // 已经在其他处阻止;
			
			var url = node.getAttribute("href");
			var targetType = node.getAttribute('target');
			var iframes = document.getElementsByName(targetType);
			if(iframes.length >= 1){// target到某个ifram存在时不处理;
				iframes[0].src = url;
				return stopPP(e);
			}
			if(gotoPage(url)){return stopPP(e);};
		});
		
		// location 跳转监听,暂时无解; //onhashchange,popstate,locationchange...
		// https://stackoverflow.com/questions/6390341/how-to-detect-if-url-has-changed-after-hash-in-javascript
		// window.addEventListener('popstate',function(e){});
		var _open = window.open;
		window.open = function(url){
			if(gotoPage(url)){return;};
			return _open.apply(window,arguments);
		}
		// window.location.pathname = window._hash;
		window.location.origin = (new URL(linkPre)).origin; //无效; window.orgin依然为null; setter无效;
	},
});