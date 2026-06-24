#!/usr/bin/env python3.11
"""Video pipeline server: script -> storyboard -> AI video segments -> concat.
Listens on port 8768.

Flow:
  1. Collect character/scene reference images + text descriptions
  2. Segment 1: generate with reference images (img2video or text)
  3. Extract mid-frame from segment 1
  4. Segments 2-N: parallel img2video (keyframe + character/scene refs)
  5. Concat all segments into final video
"""
import json, os, time, uuid, threading, shutil, subprocess
from http.server import HTTPServer, BaseHTTPRequestHandler
from urllib.parse import urlparse
import requests as req
import urllib3
urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)
from rembg import remove, new_session
from PIL import Image
from io import BytesIO
import numpy as np

PORT = 8768
OUTPUT_DIR = "/www/wwwroot/aifupang.com/studio/video-pipeline/output"
FRAMES_DIR = "/www/wwwroot/aifupang.com/studio/video-pipeline/frames"
TEMP_DIR = "/tmp/video-pipeline"
VIDEO_API = "https://127.0.0.1/studio/video/api.php"
FRAMES_BASE_URL = "https://aifupang.com/studio/video-pipeline/frames"
SITE_BASE = "https://aifupang.com"
IMAGE_API = "https://127.0.0.1/studio/image/api.php"

os.makedirs(OUTPUT_DIR, exist_ok=True)
os.makedirs(FRAMES_DIR, exist_ok=True)
os.makedirs(TEMP_DIR, exist_ok=True)

_jobs = {}


def _video_create(prompt, mode="text", images=None, num_frames=81, aspect_ratio="16:9"):
    payload = {
        "prompt": prompt,
        "mode": mode,
        "num_frames": num_frames,
        "frame_rate": 24,
        "aspect_ratio": aspect_ratio,
    }
    if images:
        payload["images"] = images

    print(f"[DEBUG] _video_create images: {images}", flush=True)
    with open("/tmp/vp_debug.log", "a") as _f:
        _f.write(f"VIDEO_CREATE mode={mode} images={images}\n")
    r = req.post(f"{VIDEO_API}?action=create", json=payload, timeout=120,
                   headers={"Host": "aifupang.com"}, verify=False)
    try:
        data = r.json()
    except Exception:
        raise Exception(f"Video API returned non-JSON (HTTP {r.status_code}): {r.text[:200]}")
    if not data.get("success"):
        raise Exception(f"Video create failed: {data.get('error', 'unknown')}")
    return data.get("video_id") or data.get("task_id")


def _video_poll(task_id, timeout=600):
    deadline = time.time() + timeout
    while time.time() < deadline:
        r = req.get(f"{VIDEO_API}?action=query&video_id={task_id}", timeout=30,
                   headers={"Host": "aifupang.com"}, verify=False)
        try:
            data = r.json()
        except Exception:
            time.sleep(3)
            continue
        status = data.get("status", "")
        if status == "completed":
            url = data.get("video_url")
            if not url:
                raise Exception("Video completed but returned empty URL")
            return url
        elif status == "failed":
            raise Exception(f"Video generation failed: {data.get('error', 'unknown')}")
        time.sleep(5)
    raise Exception("Video generation timed out")


def _download(url, dest):
    r = req.get(url, timeout=120, stream=True)
    r.raise_for_status()
    with open(dest, "wb") as f:
        for chunk in r.iter_content(8192):
            f.write(chunk)
# ---- Background Removal ----
_rembg_session = None

def _get_rembg_session():
    global _rembg_session
    if _rembg_session is None:
        _rembg_session = new_session('u2netp')
    return _rembg_session

