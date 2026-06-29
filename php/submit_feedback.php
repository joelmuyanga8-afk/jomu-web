<?php
declare(strict_types=1);

require_once __DIR__ . '/connection/env.php';
require_once __DIR__ . '/partials/helpers.php';
require_once __DIR__ . '/partials/smtp_mailer.php';
load_env_file(dirname(__DIR__) . '/.env');

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /feedback?status=error&message=' . rawurlencode('Invalid request method.'));
    exit();
}

$businessName = trim((string) ($_POST['business_name'] ?? ''));
$emailAddress = trim((string) ($_POST['email_address'] ?? ''));
$feedbackText = trim((string) ($_POST['feedback'] ?? ''));
jomu_require_csrf();
jomu_require_rate_limit('feedback', 5, 60 * 60, 'Too many feedback submissions. Please wait and try again.', $emailAddress);

if ($businessName === '' || $emailAddress === '' || $feedbackText === '') {
    header('Location: /feedback?status=error&message=' . rawurlencode('All fields are required.'));
    exit();
}

if (!filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
    header('Location: /feedback?status=error&message=' . rawurlencode('Please provide a valid email address.'));
    exit();
}

$businessNameLength = function_exists('mb_strlen') ? mb_strlen($businessName) : strlen($businessName);
$feedbackLength = function_exists('mb_strlen') ? mb_strlen($feedbackText) : strlen($feedbackText);

if ($businessNameLength > 120 || $feedbackLength > 1000) {
    header('Location: /feedback?status=error&message=' . rawurlencode('Input is too long.'));
    exit();
}

$feedbackTo = env_value('FEEDBACK_TO_EMAIL', env_value('SUPPORT_EMAIL'));

if (!$feedbackTo) {
    header('Location: /feedback?status=error&message=' . rawurlencode('Feedback email is not configured yet.'));
    exit();
}

$subject = 'New JoMu Feedback Submission';
$safeBusinessName = htmlspecialchars($businessName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$safeEmailAddress = htmlspecialchars($emailAddress, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$safeFeedbackText = htmlspecialchars($feedbackText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$textBody = "New feedback received from JoMu website.\n\n"
    . "Business Name: {$businessName}\n"
    . "Email Address: {$emailAddress}\n\n"
    . "Feedback:\n{$feedbackText}\n";

$htmlBody = '<p>New feedback received from JoMu website.</p>'
    . '<p><strong>Business Name:</strong> ' . $safeBusinessName . '<br>'
    . '<strong>Email Address:</strong> ' . $safeEmailAddress . '</p>'
    . '<p><strong>Feedback:</strong><br>' . nl2br($safeFeedbackText) . '</p>';

$reason = '';
$sent = jomu_send_smtp_mail($feedbackTo, $subject, $textBody, $htmlBody, $reason, [
    'from_email' => env_value('SMTP_FEEDBACK_FROM_EMAIL', env_value('SMTP_FROM_EMAIL', '')),
    'from_name' => env_value('SMTP_FEEDBACK_FROM_NAME', env_value('SMTP_FROM_NAME', 'JoMu Feedback')),
    'reply_to' => $emailAddress,
]);

if (!$sent) {
    error_log('Feedback SMTP error: ' . $reason);
    header('Location: /feedback?status=error&message=' . rawurlencode('Unable to submit feedback right now. Please try again.'));
    exit();
}

header('Location: /feedback?status=success&message=' . rawurlencode('Thank you. Your feedback has been sent successfully.'));
exit();
