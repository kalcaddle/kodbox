/*! Powered by kodbox;hash:724ea91301fe1a9cb863 [2023/04/12 14:01:21] */
/******/ (function(modules) { // webpackBootstrap
/******/ 	// install a JSONP callback for chunk loading
/******/ 	function webpackJsonpCallback(data) {
/******/ 		var chunkIds = data[0];
/******/ 		var moreModules = data[1];
/******/
/******/
/******/ 		// add "moreModules" to the modules object,
/******/ 		// then flag all "chunkIds" as loaded and fire callback
/******/ 		var moduleId, chunkId, i = 0, resolves = [];
/******/ 		for(;i < chunkIds.length; i++) {
/******/ 			chunkId = chunkIds[i];
/******/ 			if(Object.prototype.hasOwnProperty.call(installedChunks, chunkId) && installedChunks[chunkId]) {
/******/ 				resolves.push(installedChunks[chunkId][0]);
/******/ 			}
/******/ 			installedChunks[chunkId] = 0;
/******/ 		}
/******/ 		for(moduleId in moreModules) {
/******/ 			if(Object.prototype.hasOwnProperty.call(moreModules, moduleId)) {
/******/ 				modules[moduleId] = moreModules[moduleId];
/******/ 			}
/******/ 		}
/******/ 		if(parentJsonpFunction) parentJsonpFunction(data);
/******/
/******/ 		while(resolves.length) {
/******/ 			resolves.shift()();
/******/ 		}
/******/
/******/ 	};
/******/
/******/
/******/ 	// The module cache
/******/ 	var installedModules = {};
/******/
/******/ 	// object to store loaded and loading chunks
/******/ 	// undefined = chunk not loaded, null = chunk preloaded/prefetched
/******/ 	// Promise = chunk loading, 0 = chunk loaded
/******/ 	var installedChunks = {
/******/ 		"lib": 0
/******/ 	};
/******/
/******/
/******/
/******/ 	// script path function
/******/ 	function jsonpScriptSrc(chunkId) {
/******/ 		return __webpack_require__.p + "" + ({"vendor":"vendor"}[chunkId]||chunkId) + ".js?v=" + "724ea913" + ""
/******/ 	}
/******/
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/
/******/ 		// Check if module is in cache
/******/ 		if(installedModules[moduleId]) {
/******/ 			return installedModules[moduleId].exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = installedModules[moduleId] = {
/******/ 			i: moduleId,
/******/ 			l: false,
/******/ 			exports: {}
/******/ 		};
/******/
/******/ 		// Execute the module function
/******/ 		modules[moduleId].call(module.exports, module, module.exports, __webpack_require__);
/******/
/******/ 		// Flag the module as loaded
/******/ 		module.l = true;
/******/
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/
/******/ 	// This file contains only the entry chunk.
/******/ 	// The chunk loading function for additional chunks
/******/ 	__webpack_require__.e = function requireEnsure(chunkId) {
/******/ 		var promises = [];
/******/
/******/
/******/ 		// JSONP chunk loading for javascript
/******/
/******/ 		var installedChunkData = installedChunks[chunkId];
/******/ 		if(installedChunkData !== 0) { // 0 means "already installed".
/******/
/******/ 			// a Promise means "currently loading".
/******/ 			if(installedChunkData) {
/******/ 				promises.push(installedChunkData[2]);
/******/ 			} else {
/******/ 				// setup Promise in chunk cache
/******/ 				var promise = new Promise(function(resolve, reject) {
/******/ 					installedChunkData = installedChunks[chunkId] = [resolve, reject];
/******/ 				});
/******/ 				promises.push(installedChunkData[2] = promise);
/******/
/******/ 				// start chunk loading
/******/ 				var script = document.createElement('script');
/******/ 				var onScriptComplete;
/******/
/******/ 				script.charset = 'utf-8';
/******/ 				script.timeout = 120;
/******/ 				if (__webpack_require__.nc) {
/******/ 					script.setAttribute("nonce", __webpack_require__.nc);
/******/ 				}
/******/ 				script.src = jsonpScriptSrc(chunkId);
/******/
/******/ 				// create error before stack unwound to get useful stacktrace later
/******/ 				var error = new Error();
/******/ 				onScriptComplete = function (event) {
/******/ 					// avoid mem leaks in IE.
/******/ 					script.onerror = script.onload = null;
/******/ 					clearTimeout(timeout);
/******/ 					var chunk = installedChunks[chunkId];
/******/ 					if(chunk !== 0) {
/******/ 						if(chunk) {
/******/ 							var errorType = event && (event.type === 'load' ? 'missing' : event.type);
/******/ 							var realSrc = event && event.target && event.target.src;
/******/ 							error.message = 'Loading chunk ' + chunkId + ' failed.\n(' + errorType + ': ' + realSrc + ')';
/******/ 							error.name = 'ChunkLoadError';
/******/ 							error.type = errorType;
/******/ 							error.request = realSrc;
/******/ 							chunk[1](error);
/******/ 						}
/******/ 						installedChunks[chunkId] = undefined;
/******/ 					}
/******/ 				};
/******/ 				var timeout = setTimeout(function(){
/******/ 					onScriptComplete({ type: 'timeout', target: script });
/******/ 				}, 120000);
/******/ 				script.onerror = script.onload = onScriptComplete;
/******/ 				document.head.appendChild(script);
/******/ 			}
/******/ 		}
/******/ 		return Promise.all(promises);
/******/ 	};
/******/
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = modules;
/******/
/******/ 	// expose the module cache
/******/ 	__webpack_require__.c = installedModules;
/******/
/******/ 	// define getter function for harmony exports
/******/ 	__webpack_require__.d = function(exports, name, getter) {
/******/ 		if(!__webpack_require__.o(exports, name)) {
/******/ 			Object.defineProperty(exports, name, { enumerable: true, get: getter });
/******/ 		}
/******/ 	};
/******/
/******/ 	// define __esModule on exports
/******/ 	__webpack_require__.r = function(exports) {
/******/ 		if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 			Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 		}
/******/ 		Object.defineProperty(exports, '__esModule', { value: true });
/******/ 	};
/******/
/******/ 	// create a fake namespace object
/******/ 	// mode & 1: value is a module id, require it
/******/ 	// mode & 2: merge all properties of value into the ns
/******/ 	// mode & 4: return value when already ns object
/******/ 	// mode & 8|1: behave like require
/******/ 	__webpack_require__.t = function(value, mode) {
/******/ 		if(mode & 1) value = __webpack_require__(value);
/******/ 		if(mode & 8) return value;
/******/ 		if((mode & 4) && typeof value === 'object' && value && value.__esModule) return value;
/******/ 		var ns = Object.create(null);
/******/ 		__webpack_require__.r(ns);
/******/ 		Object.defineProperty(ns, 'default', { enumerable: true, value: value });
/******/ 		if(mode & 2 && typeof value != 'string') for(var key in value) __webpack_require__.d(ns, key, function(key) { return value[key]; }.bind(null, key));
/******/ 		return ns;
/******/ 	};
/******/
/******/ 	// getDefaultExport function for compatibility with non-harmony modules
/******/ 	__webpack_require__.n = function(module) {
/******/ 		var getter = module && module.__esModule ?
/******/ 			function getDefault() { return module['default']; } :
/******/ 			function getModuleExports() { return module; };
/******/ 		__webpack_require__.d(getter, 'a', getter);
/******/ 		return getter;
/******/ 	};
/******/
/******/ 	// Object.prototype.hasOwnProperty.call
/******/ 	__webpack_require__.o = function(object, property) { return Object.prototype.hasOwnProperty.call(object, property); };
/******/
/******/ 	// __webpack_public_path__
/******/ 	__webpack_require__.p = "";
/******/
/******/ 	// on error function for async loading
/******/ 	__webpack_require__.oe = function(err) { console.error(err); throw err; };
/******/
/******/ 	var jsonpArray = window["webpackJsonp"] = window["webpackJsonp"] || [];
/******/ 	var oldJsonpFunction = jsonpArray.push.bind(jsonpArray);
/******/ 	jsonpArray.push = webpackJsonpCallback;
/******/ 	jsonpArray = jsonpArray.slice();
/******/ 	for(var i = 0; i < jsonpArray.length; i++) webpackJsonpCallback(jsonpArray[i]);
/******/ 	var parentJsonpFunction = oldJsonpFunction;
/******/
/******/
/******/ 	// Load entry module and return exports
/******/ 	return __webpack_require__(__webpack_require__.s = 2);
/******/ })
/************************************************************************/
/******/ ({

/***/ "./src/api/lib.js":
/*!************************!*\
  !*** ./src/api/lib.js ***!
  \************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

"use strict";


var _loader = __webpack_require__(/*! ../loader */ "./src/loader.js");

var _globalFeature = __webpack_require__(/*! @src/wap/globalFeature */ "./src/wap/globalFeature.js");

var _globalFeature2 = _interopRequireDefault(_globalFeature);

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { 'default': obj }; }

