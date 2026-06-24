#!/usr/bin/env python3.11
"""TTS audio generation service via Microsoft Edge TTS.
Listens on port 8766, accepts POST {text, voice}, returns MP3 audio.
"""

import json, os, sys, time, uuid, asyncio
from http.server import HTTPServer, BaseHTTPRequestHandler
from urllib.parse import urlparse, parse_qs
import edge_tts

PORT = 8766
OUTPUT_DIR = "/www/wwwroot/aifupang.com/studio/tts/audio"
os.makedirs(OUTPUT_DIR, exist_ok=True)

VOICES = {
    "zh-CN-XiaoxiaoNeural": "晓晓（女·温柔）",
    "zh-CN-YunxiNeural": "云希（男·叙事）",
    "zh-CN-YunjianNeural": "云健（男·运动）",
    "zh-CN-XiaoyiNeural": "晓伊（女·搞怪）",
    "zh-CN-YunyangNeural": "云扬（男·新闻）",
    "zh-CN-XiaochenNeural": "晓辰（女·平静）",
    "zh-CN-XiaohanNeural": "晓涵（女·自信）",
    "zh-CN-XiaomengNeural": "晓梦（女·活泼）",
    "zh-CN-XiaomoNeural": "晓墨（女·清晰）",
    "zh-CN-XiaoqiuNeural": "晓秋（女·温和）",
    "zh-CN-XiaoruiNeural": "晓睿（女·成熟）",
    "zh-CN-XiaoshuangNeural": "晓双（女·可爱）",
    "zh-CN-XiaoxuanNeural": "晓萱（女·自信）",
    "zh-CN-XiaoyanNeural": "晓颜（女·甜美）",
    "zh-CN-XiaoyouNeural": "晓悠（女·舒缓）",
    "zh-CN-XiaozhenNeural": "晓臻（女·温柔）",
    "zh-CN-YunfengNeural": "云峰（男·沉稳）",
    "zh-CN-YunhaoNeural": "云浩（男·明亮）",
    "zh-CN-YunxiaNeural": "云夏（男·亲切）",
    "zh-CN-YunyeNeural": "云野（男·自然）",
    "zh-CN-YunzeNeural": "云泽（男·深沉）",
}

DEFAULT_VOICE = "zh-CN-XiaoxiaoNeural"

def clean_old_files(max_age_hours=24):
    now = time.time()
    for f in os.listdir(OUTPUT_DIR):
        fp = os.path.join(OUTPUT_DIR, f)
        if os.path.isfile(fp) and f.endswith('.mp3'):
            if now - os.path.getmtime(fp) > max_age_hours * 3600:
                try: os.remove(fp)
                except OSError: pass

class TTSHandler(BaseHTTPRequestHandler):
    def _json(self, data, status=200):
        body = json.dumps(data, ensure_ascii=False).encode('utf-8')
        self.send_response(status)
        self.send_header('Content-Type', 'application/json; charset=utf-8')
        self.send_header('Access-Control-Allow-Origin', '*')
        self.send_header('Content-Length', str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def _audio(self, filepath):
        with open(filepath, 'rb') as f: data = f.read()
        self.send_response(200)
        self.send_header('Content-Type', 'audio/mpeg')
        self.send_header('Access-Control-Allow-Origin', '*')
        self.send_header('Content-Length', str(len(data)))
        self.send_header('Cache-Control', 'public, max-age=86400')
        self.end_headers()
        self.wfile.write(data)

    def do_OPTIONS(self):
        self.send_response(200)
        self.send_header('Access-Control-Allow-Origin', '*')
        self.send_header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
        self.send_header('Access-Control-Allow-Headers', 'Content-Type')
        self.end_headers()

    def do_GET(self):
        p = urlparse(self.path)
        if p.path == '/voices':
            self._json(VOICES); return
        if p.path == '/health':
            self._json({'status': 'ok'}); return
        if p.path.startswith('/audio/'):
            fn = os.path.basename(p.path)
            fp = os.path.join(OUTPUT_DIR, fn)
            if os.path.isfile(fp):
                self._audio(fp); return
        self._json({'error': 'not found'}, 404)

    def do_POST(self):
        length = int(self.headers.get('Content-Length', 0))
        raw = self.rfile.read(length);
        try: body = raw.decode("utf-8")
        except UnicodeDecodeError: body = raw.decode("gbk")
        try: data = json.loads(body)
        except json.JSONDecodeError:
            self._json({'error': 'invalid json'}, 400); return
        text = (data.get('text') or '').strip()
        voice = data.get('voice', DEFAULT_VOICE)
        if not text:
            self._json({'error': 'empty text'}, 400); return
        if len(text) > 5000:
            self._json({'error': 'too long'}, 400); return
        fn = f"{uuid.uuid4().hex}.mp3"
        fp = os.path.join(OUTPUT_DIR, fn)
        try:
            async def gen():
                c = edge_tts.Communicate(text=text, voice=voice)
                await c.save(fp)
            asyncio.run(gen())
        except Exception as e:
            self._json({'error': str(e)}, 500); return
        try: clean_old_files()
        except: pass
        self._json({'success': True, 'url': f'/studio/tts/api/audio/{fn}', 'filename': fn, 'voice': voice})

    def log_message(self, format, *args): pass

if __name__ == '__main__':
    server = HTTPServer(('127.0.0.1', PORT), TTSHandler)
    print(f"TTS server on 127.0.0.1:{PORT}", flush=True)
    server.serve_forever()
