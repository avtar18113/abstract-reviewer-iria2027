<?php
require_once __DIR__ . '/../includes/auth.php'; require_admin();
$counts = [];
$counts['total'] = (int)db()->query("SELECT COUNT(*) c FROM abstract WHERE del=0")->fetch_assoc()['c'];
$counts['assigned'] = (int)db()->query("SELECT COUNT(DISTINCT abstract_id) c FROM review_assignments WHERE is_active=1")->fetch_assoc()['c'];
$counts['unassigned'] = $counts['total'] - $counts['assigned'];
foreach (['pending','under_review','accepted','rejected','resubmit','cannot_review'] as $s) {
    $stmt=db()->prepare("SELECT COUNT(*) c FROM review_assignments WHERE is_active=1 AND status=?"); $stmt->bind_param('s',$s); $stmt->execute(); $counts[$s]=(int)$stmt->get_result()->fetch_assoc()['c'];
}
$counts['overdue']=(int)db()->query("SELECT COUNT(*) c FROM review_assignments WHERE is_active=1 AND review_deadline < NOW() AND status NOT IN ('accepted','rejected')")->fetch_assoc()['c'];
$pageTitle='Admin Dashboard'; require __DIR__ . '/../includes/header.php';
$cards=['total'=>'Total Abstracts','assigned'=>'Assigned','unassigned'=>'Unassigned','pending'=>'Pending','under_review'=>'Under Review','accepted'=>'Accepted','rejected'=>'Rejected','resubmit'=>'Re-submit','cannot_review'=>'Cannot Review','overdue'=>'Overdue'];
?>
<div class="d-flex justify-content-between align-items-center mb-4"><div><h2 class="mb-1">Admin Dashboard</h2><div class="text-muted">Review workflow overview</div></div><a href="abstracts.php" class="btn btn-brand">Manage Abstracts</a></div>
<div class="row g-3"><?php foreach($cards as $k=>$label): ?><div class="col-6 col-md-4 col-xl-2"><div class="card stat-card"><div class="card-body"><div class="small-muted"><?= e($label) ?></div><div class="stat-number"><?= (int)$counts[$k] ?></div></div></div></div><?php endforeach; ?></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