// 第三方使用的库
(0, _loader.loadApi)().then(function () {
	(0, _globalFeature2['default'])();
});

/***/ }),

/***/ "./src/loader.js":
/*!***********************!*\
  !*** ./src/loader.js ***!
  \***********************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

"use strict";


Object.defineProperty(exports, "__esModule", {
	value: true
});
/**
 * 初始化加载流程
 * main.js 统一入口：包含基础的
 * 
 * 1. 调用获取配置接口 [返回静态路径地址、语言包、用户信息、系统信息等配置，入口控制器]
 * 2. 载入其他相关资源：jquery-lib,util,ie8Shim,..等；同时载入多语言、插件配置信息
 */
if (!window.Promise) window.Promise = Promise;

/**
 * 动态载入处理;
 * https://segmentfault.com/q/1010000008980858
 * https://github.com/webpack/webpack/issues/443
 */
var staticLocal = './static/';
if (window.API_HOST) {
	// 兼容 /index.php/explorer/list/path 情况;
	var arr = API_HOST.split("/");arr.pop();
	staticLocal = arr.join('/') + '/static/';
}
window.API_URL = function (api, param) {
	var host = window.API_HOST,
	    and = '&';
	if (_.isNull(param) || _.isUndefined(param)) return host + (api || '');
	if (host.indexOf('?') == -1) {
		and = '?';
	}
	if (Cookie.accessToken) {
		param += '&accessToken=' + Cookie.accessToken;
	}
	return host + (api || '') + and + (param || '');
};
window.API_URL_TRUE = function (url) {
	var url = url || window.location.href;
	var uri = url.replace(API_URL(), '').replace(G.kod.APP_HOST, '').replace('?', '&');
	return G.kod.APP_HOST + '?' + uri;
};

