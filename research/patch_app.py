#!/usr/bin/env python3
"""Modify app.py in place to add login-required checks for deep analysis routes."""
import re

PATH = '/www/wwwroot/aifupang.com/research/app.py'

with open(PATH, 'r') as f:
    content = f.read()

# 1. Add imports
content = content.replace(
    'from flask import Flask, render_template, jsonify, request',
    'from flask import Flask, render_template, jsonify, request, g'
)
content = content.replace(
    'from functools import wraps\n',
    '')
# Add after json import
content = content.replace(
    'import json\n',
    'import json\nimport os\nimport re as re_mod\nfrom functools import wraps\n'
)

# 2. Add PHP session check after AI_MODEL line
session_code = '''
# ============ PHP Session 检查 ============
def check_php_session():
    """读取PHP session，返回 (logged_in, username)"""
    sess_id = request.cookies.get('PHPSESSID', '')
    if not sess_id:
        return False, None
    sess_file = f'/tmp/sess_{sess_id}'
    if not os.path.exists(sess_file):
        return False, None
    try:
        with open(sess_file, 'r', encoding='utf-8', errors='ignore') as f:
            scontent = f.read()
        if 'user|' in scontent:
            m = re_mod.search(r'user\\|a:\\d+:\\{.*?s:8:"username";s:\\d+:"([^"]+)"', scontent)
            username = m.group(1) if m else '用户'
            return True, username
    except:
        pass
    return False, None


@app.before_request
def before_request():
    logged_in, username = check_php_session()
    g.logged_in = logged_in
    g.username = username


def login_required(f):
    """装饰器：需要登录的API"""
    @wraps(f)
    def wrapper(*args, **kwargs):
        if not g.logged_in:
            return jsonify({'success': False, 'error': '请先登录', 'need_login': True}), 401
        return f(*args, **kwargs)
    return wrapper

'''
content = content.replace('AI_MODEL = "agnes-2.0-flash"\n', 'AI_MODEL = "agnes-2.0-flash"\n' + session_code)

# 3. Protect deep analysis routes
routes_to_protect = [
    'fundflow120',
    'fundmargin',
    'financial',
    'announcement',
    'holder',
    'dividend',
    'reports',
    'ai_chat',
    'ai_analyze',
    'ai_macro',
]

for route_name in routes_to_protect:
    # Find the route definition and add @login_required before def
    pattern = f"def {route_name}("
    replacement = f"@login_required\ndef {route_name}("
    content = content.replace(pattern, replacement)

# 4. Pass login state to template
old_template = "return render_template('index.html', hot_stocks=hot_stocks, hot_sectors=hot_sectors)"
new_template = "return render_template('index.html', hot_stocks=hot_stocks, hot_sectors=hot_sectors, logged_in=g.logged_in, username=g.username)"
content = content.replace(old_template, new_template)

with open(PATH, 'w') as f:
    f.write(content)

print("app.py modified successfully")

# Quick validation
import ast
try:
    ast.parse(content)
    print("Syntax OK")
except SyntaxError as e:
    print(f"Syntax error: {e}")
