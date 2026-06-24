<?php
/**
 * AI 语音合成 - 前端页面
 * Microsoft Edge TTS · 20+ 中文音色
 */
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TTS 语音合成 · AI花哥</title>
<style>
:root{--bg:#050507;--card:#0d0d12;--border:#2a2830;--green:#00d992;--purple:#a78bfa;--cyan:#22d3ee;--text:#e8e8ed;--text2:#a09eaa;--text3:#6b6876;--accent:#22d3ee;--accent-glow:rgba(34,211,238,.25)}
*{margin:0;padding:0;box-sizing:border-box}
body{background:var(--bg);color:var(--text);font-family:system-ui,'PingFang SC','Microsoft YaHei',sans-serif;min-height:100vh;display:flex;flex-direction:column}
header{background:rgba(5,5,7,.92);backdrop-filter:blur(12px);border-bottom:1px solid var(--border);padding:0 24px;position:sticky;top:0;z-index:100}
.header-inner{max-width:960px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;height:56px}
.logo{font-size:18px;font-weight:700;color:var(--green);text-decoration:none;display:flex;align-items:center;gap:8px}
.logo-dot{width:8px;height:8px;border-radius:50%;background:var(--green);animation:pulse 2s infinite}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(1.5)}}
.back-link{color:var(--text3);text-decoration:none;font-size:13px}.back-link:hover{color:var(--cyan)}
.container{max-width:960px;margin:0 auto;padding:32px 20px;flex:1}
h1{font-size:24px;font-weight:600;margin-bottom:4px}
.subtitle{font-size:13px;color:var(--text3);margin-bottom:28px}

.form-section{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:24px;margin-bottom:20px}
.form-group{display:flex;flex-direction:column;gap:6px;margin-bottom:16px}
.form-group label{font-size:12px;font-weight:500;color:var(--text2);text-transform:uppercase;letter-spacing:.5px}
textarea{background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:8px;padding:12px 14px;font-size:15px;color:var(--text);font-family:inherit;resize:vertical;min-height:120px;width:100%;transition:border .2s}
textarea:focus{outline:none;border-color:var(--cyan);box-shadow:0 0 0 3px rgba(34,211,238,.1)}

.voice-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:8px;max-height:240px;overflow-y:auto;padding:4px}
.voice-item{padding:10px 12px;border-radius:8px;border:1px solid var(--border);background:rgba(255,255,255,.02);cursor:pointer;transition:all .2s;text-align:center}
.voice-item:hover{border-color:rgba(255,255,255,.15);background:rgba(255,255,255,.04)}
.voice-item.active{border-color:var(--cyan);background:rgba(34,211,238,.08);box-shadow:0 0 10px var(--accent-glow)}
.voice-item .vn{font-size:13px;font-weight:500;color:var(--text)}
.voice-item .vd{font-size:11px;color:var(--text3);margin-top:2px}
.voice-item.active .vn{color:var(--cyan)}
.voice-item.active .vd{color:var(--text2)}

.speed-row{display:flex;align-items:center;gap:12px}
.speed-row select{background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:8px;padding:8px 12px;color:var(--text);font-size:13px;cursor:pointer}
.char-count{font-size:12px;color:var(--text3);text-align:right}

