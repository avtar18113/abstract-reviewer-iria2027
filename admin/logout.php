<?php
require_once __DIR__ . '/../includes/auth.php';
if (!empty($_SESSION['admin_id'])) log_activity(null, null, 'admin', (int)$_SESSION['admin_id'], 'logout');
logout_user();
redirect('admin/login.php');
