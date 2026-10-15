<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';

function touch_session(): void
{
    if (!empty($_SESSION['last_activity']) && (time() - (int)$_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        session_name(SESSION_NAME);
        session_start();
        set_flash('warning', 'Your session expired. Please log in again.');
        redirect('index.php');
    }
    $_SESSION['last_activity'] = time();
}

function login_admin(array $admin): void
{
    session_regenerate_id(true);
    $_SESSION['role'] = 'admin';
    $_SESSION['admin_id'] = (int)$admin['id'];
    $_SESSION['admin_name'] = $admin['full_name'];
    $_SESSION['last_activity'] = time();
}

function login_reviewer(array $reviewer): void
{
    session_regenerate_id(true);
    $_SESSION['role'] = 'reviewer';
    $_SESSION['reviewer_id'] = (int)$reviewer['id'];
    $_SESSION['reviewer_name'] = $reviewer['full_name'];
    $_SESSION['must_change_password'] = (int)$reviewer['must_change_password'];
    $_SESSION['last_activity'] = time();
}

function require_admin(): void
{
    touch_session();
    if (($_SESSION['role'] ?? '') !== 'admin' || empty($_SESSION['admin_id'])) {
        redirect('admin/login.php');
    }
}

function require_reviewer(bool $allowPasswordChange = false): void
{
    touch_session();
    if (($_SESSION['role'] ?? '') !== 'reviewer' || empty($_SESSION['reviewer_id'])) {
        redirect('reviewer/login.php');
    }
    if (!$allowPasswordChange && !empty($_SESSION['must_change_password'])) {
        redirect('reviewer/change-password.php');
    }
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
