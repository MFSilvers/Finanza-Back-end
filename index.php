<?php

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Load environment variables
require_once __DIR__ . '/load_env.php';

// Enable CORS - Allow requests from Vercel frontend
$allowedOrigins = [
    'https://finanza-flax.vercel.app',
    'http://localhost:5173',
    'http://localhost:3000'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: *');
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 3600');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get the request URI - handle different server configurations
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';

// Remove script path from request URI if present
if ($scriptName !== '/index.php' && strpos($requestUri, $scriptName) === 0) {
    $requestUri = substr($requestUri, strlen(dirname($scriptName)));
    if ($requestUri === '') $requestUri = '/';
}

// Remove query string
$requestUri = strtok($requestUri, '?');

// Normalize path
$path = trim($requestUri, '/');

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
    if (preg_match('#^api/auth(.*)$#', $path, $matches)) {
        $_SERVER['PATH_INFO'] = $matches[1] ?: '/';
        require __DIR__ . '/api/auth.php';
    } 
    elseif (preg_match('#^api/categories(.*)$#', $path, $matches)) {
        $_SERVER['PATH_INFO'] = $matches[1] ?: '/';
        require __DIR__ . '/api/categories.php';
    } 
    elseif (preg_match('#^api/transactions(.*)$#', $path, $matches)) {
        $_SERVER['PATH_INFO'] = $matches[1] ?: '/';
        require __DIR__ . '/api/transactions.php';
    } 
    elseif (preg_match('#^api/statistics(.*)$#', $path, $matches)) {
        $_SERVER['PATH_INFO'] = $matches[1] ?: '/';
        require __DIR__ . '/api/statistics.php';
    } 
    else {
        http_response_code(404);
        echo json_encode([
            'error' => 'Endpoint not found',
            'path' => $path,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'N/A'
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ]);
}
