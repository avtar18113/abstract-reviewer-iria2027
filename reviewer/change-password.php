<?php
require_once __DIR__ . '/../includes/auth.php';require_reviewer(true);$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();$p=$_POST['password']??'';$c=$_POST['confirm_password']??'';
 if(strlen($p)<8)$error='Password must be at least 8 characters.';elseif($p!==$c)$error='Passwords do not match.';else{$hash=password_hash($p,PASSWORD_DEFAULT);$id=(int)$_SESSION['reviewer_id'];$s=db()->prepare('UPDATE reviewers SET password=?,must_change_password=0,updated_at=NOW() WHERE id=?');$s->bind_param('si',$hash,$id);$s->execute();$_SESSION['must_change_password']=0;log_activity(null,null,'reviewer',$id,'password_changed');set_flash('success','Password updated.');redirect('reviewer/dashboard.php');}
}
$pageTitle='Change Password';require __DIR__ . '/../includes/header.php';
?>
<div class="card login-card"><div class="card-body p-4"><h4>Change Password</h4><p class="text-muted">Set a new password before reviewing abstracts.</p><?php if($error):?><div class="alert alert-danger"><?= e($error) ?></div><?php endif;?><form method="post"><?= csrf_field() ?><div class="mb-3"><label class="form-label">New Password</label><input type="password" name="password" class="form-control" required></div><div class="mb-3"><label class="form-label">Confirm Password</label><input type="password" name="confirm_password" class="form-control" required></div><button class="btn btn-brand">Update Password</button></form></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
