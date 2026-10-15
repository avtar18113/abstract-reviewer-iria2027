<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';
require_admin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (($_POST['action'] ?? '') === 'bulk_assign') {
        $ids = array_values(array_filter(array_map('intval', $_POST['abstract_ids'] ?? [])));
        $reviewerId = (int)($_POST['reviewer_id'] ?? 0);
        $deadlineInput = trim($_POST['review_deadline'] ?? '');
        $deadline = $deadlineInput !== '' ? date('Y-m-d H:i:s', strtotime($deadlineInput)) : '';
        $rv = db()->prepare('SELECT id,full_name,email FROM reviewers WHERE id=? AND is_active=1');
        $rv->bind_param('i', $reviewerId);
        $rv->execute();
        $reviewer = $rv->get_result()->fetch_assoc();
        if (!$ids || !$reviewer || $deadline === '') {
            set_flash('danger', 'Select abstracts, reviewer and deadline.');
            redirect('admin/abstracts.php');
        }
        $assigned = 0;
        $reassigned = 0;
        foreach ($ids as $abstractId) {
            $stmt = db()->prepare('SELECT id,reviewer_id FROM review_assignments WHERE abstract_id=? AND is_active=1 LIMIT 1');
            $stmt->bind_param('i', $abstractId);
            $stmt->execute();
            $old = $stmt->get_result()->fetch_assoc();
            if ($old) {
                $oldId = (int)$old['id'];
                $up = db()->prepare('UPDATE review_assignments SET is_active=0,updated_at=NOW() WHERE id=?');
                $up->bind_param('i', $oldId);
                $up->execute();
                log_activity($abstractId, $oldId, 'admin', (int)$_SESSION['admin_id'], 'assignment_deactivated', (string)$old['reviewer_id'], (string)$reviewerId, 'Bulk reassignment');
                $reassigned++;
            }
            $ins = db()->prepare("INSERT INTO review_assignments(abstract_id,reviewer_id,assigned_by,assigned_at,review_deadline,status,is_active,created_at) VALUES(?,?,?,NOW(),?,'pending',1,NOW())");
            $adminId = (int)$_SESSION['admin_id'];
            $ins->bind_param('iiis', $abstractId, $reviewerId, $adminId, $deadline);
            $ins->execute();
            $aid = db()->insert_id;
            $rev = db()->prepare("INSERT INTO abstract_reviews(assignment_id,abstract_id,reviewer_id,decision,is_final,is_locked,created_at) VALUES(?,?,?,'pending',0,0,NOW())");
            $rev->bind_param('iii', $aid, $abstractId, $reviewerId);
            $rev->execute();
            log_activity($abstractId, $aid, 'admin', $adminId, 'assigned', null, (string)$reviewerId, 'Deadline: ' . $deadline);
            $assigned++;
        }
        email_assignment($reviewer['email'], $reviewer['full_name'], $assigned, date('d M Y, h:i A', strtotime($deadline)), $reassigned > 0);
        set_flash('success', $assigned . ' abstract(s) assigned successfully.');
        redirect('admin/abstracts.php');
    }
}

