<?php
// Run with `php tests/share-root-path.php` in a configured kodbox installation.
require dirname(__DIR__).'/config/config.php';
think_config($config['databaseDefault']);
think_config($config['database']);
require_once CONTROLLER_DIR.'explorer/share.class.php';

$controller = new ReflectionClass('explorerShare');
$share = $controller->newInstanceWithoutConstructor();
$parsePath = $controller->getMethod('parsePath');
$parsePath->setAccessible(true);
$root = sys_get_temp_dir().'/kodbox-share-path-'.uniqid();
mkdir($root);
mkdir($root.'/folder');
file_put_contents($root.'/file.txt', 'shared file');
file_put_contents($root.'/folder/child.txt', 'folder child');

function checkSharePath($source, $suffix, $expected, $allowNotExist = false) {
    global $share, $parsePath;
    $share->share = array(
        'shareHash' => 'test-root',
        'sourceInfo' => array('path' => $source),
    );
    $actual = $parsePath->invoke($share, '{shareItemLink:test-root}'.$suffix, $allowNotExist);
    if ($actual !== $expected) {
        throw new RuntimeException('Unexpected path: '.var_export($actual, true));
    }
}

try {
    // Actual local filesystem IO: file roots must not acquire a trailing slash.
    checkSharePath($root.'/file.txt', '', $root.'/file.txt');
    checkSharePath($root.'/file.txt', '/', $root.'/file.txt');
    // Directory roots and child paths retain their existing behavior.
    checkSharePath($root.'/folder/', '/', $root.'/folder/');
    checkSharePath($root.'/folder/', '/child.txt', $root.'/folder/child.txt');
    checkSharePath($root.'/folder/', '/new.txt', $root.'/folder//new.txt', true);
    // The allowNotExist fallback must use the same root path as the IO lookup.
    checkSharePath($root.'/missing.txt', '/', $root.'/missing.txt', true);
    echo "PASS: six share root / child path integration checks\n";
} finally {
    unlink($root.'/file.txt');
    unlink($root.'/folder/child.txt');
    rmdir($root.'/folder');
    rmdir($root);
}