var staticPath = window.STATIC_PATH || staticLocal;
__webpack_require__.p = staticPath + 'app/dist/';

var loadLib = __webpack_require__.e(/*! require.ensure | vendor */ "vendor").then((function (require) {
	__webpack_require__(/*! @lib/nprogress/nprogress */ "./vender-lib/nprogress/nprogress.js");
	__webpack_require__(/*! @lib/nprogress/nprogress.css */ "./vender-lib/nprogress/nprogress.css");
	__webpack_require__(/*! @lib/lodash.min */ "./vender-lib/lodash.min.js"); // https://cdnjs.com/libraries/lodash.js/4.1.0  兼容ie;
	__webpack_require__(/*! @lib/backbone */ "./vender-lib/backbone.js");
	__webpack_require__(/*! @lib/jquery.min */ "./vender-lib/jquery.min.js");
	__webpack_require__(/*! @lib/sea */ "./vender-lib/sea.js");
	__webpack_require__(/*! @src/utils/core/ClassBase */ "./src/utils/core/ClassBase.js");
	__webpack_require__(/*! @src/utils/core/ClassBaseHook */ "./src/utils/core/ClassBaseHook.js");
	__webpack_require__(/*! @src/utils/core/ClassBaseRouter */ "./src/utils/core/ClassBaseRouter.js");
	__webpack_require__(/*! @lib/art-template */ "./vender-lib/art-template.js");
	__webpack_require__(/*! @lib/jquery.position */ "./vender-lib/jquery.position.js");
	__webpack_require__(/*! @lib/jquery.actual */ "./vender-lib/jquery.actual.js");

	__webpack_require__(/*! @lib/jquery.mousewheel */ "./vender-lib/jquery.mousewheel.js");
	__webpack_require__(/*! @lib/jquery.lazyload */ "./vender-lib/jquery.lazyload.js");
	__webpack_require__(/*! @lib/jquery.easing */ "./vender-lib/jquery.easing.js");
	__webpack_require__(/*! @lib/jquery-contextMenu */ "./vender-lib/jquery-contextMenu.js");
	__webpack_require__(/*! @lib/artDialog/jquery-artDialog */ "./vender-lib/artDialog/jquery-artDialog.js");
	__webpack_require__(/*! @lib/mousetrap */ "./vender-lib/mousetrap.js");
	__webpack_require__(/*! @lib/fullscreen */ "./vender-lib/fullscreen.js");
	__webpack_require__(/*! @lib/purify.min */ "./vender-lib/purify.min.js");
	__webpack_require__(/*! @lib/cryptoJS */ "./vender-lib/cryptoJS.js");
	__webpack_require__(/*! @lib/clipboard */ "./vender-lib/clipboard.js");
	__webpack_require__(/*! @lib/bootstrap */ "./vender-lib/bootstrap.js");
	__webpack_require__(/*! @lib/poshytip/jquery.poshytip */ "./vender-lib/poshytip/jquery.poshytip.js");
	__webpack_require__(/*! @lib/poshytip/skin.css */ "./vender-lib/poshytip/skin.css");
	__webpack_require__(/*! @lib/perfect-scrollbar/perfect-scrollbar */ "./vender-lib/perfect-scrollbar/perfect-scrollbar.js");
	__webpack_require__(/*! @lib/perfect-scrollbar/perfect-scrollbar.css */ "./vender-lib/perfect-scrollbar/perfect-scrollbar.css");
	__webpack_require__(/*! @lib/PDFObject */ "./vender-lib/PDFObject.js");
	__webpack_require__(/*! @lib/yamd5 */ "./vender-lib/yamd5.js");

	window.Pinyin = __webpack_require__(/*! @utils/pinyin/index */ "./src/utils/pinyin/index.js")['default'];
	__webpack_require__(/*! @utils/pageBox/index */ "./src/utils/pageBox/index.js");
	__webpack_require__(/*! @utils/pageBox/style.css */ "./src/utils/pageBox/style.css");
	__webpack_require__(/*! @utils/tools/util */ "./src/utils/tools/util.js");
	__webpack_require__(/*! @utils/tools/jquery.input */ "./src/utils/tools/jquery.input.js");
	__webpack_require__(/*! @src/utils/tools/jquery.drag */ "./src/utils/tools/jquery.drag.js");
	__webpack_require__(/*! @src/utils/tools/jquery.dropFile */ "./src/utils/tools/jquery.dropFile.js");
	__webpack_require__(/*! @src/utils/tools/jquery.fly */ "./src/utils/tools/jquery.fly.js");
	__webpack_require__(/*! @src/utils/tools/encode */ "./src/utils/tools/encode.js");
	__webpack_require__(/*! @src/utils/tools/jquery.dragsort */ "./src/utils/tools/jquery.dragsort.js");
	__webpack_require__(/*! @utils/tools/jquery.lib */ "./src/utils/tools/jquery.lib.js");
	__webpack_require__(/*! @utils/tools/jquery.formPop */ "./src/utils/tools/jquery.formPop.js");

	__webpack_require__(/*! @utils/tools/workerRun */ "./src/utils/tools/workerRun.js");
	__webpack_require__(/*! @utils/tools/windowsMessage */ "./src/utils/tools/windowsMessage.js");
	__webpack_require__(/*! @utils/tools/tips */ "./src/utils/tools/tips.js");
	__webpack_require__(/*! @utils/tools/queue */ "./src/utils/tools/queue.js");
	__webpack_require__(/*! @utils/tools/pathTools */ "./src/utils/tools/pathTools.js");
	__webpack_require__(/*! @utils/tools/maskView */ "./src/utils/tools/maskView.js");
	__webpack_require__(/*! @utils/tools/pulltorefresh */ "./src/utils/tools/pulltorefresh.js");
	__webpack_require__(/*! @utils/tools/loadRipple */ "./src/utils/tools/loadRipple.js");
	__webpack_require__(/*! @utils/tools/hook */ "./src/utils/tools/hook.js");
	__webpack_require__(/*! @utils/tools/date */ "./src/utils/tools/date.js");
	__webpack_require__(/*! @utils/tools/uaParser */ "./src/utils/tools/uaParser.js");

	window.Backbone.$ = $;
	window.Events = Backbone.Events;
	initRequire();
}).bind(null, __webpack_require__)).catch(__webpack_require__.oe);

