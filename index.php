<?php

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Set error handler to prevent fatal errors from crashing the server
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno]: $errstr in $errfile on line $errline");
    return false; // Let PHP handle the error normally
});

// Set exception handler
set_exception_handler(function($exception) {
    error_log("Uncaught exception: " . $exception->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Internal server error',
        'message' => $exception->getMessage()
    ]);
    exit;
});

// Load environment variables (non-blocking)
try {
    require_once __DIR__ . '/load_env.php';
} catch (Exception $e) {
    error_log("Error loading env: " . $e->getMessage());
}

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

// Health check endpoint (works without database)
if ($path === '' || $path === 'health' || $path === 'api') {
    echo json_encode([
        'status' => 'ok',
        'message' => 'Finanze App API',
        'version' => '1.0.0',
        'timestamp' => date('Y-m-d H:i:s'),
        'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'php_version' => PHP_VERSION
    ]);
    exit;
}

// Route to appropriate API file
try {
    if (preg_match('#^api/auth(.*)$#', $path, $matches)) {
        $_SERVER['PATH_INFO'] = $matches[1] ?: '/';
        if (file_exists(__DIR__ . '/api/auth.php')) {
            require __DIR__ . '/api/auth.php';
        } else {
            throw new Exception('auth.php not found');
        }
    } 
    elseif (preg_match('#^api/categories(.*)$#', $path, $matches)) {
        $_SERVER['PATH_INFO'] = $matches[1] ?: '/';
        if (file_exists(__DIR__ . '/api/categories.php')) {
            require __DIR__ . '/api/categories.php';
        } else {
            throw new Exception('categories.php not found');
        }
    } 
    elseif (preg_match('#^api/transactions(.*)$#', $path, $matches)) {
        $_SERVER['PATH_INFO'] = $matches[1] ?: '/';
        if (file_exists(__DIR__ . '/api/transactions.php')) {
            require __DIR__ . '/api/transactions.php';
        } else {
            throw new Exception('transactions.php not found');
        }
    } 
    elseif (preg_match('#^api/statistics(.*)$#', $path, $matches)) {
        $_SERVER['PATH_INFO'] = $matches[1] ?: '/';
        if (file_exists(__DIR__ . '/api/statistics.php')) {
            require __DIR__ . '/api/statistics.php';
        } else {
            throw new Exception('statistics.php not found');
        }
    } 
    else {
        http_response_code(404);
        echo json_encode([
            'error' => 'Endpoint not found',
            'path' => $path,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'N/A',
            'available_endpoints' => ['/health', '/api/auth', '/api/categories', '/api/transactions', '/api/statistics']
        ]);
    }
} catch (Throwable $e) {
    error_log("Routing error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
