<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$format = strtolower(trim($_GET['format'] ?? 'csv'));
if (!in_array($format, ['csv', 'excel'], true)) {
    $format = 'csv';
}

$where = ['a.del=0'];
$types = '';
$params = [];

$filterMap = [
    'presentation' => 'a.presentation',
    'mode1' => 'a.mode_presentation1',
    'mode2' => 'a.mode_presentation2',
    'eposter' => 'a.type_of_eposter',
];

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
if ($status === 'assigned') {
    $where[] = 'ra.id IS NOT NULL';
} elseif ($status === 'unassigned') {
    $where[] = 'ra.id IS NULL';
} elseif ($status === 'reviewed') {
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
    $where[] = 'ra.reviewer_id = ?';
    $types .= 'i';
    $params[] = $reviewerFilter;
}

$sql = "SELECT
            a.abs_id,
            a.topic AS abstract_title,
            a.category,
            a.presentation,
            a.mode_presentation1,
            a.mode_presentation2,
            a.type_of_eposter,
            r.full_name AS reviewer_name,
            COALESCE(ar.decision, ra.status, 'unassigned') AS decision,
            ar.reviewer_comment,
            ra.assigned_at,
            ra.review_deadline,
            COALESCE(ar.submitted_at, ra.completed_at) AS reviewed_at
        FROM abstract a
        LEFT JOIN review_assignments ra
            ON ra.abstract_id = a.id AND ra.is_active = 1
        LEFT JOIN reviewers r
            ON r.id = ra.reviewer_id
        LEFT JOIN abstract_reviews ar
            ON ar.assignment_id = ra.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY a.id DESC";

$stmt = db()->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Prevent Excel/CSV formula injection for values that start with = + - or @.
function export_safe($value)
{
    $value = (string)($value ?? '');
    if ($value !== '' && preg_match('/^[=+\-@]/', $value)) {
        return "'" . $value;
    }
    return $value;
}

function export_date($value)
{
    if (empty($value)) {
        return '';
    }
    $time = strtotime($value);
    return $time ? date('d-m-Y H:i', $time) : (string)$value;
}

$headers = [
    'Abstract ID',
    'Title',
    'Category',
    'Presentation',
    'Mode 1',
    'Mode 2',
    'E-Poster Type',
    'Reviewer',
    'Decision',
    'Comments',
    'Score',
    'Assigned Date',
    'Review Deadline',
    'Reviewed Date',
];

$data = [];
foreach ($rows as $row) {
    $data[] = [
        export_safe($row['abs_id']),
        export_safe($row['abstract_title']),
        export_safe($row['category']),
        export_safe($row['presentation']),
        export_safe($row['mode_presentation1']),
        export_safe($row['mode_presentation2']),
        export_safe($row['type_of_eposter']),
        export_safe($row['reviewer_name'] ?: 'Unassigned'),
        export_safe($row['decision'] === 'unassigned' ? 'Unassigned' : decision_label($row['decision'])),
        export_safe($row['reviewer_comment']),
        'N/A', // scoring is not enabled in the current portal
        export_date($row['assigned_at']),
        export_date($row['review_deadline']),
        export_date($row['reviewed_at']),
    ];
}

$stamp = date('Ymd_His');

log_activity(null, null, 'admin', (int)$_SESSION['admin_id'], 'export_abstracts', null, $format, 'Exported ' . count($rows) . ' filtered abstract review record(s).');

if ($format === 'excel') {
    $filename = 'IRIA_2027_Abstract_Review_Export_' . $stamp . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    echo "\xEF\xBB\xBF";
    echo '<html><head><meta charset="UTF-8"></head><body><table border="1">';
    echo '<tr>';
    foreach ($headers as $heading) {
        echo '<th>' . htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') . '</th>';
    }
    echo '</tr>';

    foreach ($data as $record) {
        echo '<tr>';
        foreach ($record as $cell) {
            echo '<td>' . htmlspecialchars((string)$cell, ENT_QUOTES, 'UTF-8') . '</td>';
        }
        echo '</tr>';
    }
    echo '</table></body></html>';
} else {
    $filename = 'IRIA_2027_Abstract_Review_Export_' . $stamp . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    // UTF-8 BOM so Microsoft Excel displays Unicode correctly.
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $headers);
    foreach ($data as $record) {
        fputcsv($out, $record);
    }
    fclose($out);
}

exit;
