<?php
require_once 'auth.php';
require_login();
$user = $_SESSION['user'];
$isAdmin = $user['role'] === 'admin';
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>AI创作工坊 · AI花哥</title>
<style>
:root{--bg:#050507;--card:#101010;--border:#3d3a39;--green:#00d992;--green-dim:rgba(0,217,146,.08);--green-glow:rgba(0,217,146,.3);--text:#f2f2f2;--text2:#b8b3b0;--text3:#8b949e;--purple:#a78bfa;--cyan:#22d3ee;--gold:#fbbf24;--rose:#fb7185}
*{margin:0;padding:0;box-sizing:border-box}
body{background:var(--bg);color:var(--text);font-family:system-ui,'PingFang SC','Microsoft YaHei',sans-serif;min-height:100vh}
body::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background-image:linear-gradient(rgba(30,41,59,.3)1px,transparent 1px),linear-gradient(90deg,rgba(30,41,59,.3)1px,transparent 1px);background-size:60px 60px}

header{position:sticky;top:0;z-index:100;background:rgba(5,5,7,.92);backdrop-filter:blur(12px);border-bottom:1px solid var(--border);padding:0 24px}
.header-inner{max-width:1200px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;height:56px}
.logo{font-size:20px;font-weight:700;color:var(--green);text-decoration:none;display:flex;align-items:center;gap:8px}
.logo-dot{width:8px;height:8px;border-radius:50%;background:var(--green);animation:pulse 2s infinite;box-shadow:0 0 8px var(--green-glow)}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(1.5)}}
.user-area{display:flex;align-items:center;gap:16px;font-size:14px}
.user-tag{padding:4px 12px;border-radius:12px;font-size:12px;font-weight:600}
.user-tag.admin{background:rgba(251,191,36,.12);color:var(--gold);border:1px solid rgba(251,191,36,.2)}
.user-tag.member{background:rgba(0,217,146,.08);color:var(--green);border:1px solid rgba(0,217,146,.15)}
.logout-link{color:var(--text3);text-decoration:none;font-size:13px}
.logout-link:hover{color:var(--rose)}

.container{position:relative;z-index:1;max-width:1100px;margin:0 auto;padding:40px 20px}

.hero{text-align:center;margin-bottom:48px}
.hero h1{font-size:36px;font-weight:400;letter-spacing:-.5px;margin-bottom:8px}
.hero h1 .hl{color:var(--purple)}
.hero .badge{display:inline-flex;align-items:center;gap:6px;padding:4px 14px;border-radius:20px;border:1px solid rgba(167,139,250,.25);background:rgba(167,139,250,.08);color:var(--purple);font-size:12px;margin-bottom:16px}

.welcome{background:var(--card);border:1px solid var(--border);border-radius:8px;padding:24px;margin-bottom:32px;display:flex;align-items:center;gap:16px}
.welcome-avatar{width:48px;height:48px;border-radius:50%;background:rgba(0,217,146,.12);border:2px solid var(--green);display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0}
.welcome-info h3{font-size:16px;margin-bottom:4px}
.welcome-info p{font-size:12px;color:var(--text3)}

.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px}

.card{display:flex;flex-direction:column;min-height:200px;background:var(--card);border:1px solid var(--border);border-radius:8px;padding:28px;transition:all .3s}
.card:hover{border-color:rgba(255,255,255,.1);box-shadow:0 0 30px rgba(0,0,0,.5)}
.card-icon{font-size:32px;margin-bottom:12px}
.card h3{font-size:18px;margin-bottom:6px;font-weight:600}
.card p{font-size:13px;color:var(--text3);line-height:1.6}
.card .tag{display:inline-block;margin-top:12px;padding:3px 10px;border-radius:12px;font-size:11px;font-family:'JetBrains Mono',monospace}

.tag-green{background:rgba(0,217,146,.12);color:var(--green)}
.tag-purple{background:rgba(167,139,250,.12);color:var(--purple)}
.tag-gold{background:rgba(251,191,36,.12);color:var(--gold)}
.tag-cyan{background:rgba(34,211,238,.12);color:var(--cyan)}

.admin-section{margin-top:48px;padding-top:32px;border-top:1px solid var(--border)}
.admin-section h2{font-size:20px;font-weight:400;margin-bottom:20px;color:var(--gold)}
.admin-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px}
.admin.card{display:flex;flex-direction:column;min-height:200px;background:var(--card);border:1px solid rgba(251,191,36,.15);border-radius:8px;padding:20px}
.admin-card h4{font-size:14px;margin-bottom:8px;color:var(--gold)}
.admin-card p{font-size:12px;color:var(--text3);line-height:1.5}

