<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/helpers.php';

setCorsHeaders();

try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    error_log("Verify Code: Database connection failed - " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database connection error. Please check server configuration.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['PATH_INFO'] ?? '/';

// POST /api/verify-code
if ($method === 'POST' && ($path === '/' || $path === '')) {
    $data = getJsonInput();
    
    validateRequired($data, ['email', 'code']);
    
    $email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
    $code = trim($data['code']);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendError('Invalid email format', 400);
    }
    
    if (strlen($code) !== 6 || !ctype_digit($code)) {
        sendError('Invalid code format. Code must be 6 digits.', 400);
    }
    
    // Get user with code
    $dbType = getenv('DB_TYPE') ?: 'mysql';
    if ($dbType === 'pgsql' || $dbType === 'postgres') {
        $stmt = $db->prepare("SELECT id, email_code, email_code_expires_at, email_verified FROM users WHERE email = ?");
    } else {
        $stmt = $db->prepare("SELECT id, email_code, email_code_expires_at, email_verified FROM users WHERE email = ?");
    }
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        sendError('User not found', 404);
    }
    
    if ($user['email_verified']) {
        sendError('Email already verified', 400);
    }
    
    if (empty($user['email_code'])) {
        sendError('No verification code found. Please request a new code.', 400);
    }
    
    // Check if code matches
    if ($user['email_code'] !== $code) {
        sendError('Invalid verification code', 400);
    }
    
    // Check if code is expired
    $now = new DateTime();
    $expiresAt = new DateTime($user['email_code_expires_at']);
    
    if ($now > $expiresAt) {
        sendError('Verification code has expired. Please request a new code.', 400);
    }
    
    // Verify email
    $dbType = getenv('DB_TYPE') ?: 'mysql';
    if ($dbType === 'pgsql' || $dbType === 'postgres') {
        // PostgreSQL - use boolean true
        $stmt = $db->prepare("UPDATE users SET email_verified = true, email_code = NULL, email_code_expires_at = NULL WHERE id = ?");
    } else {
        // MySQL - use integer 1
        $stmt = $db->prepare("UPDATE users SET email_verified = 1, email_code = NULL, email_code_expires_at = NULL WHERE id = ?");
    }
    $stmt->execute([$user['id']]);
    
    error_log("Verify Code: Email verified successfully for {$email}");
    
    sendJsonResponse([
        'success' => true,
        'message' => 'Email verified successfully'
    ]);
} else {
    sendError('Endpoint not found', 404);
}

