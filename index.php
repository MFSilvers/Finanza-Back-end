<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$uri = parse_url($uri, PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

error_log("Index: Processing {$method} {$uri}");

if ($uri === '/' || $uri === '/health' || strpos($uri, '/health') === 0) {
    error_log("Index: Health check endpoint");
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    $response = [
        'status' => 'ok', 
        'message' => 'API is running', 
        'uri' => $uri, 
        'method' => $method,
        'php_version' => PHP_VERSION,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    error_log("Index: Sending response: " . json_encode($response));
    echo json_encode($response);
    exit;
}

@require_once __DIR__ . '/load_env.php';

$path = trim(parse_url($uri, PHP_URL_PATH), '/');

error_log("Index: Routing path: {$path}");

try {
    if (strpos($path, 'api/auth') === 0) {
        $subPath = substr($path, 8) ?: '/';
        $_SERVER['PATH_INFO'] = $subPath;
        error_log("Index: Routing to api/auth.php with PATH_INFO: {$subPath}");
        if (file_exists(__DIR__ . '/api/auth.php')) {
            require __DIR__ . '/api/auth.php';
        } else {
            error_log("Index: ERROR - auth.php not found");
            http_response_code(500);
            echo json_encode(['error' => 'auth.php not found']);
            exit;
        }
    } elseif (strpos($path, 'api/categories') === 0) {
        $subPath = substr($path, 14) ?: '/';
        $_SERVER['PATH_INFO'] = $subPath;
        error_log("Index: Routing to api/categories.php with PATH_INFO: {$subPath}");
        if (file_exists(__DIR__ . '/api/categories.php')) {
            require __DIR__ . '/api/categories.php';
        } else {
            error_log("Index: ERROR - categories.php not found");
            http_response_code(500);
            echo json_encode(['error' => 'categories.php not found']);
            exit;
        }
    } elseif (strpos($path, 'api/transactions') === 0) {
        $subPath = substr($path, 16) ?: '/';
        $_SERVER['PATH_INFO'] = $subPath;
        error_log("Index: Routing to api/transactions.php with PATH_INFO: {$subPath}");
        if (file_exists(__DIR__ . '/api/transactions.php')) {
            require __DIR__ . '/api/transactions.php';
        } else {
            error_log("Index: ERROR - transactions.php not found");
            http_response_code(500);
            echo json_encode(['error' => 'transactions.php not found']);
            exit;
        }
    } elseif (strpos($path, 'api/statistics') === 0) {
        $subPath = substr($path, 14) ?: '/';
        $_SERVER['PATH_INFO'] = $subPath;
        error_log("Index: Routing to api/statistics.php with PATH_INFO: {$subPath}");
        if (file_exists(__DIR__ . '/api/statistics.php')) {
            require __DIR__ . '/api/statistics.php';
        } else {
            error_log("Index: ERROR - statistics.php not found");
            http_response_code(500);
            echo json_encode(['error' => 'statistics.php not found']);
            exit;
        }
    } else {
        error_log("Index: 404 - Path not found: {$path}");
        http_response_code(404);
        echo json_encode(['error' => 'Not found', 'path' => $path]);
        exit;
    }
} catch (Throwable $e) {
    error_log("Index: FATAL ERROR - " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    error_log("Index: Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
    exit;
}
