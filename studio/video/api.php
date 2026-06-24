<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$prefix = 'Authorization';
$type = 'Bearer';
$key = AGNES_API_KEY;
$authHdr = $prefix . ': ' . $type . ' ' . $key;

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create') {
    $input = json_decode(file_get_contents('php://input'), true);
    $prompt = trim($input['prompt'] ?? '');
    $mode = $input['mode'] ?? 'text';
    $images = $input['images'] ?? [];
    $numFrames = intval($input['num_frames'] ?? 121);
    $frameRate = intval($input['frame_rate'] ?? 24);
    $negativePrompt = trim($input['negative_prompt'] ?? '');
    $seed = $input['seed'] ?? null;
    $aspectRatio = trim($input['aspect_ratio'] ?? '16:9');
    
    if (!$prompt) {
        echo json_encode(['success' => false, 'error' => '请输入提示词']);
        exit;
    }
    
    if ($numFrames < 1) $numFrames = 1;
    if ($numFrames > 409) $numFrames = 409;
    $validFrames = [1,9,17,25,33,41,49,57,65,73,81,89,97,105,113,121,129,137,145,153,161,169,177,185,193,201,209,217,225,233,241,249,257,265,273,281,289,297,305,313,321,329,337,345,353,361,369,377,385,393,401,409];
    if (!in_array($numFrames, $validFrames)) {
        $closest = $validFrames[0];
        foreach ($validFrames as $v) { if (abs($v - $numFrames) < abs($closest - $numFrames)) $closest = $v; }
        $numFrames = $closest;
    }
    
    $body = array(
        'model' => AGNES_VIDEO_MODEL,
        'prompt' => $prompt,
        'num_frames' => $numFrames,
        'frame_rate' => $frameRate,
        'aspect_ratio' => $aspectRatio,
        'extra_body' => array()
    );
    
    // Fix image URLs: convert relative paths to absolute URLs
    $fixedImages = [];
    foreach ($images as $img) {
        if ($img && strpos($img, 'http') !== 0 && strpos($img, 'data:') !== 0) {
            $img = 'https://aifupang.com' . (($img[0] === '/') ? $img : ('/' . $img));
        }
        $fixedImages[] = $img;
    }
    $images = $fixedImages;
    
    if ($mode === 'image' && !empty($images[0])) {
        $body['extra_body']['image'] = [$images[0]];
    } elseif (($mode === 'multi' || $mode === 'keyframes') && count($images) >= 2) {
        $body['extra_body']['image'] = $images;
        if ($mode === 'keyframes') $body['extra_body']['mode'] = 'keyframes';
    }
    
    if ($negativePrompt) $body['negative_prompt'] = $negativePrompt;
    if ($seed !== null && $seed !== '') $body['extra_body']['seed'] = intval($seed);
    
    $ch = curl_init(AGNES_API_BASE . '/v1/videos');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($body),
        CURLOPT_HTTPHEADER => [$authHdr, 'Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    $body_json = json_encode($body);
    $img_data = json_encode($body["extra_body"]["image"] ?? []);
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
        $errMsg = 'API返回错误 (HTTP ' . $httpCode . ')';
        if (isset($data['error']['message'])) $errMsg .= ': ' . $data['error']['message'];
        if (isset($data['message'])) $errMsg .= ': ' . $data['message'];
        echo json_encode(['success' => false, 'error' => $errMsg]);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'video_id' => $data['video_id'] ?? $data['task_id'] ?? $data['id'] ?? '',
        'task_id' => $data['task_id'] ?? $data['id'] ?? '',
        'status' => $data['status'] ?? 'queued'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'query') {
    $videoId = trim($_GET['video_id'] ?? '');
    if (!$videoId) {
        echo json_encode(['success' => false, 'error' => '缺少video_id']);
        exit;
    }
    
    $url = AGNES_API_BASE . '/agnesapi?video_id=' . urlencode($videoId);
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [$authHdr],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        echo json_encode(['success' => false, 'error' => '查询失败: ' . $curlError]);
        exit;
    }
    
    $data = json_decode($response, true);
    $result = [
        'success' => true,
        'status' => $data['status'] ?? 'unknown',
        'progress' => $data['progress'] ?? 0,
        'video_url' => $data['remixed_from_video_id'] ?? null
    ];
    
    if (($data['status'] ?? '') === 'failed') {
        $msg = $data['error']['message'] ?? ($data['error'] ?? '生成失败');
        $result['error'] = is_string($msg) ? $msg : json_encode($msg);
    }
    
    echo json_encode($result);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'error' => '未知操作']);
