<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';
require_admin();
$assignmentId = (int)($_GET['id'] ?? $_POST['assignment_id'] ?? 0);
$q = db()->prepare('SELECT ra.*,r.full_name,r.email,a.abs_id,ar.id review_id,ar.decision,ar.reviewer_comment,ar.is_final,ar.is_locked FROM review_assignments ra JOIN reviewers r ON r.id=ra.reviewer_id JOIN abstract a ON a.id=ra.abstract_id LEFT JOIN abstract_reviews ar ON ar.assignment_id=ra.id WHERE ra.id=?');
$q->bind_param('i', $assignmentId);
$q->execute();
$row = $q->get_result()->fetch_assoc();
if (!$row) {
    http_response_code(404);
    exit('Assignment not found');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $adminId = (int)$_SESSION['admin_id'];
    if ($action === 'deadline') {
        $d = $_POST['review_deadline'] ?? '';
        $s = db()->prepare('UPDATE review_assignments SET review_deadline=?,updated_at=NOW() WHERE id=?');
        $s->bind_param('si', $d, $assignmentId);
        $s->execute();
        log_activity((int)$row['abstract_id'], $assignmentId, 'admin', $adminId, 'deadline_changed', $row['review_deadline'], $d);
        set_flash('success', 'Deadline updated.');
    } elseif ($action === 'reopen') {
        db()->query('UPDATE abstract_reviews SET is_final=0,is_locked=0,updated_at=NOW() WHERE assignment_id=' . (int)$assignmentId);
        log_activity((int)$row['abstract_id'], $assignmentId, 'admin', $adminId, 'review_reopened', $row['decision'], $row['decision']);
        set_flash('success', 'Review reopened.');
    } elseif ($action === 'override') {
        $decision = normalize_decision($_POST['decision'] ?? 'pending');
        $comment = trim($_POST['comment'] ?? '');
        $locked = in_array($decision, ['accepted', 'rejected'], true) ? 1 : 0;
        $final = (int)($_POST['is_final'] ?? 0);
        $s = db()->prepare('UPDATE abstract_reviews SET decision=?,reviewer_comment=?,is_final=?,is_locked=?,submitted_at=IF(?=1,NOW(),submitted_at),updated_at=NOW() WHERE assignment_id=?');
        $s->bind_param('ssiiii', $decision, $comment, $final, $locked, $final, $assignmentId);
        $s->execute();
        $u = db()->prepare('UPDATE review_assignments SET status=?,completed_at=IF(?=1,NOW(),completed_at),updated_at=NOW() WHERE id=?');
        $u->bind_param('sii', $decision, $final, $assignmentId);
        $u->execute();
        log_activity((int)$row['abstract_id'], $assignmentId, 'admin', $adminId, 'decision_overridden', $row['decision'], $decision, $comment);
        set_flash('success', 'Decision updated.');
    } elseif ($action === 'unassign') {
        $s = db()->prepare('UPDATE review_assignments SET is_active=0,updated_at=NOW() WHERE id=?');
        $s->bind_param('i', $assignmentId);
        $s->execute();
        log_activity((int)$row['abstract_id'], $assignmentId, 'admin', $adminId, 'unassigned', (string)$row['reviewer_id'], null);
        set_flash('success', 'Assignment removed.');
        redirect('admin/abstract-view.php?id=' . (int)$row['abstract_id']);
    }
    redirect('admin/assignment-actions.php?id=' . $assignmentId);
}
$pageTitle = 'Manage Assignment';
require __DIR__ . '/../includes/header.php';
?>
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-body">
                <h4>Manage Assignment</h4>
                <p>Abstract #<?= e($row['abs_id']) ?> · <?= e($row['full_name']) ?></p>
                <form method="post" class="mb-4"><?= csrf_field() ?><input type="hidden" name="assignment_id" value="<?= $assignmentId ?>"><input type="hidden" name="action" value="deadline"><label class="form-label">Review Deadline</label>
                    <div class="input-group"><input type="datetime-local" class="form-control" name="review_deadline" value="<?= e(str_replace(' ', 'T', substr($row['review_deadline'], 0, 16))) ?>"><button class="btn btn-outline-secondary">Update</button></div>
                </form>
                <form method="post" class="mb-4"><?= csrf_field() ?><input type="hidden" name="assignment_id" value="<?= $assignmentId ?>"><input type="hidden" name="action" value="override"><label class="form-label">Override Decision</label><select name="decision" class="form-select mb-2"><?php foreach (['pending', 'under_review', 'accepted', 'rejected', 'resubmit', 'cannot_review'] as $s): ?><option value="<?= $s ?>" <?= ($row['decision'] ?? 'pending') === $s ? 'selected' : '' ?>><?= e(decision_label($s)) ?></option><?php endforeach; ?></select><textarea name="comment" class="form-control mb-2" rows="4" placeholder="Admin comment / reviewer comment"><?= e($row['reviewer_comment']) ?></textarea>
                    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" value="1" name="is_final" id="final" <?= $row['is_final'] ? 'checked' : '' ?>><label for="final" class="form-check-label">Mark as final</label></div><button class="btn btn-brand">Save Override</button>
                </form>
                <div class="d-flex gap-2">
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="assignment_id" value="<?= $assignmentId ?>"><input type="hidden" name="action" value="reopen"><button class="btn btn-warning">Reopen Review</button></form>
                    <form method="post" onsubmit="return confirm('Remove active assignment?')"><?= csrf_field() ?><input type="hidden" name="assignment_id" value="<?= $assignmentId ?>"><input type="hidden" name="action" value="unassign"><button class="btn btn-outline-danger">Unassign</button></form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>