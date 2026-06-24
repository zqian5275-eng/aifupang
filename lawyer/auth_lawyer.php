<?php
session_start();
require_once '/www/wwwroot/aifupang.com/studio/auth.php';

function is_vip() {
    if (!is_logged_in()) return false;
    $user = $_SESSION['user'];
    if ($user['role'] === 'admin') return true;
    if ($user['role'] === 'vip') {
        $expiry = $user['vip_expiry'] ?? null;
        return $expiry && strtotime($expiry) > time();
    }
    return false;
}

function get_vip_info() {
    if (!is_logged_in()) return null;
    $user = $_SESSION['user'];
    return [
        'username' => $user['username'],
        'is_vip' => is_vip(),
        'role' => $user['role'],
        'vip_expiry' => $user['vip_expiry'] ?? null,
    ];
}
