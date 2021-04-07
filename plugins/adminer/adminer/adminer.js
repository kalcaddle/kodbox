(function(){
	$(window).ready(function(){
		var $main = $('body');
		$('<div class="toggle-menu"></div>').appendTo($main);
		if($(window).width() < 769){
			$main.addClass('app-page-small');
			$main.addClass('menu-hide');
		}
		$('.toggle-menu').bind('click',function(){
			$main.toggleClass('menu-hide');
		});
	});
})();