$where = ['a.del=0'];
$types = '';
$params = [];
$filterMap = ['presentation' => 'a.presentation', 'mode1' => 'a.mode_presentation1', 'mode2' => 'a.mode_presentation2', 'eposter' => 'a.type_of_eposter'];
foreach ($filterMap as $key => $col) {
    if (isset($_GET[$key]) && $_GET[$key] !== '') {
        $where[] = "$col = ?";
        $types .= 's';
        $params[] = $_GET[$key];
    }
}
if (!empty($_GET['q'])) {
    $where[] = '(a.abs_id LIKE ? OR a.topic LIKE ?)';
    $types .= 'ss';
    $q = '%' . $_GET['q'] . '%';
    $params[] = $q;
    $params[] = $q;
}
$status = $_GET['status'] ?? '';
if ($status === 'assigned') $where[] = 'ra.id IS NOT NULL';
elseif ($status === 'unassigned') $where[] = 'ra.id IS NULL';
elseif ($status === 'reviewed') {
    $where[] = "ra.status IN ('under_review','accepted','rejected','resubmit','cannot_review')";
} elseif ($status === 'not_reviewed') {
    $where[] = "ra.status='pending'";
} elseif (in_array($status, ['pending', 'under_review', 'accepted', 'rejected', 'resubmit', 'cannot_review'], true)) {
    $where[] = 'ra.status = ?';
    $types .= 's';
    $params[] = $status;
}
$reviewerFilter = (int)($_GET['reviewer_id'] ?? 0);
if ($reviewerFilter) {
    $where[] = 'ra.reviewer_id=?';
    $types .= 'i';
    $params[] = $reviewerFilter;
}
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 50;
$offset = ($page - 1) * $per;
$sql = 'SELECT a.id,a.abs_id,a.topic AS abstract_title,a.presentation,a.mode_presentation1,a.mode_presentation2,a.type_of_eposter,a.dateCreated, ra.id assignment_id,ra.status review_status,ra.review_deadline,r.full_name reviewer_name FROM abstract a LEFT JOIN review_assignments ra ON ra.abstract_id=a.id AND ra.is_active=1 LEFT JOIN reviewers r ON r.id=ra.reviewer_id WHERE ' . implode(' AND ', $where) . ' ORDER BY a.id DESC LIMIT ? OFFSET ?';
$stmt = db()->prepare($sql);
$bindTypes = $types . 'ii';
$bindParams = array_merge($params, [$per, $offset]);
$stmt->bind_param($bindTypes, ...$bindParams);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$countSql = 'SELECT COUNT(*) c FROM abstract a LEFT JOIN review_assignments ra ON ra.abstract_id=a.id AND ra.is_active=1 LEFT JOIN reviewers r ON r.id=ra.reviewer_id WHERE ' . implode(' AND ', $where);
$cs = db()->prepare($countSql);
if ($types !== '') $cs->bind_param($types, ...$params);
$cs->execute();
$total = (int)$cs->get_result()->fetch_assoc()['c'];
$pages = max(1, (int)ceil($total / $per));
function distinct_opts($col)
{
    $allowed = ['presentation', 'mode_presentation1', 'mode_presentation2', 'type_of_eposter'];
    if (!in_array($col, $allowed, true)) return [];
    $res = db()->query("SELECT DISTINCT $col v FROM abstract WHERE del=0 AND $col IS NOT NULL AND TRIM($col)<>'' ORDER BY $col");
    return array_column($res->fetch_all(MYSQLI_ASSOC), 'v');
}
$reviewers = db()->query('SELECT id,full_name FROM reviewers WHERE is_active=1 ORDER BY full_name')->fetch_all(MYSQLI_ASSOC);
$pageTitle = 'Abstracts';
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2 class="mb-1">All Abstracts</h2>
        <div class="text-muted"><?= $total ?> record(s)</div>
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2" method="get">
            <div class="col-md-3"><input class="form-control" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Abstract ID or title"></div>
            <div class="col-md-2"><select name="presentation" class="form-select">
                    <option value="">Presentation</option><?php foreach (distinct_opts('presentation') as $v): ?><option <?= ($_GET['presentation'] ?? '') === $v ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
                </select></div>
            <div class="col-md-2"><select name="mode1" class="form-select">
                    <option value="">Mode 1</option><?php foreach (distinct_opts('mode_presentation1') as $v): ?><option value="<?= e($v) ?>" <?= ($_GET['mode1'] ?? '') === $v ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
                </select></div>
            <div class="col-md-2"><select name="mode2" class="form-select">
                    <option value="">Mode 2</option><?php foreach (distinct_opts('mode_presentation2') as $v): ?><option value="<?= e($v) ?>" <?= ($_GET['mode2'] ?? '') === $v ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
                </select></div>
            <div class="col-md-3"><select name="eposter" class="form-select">
                    <option value="">E-Poster Type</option><?php foreach (distinct_opts('type_of_eposter') as $v): ?><option value="<?= e($v) ?>" <?= ($_GET['eposter'] ?? '') === $v ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
                </select></div>
            <div class="col-md-3"><select name="reviewer_id" class="form-select">
                    <option value="">All Reviewers</option><?php foreach ($reviewers as $r): ?><option value="<?= (int)$r['id'] ?>" <?= (int)($_GET['reviewer_id'] ?? 0) === (int)$r['id'] ? 'selected' : '' ?>><?= e($r['full_name']) ?></option><?php endforeach; ?>
                </select></div>
            <div class="col-md-3"><select name="status" class="form-select">
                    <option value="">All Status</option><?php foreach (['assigned' => 'Assigned', 'unassigned' => 'Unassigned', 'reviewed' => 'Reviewed', 'not_reviewed' => 'Not Reviewed', 'pending' => 'Pending', 'under_review' => 'Under Review', 'accepted' => 'Accepted', 'rejected' => 'Rejected', 'resubmit' => 'Re-submit', 'cannot_review' => 'Cannot Review'] as $k => $v): ?><option value="<?= $k ?>" <?= ($_GET['status'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
                </select></div>
            <div class="col-md-2"><button class="btn btn-brand w-100">Apply Filters</button></div>
            <div class="col-md-1"><a href="abstracts.php" class="btn btn-outline-secondary w-100">Reset</a></div>
        </form>
    </div>
</div>
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="bulk_assign">
    <div class="card">
        <div class="card-body">
            <div class="row g-2 align-items-end mb-3">
                <div class="col-md-4"><label class="form-label">Assign selected to reviewer</label><select name="reviewer_id" class="form-select" required>
                        <option value="">Select reviewer</option><?php foreach ($reviewers as $r): ?><option value="<?= (int)$r['id'] ?>"><?= e($r['full_name']) ?></option><?php endforeach; ?>
                    </select></div>
                <div class="col-md-3"><label class="form-label">Review deadline</label><input type="datetime-local" name="review_deadline" class="form-control" required></div>
                <div class="col-md-2"><button class="btn btn-brand w-100">Assign Selected</button></div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="checkAll"></th>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Presentation</th>
                            <th>Mode 1</th>
                            <th>Mode 2</th>
                            <th>Type</th>
                            <th>Reviewer</th>
                            <th>Deadline</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody><?php foreach ($rows as $row): ?><tr>
                                <td><input class="form-check-input rowcheck" type="checkbox" name="abstract_ids[]" value="<?= (int)$row['id'] ?>"></td>
                                <td><?= e($row['abs_id']) ?></td>
                                <td style="min-width:280px"><?= e($row['abstract_title']) ?></td>
                                <td><?= e($row['presentation']) ?></td>
                                <td><?= e($row['mode_presentation1']) ?></td>
                                <td><?= e($row['mode_presentation2']) ?></td>
                                <td><?= e($row['type_of_eposter']) ?></td>
                                <td><?= e($row['reviewer_name'] ?? 'Unassigned') ?></td>
                                <td><?= $row['review_deadline'] ? e(date('d M Y H:i', strtotime($row['review_deadline']))) : '—' ?></td>
                                <td><?= $row['assignment_id'] ? status_badge($row['review_status']) : '<span class="badge bg-dark">Unassigned</span>' ?></td>
                                <td><a class="btn btn-sm btn-outline-secondary" href="abstract-view.php?id=<?= (int)$row['id'] ?>">View</a></td>
                            </tr><?php endforeach; ?></tbody>
                </table>
            </div>
        </div>
    </div>
</form>
<nav class="mt-3">
    <ul class="pagination"><?php for ($p = max(1, $page - 2); $p <= min($pages, $page + 2); $p++): ?><li class="page-item <?= $p === $page ? 'active' : '' ?>"><a class="page-link" href="<?= e(pagination_url(['page' => $p])) ?>"><?= $p ?></a></li><?php endfor; ?></ul>
</nav>
<script>
    document.getElementById('checkAll').addEventListener('change', e => document.querySelectorAll('.rowcheck').forEach(c => c.checked = e.target.checked));
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>