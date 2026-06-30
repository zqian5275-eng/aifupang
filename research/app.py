1|1|from flask import Flask, render_template, jsonify, request, g
2|2|import stock_data
3|3|import traceback
4|4|import requests
5|5|import json
6|6|import os
7|7|import re
8|8|from functools import wraps
9|9|
10|10|app = Flask(__name__)
11|11|
12|12|# ============ AI 配置 ============
13|13|AI_API_KEY="sk-72e...2abf"
14|14|AI_API_KEY="sk-7...your-key"
15|15|AI_API_KEY="sk-72e...2abf"
16|16|AI_MODEL = "agnes-2.0-flash"
17|17|
18|18|# ============ PHP Session 检查 ============
19|19|def check_php_session():
20|20|    """读取PHP session，返回 (logged_in, username)"""
21|21|    sess_id = request.cookies.get('PHPSESSID', '')
22|22|    if not sess_id:
23|23|        return False, None
24|24|    sess_file = f'/tmp/sess_{sess_id}'
25|25|    if not os.path.exists(sess_file):
26|26|        return False, None
27|27|    try:
28|28|        with open(sess_file, 'r', encoding='utf-8', errors='ignore') as f:
29|29|            content = f.read()
30|30|        # PHP session中查找 user| 表示已登录
31|31|        if 'user|' in content:
32|32|            # 尝试提取用户名
33|33|            m = re.search(r'user\|a:\d+:\{.*?s:8:"username";s:\d+:"([^"]+)"', content)
34|34|            username = m.group(1) if m else '用户'
35|35|            return True, username
36|36|    except:
37|37|        pass
38|38|    return False, None
39|39|
40|40|
41|41|@app.before_request
42|42|def before_request():
43|43|    logged_in, username = check_php_session()
44|44|    g.logged_in = logged_in
45|45|    g.username = username
46|46|
47|47|
48|48|def login_required(f):
49|49|    """装饰器：需要登录的API"""
50|50|    @wraps(f)
51|51|    def wrapper(*args, **kwargs):
52|52|        if not g.logged_in:
53|53|            return jsonify({'success': False, 'error': '请先登录', 'need_login': True}), 401
54|54|        return f(*args, **kwargs)
55|55|    return wrapper
56|56|
57|57|
58|58|def _normalize_code(code):
59|59|    """统一股票代码格式"""
60|60|    code = code.upper().replace('SH', '').replace('SZ', '').replace('BJ', '')
61|61|    if code.startswith('6'):
62|62|        return 'sh' + code
63|63|    elif code.startswith('0') or code.startswith('3'):
64|64|        return 'sz' + code
65|65|    else:
66|66|        return 'sz' + code
67|67|
68|68|
69|69|# ============ 页面路由 ============
70|70|@app.route('/')
71|71|def index():
72|72|    # 服务端预加载热门数据，避免客户端JS加载失败
73|73|    hot_stocks = []
74|74|    hot_sectors = []
75|75|    try:
76|76|        from stock_data import get_hot_stocks, get_sector_trend
77|77|        hot_stocks = get_hot_stocks()[:10] if callable(get_hot_stocks) else []
78|78|        hot_sectors = get_sector_trend()[:15] if callable(get_sector_trend) else []
79|79|    except Exception as e:
80|80|        print(f'[WARN] 首页预加载失败: {e}')
81|81|    return render_template('index.html', hot_stocks=hot_stocks, hot_sectors=hot_sectors,
82|82|                          logged_in=g.logged_in, username=g.username)
83|83|
84|84|
85|85|# ============ 行情 API（免费） ============
86|86|@app.route('/api/quote/<code>')
87|87|def quote(code):
88|88|    try:
89|89|        full_code = _normalize_code(code)
90|90|        result = stock_data.parse_tencent_quote(full_code)
91|91|        if result:
92|92|            result['full_code'] = full_code
93|93|        return jsonify({'success': True, 'data': result})
94|94|    except Exception as e:
95|95|        traceback.print_exc()
96|96|        return jsonify({'success': False, 'error': str(e)})
97|97|
98|98|
99|99|@app.route('/api/kline/<code>')
100|100|def kline(code):
101|101|    try:
102|102|        full_code = _normalize_code(code)
103|103|        days = request.args.get('days', 60, type=int)
104|104|        result = stock_data.get_kline(full_code, days)
105|105|        return jsonify({'success': True, 'data': result})
106|106|    except Exception as e:
107|107|        traceback.print_exc()
108|108|        return jsonify({'success': False, 'error': str(e)})
109|109|
110|110|
111|111|@app.route('/api/sectors/<code>')
112|112|def sectors(code):
113|113|    try:
114|114|        full_code = _normalize_code(code)
115|115|        result = stock_data.get_sectors(full_code)
116|116|        return jsonify({'success': True, 'data': result})
117|117|    except Exception as e:
118|118|        traceback.print_exc()
119|119|        return jsonify({'success': False, 'error': str(e)})
120|120|
121|121|
122|122|@app.route('/api/fundflow/<code>')
123|123|def fundflow(code):
124|124|    try:
125|125|        full_code = _normalize_code(code)
126|126|        days = request.args.get('days', 10, type=int)
127|127|        result = stock_data.get_fund_flow(full_code, days)
128|128|        return jsonify({'success': True, 'data': result})
129|129|    except Exception as e:
130|130|        traceback.print_exc()
131|131|        return jsonify({'success': False, 'error': str(e)})
132|132|
133|133|
134|134|@app.route('/api/search')
135|135|def search():
136|136|    try:
137|137|        keyword = request.args.get('q', '').strip()
138|138|        if len(keyword) < 1:
139|139|            return jsonify({'success': False, 'error': '请输入股票代码或名称'})
140|140|        result = stock_data.search_stock(keyword)
141|141|        return jsonify({'success': True, 'data': result})
142|142|    except Exception as e:
143|143|        traceback.print_exc()
144|144|        return jsonify({'success': False, 'error': str(e)})
145|145|
146|146|
147|147|@app.route('/api/valuation/<code>')
148|148|def valuation(code):
149|149|    try:
150|150|        full_code = _normalize_code(code)
151|151|        result = stock_data.get_valuation(full_code)
152|152|        return jsonify({'success': True, 'data': result})
153|153|    except Exception as e:
154|154|        traceback.print_exc()
155|155|        return jsonify({'success': False, 'error': str(e)})
156|156|
157|157|
158|158|@app.route('/api/signals/<code>')
159|159|def signals(code):
160|160|    try:
161|161|        sig_type = request.args.get('type', 'hot')
162|162|        if sig_type == 'hot':
163|163|            result = stock_data.get_hot_stocks()
164|164|        elif sig_type == 'northbound':
165|165|            result = stock_data.get_northbound()
166|166|        elif sig_type == 'dragon_tiger':
167|167|            result = stock_data.get_dragon_tiger()
168|168|        elif sig_type == 'restriction':
169|169|            full_code = _normalize_code(code)
170|170|            result = stock_data.get_restriction_alert(full_code)
171|171|        elif sig_type == 'block_trade':
172|172|            full_code = _normalize_code(code)
173|173|            result = stock_data.get_block_trade(full_code)
174|174|        elif sig_type == 'sector_trend':
175|175|            result = stock_data.get_sector_trend()
176|176|        else:
177|177|            result = stock_data.get_hot_stocks()
178|178|        return jsonify({'success': True, 'data': result})
179|179|    except Exception as e:
180|180|        traceback.print_exc()
181|181|        return jsonify({'success': False, 'error': str(e)})
182|182|
183|183|
184|184|@app.route('/api/news/<code>')
185|185|def news(code):
186|186|    try:
187|187|        full_code = _normalize_code(code)
188|188|        news_type = request.args.get('type', 'individual')
189|189|        if news_type == 'individual':
190|190|            result = stock_data.get_individual_news(full_code)
191|191|        else:
192|192|            result = stock_data.get_global_news()
193|193|        return jsonify({'success': True, 'data': result})
194|194|    except Exception as e:
195|195|        traceback.print_exc()
196|196|        return jsonify({'success': False, 'error': str(e)})
197|197|
198|198|
199|199|@app.route('/api/multi-quote')
200|200|def multi_quote():
201|201|    try:
202|202|        codes = request.args.get('codes', '').split(',')
203|203|        results = []
204|204|        for code in codes:
205|205|            code = code.strip().upper().replace('SH', '').replace('SZ', '').replace('BJ', '')
206|206|            if not code:
207|207|                continue
208|208|            if code.startswith('6'):
209|209|                full_code = 'sh' + code
210|210|            elif code.startswith('0') or code.startswith('3'):
211|211|                full_code = 'sz' + code
212|212|            else:
213|213|                full_code = 'sz' + code
214|214|            r = stock_data.parse_tencent_quote(full_code)
215|215|            if r:
216|216|                r['full_code'] = full_code
217|217|                results.append(r)
218|218|        return jsonify({'success': True, 'data': results})
219|219|    except Exception as e:
220|220|        traceback.print_exc()
221|221|        return jsonify({'success': False, 'error': str(e)})
222|222|
223|223|
224|224|@app.route('/api/macro')
225|225|def macro():
226|226|    """获取宏观数据 - 免费"""
227|227|    try:
228|228|        data_type = request.args.get('type', 'china')
229|229|        if data_type == 'china':
230|230|            result = stock_data.get_macro_china()
231|231|        elif data_type == 'usa':
232|232|            result = stock_data.get_macro_usa()
233|233|        elif data_type == 'calendar':
234|234|            result = stock_data.get_macro_calendar()
235|235|        else:
236|236|            result = stock_data.get_macro_china()
237|237|        return jsonify({'success': True, 'data': result})
238|238|    except Exception as e:
239|239|        traceback.print_exc()
240|240|        return jsonify({'success': False, 'error': str(e)})
241|241|
242|242|
243|243|# ============ 深度分析 API（需登录） ============
244|244|@app.route('/api/fundflow120/<code>')
245|245|@login_required
246|246|def fundflow120(code):
247|247|    try:
248|248|        full_code = _normalize_code(code)
249|249|        result = stock_data.get_fund_flow_120d(full_code)
250|250|        return jsonify({'success': True, 'data': result})
251|251|    except Exception as e:
252|252|        traceback.print_exc()
253|253|        return jsonify({'success': False, 'error': str(e)})
254|254|
255|255|
256|256|@app.route('/api/fundmargin/<code>')
257|257|@login_required
258|258|def fundmargin(code):
259|259|    try:
260|260|        full_code = _normalize_code(code)
261|261|        result = stock_data.get_margin_data(full_code)
262|262|        return jsonify({'success': True, 'data': result})
263|263|    except Exception as e:
264|264|        traceback.print_exc()
265|265|        return jsonify({'success': False, 'error': str(e)})
266|266|
267|267|
268|268|@app.route('/api/financial/<code>')
269|269|@login_required
270|270|def financial(code):
271|271|    try:
272|272|        code_num = code.upper().replace('SH', '').replace('SZ', '').replace('BJ', '')
273|273|        table_type = request.args.get('type', 'income')
274|274|        result = stock_data.get_financial_table(code_num, table_type)
275|275|        return jsonify({'success': True, 'data': result})
276|276|    except Exception as e:
277|277|        traceback.print_exc()
278|278|        return jsonify({'success': False, 'error': str(e)})
279|279|
280|280|
281|281|@app.route('/api/announcement/<code>')
282|282|@login_required
283|283|def announcement(code):
284|284|    try:
285|285|        full_code = _normalize_code(code)
286|286|        result = stock_data.get_announcements(full_code)
287|287|        return jsonify({'success': True, 'data': result})
288|288|    except Exception as e:
289|289|        traceback.print_exc()
290|290|        return jsonify({'success': False, 'error': str(e)})
291|291|
292|292|
293|293|@app.route('/api/holder/<code>')
294|294|@login_required
295|295|def holder(code):
296|296|    try:
297|297|        full_code = _normalize_code(code)
298|298|        result = stock_data.get_holder_num(full_code)
299|299|        return jsonify({'success': True, 'data': result})
300|300|    except Exception as e:
301|301|        traceback.print_exc()
302|302|        return jsonify({'success': False, 'error': str(e)})
303|303|
304|304|
305|305|@app.route('/api/dividend/<code>')
306|306|@login_required
307|307|def dividend(code):
308|308|    try:
309|309|        full_code = _normalize_code(code)
310|310|        result = stock_data.get_dividend(full_code)
311|311|        return jsonify({'success': True, 'data': result})
312|312|    except Exception as e:
313|313|        traceback.print_exc()
314|314|        return jsonify({'success': False, 'error': str(e)})
315|315|
316|316|
317|317|@app.route('/api/reports/<code>')
318|318|@login_required
319|319|def reports(code):
320|320|    try:
321|321|        full_code = _normalize_code(code)
322|322|        result = stock_data.get_reports(full_code)
323|323|        return jsonify({'success': True, 'data': result})
324|324|    except Exception as e:
325|325|        traceback.print_exc()
326|326|        return jsonify({'success': False, 'error': str(e)})
327|327|
328|328|
329|329|# ============ AI 智能引导 API（需登录） ============
330|330|@app.route('/api/ai/chat', methods=['POST'])
331|331|@login_required
332|332|def ai_chat():
333|333|    try:
334|334|        data = request.get_json() or {}
335|335|        messages = data.get('messages', [])
336|336|        current_stock = data.get('current_stock', '')
337|337|        
338|338|        if not messages:
339|339|            return jsonify({'success': False, 'error': '消息不能为空'})
340|340|        
341|341|        system_prompt = """你是一位专业的A股投研分析师，擅长技术分析、基本面分析和市场情绪分析。
342|342|
343|343|你的能力包括：
344|344|1. 解读K线形态、均线排列、成交量变化
345|345|2. 分析资金流向（主力、大单、散户）
346|346|3. 评估估值水平（PE、PB、PEG）
347|347|4. 跟踪市场热点和题材概念
348|348|5. 解读龙虎榜、融资融券、大宗交易等信号
349|349|6. 分析财务报表（利润表、资产负债表、现金流量表）
350|350|7. 评估解禁、分红、股东户数变化等事件影响
351|351|
352|352|回答风格：
353|353|- 专业但不晦涩，用通俗语言解释复杂概念
354|354|- 数据驱动，引用具体数字支撑观点
355|355|- 风险提示明确，不给出绝对买卖建议
356|356|- 结构化输出，使用markdown格式
357|357|
358|358|当前分析的股票代码：""" + (current_stock if current_stock else "未指定")
359|359|        
360|360|        api_messages = [{"role": "system", "content": system_prompt}]
361|361|        for msg in messages:
362|362|            api_messages.append({
363|363|                "role": msg.get('role', 'user'),
364|364|                "content": msg.get('content', '')
365|365|            })
366|366|        
367|367|        payload = {
368|368|            "model": AI_MODEL,
369|369|            "messages": api_messages,
370|370|            "temperature": 0.7,
371|371|            "max_tokens": 2000
372|372|        }
373|373|        
374|374|        headers = {
375|375|            "Authorization": f"Bearer {AI_API_KEY}",
376|376|            "Content-Type": "application/json"
377|377|        }
378|378|        
379|379|        r = requests.post(AI_API_URL, json=payload, headers=headers, timeout=120)
380|380|        
381|381|        if r.status_code == 200:
382|382|            ai_response = r.json()
383|383|            content = ai_response.get('choices', [{}])[0].get('message', {}).get('content', '')
384|384|            return jsonify({'success': True, 'data': {'content': content}})
385|385|        else:
386|386|            return jsonify({'success': False, 'error': f'AI服务错误: {r.status_code}'})
387|387|            
388|388|    except requests.exceptions.Timeout:
389|389|        return jsonify({'success': False, 'error': 'AI分析超时（120秒），Agnes AI服务可能繁忙，请稍后重试'})
390|390|    except Exception as e:
391|391|        traceback.print_exc()
392|392|        return jsonify({'success': False, 'error': str(e)})
393|393|
394|394|
395|395|@app.route('/api/ai/analyze', methods=['POST'])
396|396|@login_required
397|397|def ai_analyze():
398|398|    """AI一键分析当前股票"""
399|399|    try:
400|400|        data = request.get_json() or {}
401|401|        code = data.get('code', '')
402|402|        stock_data_json = data.get('stock_data', {})
403|403|        
404|404|        if not code:
405|405|            return jsonify({'success': False, 'error': '股票代码不能为空'})
406|406|        
407|407|        from datetime import datetime
408|408|        today_str = datetime.now().strftime('%Y年%m月%d日')
409|409|        current_year = datetime.now().year
410|410|
411|411|        prompt = f"""请对股票 {code} 进行全面的投研分析。
412|412|
413|413|当前日期：{today_str}（{current_year}年）
414|414|当前数据：
415|415|{json.dumps(stock_data_json, ensure_ascii=False, indent=2)}
416|416|
417|417|请从以下维度进行分析：
418|418|1. **技术面**：K线形态、均线系统、成交量、支撑压力位
419|419|2. **资金面**：主力资金流向、大单动向、融资融券余额变化
420|420|3. **估值面**：PE/PB水平、与行业对比、历史分位
421|421|4. **消息面**：近期新闻、公告、题材概念催化
422|422|5. **风险点**：解禁、大宗交易、股东户数变化等
423|423|6. **综合评级**：给出谨慎/中性/乐观的倾向性判断（非投资建议）
424|424|
425|425|请用markdown格式输出，包含具体数据引用。"""
426|426|
427|427|        payload = {
428|428|            "model": AI_MODEL,
429|429|            "messages": [
430|430|                {"role": "system", "content": f"你是一位专业的A股投研分析师。当前真实日期是{today_str}（{current_year}年）。请基于用户提供的实时数据进行分析。"},
431|431|                {"role": "user", "content": prompt}
432|432|            ],
433|433|            "temperature": 0.7,
434|434|            "max_tokens": 3000
435|435|        }
436|436|        
437|437|        headers = {
438|438|            "Authorization": f"Bearer {AI_API_KEY}",
439|439|            "Content-Type": "application/json"
440|440|        }
441|441|        
442|442|        r = requests.post(AI_API_URL, json=payload, headers=headers, timeout=120)
443|443|        
444|444|        if r.status_code == 200:
445|445|            ai_response = r.json()
446|446|            content = ai_response.get('choices', [{}])[0].get('message', {}).get('content', '')
447|447|            return jsonify({'success': True, 'data': {'content': content}})
448|448|        else:
449|449|            return jsonify({'success': False, 'error': f'AI服务错误: {r.status_code}'})
450|450|            
451|451|    except requests.exceptions.Timeout:
452|452|        return jsonify({'success': False, 'error': 'AI分析超时（120秒），Agnes AI服务可能繁忙，请稍后重试'})
453|453|    except Exception as e:
454|454|        traceback.print_exc()
455|455|        return jsonify({'success': False, 'error': str(e)})
456|456|
457|457|
458|458|@app.route('/api/ai/macro', methods=['POST'])
459|459|@login_required
460|460|def ai_macro():
461|461|    """AI分析宏观数据"""
462|462|    try:
463|463|        data = request.get_json() or {}
464|464|        macro_data = data.get('macro_data', {})
465|465|        indicator = data.get('indicator', '宏观数据')
466|466|        
467|467|        if not macro_data:
468|468|            return jsonify({'success': False, 'error': '宏观数据不能为空'})
469|469|        
470|470|        data_rows = macro_data.get('data', [])
471|471|        latest_val = prev_val = forecast_val = None
472|472|        if data_rows and len(data_rows) > 0:
473|473|            latest = data_rows[0]
474|474|            latest_val = latest.get('value')
475|475|            prev_val = latest.get('previous')
476|476|            forecast_val = latest.get('forecast')
477|477|            if prev_val is None and len(data_rows) > 1:
478|478|                prev_val = data_rows[1].get('value')
479|479|
480|480|        compare_info = ""
481|481|        if latest_val is not None:
482|482|            compare_info += f"\n最新值: {latest_val}"
483|483|        if prev_val is not None:
484|484|            compare_info += f"\n前值: {prev_val}"
485|485|            if latest_val is not None:
486|486|                change = latest_val - prev_val
487|487|                compare_info += f" (较前值变化: {change:+.2f})"
488|488|        if forecast_val is not None:
489|489|            compare_info += f"\n市场预期: {forecast_val}"
490|490|            if latest_val is not None:
491|491|                diff = latest_val - forecast_val
492|492|                compare_info += f" (实际值较预期: {diff:+.2f}, {'高于' if diff > 0 else '低于' if diff < 0 else '符合'}预期)"
493|493|
494|494|        prompt = f"""请对以下{macro_data.get('country', '中国')}{indicator}数据进行专业分析。
495|495|
496|496|数据详情：
497|497|{json.dumps(macro_data, ensure_ascii=False, indent=2)}
498|498|
499|499|关键对比数据：{compare_info}
500|500|
501|501|请从以下维度分析：
502|502|1. **数据解读**：最新数值的含义，与市场预期和前值的对比
503|503|2. **趋势分析**：近期走势方向
504|504|3. **政策含义**：对货币政策、财政政策的潜在影响
505|505|4. **市场影响**：对股市、债市、汇市的影响
506|506|5. **投资启示**：对资产配置的参考意义（非投资建议）"""
507|507|        
508|508|        from datetime import datetime
509|509|        today_str = datetime.now().strftime('%Y年%m月%d日')
510|510|
511|511|        payload = {
512|512|            "model": AI_MODEL,
513|513|            "messages": [
514|514|                {"role": "system", "content": f"你是一位专业的宏观经济分析师。今天是{today_str}。"},
515|515|                {"role": "user", "content": prompt}
516|516|            ],
517|517|            "temperature": 0.7,
518|518|            "max_tokens": 3000
519|519|        }
520|520|        
521|521|        headers = {
522|522|            "Authorization": f"Bearer {AI_API_KEY}",
523|523|            "Content-Type": "application/json"
524|524|        }
525|525|        
526|526|        r = requests.post(AI_API_URL, json=payload, headers=headers, timeout=120)
527|527|        
528|528|        if r.status_code == 200:
529|529|            ai_response = r.json()
530|530|            content = ai_response.get('choices', [{}])[0].get('message', {}).get('content', '')
531|531|            return jsonify({'success': True, 'data': {'content': content}})
532|532|        else:
533|533|            return jsonify({'success': False, 'error': f'AI服务错误: {r.status_code}'})
534|534|            
535|535|    except requests.exceptions.Timeout:
536|536|        return jsonify({'success': False, 'error': 'AI分析超时（120秒），Agnes AI服务可能繁忙，请稍后重试'})
537|537|    except Exception as e:
538|538|        traceback.print_exc()
539|539|        return jsonify({'success': False, 'error': str(e)})
540|540|
541|541|
542|542|if __name__ == '__main__':
543|543|    print('启动 a-stock-data AI智能投研平台...')
544|544|    print('打开浏览器访问: http://127.0.0.1:5000')
545|545|    app.run(host='0.0.0.0', port=5001, debug=False)
546|546|