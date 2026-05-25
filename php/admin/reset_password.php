<?php
session_start();
require __DIR__ . '/../connection/dbconn.php';
require_once __DIR__ . '/../partials/helpers.php';
require __DIR__ . '/../partials/admin_helpers.php';

jomu_ensure_admin_schema($conn);
$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$tokenHash = hash('sha256', $token);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    jomu_require_admin_csrf();
    jomu_require_rate_limit('admin_reset_password', 5, 15 * 60, 'Too many admin password reset attempts. Please wait and try again.', substr($tokenHash, 0, 24));
    $password = (string) ($_POST['password'] ?? '');
    if (strlen($password) < 10) {
        $error = 'Use at least 10 characters.';
    } else {
        $stmt = $conn->prepare("SELECT reset_id, admin_id FROM admin_password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1");
        $stmt->bind_param('s', $tokenHash);
        $stmt->execute();
        $reset = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($reset) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $adminId = (int) $reset['admin_id'];
            $resetId = (int) $reset['reset_id'];
            $update = $conn->prepare("UPDATE admin_users SET password = ?, updated_at = NOW() WHERE admin_id = ?");
            $update->bind_param('si', $hash, $adminId);
            $update->execute();
            $update->close();
            $used = $conn->prepare("UPDATE admin_password_resets SET used_at = NOW() WHERE reset_id = ?");
            $used->bind_param('i', $resetId);
            $used->execute();
            $used->close();
            $success = 'Password updated. You can now sign in.';
        } else {
            $error = 'Reset link is invalid or expired.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Admin Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="admin.css">
</head>
<body class="admin-auth-page">
    <main class="admin-auth-card">
        <h1>Reset Password</h1>
        <?php if ($error !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if ($success !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(jomu_admin_csrf_token()); ?>">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            <label>New password</label>
            <input class="form-control" type="password" name="password" required minlength="10">
            <button class="btn btn-dark w-100 mt-3" type="submit">Update password</button>
        </form>
        <a class="admin-small-link" href="login.php">Back to login</a>
    </main>
</body>
</html>
