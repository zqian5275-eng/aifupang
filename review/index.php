<?php
session_start();
require_once __DIR__ . '/../studio/auth.php';

// 登录检查 - 未登录跳转登录页
if (!is_logged_in()) {
    header('Location: /studio/login.php?redirect=' . urlencode('/review/'));
    exit;
}

// 读取最新报告
$report_file = __DIR__ . '/report.html';
if (!file_exists($report_file)) {
    die('<html><body style="background:#050507;color:#e0e0e0;text-align:center;padding:100px;font-family:sans-serif"><h2>暂无报告</h2><p>请稍后再来</p></body></html>');
}
$html = file_get_contents($report_file);

// GEO: 从报告中提取 meta 和 JSON-LD
$report_title = 'A股每日复盘 · AI花哥';
if (preg_match('/<title>(.*?)<\/title>/', $html, $tm)) {
    $report_title = $tm[1];
}
$report_desc = '';
if (preg_match('/<meta name="description" content="(.*?)">/', $html, $dm)) {
    $report_desc = $dm[1];
}
$report_keywords = '';
if (preg_match('/<meta name="keywords" content="(.*?)">/', $html, $km)) {
    $report_keywords = $km[1];
}
$jsonld_block = '';
if (preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $jm)) {
    $jsonld_block = $jm[1];
}

// 提取报告核心内容
$style_start = strpos($html, '<style>');
$style_end = strpos($html, '</style>', $style_start) + 8;
$styles = substr($html, $style_start, $style_end - $style_start);

// 删掉登录门控相关样式
$styles = preg_replace('/\/\*\s*会员登录门控.*?\*\/[\s\S]*?\.blur-content.*?\}/', '', $styles);
$styles = preg_replace('/\/\*\s*风险揭示\s*\*\/[\s\S]*?(?=\/\*|<\/style>)/', '', $styles);

// 提取报告容器内容
preg_match('/<div class="container">(.*?)<\/div>\s*<!-- end mainContent -->/s', $html, $matches);
$report_body = $matches[1] ?? '<p>报告内容提取失败</p>';

$is_admin = is_admin();
$user_email = $_SESSION['user']['username'] ?? '';

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /studio/login.php');
    exit;
}
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($report_title) ?></title>
    <?php if ($report_desc): ?>
    <meta name="description" content="<?= htmlspecialchars($report_desc) ?>">
    <?php endif; ?>
    <?php if ($report_keywords): ?>
    <meta name="keywords" content="<?= htmlspecialchars($report_keywords) ?>">
    <?php endif; ?>
    <meta name="author" content="AI花哥">
    <link rel="canonical" href="https://aifupang.com/review/">
    <meta property="og:title" content="<?= htmlspecialchars($report_title) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($report_desc) ?>">
    <meta property="og:type" content="article">
    <meta property="og:url" content="https://aifupang.com/review/">
    <meta property="og:site_name" content="AI花哥">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($report_title) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($report_desc) ?>">
    <?php if ($jsonld_block): ?>
    <script type="application/ld+json">
    <?= $jsonld_block ?>
    </script>
    <?php endif; ?>
    <style>
        :root {
            --bg: #050507;
            --card: #0d0d12;
            --border: #2a2830;
            --green: #00d992;
            --gold: #fbbf24;
            --text: #e8e8ed;
            --text2: #a09eaa;
            --text3: #6b6876;
            --accent: #a78bfa;
            --rose: #fb7185;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: var(--bg);
            color: var(--text);
            font-family: system-ui, 'PingFang SC', 'Microsoft YaHei', sans-serif;
            min-height: 100vh;
        }
        .topbar {
            background: rgba(5,5,7,.92);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            padding: 0 24px;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .topbar-inner {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 52px;
        }
        .logo {
            font-size: 16px;
            font-weight: 700;
            color: var(--green);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .logo-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--green);
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%,100% { opacity: 1; transform: scale(1); }
            50% { opacity: .5; transform: scale(1.5); }
        }
        .topbar-right {
            display: flex;
            align-items: center;
            gap: 16px;
            font-size: 13px;
        }
        .user-info { color: var(--text2); }
        .user-info span { color: var(--green); }
        .btn-sm {
            padding: 5px 14px;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            border: 1px solid var(--border);
            background: rgba(255,255,255,.05);
            color: var(--text2);
            text-decoration: none;
            transition: all .2s;
        }
        .btn-sm:hover { background: rgba(255,255,255,.1); color: var(--text); }
        .btn-sm.logout { border-color: rgba(251,113,133,.2); color: var(--rose); }
        .btn-sm.logout:hover { background: rgba(251,113,133,.1); }
        .report-wrapper {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px 40px;
        }
        .risk-footer {
            max-width: 1400px;
            margin: 40px auto 40px;
            padding: 0 20px;
        }
        .risk-box {
            background: linear-gradient(135deg, rgba(251,113,133,.08) 0%, rgba(251,113,133,.03) 100%);
            border: 2px solid rgba(251,113,133,.25);
            border-radius: 12px;
            padding: 28px;
        }
        .risk-box h3 {
            color: var(--rose);
            font-size: 16px;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .risk-box p {
            font-size: 13px;
            color: var(--text2);
            line-height: 2;
            margin-bottom: 4px;
            padding-left: 12px;
            border-left: 2px solid rgba(251,113,133,.2);
        }
    </style>
    <?= $styles ?>
</head>
<body>
    <div class="topbar">
        <div class="topbar-inner">
            <a href="/" class="logo"><span class="logo-dot"></span>AI花哥</a>
            <div class="topbar-right">
                <span class="user-info">👤 <span><?= htmlspecialchars($user_email) ?></span></span>
                <a href="/studio/" class="btn-sm">🧰 创作工坊</a>
                <a href="/research/" class="btn-sm">📊 投研平台</a>
                <a href="?logout=1" class="btn-sm logout">退出</a>
            </div>
        </div>
    </div>

    <div class="report-wrapper">
        <div class="container">
            <?= $report_body ?>
        </div>
    </div>

    <div class="risk-footer">
        <div class="risk-box">
            <h3>⚠️ 风险揭示与免责声明</h3>
            <p>1. 本报告由AI自动生成，仅供学习参考，不构成任何投资建议。</p>
            <p>2. 股市有风险，投资需谨慎。过往业绩不预示未来收益。</p>
            <p>3. 报告中提及的个股、板块仅作为市场分析案例，不构成买入建议。</p>
            <p>4. 数据来源包括东方财富、Tushare等公开接口。</p>
            <p>5. 本报告不属于证券投资顾问服务。</p>
        </div>
    </div>

    <?php
    preg_match_all('/<script>(?![\s\S]*?handleLogin)(.*?)<\/script>/s', $html, $script_matches);
    foreach ($script_matches[1] as $script) {
        if (strpos($script, 'handleLogin') === false && strpos($script, 'memberGate') === false && strpos($script, 'blur-content') === false) {
            echo "<script>$script</script>\n";
        }
    }
    ?>
</body>
</html>
