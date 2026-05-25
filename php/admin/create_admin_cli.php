<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require __DIR__ . '/../connection/dbconn.php';
require __DIR__ . '/../partials/admin_helpers.php';

jomu_ensure_admin_schema($conn);

$email = strtolower(trim((string) ($argv[1] ?? '')));
$password = (string) ($argv[2] ?? '');
$name = trim((string) ($argv[3] ?? 'JoMu Admin'));

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10) {
    fwrite(STDERR, "Usage: php php/admin/create_admin_cli.php admin@example.com strong-password \"JoMu Admin\"\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conn->prepare(
    "INSERT INTO admin_users (email, password, name, created_at)
     VALUES (?, ?, ?, NOW())
     ON DUPLICATE KEY UPDATE password = VALUES(password), name = VALUES(name), updated_at = NOW()"
);
$stmt->bind_param('sss', $email, $hash, $name);
$stmt->execute();
$stmt->close();

echo "Admin account saved for {$email}\n";
