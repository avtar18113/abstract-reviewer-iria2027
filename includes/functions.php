<?php
require_once __DIR__ . '/db.php';

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    if (!preg_match('~^https?://~i', $path)) {
        $path = APP_URL . '/' . ltrim($path, '/');
    }
    header('Location: ' . $path);
    exit;
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function client_ip(): string
{
    return substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45);
}

function user_agent(): string
{
    return substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
}

function now_sql(): string
{
    return date('Y-m-d H:i:s');
}

function normalize_decision(string $decision): string
{
    $allowed = ['pending', 'under_review', 'accepted', 'rejected', 'resubmit', 'cannot_review'];
    return in_array($decision, $allowed, true) ? $decision : 'pending';
}

function decision_label(string $decision): string
{
    switch ($decision) {
        case 'under_review': return 'Under Review';
        case 'accepted': return 'Accepted';
        case 'rejected': return 'Rejected';
        case 'resubmit': return 'Re-submit';
        case 'cannot_review': return 'Cannot Review';
        default: return 'Pending';
    }
}

function status_badge(string $decision): string
{
    switch ($decision) {
        case 'accepted': $class = 'success'; break;
        case 'rejected': $class = 'danger'; break;
        case 'resubmit': $class = 'warning'; break;
        case 'under_review': $class = 'info'; break;
        case 'cannot_review': $class = 'secondary'; break;
        default: $class = 'light text-dark';
    }
    return '<span class="badge bg-' . $class . '">' . e(decision_label($decision)) . '</span>';
}

function log_activity(?int $abstractId, ?int $assignmentId, string $actorType, ?int $actorId, string $action, ?string $oldValue = null, ?string $newValue = null, ?string $description = null): void
{
    $stmt = db()->prepare('INSERT INTO review_activity_logs
        (abstract_id, assignment_id, actor_type, actor_id, action, old_value, new_value, description, ip_address, user_agent, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
    $ip = client_ip();
    $ua = user_agent();
    $stmt->bind_param('iisissssss', $abstractId, $assignmentId, $actorType, $actorId, $action, $oldValue, $newValue, $description, $ip, $ua);
    $stmt->execute();
}

function reviewer_safe_abstract(int $abstractId): ?array
{
    // Important privacy rule: never select submitter/author/co-author PII here.
    $sql = 'SELECT id, abs_id, topic AS abstract_title, category, topic, presentation,
                   abstract, words, sQuestion, currentSubject, materialMethod, limitStudy, conclusion,
                   studyInterest, clinicalTrial, ethicalClearance, studyDesign,
                   mode_presentation1, type_of_eposter, conclusion_field,
                   aims_objectives, material_methods, results, references_text,
                   edu_aims_objectives, outline_contents, takehome_points, mode_presentation2,
                   upload_abs, upload_absnew, dateCreated
            FROM abstract
            WHERE id = ? AND del = 0
            LIMIT 1';
    $stmt = db()->prepare($sql);
    $stmt->bind_param('i', $abstractId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
}

function display_abstract_sections(array $a): array
{
    $sections = [
        'Abstract' => $a['abstract'] ?? '',
        'Aims & Objectives' => $a['aims_objectives'] ?? '',
        'Materials & Methods' => $a['material_methods'] ?? '',
        'Results' => $a['results'] ?? '',
        'Conclusion' => $a['conclusion_field'] ?? '',
        'References' => $a['references_text'] ?? '',
        'Educational Aims / Objectives' => $a['edu_aims_objectives'] ?? '',
        'Outline / Contents' => $a['outline_contents'] ?? '',
        'Take-home Points' => $a['takehome_points'] ?? '',
        'Study Question' => $a['sQuestion'] ?? '',
        'Current Subject' => $a['currentSubject'] ?? '',
        'Material / Method' => $a['materialMethod'] ?? '',
        'Limitations' => $a['limitStudy'] ?? '',
        'Legacy Conclusion' => $a['conclusion'] ?? '',
        'Study Design' => $a['studyDesign'] ?? '',
    ];
    return array_filter($sections, static function ($v) { return trim((string)$v) !== ''; });
}

function assignment_for_reviewer(int $abstractId, int $reviewerId): ?array
{
    $stmt = db()->prepare('SELECT ra.*, ar.id AS review_id, ar.decision, ar.reviewer_comment, ar.is_final, ar.is_locked, ar.submitted_at
        FROM review_assignments ra
        LEFT JOIN abstract_reviews ar ON ar.assignment_id = ra.id
        WHERE ra.abstract_id = ? AND ra.reviewer_id = ? AND ra.is_active = 1
        ORDER BY ra.id DESC LIMIT 1');
    $stmt->bind_param('ii', $abstractId, $reviewerId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function generate_temp_password(int $length = 12): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$';
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $out;
}

function pagination_url(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    return '?' . http_build_query($params);
}
