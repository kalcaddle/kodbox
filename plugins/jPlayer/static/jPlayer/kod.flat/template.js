var jplayerTemplateMovie =
'<div class="jPlayer jPlayer-movie">\
	<div class="playerScreen">\
		<div class="jPlayer-container"></div>\
		<div class="jPlayerMask"></div>\
		<a href="javascript:void(0);" class="video-play disable-ripple"><i class="play-icon ri-play-circle-line"></i></a>\
		<a href="javascript:void(0);" class="video-play-loading disable-ripple"><i class="play-icon ri-loader-line-4 moveCircleRight"></i></a>\
	</div>\
	<div class="current-time-tips"><span>00:00</span></div>\
	<div class="controls">\
		<div class="controlset left">\
			<a href="javascript:void(0);" class="play smooth disable-ripple"><i class="font-icon ri-play-fill"></i></a>\
			<a href="javascript:void(0);" class="pause smooth"><i class="font-icon ri-stop-fill"></i></a>\
		</div>\
		<div class="controlset right-volume">\
			<a href="javascript:void(0);" class="mute smooth"><i class="font-icon ri-volume-up-fill"></i></a>\
			<a href="javascript:void(0);" class="unmute smooth"><i class="font-icon ri-volume-mute-fill"></i></a>\
		</div>\
		<div class="volumeblock">\
			<div class="volume-control">\
				<div class="volume-value"></div>\
			</div>\
		</div>\
		<div class="controlset right">\
			<a href="javascript:void(0);" class="fullscreen smooth"><i class="font-icon ri-fullscreen-line"></i></a>\
			<a href="javascript:void(0);" class="smallscreen smooth"><i class=" font-icon ri-fullscreen-exit-line"></i></a>\
		</div>\
		<div class="progress-block">\
			<div class="timer current"></div>\
			<div class="timer duration"></div>\
			<div class="progress">\
				<div class="fullBar"></div>\
				<div class="seekBar">\
					<div class="playBar"></div>\
				</div>\
			</div>\
		</div>\
	</div>\
	<div class="wmp-player"></div>\
</div>';

var jplayerTemplateMusic =
'<div class="jPlayer jPlayer-music">\
	<div class="player-bg"></div>\
	<div class="playerScreen">\
		<div class="jPlayer-container"></div>\
		<div class="jPlayerMask"></div>\
	</div>\
	<div class="top-banner">\
		<div class="item-title">--</div>\
		<div class="control-actions">\
			<a href="javascript:void(0);" class="play-backward smooth"><i class="font-icon ri-rewind-fill"></i></a>\
			<a href="javascript:void(0);" class="play smooth"><i class="font-icon ri-play-fill"></i></a>\
			<a href="javascript:void(0);" class="pause smooth"><i class="font-icon ri-stop-fill"></i></a>\
			<a href="javascript:void(0);" class="play-forward smooth"><i class="font-icon ri-speed-fill"></i></a>\
			<div class="clear"></div>\
		</div>\
		<div class="current-time-tips"><span>00:00</span></div>\
		<!-- volume -->\
		<div class="controlset right-volume">\
			<a href="javascript:void(0);" class="mute smooth"><i class="font-icon ri-volume-up-fill"></i></a>\
			<a href="javascript:void(0);" class="unmute smooth"><i class="font-icon ri-volume-mute-fill"></i></a>\
			<div class="volumeblock">\
				<div class="volume-control">\
					<div class="volume-value"></div>\
				</div>\
			</div>\
		</div>\
		<!-- progress -->\
		<div class="progress">\
			<div class="fullBar"></div>\
			<div class="seekBar">\
				<div class="playBar"><i class="video-play-loading moveCircleRight font-icon ri-loader-line-4"></i></div>\
			</div>\
		</div>\
	</div>\
	<!-- info -->\
	<div class="play-tools">\
		<span class="time">\
			<span class="timer current"></span> / <span class="timer duration"></span>\
		</span>\
		<span class="right">\
			<span class="change-loop" data-loop="0"><i  class="font-icon ri-repeat-line"></i> </span>\
			<span class="show-list"><i  class="font-icon ri-picture-in-picture-line"></i> </span>\
		</span>\
		<div class="clear"></div>\
	</div>\
	<div class="play-list">\
		<ul class="content"></ul>\
	</div>\
	<div class="wmp-player"></div>\
</div>';