// 所有requireAsync,requirePromise 异步请求的资源都加上版本号;
var loadTime = Date.now();
var initRequire = function initRequire() {
	var requireUse = seajs.use;
	seajs.use = function () {
		var args = _.toArray(arguments);
		var addVersion = function addVersion(url) {
			var version = _.get(window, 'G.kod.version', '');
			var build = _.get(window, 'G.kod.build', '');
			var ENV_DEV = _.get(window, 'G.kod.ENV_DEV') == 1;
			version = ENV_DEV ? loadTime : version + '.' + build;

			if (!version || _.includes(url, '&v=') || _.includes(url, '?v=')) return url;
			if (_.includes(url, '?')) return url;
			if (!_.endsWith(url, '.htm') && !_.endsWith(url, '.html') && !_.endsWith(url, '.css') && !_.endsWith(url, '.json') && !_.endsWith(url, '.js')) {
				url += '.js'; // 省略js的情况;
			}
			return url += '?v=' + version;
		};
		var url = args[0];
		if (_.isString(url)) {
			args[0] = addVersion(url);
		} else if (_.isArray(url)) {
			args[0] = _.map(url, function (link) {
				return addVersion(link);
			});
		}
		requireUse.apply(seajs, args);
	};

	window._ktime = dateFormat(false, "dhi");
	window.requireAsync = seajs.use;
	window.requirePromise = function (url) {
		var promise = $.Deferred();
		seajs.use(url, promise.resolve);
		return promise;
	};
};

var lessLoaderReset = function lessLoaderReset() {
	if (window.lessENV != 'development') return;
	var preOpen = XMLHttpRequest.prototype.open;
	XMLHttpRequest.prototype.open = function (method, url) {
		var args = Array.prototype.slice.call(arguments, 0);
		if (url.match(/\.less$/)) {
			args[1] = url + '?_t=' + loadTime;
		}
		return preOpen.apply(this, args);
	};
};
lessLoaderReset();

/**
 * 本地方式加载样式:字体加载跨域问题; 
 * 默认方法: 含有字体的样式文件直连服务器请求;
 * 
 * 定义STATIC_PATH_ALL;则代表跨域资源也走cdn;
 * 
 * 方法一: 可以通过配置跨域方式处理;
 * 方法二: 将文件引用转为base64来处理;[php转换处理;]
 * https://www.cnblogs.com/victorlyw/articles/9970805.html
 */
var loadFontCss = function loadFontCss() {
	// if(!window.STATIC_PATH) return; // 插件等地方引入;
	var staticPath = window.STATIC_PATH_ALL || staticLocal;
	requireAsync([staticPath + "style/lib/alifont/iconfont.css", staticPath + "style/lib/font-icon/style.css"]);
};

