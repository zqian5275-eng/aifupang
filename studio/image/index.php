<?php
/**
 * AI 图片生成 - 前端页面
 * 支持文生图 / 图生图
 */
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AI 图片生成 · AI花哥</title>
<style>
:root {
    --bg: #050507; --card: #0d0d12; --border: #2a2830;
    --green: #00d992; --purple: #a78bfa;
    --text: #e8e8ed; --text2: #a09eaa; --text3: #6b6876;
    --accent: #8b5cf6; --accent-glow: rgba(139,92,246,.25);
}
*{margin:0;padding:0;box-sizing:border-box}
body{background:var(--bg);color:var(--text);font-family:system-ui,'PingFang SC','Microsoft YaHei',sans-serif;min-height:100vh;display:flex;flex-direction:column}
header{background:rgba(5,5,7,.92);backdrop-filter:blur(12px);border-bottom:1px solid var(--border);padding:0 24px;position:sticky;top:0;z-index:100}
.header-inner{max-width:960px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;height:56px}
.logo{font-size:18px;font-weight:700;color:var(--green);text-decoration:none;display:flex;align-items:center;gap:8px}
.logo-dot{width:8px;height:8px;border-radius:50%;background:var(--green);animation:pulse 2s infinite}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(1.5)}}
.back-link{color:var(--text3);text-decoration:none;font-size:13px}.back-link:hover{color:var(--purple)}
.container{max-width:960px;margin:0 auto;padding:32px 20px;flex:1}
h1{font-size:24px;font-weight:600;margin-bottom:4px}
.subtitle{font-size:13px;color:var(--text3);margin-bottom:28px}

.mode-tabs{display:flex;gap:4px;background:var(--card);border:1px solid var(--border);border-radius:10px;padding:4px;margin-bottom:20px}
.mode-tab{flex:1;padding:10px 16px;border-radius:7px;font-size:13px;font-weight:500;color:var(--text2);background:transparent;border:none;cursor:pointer;transition:all .2s;text-align:center}
.mode-tab:hover{color:var(--text);background:rgba(255,255,255,.04)}
.mode-tab.active{color:#fff;background:rgba(139,92,246,.15);box-shadow:0 0 12px var(--accent-glow)}

.form-section{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:24px;margin-bottom:20px}
.form-row{display:flex;gap:16px;margin-bottom:16px}
.form-row:last-child{margin-bottom:0}
.form-group{display:flex;flex-direction:column;gap:6px}
.form-group.grow{flex:1}
.form-group label{font-size:12px;font-weight:500;color:var(--text2);text-transform:uppercase;letter-spacing:.5px}
textarea,input[type="text"],input[type="number"],select{background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:8px;padding:10px 14px;font-size:14px;color:var(--text);font-family:inherit;transition:border .2s;width:100%}
textarea:focus,input:focus,select:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(139,92,246,.1)}
textarea{resize:vertical;min-height:100px}
select{cursor:pointer}

