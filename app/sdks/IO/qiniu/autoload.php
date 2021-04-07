<?php

function qnClassLoader($class)
{
    $path = str_replace('\\', DIRECTORY_SEPARATOR, $class);
    // $file = __DIR__ . '/src/' . $path . '.php';
	$file = __DIR__ . '/' . $path . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
}
spl_autoload_register('qnClassLoader');

require_once  __DIR__ . '/Qiniu/functions.php';
