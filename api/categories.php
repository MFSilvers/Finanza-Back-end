<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../middleware/auth.php';

setCorsHeaders();

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$userId = getUserIdFromToken();

// GET /api/categories - Get all categories for user
if ($method === 'GET') {
    $type = $_GET['type'] ?? null;
    
    $query = "SELECT * FROM categories WHERE user_id = ?";
    $params = [$userId];
    
    if ($type && in_array($type, ['income', 'expense'])) {
        $query .= " AND type = ?";
        $params[] = $type;
    }
    
    $query .= " ORDER BY name ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $categories = $stmt->fetchAll();
    
    sendJsonResponse([
        'categories' => $categories
    ]);
}

// POST /api/categories - Create new category
elseif ($method === 'POST') {
    $data = getJsonInput();
    
    validateRequired($data, ['name', 'type']);
    
    if (!in_array($data['type'], ['income', 'expense'])) {
        sendError('Type must be income or expense', 400);
    }
    
    $stmt = $db->prepare("INSERT INTO categories (user_id, name, type) VALUES (?, ?, ?)");
    
    if ($stmt->execute([$userId, $data['name'], $data['type']])) {
        $categoryId = $db->lastInsertId();
        
        sendJsonResponse([
            'message' => 'Category created successfully',
            'category' => [
                'id' => $categoryId,
                'user_id' => $userId,
                'name' => $data['name'],
                'type' => $data['type']
            ]
        ], 201);
    } else {
        sendError('Failed to create category', 500);
    }
}

// PUT /api/categories/{id} - Update category
elseif ($method === 'PUT') {
    $pathParts = explode('/', trim($_SERVER['PATH_INFO'] ?? '', '/'));
    $categoryId = end($pathParts);
    
    if (!is_numeric($categoryId)) {
        sendError('Invalid category ID', 400);
    }
    
    $data = getJsonInput();
    
    // Check if category belongs to user
    $stmt = $db->prepare("SELECT id FROM categories WHERE id = ? AND user_id = ?");
    $stmt->execute([$categoryId, $userId]);
    
    if (!$stmt->fetch()) {
        sendError('Category not found', 404);
    }
    
    $updates = [];
    $params = [];
    
    if (isset($data['name'])) {
        $updates[] = "name = ?";
        $params[] = $data['name'];
    }
    
    if (isset($data['type']) && in_array($data['type'], ['income', 'expense'])) {
        $updates[] = "type = ?";
        $params[] = $data['type'];
    }
    
    if (empty($updates)) {
        sendError('No valid fields to update', 400);
    }
    
    $params[] = $categoryId;
    $params[] = $userId;
    
    $query = "UPDATE categories SET " . implode(', ', $updates) . " WHERE id = ? AND user_id = ?";
    $stmt = $db->prepare($query);
    
    if ($stmt->execute($params)) {
        sendJsonResponse([
            'message' => 'Category updated successfully'
        ]);
    } else {
        sendError('Failed to update category', 500);
    }
}

// DELETE /api/categories/{id} - Delete category
elseif ($method === 'DELETE') {
    $pathParts = explode('/', trim($_SERVER['PATH_INFO'] ?? '', '/'));
    $categoryId = end($pathParts);
    
    if (!is_numeric($categoryId)) {
        sendError('Invalid category ID', 400);
    }
    
    // Check if category belongs to user
    $stmt = $db->prepare("SELECT id FROM categories WHERE id = ? AND user_id = ?");
    $stmt->execute([$categoryId, $userId]);
    
    if (!$stmt->fetch()) {
        sendError('Category not found', 404);
    }
    
    $stmt = $db->prepare("DELETE FROM categories WHERE id = ? AND user_id = ?");
    
    if ($stmt->execute([$categoryId, $userId])) {
        sendJsonResponse([
            'message' => 'Category deleted successfully'
        ]);
    } else {
        sendError('Failed to delete category', 500);
    }
}

else {
    sendError('Method not allowed', 405);
}
