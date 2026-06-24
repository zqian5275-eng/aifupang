<?php
session_start();
$is_admin = isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin';
$BLOG_DIR = __DIR__;
$META_FILE = __DIR__ . '/articles.json';
$articles = file_exists($META_FILE) ? (json_decode(file_get_contents($META_FILE), true) ?: []) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_admin) {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'delete' && isset($_POST['id'])) {
            $id = $_POST['id'];
            if (isset($articles[$id])) {
                $file = $articles[$id]['file'];
                if (file_exists($BLOG_DIR . '/' . $file)) unlink($BLOG_DIR . '/' . $file);
                unset($articles[$id]);
                file_put_contents($META_FILE, json_encode($articles, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            }
            header('Location: index.php?deleted=1'); exit;
        }
        if ($_POST['action'] === 'publish') {
            $title = trim($_POST['title'] ?? '');
            $content = $_POST['content'] ?? '';
            $tag = trim($_POST['tag'] ?? '');
            if ($title && $content) {
                $id = time();
                $slug = preg_replace('/[^a-z0-9\\x{4e00}-\\x{9fa5}]+/u', '-', mb_strtolower($title));
                $slug = trim($slug, '-') ?: $id;
                $fn = $slug . '.html';
                $html = '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>' . htmlspecialchars($title) . ' · AI花哥</title>';
                $html .= '<style>:root{--bg:#050507;--card:#101010;--border:#3d3a39;--green:#00d992;--purple:#a78bfa;--text:#f2f2f2;--text2:#b8b3b0;--text3:#8b949e}*{margin:0;padding:0;box-sizing:border-box}body{background:var(--bg);color:var(--text);font-family:system-ui,"PingFang SC","Microsoft YaHei",sans-serif;min-height:100vh}body::before{content:"";position:fixed;inset:0;z-index:0;pointer-events:none;background-image:linear-gradient(rgba(30,41,59,.3)1px,transparent 1px),linear-gradient(90deg,rgba(30,41,59,.3)1px,transparent 1px);background-size:60px 60px}header{position:sticky;top:0;z-index:100;background:rgba(5,5,7,.92);backdrop-filter:blur(12px);border-bottom:1px solid var(--border);padding:0 24px}.h-inner{max-width:800px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;height:56px}.logo{font-size:18px;font-weight:700;color:var(--green);text-decoration:none}.back{color:var(--text2);text-decoration:none;font-size:13px}.back:hover{color:var(--green)}article{position:relative;z-index:1;max-width:800px;margin:0 auto;padding:40px 20px;line-height:1.9;font-size:15px}article h1{font-size:28px;font-weight:400;margin-bottom:8px}article .meta{font-size:12px;color:var(--text3);margin-bottom:28px;padding-bottom:16px;border-bottom:1px solid var(--border)}article p{margin-bottom:16px;color:var(--text2)}footer{position:relative;z-index:1;text-align:center;padding:30px;border-top:1px solid var(--border);color:var(--text3);font-size:11px}@media(max-width:768px){
body{padding:0;font-size:14px}
header{padding:0 12px}
.h-inner{height:48px}
.logo{font-size:16px}
.container{padding:20px 12px}
h1{font-size:20px}
.grid{grid-template-columns:1fr!important;gap:12px}
.card{min-height:auto;padding:16px}
.stats-row{grid-template-columns:1fr 1fr!important}
.chart-row{grid-template-columns:1fr!important}
.stat-card .value{font-size:24px}
.stat-card{padding:14px}
table{font-size:11px}
th,td{padding:6px 8px}
.btn{padding:8px 20px;font-size:13px}
nav a{font-size:12px;padding:4px 8px}
.btns{flex-wrap:wrap;gap:8px}
.controls{flex-direction:column;align-items:flex-start}
.preview-wrap canvas{max-width:100%!important}
canvas{max-width:100%}
.area{padding:30px 16px}
.area .icon{font-size:36px}
.area .txt{font-size:13px}
.login-box{margin:40px 12px;padding:32px 20px}
footer{padding:24px 12px;margin-top:32px}
}</style></head><body>';
                $html .= '<header><div class="h-inner"><a href="/" class="logo">AI花哥</a><a href="/blog/" class="back">← 返回博客</a></div></header>';
                $html .= '<article><h1>' . htmlspecialchars($title) . '</h1><div class="meta">' . date('Y.m.d') . ' · ' . htmlspecialchars($tag) . '</div>';
                $html .= '<div class="content">' . nl2br(htmlspecialchars($content)) . '</div></article>';
                $html .= '<footer>© 2026 AI花哥</footer></body></html>';
                file_put_contents($BLOG_DIR . '/' . $fn, $html);
                $articles[$id] = ['title' => $title, 'file' => $fn, 'tag' => $tag, 'date' => date('Y-m-d'), 'summary' => mb_substr($content, 0, 100)];
                file_put_contents($META_FILE, json_encode($articles, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                header('Location: index.php?published=1'); exit;
            }
        }
    }
}

uasort($articles, function($a,$b){return strcmp($b['date'],$a['date']);});
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>文章博客 · AI花哥</title>
<style>
:root{--bg:#050507;--card:#101010;--border:#3d3a39;--green:#00d992;--purple:#a78bfa;--gold:#fbbf24;--rose:#fb7185;--text:#f2f2f2;--text2:#b8b3b0;--text3:#8b949e;--radius:8px}
*{margin:0;padding:0;box-sizing:border-box}
body{background:var(--bg);color:var(--text);font-family:system-ui,'PingFang SC','Microsoft YaHei',sans-serif;min-height:100vh}
body::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background-image:linear-gradient(rgba(30,41,59,.3)1px,transparent 1px),linear-gradient(90deg,rgba(30,41,59,.3)1px,transparent 1px);background-size:60px 60px}
header{position:sticky;top:0;z-index:100;background:rgba(5,5,7,.92);backdrop-filter:blur(12px);border-bottom:1px solid var(--border);padding:0 24px}
.h-inner{max-width:1100px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;height:56px}
.logo{font-size:20px;font-weight:700;color:var(--green);text-decoration:none;display:flex;align-items:center;gap:8px}
.dot{width:8px;height:8px;border-radius:50%;background:var(--green);animation:pulse 2s infinite;box-shadow:0 0 8px rgba(0,217,146,.3)}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(1.5)}}
.nav{display:flex;gap:20px;align-items:center}
.nav a{color:var(--text2);text-decoration:none;font-size:14px;font-weight:500}
.nav a:hover{color:var(--green)}
.admin-badge{padding:4px 12px;border-radius:12px;font-size:12px;background:rgba(251,191,36,.12);border:1px solid rgba(251,191,36,.2);color:var(--gold);font-weight:600}
.container{position:relative;z-index:1;max-width:1100px;margin:0 auto;padding:40px 20px}
.hero{text-align:center;margin-bottom:40px}
.hero h1{font-size:clamp(28px,5vw,40px);font-weight:400;letter-spacing:-.5px;margin-bottom:8px}
.hero h1 span{color:var(--purple)}
.hero .sub{font-size:14px;color:var(--text3)}
.hero .badge{display:inline-flex;align-items:center;gap:6px;padding:4px 14px;border-radius:20px;border:1px solid rgba(167,139,250,.25);background:rgba(167,139,250,.08);color:var(--purple);font-size:12px;margin-bottom:12px}

