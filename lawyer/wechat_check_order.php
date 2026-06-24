<?php
/**
 * 检查订单支付状态（前端轮询用）
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/wechat_pay_config.php';

$out_trade_no = $_GET['out_trade_no'] ?? '';

if (!$out_trade_no) {
    echo json_encode(['paid' => false, 'error' => 'missing param']);
    exit;
}

// 检查pending文件（支付回调会清理）
$pending_file = __DIR__ . '/pending_orders.json';
if (!file_exists($pending_file)) {
    echo json_encode(['paid' => false, 'status' => 'no_file']);
    exit;
}

$pending = json_decode(file_get_contents($pending_file), true);

if (!isset($pending[$out_trade_no])) {
    // 订单已从pending移除 = 已支付
    echo json_encode(['paid' => true, 'status' => 'cleared']);
    exit;
}

// 还在pending = 未支付或等待回调
// 也可主动查询微信支付订单状态
echo json_encode(['paid' => false, 'status' => 'pending']);
