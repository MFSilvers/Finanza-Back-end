<?php

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Load environment variables
require_once __DIR__ . '/load_env.php';

// Enable CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 3600');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get the request URI
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';

// Remove query string
$requestUri = strtok($requestUri, '?');

// Remove leading slash if present
$path = ltrim($requestUri, '/');

// Health check endpoint
if ($path === '' || $path === 'health') {
    echo json_encode([
        'status' => 'ok',
        'message' => 'Finanze App API',
        'version' => '1.0.0',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Route to appropriate API file
try {
    if (preg_match('#^api/auth(/.*)?$#', $path)) {
        $_SERVER['PATH_INFO'] = preg_replace('#^api/auth#', '', '/' . $path);
        require __DIR__ . '/api/auth.php';
    } 
    elseif (preg_match('#^api/categories(/.*)?$#', $path)) {
        $_SERVER['PATH_INFO'] = preg_replace('#^api/categories#', '', '/' . $path);
        require __DIR__ . '/api/categories.php';
    } 
    elseif (preg_match('#^api/transactions(/.*)?$#', $path)) {
        $_SERVER['PATH_INFO'] = preg_replace('#^api/transactions#', '', '/' . $path);
        require __DIR__ . '/api/transactions.php';
    } 
    elseif (preg_match('#^api/statistics(/.*)?$#', $path)) {
        $_SERVER['PATH_INFO'] = preg_replace('#^api/statistics#', '', '/' . $path);
        require __DIR__ . '/api/statistics.php';
    } 
    else {
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found', 'path' => $path]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ]);
}
