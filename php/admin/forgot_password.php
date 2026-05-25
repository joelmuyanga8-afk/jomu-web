<?php
session_start();
require __DIR__ . '/../connection/dbconn.php';
require_once __DIR__ . '/../partials/helpers.php';
require __DIR__ . '/../partials/admin_helpers.php';
require __DIR__ . '/../connection/env.php';

load_env_file(__DIR__ . '/../../.env');
jomu_ensure_admin_schema($conn);

$notice = '';
$resetLink = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    jomu_require_admin_csrf();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    jomu_require_rate_limit('admin_forgot_password', 3, 15 * 60, 'Too many admin password reset attempts. Please wait and try again.', $email);
    $stmt = $conn->prepare("SELECT admin_id FROM admin_users WHERE email = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($admin) {
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $adminId = (int) $admin['admin_id'];
            $insert = $conn->prepare("INSERT INTO admin_password_resets (admin_id, token_hash, expires_at, created_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE), NOW())");
            $insert->bind_param('is', $adminId, $tokenHash);
            $insert->execute();
            $insert->close();
            $resetLink = 'reset_password.php?token=' . urlencode($token);
        }
    }
    $notice = 'If that admin email exists, a password reset link has been prepared.';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recover Admin Password</title>
    <link rel="stylesheet" href="/assets/bootstrap.min.css">
    <link rel="stylesheet" href="admin.css">
</head>
<body class="admin-auth-page">
    <main class="admin-auth-card">
        <h1>Password Recovery</h1>
        <?php if ($notice !== ''): ?><div class="alert alert-info"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
        <?php if ($resetLink !== ''): ?><p class="admin-reset-link">Local reset link: <a href="<?php echo htmlspecialchars($resetLink); ?>"><?php echo htmlspecialchars($resetLink); ?></a></p><?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(jomu_admin_csrf_token()); ?>">
            <label>Admin email</label>
            <input class="form-control" type="email" name="email" required>
            <button class="btn btn-dark w-100 mt-3" type="submit">Prepare reset link</button>
        </form>
        <a class="admin-small-link" href="login.php">Back to login</a>
    </main>
</body>
</html>
