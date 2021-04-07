<!DOCTYPE html>
<html dir="ltr" mozdisallowselectionprint moznomarginboxes>
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
		<meta name="google" content="notranslate">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<title><?php echo $fileName;?></title>
		<?php $this->link('static/Djvu/style.css');?>
		<?php $this->link('static/Djvu/render.js');?>
		<?php $this->link('static/ofd/lib/jquery.min.js');?>
		<?php $this->link('static/Djvu/add.js');?>
	</head>
	<body>
		<div id="djvuContainer"></div>
		<script type="text/javascript">
			var DJVU_CONTEXT = {
				file: "<?php echo $fileUrl;?>",
				canDownload:"<?php echo intval($canDownload);?>",
				background: "#404040"
			};
		</script>
	</body>
</html>