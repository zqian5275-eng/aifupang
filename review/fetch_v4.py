#!/usr/bin/env python3
"""Fetch Eastmoney data for 20260629, then run generate_report.py"""
import requests, time, os, sys, pandas as pd

TRADE_DATE = '20260629'
DATA_DIR = '/www/wwwroot/aifupang.com/review/data'
UA = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36"

def push2_get(fs, fields, fid='f3', pz=100, max_pages=50):
    all_data, total, err_count = [], None, 0
    for page in range(1, max_pages + 1):
        url = 'https://push2.eastmoney.com/api/qt/clist/get'
        params = {'pn': str(page), 'pz': str(pz), 'po': '1', 'np': '1', 'fltt': '2', 'invt': '2', 'fs': fs, 'fields': fields, 'fid': fid}
        try:
            r = requests.get(url, params=params, headers={'User-Agent': UA, 'Referer': 'https://quote.eastmoney.com/'}, timeout=30)
            d = r.json()
            items = d.get('data', {}).get('diff', [])
            if total is None: total = d.get('data', {}).get('total', 0)
            if not items: break
            all_data.extend(items)
            err_count = 0
            if len(all_data) >= total: break
            if page % 10 == 0: print(f"  Page {page}: {len(all_data)}/{total}")
            time.sleep(0.2)
        except Exception as e:
            err_count += 1
            print(f"  Page {page} err (consecutive={err_count}): {e}")
            if err_count >= 5:
                print("  Too many errors, stopping")
                break
            time.sleep(2)
    return all_data

def safe_float(v, default=0):
    try:
        if v is None or v == '-' or v == '': return default
        return float(v)
    except: return default

os.makedirs(DATA_DIR, exist_ok=True)

print("=== 板块数据 ===")
items = push2_get('m:90+t:2', 'f2,f3,f4,f5,f6,f12,f14,f20,f104,f105,f106,f124,f128,f140', pz=100, max_pages=10)
rows = []
for item in items:
    rows.append({'板块名称': item.get('f14',''), '板块代码': item.get('f12',''), '涨幅': item.get('f3',0), '涨跌额': item.get('f4',0), '成交量': item.get('f5',0), '成交额': item.get('f6',0), '总市值': item.get('f20',0), '涨家数': int(item.get('f104',0) or 0), '跌家数': int(item.get('f105',0) or 0), '平家数': int(item.get('f106',0) or 0), '领涨股': item.get('f128','--'), '领涨股代码': item.get('f140',''), '主力净量': item.get('f124',0)})
pd.DataFrame(rows).to_csv(f'{DATA_DIR}/{TRADE_DATE}板块数据.xls', sep='\t', index=False, encoding='gbk')
print(f"  {len(rows)} sectors")

print("=== 个股数据 ===")
items = push2_get('m:0+t:6,m:1+t:2,m:0+t:13', 'f2,f3,f4,f5,f6,f12,f14,f20,f21,f23,f24,f25,f115,f184,f100,f102', pz=100, max_pages=40)
rows = []
for item in items:
    pe = item.get('f115','-')
    pe_d = '--' if pe == '-' or pe is None else f"{float(pe):.2f}" if pe else '--'
    pb = item.get('f23','-')
    pb_d = '--' if pb == '-' or pb is None else f"{float(pb):.2f}" if pb else '--'
    rows.append({'代码': str(item.get('f12','')), '名称': item.get('f14',''), '涨幅': item.get('f3',0), '涨跌额': item.get('f4',0), '成交量': item.get('f5',0), '总金额': item.get('f6',0), '总市值': item.get('f20',0), '流通市值': item.get('f21',0), '市盈(动)': pe_d, '市净率': pb_d, '市盈TTM': item.get('f115','--'), '市盈静态': item.get('f24','--'), '所属概念': item.get('f100',''), '上市日期': item.get('f102',''), '主力净量': item.get('f184',0)})
pd.DataFrame(rows).to_csv(f'{DATA_DIR}/{TRADE_DATE}个股数据.xls', sep='\t', index=False, encoding='gbk')
print(f"  {len(rows)} stocks")

print("=== 板块资金 ===")
items = push2_get('m:90+t:2', 'f2,f3,f4,f12,f14,f124,f184', pz=100, max_pages=10)
rows = [{'板块名称': i.get('f14',''), '涨幅': i.get('f3',0), '主力净流入': i.get('f124',0)} for i in items]
pd.DataFrame(rows).to_csv(f'{DATA_DIR}/{TRADE_DATE}板块资金.xls', sep='\t', index=False, encoding='gbk')
print(f"  {len(rows)} rows")

print("=== 百日新高 ===")
items = push2_get('m:0+t:6,m:1+t:2', 'f2,f3,f12,f14,f6,f100', fid='f3', pz=100, max_pages=10)
print(f"  Got {len(items)} candidates, filtering >5%...")
rows = []
for i in items:
    pct = safe_float(i.get('f3'), 0)
    if pct > 5:
        rows.append({'代码': i.get('f12',''), '名称': i.get('f14',''), '涨幅': pct, '所属概念': i.get('f100',''), '上榜原因': '涨幅居前', '成交额': i.get('f6',0)})
pd.DataFrame(rows).to_csv(f'{DATA_DIR}/{TRADE_DATE}百日新高.xls', sep='\t', index=False, encoding='gbk')
print(f"  {len(rows)} high stocks")

print("\nDone!")
for f in sorted(os.listdir(DATA_DIR)):
    print(f"  {f} ({os.path.getsize(os.path.join(DATA_DIR, f)):,} bytes)")
