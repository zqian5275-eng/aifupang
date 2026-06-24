<?php
/**
 * 微信支付APIv3 Native支付
 * 商户号: 1111742363
 */

define('WX_MCHID', '1111742363');
define('WX_API_KEY', '2aF9xKp7Qw3Rz5TgVb8Nc2Mh4Lk6Jd1S');
define('WX_APPID', 'wx4f84f36271262795');  // Native支付不需要appid，留空
define('WX_KEY_PATH', __DIR__ . '/apiclient_key.pem');
define('WX_CERT_PATH', __DIR__ . '/apiclient_cert.pem');
define('WX_NOTIFY_URL', 'https://aifupang.com/lawyer/wechat_notify.php');

// 从证书提取序列号
function wx_get_serial_no() {
    $cert = file_get_contents(WX_CERT_PATH);
    $info = openssl_x509_parse($cert);
    return $info['serialNumberHex'] ?? '';
}

// 签名
function wx_sign($method, $url, $timestamp, $nonce, $body = '') {
    $message = $method . "\n" . $url . "\n" . $timestamp . "\n" . $nonce . "\n" . ($body ?: '') . "\n";
    $key = file_get_contents(WX_KEY_PATH);
    openssl_sign($message, $signature, $key, OPENSSL_ALGO_SHA256);
    return base64_encode($signature);
}

// 调用API
function wx_request($method, $path, $body = null) {
    $url = 'https://api.mch.weixin.qq.com' . $path;
    $timestamp = (string)time();
    $nonce = bin2hex(random_bytes(16));
    $body_str = $body ? json_encode($body, JSON_UNESCAPED_UNICODE) : '';
    $signature = wx_sign($method, $path, $timestamp, $nonce, $body_str);
    $serial = wx_get_serial_no();

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: WECHATPAY2-SHA256-RSA2048 mchid="' . WX_MCHID . '",nonce_str="' . $nonce . '",timestamp="' . $timestamp . '",serial_no="' . $serial . '",signature="' . $signature . '"',
        'User-Agent: aifupang.com',
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    if ($body_str) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body_str);
    }

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['http_code' => $http_code, 'body' => json_decode($response, true) ?: $response];
}

// 创建Native支付订单
function wx_create_order($out_trade_no, $amount_yuan, $description) {
    $body = [
        'mchid' => WX_MCHID,
        'out_trade_no' => $out_trade_no,
        'appid' => WX_APPID,
        'description' => $description,
        'notify_url' => WX_NOTIFY_URL,
        'amount' => [
            'total' => intval($amount_yuan * 100),  // 单位：分
            'currency' => 'CNY',
        ],
    ];

    return wx_request('POST', '/v3/pay/transactions/native', $body);
}

// 验证回调签名
function wx_verify_notify($body, $timestamp, $nonce, $signature, $serial) {
    $message = $timestamp . "\n" . $nonce . "\n" . $body . "\n";
    // 用微信平台公钥验证（简化：先用商户证书公钥）
    $cert = file_get_contents(WX_CERT_PATH);
    $pubkey = openssl_pkey_get_public($cert);
    if (!$pubkey) return false;
    $result = openssl_verify($message, base64_decode($signature), $pubkey, OPENSSL_ALGO_SHA256);
    return $result === 1;
}

// 解密回调数据
function wx_decrypt_notify($associated_data, $nonce, $ciphertext) {
    $key = WX_API_KEY;
    $decoded = base64_decode($ciphertext);
    $tag = substr($decoded, -16);
    $cipher = substr($decoded, 0, -16);
    return openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, $associated_data);
}
