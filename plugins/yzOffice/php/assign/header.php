<!DOCTYPE html>
<html>
<head>
<?php $this->link();?>
<style type="text/css">
	html body{
		font-family:-apple-system,BlinkMacSystemFont,PingFang SC,
			Helvetica,Tahoma,Arial,Hiragino Sans GB,Microsoft YaHei,Heiti,sans-serif;
		background-image:none !important;
		/*background-color:#f0f0f0;*/
	}
	.powerby{text-align: center;font-size:12px;color:#ccc;}
	div.word-body, .ppt-body{background:#f9f9f9;}
	/* div.word-page{border-bottom:1px solid #d9d9d9;box-shadow:0 1px 6px #ccc; max-width: 845px;} */
	div.word-page{border-bottom:1px solid #d9d9d9;box-shadow:0 1px 6px #ccc; }
	div.word-page .word-content{max-width: 85%;}}
	div.navbar{display:none;}
	body.fullScreen div.word-page{max-width: 100%;border: none;}

	
	body > div,body > hr{display:none;}
	body div.container-fluid,
	body div.navbar-inverse,
	body #header,
	div.openSide #loading,
	div.openSide #header
	{display:block;}

	div.navbar-inverse{opacity:1;}
	div.openSide .side-pager {
		cursor: pointer;display: inline-block;font-size: 12px;color: #666;font-weight: 100;
		background: #eee;padding: 0px 1em;margin-top: 4px;border-radius: 18px;
	}
	div#next, div#prev{
		border-radius:4px;text-decoration: inherit;
		font-family: FontAwesome;
		font-weight: normal;font-style: normal;
		-webkit-font-smoothing: antialiased;
		color: rgba(0,0,0,0.001);
	}

	div.navbar-inverse .navbar-inner {
		background:#eee;filter:none;
	    background-image: linear-gradient(to bottom,#fff,#eee);
	    border-bottom: 1px solid #ddd;
	    background-repeat: repeat-x;
	}
	div.navbar-inverse .nav>li>a {color: #666;text-shadow: none;}
	div.navbar-inverse .brand{color: #666;text-shadow: none;width:40%;margin-left:0px;}
	div.changePage{color:#888;}

	div.navbar .nav{position: static;}
	.nav.word-tab-title .dropdown.word-tab-title-li{position:absolute;left:0px;}
	ul.dropdown-menu{margin-top:0;border-radius:2px;border: 1px solid rgba(0,0,0,0.1);}
	ul.dropdown-menu>li>a:hover, 
	ul.dropdown-menu>li>a:focus, 
	ul.dropdown-submenu:hover>a, 
	ul.dropdown-submenu:focus>a{background:#2196F3 !important}

	div.navbar-inverse .nav>li>a:focus, 
	div.navbar-inverse .nav>li>a:hover,
	div.left a:hover,div.right a:hover{
	    color: #2196F3;background-color:rgba(0,0,0,0.05);
	}
	div.left,div.right{margin-right:5px;}
	div.left a,div.right a{width:40px;text-align: center;display: block;}

	div.navbar-inverse .brand:hover, 
	div.navbar-inverse .nav>li>a:hover, 
	div.navbar-inverse .brand:focus, 
	div.navbar-inverse .nav>li>a:focus{
		color: #2196F3;
	}

	div.navbar-inverse .nav .active>a, 
	div.navbar-inverse .nav .active>a:hover, 
	div.navbar-inverse .nav .active>a:focus {
	    color: #fff !important;background: #2196F3 !important;
	}

	#next:after, #prev:after{color:#222;margin-left:-25px;}
	/*excle*/
	@media (max-width: 979px) {
		div.navbar-inverse .nav-collapse .nav>li>a:hover, 
		div.navbar-inverse .nav-collapse .nav>li>a:focus, 
		div.navbar-inverse .nav-collapse .dropdown-menu a:hover, 
		div.navbar-inverse .nav-collapse .dropdown-menu a:focus{
			background-color: #d4ecff;
		}
	}
	#btnPrint{filter: invert(60%);}
	#zoom{display:none !important;}
	.changePage .pageUp:after{content: "";font-family: FontAwesome;}
	.changePage .pageDown:after {content: "";font-family: FontAwesome;}
	.changePage .pageUp,.changePage .pageDown{font-size:16px;background:transparent !important;color:#888;text-decoration: none;}
	.changePage .pageUp:hover,.changePage .pageDown:hover{background:#444;}
	

	@media print {
		body .powerby{display:none !important;}
		body #zoom{display:none !important;}
		body #btnPrint{filter: invert(60%);}
	}


	/*word*/
	@media (max-width: 743px) {
		div.word-page,div.word-page .word-content{border-bottom:none;box-shadow:none;}
		/*.container-fluid{padding:20px 10px;}*/
	}

	div.navbar-inverse{background: none !important;border:none !important;}
	div.navbar-inverse .brand {width: 40% !important;}
	div.changePage .activePage {height:16px;line-height:16px;}
	div.container-fluid.container-fluid-content{background:#eeeef4;}
	div.word-page {
		border: none;
		box-shadow: 0 2px 10px rgba(0,0,0,0.1);
		background: #fff;
		border-radius: 2px;
	}
	div #rotateLeft,div #rotateRight,
	div .pageUp,div .pageDown{
		width: 30px;height: 30px;line-height:30px;
		text-align: center;
		margin-top: 6px;
		padding: 0 !important;
		border-radius: 4px;
		text-decoration: none;
    	color: #bbb;
	}
	div #rotateLeft:hover,div #rotateRight:hover,
	div .pageUp:hover,div .pageDown:hover{
		background:rgba(0,0,0,0.1) !important;
	}
	div .pageUp,div .pageDown{margin-top:-5px;}
	#rotateLeft img,#rotateRight img{display:none;}
	
	#prev:after,.changePage .pageUp:after{content:"\ea64";font-family: 'remixicon';}
	.changePage .pageDown:after,#next:after{content:"\ea6e";font-family: 'remixicon';}
	#rotateLeft:before{content:"\ea3a";font-family: 'remixicon';}
	#rotateRight:before{content:"\eb93";font-family: 'remixicon';}
	<?php 
		$doc = array('doc','docx','docm','dot','dotx','dotm','rtf',  'wps','wpt');
		if(in_array(get_path_ext($app->filePath),$doc)){
			echo '
			html body{background-color:#f0f0f0;}
			div.powerby{display:block;}
			';
		}
	?>
</style>
<script type="text/javascript">
	if(!window.addEventListener){
		window.addEventListener = window.attachEvent;
	}
</script>