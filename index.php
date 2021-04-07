<?php
	ob_start();
	include ('config/config.php');
	$app = new Application();
	$app->setDefault('user.index.index');
	$app->run();
?>