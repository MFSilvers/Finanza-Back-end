<?php

$request_uri = $_SERVER['REQUEST_URI'] ?? '/';
$request_path = parse_url($request_uri, PHP_URL_PATH) ?: '/';

if (preg_match('/\.php$/', $request_path)) {
    $file_path = __DIR__ . $request_path;
    if (file_exists($file_path)) {
        require $file_path;
        return;
    }
}

if (file_exists(__DIR__ . $request_path) && !is_dir(__DIR__ . $request_path)) {
    return false;
}

require __DIR__ . '/index.php';

