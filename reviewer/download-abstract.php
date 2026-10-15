<?php
require_once __DIR__ . '/../includes/auth.php';
require_reviewer();
$rid = (int)$_SESSION['reviewer_id'];
$id = (int)($_GET['id'] ?? 0);
$assignment = assignment_for_reviewer($id, $rid);
if (!$assignment) {
    http_response_code(403);
    exit('Access denied.');
}
$a = reviewer_safe_abstract($id);
if (!$a) {
    http_response_code(404);
    exit('Abstract not found.');
}
$filename = trim((string)($a['upload_absnew'] ?: $a['upload_abs']));
if ($filename === '') {
    http_response_code(404);
    exit('No abstract file uploaded.');
}
$filename = basename($filename);
$base = rtrim(ABSTRACT_FILE_BASE_PATH, '/\\');
$full = $base . DIRECTORY_SEPARATOR . $filename;
$realBase = realpath($base);
$realFile = realpath($full);
if (!$realBase || !$realFile || strpos($realFile, $realBase) !== 0 || !is_file($realFile)) {
    http_response_code(404);
    exit('Abstract file could not be located. Check ABSTRACT_FILE_BASE_PATH configuration.');
}
log_activity($id, (int)$assignment['id'], 'reviewer', $rid, 'abstract_file_downloaded', null, $filename);
$mime = mime_content_type($realFile) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($realFile));
header('Content-Disposition: inline; filename="abstract-' . $a['abs_id'] . '.' . pathinfo($filename, PATHINFO_EXTENSION) . '"');
header('X-Content-Type-Options: nosniff');
readfile($realFile);
exit;
