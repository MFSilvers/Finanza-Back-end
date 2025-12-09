<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../middleware/auth.php';

setCorsHeaders();

try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    error_log("Auth: Database connection failed - " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database connection error. Please check server configuration.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['PATH_INFO'] ?? '/';

// POST /api/auth/register
if ($method === 'POST' && $path === '/register') {
    $data = getJsonInput();
    
    validateRequired($data, ['email', 'password', 'name']);
    
    // Validate email format
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        sendError('Invalid email format', 400);
    }
    
    // Check if email already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$data['email']]);
    
    if ($stmt->fetch()) {
        sendError('Email already registered', 409);
    }
    
    // Hash password
    $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
    
    // Generate 6-digit verification code
    $verificationCode = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
    
    // Calculate expiration time (10 minutes from now)
    $dbType = getenv('DB_TYPE') ?: 'mysql';
    if ($dbType === 'pgsql' || $dbType === 'postgres') {
        // PostgreSQL - use boolean false
        $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        $stmt = $db->prepare("INSERT INTO users (email, password_hash, name, email_code, email_verified, email_code_expires_at) VALUES (?, ?, ?, ?, false, ?)");
        $stmt->execute([$data['email'], $passwordHash, $data['name'], $verificationCode, $expiresAt]);
    } else {
        // MySQL - use integer 0
        $stmt = $db->prepare("INSERT INTO users (email, password_hash, name, email_code, email_verified, email_code_expires_at) VALUES (?, ?, ?, ?, 0, DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
        $stmt->execute([$data['email'], $passwordHash, $data['name'], $verificationCode]);
    }
    
    if ($stmt->rowCount() > 0) {
        $userId = $db->lastInsertId();
        
        // Create default categories for new user
        $defaultCategories = [
            ['Stipendio', 'income'],
            ['Freelance', 'income'],
            ['Investimenti', 'income'],
            ['Alimentari', 'expense'],
            ['Trasporti', 'expense'],
            ['Affitto', 'expense'],
            ['Bollette', 'expense'],
            ['Intrattenimento', 'expense'],
            ['Salute', 'expense'],
            ['Ristoranti', 'expense'],
            ['Altro', 'expense']
        ];
        
        $stmt = $db->prepare("INSERT INTO categories (user_id, name, type) VALUES (?, ?, ?)");
        foreach ($defaultCategories as $cat) {
            $stmt->execute([$userId, $cat[0], $cat[1]]);
        }
        
        // Generate JWT token
        $payload = [
            'user_id' => $userId,
            'email' => $data['email'],
            'exp' => time() + (86400 * 30) // 30 days
        ];
        
        $token = JWT::encode($payload);
        
        // Send verification code email (non-blocking - don't fail registration if email fails)
        sendVerificationCodeEmail($data['email'], $data['name'], $verificationCode);
        
        sendJsonResponse([
            'message' => 'User registered successfully. Please verify your email with the code sent to your inbox.',
            'token' => $token,
            'user' => [
                'id' => $userId,
                'email' => $data['email'],
                'name' => $data['name'],
                'email_verified' => false
            ],
            'requires_verification' => true
        ], 201);
    } else {
        sendError('Failed to register user', 500);
    }
}

// POST /api/auth/login
elseif ($method === 'POST' && $path === '/login') {
    $data = getJsonInput();
    
    validateRequired($data, ['email', 'password']);
    
    // Get user by email
    $stmt = $db->prepare("SELECT id, email, password_hash, name, email_verified FROM users WHERE email = ?");
    $stmt->execute([$data['email']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        sendError('Invalid credentials', 401);
    }
    
    // Verify password
    if (!password_verify($data['password'], $user['password_hash'])) {
        sendError('Invalid credentials', 401);
    }
    
    // Check if email is verified
    if (!$user['email_verified']) {
        sendError('Email not verified. Please verify your email before logging in.', 403);
    }
    
    // Generate JWT token
    $payload = [
        'user_id' => $user['id'],
        'email' => $user['email'],
        'exp' => time() + (86400 * 30) // 30 days
    ];
    
    $token = JWT::encode($payload);
    
    sendJsonResponse([
        'message' => 'Login successful',
        'token' => $token,
        'user' => [
            'id' => $user['id'],
            'email' => $user['email'],
            'name' => $user['name']
        ]
    ]);
}

// GET /api/auth/me
elseif ($method === 'GET' && $path === '/me') {
    $userId = getUserIdFromToken();
    
    $stmt = $db->prepare("SELECT id, email, name, created_at FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        sendError('User not found', 404);
    }
    
    sendJsonResponse([
        'user' => $user
    ]);
}

else {
    sendError('Endpoint not found', 404);
}
