<?php
require_once __DIR__ . '/../includes/auth.php';require_once __DIR__ . '/../includes/mailer.php';require_reviewer();$rid=(int)$_SESSION['reviewer_id'];$id=(int)($_GET['id']??$_POST['abstract_id']??0);$assignment=assignment_for_reviewer($id,$rid);if(!$assignment){http_response_code(403);exit('This abstract is not assigned to you.');}$a=reviewer_safe_abstract($id);if(!$a){http_response_code(404);exit('Abstract not found.');}
if(empty($assignment['opened_at'])){$s=db()->prepare('UPDATE review_assignments SET opened_at=NOW() WHERE id=?');$aid=(int)$assignment['id'];$s->bind_param('i',$aid);$s->execute();log_activity($id,$aid,'reviewer',$rid,'abstract_opened');}
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();$decision=normalize_decision($_POST['decision']??'pending');$comment=trim($_POST['reviewer_comment']??'');$mode=$_POST['submit_mode']??'save';$isFinal=$mode==='final';
 if(!in_array($decision,['pending','under_review','accepted','rejected','resubmit','cannot_review'],true))$error='Invalid decision.';
 if(in_array($decision,['rejected','resubmit','under_review','cannot_review'],true)&&$comment==='')$error='Comment is required for this decision.';
 if(!empty($assignment['is_locked'])&&!in_array($assignment['decision'],['accepted','rejected'],true)){} elseif(!empty($assignment['is_locked']))$error='This final review is locked. Please contact the administrator.';
 if($error===''){
  $aid=(int)$assignment['id'];$locked=($isFinal&&in_array($decision,['accepted','rejected'],true))?1:0;$final=$isFinal?1:0;
  $s=db()->prepare('UPDATE abstract_reviews SET decision=?,reviewer_comment=?,is_final=?,is_locked=?,submitted_at=IF(?=1,NOW(),submitted_at),updated_at=NOW() WHERE assignment_id=?');$s->bind_param('ssiiii',$decision,$comment,$final,$locked,$final,$aid);$s->execute();
  $u=db()->prepare('UPDATE review_assignments SET status=?,started_at=COALESCE(started_at,NOW()),completed_at=IF(?=1,NOW(),completed_at),updated_at=NOW() WHERE id=?');$u->bind_param('sii',$decision,$final,$aid);$u->execute();
  log_activity($id,$aid,'reviewer',$rid,$isFinal?'review_final_submitted':'review_saved',$assignment['decision']??'pending',$decision,$comment);
  if($isFinal){email_admin_review_completed($a['abs_id'],$_SESSION['reviewer_name'],$decision);if($decision==='cannot_review')email_admin_cannot_review($a['abs_id'],$_SESSION['reviewer_name'],$comment);set_flash('success','Review submitted successfully.');}else set_flash('success','Review saved.');
  redirect('reviewer/abstract-view.php?id='.$id);
 }
}
$assignment=assignment_for_reviewer($id,$rid);$locked=!empty($assignment['is_locked']);$pageTitle='Review Abstract '.$a['abs_id'];require __DIR__ . '/../includes/header.php';
?>
<div class="row g-4"><div class="col-xl-8"><div class="card"><div class="card-body"><div class="d-flex justify-content-between align-items-start"><div><div class="small-muted">Abstract #<?= e($a['abs_id']) ?></div><h3><?= e($a['abstract_title']) ?></h3></div><?= status_badge($assignment['status']) ?></div><div class="row g-3 mt-1"><div class="col-md-4"><strong>Presentation</strong><br><?= e($a['presentation']) ?></div><div class="col-md-4"><strong>Mode 1</strong><br><?= e($a['mode_presentation1']) ?></div><div class="col-md-4"><strong>Mode 2</strong><br><?= e($a['mode_presentation2']) ?></div>

<?php if($a['type_of_eposter']):?>
<div class="col-md-4"><strong>E-Poster Type</strong><br><?= e($a['type_of_eposter']) ?></div>
<?php endif;?>
<div class="col-md-4"><strong>Deadline</strong><br><?= e($assignment['review_deadline']?date('d M Y, h:i A',strtotime($assignment['review_deadline'])):'—') ?></div>

</div>
<?php if(!empty($a['upload_abs'])||!empty($a['upload_absnew'])):?>
<div class="mt-3">
    
    <a class="btn btn-outline-secondary d-none" href="download-abstract.php?id=<?= $id ?>">View / Download Abstract File</a>

</div>
<?php endif;?>
<hr><?php foreach(display_abstract_sections($a) as $label=>$text):?><h6 class="section-title mt-4"><?= e($label) ?></h6><div class="abstract-text"><?= e($text) ?></div><?php endforeach;?></div></div></div><div class="col-xl-4"><div class="card filter-card"><div class="card-body"><h5 class="section-title">Your Review</h5><?php if($error):?><div class="alert alert-danger"><?= e($error) ?></div><?php endif;?><?php if($locked):?><div class="alert alert-success">Final decision <strong><?= e(decision_label($assignment['decision'])) ?></strong> is locked. Contact the Admin if it must be reopened.</div><p><strong>Comment</strong></p><div class="abstract-text"><?= e($assignment['reviewer_comment']) ?></div><?php else:?><form method="post" id="reviewForm"><?= csrf_field() ?><input type="hidden" name="abstract_id" value="<?= $id ?>"><div class="mb-3"><label class="form-label required">Decision</label><select class="form-select" name="decision" required><?php foreach(['pending','under_review','accepted','rejected','resubmit','cannot_review'] as $s):?><option value="<?= $s ?>" <?= ($assignment['decision']??'pending')===$s?'selected':'' ?>><?= e(decision_label($s)) ?></option><?php endforeach;?></select></div><div class="mb-3"><label class="form-label">Comment</label><textarea class="form-control" name="reviewer_comment" rows="8"><?= e($assignment['reviewer_comment']) ?></textarea><div class="form-text">Mandatory for Rejected, Re-submit, Under Review and Cannot Review.</div></div><div class="d-grid gap-2"><button class="btn btn-outline-secondary" name="submit_mode" value="save">Save Draft</button><button class="btn btn-brand" name="submit_mode" value="final" onclick="return confirm('Final submit this review? Accepted and Rejected decisions will be locked.')">Final Submit</button></div></form><?php endif;?></div></div></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
