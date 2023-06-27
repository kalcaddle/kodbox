<?php

class PathDriverSamba extends PathDriverLocal{
	public function __construct($config) {
		parent::__construct();
		$pluginOption = Model("Plugin")->getConfig('webdav');
	}
}
