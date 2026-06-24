<?php
/**
 * Agnes Video API 配置
 * API Key 从 lawyer/api.php 中读取
 */

// 尝试读取 lawyer 中的 API Key
$keySourceFile = __DIR__ . '/../../lawyer/api.php';
$apiKey = '';
if (file_exists($keySourceFile)) {
    $content = file_get_contents($keySourceFile);
    if (preg_match("/\\\$AGNES_KEY\s*=\s*'([^']+)'/", $content, $m)) {
        $apiKey = $m[1];
    }
}

define('AGNES_API_KEY', $apiKey);
define('AGNES_API_BASE', 'https://apihub.agnes-ai.com');
define('AGNES_VIDEO_MODEL', 'agnes-video-v2.0');

// 如果没找到 key，尝试直接定义
if (empty(AGNES_API_KEY)) {
    // 在这里手动设置 API Key
    // define('AGNES_API_KEY', 'sk-xxxx');
}
