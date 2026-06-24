<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once __DIR__ . '/auth_lawyer.php';

$AGNES_URL = 'https://apihub.agnes-ai.com/v1/chat/completions';
$AGNES_KEY = 'sk-MWFOIA8F2K2gTLMWsp5cwAaYapEXn4HPNkWAEheH9ynow6oL';
$AGNES_MODEL = 'agnes-2.0-flash';
$RATE_LIMIT = 10;
$RATE_WINDOW = 3600;
$RATE_FILE = __DIR__ . '/rate_limit.json';
$MAX_HISTORY = 10;

$SYSTEM_PROMPT = "你是\"AI花哥·家庭法律助手\"，一个面向中国大陆用户的免费家庭法律分流指引工具。\n\n"
    . "【核心定位】\n"
    . "- 帮助用户快速判断法律问题的紧急程度和风险等级\n"
    . "- 引导用户保留证据、选择正确的官方渠道\n"
    . "- 你不是执业律师，不出具最终法律意见，不保证结果\n"
    . "- 默认原则：先排紧急风险→再保全证据→再选择正确渠道\n\n"
    . "【安全优先-以下情况立即建议拨打110】\n"
    . "- 正在发生的家暴、威胁、跟踪、胁迫、非法拘禁\n"
    . "- 儿童/老人/残障人士正在遭受虐待\n"
    . "- 暴力催收、强行入室、群体恐吓\n\n"
    . "【渠道分流】\n"
    . "- 110：人身安全、暴力、刑事风险\n"
    . "- 12348/中国法律服务网：法律援助、基础法律咨询\n"
    . "- 12315：消费纠纷\n"
    . "- 12333/人社局：欠薪、辞退、社保\n"
    . "- 12368：法院诉讼服务\n"
    . "- 人民调解：邻里、家庭、小额纠纷\n"
    . "- 劳动仲裁：劳动争议\n"
    . "- 法院起诉：民事、财产、家庭、侵权\n\n"
    . "【纠纷要点】\n"
    . "婚姻家暴→报警→伤情鉴定→证据→保护令→离婚诉讼\n"
    . "劳动欠薪→考勤/合同→协商→12333→劳动仲裁(1年)→法院\n"
    . "租房押金→照片视频→协商→12315→调解→小额诉讼\n"
    . "民间借贷→借条/转账→催告函→调解→起诉(3年时效)\n"
    . "消费纠纷→订单/付款→平台投诉→12315→起诉\n\n"
    . "【文书生成-VIP专享】\n"
    . "VIP用户可生成：催告函、民事起诉状、证据清单、劳动仲裁申请书、借条/欠条、\n"
    . "离婚协议书(参考)、和解协议书、租赁合同(简版)、授权委托书。\n"
    . "格式：标题→当事人信息(【】标注)→事实与理由→请求事项→落款→证据清单。\n"
    . "免责加末尾。文书主体用【DOC_START】和【DOC_END】包裹以便下载。\n"
    . "生成前先确认关键信息（双方姓名、金额、日期等）。\n\n"
    . "【法律边界】\n"
    . "不出具最终法律意见，不编造法律条文，不指导违法行为\n"
    . "用简体中文，语言朴实实用流程化\n"
    . "每次回答末尾加免责提醒";

function check_rate_limit($ip, $file, $window, $limit) {
    $now = time();
    $data = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    $data = array_filter($data, function($t) use ($now, $window) {
        return $t['time'] > $now - $window;
    });
    $count = 0;
    foreach ($data as $entry) {
        if ($entry['ip'] === $ip) $count++;
    }
    $data[] = ['ip' => $ip, 'time' => $now];
    file_put_contents($file, json_encode(array_values($data)));
    return $count >= $limit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => '仅支持POST'], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$user_message = trim($input['message'] ?? '');
$history = $input['history'] ?? [];
$req_type = $input['type'] ?? 'consult';

if (empty($user_message)) {
    echo json_encode(['error' => '请输入您的问题'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($req_type === 'document') {
    if (!is_logged_in()) {
        echo json_encode(['error' => '生成文书需要登录会员', 'action' => 'login', 'redirect' => '/studio/login.php?redirect=/lawyer/'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!is_vip()) {
        echo json_encode(['error' => '文书生成是VIP专属功能，请升级会员', 'action' => 'upgrade', 'redirect' => '/lawyer/vip.php'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (check_rate_limit($ip, $RATE_FILE, $RATE_WINDOW, $RATE_LIMIT)) {
    echo json_encode(['error' => '咨询次数已达上限，请稍后再试（每小时10次）'], JSON_UNESCAPED_UNICODE);
    exit;
}

$messages = [['role' => 'system', 'content' => $SYSTEM_PROMPT]];
$recent = array_slice($history, -$MAX_HISTORY * 2);
foreach ($recent as $msg) {
    if (isset($msg['role'], $msg['content'])) {
        $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
    }
}

if ($req_type === 'document') {
    $user_message = "请生成以下法律文书，按标准格式，用【】标注需填写信息，文书主体用【DOC_START】和【DOC_END】包裹：\n" . $user_message;
}
$messages[] = ['role' => 'user', 'content' => $user_message];

$ch = curl_init($AGNES_URL);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer '. $AGNES_KEY,
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'model' => $AGNES_MODEL,
        'messages' => $messages,
        'max_tokens' => 1500,
        'temperature' => 0.3,
    ]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 120,
    CURLOPT_SSL_VERIFYPEER => false,
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo json_encode(['error' => '服务暂时不可用，请稍后重试'], JSON_UNESCAPED_UNICODE);
    exit;
}

$result = json_decode($response, true);
if ($http_code !== 200 || isset($result['error'])) {
    echo json_encode(['error' => '服务异常: ' . ($result['error']['message'] ?? 'API错误')], JSON_UNESCAPED_UNICODE);
    exit;
}

$reply = $result['choices'][0]['message']['content'] ?? '抱歉，暂时无法回答。';

// 检测文书内容，提取可下载部分
$has_doc = false;
if (strpos($reply, '【DOC_START】') !== false && strpos($reply, '【DOC_END】') !== false) {
    $has_doc = true;
}

echo json_encode([
    'reply' => $reply,
    'has_doc' => $has_doc,
    'usage' => $result['usage'] ?? null,
], JSON_UNESCAPED_UNICODE);
