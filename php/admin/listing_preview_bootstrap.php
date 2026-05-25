<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

require __DIR__ . '/../connection/dbconn.php';
require __DIR__ . '/../partials/admin_helpers.php';

jomu_ensure_admin_schema($conn);

$admin = jomu_current_admin($conn);
if (!$admin) {
    echo json_encode(['ok' => true, 'is_admin' => false, 'csrf_token' => '']);
    exit;
}

echo json_encode([
    'ok' => true,
    'is_admin' => true,
    'csrf_token' => jomu_admin_csrf_token(),
]);
