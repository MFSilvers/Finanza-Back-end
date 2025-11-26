<?php

require_once __DIR__ . '/../utils/JWT.php';

function authenticate() {
    $headers = getallheaders();
    
    if (!isset($headers['Authorization'])) {
        http_response_code(401);
        echo json_encode(['error' => 'No authorization token provided']);
        exit();
    }
    
    $authHeader = $headers['Authorization'];
    $arr = explode(" ", $authHeader);
    
    if (count($arr) !== 2 || $arr[0] !== 'Bearer') {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid authorization format']);
        exit();
    }
    
    $jwt = $arr[1];
    $decoded = JWT::decode($jwt);
    
    if (!$decoded) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid or expired token']);
        exit();
    }
    
    return $decoded;
}

function getUserIdFromToken() {
    $decoded = authenticate();
    return $decoded['user_id'];
}
