<?php

echo "Exporting wasm-php...\n";

// Find project root (where composer.json is)
$rootDir = getcwd();
while (!file_exists($rootDir . '/composer.json') && $rootDir !== '/') {
    $rootDir = dirname($rootDir);
}

if ($rootDir === '/' || !file_exists($rootDir . '/composer.json')) {
    fwrite(STDERR, "Error: Could not find project root (no composer.json found).\n");
    exit(1);
}

$publicBuildDir = $rootDir . '/public/build';

if (!is_dir($publicBuildDir)) {
    mkdir($publicBuildDir, 0777, true);
}

copy($rootDir . '/build/php-vrzno-web.wasm', $publicBuildDir . '/php-vrzno-web.wasm');
copy($rootDir . '/build/php-web.data', $publicBuildDir . '/php-web.data');

$data = file_get_contents($rootDir . '/build/php-web.data.js');
$mjs = file_get_contents($rootDir . '/build/php-vrzno-web.mjs');
$mjs = str_replace($p = 'var moduleOverrides = Object.assign({}, Module);', "{$p}\n{$data}", $mjs);
$baseUrl = $_SERVER['BASE_URL'] ?? '';
$mjs = str_replace("var REMOTE_PACKAGE_BASE = 'php-web.data';", "var REMOTE_PACKAGE_BASE = '{$baseUrl}/build/php-web.data';", $mjs);
file_put_contents($publicBuildDir . '/php-vrzno-web.mjs', $mjs);

echo "Export completed successfully!\n";
