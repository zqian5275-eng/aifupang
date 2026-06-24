<?php
/**
 * 文件上传处理 - 提取文本内容
 */
header('Content-Type: application/json; charset=utf-8');

$ALLOWED_TYPES = [
    'text/plain' => 'txt',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    'application/pdf' => 'pdf',
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp',
];

$MAX_SIZE = 10 * 1024 * 1024; // 10MB

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['file'])) {
    echo json_encode(['error' => '请选择文件'], JSON_UNESCAPED_UNICODE);
    exit;
}

$file = $_FILES['file'];
$mime = $file['type'] ?? '';

if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => '上传失败，错误码: ' . $file['error']], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($file['size'] > $MAX_SIZE) {
    echo json_encode(['error' => '文件过大，最大10MB'], JSON_UNESCAPED_UNICODE);
    exit;
}

$type = $ALLOWED_TYPES[$mime] ?? null;
if (!$type) {
    echo json_encode(['error' => '不支持的文件格式，支持：图片、PDF、Word、TXT'], JSON_UNESCAPED_UNICODE);
    exit;
}

$text = '';
$filename = $file['name'];

switch ($type) {
    case 'txt':
        $text = file_get_contents($file['tmp_name']);
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8,GBK,GB2312');
        break;

    case 'docx':
        $text = extractDocx($file['tmp_name']);
        break;

    case 'pdf':
        // 尝试用Python提取
        $text = extractPdf($file['tmp_name']);
        break;

    case 'jpg':
    case 'png':
    case 'gif':
    case 'webp':
        // 图片：保存并返回描述
        $upload_dir = __DIR__ . '/uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $save_path = $upload_dir . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        move_uploaded_file($file['tmp_name'], $save_path);
        $text = "[图片文件: {$filename}]";
        break;
}

if (empty($text)) {
    $text = "[文件 {$filename} 已上传，但未能提取文字内容]";
}

echo json_encode([
    'success' => true,
    'filename' => $filename,
    'type' => $type,
    'text' => trim($text),
], JSON_UNESCAPED_UNICODE);

// ============ 辅助函数 ============

function extractDocx($path) {
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) return '';
    
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    
    if (!$xml) return '';
    
    // 移除XML标签，提取纯文本
    $xml = strip_tags($xml);
    $xml = preg_replace('/\s+/', ' ', $xml);
    return trim($xml);
}

function extractPdf($path) {
    // 尝试用Python PyPDF2
    $script = "
import sys
try:
    from PyPDF2 import PdfReader
    reader = PdfReader('$path')
    text = ''
    for page in reader.pages:
        t = page.extract_text()
        if t: text += t + '\n'
    print(text.strip())
except Exception as e:
    print('')
";
    $output = shell_exec('python3 -c ' . escapeshellarg($script) . ' 2>/dev/null');
    return trim($output ?? '');
}
