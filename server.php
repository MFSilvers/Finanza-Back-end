<?php
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

error_log("Server: Request {$method} {$uri}");
error_log("Server: HTTP_ORIGIN: " . ($_SERVER['HTTP_ORIGIN'] ?? 'N/A'));

if ($method === 'OPTIONS') {
    error_log("Server: Handling OPTIONS preflight in server.php");
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 3600');
    http_response_code(200);
    error_log("Server: OPTIONS response sent");
    exit;
}

if ($uri !== '/' && $uri !== '' && file_exists(__DIR__ . $uri) && !is_dir(__DIR__ . $uri)) {
    error_log("Server: Serving static file {$uri}");
    return false;
}

error_log("Server: Routing to index.php");
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/index.php';

