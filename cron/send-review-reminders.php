<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';

if (PHP_SAPI !== 'cli') {
    if (!hash_equals(CRON_SECRET, $_GET['key'] ?? '')) {
        http_response_code(403); exit('Forbidden');
    }
}

// Send reminder once per day for assignments due in <= 2 days or already overdue.
$sql = "SELECT ra.id,ra.abstract_id,ra.review_deadline,ra.status,r.full_name,r.email,a.abs_id
        FROM review_assignments ra
        JOIN reviewers r ON r.id=ra.reviewer_id
        JOIN abstract a ON a.id=ra.abstract_id
        WHERE ra.is_active=1 AND r.is_active=1
          AND ra.status NOT IN ('accepted','rejected')
          AND ra.review_deadline IS NOT NULL
          AND ra.review_deadline <= DATE_ADD(NOW(), INTERVAL 2 DAY)
          AND NOT EXISTS (
              SELECT 1 FROM review_activity_logs l
              WHERE l.assignment_id=ra.id AND l.action='reminder_sent'
                AND DATE(l.created_at)=CURDATE()
          )";
$res=db()->query($sql);$count=0;
while($row=$res->fetch_assoc()){
    $body='<p>Dear '.htmlspecialchars($row['full_name']).',</p><p>This is a reminder to complete your review for abstract <strong>#'.htmlspecialchars($row['abs_id']).'</strong>.</p><p><strong>Deadline:</strong> '.htmlspecialchars(date('d M Y, h:i A',strtotime($row['review_deadline']))).'</p><p><a href="'.APP_URL.'/reviewer/abstract-view.php?id='.(int)$row['abstract_id'].'">Open Abstract</a></p>';
    if(send_mail($row['email'],'IRIA 2027 – Abstract Review Reminder',mail_shell('Abstract Review Reminder',$body))){log_activity((int)$row['abstract_id'],(int)$row['id'],'system',null,'reminder_sent',null,null,'Deadline reminder sent');$count++;}
}
echo "Sent $count reminder(s).\n";
