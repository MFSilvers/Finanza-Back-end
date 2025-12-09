<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/helpers.php';

setCorsHeaders();

try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    error_log("Resend Code: Database connection failed - " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database connection error. Please check server configuration.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['PATH_INFO'] ?? '/';

// POST /api/resend-code
if ($method === 'POST' && $path === '/') {
    $data = getJsonInput();
    
    validateRequired($data, ['email']);
    
    $email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendError('Invalid email format', 400);
    }
    
    // Get user
    $stmt = $db->prepare("SELECT id, name, email_verified FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        sendError('User not found', 404);
    }
    
    if ($user['email_verified']) {
        sendError('Email already verified', 400);
    }
    
    // Generate new 6-digit verification code
    $verificationCode = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
    
    // Update code and expiration
    $dbType = getenv('DB_TYPE') ?: 'mysql';
    if ($dbType === 'pgsql' || $dbType === 'postgres') {
        // PostgreSQL
        $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        $stmt = $db->prepare("UPDATE users SET email_code = ?, email_code_expires_at = ? WHERE id = ?");
        $stmt->execute([$verificationCode, $expiresAt, $user['id']]);
    } else {
        // MySQL
        $stmt = $db->prepare("UPDATE users SET email_code = ?, email_code_expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE) WHERE id = ?");
        $stmt->execute([$verificationCode, $user['id']]);
    }
    
    // Send verification code email
    sendVerificationCodeEmail($email, $user['name'], $verificationCode);
    
    error_log("Resend Code: New verification code sent to {$email}");
    
    sendJsonResponse([
        'success' => true,
        'message' => 'Verification code sent successfully'
    ]);
} else {
    sendError('Endpoint not found', 404);
}