def _remove_bg(image_url):
    """Download image, remove background, save as PNG, return local URL."""
    import tempfile
    # Download image
    r = req.get(image_url, timeout=60, stream=True)
    r.raise_for_status()
    img = Image.open(BytesIO(r.content)).convert('RGB')
    
    # Remove background
    sess = _get_rembg_session()
    result = remove(img, session=sess)
    
    # Save to frames directory
    fid = uuid.uuid4().hex[:8]
    out_path = os.path.join(FRAMES_DIR, f"nobg_{fid}.png")
    result.save(out_path, 'PNG')
    
    return f"{FRAMES_BASE_URL}/nobg_{fid}.png"


def _image_composite(char_desc, scene_desc, scene_img_url, segment_prompt):
    """Generate a composite image: character in scene, using scene as base."""
    prompt = f"{char_desc}。在{scene_desc}中。{segment_prompt}"
    payload = {
        "prompt": prompt,
        "mode": "image",
        "size": "1280x704",
        "images": [scene_img_url],
    }
    r = req.post(f"{IMAGE_API}", json=payload, timeout=180,
                   headers={"Host": "aifupang.com"}, verify=False)
    data = r.json()
    if not data.get("success"):
        raise Exception(f"Composite image failed: {data.get('error', 'unknown')}")
    img_url = data.get("url")
    if not img_url:
        raise Exception("Composite image returned empty URL")
    return img_url


def _extract_mid_frame(video_path, output_path):
    result = subprocess.run(
        ["ffprobe", "-v", "error", "-show_entries", "format=duration",
         "-of", "default=noprint_wrappers=1:nokey=1", video_path],
        capture_output=True, text=True, timeout=10,
    )
    duration = float(result.stdout.strip())
    mid = max(0.5, duration / 2)

    subprocess.run(
        ["ffmpeg", "-y", "-ss", str(mid), "-i", video_path,
         "-vframes", "1", "-q:v", "3", output_path],
        check=True, capture_output=True, timeout=30,
    )


def _concat(video_paths, output_path):
    list_file = os.path.join(TEMP_DIR, f"clist_{uuid.uuid4().hex[:8]}.txt")
    with open(list_file, "w") as f:
        for p in video_paths:
            f.write(f"file '{p}'\n")
    subprocess.run(
        ["ffmpeg", "-y", "-f", "concat", "-safe", "0", "-i", list_file,
         "-c", "copy", "-movflags", "+faststart", output_path],
        check=True, capture_output=True, timeout=120,
    )


def _build_prompt_and_refs(character_desc, characters, scenes):
    """Build a combined prompt prefix and a flat list of reference image URLs."""
    prompt_parts = []
    ref_urls = []

    # --- Characters ---
    if characters:
        for ch in characters:
            if not isinstance(ch, dict):
                continue
            name = ch.get("name", "").strip()
            desc = ch.get("description", "").strip()
            images = ch.get("images", [])
            if not isinstance(images, list):
                images = []

            if name and desc:
                prompt_parts.append(f"角色「{name}」：{desc}")
            elif desc:
                prompt_parts.append(desc)

            for u in images:
                if u and isinstance(u, str) and u:
                    # Make relative URLs absolute
                    if u.startswith("/"):
                        u = SITE_BASE + u
                    # Remove background from character images
                    try:
                        u_clean = _remove_bg(u)
                        ref_urls.append(u_clean)
                    except Exception as e:
                        print(f"BG removal failed for {u[:50]}: {e}, using original")
                        ref_urls.append(u)

    # --- Scenes ---
    if scenes:
        for sc in scenes:
            if not isinstance(sc, dict):
                continue
            name = sc.get("name", "").strip()
            desc = sc.get("description", "").strip()
            img = sc.get("image", "").strip()

            if name and desc:
                prompt_parts.append(f"场景「{name}」：{desc}")
            elif desc:
                prompt_parts.append(desc)

            if img:
                # Make relative URLs absolute for Agnes API
                if img.startswith("/"):
                    img = SITE_BASE + img
                ref_urls.append(img)

    # Fallback: old-style flat character string
    if not prompt_parts and character_desc:
        prompt_parts.append(character_desc)

    prefix = "。".join(prompt_parts) + "。" if prompt_parts else ""

    # Add composition instruction: place character IN scene, ignore character bg
    if characters and scenes and ref_urls:
        prefix = "将角色放置在场景环境中，角色外观参考角色图（忽略角色原图背景），环境参考场景图。" + prefix

    # Cap reference images at 5 (Agnes API practical limit)
    ref_urls = ref_urls[:5]

    return prefix, ref_urls


