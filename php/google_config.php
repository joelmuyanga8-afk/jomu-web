<?php
header('Content-Type: application/json');

require_once __DIR__ . '/connection/env.php';
load_env_file(__DIR__ . '/../.env');

$clientId = env_value('GOOGLE_CLIENT_ID');
if (!$clientId) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Google client ID is not configured.']);
    exit();
}

echo json_encode([
    'success' => true,
    'google_client_id' => $clientId
]);
