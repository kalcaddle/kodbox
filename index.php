<?php
	ob_start();
	include(dirname(__FILE__).'/config/config.php');
	$app = new Application();
	$app->setDefault('user.index.index');
	$app->run();
?>