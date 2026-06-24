<?php
require_once __DIR__ . '/auth_lawyer.php';
require_login();
$info = get_vip_info();
if (is_vip()) {
    header('Location: /lawyer/');
    exit;
}
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>升级VIP · 家庭法律助手</title>
<style>
:root{--bg:#0a0a0f;--surface:#14141a;--border:#2a2a35;--gold:#f0c060;--cyan:#4dc9f6;--text:#e8e8ec;--text2:#9898a8;--vip:#f59e0b;--green:#4ade80}
*{margin:0;padding:0;box-sizing:border-box}
body{background:var(--bg);color:var(--text);font-family:-apple-system,'PingFang SC','Microsoft YaHei',sans-serif;min-height:100vh}
header{width:100%;padding:14px 20px;background:var(--surface);border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px}
header a{color:var(--text2);text-decoration:none;font-size:14px}
.container{max-width:480px;margin:0 auto;padding:32px 20px;text-align:center}
h1{font-size:24px;margin-bottom:8px}
h1 span{color:var(--vip)}
.sub{font-size:14px;color:var(--text2);margin-bottom:28px}
.card{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:28px 22px;margin-bottom:20px;text-align:left}
.card.vip-card{border-color:var(--vip)}
.card h3{font-size:18px;color:var(--vip);margin-bottom:16px;text-align:center}
.features{list-style:none;margin:20px 0}
.features li{padding:8px 0;color:var(--text2);font-size:14px;border-bottom:1px solid var(--border)}
.features li::before{content:'✅ ';margin-right:8px}
.features li:last-child{border:none}
.qr-wrap{text-align:center;margin:20px 0}
.qr-wrap img{width:220px;height:220px;border-radius:12px;border:1px solid var(--border)}
.qr-wrap .amount{font-size:28px;color:var(--vip);font-weight:700;margin:8px 0}
.qr-wrap .loading{color:var(--text2);font-size:14px;padding:40px 0}
.qr-wrap .status-ok{color:var(--green);font-size:14px;margin-top:12px;display:none}
.paid-hint{text-align:center;margin-top:16px;font-size:13px;color:var(--text2)}
.manual{text-align:center;margin-top:24px;padding-top:20px;border-top:1px solid var(--border)}
.manual p{font-size:13px;color:var(--text2);margin-bottom:14px}
.manual input{width:120px;background:var(--bg);border:1px solid var(--border);border-radius:8px;color:var(--text);padding:10px;font-size:16px;text-align:center;outline:none;margin:0 8px}
.manual input:focus{border-color:var(--vip)}
.manual button{padding:10px 24px;background:var(--vip);border:none;border-radius:8px;color:#000;font-size:14px;font-weight:600;cursor:pointer}
.manual button:disabled{opacity:.4}
.status-msg{text-align:center;margin-top:12px;font-size:13px;padding:10px;border-radius:8px;display:none}
.status-msg.ok{display:block;background:rgba(74,222,128,.1);color:var(--green);border:1px solid rgba(74,222,128,.3)}
.status-msg.err{display:block;background:rgba(251,113,133,.1);color:#f87171;border:1px solid rgba(251,113,133,.3)}
.note{background:rgba(251,113,133,.1);border:1px solid rgba(251,113,133,.3);border-radius:8px;padding:14px;font-size:12px;color:var(--text2);line-height:1.8;margin-top:16px}
@media(max-width:480px){.container{padding:20px 14px}.card{padding:22px 16px}}
</style>
</head>
<body>

<header>
<a href="/lawyer/">← 返回</a>
<span style="color:var(--text2);font-size:14px"><?= htmlspecialchars($info['username']) ?></span>
</header>

<div class="container">
<h1>👑 升级<span>VIP会员</span></h1>
<p class="sub">解锁文书生成 · 100元/月 · 扫码自动激活</p>

<div class="card vip-card">
<h3>VIP专属权益</h3>
<ul class="features">
<li>催告函生成（欠款/欠薪/押金催收）</li>
<li>民事起诉状草稿</li>
<li>证据清单模板</li>
<li>劳动仲裁申请书</li>
<li>借条/欠条模板</li>
<li>离婚协议书（参考模板）</li>
<li>法律咨询（免费用户也可用）</li>
</ul>
</div>

<div class="card">
<h3 style="color:var(--text);text-align:center;margin-bottom:16px">📱 微信扫码支付</h3>
<div class="qr-wrap" id="qrWrap">
<div class="loading" id="qrLoading">正在生成支付二维码…</div>
<img id="qrImage" src="" alt="支付二维码" style="display:none">
<p class="amount">¥100.00</p>
<p class="status-ok" id="paidHint">✅ 支付成功后VIP自动激活，无需手动操作</p>
</div>

<div class="manual">
<p>二维码加载失败？手动激活</p>
<input type="number" id="payAmount" placeholder="金额" step="0.01" min="100" value="100">
<button onclick="manualActivate()">激活VIP</button>
<div class="status-msg" id="statusMsg"></div>
</div>

<div class="note">⚠️ 激活后有效期30天。客服：抖音号 731295726</div>
</div>
</div>

<script>
// 加载微信支付二维码
async function loadQrCode() {
    try {
        let resp = await fetch('wechat_create_order.php');
        let data = await resp.json();
        
        if (data.success && data.code_url) {
            let qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' + encodeURIComponent(data.code_url);
            document.getElementById('qrImage').src = qrUrl;
            document.getElementById('qrImage').style.display = 'block';
            document.getElementById('qrLoading').style.display = 'none';
            document.getElementById('paidHint').style.display = 'block';
            // 每5秒检查支付状态
            startPolling(data.out_trade_no);
        } else {
            document.getElementById('qrLoading').textContent = '二维码生成失败，请使用手动激活';
        }
    } catch(e) {
        document.getElementById('qrLoading').textContent = '网络异常，请使用手动激活';
    }
}

// 轮询支付状态
function startPolling(outTradeNo) {
    let count = 0;
    let timer = setInterval(async function() {
        count++;
        if (count > 120) { clearInterval(timer); return; } // 10分钟超时
        
        try {
            let resp = await fetch('wechat_check_order.php?out_trade_no=' + outTradeNo);
            let data = await resp.json();
            if (data.paid) {
                clearInterval(timer);
                document.getElementById('paidHint').textContent = '🎉 支付成功！VIP已激活，2秒后跳转…';
                document.getElementById('paidHint').style.color = '#4ade80';
                setTimeout(function() { location.href = '/lawyer/'; }, 2000);
            }
        } catch(e) {}
    }, 5000);
}

// 手动激活
async function manualActivate() {
    let amount = parseFloat(document.getElementById('payAmount').value);
    let msg = document.getElementById('statusMsg');
    
    if (!amount || amount < 100) {
        msg.className = 'status-msg err';
        msg.textContent = '金额不足100元';
        msg.style.display = 'block';
        return;
    }
    
    try {
        let resp = await fetch('activate_vip.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({amount: amount})
        });
        let data = await resp.json();
        
        if (data.success) {
            msg.className = 'status-msg ok';
            msg.textContent = '🎉 ' + data.message;
            msg.style.display = 'block';
            setTimeout(function() { location.href = '/lawyer/'; }, 2000);
        } else {
            msg.className = 'status-msg err';
            msg.textContent = data.error;
            msg.style.display = 'block';
        }
    } catch(e) {
        msg.className = 'status-msg err';
        msg.textContent = '网络异常';
        msg.style.display = 'block';
    }
}

loadQrCode();
</script>
</body>
</html>
