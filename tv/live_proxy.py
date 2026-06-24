#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""TV Live Proxy v5 - M3U加载+硬编码备用源+CCTV+置顶"""
import os, json, re
from flask import Flask, jsonify

app = Flask(__name__)

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))

def parse_m3u(filepath):
    """解析M3U文件，返回频道列表"""
    channels = []
    if not os.path.exists(filepath):
        return channels
    with open(filepath, 'r', encoding='utf-8') as f:
        lines = f.readlines()

    i = 0
    while i < len(lines):
        line = lines[i].strip()
        if line.startswith('#EXTINF'):
            m = re.search(r',(.+)$', line)
            name = m.group(1).strip() if m else ''
            logo_m = re.search(r'tvg-logo="([^"]*)"', line)
            logo = logo_m.group(1) if logo_m else ''
            group_m = re.search(r'group-title="([^"]*)"', line)
            group = group_m.group(1) if group_m else ''
            url = ''
            if i + 1 < len(lines) and not lines[i + 1].startswith('#'):
                url = lines[i + 1].strip().split('$')[0].strip()
            cid = re.sub(r'[^A-Za-z0-9\u4e00-\u9fff]', '', name).lower()[:30]
            if not cid:
                cid = 'ch_%d' % len(channels)
            base_cid = cid
            counter = 0
            while any(ch['id'] == cid for ch in channels):
                counter += 1
                cid = '%s_%d' % (base_cid, counter)

            channels.append({
                'id': cid,
                'name': name,
                'url': url,
                'group': group,
                'logo': logo
            })
            i += 2
        else:
            i += 1
    return channels


def load_all_channels():
    """加载所有频道：M3U文件 + 硬编码备用源，CCTV+置顶"""
    all_channels = []
    seen_ids = set()

    # === 置顶：CCTV+综合、CCTV+新闻、CCTV-9、浙江新闻、CGNTV、浙江国际、浙江经济 ===
    top_channels = [
        {"id": "cctvplus1", "name": "CCTV+ 综合", "url": "https://cd-live-stream.news.cctvplus.com/live/smil:CHANNEL1.smil/playlist.m3u8", "group": "央视官方"},
        {"id": "cctvplus2", "name": "CCTV+ 新闻", "url": "https://cd-live-stream.news.cctvplus.com/live/smil:CHANNEL2.smil/playlist.m3u8", "group": "央视官方"},
        {"id": "cctv9576i", "name": "CCTV-9 (576i)", "url": "https://xykt-fix.github.io/Y77.m3u8", "group": "央视官方"},
        {"id": "zjnews", "name": "浙江新闻", "url": "https://ali-m-l.cztv.com/channels/lantian/channel007/1080p.m3u8", "group": "央视官方"},
        {"id": "cgntvchinese1080p", "name": "CGNTV Chinese (1080p)", "url": "https://d3e05csss9c272.cloudfront.net/out/v1/f0bf71c57581470fb9379f603e8f5d83/CGNWebLiveCN.m3u8", "group": "央视官方"},
        {"id": "zjguoji", "name": "浙江国际", "url": "https://ali-m-l.cztv.com/channels/lantian/channel010/1080p.m3u8", "group": "央视官方"},
        {"id": "zjjingji", "name": "浙江经济", "url": "https://ali-m-l.cztv.com/channels/lantian/channel003/1080p.m3u8", "group": "央视官方"},
    ]
    for ch in top_channels:
        all_channels.append(ch)
        seen_ids.add(ch['id'])

    # 1. 加载扩展M3U文件
    ext_m3u = os.path.join(SCRIPT_DIR, 'live_extended.m3u')
    ext_chs = parse_m3u(ext_m3u)
    for ch in ext_chs:
        all_channels.append(ch)
        seen_ids.add(ch['id'])

    # 2. 其他硬编码备用源
    hardcoded = [
        {"id": "beijing", "name": "北京卫视", "url": "http://go.bkpcp.top/mg/bjws", "group": "卫视"},
        {"id": "abn", "name": "ABN China", "url": "https://mediaserver.abnvideos.com/streams/abnchina.m3u8", "group": "海外中文"},
        {"id": "angel", "name": "Angel TV 中文", "url": "https://janya-digimix.akamaized.net/vglive-sk-999451/chinese/ngrp:angelchinese_all/playlist.m3u8", "group": "海外中文"},
        {"id": "cctv1_se", "name": "CCTV-1 (海外源)", "url": "http://69.30.245.50/live/cctv1.m3u8", "group": "海外CCTV"},
        {"id": "cctv2_se", "name": "CCTV-2 (海外源)", "url": "http://74.91.26.218:82/live/cctv2hd.m3u8", "group": "海外CCTV"},
        {"id": "cctv3_se", "name": "CCTV-3 (海外源)", "url": "http://74.91.26.218:82/live/cctv3hd.m3u8", "group": "海外CCTV"},
        {"id": "cctv6_se", "name": "CCTV-6 (海外源)", "url": "http://198.204.240.250:82/live/cctv6.m3u8", "group": "海外CCTV"},
        {"id": "cctv7_se", "name": "CCTV-7 (海外源)", "url": "http://74.91.26.218:82/live/cctv7hd.m3u8", "group": "海外CCTV"},
    ]
    for ch in hardcoded:
        if ch['id'] not in seen_ids:
            all_channels.append(ch)
            seen_ids.add(ch['id'])

    return all_channels


CHANNELS = load_all_channels()

@app.route('/api/channels')
def list_channels():
    result = []
    for ch in CHANNELS:
        result.append({
            'id': ch['id'],
            'name': ch['name'],
            'url': ch.get('url', ''),
            'group': ch.get('group', '')
        })
    return jsonify(result)

@app.route('/api/channels/grouped')
def list_channels_grouped():
    groups = {}
    for ch in CHANNELS:
        g = ch.get('group', '未分类')
        if g not in groups:
            groups[g] = []
        groups[g].append({
            'id': ch['id'],
            'name': ch['name'],
            'url': ch.get('url', ''),
        })
    return jsonify(groups)

@app.route('/api/stats')
def stats():
    groups = {}
    for ch in CHANNELS:
        g = ch.get('group', '未分类')
        groups[g] = groups.get(g, 0) + 1
    hc_count = 0
    for c in CHANNELS:
        cid = c.get('id', '')
        if cid.startswith('cctvplus') or cid.startswith('beijing') or cid.startswith('abn') or cid.startswith('angel') or cid.startswith('cctv'):
            hc_count += 1
    return jsonify({
        'total': len(CHANNELS),
        'groups': groups,
        'sources': {
            'm3u_extended': os.path.exists(os.path.join(SCRIPT_DIR, 'live_extended.m3u')),
            'hardcoded': hc_count
        }
    })

@app.route('/api/play/<channel_id>')
def play(channel_id):
    for ch in CHANNELS:
        if ch['id'] == channel_id:
            return jsonify({'success': True, 'url': ch['url'], 'name': ch['name']})
    return jsonify({'error': '频道不存在'}), 404

if __name__ == '__main__':
    print('加载了 %d 个频道' % len(CHANNELS))
    app.run(host='0.0.0.0', port=8765, debug=False)
