#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""TV Live Proxy - 从 B站直播获取流并转 HLS"""
import os, subprocess, threading, time, shutil, re
import requests

app = None
from flask import Flask, jsonify, Response

app = Flask(__name__)

CHANNELS = {
    "cctv1": {"name": "CCTV-1 综合", "room": 6, "group": "央视频道"},
    "cctv2": {"name": "CCTV-2 财经", "room": 25523911, "group": "央视频道"},
    "cctv3": {"name": "CCTV-3 综艺", "room": 25523922, "group": "央视频道"},
    "cctv4": {"name": "CCTV-4 中文国际", "room": 25523933, "group": "央视频道"},
    "cctv5": {"name": "CCTV-5 体育", "room": 25523944, "group": "央视频道"},
    "cctv5p": {"name": "CCTV-5+ 体育赛事", "room": 25523955, "group": "央视频道"},
    "cctv6": {"name": "CCTV-6 电影", "room": 25523966, "group": "央视频道"},
    "cctv7": {"name": "CCTV-7 军事农业", "room": 25523977, "group": "央视频道"},
    "cctv9": {"name": "CCTV-9 记录", "room": 25523988, "group": "央视频道"},
    "cctv10": {"name": "CCTV-10 科教", "room": 25523999, "group": "央视频道"},
    "cctv11": {"name": "CCTV-11 戏曲", "room": 25524000, "group": "央视频道"},
    "cctv12": {"name": "CCTV-12 社会与法", "room": 25524011, "group": "央视频道"},
    "cctv13": {"name": "CCTV-13 新闻", "room": 25524022, "group": "央视频道"},
    "cctv14": {"name": "CCTV-14 少儿", "room": 25524033, "group": "央视频道"},
    "cctv15": {"name": "CCTV-15 音乐", "room": 25524044, "group": "央视频道"},
    "cctv16": {"name": "CCTV-16 奥林匹克", "room": 25524055, "group": "央视频道"},
    "cctv17": {"name": "CCTV-17 农业农村", "room": 25524066, "group": "央视频道"},
    "hunan": {"name": "湖南卫视", "room": 25524077, "group": "卫视"},
    "zhejiang": {"name": "浙江卫视", "room": 25524088, "group": "卫视"},
    "jiangsu": {"name": "江苏卫视", "room": 25524099, "group": "卫视"},
    "beijing": {"name": "北京卫视", "room": 25524111, "group": "卫视"},
    "guangdong": {"name": "广东卫视", "room": 25524122, "group": "卫视"},
    "shenzhen": {"name": "深圳卫视", "room": 25524133, "group": "卫视"},
    "dongfang": {"name": "东方卫视", "room": 25524100, "group": "卫视"},
    "anhui": {"name": "安徽卫视", "room": 25524144, "group": "卫视"},
    "shandong": {"name": "山东卫视", "room": 25524155, "group": "卫视"},
    "jiangxi": {"name": "江西卫视", "room": 25524166, "group": "卫视"},
    "sichuan": {"name": "四川卫视", "room": 25524177, "group": "卫视"},
    "henan": {"name": "河南卫视", "room": 25524188, "group": "卫视"},
    "chongqing": {"name": "重庆卫视", "room": 25524199, "group": "卫视"},
    "tj": {"name": "天津卫视", "room": 25524200, "group": "卫视"},
    "hebei": {"name": "河北卫视", "room": 25524211, "group": "卫视"},
    "hubei": {"name": "湖北卫视", "room": 25524222, "group": "卫视"},
    "liaoning": {"name": "辽宁卫视", "room": 25524233, "group": "卫视"},
    "fujian": {"name": "福建东南卫视", "room": 25524244, "group": "卫视"},
    "guangxi": {"name": "广西卫视", "room": 25524255, "group": "卫视"},
    "shanxi": {"name": "山西卫视", "room": 25524266, "group": "卫视"},
    "inner": {"name": "内蒙古卫视", "room": 25524277, "group": "卫视"},
    "ningxia": {"name": "宁夏卫视", "room": 25524288, "group": "卫视"},
    "xinjiang": {"name": "新疆卫视", "room": 25524299, "group": "卫视"},
    "xizang": {"name": "西藏卫视", "room": 25524300, "group": "卫视"},
    "heilongjiang": {"name": "黑龙江卫视", "room": 25524311, "group": "卫视"},
    "jilin": {"name": "吉林卫视", "room": 25524322, "group": "卫视"},
    "gansu": {"name": "甘肃卫视", "room": 25524333, "group": "卫视"},
    "yunnan": {"name": "云南卫视", "room": 25524344, "group": "卫视"},
    "guizhou": {"name": "贵州卫视", "room": 25524355, "group": "卫视"},
    "hainan": {"name": "海南卫视", "room": 25524366, "group": "卫视"},
    "shaanxi": {"name": "陕西卫视", "room": 25524377, "group": "卫视"},
}

