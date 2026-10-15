<?php
require_once __DIR__ . '/../includes/auth.php';
if (($_SESSION['role'] ?? '') === 'admin') redirect('admin/dashboard.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim(strtolower($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $stmt = db()->prepare('SELECT * FROM review_admin WHERE email = ? AND is_active = 1 LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    if ($admin && password_verify($password, $admin['password'])) {
        login_admin($admin);
        $ip = client_ip();
        $id = (int)$admin['id'];
        $u = db()->prepare('UPDATE review_admin SET last_login=NOW(), last_login_ip=? WHERE id=?');
        $u->bind_param('si', $ip, $id);
        $u->execute();
        log_activity(null, null, 'admin', $id, 'login', null, null, 'Admin logged in');
        redirect('admin/dashboard.php');
    }
    $error = 'Invalid email or password.';
}
$pageTitle = 'Admin Login';
require __DIR__ . '/../includes/header.php';
?>
<div class="card login-card">
    <div class="card-body p-4 p-lg-5">
        <h3 class="mb-1">Admin Login</h3>
        <p class="text-muted mb-4">IRIA 2027 Abstract Review Portal</p>
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        <form method="post"><?= csrf_field() ?>
            <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
            <div class="mb-4"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
            <button class="btn btn-brand w-100">Login</button>
        </form>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>