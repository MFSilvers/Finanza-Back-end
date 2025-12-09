<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno]: $errstr in $errfile:$errline");
    return false;
});

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        error_log("Fatal error: " . $error['message'] . " in " . $error['file'] . ":" . $error['line']);
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Internal server error']);
    }
});

// Load helpers to use setCorsHeaders and validateOrigin functions
try {
    require_once __DIR__ . '/utils/helpers.php';
} catch (Throwable $e) {
    error_log("Index: ERROR loading helpers.php - " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    echo json_encode(['error' => 'Server configuration error']);
    exit;
}

// Validate origin before processing request (except health check)
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$uri = parse_url($uri, PHP_URL_PATH) ?: '/';
if ($uri !== '/' && $uri !== '/health' && strpos($uri, '/health') !== 0) {
    // Validate origin for all API requests
    if (!validateOrigin()) {
        http_response_code(403);
        header("Content-Type: application/json");
        echo json_encode(['error' => 'Access denied. This API can only be accessed from the authorized frontend.']);
        exit;
    }
}

// Set CORS headers early - this handles OPTIONS preflight requests
try {
    setCorsHeaders();
} catch (Throwable $e) {
    error_log("Index: ERROR in setCorsHeaders() - " . $e->getMessage());
    
    // Allow health check even if CORS fails
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $uri = parse_url($uri, PHP_URL_PATH) ?: '/';
    $isHealthCheck = ($uri === '/' || $uri === '/health' || strpos($uri, '/health') === 0);
    
    if ($isHealthCheck) {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    } else {
        // For non-health-check endpoints, validate origin
        if (!validateOrigin()) {
            http_response_code(403);
            header("Content-Type: application/json");
            echo json_encode(['error' => 'Access denied.']);
            exit;
        }
        // Fallback CORS headers only for valid origins
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $envOrigins = getenv('ALLOWED_ORIGINS');
        if (empty($envOrigins)) {
            error_log("Security: ALLOWED_ORIGINS not configured in index.php fallback.");
            http_response_code(500);
            header("Content-Type: application/json");
            echo json_encode(['error' => 'Server configuration error: ALLOWED_ORIGINS not set']);
            exit();
        }
        $allowedOrigins = array_map('trim', explode(',', $envOrigins));
        if (in_array($origin, $allowedOrigins)) {
            header('Access-Control-Allow-Origin: ' . $origin);
        }
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }
}

// URI already parsed above for origin validation
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($uri === '/' || $uri === '/health' || strpos($uri, '/health') === 0) {
    // Health check endpoint - minimal information for security
    // Railway uses this for monitoring, so we keep it public but minimal
    error_log("Index: Health check endpoint");
    header('Content-Type: application/json');
    $response = [
        'status' => 'ok'
    ];
    error_log("Index: Sending health check response");
    echo json_encode($response);
    exit;
}

if ($uri === '/test') {
    error_log("Index: Test endpoint matched");
    header('Content-Type: application/json');
    $response = [
        'status' => 'ok',
        'message' => 'Test endpoint works',
        'uri' => $uri,
        'method' => $method,
        'php_version' => PHP_VERSION,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    error_log("Index: Sending test response");
    echo json_encode($response);
    exit;
}

@require_once __DIR__ . '/load_env.php';

$path = trim(parse_url($uri, PHP_URL_PATH), '/');

error_log("Index: Routing path: '{$path}', method: '{$method}'");
error_log("Index: Full REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A'));
error_log("Index: HTTP_ORIGIN: " . ($_SERVER['HTTP_ORIGIN'] ?? 'N/A'));

// OPTIONS requests are already handled by setCorsHeaders() above

try {
    if (strpos($path, 'api/auth') === 0) {
        $subPath = substr($path, 8);
        if ($subPath === '') $subPath = '/';
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
        $subPath = substr($path, 14);
        if ($subPath === '') $subPath = '/';
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
        $subPath = substr($path, 16);
        if ($subPath === '') $subPath = '/';
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
        $subPath = substr($path, 14);
        if ($subPath === '') $subPath = '/';
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
    } elseif (strpos($path, 'api/verify-code') === 0) {
        $subPath = substr($path, 14);
        if ($subPath === '') $subPath = '/';
        $_SERVER['PATH_INFO'] = $subPath;
        error_log("Index: Routing to api/verify-code.php with PATH_INFO: {$subPath}");
        if (file_exists(__DIR__ . '/api/verify-code.php')) {
            require __DIR__ . '/api/verify-code.php';
        } else {
            error_log("Index: ERROR - verify-code.php not found");
            http_response_code(500);
            echo json_encode(['error' => 'verify-code.php not found']);
            exit;
        }
    } elseif (strpos($path, 'api/resend-code') === 0) {
        $subPath = substr($path, 14);
        if ($subPath === '') $subPath = '/';
        $_SERVER['PATH_INFO'] = $subPath;
        error_log("Index: Routing to api/resend-code.php with PATH_INFO: {$subPath}");
        if (file_exists(__DIR__ . '/api/resend-code.php')) {
            require __DIR__ . '/api/resend-code.php';
        } else {
            error_log("Index: ERROR - resend-code.php not found");
            http_response_code(500);
            echo json_encode(['error' => 'resend-code.php not found']);
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
