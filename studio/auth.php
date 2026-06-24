<?php
session_start();
define('DATA_DIR', __DIR__ . '/data');
define('USERS_FILE', DATA_DIR . '/users.json');
define('SITE_NAME', 'AI创作工坊');

function load_users() {
    if (!file_exists(USERS_FILE)) {
        if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);
        file_put_contents(USERS_FILE, json_encode(['users' => []]));
    }
    return json_decode(file_get_contents(USERS_FILE), true);
}

function save_users($data) {
    file_put_contents(USERS_FILE, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function find_user($username) {
    $data = load_users();
    foreach ($data['users'] as $u) {
        if ($u['username'] === $username) return $u;
    }
    return null;
}

function create_user($username, $password, $role = 'member') {
    $data = load_users();
    if (find_user($username)) return false;
    $data['users'][] = [
        'username' => $username,
        'password' => password_hash($password, PASSWORD_BCRYPT),
        'role' => $role,
        'created' => date('Y-m-d H:i:s'),
        'last_login' => null,
    ];
    save_users($data);
    return true;
}

function is_logged_in() {
    return isset($_SESSION['user']);
}

function is_admin() {
    return is_logged_in() && $_SESSION['user']['role'] === 'admin';
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

function require_admin() {
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        die('<h1>403</h1><p>需要管理员权限</p>');
    }
}
