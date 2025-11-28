<?php

function validateOrigin() {
    
    $allowedOrigins = [
        'https://finanza-flax.vercel.app'
    ];
    
    // $allowedOrigins[] = 'http://localhost:5173';
    // $allowedOrigins[] = 'http://localhost:3000';
    
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    
    // Allow health check endpoint without origin validation
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $uri = parse_url($uri, PHP_URL_PATH) ?: '/';
    if ($uri === '/' || $uri === '/health' || strpos($uri, '/health') === 0) {
        return true;
    }
    
    // For API requests, require valid origin
    if (empty($origin)) {
        // No origin header - could be direct API call or server-to-server
        // Check referer as fallback
        if (!empty($referer)) {
            $refererHost = parse_url($referer, PHP_URL_HOST);
            $allowedHosts = array_map(function($url) {
                return parse_url($url, PHP_URL_HOST);
            }, $allowedOrigins);
            
            if (!in_array($refererHost, $allowedHosts)) {
                error_log("Security: Blocked request - Invalid referer: {$refererHost}");
                return false;
            }
        } else {
            // No origin and no referer - block direct API calls
            error_log("Security: Blocked request - No origin or referer header");
            return false;
        }
    } else {
        // Check if origin is in allowed list
        if (!in_array($origin, $allowedOrigins)) {
            error_log("Security: Blocked request - Invalid origin: {$origin}");
            return false;
        }
    }
    
    return true;
}

function setCorsHeaders() {
    // Only allow requests from the production frontend
    $allowedOrigins = [
        'https://finanza-flax.vercel.app'
    ];
    
    // Optional: Add localhost for local development (uncomment if needed)
    // $allowedOrigins[] = 'http://localhost:5173';
    // $allowedOrigins[] = 'http://localhost:3000';
    
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $isOptions = $method === 'OPTIONS';
    
    // Validate origin first
    if (!validateOrigin()) {
        http_response_code(403);
        header("Content-Type: application/json");
        echo json_encode(['error' => 'Access denied. Invalid origin.']);
        exit();
    }
    
    // If origin is in allowed list, use it
    if (in_array($origin, $allowedOrigins)) {
        header("Access-Control-Allow-Origin: " . $origin);
        header("Access-Control-Allow-Credentials: true");
    } elseif (!empty($origin)) {
        // This shouldn't happen if validateOrigin() works correctly, but as fallback
        header("Access-Control-Allow-Origin: " . $origin);
    } else {
        // No origin header - only allow for health check (already validated)
        header("Access-Control-Allow-Origin: *");
    }
    
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    header("Access-Control-Max-Age: 3600");
    
    // Handle preflight OPTIONS request
    if ($isOptions) {
        error_log("setCorsHeaders: Handling OPTIONS preflight request");
        http_response_code(200);
        // Clean any output buffers if they exist
        if (function_exists('ob_get_level')) {
            $level = @ob_get_level();
            while ($level > 0) {
                @ob_end_clean();
                $level = @ob_get_level();
            }
        }
        exit();
    }
    
    // Set Content-Type for non-OPTIONS requests
    header("Content-Type: application/json; charset=UTF-8");
}

function getJsonInput() {
    $input = file_get_contents('php://input');
    return json_decode($input, true);
}

function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit();
}

function sendError($message, $statusCode = 400) {
    http_response_code($statusCode);
    echo json_encode(['error' => $message]);
    exit();
}

function validateRequired($data, $fields) {
    foreach ($fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            sendError("Field '$field' is required", 400);
        }
    }
}