def _choose_mode(images):
    """images: list of URL strings. Returns (mode, images_or_None)."""
    if len(images) >= 2:
        return "multi", images
    elif len(images) == 1:
        return "image", images
    else:
        return "text", None


# ---------------------------------------------------------------------------
#  Pipeline runner
# ---------------------------------------------------------------------------
def run_pipeline(job_id, segments, character="", characters=None, scenes=None, num_frames=121, aspect_ratio="16:9"):
    with open("/tmp/vp_debug.log", "a") as _f:
        _f.write(f"RUN_PIPELINE START job={job_id} chars={bool(characters)} scenes={bool(scenes)}\n")
    job = _jobs[job_id]
    try:
        total = len(segments)
        job["total"] = total
        job["status"] = "processing"
        video_segments = []

        # ---- Build assets ----
        prompt_prefix, base_refs = _build_prompt_and_refs(character, characters, scenes)

        # ---- Segment 1 (blocking, may use ref images) ----
        job["step"] = "video"
        job["current"] = 1

        p = segments[0].get("image_prompt", "").strip()
        if prompt_prefix:
            p = f"{prompt_prefix}{p}"

        mode1, imgs1 = _choose_mode(base_refs)
        tid = _video_create(p, mode=mode1, images=imgs1, num_frames=num_frames, aspect_ratio=aspect_ratio)
        vurl = _video_poll(tid)
        p0 = os.path.join(TEMP_DIR, f"{job_id}_seg0.mp4")
        _download(vurl, p0)
        video_segments.append(p0)

        # ---- Extract keyframe from segment 1 ----
        job["step"] = "extract_frame"
        fid = uuid.uuid4().hex[:8]
        fpath = os.path.join(FRAMES_DIR, f"{fid}.jpg")
        _extract_mid_frame(p0, fpath)
        keyframe_url = f"{FRAMES_BASE_URL}/{fid}.jpg"

        # ---- Segments 2..N: parallel img2video ----
        if total > 1:
            job["step"] = "video"
            job["current"] = 2

            remaining = segments[1:]
            n = len(remaining)
            results = [None] * n
            errors = [None] * n

            def _gen_one(idx, seg):
                try:
                    p2 = seg.get("image_prompt", "").strip()
                    if prompt_prefix:
                        p2 = f"{prompt_prefix}{p2}"

                    # Combine base refs + keyframe
                    seg_imgs = list(base_refs)
                    seg_imgs.append(keyframe_url)

                    mode, imgs = _choose_mode(seg_imgs)
                    tid2 = _video_create(p2, mode=mode, images=imgs, num_frames=num_frames, aspect_ratio=aspect_ratio)
                    vurl2 = _video_poll(tid2)
                    sp = os.path.join(TEMP_DIR, f"{job_id}_seg{idx+1}.mp4")
                    _download(vurl2, sp)
                    results[idx] = sp
                except Exception as e:
                    errors[idx] = str(e)

            threads = []
            for i, seg in enumerate(remaining):
                t = threading.Thread(target=_gen_one, args=(i, seg))
                t.start()
                threads.append(t)
            for t in threads:
                t.join()

            for i, err in enumerate(errors):
                if err:
                    raise Exception(f"第{i+2}段生成失败: {err}")
            for p in results:
                if p:
                    video_segments.append(p)

        # ---- Concat ----
        job["step"] = "concat"
        out_name = f"{job_id}.mp4"
        out_path = os.path.join(OUTPUT_DIR, out_name)
        if len(video_segments) == 1:
            shutil.copy(video_segments[0], out_path)
        else:
            _concat(video_segments, out_path)

        # Clean temp files
        for p in video_segments:
            try:
                os.remove(p)
            except Exception:
                pass
        try:
            os.remove(fpath)
        except Exception:
            pass

        job["status"] = "completed"
        job["url"] = f"/studio/video-pipeline/output/{out_name}"
        job["step"] = "done"

    except Exception as e:
        job["status"] = "failed"
        job["error"] = str(e)

    # Housekeeping: remove >24h old output & frames
    try:
        now = time.time()
        for d in (OUTPUT_DIR, FRAMES_DIR):
            for fn in os.listdir(d):
                fp = os.path.join(d, fn)
                if os.path.isfile(fp) and now - os.path.getmtime(fp) > 86400:
                    os.remove(fp)
    except Exception:
        pass