var loadPlugin = function loadPlugin() {
	var plugins = API_URL('user/view/plugins', 'v=' + time());
	return requirePromise(plugins);
};
var loadOption = function loadOption() {
	Events.trigger('user.optionLoadBefore');
	var options = API_URL('user/view/options', 'v=' + time());
	return requirePromise("text!" + options).then(function (data) {
		if (!data) return;
		var data = JSON.parse(data);
		if (!data || !data.code || !data.data) return;

		window.G = _.extend(window.G || {}, data.data);
		var staticPath = G.kod.staticPath;
		var urlPath = API_URL();
		if (!_.startsWith(staticPath, 'http')) {
			if (_.startsWith(staticPath, '/')) {
				staticPath = $.parseUrl(urlPath).origin + staticPath;
			} else {
				var staticBase = urlPath.substr(0, _.lastIndexOf(urlPath, '/'));
				staticPath = staticBase + '/' + staticPath;
			}
			staticPath = staticPath.replace('/./', '/');
		}

		window.STATIC_PATH_ALL = window.STATIC_PATH_ALL || G.kod.APP_HOST + 'static/';
		window.STATIC_PATH = staticPath;
		window.VENDER_PATH = window.STATIC_PATH + 'app/vender/';
		window.API_HOST = G.kod.appApi;
		$.dialog.defaults.path = window.STATIC_PATH + 'app/vender/artDialog-icon/';

		// requireAsync('http://at.alicdn.com/t/font_1107537_2e5p6mlqkog.js');
		requireAsync(window.STATIC_PATH + 'style/lib/alifont/iconfont.js');
		loadFontCss();
		Events.trigger('user.optionLoadAfter');
	});
};
var loadLang = function loadLang() {
	var link = API_URL('user/view/lang', 'v=' + time());
	return requirePromise("text!" + link).then(function (data) {
		if (!data) return;
		try {
			var data = JSON.parse(data);
		} catch (e) {
			return showError(data);
		}
		if (!data || !data.code || !data.data) return;

		window.LNG = _.extend(window.LNG || {}, _.get(data, 'data.list'));
		window.G.lang = _.get(data, 'data.lang');
		LNG.find = function (val) {
			var arr = {};
			_.each(LNG, function (theValue, theKey) {
				if (_.includes(theValue, val)) {
					arr[theKey] = theValue;
				}
			});
			return arr;
		};
		LNG.set = function (lang) {
			if (!lang || !_.isObject(lang)) return;
			_.extend(LNG, lang);
		}, LNG.make = function (key) {
			var args = _.toArray(arguments);
			var result = LNG[key];
			if (!result) return key;
			for (var i = 1; i < args.length; i++) {
				result = result.replace(/(%d|%s)/, args[i]);
			}
			return result;
		};

		LNG.space = '<i class="char-space"></i>';
		/**
   * logo获取;checkType类型
   * 1. copyright: 关于对话框logo展示,区分文字/图片logo, 免费版使用默认kod的logo;
   * 2. login: 登陆界面logo, 区分文字/图片logo;
   * 3. 其他图片logo: 左侧toolbar全局icon;个人中心;后台管理;分享页面
   */
		LNG.logo = function (checkType) {
			var options = window.G.system.options || {};
			var isImageLogo = options.systemNameType == 'image';
			var logoImage = options.systemLogo;
			var defaultLogo = STATIC_PATH + 'images/common/logo.png';
			if (!_.includes(['zh-CN', 'zh-TW'], G.lang)) {
				defaultLogo = STATIC_PATH + 'images/common/logo-en.png';
			}

			// 子公司信息;
			var companyInfo = G.kod.companyInfo || false;
			if (companyInfo && companyInfo.logoType == 'text' && companyInfo.logoText) {
				return '<span class="logo-text" title="' + companyInfo.logoText + '" title-timeout="200"><i class="font-icon ri-cloud-fill mr-5"></i>' + companyInfo.logoText + '</span>';
			}

			var image = function image(src) {
				return '<img src="' + src + '" onerror="this.src=\'' + defaultLogo + '\'"/>';
			};
			var text = function text(str) {
				return '<span class="logo-text">' + str + '</span>';
			};
			if (checkType == 'copyright') {
				var logoText = LNG['common.copyright.name'];
				if (window.G.kod.versionType == 'A' && LNG['common.oemCompany'] != window.md5(_.get(window, 'G.kod.channel', ''))) {
					logoImage = defaultLogo;logoText = 'kodbox';
				}
				return isImageLogo ? image(logoImage) : text(logoText);
			}
			if (checkType == 'login') {
				return isImageLogo ? image(logoImage) : text(options.systemName);
			}
			return image(logoImage);
		};
	});
};

