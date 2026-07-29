<?php
session_start();
require_once 'config.php';
require_once 'helpers.php';

if (!isset($_SESSION['user_id'])) {
    tryRememberLogin($conn);
}
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = 'Please log in first.';
    header('Location: Login.php');
    exit;
}
