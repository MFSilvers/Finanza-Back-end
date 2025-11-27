<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../middleware/auth.php';

setCorsHeaders();

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$userId = getUserIdFromToken();

if ($method !== 'GET') {
    sendError('Method not allowed', 405);
}

$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;

$dateFilter = "";
$params = [$userId];

if ($startDate && $endDate) {
    $dateFilter = " AND date BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
}

// Total balance
$stmt = $db->prepare("SELECT 
    COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as total_income,
    COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as total_expense
    FROM transactions 
    WHERE user_id = ?" . $dateFilter);
$stmt->execute($params);
$totals = $stmt->fetch();

$balance = $totals['total_income'] - $totals['total_expense'];

// Expenses by category
$stmt = $db->prepare("SELECT c.name, c.id, SUM(t.amount) as total
    FROM transactions t
    LEFT JOIN categories c ON t.category_id = c.id
    WHERE t.user_id = ? AND t.type = 'expense'" . $dateFilter . "
    GROUP BY c.id, c.name
    ORDER BY total DESC
    LIMIT 10");
$stmt->execute($params);
$expensesByCategory = $stmt->fetchAll();

// Income by category
$stmt = $db->prepare("SELECT c.name, c.id, SUM(t.amount) as total
    FROM transactions t
    LEFT JOIN categories c ON t.category_id = c.id
    WHERE t.user_id = ? AND t.type = 'income'" . $dateFilter . "
    GROUP BY c.id, c.name
    ORDER BY total DESC
    LIMIT 10");
$stmt->execute($params);
$incomeByCategory = $stmt->fetchAll();

// Monthly trends (last 12 months or filtered period)
// Detect database type for SQL compatibility
$dbType = getenv('DB_TYPE') ?: 'mysql';
if ($dbType === 'pgsql' || $dbType === 'postgres' || $dbType === 'postgresql') {
    // PostgreSQL syntax
    if (!$startDate || !$endDate) {
        $monthsQuery = "SELECT 
            TO_CHAR(date, 'YYYY-MM') as month,
            SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income,
            SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense
            FROM transactions
            WHERE user_id = ? AND date >= (CURRENT_DATE - INTERVAL '12 months')
            GROUP BY TO_CHAR(date, 'YYYY-MM')
            ORDER BY month ASC";
        $stmt = $db->prepare($monthsQuery);
        $stmt->execute([$userId]);
    } else {
        $monthsQuery = "SELECT 
            TO_CHAR(date, 'YYYY-MM') as month,
            SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income,
            SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense
            FROM transactions
            WHERE user_id = ? AND date BETWEEN ? AND ?
            GROUP BY TO_CHAR(date, 'YYYY-MM')
            ORDER BY month ASC";
        $stmt = $db->prepare($monthsQuery);
        $stmt->execute([$userId, $startDate, $endDate]);
    }
} else {
    // MySQL syntax
    if (!$startDate || !$endDate) {
        $monthsQuery = "SELECT 
            DATE_FORMAT(date, '%Y-%m') as month,
            SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income,
            SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense
            FROM transactions
            WHERE user_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            GROUP BY month
            ORDER BY month ASC";
        $stmt = $db->prepare($monthsQuery);
        $stmt->execute([$userId]);
    } else {
        $monthsQuery = "SELECT 
            DATE_FORMAT(date, '%Y-%m') as month,
            SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income,
            SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense
            FROM transactions
            WHERE user_id = ? AND date BETWEEN ? AND ?
            GROUP BY month
            ORDER BY month ASC";
        $stmt = $db->prepare($monthsQuery);
        $stmt->execute([$userId, $startDate, $endDate]);
    }
}
$monthlyTrends = $stmt->fetchAll();

// Average monthly income and expense
$avgIncome = 0;
$avgExpense = 0;
if (count($monthlyTrends) > 0) {
    $totalIncome = array_sum(array_column($monthlyTrends, 'income'));
    $totalExpense = array_sum(array_column($monthlyTrends, 'expense'));
    $avgIncome = $totalIncome / count($monthlyTrends);
    $avgExpense = $totalExpense / count($monthlyTrends);
}

// Recent transactions
$stmt = $db->prepare("SELECT t.*, c.name as category_name 
    FROM transactions t
    LEFT JOIN categories c ON t.category_id = c.id
    WHERE t.user_id = ?" . $dateFilter . "
    ORDER BY t.date DESC, t.created_at DESC
    LIMIT 10");
$stmt->execute($params);
$recentTransactions = $stmt->fetchAll();

sendJsonResponse([
    'balance' => $balance,
    'total_income' => $totals['total_income'],
    'total_expense' => $totals['total_expense'],
    'expenses_by_category' => $expensesByCategory,
    'income_by_category' => $incomeByCategory,
    'monthly_trends' => $monthlyTrends,
    'average_monthly_income' => round($avgIncome, 2),
    'average_monthly_expense' => round($avgExpense, 2),
    'recent_transactions' => $recentTransactions
]);
