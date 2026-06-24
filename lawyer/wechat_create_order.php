<?php
/**
 * 创建微信支付订单 - 返回code_url给前端生成二维码
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/auth_lawyer.php';
require_once __DIR__ . '/wechat_pay_config.php';

if (!is_logged_in()) {
    echo json_encode(['error' => '请先登录'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (is_vip()) {
    echo json_encode(['error' => '您已是VIP会员'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 生成订单号
$out_trade_no = 'VIP' . date('YmdHis') . rand(1000, 9999);
$amount = 100.00;  // 100元/月

$result = wx_create_order($out_trade_no, $amount, '家庭法律助手VIP会员-30天');

if ($result['http_code'] === 200 && isset($result['body']['code_url'])) {
    // 保存订单信息到session，回调时验证
    $_SESSION['wx_order'] = [
        'out_trade_no' => $out_trade_no,
        'username' => $_SESSION['user']['username'],
        'created' => time(),
    ];

    // 同时保存到pending文件，回调时查找用户名
    $pending_file = __DIR__ . '/pending_orders.json';
    $pending = file_exists($pending_file) ? json_decode(file_get_contents($pending_file), true) : [];
    $pending[$out_trade_no] = $_SESSION['user']['username'];
    file_put_contents($pending_file, json_encode($pending));

    echo json_encode([
        'success' => true,
        'code_url' => $result['body']['code_url'],
        'out_trade_no' => $out_trade_no,
    ], JSON_UNESCAPED_UNICODE);
} else {
    $err = $result['body']['message'] ?? '未知错误';
    // 降级：返回错误但允许手动激活
    echo json_encode([
        'error' => '微信支付创建失败: ' . $err,
        'fallback' => true,
    ], JSON_UNESCAPED_UNICODE);
}
