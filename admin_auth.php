<?php
session_start();
$ADMIN_ONLY = true;
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    die('<h1 style="color:#fb7185;text-align:center;padding:80px">403 · 需要超级管理员权限</h1><p style="text-align:center"><a href="/studio/login.php">登录</a></p>');
}
function is_admin() { return isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin'; }
