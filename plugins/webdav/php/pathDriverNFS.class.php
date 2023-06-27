<?php

class PathDriverNFS extends PathDriverLocal{
	public function __construct($config) {
		parent::__construct();
		$pluginOption = Model("Plugin")->getConfig('webdav');
	}
}
