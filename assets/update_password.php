<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login"); exit; }
require_once __DIR__ . '/db.php';

$my_id = (int)$_SESSION['user_id'];

function safeRedirectPath($path) {
    if (empty($path)) return '/';
    if (preg_match('#^https?://#i', $path)) return '/';
    if (strpos($path, '//') === 0) return '/';
    if ($path[0] !== '/') return '/' . $path;
    return $path;
}

function appendQueryParam($url, $key, $value) {
    $separator = (strpos($url, '?') === false) ? '?' : '&';
    return $url . $separator . $key . '=' . urlencode($value);
}

$redirect_to = safeRedirectPath($_POST['redirect_to'] ?? '/');

$new_raw_password = trim($_POST['new_password'] ?? '');
$confirm_password = trim($_POST['confirm_password'] ?? '');

if ($new_raw_password === '' || $confirm_password === '') {
    header("Location: " . appendQueryParam($redirect_to, 'pwmsg', 'empty'));
    exit;
}

if (strlen($new_raw_password) < 6) {
    header("Location: " . appendQueryParam($redirect_to, 'pwmsg', 'short'));
    exit;
}

if ($new_raw_password !== $confirm_password) {
    header("Location: " . appendQueryParam($redirect_to, 'pwmsg', 'mismatch'));
    exit;
}

$new_password = password_hash($new_raw_password, PASSWORD_BCRYPT);

$stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
mysqli_stmt_bind_param($stmt, "si", $new_password, $my_id);
mysqli_stmt_execute($stmt);

header("Location: " . appendQueryParam($redirect_to, 'pwmsg', 'success'));
exit;