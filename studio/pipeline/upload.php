<?php
ini_set("display_errors", 0);
error_reporting(0);
header("Content-Type: application/json");

$destDir = __DIR__ . "/frames/";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "error" => "仅支持POST"]);
    exit;
}

if (empty($_FILES["image"])) {
    echo json_encode(["success" => false, "error" => "未收到文件"]);
    exit;
}

$file = $_FILES["image"];
if ($file["error"] !== UPLOAD_ERR_OK) {
    echo json_encode(["success" => false, "error" => "上传错误: " . $file["error"]]);
    exit;
}

$ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
if (!in_array($ext, ["jpg", "jpeg", "png", "webp", "gif"])) {
    echo json_encode(["success" => false, "error" => "仅支持 jpg/png/webp/gif"]);
    exit;
}

if ($file["size"] > 10 * 1024 * 1024) {
    echo json_encode(["success" => false, "error" => "文件不能超过10MB"]);
    exit;
}

$name = $_POST["name"] ?? "";
$filename = uniqid("char_") . "." . $ext;
$dest = $destDir . $filename;

if (!move_uploaded_file($file["tmp_name"], $dest)) {
    echo json_encode(["success" => false, "error" => "保存失败，检查目录权限"]);
    exit;
}

$url = "/studio/pipeline/frames/" . $filename;
echo json_encode([
    "success" => true,
    "url" => $url,
    "name" => $name,
    "filename" => $filename
]);
