<?php
session_start();
$error = $success = $sent = false;

// Generate code
if (isset($_GET['send']) && !empty($_POST['email'])) {
    $email = trim($_POST['email']);
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['reg_email'] = $email;
        $_SESSION['reg_code'] = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $_SESSION['reg_time'] = time();
        // Try to send email
        $subject = "=?UTF-8?B?".base64_encode('AI花哥 - 注册验证码')."?=";
        $body = "您的验证码是：{$_SESSION['reg_code']}，5分钟内有效。";
        $headers = "From: noreply@aifupang.com\r\nContent-Type: text/plain; charset=UTF-8";
        @mail($email, $subject, $body, $headers);
        $sent = true;
    }
}

// Register
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $email = $_SESSION['reg_email'] ?? '';
    $password = $_POST['password'] ?? '';
    $code = trim($_POST['code'] ?? '');
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = '邮箱无效';
    elseif (strlen($password) < 6) $error = '密码至少6个字符';
    elseif ($code !== ($_SESSION['reg_code'] ?? '')) $error = '验证码错误';
    elseif (time() - ($_SESSION['reg_time'] ?? 0) > 300) $error = '验证码已过期（5分钟）';
    else {
        require_once __DIR__ . '/auth.php';
        if (find_user($email)) {
            $error = '该邮箱已注册';
        } else {
            create_user($email, $password);
            unset($_SESSION['reg_email'], $_SESSION['reg_code'], $_SESSION['reg_time']);
            $success = true;
        }
    }
}
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>注册 · AI花哥</title>
<style>
:root{--bg:#050507;--card:#101010;--border:#3d3a39;--green:#00d992;--green-glow:rgba(0,217,146,.3);--gold:#fbbf24;--text:#f2f2f2;--text2:#b8b3b0;--text3:#8b949e}
*{margin:0;padding:0;box-sizing:border-box}
body{background:var(--bg);color:var(--text);font-family:system-ui,'PingFang SC','Microsoft YaHei',sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background-image:linear-gradient(rgba(30,41,59,.2) 1px,transparent 1px),linear-gradient(90deg,rgba(30,41,59,.2) 1px,transparent 1px);background-size:50px 50px}
.box{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:40px;width:400px;max-width:90vw;box-shadow:0 0 40px rgba(0,0,0,.5)}
h1{font-size:24px;font-weight:400;text-align:center;margin-bottom:4px}
h1 span{color:var(--green)}
.sub{text-align:center;font-size:13px;color:var(--text3);margin-bottom:32px}
.step{text-align:center;margin-bottom:20px}
.step-num{display:inline-block;width:28px;height:28px;border-radius:50%;background:rgba(0,217,146,.12);color:var(--green);font-size:14px;font-weight:700;line-height:28px;margin:0 4px}
.step-num.active{background:var(--green);color:var(--bg)}
.input-group{margin-bottom:20px}
.input-group label{display:block;font-size:12px;color:var(--text2);margin-bottom:6px;text-transform:uppercase;letter-spacing:1px}
.input-group input{width:100%;padding:12px 16px;background:var(--bg);border:1px solid var(--border);border-radius:6px;color:var(--text);font-size:14px;font-family:inherit;outline:none;transition:border-color .3s}
.input-group input:focus{border-color:var(--green)}
.row{display:flex;gap:10px}
.row input{flex:1}
.code-display{padding:10px 16px;background:rgba(0,217,146,.06);border:1px solid rgba(0,217,146,.15);border-radius:6px;color:var(--green);font-size:13px;text-align:center;word-break:break-all}
.code-display strong{font-size:24px;letter-spacing:3px}
.btn{width:100%;padding:12px;background:var(--green);border:none;border-radius:6px;color:var(--bg);font-size:15px;font-weight:600;cursor:pointer;transition:all .3s;font-family:inherit}
.btn:hover{box-shadow:0 0 20px var(--green-glow)}
.btn-outline{background:transparent;border:1px solid var(--green);color:var(--green)}
.error{background:rgba(251,113,133,.1);border:1px solid rgba(251,113,133,.3);color:#fb7185;padding:10px;border-radius:6px;font-size:13px;text-align:center;margin-bottom:20px}
.success{background:rgba(0,217,146,.1);border:1px solid rgba(0,217,146,.3);color:var(--green);padding:10px;border-radius:6px;font-size:13px;text-align:center;margin-bottom:20px}
.info{background:rgba(251,191,36,.06);border:1px solid rgba(251,191,36,.15);color:var(--gold);padding:10px;border-radius:6px;font-size:12px;text-align:center;margin-bottom:20px;line-height:1.6}
.links{text-align:center;margin-top:20px;font-size:13px}
.links a{color:var(--green);text-decoration:none}
.links a:hover{text-decoration:underline}
</style>
</head>
<body>
<div class="box">
<h1>AI<span>花哥</span></h1>
<p class="sub">邮箱注册</p>

<?php if($error): ?><div class="error"><?= $error ?></div><?php endif; ?>

<?php if($success): ?>
<div class="success">✅ 注册成功！</div>
<div class="links"><a href="login.php">→ 去登录</a></div>

<?php elseif($sent): ?>
<!-- Step 2: Enter code -->
<div class="step">
<span class="step-num">1</span> ── <span class="step-num active">2</span>
</div>
<div class="info">📧 验证码已发送到 <strong><?= htmlspecialchars($_SESSION['reg_email']) ?></strong><br>如未收到，请检查垃圾邮件</div>
<div class="code-display" style="margin-bottom:16px">备用验证码：<strong><?= $_SESSION['reg_code'] ?></strong></div>
<form method="post">
<input type="hidden" name="register" value="1">
<div class="input-group"><label>验证码</label><input name="code" placeholder="输入6位验证码" required maxlength="6" autofocus></div>
<div class="input-group"><label>设置密码（6位以上）</label><input type="password" name="password" required placeholder="设置登录密码"></div>
<button class="btn" type="submit">完成注册</button>
</form>
<div class="links"><a href="register.php">← 更换邮箱</a></div>

<?php else: ?>
<!-- Step 1: Enter email -->
<div class="step">
<span class="step-num active">1</span> ── <span class="step-num">2</span>
</div>
<form method="post" action="?send=1">
<div class="input-group"><label>邮箱地址</label><input name="email" type="email" placeholder="your@email.com" required autofocus></div>
<button class="btn" type="submit">发送验证码</button>
</form>
<div class="links">已有账号？<a href="login.php">去登录</a></div>
<?php endif; ?>

</div>
</body>
</html>
