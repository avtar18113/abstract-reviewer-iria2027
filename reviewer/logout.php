<?php
require_once __DIR__ . '/../includes/auth.php';
if(!empty($_SESSION['reviewer_id']))log_activity(null,null,'reviewer',(int)$_SESSION['reviewer_id'],'logout');
logout_user();redirect('reviewer/login.php');
