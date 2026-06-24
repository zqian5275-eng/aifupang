<?php
session_start();
require_once __DIR__ . '/auth.php';

$user = isset($_SESSION['user']) ? $_SESSION['user'] : null;
if ($user) {
    http_response_code(200);
    echo 'ok';
} else {
    http_response_code(401);
    echo 'unauthorized';
}