# ---------------------------------------------------------------------------
# HTTP handler
# ---------------------------------------------------------------------------
class Handler(BaseHTTPRequestHandler):

    def _json(self, data, status=200):
        body = json.dumps(data, ensure_ascii=False).encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Access-Control-Allow-Origin", "*")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def do_OPTIONS(self):
        self.send_response(200)
        self.send_header("Access-Control-Allow-Origin", "*")
        self.send_header("Access-Control-Allow-Methods", "GET,POST,OPTIONS")
        self.send_header("Access-Control-Allow-Headers", "Content-Type")
        self.end_headers()

    def do_GET(self):
        p = urlparse(self.path)
        if p.path == "/health":
            self._json({"status": "ok"})
            return
        if p.path.startswith("/progress/"):
            jid = os.path.basename(p.path)
            job = _jobs.get(jid)
            if job:
                self._json(job)
            else:
                self._json({"error": "not found"}, 404)
            return
        self._json({"error": "not found"}, 404)

    def do_POST(self):
        length = int(self.headers.get("Content-Length", 0))
        body = self.rfile.read(length)
        try:
            data = json.loads(body.decode("utf-8"))
        except Exception:
            try:
                data = json.loads(body.decode("gbk"))
            except Exception:
                self._json({"error": "invalid json"}, 400)
                return

        segments = data.get("segments", [])
        character = data.get("character", "")
        characters = data.get("characters", None)
        scenes = data.get("scenes", None)
        duration = int(data.get("duration", 5))
        aspect_ratio = data.get("aspect_ratio", "16:9")

        # Map seconds to num_frames (Agnes API valid frame count)
        DURATION_MAP = {3:73, 5:121, 8:193, 10:241, 12:289, 15:361, 18:409}
        num_frames = DURATION_MAP.get(duration, DURATION_MAP.get(min(DURATION_MAP.keys(), key=lambda k: abs(k-duration)), 121))

        if not segments or not isinstance(segments, list):
            self._json({"error": "segments required"}, 400)
            return

        job_id = uuid.uuid4().hex[:12]
        _jobs[job_id] = {
            "id": job_id,
            "status": "starting",
            "total": 0,
            "current": 0,
            "step": "",
            "error": None,
            "url": None,
        }

        for k in list(_jobs.keys()):
            if _jobs[k]["status"] in ("completed", "failed"):
                try:
                    del _jobs[k]
                except Exception:
                    pass

        t = threading.Thread(
            target=run_pipeline,
            args=(job_id, segments, character, characters, scenes, num_frames, aspect_ratio),
            daemon=True,
        )
        t.start()
        self._json({"success": True, "job_id": job_id})

    def log_message(self, format, *args):
        pass


if __name__ == "__main__":
    server = HTTPServer(("127.0.0.1", PORT), Handler)
    print(f"Video pipeline server on 127.0.0.1:{PORT}", flush=True)
    server.serve_forever()