.submit-btn{width:100%;padding:14px;border:none;border-radius:10px;background:linear-gradient(135deg,var(--cyan),#0891b2);color:#fff;font-size:15px;font-weight:600;cursor:pointer;transition:all .3s;margin-top:8px}
.submit-btn:hover:not(:disabled){box-shadow:0 0 30px var(--accent-glow);transform:translateY(-1px)}
.submit-btn:disabled{opacity:.5;cursor:not-allowed}

.result-section{display:none;margin-top:20px}
.result-section.show{display:block}
.audio-player{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:16px 24px}
.audio-player audio{width:100%;margin-bottom:12px}
.audio-actions{display:flex;gap:8px}
.audio-actions a,.audio-actions button{padding:8px 16px;border-radius:8px;font-size:13px;text-decoration:none;cursor:pointer;background:rgba(255,255,255,.05);border:1px solid var(--border);color:var(--text2);transition:all .2s}
.audio-actions a:hover,.audio-actions button:hover{background:rgba(255,255,255,.1);color:var(--text)}
.audio-actions .dl-btn{background:rgba(0,217,146,.1)!important;border-color:rgba(0,217,146,.2)!important;color:var(--green)!important}

.spinner{display:none;text-align:center;padding:24px}
.spinner.show{display:block}
.spinner-dot{display:inline-block;width:10px;height:10px;margin:0 6px;border-radius:50%;background:var(--cyan);animation:bounce 1.2s infinite}
.spinner-dot:nth-child(2){animation-delay:.2s}.spinner-dot:nth-child(3){animation-delay:.4s}
@keyframes bounce{0%,80%,100%{transform:scale(.6)}40%{transform:scale(1)}}
.spinner-text{color:var(--text2);font-size:13px;margin-top:12px}

.error-msg{display:none;margin-top:12px;padding:12px;border-radius:8px;background:rgba(251,113,133,.08);border:1px solid rgba(251,113,133,.2);color:#fb7185;font-size:13px}
.error-msg.show{display:block}

.quick-texts{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px}
.quick-btn{padding:4px 10px;border-radius:6px;font-size:11px;background:rgba(34,211,238,.08);border:1px solid rgba(34,211,238,.15);color:var(--text2);cursor:pointer;transition:all .2s}
.quick-btn:hover{background:rgba(34,211,238,.15);color:var(--cyan)}

footer{text-align:center;padding:24px;color:var(--text3);font-size:12px;border-top:1px solid var(--border)}
@media(max-width:600px){.voice-grid{grid-template-columns:repeat(3,1fr)}}
</style>
</head>
<body>

<header>
<div class="header-inner">
<a href="/" class="logo"><span class="logo-dot"></span>AI花哥</a>
<a href="/studio/" class="back-link">← 返回工坊</a>
</div>
</header>

<div class="container">
<h1>🗣️ TTS 语音合成</h1>
<p class="subtitle">语音合成 · 20+ 中文音色 · 免费不限量</p>

<div class="form-section">
<div class="form-group">
<label>输入文本</label>
<textarea id="text-input" placeholder="输入要合成的文本内容...&#10;&#10;支持中文、英文混读，最多5000字"></textarea>
<div class="char-count"><span id="char-count">0</span> / 5000</div>
<div class="quick-texts">
<span class="quick-btn" onclick="setText('欢迎使用AI创作工坊的语音合成功能，我们可以将文字转换为自然流畅的语音。')">欢迎语</span>
<span class="quick-btn" onclick="setText('今天天气不错，适合出去走走，感受一下大自然的美好。')">日常</span>
<span class="quick-btn" onclick="setText('人工智能正在改变世界，从自动驾驶到医疗诊断，AI技术已经深入我们生活的方方面面。')">科技</span>
</div>
</div>

<div class="form-group">
<label>选择音色</label>
<div class="voice-grid" id="voice-grid"></div>
</div>

<div class="form-group">
<label>语速</label>
<div class="speed-row">
<select id="speed">
<option value="-20%">很慢</option>
<option value="-10%">较慢</option>
<option value="+0%" selected>正常</option>
<option value="+10%">较快</option>
<option value="+20%">很快</option>
</select>
</div>
</div>
</div>

<button class="submit-btn" id="submit-btn" onclick="generateTTS()">🔊 开始合成</button>

<div class="spinner" id="spinner">
<span class="spinner-dot"></span><span class="spinner-dot"></span><span class="spinner-dot"></span>
<div class="spinner-text">正在合成语音...</div>
</div>
<div class="error-msg" id="error-msg"></div>

<div class="result-section" id="result-section">
<div class="audio-player">
<audio id="audio-player" controls></audio>
<div class="audio-actions">
<a id="dl-btn" class="dl-btn" href="#" download>💾 下载 MP3</a>
</div>
</div>
</div>
</div>

<footer>© 2026 AI创作工坊 · Microsoft Edge TTS</footer>

<script>
const API = '/studio/tts/api/';
let voices = {};
let selectedVoice = 'zh-CN-XiaoxiaoNeural';

// Load voices
fetch(API + 'voices').then(r => r.json()).then(data => {
    voices = data;
    const grid = document.getElementById('voice-grid');
    grid.innerHTML = Object.entries(voices).map(([id, name]) => 
        `<div class="voice-item${id === selectedVoice ? ' active' : ''}" data-voice="${id}" onclick="selectVoice(this, '${id}')">
            <div class="vn">${name.split('（')[0]}</div>
            <div class="vd">${name.includes('（') ? name.match(/（(.+?)）/)[1] : ''}</div>
        </div>`
    ).join('');
});

function selectVoice(el, voice) {
    document.querySelectorAll('.voice-item').forEach(i => i.classList.remove('active'));
    el.classList.add('active');
    selectedVoice = voice;
}

function setText(t) { document.getElementById('text-input').value = t; updateCount(); }

document.getElementById('text-input').addEventListener('input', updateCount);
function updateCount() {
    const len = document.getElementById('text-input').value.length;
    document.getElementById('char-count').textContent = len;
}

async function generateTTS() {
    const text = document.getElementById('text-input').value.trim();
    if (!text) { alert('请输入文本'); return; }
    if (text.length > 5000) { alert('文本过长，最多5000字'); return; }

    const speed = document.getElementById('speed').value;
    const btn = document.getElementById('submit-btn');
    const spinner = document.getElementById('spinner');
    const resultSection = document.getElementById('result-section');
    const errorMsg = document.getElementById('error-msg');

    btn.disabled = true; btn.textContent = '⏳ 合成中...';
    spinner.classList.add('show');
    resultSection.classList.remove('show');
    errorMsg.classList.remove('show');

    try {
        const resp = await fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ text, voice: selectedVoice, rate: speed })
        });
        const data = await resp.json();

        if (!data.success) {
            errorMsg.textContent = data.error || '合成失败';
            errorMsg.classList.add('show');
            return;
        }

        const audio = document.getElementById('audio-player');
        audio.src = data.url;
        document.getElementById('dl-btn').href = data.url;
        resultSection.classList.add('show');

    } catch(e) {
        errorMsg.textContent = '网络错误: ' + e.message;
        errorMsg.classList.add('show');
    } finally {
        btn.disabled = false; btn.textContent = '🔊 开始合成';
        spinner.classList.remove('show');
    }
}
</script>
</body>
</html>
