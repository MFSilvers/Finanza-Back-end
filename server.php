<?php
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

error_log("Server: Request {$method} {$uri}");

if ($uri !== '/' && $uri !== '' && file_exists(__DIR__ . $uri) && !is_dir(__DIR__ . $uri)) {
    error_log("Server: Serving static file {$uri}");
    return false;
}

error_log("Server: Routing to index.php");
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/index.php';

