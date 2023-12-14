kodReady.push(function(){
	Events.bind('explorer.kodApp.before',function(appList){
		appList.push({
			name:'{{package.id}}',
			title:'{{package.name}}',
			ext:"{{config.fileExt}}",
			sort:"{{config.fileSort}}",
			icon:'x-item-icon x-html',
			callback:showView
		});
	});
	
	var showView = function(filePath,ext,name){
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
		
		var title   = 'title="'+LNG['common.save'] + '(ctrl+s)" title-timeout="100" ';
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