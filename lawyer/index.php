<?php
require_once __DIR__ . '/auth_lawyer.php';
$logged = is_logged_in();
$vip = is_vip();
$info = get_vip_info();
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>家庭法律助手 · AI花哥</title>
<meta name="description" content="免费法律咨询，AI智能分析婚姻、劳动、借贷、租房等法律问题。VIP会员可生成专业法律文书。">
<style>
:root{--bg:#0a0a0f;--surface:#14141a;--border:#2a2a35;--gold:#f0c060;--cyan:#4dc9f6;--text:#e8e8ec;--text2:#9898a8;--danger:#f87171;--green:#4ade80;--vip:#f59e0b}
*{margin:0;padding:0;box-sizing:border-box}
body{background:var(--bg);color:var(--text);font-family:-apple-system,'PingFang SC','Microsoft YaHei',sans-serif;height:100dvh;display:flex;flex-direction:column;overflow:hidden}
.overlay{position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.9);display:flex;align-items:center;justify-content:center;backdrop-filter:blur(8px)}
.overlay-box{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:32px 24px;margin:20px;max-width:440px;text-align:left}
.overlay-box h2{font-size:20px;color:var(--danger);margin-bottom:20px;text-align:center}
.overlay-box p{font-size:13px;color:var(--text2);line-height:1.8;margin-bottom:8px}
.overlay-box .btn{display:block;margin:24px auto 0;padding:12px 40px;background:var(--cyan);border:none;border-radius:8px;color:#000;font-size:15px;font-weight:600;cursor:pointer}
header{flex-shrink:0;padding:10px 16px;background:var(--surface);border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;flex-wrap:wrap}
header .back{color:var(--text2);text-decoration:none;font-size:13px;white-space:nowrap}
header h1{font-size:16px;font-weight:500;white-space:nowrap}
header h1 span{color:var(--gold)}
header .right{display:flex;align-items:center;gap:8px;margin-left:auto;font-size:12px}
header .login-link{color:var(--cyan);text-decoration:none}
header .vip-badge{background:var(--vip);color:#000;padding:2px 8px;border-radius:10px;font-weight:600;font-size:11px;white-space:nowrap}
header .user-name{color:var(--text2)}
header .vip-upgrade{color:var(--vip);text-decoration:none;border:1px solid var(--vip);padding:2px 8px;border-radius:10px;font-size:11px}
.doc-toggle{flex-shrink:0;padding:8px 16px;background:var(--bg);border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px;font-size:13px;color:var(--text2)}
.doc-toggle .mode{display:flex;gap:4px}
.doc-toggle .mode-btn{padding:5px 14px;border-radius:14px;border:1px solid var(--border);background:transparent;color:var(--text2);cursor:pointer;font-size:12px;white-space:nowrap}
.doc-toggle .mode-btn.active{background:var(--vip);border-color:var(--vip);color:#000;font-weight:600}
.doc-toggle .mode-btn.doc-active{background:var(--vip);border-color:var(--vip);color:#000;font-weight:600}
.doc-toggle .vip-only{font-size:10px;color:var(--vip)}
.chat-wrap{flex:1;overflow-y:auto;padding:16px;-webkit-overflow-scrolling:touch}
.chat-wrap .empty{text-align:center;padding:60px 20px;color:var(--text2)}
.chat-wrap .empty .icon{font-size:48px;margin-bottom:16px}
.chat-wrap .empty p{font-size:14px;line-height:1.8}
.msg{margin-bottom:16px;max-width:90%}
.msg.user{margin-left:auto}
.msg.assistant{margin-right:auto}
.msg .bubble{padding:12px 16px;border-radius:14px;font-size:14px;line-height:1.7;word-break:break-word;white-space:pre-wrap}
.msg.user .bubble{background:var(--cyan);color:#000;border-bottom-right-radius:4px}
.msg.assistant .bubble{background:var(--surface);border:1px solid var(--border);border-bottom-left-radius:4px}
.msg .time{font-size:11px;color:var(--text2);margin-top:4px;padding:0 4px}
.msg.user .time{text-align:right}
.typing{display:none;padding:12px 16px}
.typing.show{display:block}
.typing .dots{display:flex;gap:4px}
.typing .dots span{width:6px;height:6px;background:var(--text2);border-radius:50%;animation:bounce 1.4s infinite ease-in-out both}
.typing .dots span:nth-child(1){animation-delay:-0.32s}
.typing .dots span:nth-child(2){animation-delay:-0.16s}
@keyframes bounce{0%,80%,100%{transform:scale(0)}40%{transform:scale(1)}}
.input-wrap{flex-shrink:0;padding:12px 16px;background:var(--surface);border-top:1px solid var(--border);display:flex;gap:10px;align-items:flex-end}
.input-wrap textarea{flex:1;background:var(--bg);border:1px solid var(--border);border-radius:12px;color:var(--text);padding:10px 14px;font-size:15px;resize:none;max-height:100px;line-height:1.5;font-family:inherit;outline:none}
.input-wrap textarea:focus{border-color:var(--cyan)}
.input-wrap button{width:44px;height:44px;background:var(--cyan);border:none;border-radius:50%;color:#000;font-size:18px;cursor:pointer;flex-shrink:0;display:flex;align-items:center;justify-content:center}
.input-wrap button:disabled{opacity:.4;cursor:not-allowed}
.input-wrap button.doc-btn{background:var(--vip)}
@media(max-width:480px){
header{padding:8px 12px}
header h1{font-size:14px}
.msg .bubble{font-size:13px;padding:10px 14px}
.input-wrap{padding:10px 12px}
.input-wrap textarea{font-size:14px;padding:8px 12px}
}
</style>
</head>
<body>

<div class="overlay" id="disclaimer">
<div class="overlay-box">
<h2>⚠️ 免责声明</h2>
<p>1. 法律咨询免费，AI仅提供初步流程指引，不构成正式法律意见。</p>
<p>2. 文书生成为VIP专属功能（100元/月）。</p>
<p>3. 涉及人身安全、暴力威胁等紧急情况，请立即拨打110。</p>
<p>4. 具体情况请咨询执业律师。</p>
<button class="btn" onclick="dismissDisclaimer()">已知悉，开始使用</button>
</div>
</div>

<header>
<a href="/" class="back">← 首页</a>
<h1>⚖️ <span>家庭法律</span>助手</h1>
<div class="right">
<?php if ($logged): ?>
  <span class="user-name"><?= htmlspecialchars($info['username']) ?></span>
  <?php if ($vip): ?>
    <span class="vip-badge">VIP</span>
  <?php else: ?>
    <a href="/lawyer/vip.php" class="vip-upgrade">升级VIP</a>
  <?php endif; ?>
  <a href="/studio/logout.php" class="login-link">退出</a>
<?php else: ?>
  <a href="/studio/login.php?redirect=/lawyer/" class="login-link">登录</a>
  <a href="/studio/register.php?redirect=/lawyer/" class="login-link">注册</a>
<?php endif; ?>
</div>
</header>

<div class="doc-toggle">
  <span>模式：</span>
  <div class="mode">
    <button class="mode-btn active" id="modeConsult" onclick="setMode('consult')">💬 法律咨询</button>
    <button class="mode-btn" id="modeDoc" onclick="setMode('document')">📄 生成文书 <span class="vip-only">VIP</span></button>
  </div>
</div>

<div class="chat-wrap" id="chatWrap">
<div class="empty" id="emptyHint">
<div class="icon">⚖️</div>
<p>法律咨询 · 免费<br>文书生成 · VIP会员专属</p>
<p style="font-size:12px;margin-top:12px;color:var(--text2)">
📎 上传证据材料：合同、聊天截图、欠条等<br>
AI会读取文件内容辅助分析
</p>
<p style="font-size:11px;color:var(--text2);margin-top:8px">
支持：图片、PDF、Word、TXT · 最大10MB
</p>
</div>
</div>

<div class="input-wrap">
<input type="file" id="fileInput" accept="image/*,.pdf,.docx,.txt" style="display:none" onchange="handleFileUpload(this)">
<button onclick="document.getElementById('fileInput').click()" title="上传证据" style="width:40px;height:40px;background:var(--surface);border:1px solid var(--border);border-radius:50%;color:var(--text2);font-size:18px;cursor:pointer;flex-shrink:0">📎</button>
<textarea id="userInput" rows="1" placeholder="描述您的情况，可上传证据文件辅助分析……" onkeydown="onKeyDown(event)"></textarea>
<button onclick="sendMessage()" id="sendBtn">▲</button>
</div>
<div id="uploadStatus" style="text-align:center;padding:4px;font-size:12px;color:var(--cyan);display:none"></div>

<script>
let history = [];
let isWaiting = false;
let mode = 'consult';  // consult | document

function dismissDisclaimer() {
    document.getElementById('disclaimer').remove();
    document.getElementById('userInput').focus();
}

function setMode(m) {
    mode = m;
    document.getElementById('modeConsult').classList.toggle('active', m === 'consult');
    document.getElementById('modeDoc').classList.toggle('active', m === 'document');
    let btn = document.getElementById('sendBtn');
    let ta = document.getElementById('userInput');
    if (m === 'document') {
        btn.classList.add('doc-btn');
        ta.placeholder = '描述您需要的文书，如：帮我写一份催告函……';
    } else {
        btn.classList.remove('doc-btn');
        ta.placeholder = '描述您的法律问题……';
    }
    ta.focus();
}

function onKeyDown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
}

document.getElementById('userInput').addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 100) + 'px';
});

function addMessage(role, text, isDoc) {
    let wrap = document.getElementById('chatWrap');
    document.getElementById('emptyHint').style.display = 'none';
    let div = document.createElement('div');
    div.className = 'msg ' + role;
    
    let displayText = text;
    let downloadBtn = '';
    
    if (isDoc) {
        // 提取文书内容（【DOC_START】和【DOC_END】之间）
        let docMatch = text.match(/【DOC_START】([\s\S]*?)【DOC_END】/);
        if (docMatch) {
            let docContent = docMatch[1].trim();
            // 替换标记，高亮文书区域
            displayText = text.replace(/【DOC_START】/g, '<div style="border:1px solid #f59e0b;border-radius:8px;padding:12px;margin:8px 0;background:rgba(245,158,11,.05)">')
                .replace(/【DOC_END】/g, '</div>');
            // 生成下载文件名
            let docTitle = docContent.split('\n')[0].replace(/[#*\s]/g, '').substring(0, 30) || '法律文书';
            let encoded = encodeURIComponent(docContent);
            downloadBtn = '<div style="margin-top:10px"><a href="data:text/plain;charset=utf-8,' + encoded 
                + '" download="' + docTitle + '.txt" '
                + 'style="display:inline-block;padding:6px 16px;background:#f59e0b;color:#000;border-radius:6px;text-decoration:none;font-size:13px">📥 下载文书</a></div>';
        }
    }
    
    div.innerHTML = '<div class="bubble">' + escapeHtml(displayText).replace(/\n/g, '<br>') + '</div>' + downloadBtn
        + '<div class="time">' + new Date().toLocaleTimeString('zh-CN', {hour:'2-digit',minute:'2-digit'}) + '</div>';
    wrap.appendChild(div);
    wrap.scrollTop = wrap.scrollHeight;
}

function showTyping(show) {
    let typing = document.getElementById('typingIndicator');
    if (show) {
        if (!typing) {
            typing = document.createElement('div');
            typing.id = 'typingIndicator';
            typing.className = 'typing show';
            typing.innerHTML = '<div class="dots"><span></span><span></span><span></span></div>';
            document.getElementById('chatWrap').appendChild(typing);
        }
        typing.classList.add('show');
    } else if (typing) {
        typing.classList.remove('show');
    }
    document.getElementById('chatWrap').scrollTop = document.getElementById('chatWrap').scrollHeight;
}

async function sendMessage() {
    let input = document.getElementById('userInput');
    let text = input.value.trim();
    if (!text || isWaiting) return;
    input.value = '';
    input.style.height = 'auto';
    isWaiting = true;
    document.getElementById('sendBtn').disabled = true;

    addMessage('user', (mode === 'document' ? '[文书生成] ' : '') + text);
    showTyping(true);

    try {
        let body = { message: text, history: history, type: mode };
        let resp = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        let data = await resp.json();
        showTyping(false);

        if (data.error) {
            if (data.action === 'login') {
                addMessage('assistant', '🔒 ' + data.error + '\n\n<a href="' + data.redirect + '" style="color:#4dc9f6">点击登录</a>');
            } else if (data.action === 'upgrade') {
                addMessage('assistant', '👑 ' + data.error + '\n\n<a href="' + data.redirect + '" style="color:#f59e0b">点击升级VIP（100元/月）</a>');
            } else {
                addMessage('assistant', '❌ ' + data.error);
            }
        } else {
            addMessage('assistant', data.reply, data.has_doc);
            history.push({ role: 'user', content: text });
            history.push({ role: 'assistant', content: data.reply });
            if (history.length > 40) history = history.slice(-40);
        }
    } catch (e) {
        showTyping(false);
        addMessage('assistant', '❌ 网络异常，请检查连接后重试');
    }

    isWaiting = false;
    document.getElementById('sendBtn').disabled = false;
    input.focus();
}

function escapeHtml(text) {
    let div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// 文件上传处理
let uploadedFiles = [];

async function handleFileUpload(input) {
    let file = input.files[0];
    if (!file) return;
    
    let status = document.getElementById('uploadStatus');
    status.textContent = '正在分析文件: ' + file.name + '...';
    status.style.display = 'block';
    
    let formData = new FormData();
    formData.append('file', file);
    
    try {
        let resp = await fetch('upload.php', { method: 'POST', body: formData });
        let data = await resp.json();
        
        if (data.success) {
            uploadedFiles.push(data);
            status.textContent = '✅ ' + data.filename + ' 已分析' + (data.text.length > 50 ? ' (' + data.text.length + '字)' : '');
            status.style.color = '#4ade80';
            
            // 将提取的文字添加到输入框
            let ta = document.getElementById('userInput');
            if (data.text && data.text.length > 10) {
                let preview = data.text.substring(0, 200).replace(/\n/g, ' ');
                addMessage('user', '📎 上传文件: ' + data.filename + '\n内容摘要: ' + preview + (data.text.length > 200 ? '...(共' + data.text.length + '字)' : ''));
            } else {
                addMessage('user', '📎 上传文件: ' + data.filename + ' (图片已接收，请在消息中描述图片内容)');
            }
            
            setTimeout(function() { status.style.display = 'none'; }, 3000);
        } else {
            status.textContent = '❌ ' + data.error;
            status.style.color = '#f87171';
            setTimeout(function() { status.style.display = 'none'; }, 3000);
        }
    } catch(e) {
        status.textContent = '❌ 上传失败';
        status.style.color = '#f87171';
        setTimeout(function() { status.style.display = 'none'; }, 3000);
    }
    
    input.value = '';
}

// 发送消息时附带文件内容
let origSendMessage = sendMessage;
sendMessage = async function() {
    let input = document.getElementById('userInput');
    let text = input.value.trim();
    if (!text && uploadedFiles.length === 0) return;
    
    // 如果有上传文件，将提取的文字附加到消息
    if (uploadedFiles.length > 0) {
        let fileContext = '\n\n【已上传证据材料】\n';
        for (let f of uploadedFiles) {
            fileContext += '--- ' + f.filename + ' ---\n';
            if (f.text && f.text.length > 0 && f.type !== 'jpg' && f.type !== 'png' && f.type !== 'gif' && f.type !== 'webp') {
                fileContext += f.text + '\n';
            } else {
                fileContext += '[图片文件，请用户描述内容]\n';
            }
        }
        text = (text || '请根据上传的证据材料分析') + fileContext;
        uploadedFiles = []; // 清除已用文件
    }
    
    // 调用原始发送逻辑
    if (!text || isWaiting) return;
    input.value = '';
    input.style.height = 'auto';
    isWaiting = true;
    document.getElementById('sendBtn').disabled = true;
    
    addMessage('user', (mode === 'document' ? '[文书生成] ' : '') + text);
    showTyping(true);
    
    try {
        let body = { message: text, history: history, type: mode };
        let resp = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        let data = await resp.json();
        showTyping(false);
        
        if (data.error) {
            if (data.action === 'login') {
                addMessage('assistant', '🔒 ' + data.error + '\n\n<a href="' + data.redirect + '" style="color:#4dc9f6">点击登录</a>');
            } else if (data.action === 'upgrade') {
                addMessage('assistant', '👑 ' + data.error + '\n\n<a href="' + data.redirect + '" style="color:#f59e0b">点击升级VIP（100元/月）</a>');
            } else {
                addMessage('assistant', '❌ ' + data.error);
            }
        } else {
            addMessage('assistant', data.reply, data.has_doc);
            history.push({ role: 'user', content: text });
            history.push({ role: 'assistant', content: data.reply });
            if (history.length > 40) history = history.slice(-40);
        }
    } catch (e) {
        showTyping(false);
        addMessage('assistant', '❌ 网络异常，请检查连接后重试');
    }
    
    isWaiting = false;
    document.getElementById('sendBtn').disabled = false;
    input.focus();
};
</script>
</body>
</html>
