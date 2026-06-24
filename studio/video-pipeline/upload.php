<?php
ini_set('display_errors', 0);
error_reporting(0);
/**
 * Video Pipeline - Image Upload Endpoint
 * Receives multipart image uploads, saves to frames/ directory.
 */
header('Content-Type: application/json; charset=utf-8');
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

if (empty($_FILES['image'])) {
    echo json_encode(['success' => false, 'error' => '未上传图片']);
    exit;
}

$file = $_FILES['image'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    $errors = [
        UPLOAD_ERR_INI_SIZE   => '文件超过服务器限制',
        UPLOAD_ERR_FORM_SIZE  => '文件超过表单限制',
        UPLOAD_ERR_PARTIAL    => '文件上传不完整',
        UPLOAD_ERR_NO_FILE    => '未选择文件',
    ];
    $msg = $errors[$file['error']] ?? '上传错误代码: ' . $file['error'];
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed = ['jpg', 'jpeg', 'png', 'webp'];
if (!in_array($ext, $allowed)) {
    echo json_encode(['success' => false, 'error' => '仅支持 JPG/PNG/WebP 格式']);
    exit;
}

// 10 MB max
if ($file['size'] > 10 * 1024 * 1024) {
    echo json_encode(['success' => false, 'error' => '图片不能超过 10MB']);
    exit;
}

$destDir = __DIR__ . '/frames';
if (!is_dir($destDir)) {
    mkdir($destDir, 0755, true);
}

$filename = uniqid('up_') . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
$destPath = $destDir . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    echo json_encode(['success' => false, 'error' => '保存文件失败']);
    exit;
}

$url = '/studio/video-pipeline/frames/' . $filename;

echo json_encode([
    'success' => true,
    'url'     => $url,
    'width'   => 0,   // 前端填充
    'height'  => 0,
]);
