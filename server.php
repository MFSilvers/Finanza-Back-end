<?php

$request_uri = $_SERVER['REQUEST_URI'] ?? '/';
$request_path = parse_url($request_uri, PHP_URL_PATH) ?: '/';

// Handle direct PHP file requests
if (preg_match('/\.php$/', $request_path)) {
    $file_path = __DIR__ . $request_path;
    if (file_exists($file_path)) {
        require $file_path;
        return;
    }
}

// Serve static files if they exist
if (file_exists(__DIR__ . $request_path) && !is_dir(__DIR__ . $request_path)) {
    return false;
}

// Route all other requests (including API routes and OPTIONS) to index.php
require __DIR__ . '/index.php';