var showError = function showError(html) {
	Tips.close('System error!', false);
	var dialog = $.dialog.list['xhrErrorDialog'];
	if (!dialog) {
		dialog = $.dialog({
			id: 'xhrErrorDialog',
			padding: 0,
			width: '55%', height: '60%',
			fixed: true, resize: true,
			title: 'System Error',
			content: ''
		});
	}
	// var errorHtml = htmlSafeReplace(html);	// htmlSafe
	var error = htmlSafe(html);
	var errorHtml = '\n\t\t<div class="ajaxError">\n\t\t<div class="content-preview">\n\t\t<style>\n\t\t.ajaxError{\n\t\t\toverflow:auto;padding:20px 5%;color:#555;font-size:13px;line-height:1.5em;\n\t\t\tfont-family:"Lantinghei SC","Hiragino Sans GB","Microsoft Yahei",Helvetica,arial,sans-serif;\n\t\t}\n\t\t.ajaxError #msgbox{margin:0;}\n\t\t.error-tips{padding:5px 0 10px;border-bottom:1px solid #eee;margin-bottom:10px;font-size: 14px;}\n\t\t.content-preview{\n\t\t\tborder: 1px solid #fff1f0;padding:5px 20px 10px 20px;\n\t\t\tbackground: #fff9f9;border-radius:4px;margin-bottom:50px;\n\t\t}\n\t\t</style>\n\t\t<h3 style="color:#f04134" >System Error!</h3>' + error + '\n\t\t</div></div>';
	$.iframeHtml(dialog.$main.find('.aui-content'), errorHtml);
};

// 初始化网络相关资源； 载入配置 => 载入
var loadMain = function loadMain() {
	return loadLib.then(function () {
		NProgress.isStarted() || NProgress.start();
		NProgress.set(0.5);
	}).then(loadPlugin).then(function () {
		NProgress.set(0.6);
	}).then(loadOption).then(function () {
		NProgress.set(0.8);
	}).then(loadLang).then(function () {
		NProgress.done();
		$('body > .loading-body').fadeOut(1000, function () {
			$(this).remove();
		});
	});
};

var loadApi = function loadApi() {
	if (!window.API_HOST) return loadLib.then();
	return loadLib.then(function () {
		NProgress.isStarted() || NProgress.start();
		NProgress.set(0.6);
	}).then(loadOption).then(function () {
		NProgress.set(0.8);
	}).then(loadLang).then(function () {
		NProgress.done();
	});
};

exports.loadMain = loadMain;
exports.loadApi = loadApi;
exports.loadOption = loadOption;
exports.loadLang = loadLang;
exports.loadPlugin = loadPlugin;

/***/ }),

