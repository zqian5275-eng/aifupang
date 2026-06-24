<?php
/**
 * 微信支付回调通知 - 支付成功后自动激活VIP
 */
require_once __DIR__ . '/wechat_pay_config.php';

// 获取回调数据
$body = file_get_contents('php://input');
$headers = getallheaders();
$timestamp = $headers['Wechatpay-Timestamp'] ?? '';
$nonce = $headers['Wechatpay-Nonce'] ?? '';
$signature = $headers['Wechatpay-Signature'] ?? '';
$serial = $headers['Wechatpay-Serial'] ?? '';

// 记录日志
file_put_contents(__DIR__ . '/wx_notify.log', date('Y-m-d H:i:s') . " NOTIFY: " . $body . "\n", FILE_APPEND);

$data = json_decode($body, true);
if (!$data || !isset($data['resource'])) {
    http_response_code(400);
    echo json_encode(['code' => 'FAIL', 'message' => 'Invalid data']);
    exit;
}

// 解密
$resource = $data['resource'];
$decrypted = wx_decrypt_notify(
    $resource['associated_data'] ?? '',
    $resource['nonce'] ?? '',
    $resource['ciphertext'] ?? ''
);

if (!$decrypted) {
    http_response_code(400);
    echo json_encode(['code' => 'FAIL', 'message' => 'Decrypt failed']);
    exit;
}

$notify = json_decode($decrypted, true);
file_put_contents(__DIR__ . '/wx_notify.log', date('Y-m-d H:i:s') . " DECRYPTED: " . json_encode($notify) . "\n", FILE_APPEND);

if ($notify['trade_state'] !== 'SUCCESS') {
    echo json_encode(['code' => 'SUCCESS']);  // 非成功状态也返回成功避免重试
    exit;
}

// 支付成功，激活VIP
$out_trade_no = $notify['out_trade_no'] ?? '';
$amount = ($notify['amount']['total'] ?? 0) / 100;

// 从订单号提取用户名（简化方案：遍历pending订单）
// 更可靠的方案：用文件记录pending订单
$pending_file = __DIR__ . '/pending_orders.json';
$pending = file_exists($pending_file) ? json_decode(file_get_contents($pending_file), true) : [];
$username = $pending[$out_trade_no] ?? null;

if (!$username) {
    // 尝试从out_trade_no格式 VIP20260609xxx 反推
    // 无法确定用户，记录并人工处理
    file_put_contents(__DIR__ . '/wx_notify.log', date('Y-m-d H:i:s') . " UNMATCHED: $out_trade_no" . "\n", FILE_APPEND);
    echo json_encode(['code' => 'SUCCESS']);
    exit;
}

// 激活VIP
$users_file = '/www/wwwroot/aifupang.com/studio/data/users.json';
$users = json_decode(file_get_contents($users_file), true);
foreach ($users['users'] as &$u) {
    if ($u['username'] === $username) {
        $u['role'] = 'vip';
        $u['vip_expiry'] = date('Y-m-d H:i:s', strtotime('+30 days'));
        $u['vip_activated'] = date('Y-m-d H:i:s');
        $u['vip_amount'] = $amount;
        $u['wx_transaction_id'] = $notify['transaction_id'] ?? '';
        break;
    }
}
file_put_contents($users_file, json_encode($users, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

// 清理pending订单
unset($pending[$out_trade_no]);
file_put_contents($pending_file, json_encode($pending));

file_put_contents(__DIR__ . '/wx_notify.log', date('Y-m-d H:i:s') . " ACTIVATED: $username VIP until " . date('Y-m-d', strtotime('+30 days')) . "\n", FILE_APPEND);

// 返回成功
echo json_encode(['code' => 'SUCCESS']);
