<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../middleware/auth.php';

setCorsHeaders();

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$userId = getUserIdFromToken();

// GET /api/transactions - Get all transactions with filters
if ($method === 'GET') {
    $type = $_GET['type'] ?? null;
    $categoryId = $_GET['category_id'] ?? null;
    $startDate = $_GET['start_date'] ?? null;
    $endDate = $_GET['end_date'] ?? null;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $offset = ($page - 1) * $limit;
    
    $query = "SELECT t.*, c.name as category_name FROM transactions t 
              LEFT JOIN categories c ON t.category_id = c.id 
              WHERE t.user_id = ?";
    $params = [$userId];
    
    if ($type && in_array($type, ['income', 'expense'])) {
        $query .= " AND t.type = ?";
        $params[] = $type;
    }
    
    if ($categoryId) {
        $query .= " AND t.category_id = ?";
        $params[] = $categoryId;
    }
    
    if ($startDate) {
        $query .= " AND t.date >= ?";
        $params[] = $startDate;
    }
    
    if ($endDate) {
        $query .= " AND t.date <= ?";
        $params[] = $endDate;
    }
    
    // Count total
    $countStmt = $db->prepare(str_replace("SELECT t.*, c.name as category_name", "SELECT COUNT(*)", $query));
    $countStmt->execute($params);
    $total = $countStmt->fetchColumn();
    
    $query .= " ORDER BY t.date DESC, t.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();
    
    sendJsonResponse([
        'transactions' => $transactions,
        'pagination' => [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

// POST /api/transactions - Create new transaction
elseif ($method === 'POST') {
    $data = getJsonInput();
    
    validateRequired($data, ['type', 'amount', 'date']);
    
    if (!in_array($data['type'], ['income', 'expense'])) {
        sendError('Type must be income or expense', 400);
    }
    
    $recurringRule = $data['recurring_rule'] ?? 'single';
    if (!in_array($recurringRule, ['single', 'daily', 'weekly', 'monthly', 'yearly'])) {
        sendError('Invalid recurring rule', 400);
    }
    
    $categoryId = $data['category_id'] ?? null;
    $currency = $data['currency'] ?? 'EUR';
    $description = $data['description'] ?? null;
    
    // Verify category belongs to user if provided
    if ($categoryId) {
        $stmt = $db->prepare("SELECT id FROM categories WHERE id = ? AND user_id = ?");
        $stmt->execute([$categoryId, $userId]);
        if (!$stmt->fetch()) {
            sendError('Invalid category', 400);
        }
    }
    
    $stmt = $db->prepare("INSERT INTO transactions (user_id, category_id, type, amount, currency, date, description, recurring_rule) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
    if ($stmt->execute([$userId, $categoryId, $data['type'], $data['amount'], $currency, $data['date'], $description, $recurringRule])) {
        $transactionId = $db->lastInsertId();
        
        // Generate recurring instances if not single
        if ($recurringRule !== 'single') {
            generateRecurringInstances($db, $transactionId, $data['date'], $recurringRule);
        }
        
        sendJsonResponse([
            'message' => 'Transaction created successfully',
            'transaction_id' => $transactionId
        ], 201);
    } else {
        sendError('Failed to create transaction', 500);
    }
}

// PUT /api/transactions/{id} - Update transaction
elseif ($method === 'PUT') {
    $pathParts = explode('/', trim($_SERVER['PATH_INFO'] ?? '', '/'));
    $transactionId = end($pathParts);
    
    if (!is_numeric($transactionId)) {
        sendError('Invalid transaction ID', 400);
    }
    
    $data = getJsonInput();
    
    // Check if transaction belongs to user
    $stmt = $db->prepare("SELECT id, recurring_rule FROM transactions WHERE id = ? AND user_id = ?");
    $stmt->execute([$transactionId, $userId]);
    $transaction = $stmt->fetch();
    
    if (!$transaction) {
        sendError('Transaction not found', 404);
    }
    
    $updates = [];
    $params = [];
    
    if (isset($data['category_id'])) {
        $updates[] = "category_id = ?";
        $params[] = $data['category_id'];
    }
    
    if (isset($data['type']) && in_array($data['type'], ['income', 'expense'])) {
        $updates[] = "type = ?";
        $params[] = $data['type'];
    }
    
    if (isset($data['amount'])) {
        $updates[] = "amount = ?";
        $params[] = $data['amount'];
    }
    
    if (isset($data['currency'])) {
        $updates[] = "currency = ?";
        $params[] = $data['currency'];
    }
    
    if (isset($data['date'])) {
        $updates[] = "date = ?";
        $params[] = $data['date'];
    }
    
    if (isset($data['description'])) {
        $updates[] = "description = ?";
        $params[] = $data['description'];
    }
    
    if (isset($data['recurring_rule']) && in_array($data['recurring_rule'], ['single', 'daily', 'weekly', 'monthly', 'yearly'])) {
        $updates[] = "recurring_rule = ?";
        $params[] = $data['recurring_rule'];
        
        // Regenerate recurring instances if rule changed
        if ($data['recurring_rule'] !== $transaction['recurring_rule']) {
            $db->prepare("DELETE FROM recurring_instances WHERE transaction_id = ?")->execute([$transactionId]);
            if ($data['recurring_rule'] !== 'single') {
                $date = $data['date'] ?? null;
                if (!$date) {
                    $stmt = $db->prepare("SELECT date FROM transactions WHERE id = ?");
                    $stmt->execute([$transactionId]);
                    $date = $stmt->fetchColumn();
                }
                generateRecurringInstances($db, $transactionId, $date, $data['recurring_rule']);
            }
        }
    }
    
    if (empty($updates)) {
        sendError('No valid fields to update', 400);
    }
    
    $params[] = $transactionId;
    $params[] = $userId;
    
    $query = "UPDATE transactions SET " . implode(', ', $updates) . " WHERE id = ? AND user_id = ?";
    $stmt = $db->prepare($query);
    
    if ($stmt->execute($params)) {
        sendJsonResponse([
            'message' => 'Transaction updated successfully'
        ]);
    } else {
        sendError('Failed to update transaction', 500);
    }
}

// DELETE /api/transactions/{id} - Delete transaction
elseif ($method === 'DELETE') {
    $pathParts = explode('/', trim($_SERVER['PATH_INFO'] ?? '', '/'));
    $transactionId = end($pathParts);
    
    if (!is_numeric($transactionId)) {
        sendError('Invalid transaction ID', 400);
    }
    
    // Check if transaction belongs to user
    $stmt = $db->prepare("SELECT id FROM transactions WHERE id = ? AND user_id = ?");
    $stmt->execute([$transactionId, $userId]);
    
    if (!$stmt->fetch()) {
        sendError('Transaction not found', 404);
    }
    
    $stmt = $db->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ?");
    
    if ($stmt->execute([$transactionId, $userId])) {
        sendJsonResponse([
            'message' => 'Transaction deleted successfully'
        ]);
    } else {
        sendError('Failed to delete transaction', 500);
    }
}

else {
    sendError('Method not allowed', 405);
}

// Helper function to generate recurring instances
function generateRecurringInstances($db, $transactionId, $startDate, $recurringRule) {
    $instances = [];
    $date = new DateTime($startDate);
    $occurrences = 12; // Generate 12 occurrences
    
    for ($i = 1; $i <= $occurrences; $i++) {
        switch ($recurringRule) {
            case 'daily':
                $date->modify('+1 day');
                break;
            case 'weekly':
                $date->modify('+1 week');
                break;
            case 'monthly':
                $date->modify('+1 month');
                break;
            case 'yearly':
                $date->modify('+1 year');
                break;
        }
        
        $instances[] = [$transactionId, $date->format('Y-m-d')];
    }
    
    $stmt = $db->prepare("INSERT IGNORE INTO recurring_instances (transaction_id, occurrence_date) VALUES (?, ?)");
    foreach ($instances as $instance) {
        $stmt->execute($instance);
    }
}
