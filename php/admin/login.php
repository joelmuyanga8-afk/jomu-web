<?php
session_start();
require __DIR__ . '/../connection/dbconn.php';
require_once __DIR__ . '/../partials/helpers.php';
require __DIR__ . '/../partials/admin_helpers.php';

jomu_ensure_admin_schema($conn);

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    jomu_require_admin_csrf();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    jomu_require_rate_limit('admin_login', 5, 15 * 60, 'Too many admin login attempts. Please wait and try again.', $email);

    $stmt = $conn->prepare("SELECT admin_id, password FROM admin_users WHERE email = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($admin && password_verify($password, (string) ($admin['password'] ?? ''))) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int) $admin['admin_id'];
            jomu_admin_log($conn, (int) $admin['admin_id'], 'login', 'admin', (int) $admin['admin_id']);
            header('Location: dashboard.php');
            exit;
        }
    }

    $error = 'Invalid admin email or password.';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>JoMu Admin Login</title>
    <!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"> -->
    <link rel="stylesheet"  href="/assets/bootstrap.min.css">
    <link rel="stylesheet" href="admin.css">
        <link rel="icon" type="image/png" sizes="16x16" href="/./assets/images/jomu_favicon_orange-16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/./assets/images/jomu_favicon_orange-32.png">
    <link rel="icon" type="image/png" sizes="48x48" href="/./assets/images/jomu_favicon_orange-48.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/./assets/images/jomu_favicon_orange-192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="/./assets/images/jomu_favicon_orange-512.png">
</head>
<body class="admin-auth-page">
    <main class="admin-auth-card">
        <img src="/assets/images/JoMu logo redesigned.png" alt="JoMu" class="admin-logo">
        <h1>Admin Login</h1>
        <?php if ($error !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <form method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(jomu_admin_csrf_token()); ?>">
            <label>Email</label>
            <input class="form-control" type="email" name="email" required>
            <label>Password</label>
            <input class="form-control" type="password" name="password" required>
            <button class="btn btn-dark w-100 mt-3" type="submit">Sign in</button>
        </form>
        <a class="admin-small-link" href="forgot_password.php">Forgot password?</a>
    </main>
</body>
</html>
