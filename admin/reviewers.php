<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';
require_admin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $name = trim($_POST['full_name'] ?? '');
        $email = trim(strtolower($_POST['email'] ?? ''));
        $mobile = trim($_POST['mobile'] ?? '');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_flash('danger', 'Valid name and email are required.');
            redirect('admin/reviewers.php');
        }
        $temp = generate_temp_password();
        $hash = password_hash($temp, PASSWORD_DEFAULT);
        try {
            $stmt = db()->prepare('INSERT INTO reviewers(full_name,email,mobile,password,must_change_password,is_active,created_at) VALUES(?,?,?,?,1,1,NOW())');
            $stmt->bind_param('ssss', $name, $email, $mobile, $hash);
            $stmt->execute();
            $id = db()->insert_id;
            log_activity(null, null, 'admin', (int)$_SESSION['admin_id'], 'reviewer_created', null, (string)$id, 'Reviewer: ' . $name);
            $sent = email_reviewer_created($email, $name, $temp);
            set_flash($sent ? 'success' : 'warning', $sent ? 'Reviewer created and credentials emailed.' : 'Reviewer created, but email could not be sent. Temporary password: ' . $temp);
        } catch (Throwable $e) {
            set_flash('danger', 'Could not create reviewer. Email may already exist.');
        }
        redirect('admin/reviewers.php');
    }
    if ($action === 'toggle') {
        $id = (int)($_POST['reviewer_id'] ?? 0);
        $new = (int)($_POST['new_status'] ?? 0);
        $stmt = db()->prepare('UPDATE reviewers SET is_active=?,updated_at=NOW() WHERE id=?');
        $stmt->bind_param('ii', $new, $id);
        $stmt->execute();
        log_activity(null, null, 'admin', (int)$_SESSION['admin_id'], 'reviewer_status_changed', null, (string)$new, 'Reviewer ID ' . $id);
        set_flash('success', 'Reviewer status updated.');
        redirect('admin/reviewers.php');
    }
}
$reviewers = db()->query("SELECT r.*, COUNT(ra.id) assigned_count, SUM(CASE WHEN ra.is_active=1 AND ra.status IN ('pending','under_review','resubmit') THEN 1 ELSE 0 END) open_count FROM reviewers r LEFT JOIN review_assignments ra ON ra.reviewer_id=r.id GROUP BY r.id ORDER BY r.full_name")->fetch_all(MYSQLI_ASSOC);
$pageTitle = 'Reviewers';
require __DIR__ . '/../includes/header.php';
?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <h5 class="section-title">Create Reviewer</h5>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="create">
                    <div class="mb-3"><label class="form-label required">Full Name</label><input class="form-control" name="full_name" required></div>
                    <div class="mb-3"><label class="form-label required">Email</label><input class="form-control" name="email" type="email" required></div>
                    <div class="mb-3"><label class="form-label">Mobile</label><input class="form-control" name="mobile"></div><button class="btn btn-brand w-100">Create & Email Credentials</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <h5 class="section-title">Reviewer List</h5>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Mobile</th>
                                <th>Assigned</th>
                                <th>Open</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody><?php foreach ($reviewers as $r): ?><tr>
                                    <td><?= e($r['full_name']) ?></td>
                                    <td><?= e($r['email']) ?></td>
                                    <td><?= e($r['mobile']) ?></td>
                                    <td><?= (int)$r['assigned_count'] ?></td>
                                    <td><?= (int)$r['open_count'] ?></td>
                                    <td><?= $r['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                                    <td>
                                        <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="reviewer_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="new_status" value="<?= $r['is_active'] ? 0 : 1 ?>"><button class="btn btn-sm btn-outline-secondary"><?= $r['is_active'] ? 'Deactivate' : 'Activate' ?></button></form>
                                    </td>
                                </tr><?php endforeach; ?></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>