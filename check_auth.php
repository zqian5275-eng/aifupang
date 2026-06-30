<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache');

if (isset($_SESSION['user'])) {
    echo json_encode([
        'logged_in' => true,
        'username' => $_SESSION['user']['username'],
        'role' => $_SESSION['user']['role']
    ]);
} else {
    echo json_encode(['logged_in' => false]);
}