.modules{display:grid;grid-template-columns:repeat(2,1fr);gap:20px}
@media(max-width:640px){.modules{grid-template-columns:1fr}}

.module-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:28px;text-decoration:none;color:var(--text);transition:all .3s;position:relative;overflow:hidden;display:flex;flex-direction:column;min-height:200px}
.module-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:var(--border);transition:background .3s}
.module-card:hover{border-color:rgba(255,255,255,.1);transform:translateY(-2px);box-shadow:0 0 30px rgba(0,0,0,.5)}
.module-card:hover::before{background:var(--purple)}
.module-icon{font-size:32px;margin-bottom:12px}
.module-card h3{font-size:20px;font-weight:600;margin-bottom:6px}
.module-card .desc{font-size:13px;color:var(--text3);line-height:1.5;flex:1}
.module-card .meta{font-size:11px;color:var(--text3);margin-bottom:8px;font-family:'JetBrains Mono',monospace}
.module-card .tag{display:inline-block;margin-top:12px;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;font-family:'JetBrains Mono',monospace;background:rgba(167,139,250,.12);color:var(--purple)}

.admin-panel{background:var(--card);border:1px solid rgba(251,191,36,.15);border-radius:var(--radius);padding:24px;margin-bottom:32px}
.admin-panel h3{font-size:16px;color:var(--gold);margin-bottom:14px;display:flex;align-items:center;gap:6px}
.admin-panel input,.admin-panel textarea{width:100%;background:var(--bg);border:1px solid var(--border);border-radius:6px;color:var(--text);padding:10px 12px;font-size:14px;font-family:inherit;margin-bottom:10px;outline:none}
.admin-panel input:focus,.admin-panel textarea:focus{border-color:var(--gold)}
.admin-panel .btn{padding:10px 20px;background:var(--purple);border:none;border-radius:6px;color:var(--bg);font-weight:600;cursor:pointer;font-size:14px;font-family:inherit}
.admin-panel .btn:hover{box-shadow:0 0 16px rgba(167,139,250,.3)}
.del-btn{background:none;border:1px solid rgba(251,113,133,.3);color:var(--rose);padding:6px 12px;border-radius:6px;cursor:pointer;font-size:12px;font-family:inherit}
.del-btn:hover{background:rgba(251,113,133,.1)}
.msg{padding:10px 14px;border-radius:6px;font-size:13px;margin-bottom:20px}
.msg.ok{background:rgba(0,217,146,.1);border:1px solid rgba(0,217,146,.2);color:var(--green)}
.login-hint{background:var(--card);border:1px dashed var(--border);border-radius:var(--radius);padding:20px;text-align:center;color:var(--text3);margin-bottom:32px;font-size:14px}
.login-hint a{color:var(--green)}
footer{position:relative;z-index:1;text-align:center;padding:40px 20px;border-top:1px solid var(--border);color:var(--text3);font-size:12px;margin-top:40px}
</style>
</head>
<body>
<header><div class="h-inner">
<a href="/" class="logo"><span class="dot"></span>AI花哥</a>
<div class="nav">
<?php if($is_admin): ?><span class="admin-badge">⚡ 管理员</span><?php endif; ?>
<a href="/">← 返回</a>
</div>
</div></header>

