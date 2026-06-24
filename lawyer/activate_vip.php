<?php
/**
 * VIP激活接口 - 用户支付后自助激活
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/auth_lawyer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => '仅支持POST'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!is_logged_in()) {
    echo json_encode(['error' => '请先登录'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (is_vip()) {
    echo json_encode(['error' => '您已是VIP会员'], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$amount = floatval($input['amount'] ?? 0);

if ($amount < 100) {
    echo json_encode(['error' => 'VIP月费100元，支付金额不足'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 开通VIP
$username = $_SESSION['user']['username'];
$users_file = '/www/wwwroot/aifupang.com/studio/data/users.json';
$data = json_decode(file_get_contents($users_file), true);

$found = false;
foreach ($data['users'] as &$u) {
    if ($u['username'] === $username) {
        $u['role'] = 'vip';
        $u['vip_expiry'] = date('Y-m-d H:i:s', strtotime('+30 days'));
        $u['vip_activated'] = date('Y-m-d H:i:s');
        $u['vip_amount'] = $amount;
        $found = true;
        break;
    }
}

if (!$found) {
    echo json_encode(['error' => '用户不存在'], JSON_UNESCAPED_UNICODE);
    exit;
}

file_put_contents($users_file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

// 更新session
$_SESSION['user']['role'] = 'vip';
$_SESSION['user']['vip_expiry'] = date('Y-m-d H:i:s', strtotime('+30 days'));

echo json_encode([
    'success' => true,
    'message' => 'VIP已激活！有效期至 ' . date('Y-m-d', strtotime('+30 days')),
    'vip_expiry' => date('Y-m-d', strtotime('+30 days')),
], JSON_UNESCAPED_UNICODE);
