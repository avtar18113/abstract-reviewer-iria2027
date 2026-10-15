<?php
require_once __DIR__ . '/includes/auth.php';
$role = $_SESSION['role'] ?? '';
if ($role === 'admin') redirect('admin/dashboard.php');
if ($role === 'reviewer') redirect('reviewer/dashboard.php');
redirect('reviewer/login.php');