.upload-zone{border:2px dashed var(--border);border-radius:10px;padding:32px;text-align:center;cursor:pointer;transition:all .2s;min-height:120px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px}
.upload-zone:hover,.upload-zone.dragover{border-color:var(--accent);background:rgba(139,92,246,.04)}
.upload-icon{font-size:36px;opacity:.6}.upload-text{font-size:13px;color:var(--text2)}.upload-hint{font-size:11px;color:var(--text3)}
.image-preview{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}
.image-preview img{width:100px;height:100px;object-fit:cover;border-radius:6px;border:1px solid var(--border)}
.preview-item{position:relative}
.remove-btn{position:absolute;top:-6px;right:-6px;width:20px;height:20px;border-radius:50%;background:#fb7185;color:#fff;border:none;cursor:pointer;font-size:12px;display:flex;align-items:center;justify-content:center;opacity:0;transition:opacity .2s}
.preview-item:hover .remove-btn{opacity:1}

.size-presets{display:flex;gap:4px;background:rgba(255,255,255,.02);border:1px solid var(--border);border-radius:8px;padding:4px;width:fit-content}
.size-btn{padding:6px 14px;border-radius:6px;font-size:12px;color:var(--text2);background:transparent;border:none;cursor:pointer;transition:all .2s}
.size-btn:hover{color:var(--text)}.size-btn.active{color:#fff;background:rgba(139,92,246,.15)}

.submit-btn{width:100%;padding:14px;border:none;border-radius:10px;background:linear-gradient(135deg,var(--accent),#7c3aed);color:#fff;font-size:15px;font-weight:600;cursor:pointer;transition:all .3s;margin-top:8px}
.submit-btn:hover:not(:disabled){box-shadow:0 0 30px var(--accent-glow);transform:translateY(-1px)}
.submit-btn:disabled{opacity:.5;cursor:not-allowed}

.result-section{display:none;margin-top:20px}
.result-section.show{display:block}
.result-section img{width:100%;max-width:100%;border-radius:10px;border:1px solid var(--border)}
.result-actions{display:flex;gap:8px;margin-top:12px}
.result-actions a,.result-actions button{padding:8px 16px;border-radius:8px;font-size:13px;text-decoration:none;cursor:pointer;transition:all .2s;background:rgba(255,255,255,.05);border:1px solid var(--border);color:var(--text2)}
.result-actions a:hover,.result-actions button:hover{background:rgba(255,255,255,.1);color:var(--text)}
.download-btn{background:rgba(0,217,146,.1)!important;border-color:rgba(0,217,146,.2)!important;color:var(--green)!important}

.spinner{display:none;text-align:center;padding:32px}
.spinner.show{display:block}
.spinner-dot{display:inline-block;width:10px;height:10px;margin:0 6px;border-radius:50%;background:var(--accent);animation:bounce 1.2s infinite}
.spinner-dot:nth-child(2){animation-delay:.2s}.spinner-dot:nth-child(3){animation-delay:.4s}
@keyframes bounce{0%,80%,100%{transform:scale(.6)}40%{transform:scale(1)}}
.spinner-text{color:var(--text2);font-size:13px;margin-top:12px}

.error-msg{display:none;margin-top:12px;padding:12px;border-radius:8px;background:rgba(251,113,133,.08);border:1px solid rgba(251,113,133,.2);color:#fb7185;font-size:13px}
.error-msg.show{display:block}

.hidden{display:none!important}
footer{text-align:center;padding:24px;color:var(--text3);font-size:12px;border-top:1px solid var(--border)}
@media(max-width:600px){.form-row{flex-direction:column}.mode-tab{font-size:12px;padding:8px 10px}}
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
<h1>🖼️ AI 图片生成</h1>
<p class="subtitle">AI 图片生成 · 文生图 / 图生图</p>

<div class="mode-tabs">
<button class="mode-tab active" data-mode="text">📝 文生图</button>
<button class="mode-tab" data-mode="image">🔄 图生图</button>
</div>

<div class="form-section">
<div class="form-group" style="margin-bottom:16px">
<label>提示词（Prompt）</label>
<textarea id="prompt" rows="3" placeholder="描述你想生成的图片内容...&#10;&#10;推荐：主体 + 场景 + 风格 + 光照 + 构图 + 质量要求"></textarea>
</div>

<div id="image-area" class="hidden">
<div class="form-row">
<div class="form-group grow">
<label>上传图片</label>
<div class="upload-zone" id="upload-zone">
<span class="upload-icon">📤</span>
<span class="upload-text">点击或拖拽上传参考图</span>
<span class="upload-hint">支持 JPG / PNG / WebP，最大 10MB</span>
</div>
<input type="file" id="file-input" accept="image/*" class="hidden">
<div class="image-preview" id="image-preview"></div>
</div>
</div>
<div class="form-row" style="margin-top:12px">
<div class="form-group grow">
<label>或粘贴图片 URL</label>
<input type="text" id="url-input" placeholder="https://example.com/image.png">
</div>
</div>
</div>

<div class="form-row">
<div class="form-group">
<label>尺寸</label>
<div class="size-presets" id="size-presets">
<button class="size-btn" data-size="1024x768">1024×768</button>
<button class="size-btn active" data-size="1024x1024">1024×1024</button>
<button class="size-btn" data-size="768x1024">768×1024</button>
<button class="size-btn" data-size="1280x720">1280×720</button>
</div>
</div>
<div class="form-group">
<label>输出格式</label>
<select id="output-format">
<option value="url">图片 URL</option>
<option value="base64">Base64</option>
</select>
</div>
</div>
</div>

<button class="submit-btn" id="submit-btn" onclick="generateImage()">🚀 开始生成</button>

<div class="spinner" id="spinner">
<span class="spinner-dot"></span><span class="spinner-dot"></span><span class="spinner-dot"></span>
<div class="spinner-text">AI 正在生成图片...</div>
</div>

<div class="error-msg" id="error-msg"></div>

<div class="result-section" id="result-section">
<img id="result-img" alt="生成结果">
<div class="result-actions">
<a id="download-btn" class="download-btn" href="#" download>💾 下载图片</a>
<button onclick="copyImage()">📋 复制图片</button>
</div>
</div>
</div>

<footer>© 2026 AI创作工坊 · AI 图片生成</footer>

<script>
let currentMode = 'text';
let uploadedImage = null;
let resultUrl = null;

// 模式切换
document.querySelectorAll('.mode-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.mode-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        currentMode = this.dataset.mode;
        document.getElementById('image-area').classList.toggle('hidden', currentMode === 'text');
    });
});

// 尺寸选择
document.querySelectorAll('.size-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.size-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
    });
});

