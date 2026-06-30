<?php
require_once 'auth.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $user = find_user($username);
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user'] = $user;
        // Update last_login
        $data = load_users();
        foreach ($data['users'] as &$u) {
            if ($u['username'] === $username) { $u['last_login'] = date('Y-m-d H:i:s'); break; }
        }
        save_users($data);
        $redirect = $_GET['redirect'] ?? 'index.php';
        header('Location: ' . $redirect);
        exit;
    }
    $error = '邮箱或密码错误';
}
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>登录 · AI花哥</title>
<style>
:root{--bg:#050507;--card:#101010;--border:#3d3a39;--green:#00d992;--green-glow:rgba(0,217,146,.3);--text:#f2f2f2;--text2:#b8b3b0;--text3:#8b949e;--purple:#a78bfa}
*{margin:0;padding:0;box-sizing:border-box}
body{background:var(--bg);color:var(--text);font-family:system-ui,'PingFang SC','Microsoft YaHei',sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background-image:linear-gradient(rgba(30,41,59,.2) 1px,transparent 1px),linear-gradient(90deg,rgba(30,41,59,.2) 1px,transparent 1px);background-size:50px 50px}
.login-box{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:40px;width:380px;max-width:90vw;box-shadow:0 0 40px rgba(0,0,0,.5)}
h1{font-size:24px;font-weight:400;text-align:center;margin-bottom:4px}
h1 span{color:var(--green)}
.sub{text-align:center;font-size:13px;color:var(--text3);margin-bottom:32px}
.input-group{margin-bottom:20px}
.input-group label{display:block;font-size:12px;color:var(--text2);margin-bottom:6px;text-transform:uppercase;letter-spacing:1px}
.input-group input{width:100%;padding:12px 16px;background:var(--bg);border:1px solid var(--border);border-radius:6px;color:var(--text);font-size:14px;font-family:inherit;outline:none;transition:border-color .3s}
.input-group input:focus{border-color:var(--green)}
.btn{width:100%;padding:12px;background:var(--green);border:none;border-radius:6px;color:var(--bg);font-size:15px;font-weight:600;cursor:pointer;transition:all .3s;font-family:inherit}
.btn:hover{box-shadow:0 0 20px var(--green-glow)}
.error{background:rgba(251,113,133,.1);border:1px solid rgba(251,113,133,.3);color:#fb7185;padding:10px;border-radius:6px;font-size:13px;text-align:center;margin-bottom:20px}
.links{text-align:center;margin-top:20px;font-size:13px}
.links a{color:var(--green);text-decoration:none}
.links a:hover{text-decoration:underline}
.back{text-align:center;margin-top:16px}
.back a{color:var(--text3);text-decoration:none;font-size:13px}
.back a:hover{color:var(--text)}
</style>
</head>
<body>
<div class="login-box">
<h1>AI<span>花哥</span></h1>
<p class="sub">登录</p>
<?php if($error): ?><div class="error"><?= $error ?></div><?php endif; ?>
<form method="post">
<div class="input-group"><label>邮箱</label><input name="username" required autofocus></div>
<div class="input-group"><label>密码</label><input type="password" name="password" required></div>
<button class="btn" type="submit">登 录</button>
</form>
<div class="links"><a href="register.php">免费注册新账号</a></div>
<div class="back"><a href="/">← 返回首页</a></div>
</div>
</body>
</html>
