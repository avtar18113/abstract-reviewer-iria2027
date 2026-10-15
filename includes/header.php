<?php
require_once __DIR__ . '/functions.php';
$flash = get_flash();
$title = $pageTitle ?? APP_NAME;
$role = $_SESSION['role'] ?? '';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark brand-nav shadow-sm">
<div class="container-fluid px-4">
<a class="navbar-brand fw-semibold" href="<?= APP_URL ?>/">IRIA 2027 Review Portal</a>
<div class="d-flex gap-2 align-items-center">
<?php if ($role === 'admin'): ?>
<a class="btn btn-sm btn-outline-light" href="<?= APP_URL ?>/admin/dashboard.php">Dashboard</a>
<a class="btn btn-sm btn-outline-light" href="<?= APP_URL ?>/admin/abstracts.php">Abstracts</a>
<a class="btn btn-sm btn-outline-light" href="<?= APP_URL ?>/admin/reviewers.php">Reviewers</a>
<a class="btn btn-sm btn-light" href="<?= APP_URL ?>/admin/logout.php">Logout</a>
<?php elseif ($role === 'reviewer'): ?>
<a class="btn btn-sm btn-outline-light" href="<?= APP_URL ?>/reviewer/dashboard.php">Dashboard</a>
<a class="btn btn-sm btn-outline-light" href="<?= APP_URL ?>/reviewer/abstracts.php">My Abstracts</a>
<a class="btn btn-sm btn-light" href="<?= APP_URL ?>/reviewer/logout.php">Logout</a>
<?php endif; ?>
</div>
</div>
</nav>
<main class="container-fluid px-3 px-lg-4 py-4">
<?php if ($flash): ?>
<div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
<?= e($flash['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
