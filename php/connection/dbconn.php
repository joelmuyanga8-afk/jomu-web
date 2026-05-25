<?php

require_once __DIR__ . '/env.php';

load_env_file(__DIR__ . '/../../.env');

$servername = env_value('DB_HOST', 'localhost');
$username = env_value('DB_USER', 'root');
$pwd = env_value('DB_PASSWORD', '');
$dbname = env_value('DB_NAME', 'jomudb');
$port = (int) env_value('DB_PORT', '3306');

mysqli_report(MYSQLI_REPORT_OFF);

$conn = new mysqli(
    hostname: $servername,
    username: $username,
    password: $pwd,
    database: $dbname,
    port: $port
);

if ($conn->connect_error) {
    error_log('Database connection failed: ' . $conn->connect_error);
    http_response_code(500);
    exit('Service temporarily unavailable.');
}

$conn->set_charset('utf8mb4');