<div class="container">
<div class="hero">
<div class="badge"><span style="width:6px;height:6px;border-radius:50%;background:var(--purple);display:inline-block;box-shadow:0 0 6px rgba(167,139,250,.5)"></span> 私募 · AI · 趋势</div>
<h1>📝 文章<span>博客</span></h1>
<p class="sub">10年私募副总视角 · AI×金融 · 行业洞察</p>
</div>

<?php if(isset($_GET['published'])): ?><div class="msg ok">✅ 文章已发布</div><?php endif; ?>
<?php if(isset($_GET['deleted'])): ?><div class="msg ok">🗑️ 已删除</div><?php endif; ?>

<?php if($is_admin): ?>
<div class="admin-panel">
<h3>✍️ 发布文章</h3>
<form method="post">
<input type="hidden" name="action" value="publish">
<input name="title" placeholder="文章标题" required>
<input name="tag" placeholder="标签 (如: AI×金融)" style="max-width:200px">
<textarea name="content" placeholder="文章内容…" rows="8" required></textarea>
<button class="btn" type="submit">发布文章</button>
</form>
</div>
<?php else: ?>
<div class="login-hint">🔒 <a href="/studio/login.php">登录</a> 后可以发布和管理文章</div>
<?php endif; ?>

<div class="modules">
<?php foreach($articles as $id => $a): ?>
<a href="/blog/<?= htmlspecialchars($a['file']) ?>" class="module-card">
<div class="module-icon">📄</div>
<div class="meta"><?= htmlspecialchars($a['date']) ?></div>
<h3><?= htmlspecialchars($a['title']) ?></h3>
<div class="desc"><?= htmlspecialchars(mb_substr($a['summary'] ?? '', 0, 80)) ?>…</div>
<span class="tag"><?= htmlspecialchars($a['tag']) ?></span>
<?php if($is_admin): ?>
<form method="post" onsubmit="event.stopPropagation();return confirm('删除?')" style="position:absolute;top:12px;right:12px;z-index:2" onclick="event.stopPropagation()">
<input type="hidden" name="action" value="delete">
<input type="hidden" name="id" value="<?= $id ?>">
<button class="del-btn" type="submit">删除</button>
</form>
<?php endif; ?>
</a>
<?php endforeach; ?>
</div>
</div>
<footer>© 2026 AI花哥</footer>
</body>
</html>
