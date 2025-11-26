<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../middleware/auth.php';

setCorsHeaders();

$database = new Database();
$db = $database->getConnection();

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
    
    // Insert user
    $stmt = $db->prepare("INSERT INTO users (email, password_hash, name) VALUES (?, ?, ?)");
    
    if ($stmt->execute([$data['email'], $passwordHash, $data['name']])) {
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
        
        sendJsonResponse([
            'message' => 'User registered successfully',
            'token' => $token,
            'user' => [
                'id' => $userId,
                'email' => $data['email'],
                'name' => $data['name']
            ]
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
    $stmt = $db->prepare("SELECT id, email, password_hash, name FROM users WHERE email = ?");
    $stmt->execute([$data['email']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        sendError('Invalid credentials', 401);
    }
    
    // Verify password
    if (!password_verify($data['password'], $user['password_hash'])) {
        sendError('Invalid credentials', 401);
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
