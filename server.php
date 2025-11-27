<?php

$request_uri = $_SERVER['REQUEST_URI'];
$request_path = parse_url($request_uri, PHP_URL_PATH);

if (preg_match('/\.php$/', $request_path)) {
    $file_path = __DIR__ . $request_path;
    if (file_exists($file_path)) {
        require $file_path;
        return;
    }
}

if ($request_path === '/' || $request_path === '') {
    require __DIR__ . '/index.php';
} elseif (file_exists(__DIR__ . $request_path)) {
    return false;
} else {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not found']);
}

