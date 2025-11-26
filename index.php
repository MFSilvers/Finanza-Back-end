<?php

// Simple test - always works
if ($_SERVER['REQUEST_URI'] === '/' || $_SERVER['REQUEST_URI'] === '/health') {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    echo json_encode(['status' => 'ok', 'message' => 'API is running']);
    exit;
}

// Load environment variables
@require_once __DIR__ . '/load_env.php';

// Enable CORS
$allowedOrigins = ['https://finanza-flax.vercel.app', 'http://localhost:5173'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get path
$path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');

// Route
if (strpos($path, 'api/auth') === 0) {
    $_SERVER['PATH_INFO'] = '/' . substr($path, 8);
    @require __DIR__ . '/api/auth.php';
} elseif (strpos($path, 'api/categories') === 0) {
    $_SERVER['PATH_INFO'] = '/' . substr($path, 14);
    @require __DIR__ . '/api/categories.php';
} elseif (strpos($path, 'api/transactions') === 0) {
    $_SERVER['PATH_INFO'] = '/' . substr($path, 16);
    @require __DIR__ . '/api/transactions.php';
} elseif (strpos($path, 'api/statistics') === 0) {
    $_SERVER['PATH_INFO'] = '/' . substr($path, 14);
    @require __DIR__ . '/api/statistics.php';
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Not found', 'path' => $path]);
}