footer{position:relative;z-index:1;text-align:center;padding:40px 20px;border-top:1px solid var(--border);color:var(--text3);font-size:12px;margin-top:60px}
</style>
</head>
<body>

<header>
<div class="header-inner">
<a href="/" class="logo"><span class="logo-dot"></span>AI花哥</a>
<div class="user-area">
<span>👤 <?= htmlspecialchars($user['username']) ?></span>
<span class="user-tag <?= $isAdmin ? 'admin' : 'member' ?>"><?= $isAdmin ? '超级管理员' : '会员' ?></span>
<a href="logout.php" class="logout-link">退出</a>
</div>
</div>
</header>

<div class="container">

<div class="welcome">
<div class="welcome-avatar">🎬</div>
<div class="welcome-info">
<h3>欢迎回来，<?= htmlspecialchars($user['username']) ?></h3>
<p>上次登录：<?= htmlspecialchars($user['last_login'] ?? '首次登录') ?> · 角色：<?= $isAdmin ? '超级管理员' : '普通会员' ?></p>
</div>
</div>

<div class="hero">
<div class="badge"><span style="width:6px;height:6px;border-radius:50%;background:var(--purple);display:inline-block;box-shadow:0 0 6px rgba(167,139,250,.5)"></span> RunningHub + AI 云端算力</div>
<h1>AI<span class="hl">创作</span>工坊</h1>
</div>

<div class="grid">
<a href="/studio/image/" class="card" style="text-decoration:none;color:inherit;">
<div class="card-icon">🖼️</div>
<h3>AI 图片生成</h3>
<p>文生图 / 图生图，支持高信息密度复杂画面</p>
<span class="tag tag-green">AI绘画</span>
</a>
<a href="/studio/video/" class="card" style="text-decoration:none;color:inherit;">
<div class="card-icon">🎥</div>
<h3>AI 视频生成</h3>
<p>文生视频 / 图生视频 / 关键帧动画</p>
<span class="tag tag-purple">AI视频</span>
</a>
<a href="/studio/tts/" class="card" style="text-decoration:none;color:inherit;">
<div class="card-icon">🗣️</div>
<h3>TTS 语音合成</h3>
<p>20+ 中文音色，在线试听，免费不限量</p>
<span class="tag tag-cyan">语音</span>
</a>
<a href="/studio/pipeline/" class="card" style="text-decoration:none;color:inherit;">
<div class="card-icon">📦</div>
<h3>批量流水线</h3>
<p>脚本 → 配图 → 配音 → 合成，全自动视频生产流水线</p>
<span class="tag tag-gold">PIPELINE</span>
</a>
<a href="/studio/video-pipeline/" class="card" style="text-decoration:none;color:inherit;"><div class="card-icon">🎬</div><h3>视频生成流水线</h3><p>脚本 → 分镜 → 动态视频片段 → 合成。角色场景一致性锁定</p><span class="tag tag-purple">AI视频流</span></a>
<a href="/studio/pv/" class="card" style="text-decoration:none;color:inherit;">
<div class="card-icon">🚀</div>
<h3>启动AI工坊</h3>
<p>AI图片 · AI视频 · 语音合成 · 全自动流水线</p>
<span class="tag tag-purple">LAUNCH →</span>
</a>
</div>

<?php if ($isAdmin): ?>
<div class="admin-section">
<h2>🔧 管理面板</h2>
<div class="admin-grid">
<div class="admin-card">
<h4>会员管理</h4>
<p>查看/管理注册会员，修改权限</p>
</div>
<div class="admin-card">
<h4>系统状态</h4>
<p>RunningHub 工作流：20+ 已配置<br>AI工坊：服务器常驻运行</p>
</div>
<div class="admin-card">
<h4>邀请码</h4>
<p>当前邀请码：<code style="color:var(--green);background:rgba(0,217,146,.1);padding:2px 6px;border-radius:4px;font-family:'JetBrains Mono',monospace">AI2026</code></p>
</div>
</div>
</div>
<?php endif; ?>

</div>

<footer>© 2026 AI创作工坊 · 花哥不花</footer>
</body>
</html>
