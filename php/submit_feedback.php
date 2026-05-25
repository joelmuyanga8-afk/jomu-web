<?php
declare(strict_types=1);

require_once __DIR__ . '/connection/env.php';
require_once __DIR__ . '/partials/helpers.php';
load_env_file(dirname(__DIR__) . '/.env');

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../feedback.html?status=error&message=' . rawurlencode('Invalid request method.'));
    exit();
}

$businessName = trim((string) ($_POST['business_name'] ?? ''));
$emailAddress = trim((string) ($_POST['email_address'] ?? ''));
$feedbackText = trim((string) ($_POST['feedback'] ?? ''));
jomu_require_csrf();
jomu_require_rate_limit('feedback', 5, 60 * 60, 'Too many feedback submissions. Please wait and try again.', $emailAddress);

if ($businessName === '' || $emailAddress === '' || $feedbackText === '') {
    header('Location: ../feedback.html?status=error&message=' . rawurlencode('All fields are required.'));
    exit();
}

if (!filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
    header('Location: ../feedback.html?status=error&message=' . rawurlencode('Please provide a valid email address.'));
    exit();
}

$businessNameLength = function_exists('mb_strlen') ? mb_strlen($businessName) : strlen($businessName);
$feedbackLength = function_exists('mb_strlen') ? mb_strlen($feedbackText) : strlen($feedbackText);

if ($businessNameLength > 120 || $feedbackLength > 1000) {
    header('Location: ../feedback.html?status=error&message=' . rawurlencode('Input is too long.'));
    exit();
}

$mailgunApiKey = env_value('MAILGUN_API_KEY');
$mailgunDomain = env_value('MAILGUN_DOMAIN');
$mailgunFrom = env_value('MAILGUN_FROM', 'JoMu Feedback <no-reply@jomu.ug>');
$feedbackTo = env_value('FEEDBACK_TO_EMAIL', env_value('SUPPORT_EMAIL'));

if (!$mailgunApiKey || !$mailgunDomain || !$feedbackTo) {
    header('Location: ../feedback.html?status=error&message=' . rawurlencode('Feedback email is not configured yet.'));
    exit();
}

if (!function_exists('curl_init')) {
    header('Location: ../feedback.html?status=error&message=' . rawurlencode('Server cannot send feedback right now.'));
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

$endpoint = 'https://api.mailgun.net/v3/' . rawurlencode($mailgunDomain) . '/messages';
$postFields = [
    'from' => $mailgunFrom,
    'to' => $feedbackTo,
    'subject' => $subject,
    'text' => $textBody,
    'html' => $htmlBody,
    'h:Reply-To' => $emailAddress
];

$ch = curl_init($endpoint);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $postFields,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_USERPWD => 'api:' . $mailgunApiKey,
    CURLOPT_HTTPAUTH => CURLAUTH_BASIC
]);

$response = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $httpCode < 200 || $httpCode >= 300) {
    error_log('Feedback Mailgun error. HTTP: ' . $httpCode . '; cURL: ' . $curlError . '; Response: ' . (string) $response);
    header('Location: ../feedback.html?status=error&message=' . rawurlencode('Unable to submit feedback right now. Please try again.'));
    exit();
}

header('Location: ../feedback.html?status=success&message=' . rawurlencode('Thank you. Your feedback has been sent successfully.'));
exit();
