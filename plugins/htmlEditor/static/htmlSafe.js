ClassBase.define({
	init:function(){
		this.bindMessage();
		this.iframeUrlBase 	=  G.kod.APP_HOST + 'plugins/htmlEditor/static/';
		this.iframeProxy 	=  this.iframeUrlBase+'iframe-proxy.html';
		this.allowSameOrgin = false;// 是否允许同源; 开启后iframe同源,能获取到parent或top页面内容-安全问题; 关闭后问题:静态资源无法缓存;		
		this.allowSrcdoc 	= true;	// 是否启用srcdoc方式加载;
		
		if(window.kodHasNetWork){// 有网络时,使用cdn上的代理iframe实现跨域 (静态资源缓存/iframe子内容支持)
			this.allowSameOrgin = true;
			this.allowSrcdoc 	= false;
			this.iframeUrlBase  = $.parseUrl().protocol + '://static.kodcloud.com/update/plugins/app/htmlEditor/static/';
			this.iframeProxy 	=  this.iframeUrlBase+'iframe-proxy.html';
		}
		// this.iframeUrlBase =  G.kod.APP_HOST;
		// this.iframeProxy   =  G.kod.appApi + 'plugin/htmlEditor/iframe';
		
		if($.browserIS.ios){// ios webview中不支持;Safari支持;
			var ua = navigator.userAgent.toLowerCase();
			if(ua.indexOf('quark') || ua.indexOf('weixin')){this.allowSrcdoc = false;}
		}
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
		if(!window.fetch || !window.MutationObserver || !window.postMessage){return false;}
		var frame = document.createElement("iframe");
		if(!("sandbox" in frame) || !("srcdoc" in frame)){return false;}
		return true;
	},
	// 禁用cookie,去除 allow-same-origin;  iframe内请求无法缓存;
	// https://stackoverflow.com/questions/67680940/iframe-sandbox-is-not-caching-my-js-script
	iframeAttr:function(){
		var allow   = "midi; geolocation; camera *; microphone; camera; display-capture; encrypted-media; clipboard-read; clipboard-write;";
		var sandbox = 'allow-modals allow-orientation-lock allow-forms allow-scripts allow-popups allow-pointer-lock allow-downloads';
		if(this.allowSameOrgin){sandbox += ' allow-same-origin';}
		return "sandbox='"+sandbox+"' allow='"+allow+"' allowfullscreen allowpaymentrequest";//移出 allow-same-origin
	},
	iframe:function(){
		return '<iframe src="" '+this.iframeAttr()+' frameborder="0" style="width:100%;height:100%;border:0;"></iframe>';
	},
	
	getTruePathCache:{},
	fileContentCache:{},
	loadContent:function($iframe,filePath,pathModel,contentSet,args){
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
		
		var cacheKeyAll = '';// 缓存处理,文件变更时支持;
		if(args && args._fileInfo && args._fileInfo.size){
			cacheKeyAll = '[size-'+args._fileInfo.size+',time='+args._fileInfo.modifyTime+']';
		}
		var getTruePath = function(thePath,addPath,callback){
			var cache = self.getTruePathCache,cacheKey = cacheKeyAll+thePath+'__'+(addPath || '');
			if(cache[cacheKey]){return callback && callback(cache[cacheKey],addPath);}
			
			$.ajax({
				url:pathModel.urlMake('fileOutBy'),
				type:'POST',dateType:'json',
				data:{path:thePath,add:addPath,type:'getTruePath'},
				success:function(data){
					if(!_.isObject(data) || !data.code || !data.data){return;}
					cache[cacheKey] = data.data;
					callback && callback(data.data,addPath);
				}
			});
		};
		var fileContent = function(thePath,addPath,callback){
			var cache = self.fileContentCache,cacheKey = cacheKeyAll+'__'+thePath+'__'+(addPath || '');
			if(cache[cacheKey]){return callback && callback(cache[cacheKey]);}
			pathModel.fileContent({path:thePath,pageNum:1024*1024*50},function(content){
				var newContent = self.parseContent(content,thePath,linkPre,addPath);
				cache[cacheKey] = newContent;
				callback && callback(newContent);
			});
		}
		
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
			var iframeWindow = $iframe.get(0).contentWindow;
			if(!thePath || !iframeWindow){return;}
			
			getTruePath(thePath,addPath,function(truePath){
				fileContent(truePath,addPath,function(content){
					iframeWindow.postMessage({
						type:'parent.event',event:'iframeChildLoadSuccess',
						url:truePath,uuid:eventData.uuid,content:content
					},'*');
				});
			});
		});
		var reloadContent = function(thePath,addPath,theContent){
			$iframe.attr('data-file-path',thePath);
			var setContent = function(newContent){
				self.iframeContent($iframe,newContent);
			};
			setContent('');//$iframe.attr('src','about:blank');
			if(theContent){return setContent(self.parseContent(contentSet,thePath,linkPre,addPath));}
			fileContent(thePath,addPath,setContent);
		};
		reloadContent(filePath,false,contentSet);
	},

	iframeContent:function($iframe,content){
		if(!this.allowSrcdoc){
			if(!content){return;}
			$iframe.attr('src',this.iframeProxy).trigger('load');
			$iframe.unbind('iframeProxyLoad').bind('iframeProxyLoad',function(){
				var iframeWindow = $iframe.get(0).contentWindow;
				iframeWindow.postMessage({type:'render',content:content},'*');
			});
			return;
		}
		
		var self = this;
		$iframe.attr('srcdoc',content).trigger('load'); // iOS-微信中无效;
		$iframe.unbind('iframeMainload').bind('iframeMainload',function(){
			clearTimeout(self.loadTimeout);
		});
		// 一段时间未接收到载入消息,则重新设置值(safari下偶尔白屏[未载入引起])
		clearTimeout(self.loadTimeout);
		self.loadTimeout = setTimeout(function(){$iframe.attr('srcdoc',content);},500);
	},
	
	parseContent:function(content,filePath,linkPre,addPath){
		var isPathUrl = this.isPathUrl,pathUrlParse = this.pathUrlParse,pathTrue = this.pathTrue;
		var iframeUrlBase = this.iframeUrlBase,isPathUrlRoot = this.isPathUrlRoot;
		var urlFilterCurrent = eval('('+this.urlFilter.toString()+')'); // 当前环境作用域; 可以直接使用变量及方法;
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
		var urlPage = window.location.href.replace(/\?.*/,'').replace(/#.*/,'') + (addPath || '');
		var locationNow = new window.URL(urlPage); // 保留打开页面的锚点及参数; js中可能会作为逻辑处理用到
		locationNow = _.pick(locationNow,'href,origin,protocol,host,hostname,port,pathname,search,hash'.split(','));
		// console.log(999,{linkPre,filePath,basePath,addPath,location,contentNew},{urlPage,locationNow},JSON.stringify(locationNow));

		var fileEncode = '';
		if(window.kodFileOutDecode){ // 加解密处理;
			//fileEncode = ';window.kodFileOutDecode = ('+kodFileOutDecode.toString()+');window.kodFileOutDecode();';
		}
		var script = `
		(function(){
			var linkPre   = "${linkPre}",filePath ="${filePath}",basePath="${basePath}",iframeUrlBase="${this.iframeUrlBase}";
			window._hash  = "`+(addPath || '')+`";window._location_ = `+JSON.stringify(locationNow)+`;_location_._href = _location_.href;
			var domSrcMap =`+jsonEncode(domSrcMap)+`;
			var isPathUrlRoot =`+this.isPathUrlRoot.toString()+`;
			var urlFilter =`+this.urlFilter.toString()+`;
			var isPathUrl =`+this.isPathUrl.toString()+`;
			var pathTrue  =`+this.pathTrue.toString()+`;
			var pathUrlParse =`+this.pathUrlParse.toString()+`;
			var htmlContentParse =`+this.htmlContentParse.toString()+`;
			(`+this.hookScript.toString()+`)();
			(`+this.hookScriptProfill.toString()+`)();
			(`+this.hookScriptEvent.toString()+`)();`+fileEncode+`
		})();`;
		return `<!doctype html><script>`+script+'</script>'+contentNew;
	},

	// ================================================================
	// ====================以下为运行在iframe中的代码====================
	// ================================================================
	
	// 是否为绝对路径url(http:,https:,data:,blob:,#,/,javascript:), 其他则为相对路径url(../xxx,./xxx,xxx/a.html,/aa/bb.xxx);
	isPathUrl:function(url){
		if(!url || typeof(url) != 'string'){return false;}
		var ignorePre = ['http:','https:','/','data:','blob:','#','javascript:'];
		for(var i = 0; i < ignorePre.length; i++){
			if(url.substr(0,ignorePre[i].length) == ignorePre[i]){return false;}
		}
		return true;
	},
	isPathUrlRoot:function(url){
		if(!url || typeof(url) != 'string'){return false;}
		if(url.substr(0,1) != '/'){return false;}
		return (new URL(linkPre)).origin + url;
	},
	pathUrlParse:function(url,_basePath){ // 如果是路径,去除锚点及参数;
		var _url = url;
		if(url=='./'){url = './index.html';}
		var urlClear = function(theUrl){
			if(theUrl.indexOf('?') >0){theUrl = theUrl.substr(0,theUrl.indexOf('?'));}
			if(theUrl.indexOf('#') >0){theUrl = theUrl.substr(0,theUrl.indexOf('#'));}
			return theUrl.replace('/./','/');
		};
		
		if(url.substr(0,1) == '?'){
			url = './index.html'+url;
			if(window._hash){
				var arr = urlClear(_hash).split('/');
				url = './'+arr[arr.length - 1] + _url;
			}
		}
		//url = urlClear(url);
		if(url.substr(-1,1) == '/'){url += 'index.html';} // 目录结尾自动处理为文件;
		url = url.replace(/\/+/g,'/');
		if(_basePath){url = _basePath + url;}
		return url;
	},
	pathTrue:function(thePath){
		if(!thePath || typeof(thePath) != 'string') return '';
		thePath = thePath.replace(/\/+/g,'/').replace(/(\/\.\/)+/g,'/');
		thePath = thePath.replace(/\/+/g,'/').replace(/(\/\.\/)+/g,'/');
		if(thePath.indexOf('../') === -1 ) return thePath;
		
		var arr = thePath.split('/');
		for(var i = 0; i <= arr.length; i++){
			if(arr[i] !== '..'){continue;}
			for(var j = i; j>=0;j--) { 
				if(arr[j] === '.' || arr[j] === '..' || arr[j] === -1){continue;}
				if(arr[j] === ''){arr[i] = -1;break;} // 以/开头处理到根,则直接置空
				arr[i] = -1;arr[j] = -1;break; // 抵消一对;
			}
		}
		var newPathArr = [];
		for(var i = 0; i < arr.length; i++){
			if(arr[i] === -1){continue;}
			newPathArr.push(arr[i]);	
		}
		var newPath = newPathArr.join('/');
		if(newPath.indexOf('./../') === 0){
			newPath = '../'+newPath.substr('./../'.length);
		}
		return newPath.replace('/./','/');
	},
	urlFilter:function(url,replaceType){
		if(!url || typeof(url) != 'string'){return url;}
		if(url.substr(0,iframeUrlBase.length) == iframeUrlBase){
			url = url.substr(iframeUrlBase.length);
		}
		var paramAdd = '',urlOld = url;// 保持之前参数;
		if( url && url.substr(0,4) == 'http' && 
			url.indexOf('&_s_=/') > 0 && url.indexOf('&_s_=/&') === -1){ // 根据url拼接出的url处理;
			url = url.substr(url.indexOf('&_s_=/') + '&_s_=/'.length);
		}
		if(isPathUrlRoot(url)){return isPathUrlRoot(url);}
		if(!isPathUrl(url)){return url;}
		if(url.indexOf('?') > 0 && url.substr(0,4) != 'http'){paramAdd = '&_s_=/&'+url.substr(url.indexOf('?')+1);}
		if(url.indexOf('#') > 0 && url.substr(0,4) != 'http'){paramAdd = '#'+url.substr(url.indexOf('#')+1);}
		
		url = pathUrlParse(url,basePath);
		var result = linkPre;
		if(url.indexOf('.wasm.js') != -1){replaceType = 'script-wasm';} // wasm 引入; "*.wasm" 将被自动替换;
		if(replaceType){result += '&replaceType='+replaceType;}

		var urlPath = filePath,urlAdd = url;
		// 资源引用路径,相对路径转换; 多个相同资源共同缓存(暂时无效; iframe中allow-same-origin请求无法缓存, orgin为null)
		var urlPathArr = urlPath.replace(/\/*$/,'').replace(/^\/*/,'').split('/');
		var urlAddArr  = (urlAdd || '').split('../');
		if( urlAddArr.length && urlPathArr.length && urlPathArr.length > urlAddArr.length ){
			var urlAddArr = urlAdd.split('/'),lastFile = urlAddArr.pop();
			urlPath = pathTrue(urlPath + '/../' + urlAddArr.join('/')) + '/' + lastFile;
			urlAdd  = lastFile;
		}
		result += '&path='+encodeURIComponent(urlPath)+"&add="+encodeURIComponent(urlAdd)+paramAdd;
		// console.error('urlFilter',[urlPath,urlAdd],[urlOld,url,result,paramAdd]);
		return result;
	},
	htmlContentParse:function(html,domSrcMap,_urlFilter){
		var contentNew = html; // 匹配字符串后替换;
		var doc = (new DOMParser()).parseFromString(html,'text/html'); // 忽略报错
		if(!doc || !doc.getElementsByTagName){return contentNew;}
		
		var urlFilter = _urlFilter;
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
				var urlNew = urlFilter(url,replaceType);
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
				return 'url('+q+urlFilter(url)+q+')';
			});
			contentNew = contentNew.replace(style,styleNew);
		});
		
		// contentNew = contentNew.replace('window.location','window._location_');		
		contentNew = contentNew.replace(/(^|[^\w_.])window\.location($|[^\w_])/g,"$1window._location_$2");
		contentNew = contentNew.replace(/(^|[^\w_.])location\.(hash|host|hostname|href|orgin|pathname|port|protocol|reload|replace|search)($|[^\w_])/g,"$1_location_.$2$3");
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
			if(!v || typeof(v) != 'string'){return v;}
			if(!v.match(/url\s*\(\s*['"]*(.*?)['"]*\s*\)/g)){return v;}
			var result = v.replace(/url\s*\(\s*['"]*(.*?)['"]*\s*\)/g,function(str,url){
				if(!url){return str;}
				return 'url("'+urlFilter(url)+'")';
			});
			return result;
		};
		
		var importMap = {};
		var scriptImport = function(str,url,mapKey){
			var q = url.substr(0,1),urlFile = url.substr(1,url.length-2);
			if(mapKey){importMap[mapKey.substr(1,mapKey.length-2)] = urlFile;}
			
			// 不是src路径则不处理;importMap中已引入则
			if(importMap[urlFile]){return str;}
			return str.replace(url,q+urlFilter(urlFile,'script-import')+q);
		};
		
		// import 'src.js'; import Stats from 'src.js';
		var urlFilterScriptImport = function(node){
			var html =  node.innerText || node.innerHTML,result = html;
			if(!html || html.indexOf('from') === -1){return;}
			
			//分别处理单引号和双引号;避免dataUrl中引号导致提前截断问题;
			result = result.replace(/\s+from\s+('.*?')/g,function(str,url){return scriptImport(str,url);});
			result = result.replace(/\s+from\s+(".*?")/g,function(str,url){return scriptImport(str,url);});
			result = result.replace(/import\s+('.*?')/g,function(str,url){return scriptImport(str,url);});
			result = result.replace(/import\s+(".*?")/g,function(str,url){return scriptImport(str,url);});
			node.innerHTML = result;
		};
		var urlFilterScriptImportMap = function(node){
			var html =  node.innerText || node.innerHTML,result = html;
			if(!html){return;}
			result = result.replace(/(['"].*?['"])\s*:\s*('.*?')/g,function(str,mapKey,url){return scriptImport(str,url,mapKey);});
			result = result.replace(/(['"].*?['"])\s*:\s*(".*?")/g,function(str,mapKey,url){return scriptImport(str,url,mapKey);});
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
				var newUrl = urlFilter(theUrl,mapItem.replaceType || '');
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
						if(this.getProperty){return this.getProperty(key);}
						if(this.getAttribute){return this.getAttribute(key);}
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
			descriptor.configurable = true;
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
			if(isPathUrlRoot(args[1])){return isPathUrlRoot(args[1]);}
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
		
		var requestText = function(url,callback){
			fetch(url).then(function(response){
				return response.text();
			}).then(function(text){
				callback && callback(text);
			});
		};
		var noop = function(){};
		
		// worker 请求中 fetch处理;
		var proxyWorker = function(url,type,name){
			var url = urlFilter(url,'script-import');
			var proxyMethods = 'addEventListener,removeEventListener,postMessage,terminate'.split(','),proxyMethodsCall = {};
			var obj = {onmessage:noop,onerror:noop};
			var _each = function(objs,method){
				if(!objs){return;}
				for(var k in objs){
					(function(key){method(objs[key],key);})(k);
				}
			};
			var _includes = function(arr,v){return arr.indexOf(v) !== -1;};
			var _toArray = function(arr){
				var res = [];
				for(var i = 0; i < arr.length; i++){res.push(arr[i]);}
				return res;
			};
			_each(proxyMethods,function(funcName){
				obj[funcName] = function(){
					var args = _toArray(arguments);
					//console.error(222,funcName,obj.self,args,proxyMethodsCall);
					if(obj.self){return obj.self[funcName].apply(obj.self,args);}
					if(!proxyMethodsCall[funcName]){proxyMethodsCall[funcName] = [];}
					proxyMethodsCall[funcName].push(args);
				}
			});
			var loadAfter = function(objTarget){
				obj.self = objTarget;
				objTarget.onmessage = obj.onmessage;
				objTarget.onerror = obj.onerror; // 设置调用覆盖临时变量
				_each(proxyMethodsCall,function(calls,method){
					_each(calls,function(args){
						obj[method].apply(obj.self,args);
					});
				});
				proxyMethodsCall = [];
			};
			var scriptAdd = `
			;(function(){
				_location_ = `+JSON.stringify(_location_)+`;
				_location_.reload = function(){window.location.reload();};
				var linkPre   = "${linkPre}",filePath ="${filePath}",basePath="${basePath}",iframeUrlBase="${iframeUrlBase}";
				var isPathUrlRoot =`+isPathUrlRoot.toString()+`;
				var urlFilter = `+urlFilter.toString()+`;
				var isPathUrl = `+isPathUrl.toString()+`;				
				var pathTrue  = `+pathTrue.toString()+`;
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
				// console.error('proxyWorker:',blobUrl,url,text,scriptAdd+text);
				if(type == 'Worker'){worker = new _Worker(blobUrl);}
				if(type == 'SharedWorker'){worker = new _SharedWorker(blobUrl,name);}
				loadAfter(worker);
			});
			return obj;//返回临时变量,worker构造后需要覆盖;
		};
		var _Worker = window.Worker;window.Worker = function(url){return proxyWorker(url,'Worker');};
		var _SharedWorker = window.SharedWorker;window.SharedWorker = function(url,name){return proxyWorker(url,'SharedWorker',name);};
		var _Audio = window.Audio;window.Audio = function(url){
			var node = new _Audio(urlFilter(url));
			node.setAttribute("crossOrigin",'anonymous');
			return node;
		}
		
		var _fetch = window.fetch; window.fetch = function(url){
			if(typeof(url) == 'string' || !url){arguments[0] = urlFilter(url);} // theUrl 支持字符串,URL对象,Request对象
			if(url instanceof window.URL){arguments[0] = new URL(urlFilter(url.href));}
			var options  = {method:url.method,mode:url.mode,cache:url.cache,headers:url.headers,body:url.body,credentials:url.credentials};
			if(url instanceof window.Request){
				arguments[0] = new Request(urlFilter(url.url),options);
			}
		    return _fetch.apply(this,arguments);
		};
		
		// chrome xhr跨域options目录预检处理;需要带上index.php
		var _ajaxOpen = XMLHttpRequest.prototype.open;
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
		
		// 引用路径转换处理; 相对路径转换;
		var pathUrlTrue = function(url,add){
			if(add && isPathUrlRoot(add)){return isPathUrlRoot(add);}
			if(add && !isPathUrl(add)){return add;}
			if(!url || !add){return url;}
			var char = add.substr(0,1);
			if(char == '/'){return _location_.origin + add;}
			if(char == '#' || char == '?'){return url+add;}
			
			var urlTemp = url.replace('://','_:@@_');
			if(url.substr(-1,1) == '/'){urlTemp += 'index.html/';}
			var result = pathTrue(urlTemp+'/../'+add).replace('_:@@_','://');
			return result;
		};
		
		var urlMain = _location_.href.replace(/#.*$/, "" );
		var urlPage = pathUrlTrue(urlMain,_hash);
		// console.log(444,urlPage,urlPage);
		
		// 兼容 获取window.location.href无法覆盖的情况; 扩展到字符串替换上(jquery-ui等tab锚点处理兼容)
		functionHook(String.prototype,'replace',false,function(result,args){
			if(result === 'about:srcdoc'){return urlMain;}
			return result;
		});
		Object.defineProperty(document,'URL',{get:function(){
			return urlPage;
		}});
		functionHookSetter(HTMLAnchorElement.prototype,'href',false,function(){
			var url = this.getAttribute('href') || '';
			if(url.substr(0,1) == '#' || url.substr(0,1) == '?'){return urlPage+url;}
			return pathUrlTrue(urlPage,this.getAttribute('href'));
		});
	},
	
	hookScriptProfill:function(){
		// 避免cookie,localStorage读写报错  history  replaceState
		Object.defineProperty(document,"cookie",{
			set:function(v){return true;},
			get:function(){return '';},
		});
		var noop = function(){};
		var _localData = {getItem:noop,setItem:noop,clear:noop,removeItem:noop,length:0,getObject:noop,setObject:noop};
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
				var doc = iframe.contentDocument;iframe.src="";
				if(doc){doc.open();doc.write(e.data.content || '');doc.close();}
				if(!doc){iframe.srcdoc=e.data.content;}
			}
		});
		
		// url跳转拦截(a跳转,a.href修改; window.open; window.location.href==无法支持,无法setter)
		var gotoPage = function(url){
			var urlOld = url;
			if(url && url.substr(0,1)== '#'){window.location.hash = url;return true;} 
			if(isPathUrlRoot(url)){window.location.href = isPathUrlRoot(url);return true;}
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
		window._location_.reload  = function(){window.location.reload();};
		window._location_.replace = function(urlFrom,urlTo){
			var url = window._location_._href.replace(urlFrom,urlTo);
			if(gotoPage(url)){return;};
			window.location.href = url;
		};
		Object.defineProperty(window._location_,'href',{
			set:function(url){
				if(gotoPage(url)){return;};
				window.location.href = url;
			},
			get:function(){return window._location_._href;}
		});
		window.parent.postMessage({type:'iframe.event',event:'iframeMainload'},'*');
		
		// location 跳转监听,暂时无解; //onhashchange,popstate,locationchange...
		// https://stackoverflow.com/questions/6390341/how-to-detect-if-url-has-changed-after-hash-in-javascript
		// window.addEventListener('popstate',function(e){});
		var _open = window.open;
		window.open = function(url){
			if(gotoPage(url)){return;};
			return _open.apply(window,arguments);
		}

	},
});