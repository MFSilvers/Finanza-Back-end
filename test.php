<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
echo json_encode(['status' => 'ok', 'message' => 'Test endpoint works', 'time' => date('Y-m-d H:i:s')]);

