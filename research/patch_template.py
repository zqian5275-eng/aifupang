#!/usr/bin/env python3
"""Add login prompt overlay to research template."""
import re

path = '/www/wwwroot/aifupang.com/research/templates/index.html'
with open(path, 'r') as f:
    content = f.read()

# 1. Add login overlay CSS
login_css = '''
        /* ========== 登录提示弹窗 ========== */
        .login-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.85); z-index: 10001;
            display: flex; align-items: center; justify-content: center;
        }
        .login-overlay.hidden { display: none; }
        .login-modal {
            background: var(--bg-primary, #0d1117);
            border: 1px solid var(--border-light, #30363d);
            border-radius: 12px; padding: 36px;
            max-width: 400px; width: 90vw; text-align: center;
            box-shadow: 0 0 60px rgba(0,0,0,0.6);
        }
        .login-modal h3 { color: var(--accent-green, #3fb950); font-size: 18px; margin: 0 0 4px; }
        .login-modal p { color: var(--text-secondary, #8b949e); font-size: 13px; margin: 12px 0 24px; line-height: 1.6; }
        .login-modal .btn-row { display: flex; gap: 10px; justify-content: center; }
        .login-modal .btn-login, .login-modal .btn-reg {
            padding: 10px 24px; border-radius: 8px; font-size: 14px;
            font-weight: 600; cursor: pointer; text-decoration: none; border: none;
            display: inline-block;
        }
        .login-modal .btn-login {
            background: var(--accent-green, #3fb950); color: #000;
        }
        .login-modal .btn-reg {
            background: transparent; color: var(--accent-blue, #58a6ff);
            border: 1px solid var(--accent-blue, #58a6ff);
        }
        .login-modal .btn-close {
            margin-top: 12px; background: none; border: none;
            color: var(--text-muted, #484f58); font-size: 12px; cursor: pointer;
        }'''

content = content.replace(
    '/* ========== 风险揭示弹窗 ========== */',
    login_css + '\n        /* ========== 风险揭示弹窗 ========== */'
)

# 2. Add login overlay HTML
login_html = '''    <div class="login-overlay hidden" id="loginOverlay">
        <div class="login-modal">
            <h3>&#x1f512; 此功能需要登录</h3>
            <p>登录后可使用AI智能分析、财报数据、研报检索等深度功能。<br>完全免费注册。</p>
            <div class="btn-row">
                <a href="/studio/login.php?redirect=%2Fresearch%2F" class="btn-login">登 录</a>
                <a href="/studio/register.php" class="btn-reg">免费注册</a>
            </div>
            <button class="btn-close" onclick="document.getElementById('loginOverlay').classList.add('hidden')">暂不登录，继续浏览</button>
        </div>
    </div>
'''

content = content.replace('</body>', login_html + '\n</body>')

# 3. Add showLoginPrompt JS
show_login_js = '''    <script>
    window.showLoginPrompt = function() {
        document.getElementById('loginOverlay').classList.remove('hidden');
    };
    (function() {
        var origFetch = window.fetch;
        window.fetch = function() {
            var args = arguments;
            return origFetch.apply(this, args).then(function(resp) {
                if (resp.status === 401) {
                    window.showLoginPrompt();
                }
                return resp;
            });
        };
    })();
    </script>
'''

content = content.replace('</body>', show_login_js + '\n</body>')

with open(path, 'w') as f:
    f.write(content)

print('OK')