// 图片上传
const uploadZone = document.getElementById('upload-zone');
const fileInput = document.getElementById('file-input');
const imagePreview = document.getElementById('image-preview');

uploadZone.addEventListener('click', () => fileInput.click());
uploadZone.addEventListener('dragover', e => { e.preventDefault(); uploadZone.classList.add('dragover'); });
uploadZone.addEventListener('dragleave', () => uploadZone.classList.remove('dragover'));
uploadZone.addEventListener('drop', e => {
    e.preventDefault(); uploadZone.classList.remove('dragover');
    handleFile(e.dataTransfer.files[0]);
});
fileInput.addEventListener('change', () => {
    if (fileInput.files[0]) handleFile(fileInput.files[0]);
    fileInput.value = '';
});

function handleFile(file) {
    if (!file.type.startsWith('image/')) return;
    if (file.size > 10*1024*1024) { alert('图片不能超过 10MB'); return; }
    const reader = new FileReader();
    reader.onload = function(e) {
        uploadedImage = e.target.result;
        imagePreview.innerHTML = `<div class="preview-item"><img src="${uploadedImage}"><button class="remove-btn" onclick="clearImage()">×</button></div>`;
    };
    reader.readAsDataURL(file);
}

function clearImage() {
    uploadedImage = null;
    imagePreview.innerHTML = '';
    document.getElementById('url-input').value = '';
}

async function generateImage() {
    const prompt = document.getElementById('prompt').value.trim();
    if (!prompt) { alert('请输入提示词'); return; }
    
    const urlInput = document.getElementById('url-input').value.trim();
    const activeSize = document.querySelector('.size-btn.active');
    const size = activeSize ? activeSize.dataset.size : '1024x1024';
    const outputFormat = document.getElementById('output-format').value;

    const images = [];
    if (uploadedImage) images.push(uploadedImage);
    else if (urlInput) images.push(urlInput);

    if (currentMode === 'image' && images.length === 0) {
        alert('图生图需要上传图片或填写图片URL');
        return;
    }

    const btn = document.getElementById('submit-btn');
    const spinner = document.getElementById('spinner');
    const resultSection = document.getElementById('result-section');
    const errorMsg = document.getElementById('error-msg');

    btn.disabled = true; btn.textContent = '⏳ 生成中...';
    spinner.classList.add('show');
    resultSection.classList.remove('show');
    errorMsg.classList.remove('show');

    try {
        const resp = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ prompt, mode: currentMode, size, images, output_format: outputFormat })
        });
        const data = await resp.json();

        if (!data.success) {
            errorMsg.textContent = data.error || '生成失败';
            errorMsg.classList.add('show');
            return;
        }

        resultUrl = data.url;
        const img = document.getElementById('result-img');
        img.src = resultUrl;
        img.onload = () => resultSection.classList.add('show');

        const downloadBtn = document.getElementById('download-btn');
        downloadBtn.href = resultUrl;
        downloadBtn.download = 'ai-image-' + Date.now() + '.png';

    } catch(e) {
        errorMsg.textContent = '网络错误: ' + e.message;
        errorMsg.classList.add('show');
    } finally {
        btn.disabled = false; btn.textContent = '🚀 开始生成';
        spinner.classList.remove('show');
    }
}

async function copyImage() {
    if (!resultUrl) return;
    try {
        if (resultUrl.startsWith('data:')) {
            const resp = await fetch(resultUrl);
            const blob = await resp.blob();
            await navigator.clipboard.write([new ClipboardItem({'image/png': blob})]);
        } else {
            const resp = await fetch(resultUrl);
            const blob = await resp.blob();
            await navigator.clipboard.write([new ClipboardItem({'image/png': blob})]);
        }
        alert('图片已复制到剪贴板');
    } catch(e) {
        alert('复制失败，请用"下载图片"按钮');
    }
}
</script>
</body>
</html>
