<?php
	//Hide X-Powered-By PHP
	header_remove("X-Powered-By");
	ob_start();
	include ('config/config.php');
	$app = new Application();
	$app->setDefault('user.index.index');
	$app->run();
?>
