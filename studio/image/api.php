<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => '仅支持 POST']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$prompt = trim($input['prompt'] ?? '');
$mode = $input['mode'] ?? 'text';
$size = $input['size'] ?? '1024x768';
$images = $input['images'] ?? [];
$outputFormat = $input['output_format'] ?? 'url';

if (!$prompt) {
    echo json_encode(['success' => false, 'error' => '请输入提示词']);
    exit;
}

$body = array(
    'model' => AGNES_IMAGE_MODEL,
    'prompt' => $prompt,
    'size' => $size,
    'extra_body' => array()
);

// 输出格式
if ($outputFormat === 'base64' && $mode === 'text') {
    $body['return_base64'] = true;
} elseif ($outputFormat === 'base64') {
    $body['extra_body']['response_format'] = 'b64_json';
} else {
    $body['extra_body']['response_format'] = 'url';
}

// 图生图
if (($mode === 'image' || $mode === 'img2img') && !empty($images[0])) {
    $body['extra_body']['image'] = [$images[0]];
}

$prefix = 'Authorization';
$type = 'Bearer';
$key = AGNES_API_KEY;
$authHdr = $prefix . ': ' . $type . ' ' . $key;

$ch = curl_init(AGNES_API_BASE . '/v1/images/generations');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($body),
    CURLOPT_HTTPHEADER => [$authHdr, 'Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 180,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => false
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo json_encode(['success' => false, 'error' => 'API请求失败: ' . $curlError]);
    exit;
}

$data = json_decode($response, true);

if ($httpCode >= 400) {
    $errMsg = 'API错误 (HTTP ' . $httpCode . ')';
    if (isset($data['error']['message'])) $errMsg .= ': ' . $data['error']['message'];
    if (isset($data['message'])) $errMsg .= ': ' . $data['message'];
    echo json_encode(['success' => false, 'error' => $errMsg, 'detail' => $data]);
    exit;
}

$imgData = $data['data'][0] ?? null;
if (!$imgData) {
    echo json_encode(['success' => false, 'error' => 'API返回空结果']);
    exit;
}

$result = ['success' => true];

if (!empty($imgData['url'])) {
    $result['url'] = $imgData['url'];
    $result['type'] = 'url';
} elseif (!empty($imgData['b64_json'])) {
    $result['base64'] = $imgData['b64_json'];
    $result['url'] = 'data:image/png;base64,' . $imgData['b64_json'];
    $result['type'] = 'base64';
} else {
    $result['raw'] = $data;
}

echo json_encode($result);
