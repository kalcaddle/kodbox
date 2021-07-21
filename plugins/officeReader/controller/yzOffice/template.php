<!doctype html>
<head>
	<meta name="viewport" content="width=device-width, minimum-scale=1.0, initial-scale=1, user-scalable=no" />
	<meta charset="utf-8">
	<?php $this->plugin->link();?>
	<title><?php echo clear_html($fileName);?></title>
	<style>
		body {margin: 0;font-family: "Helvetica Neue Light", "Segoe UI Semilight", sans-serif;}
		.infoButtonPrint{
			cursor: pointer;font-size:20px;right:60px;text-align:center;
			background:none;color: #666;line-height:27px;
		}
		.load-content{    
			position: fixed;
			overflow: hidden;z-index: 100;top:0;left:0;right:0;bottom:0;
			font-size:13px;color:#666;
		}
		.app-icon{text-align: center;position: absolute;width: 100%;top:40%;margin-top: -150px;}
		.app-icon img{max-height: 150px;}
		.app-icon .app-name{font-size:16px;color:#222;}
		.load-status{margin: 20px auto;}
		.load-status .progress{
			width:300px;height:30px;margin: 10px auto;position: relative;
			background-image: linear-gradient(to right,#ddd, #f6f6f6, #ddd);
		}
		.progress-loading{font-size:30px;margin-top: 20px;}
		.progress-slider .content{position: absolute;width: 100%;color:#29792c;font-size:12px;line-height:30px;}
		.progress-slider .left{float:left;padding-left: 10px;}
		.progress-slider .right{float:right;padding-right: 10px;}
		.progress-bar{opacity: 0.7;}
		.clear{clear:both;}
		.progress-text{text-align: center;display: block;width:300px;margin: 0 auto;padding: 10px;word-wrap: break-word;}
		@page {
			margin-left: 0px;
			margin-right: 0px;
			margin-top: 0px;
			margin-bottom: 0px;
			margin: 0;
			-webkit-print-color-adjust: exact;
		}

		body{background:#f0f0f0 !important;}
		div.word-body, div.ppt-body{background:#ff0;}
		div.word-page{box-shadow: 0 1px 6px #ccc;}
	</style> 
</head>
<body>
	<div class="load-content display">
		<div class="app-icon">
			<img src="<?php echo "{$this->plugin->pluginHost}/static/images/icon.png";?>" />
			<div class="app-name"><?php echo LNG('officeReader.meta.name');?></div>
			<div class="load-status">
				<div class="progress-text alert"><?php echo LNG('officeReader.yzOffice.transfer');?></div>
				<div class="progress-slider progress progress-striped active hidden">
					<div class="progress-bar progress-bar-success" role="progressbar" style="width:0%;"></div>	
					<div class="content">
						<span class="left"></span>
						<span class="right"></span>
						<div class="clear"></div>
					</div>
				</div>
				<div class="progress-loading hidden">
					<div class="moveCircleRight"><i class="ri-loader-line"></i></div>
				</div>
			</div>
		</div>
	</div>
	<div id="MyViewerDiv"></div>
</body>
	<script type="text/javascript">
		var LNG = {
			"error":"<?php echo LNG('explorer.error');?>",
			"success":"<?php echo LNG('explorer.success');?>",
			"officeReader.yzOffice.transfer":"<?php echo LNG('officeReader.yzOffice.transfer');?>",
			"officeReader.yzOffice.converting":"<?php echo LNG('officeReader.yzOffice.converting');?>",
			"officeReader.yzOffice.uploadError":"<?php echo LNG('officeReader.yzOffice.uploadError');?>",
			"officeReader.yzOffice.convert":"<?php echo LNG('officeReader.yzOffice.convert');?>",
			"officeReader.yzOffice.transferAgain":"<?php echo LNG('officeReader.yzOffice.transferAgain');?>"
		};
		var path     = '<?php echo clear_quote($this->plugin->in["path"]);?>';
		var apiBase  = "<?php echo $this->plugin->pluginApi;?>";//不能含有index.php
		var selfHost = '<?php echo $this->plugin->pluginHost;?>';
		var cacheFile= '<?php echo $config["cacheFile"];?>';
		var apiUrl = {
			task:apiBase+'yzOffice/index/task&path='+urlEncode(path),
			restart:apiBase+'yzOffice/index/restart&path='+urlEncode(path),
			getFile:apiBase+'yzOffice/index/getFile&path='+urlEncode(path)+'&file='
		}
		var request = function(){
			$('.load-content').fadeIn(100);
			$('.progress-text').removeClass('alert-danger').html(LNG['officeReader.yzOffice.transfer']);
			$('.progress-bar').css('width',"0%");
			var repeatTimer;
			var taskStatus = function(){
				$.ajax({
					url:apiUrl.task+"&time"+UUID(),
					dataType:'json',
					success:function(data){
						statusUpdate(data);
					}
				});
			}
			var statusUpdate = function(data){
				if(!data) return;
				if(!data.code){
					var error = data.data;
					if(!_.isString(error)){
						error = LNG['error'];
					}
					clearInterval(repeatTimer);
					error += '&nbsp;&nbsp;<a class="load_repeat" href="javascript:void(0);">'
                			     + LNG['officeReader.yzOffice.transferAgain']+'</a>';
					$('.progress-text').addClass('alert-danger').html(error);
					$('.progress-slider,.progress-loading').hide();
					return;
				}

				if(!data.data) return;
				if(data.data.success == 1){
					clearInterval(repeatTimer);
					var item = data.data.steps[data.data.steps.length-1];
					$('.progress-text').html(LNG['success']+LNG['officeReader.yzOffice.convert']);
					loadSuccess(data);
				}else{
					var step = data.data.steps[data.data.currentStep];
					var stepInfo = step.result;
					if(!_.isObject(stepInfo)){
						return;
					}
					$('.progress-text').removeClass('alert-danger');
					$('.progress-slider,.progress-loading').removeClass('hidden');
					if(step.name == 'upload'){
						$('.progress-text').html(LNG['officeReader.yzOffice.transfer']);
						if(stepInfo === false){
							clearInterval(repeatTimer);
							loadError(LNG['officeReader.yzOffice.uploadError']);
						}

						var sizeTotal = pathTools.fileSize(stepInfo.taskTotal);
						var sizeSuccess = pathTools.fileSize(stepInfo.taskFinished);
						var speed = pathTools.fileSize(stepInfo.taskSpeed)+'/s';
						$('.progress-slider .left').html(sizeSuccess+'/'+sizeTotal+' ('+speed+')');//12.6M/25.3M (1.2M/s)
						$('.progress-slider .right').html(_.floor((stepInfo.taskPercent*100),1)+"%");
						$('.progress-bar').css('width',(stepInfo.taskPercent*100)+"%");
					}else if(step.name == 'convertProcess'){
						$('.progress-slider').addClass('hidden');
						$('.progress-text').html(LNG['officeReader.yzOffice.convert']);
					}
				}
			}
			clearInterval(repeatTimer);
			taskStatus();
			repeatTimer = setInterval(taskStatus,600);
		};
		var loadSuccess = function(data){
			window.location.reload();
		}
		var loadError = function(error){
			$('.progress-slider,.progress-loading').hide();
			error += '&nbsp;&nbsp;<a class="load_repeat" href="javascript:void(0);">'+
			LNG['officeReader.yzOffice.transferAgain']+'</a>';
			$('.progress-text').addClass('alert-danger').html(error).css('text-align','left');
		}
		$(window).ready(function(){
			request();
			$('.load_repeat').live('click',function(){
				$.ajax({
					url:apiUrl.restart+"&time"+UUID(),
					dataType:'json',
					success:function(data){
						if(data.code){
							request();
						}
					}
				});
			});
		});
	</script>
</head>