/***/ "./src/wap/globalFeature.js":
/*!**********************************!*\
  !*** ./src/wap/globalFeature.js ***!
  \**********************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

"use strict";


Object.defineProperty(exports, "__esModule", {
	value: true
});

exports['default'] = function () {
	bindScroller();
	bindTitle();
	initTemplate();
	bodyEvent();
	bindTabItem();

	Events.trigger('windowReady');
	var e = document.createEvent("CustomEvent");
	e.initCustomEvent("kodReadyView", true, true, { source: window });
	document.dispatchEvent(e);
};

// 滚动条处理
var bindScroller = function bindScroller() {
	if (!$.fn.perfectScroll) return;
	var scrollerUpdate = function scrollerUpdate() {
		$('.perfectScroll').perfectScroll();
	};
	$(window).bind('resize', scrollerUpdate);
	$(window).bind('scoller', scrollerUpdate);
};

//https://github.com/vadikom/poshytip/blob/master/src/jquery.poshytip.js
var bindTitle = function bindTitle() {
	if ($.isWindowTouch()) return;
	if (!$.fn.poshytip) return;
	var $title = $('[title]');
	$title.poshytip({
		className: 'ptips-skin',
		liveEvents: true,
		slide: false,
		alignTo: 'cursor', //target|cursor
		alignX: 'right',
		alignY: 'bottom',
		showAniDuration: 150,
		hideAniDuration: 200,
		// followCursor:true,
		// hoverClearDelay:500,
		offsetY: 10,
		offsetX: 20,
		showTimeout: function showTimeout() {
			var timeout = 1500;
			if ($(this).attr('title-timeout')) {
				timeout = parseInt($(this).attr('title-timeout'));
			}
			return timeout;
		},
		content: function content($tips) {
			var $target = $(this);
			if ($.isDraging) return;
			if ($(this).hasClass('context-menu-active') || $(this).is(":focus") || $target.hasClass('disable') || $target.hasClass('disable-title')) {
				return;
			}
			var skin = $target.attr('title-skin');
			var position = $target.attr('title-position');
			var $titleSetNode = $target.parentNode('[title-root-set]'); // 上层指定title(样式,位置);
			if ($titleSetNode) {
				skin = $titleSetNode.attr('title-skin');
				position = $titleSetNode.attr('title-position');
			}

			// 当前元素指定样式与定位(title-skin,title-position); 或上层元素title-root-set指定;优先当前层;
			$tips.addClass(skin || 'yellow'); //dark/white/yellow
			if (position) {
				// title-position='center bottom,center top-5'
				var poseDefault = ['center bottom', 'center top-5'];
				var posInfo = position.split(',');
				if (posInfo.length != 2) {
					posInfo = poseDefault;
				}
				if (!posInfo[0]) {
					posInfo[0] = poseDefault[0];
				}
				if (!posInfo[1]) {
					posInfo[1] = poseDefault[1];
				}
				setTimeout(function () {
					$tips.position({ my: posInfo[0], at: posInfo[1], of: $target, collision: "flipfit flipfit" });
				}, 0);
			}

			// 动态获取内容;
			var titleCreate = $(this).data('titleCreate');
			if (titleCreate && _.isFunction(titleCreate)) {
				return titleCreate($(this));
			}

			var str = $(this).data("title.poshytip");
			if ($(this).attr('title-data')) {
				var $target = $($(this).attr('title-data'));
				if ($target.is('input') || $target.is('textarea')) {
					str = $target.val();
				} else {
					str = $target.html();
				}
			}
			str = str ? str : "";
			// 纯文本,无html标签,则自动处理换行;
			if (str.indexOf('<') == -1 && str.indexOf('>') == -1) {
				str = str.replace(/\n/g, "<br/>");
			}
			str = str.replace(/ /g, ' '); // 从attr取出可能包含全角空格[ ]=166; [ ]=32
			return str;
		}
	});

	// mousedown mouseup click touchend touchstart keydown keyup
	$(document).bind('mousedown mouseup click touchend touchstart', function (e) {
		if (!$.fn.poshytip) return;
		if ($(e.target).attr('data-require')) return;
		$($title).poshytip('clearTimeouts').poshytip('hide');
		$(".ptips-skin").remove();
	});
	$('input,textarea').live('focus', function () {
		if (!$.fn.poshytip) return;
		if ($(this).attr('data-require')) return;
		$($title).poshytip('hide');
		$(".ptips-skin").remove();
	});
};

var initTemplate = function initTemplate() {
	if (!window.API_HOST) return;
	// https://aui.github.io/art-template/zh-cn/docs/options.html
	// template.defaults.debug = G.kod.ENV_DEV;

	template.defaults.cache = true;
	template.defaults.minimize = false;
	template.defaults.compileDebug = false; // 为false时; 直接字符串模板,含有注释,render时会卡
};
var bindHoverAnimate = function bindHoverAnimate() {
	if ($.isWindowTouch()) return;
	var selector = [
	// 'a,button,.btn,.kui-btn,.submit-button',
	'.menuBar .menu-item', '.menu-group-submenu .menu-item-sub', '.menuBar .dropdown-menu-main .ripple-item', '.setting-menu-left .menu-item-content', '.admin-menu-left .menu-item-content'].join(',');
	$.hoverAnimate({ el: selector, delegate: 'body' });
};
var bodyEvent = function bodyEvent() {
	var allowSelector = 'a,button,.ripple-item,.context-menu-item,.kui-btn,.btn,.button,[ripple-item]';
	if ($.isWindowTouch()) {
		allowSelector = 'a,button,.ripple-item,.kui-btn,.btn,.button,[ripple-item]';
	}
	//点击波纹效果
	loadRipple(allowSelector, '.disable-ripple,.disabled,.disable,.ztree a,.not-selectable');
	bindHoverAnimate();
	$(window).bind('resize', function () {
		Events.trigger('window.resize');
	});

	// 拖拽文件处理;
	var dragCheck = function dragCheck(e) {
		if ($(e.target).isEdit()) return true;
		return stopPP(e);
	};
	$(document).bind('dragover', dragCheck).bind('drop', dragCheck); // 拖放不处理;

	// 密码查看;
	$('body').delegate('.password-view', 'mousedown touchstart', function (e) {
		var $btn = $(this);
		var $password = $btn.parent().children('input[type="password"]');
		if ($password.length != 1) return;
		// if( !$password.val() ) return;

		var textHTML = $($password.get(0)).prop('outerHTML');
		textHTML = textHTML.replace(/type\s*=\s*("|')?password("|')?/i, 'type="text"');
		var $text = $(textHTML).insertAfter($password);

		$password.addClass('hidden');
		$btn.addClass('active');
		$text.val($password.val());
		$($("input[type='text']").get(0));
		$(window).one('mouseup touchend', function () {
			$password.removeClass('hidden');
			$btn.removeClass('active');
			$text.remove();
		});
	});

	// 全局禁用图片和链接拖拽;
	$('body').delegate('img,a', 'dragstart', function (e) {
		return stopPP(e);
	});
	if (!window.API_HOST) return;

	$('body').delegate('a', 'click', function (e) {
		if ($(this).attr('href') != '#') return;
		// e.stopPropagation();//只禁默认事件; 继续冒泡
		e.preventDefault();
	});

	$('body').delegate('[link-href]', 'click', function (e) {
		return openLink(e, '');
	});
	$('body').delegate('[link-href]', 'mouseup', function (e) {
		if (e.which == 2) {
			return openLink(e, '_blank');
		}
	});

	var openLink = function openLink(e, target) {
		var $click = $(e.currentTarget);
		var page = $click.attr('link-href') || '#';
		var target = target || $click.attr('target');
		var isUrl = _.startsWith(page, 'http://') || _.startsWith(page, 'https://');
		var url = page;
		if (!isUrl) {
			if (page.startsWith('/') || page.startsWith('./')) {
				if (e.which == 2 || target == '_blank') {
					return window.open(url);
				} else {
					window.location.href = page;
				}
				return;
			}
			url = $.parseUrl().urlPath + (page == '#' ? '' : '#' + page);
		}
		if ($click.attr('dialog-open') || target == 'dialog') {
			var icon = $click.find('.font-icon').prop('outerHTML') || '';
			var title = htmlSafe(icon + $click.text());
			return core.openDialog(url, '', title);
		}

		// url连接;兼容
		if (isUrl) {
			target == '_blank' ? window.open(url) : window.location.href = url;
			return;
		}
		// 新页面打开: 设置了target=blank; 或鼠标中间
		if (e.which == 2 || target == '_blank') {
			return window.open(url);
		}
		Router.go(page);
	};
};

var bindTabItem = function bindTabItem() {
	$.fn.tabCurrent = function (ignoreAnimate) {
		var $tab = $(this);
		if (!$tab || $tab.length == 0) return this;
		var $parent = $tab.parent();
		var width = $tab.outerWidth();
		var left = $tab.offset().left - $parent.offset().left;
		var $line = $parent.children('.tab-item-bar');
		if ($line.length == 0) return this;

		// 处理首次位置修正;  formMaker处理;
		if (!$line.data('initTab')) {
			$line.data('initTab', 1);
			$line.addClass('no-animate opacity-hidden'); //opacity-hidden
			setTimeout(function () {
				$line.removeClass('opacity-hidden');
				$parent.children('.tab-item').filter('.active').tabCurrent();
			}, 10);
			setTimeout(function () {
				$parent.children('.tab-item').filter('.active').tabCurrent();
				$line.removeClass('no-animate');
			}, 300); //对话框动画显示时间差; 重置一次;
		}

		// 宽度取内容宽度;
		width = $tab.width() * 1;
		left = left + ($tab.outerWidth() - width) / 2;

		var parentBottom = $parent.offset().top + $parent.outerHeight();
		var tabBottom = $tab.offset().top + $tab.outerHeight();
		var style = {
			width: width + 'px',
			left: left + 'px',
			transform: 'translate3d(0px,-' + Math.abs(parentBottom - tabBottom + 1) + 'px, 0px)'
		};

		if (ignoreAnimate) {
			$line.addClass('no-animate');
		}
		$line.css(style);
		$parent.children('.tab-item').removeClass('active');
		$tab.addClass('active');
		if (ignoreAnimate) {
			$line.offset();$line.removeClass('no-animate');
		}

		//自动选中内容;
		var $panParent = $parent.parent();
		if ($parent.attr('tab-pan-parent')) {
			$panParent = $parent.parents($parent.attr('tab-pan-parent'));
		}
		var $content = $panParent.children('.tab-group-pan').children('.tab-content');
		if ($content.length != 0) {
			var tabName = $tab.attr('tab-name').replace(/'/g, "\\'");
			var $oldContent = $content.filter(":visible");
			var $newContent = $content.filter('.' + tabName);
			// $oldContent.hide();$newContent.show();
			$oldContent.switchTo($newContent);
			$tab.trigger('tab-select');
		}
		// console.error(2001,$tab,$($tab).width(),style,$oldContent,$newContent);
		return this;
	};
	$(document).delegate('.tab-group-line .tab-item', 'click touchend', function () {
		$(this).tabCurrent();
	});
	var onResize = _.debounce(function () {
		$('.tab-group-line .tab-item.active').each(function () {
			$(this).tabCurrent(true);
		});
	}, 50);
	$(window).bind('resize', onResize);

	if ($.isWindowTouch()) {
		bindClickReset();
	}
};

var bindClickReset = function bindClickReset() {
	return;
	var resetMap = {
		bind: { index: 0, fn: 1, from: 'click', to: 'click touchend' },
		unbind: { index: 0, fn: 1, from: 'click', to: 'click touchend' },
		delegate: { index: 1, fn: 2, from: 'click', to: 'click touchend' },
		undelegate: { index: 1, fn: 2, from: 'click', to: 'click touchend' }
	};
	_.each(resetMap, function (item, functionName) {
		var oldFunction = $.fn[functionName];
		$.fn[functionName] = function () {
			var args = _.toArray(arguments);
			if (args[item.index] == item.from) {
				// 添加样式 or 绑定touch事件;
				$(this).css({ cursor: 'pointer' });
				// var argsNew = $.objClone(args);
				// argsNew[item.index] = 'touchstart';
				// argsNew[item.fn] = $.noop;
				// oldFunction.apply(this,argsNew);

				// args[item.index] = item.to;
				// console.error(101,functionName,item,args,this);
			}
			return oldFunction.apply(this, args);
		};
	});
};

;

/***/ }),

/***/ 2:
/*!*******************************!*\
  !*** multi ./src//api/lib.js ***!
  \*******************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

module.exports = __webpack_require__(/*! /Library/WebServer/Documents/localhost/kod/kodbox/static/app/src//api/lib.js */"./src/api/lib.js");


/***/ })

/******/ });
//# sourceMappingURL=lib.js.map?v=724ea913