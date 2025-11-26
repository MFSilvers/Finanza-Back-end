<?php

require_once __DIR__ . '/load_env.php';

// Simple router for API endpoints
$requestUri = $_SERVER['REQUEST_URI'];
$scriptName = dirname($_SERVER['SCRIPT_NAME']);

// Remove script path from request URI
$path = str_replace($scriptName, '', $requestUri);

// Remove query string
$path = strtok($path, '?');

// Route to appropriate API file
if (preg_match('#^/api/auth(/.*)?$#', $path)) {
    $_SERVER['PATH_INFO'] = preg_replace('#^/api/auth#', '', $path);
    require __DIR__ . '/api/auth.php';
} 
elseif (preg_match('#^/api/categories(/.*)?$#', $path)) {
    $_SERVER['PATH_INFO'] = preg_replace('#^/api/categories#', '', $path);
    require __DIR__ . '/api/categories.php';
} 
elseif (preg_match('#^/api/transactions(/.*)?$#', $path)) {
    $_SERVER['PATH_INFO'] = preg_replace('#^/api/transactions#', '', $path);
    require __DIR__ . '/api/transactions.php';
} 
elseif (preg_match('#^/api/statistics(/.*)?$#', $path)) {
    $_SERVER['PATH_INFO'] = preg_replace('#^/api/statistics#', '', $path);
    require __DIR__ . '/api/statistics.php';
} 
else {
    header('Content-Type: application/json');
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint not found']);
}
