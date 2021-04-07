<!DOCTYPE>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0;" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<title><?php echo $fileName;?></title>
	<?php $this->link('static/ofd/lib/jquery.min.js');?>
	<?php $this->link('static/ofd/lib/jszip.min.js');?>
	<?php $this->link('static/ofd/lib/jszip-utils.min.js');?>
	<?php $this->link('static/ofd/lib/int10.js');?>
	<?php $this->link('static/ofd/lib/asn1.js');?>
	<?php $this->link('static/ofd/lib/touch.js');?>
	<?php $this->link('static/ofd/lib/ofd.js');?>
	<?php $this->link('static/ofd/css/lib.css');?>
	<?php $this->link('static/ofd/css/main.css');?>
	<?php $this->link('static/ofd/css/add.css');?>
	<style>.ant-tabs-bar{display: flex;height: 40px;}</style>
</head>

<body>
	<div id="root"></div>
	<script>
		var ofdReaderParams = {
			file: "<?php echo $fileUrl;?>",
			canDownload:"<?php echo intval($canDownload);?>"
		};
	</script>
	<?php $this->link('static/ofd/load.js');?>
	<?php $this->link('static/ofd/vendor.js');?>
	<?php $this->link('static/ofd/main.js');?>
	<?php $this->link('static/ofd/add.js');?>
</body>
</html>