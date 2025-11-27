<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$uri = parse_url($uri, PHP_URL_PATH) ?: '/';

if ($uri === '/' || $uri === '/health' || strpos($uri, '/health') === 0) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    echo json_encode([
        'status' => 'ok', 
        'message' => 'API is running', 
        'uri' => $uri, 
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'N/A',
        'php_version' => PHP_VERSION
    ]);
    exit;
}

@require_once __DIR__ . '/load_env.php';

$path = trim(parse_url($uri, PHP_URL_PATH), '/');

try {
    if (strpos($path, 'api/auth') === 0) {
        $subPath = substr($path, 8) ?: '/';
        $_SERVER['PATH_INFO'] = $subPath;
        if (file_exists(__DIR__ . '/api/auth.php')) {
            require __DIR__ . '/api/auth.php';
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'auth.php not found']);
            exit;
        }
    } elseif (strpos($path, 'api/categories') === 0) {
        $subPath = substr($path, 14) ?: '/';
        $_SERVER['PATH_INFO'] = $subPath;
        if (file_exists(__DIR__ . '/api/categories.php')) {
            require __DIR__ . '/api/categories.php';
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'categories.php not found']);
            exit;
        }
    } elseif (strpos($path, 'api/transactions') === 0) {
        $subPath = substr($path, 16) ?: '/';
        $_SERVER['PATH_INFO'] = $subPath;
        if (file_exists(__DIR__ . '/api/transactions.php')) {
            require __DIR__ . '/api/transactions.php';
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'transactions.php not found']);
            exit;
        }
    } elseif (strpos($path, 'api/statistics') === 0) {
        $subPath = substr($path, 14) ?: '/';
        $_SERVER['PATH_INFO'] = $subPath;
        if (file_exists(__DIR__ . '/api/statistics.php')) {
            require __DIR__ . '/api/statistics.php';
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'statistics.php not found']);
            exit;
        }
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Not found', 'path' => $path]);
        exit;
    }
} catch (Throwable $e) {
    error_log("Fatal error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
}
