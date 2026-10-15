<?php
require_once __DIR__ . '/../config/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once PHPMAILER_PATH . '/src/Exception.php';
require_once PHPMAILER_PATH . '/src/PHPMailer.php';
require_once PHPMAILER_PATH . '/src/SMTP.php';

function send_mail(string $to, string $subject, string $html, string $alt = ''): bool
{
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = SMTP_USER !== '';
        if ($mail->SMTPAuth) {
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
        }
        if (SMTP_SECURE !== '') {
            $mail->SMTPSecure = SMTP_SECURE;
        }
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = $alt ?: trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html)));
        return $mail->send();
    } catch (Throwable $e) {
        error_log('IRIA Review mail error: ' . $e->getMessage());
        return false;
    }
}

function mail_shell(string $title, string $body): string
{
    return '<div style="font-family:Arial,sans-serif;background:#f5f7fb;padding:24px">'
        . '<div style="max-width:680px;margin:auto;background:#fff;border-radius:12px;padding:28px;border:1px solid #e5e7eb">'
        . '<h2 style="color:#7a1230;margin-top:0">' . htmlspecialchars($title) . '</h2>'
        . $body
        . '<hr style="border:0;border-top:1px solid #e5e7eb;margin:28px 0">'
        . '<p style="color:#6b7280;font-size:13px">IRIA 2027 Delhi – Abstract Review Portal</p>'
        . '</div></div>';
}

function email_reviewer_created(string $email, string $name, string $tempPassword): bool
{
    $body = '<p>Dear ' . htmlspecialchars($name) . ',</p>'
        . '<p>You have been added as an abstract reviewer for IRIA 2027 Delhi.</p>'
        . '<p><strong>Portal:</strong> <a href="' . APP_URL . '/reviewer/login.php">' . APP_URL . '/reviewer/login.php</a><br>'
        . '<strong>Username:</strong> ' . htmlspecialchars($email) . '<br>'
        . '<strong>Temporary Password:</strong> ' . htmlspecialchars($tempPassword) . '</p>'
        . '<p>You will be asked to change this password after your first login.</p>';
    return send_mail($email, 'IRIA 2027 – Reviewer Account Created', mail_shell('Reviewer Account Created', $body));
}

function email_assignment(string $email, string $name, int $count, string $deadline, bool $reassignment = false): bool
{
    $subject = $reassignment ? 'IRIA 2027 – Abstract Reassigned' : 'IRIA 2027 – New Abstract Assignment';
    $body = '<p>Dear ' . htmlspecialchars($name) . ',</p>'
        . '<p>' . ($reassignment ? 'An abstract has been reassigned to you.' : 'You have received ' . $count . ' abstract assignment(s).') . '</p>'
        . '<p><strong>Review deadline:</strong> ' . htmlspecialchars($deadline) . '</p>'
        . '<p><a href="' . APP_URL . '/reviewer/abstracts.php">Open Reviewer Portal</a></p>';
    return send_mail($email, $subject, mail_shell($subject, $body));
}

function email_admin_review_completed(string $absId, string $reviewer, string $decision): bool
{
    if (ADMIN_NOTIFICATION_EMAIL === '') return false;
    $body = '<p>A reviewer has submitted a final review.</p>'
        . '<p><strong>Abstract:</strong> ' . htmlspecialchars($absId) . '<br>'
        . '<strong>Reviewer:</strong> ' . htmlspecialchars($reviewer) . '<br>'
        . '<strong>Decision:</strong> ' . htmlspecialchars(decision_label($decision)) . '</p>'
        . '<p><a href="' . APP_URL . '/admin/abstracts.php">Open Admin Portal</a></p>';
    return send_mail(ADMIN_NOTIFICATION_EMAIL, 'IRIA 2027 – Review Completed', mail_shell('Review Completed', $body));
}

function email_admin_cannot_review(string $absId, string $reviewer, string $comment): bool
{
    if (ADMIN_NOTIFICATION_EMAIL === '') return false;
    $body = '<p>A reviewer marked an abstract as <strong>Cannot Review</strong>.</p>'
        . '<p><strong>Abstract:</strong> ' . htmlspecialchars($absId) . '<br>'
        . '<strong>Reviewer:</strong> ' . htmlspecialchars($reviewer) . '</p>'
        . '<p><strong>Reason:</strong><br>' . nl2br(htmlspecialchars($comment)) . '</p>';
    return send_mail(ADMIN_NOTIFICATION_EMAIL, 'IRIA 2027 – Reviewer Cannot Review', mail_shell('Reviewer Cannot Review', $body));
}
