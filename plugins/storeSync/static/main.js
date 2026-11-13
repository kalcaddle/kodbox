kodReady.push(function(){
	var staticPath	= '{{pluginHost}}static/';
    var version 	= '?v={{package.version}}';
    LNG.set(jsonDecode(urlDecode("{{LNG}}")));

	var storeSync;
	Events.bind('router.after.admin/storage/index', function(){
		if (!$('.admin-page .storage-page').length) return;
		addTabPage($('.admin-page .storage-page'));
	});
	// 切换页面时主动销毁
	Events.bind('router.before', function(){
		if (storeSync && storeSync.objectRemoveViewFinished !== true) {
			storeSync.objectRemove();	// onRemove
		}
	});

	// 给存储页添加tab，在原页面追加而不修改（$page.children().wrapAll），避免影响其他调用
	var self = this;
	var addTabPage = function($page){
		if ($page.find('.add-tab-page').length) return;
		var html = '<div class="add-tab-page">\
						<div class="dialog-tab tab-group-line">\
							<div class="tab-item ripple-item active" tab-name="base">存储管理</div>\
							<div class="tab-item ripple-item" tab-name="sync">存储同步</div>\
							<span class="tab-item-bar"></span>\
						</div>\
						<div class="tab-content base" tab-item="base"></div>\
						<div class="tab-content sync" tab-item="sync"></div>\
					</div>';
		$page.prepend(html);
		$page.find('.add-tab-page .dialog-tab').css({'margin':'0 -10px 10px'});
		$page.find('.add-tab-page .tab-item.active').tabCurrent();	// 自动切换tab
		$page.delegate('.add-tab-page .tab-item', 'click', function(){
            var tab = $(this).attr("tab-name");
			// tab内容页切换无效，手动处理
			var $tabpage = $(this).parents('.add-tab-page');
			$tabpage.find('.tab-content').hide();
			$tabpage.find('.tab-content[tab-item='+tab+']').show();
			if (tab == 'base') {
				$page.find('.store-list-box,.store-dtl-box').show();
				return;
			}
			$page.find('.store-list-box,.store-dtl-box').hide();

			if (storeSync && storeSync.objectRemoveViewFinished !== true) return;
			requireAsync([
				staticPath+'page/index.js'+version,
				staticPath+'page/package.js'+version,
				staticPath+'page/index.html'+version,
				staticPath+'page/detail/index.js'+version,
				staticPath+'page/detail/index.html'+version,
				staticPath+'page/detail/index.css'+version,
				staticPath+'page/index.css'+version,
			],function(SyncView, pagePkg, pageTpl, SyncDtl, dtlTpl){
				storeSync = new SyncView({parent:self, pagePkg: pagePkg, pageTpl: pageTpl, SyncDtl: SyncDtl, dtlTpl: dtlTpl});
			});
        });
	}
});