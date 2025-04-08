kodReady.push(function(){
	var htmlSafe = false;
	var supportView = "{{config.fileView}}" != '0';
	var supportEdit = "{{config.fileEdit}}" != '0';
	Events.bind('explorer.kodApp.before',function(appList){
		//外链分享不支持html编辑;
		if(supportEdit && _.get(window,'kodApp.pathModel.fileSave')){
			appList.push({
				name:'htmlEdit',
				title:"{{LNG['htmlEditor.app.edit']}}",
				ext:"{{config.fileExt}}",
				sort:parseInt("{{config.fileEditSort}}") || 5,
				icon:'x-item-icon x-html',
				callback:showEdit
			});
		}
		
		if(!supportView || !htmlViewsupport()){return;}
		kodApp.add({ // kodApp 加载后,加载htmlSafe资源; 
			name:'htmlView',
			title:"{{LNG['htmlEditor.app.show']}}",
			ext:"{{config.fileExt}}",
			sort:parseInt("{{config.fileViewSort}}") || 10,
			icon:'x-item-icon x-html',
			callback:function(filePath,ext,name,args){
				requireAsync("{{pluginHost}}static/htmlSafe.js?v={{package.version}}",function(View){
					htmlSafe = new View();kodApp.htmlSafe = htmlSafe;
					var pathModel  = kodApp.pathModel,uuid = md5(filePath);
					var findDialog = $.dialog.list[uuid];
					if(findDialog){findDialog.display(true).zIndex().$main.flash();return;}
					
					var dialog  = core.openDialog('',core.icon('html'),name,uuid,{iframeAttr:htmlSafe.iframeAttr()});
					if(!dialog || !dialog.$main){return;}
					
					var $iframe = dialog.$main.find('.aui-content iframe');
					dialog.$main.find('.iframe-mask').remove();
					dialog.refreshSupport = true;
					dialog.refresh = function(){htmlSafe.loadContent($iframe,filePath,pathModel,false,args);return dialog;};
					htmlSafe.loadContent($iframe,filePath,pathModel,false,args);
				});
			}
		});
	});
	
	var htmlViewsupport = function(){
		if(!window.fetch || !window.MutationObserver || !window.postMessage){return false;}
		var frame = document.createElement("iframe");
		if(!("sandbox" in frame) || !("srcdoc" in frame)){return false;}
		return true;
	};
	
	var showEdit = function(filePath,ext,name){
		if( !kodApp.pathModel || !kodApp.pathModel.fileContent || !kodApi.formMaker){
			return Tips.tips(LNG['explorer.error'],'warning');
		}
		
		requireAsync('{{pluginHost}}/static/style.css?v{{package.version}}');
		var formData = {
			formStyle:{className:"form-box-title-block dialog-html-editor",hideSave:1},
			text:{
				type:'htmlEditor',value:'',
				attr:{"data-options":{height:'100%',browser_spellcheck:false,statusbar:true,resize:false},style:"height:100%;"}
			}
		};
		var form = new kodApi.formMaker({formData:formData});
		form.renderDialog({
			width:'80%',height:'70%',singleDialog:1,title:name,
			ico:'<i class="path-ico"><i class="x-item-icon x-html"></i></i>',
		});
		form.bind('fieldLoad',function(e){
			bindEvent(form,filePath,name);
			kodApp.pathModel.fileContent({path:filePath},function(content){
				form.setValue('text',content);
				if( form.getValue('text') != content && 
					content && content.length >= 10){
					form._saveNeedConfirm = true; 
					// 设置后立即获取,内容经过解析变更情况,保存时需要提示确认;
				}
			});
		});
	};

	var bindEvent = function(form,filePath){
		var iframeWindow = form.$('iframe').get(0).contentWindow;
		if(!iframeWindow){return;}
		$(iframeWindow.document.body).css({padding:'10px 20px'});
		var supportSave = true;
		if(!kodApp.pathModel.fileSave){supportSave = false;}
		if(_.startsWith(filePath,'http:') || _.startsWith(filePath,'https:')){supportSave = false;}
		
		var saveNow = function(){
			form._saveNeedConfirm = false;// 只需确认一次;
			if(!supportSave){return Tips.tips(LNG['explorer.noPermissionWriteAll'],'warning');}
			var content = form.getValue('text');
			kodApp.pathModel.fileSave({path:filePath,content:content},Tips.tips);
		};
		var save = function(){
			if(form._saveNeedConfirm && supportSave){
				return $.dialog.confirm("{{LNG['htmlEditor.meta.tips']}}",saveNow);
			}
			saveNow();
		};
		var saveAction = function(e){
			var isCtrl = e.metaKey || e.ctrlKey;
			if(isCtrl && e.key == 's'){save();return stopPP(e);}
		};
		
		form.editor = form.$('.tox-tinymce').data('editor');
		if(form.editor){form.editor.focus();}
		$(form.editor.editorContainer).bind('click',function(e){
			$('.tox.tox-tinymce-aux').zIndexMax();
		});
		
		form.$el.attr('title-root-set','1').attr('title-timeout','100');//title延迟时间统一缩短;
		var title   = 'title="'+LNG['common.save'] + '(ctrl+s)"';
		var btnHtml = '<button class="tox-tbtn btn-html-save" '+title+'><i class="mce-i-icon mce-i-save"></i></button>';
		var $btn = $(btnHtml).insertAfter(form.$(".toolbar-fullscreen"));
		if(!supportSave){$btn.addClass('disable');}
		
		$btn.bind('click',save);
		$(iframeWindow.document.body).bind('keydown',saveAction);
		form.$el.bind('keydown',saveAction);
		form.$el.bind('aceEditorSave',function(){ // 代码模式编辑保存;
			var codeEditor = form.$('.toc-form-code-editor .ace_editor').data('editor');
			if(!codeEditor){return;}
			form.setValue('text',codeEditor.getValue());save();
		});
	};
});