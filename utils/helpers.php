<?php

function setCorsHeaders() {
    $allowedOrigins = [
        'https://finanza-flax.vercel.app',
        'http://localhost:5173',
        'http://localhost:3000'
    ];
    
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $isOptions = $method === 'OPTIONS';
    
    // If origin is in allowed list, use it. Otherwise, don't set origin header
    // (browsers will reject if credentials=true and origin=*)
    if (in_array($origin, $allowedOrigins)) {
        header("Access-Control-Allow-Origin: " . $origin);
        header("Access-Control-Allow-Credentials: true");
    } elseif (!empty($origin)) {
        // For other origins, allow but without credentials
        header("Access-Control-Allow-Origin: " . $origin);
    } else {
        // No origin header (e.g., direct API call), allow all
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
