<?php

function normalize_business_name_input(string $value): string
{
    $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $stripped = strip_tags($decoded);
    $normalizedWhitespace = preg_replace('/\s+/u', ' ', $stripped);
    return trim((string) $normalizedWhitespace);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    session_start();
    require_once __DIR__ . '/partials/helpers.php';

    $business_name = normalize_business_name_input((string) ($_POST['business_name'] ?? ''));
    $emailormobilenumber = trim((string) ($_POST['emailormobilenumber'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    jomu_require_csrf();
    jomu_require_rate_limit('createaccount', 6, 60 * 60, 'Too many account creation attempts. Please wait and try again.', $emailormobilenumber);

    include "connection/dbconn.php";

    // Email and mobile number validation
    $isEmail = filter_var($emailormobilenumber, FILTER_VALIDATE_EMAIL);
    $isPhone = preg_match('/^(\+?256|0)?7\d{8}$/', $emailormobilenumber);
    if (!$isEmail && !$isPhone) {
        $params = [
            'error' => 'invalid',
            'emailormobilenumber' => $emailormobilenumber,
            'business_name' => $business_name
        ];
        header("Location: ../createaccount.html?" . http_build_query($params));
        exit();
    }

    if ($business_name === '' || mb_strlen($business_name) < 3 || mb_strlen($business_name) > 40) {
        $params = [
            'error' => 'invalid_business_name',
            'emailormobilenumber' => $emailormobilenumber,
            'business_name' => $business_name
        ];
        header("Location: ../createaccount.html?" . http_build_query($params));
        exit();
    }

    if (jomu_is_reserved_business_name($business_name)) {
        $params = [
            'error' => 'reserved_business_name',
            'emailormobilenumber' => $emailormobilenumber,
            'business_name' => $business_name
        ];
        header("Location: ../createaccount.html?" . http_build_query($params));
        exit();
    }

    if (strlen($password) < 8) {
        $params = [
            'error' => 'weak_password',
            'emailormobilenumber' => $emailormobilenumber,
            'business_name' => $business_name
        ];
        header("Location: ../createaccount.html?" . http_build_query($params));
        exit();
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (businessname, emailormobilenumber, password) VALUES (?, ?, ?)");
    if (!$stmt) {
        error_log('Create account prepare failed: ' . $conn->error);
        http_response_code(500);
        echo "Unable to create account right now. Please try again.";
        exit();
    }

    $stmt->bind_param('sss', $business_name, $emailormobilenumber, $hashedPassword);

    try {
        if ($stmt->execute()) {
            $stmt->close();
            header("Location: ../createaccount.html?success=1");
            exit();
        }

        $errorCode = (int) $stmt->errno;
        $errorMessage = $stmt->error;
        $stmt->close();
        if ($errorCode === 1062) {
            header("Location: ../createaccount.html?error=emailmobilenumber_exists&email=" .
                urlencode($emailormobilenumber) . "&business_name=" .
                urlencode($business_name));
            exit();
        }
        error_log('Create account failed: ' . $errorMessage);
        http_response_code(500);
        echo "Unable to create account right now. Please try again.";
    } catch (mysqli_sql_exception $e) {
        $stmt->close();
        error_log('Create account failed: ' . $e->getMessage());
        http_response_code(500);
        echo "Unable to create account right now. Please try again.";
    }
}