stream_status = {}
STREAM_TIMEOUT = 600
HEADERS = {
    "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
    "Referer": "https://live.bilibili.com/",
    "Origin": "https://live.bilibili.com",
    "Accept": "*/*",
}


def get_stream_info(room_id):
    """从 B站直播 API 获取流信息"""
    try:
        api = f"https://api.live.bilibili.com/room/v1/Room/get_info?room_id={room_id}"
        resp = requests.get(api, timeout=10, headers=HEADERS)
        data = resp.json()
        
        if data.get("code") != 0:
            return None
        
        live_status = data["data"].get("live_status", 0)
        if live_status != 1:
            return None
        
        # 获取流地址
        url = f"https://api.live.bilibili.com/room/v1/Room/playUrl?room_id={room_id}&platform=web&quality=4"
        resp2 = requests.get(url, timeout=10, headers=HEADERS)
        play_data = resp2.json()
        
        if play_data.get("code") != 0:
            return None
        
        playurl = play_data["data"].get("playurl", [])
        if not playurl:
            return None
        
        for stream in playurl:
            for fmt in stream.get("format", []):
                for codec in fmt.get("codec", []):
                    if codec.get("url"):
                        return {
                            "url": codec["url"],
                            "backup_url": codec.get("backup_url", [""])[0],
                            "codec": codec.get("codec_type"),
                            "protocol": fmt.get("format_name", ""),
                        }
    except Exception as e:
        print(f"get_stream_info error for room {room_id}: {e}")
    return None


def convert_to_hls(flv_url, hls_dir):
    """FLV 转 HLS"""
    m3u8_path = os.path.join(hls_dir, 'index.m3u8')
    ts_pattern = os.path.join(hls_dir, 'seg_%03d.ts')
    cmd = [
        'ffmpeg', '-y', '-i', flv_url,
        '-c', 'copy', '-f', 'hls',
        '-hls_time', 5, '-hls_list_size', 5,
        '-hls_segment_filename', ts_pattern,
        m3u8_path,
    ]
    try:
        subprocess.run(cmd, timeout=STREAM_TIMEOUT,
                       stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    except Exception as e:
        print(f"FFmpeg error: {e}")
    finally:
        if os.path.exists(hls_dir):
            shutil.rmtree(hls_dir)


@app.route('/api/channels')
def list_channels():
    result = []
    for cid, info in CHANNELS.items():
        result.append({'id': cid, 'name': info['name'], 'group': info['group']})
    return jsonify(result)


@app.route('/api/play/<channel_id>')
def play(channel_id):
    if channel_id not in CHANNELS:
        return jsonify({'error': '频道不存在'}), 404
    
    channel = CHANNELS[channel_id]
    
    if channel_id in stream_status:
        if stream_status[channel_id].get('expires', 0) > time.time():
            return jsonify({'success': True, 'hls_url': f'/hls/{channel_id}/index.m3u8'})
    
    room_id = channel['room']
    print(f"Play {channel_id} (room {room_id})...")
    
    stream_info = get_stream_info(room_id)
    if not stream_info:
        return jsonify({'error': '频道未开播或获取流失败'}), 500
    
    flv_url = stream_info['url'] or stream_info.get('backup_url', '')
    if not flv_url:
        return jsonify({'error': '未获取到流地址'}), 500
    
    print(f"Got stream: {flv_url[:80]}... (protocol: {stream_info.get('protocol')})")
    
    hls_dir = f'/tmp/hls/{channel_id}'
    os.makedirs(hls_dir, exist_ok=True)
    
    def worker():
        try:
            convert_to_hls(flv_url, hls_dir)
        except Exception as e:
            print(f"Worker error for {channel_id}: {e}")
        finally:
            if channel_id in stream_status:
                del stream_status[channel_id]
    
    threading.Thread(target=worker, daemon=True).start()
    stream_status[channel_id] = {'expires': time.time() + STREAM_TIMEOUT}
    return jsonify({'success': True, 'hls_url': f'/hls/{channel_id}/index.m3u8'})


if __name__ == '__main__':
    if os.path.exists('/tmp/hls'):
        shutil.rmtree('/tmp/hls')
    os.makedirs('/tmp/hls', exist_ok=True)
    app.run(host='127.0.0.1', port=8765, debug=False)
