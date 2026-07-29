<?php
$host = 'localhost';
$dbUsername = 'root';
$dbPassword = '';
$database = 'login_system';

$conn = new mysqli($host, $dbUsername, $dbPassword, $database);
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}
$conn->set_charset('utf8mb4');
