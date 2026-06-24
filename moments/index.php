<?php
session_start();
$is_logged = isset($_SESSION['user']);
$user = $is_logged ? $_SESSION['user'] : null;
$username = $is_logged ? $user['username'] : '';
$DIR = __DIR__;
$DATA_DIR = $DIR . '/data';
if (!is_dir($DATA_DIR)) mkdir($DATA_DIR, 0755, true);

// Data files
$F_COMMENTS = $DATA_DIR . '/comments.json';
$F_STORIES  = $DATA_DIR . '/user_stories.json';
$F_VOTES    = $DATA_DIR . '/votes.json';
$F_COLLECT  = $DATA_DIR . '/collections.json';
$F_NOTIFS   = $DATA_DIR . '/notifications.json';
$F_CLIKES   = $DATA_DIR . '/comment_likes.json';
$F_REPORTS  = $DATA_DIR . '/reports.json';
$F_FEEDBACK = $DATA_DIR . '/feedbacks.json';
$F_IMAGES   = $DIR . '/uploads/';

function load($f) { return file_exists($f) ? json_decode(file_get_contents($f), true) : []; }
function save($f, $d) { file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)); }
function notify($type, $story_id, $from_user, $to_user, $content) {
    global $F_NOTIFS;
    $n = load($F_NOTIFS);
    $n[] = ['id'=>time().rand(100,999), 'type'=>$type, 'story_id'=>$story_id, 'from'=>$from_user, 'to'=>$to_user, 'content'=>mb_substr($content,0,100), 'time'=>date('m-d H:i'), 'read'=>false];
    save($F_NOTIFS, $n);
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_logged) {
    $a = $_POST['action'] ?? '';
    $sid = (int)($_POST['story_id'] ?? 0);

    // Vote
    if ($a === 'vote' && $sid && isset($_POST['option'])) {
        $v = load($F_VOTES);
        $key = $username . '_' . $sid;
        if (!isset($v[$key])) { $v[$key] = $_POST['option']; save($F_VOTES, $v); }
        header('Location: index.php#s'.$sid); exit;
    }
    // Comment
    if ($a === 'comment' && $sid && !empty($_POST['content'])) {
        $c = load($F_COMMENTS);
        if (!isset($c[$sid])) $c[$sid] = [];
        $cid = time();
        $c[$sid][] = ['id'=>$cid, 'nickname'=>$username, 'content'=>mb_substr(strip_tags($_POST['content']),0,500), 'time'=>'刚刚', 'likes'=>0, 'liked_by'=>[], 'replies'=>[], 'parent_id'=>0];
        save($F_COMMENTS, $c);
        // Simulate AI reply after some delay (store flag)
        notify('comment', $sid, $username, '', mb_substr(strip_tags($_POST['content']),0,50));
        header('Location: index.php?commented='.$sid.'#s'.$sid); exit;
    }
    // Reply to comment
    if ($a === 'reply' && $sid && !empty($_POST['content']) && !empty($_POST['comment_id'])) {
        $c = load($F_COMMENTS);
        $cid = (int)$_POST['comment_id'];
        if (isset($c[$sid])) {
            foreach ($c[$sid] as &$cm) {
                if ($cm['id'] === $cid) {
                    $cm['replies'][] = ['id'=>time(), 'nickname'=>$username, 'content'=>mb_substr(strip_tags($_POST['content']),0,300), 'time'=>'刚刚'];
                    break;
                }
            }
            save($F_COMMENTS, $c);
        }
        header('Location: index.php?replied='.$sid.'#s'.$sid); exit;
    }
    // Like comment
    if ($a === 'clike' && !empty($_POST['comment_id'])) {
        $c = load($F_COMMENTS);
        $cid = (int)$_POST['comment_id'];
        foreach ($c as $s => &$comments) {
            foreach ($comments as &$cm) {
                if ($cm['id'] === $cid) {
                    if (!in_array($username, $cm['liked_by']??[])) {
                        $cm['likes']++;
                        $cm['liked_by'][] = $username;
                        save($F_COMMENTS, $c);
                    }
                    break 2;
                }
            }
        }
        header('Location: index.php#s'.$sid); exit;
    }
    // Post story
    if ($a === 'story' && !empty($_POST['content']) && !empty($_POST['category'])) {
        $stories = load($F_STORIES);
        $text = mb_substr(strip_tags($_POST['content']), 0, 2000);
        if (mb_strlen($text) >= 10) {
            $ckey = $_POST['category'];
            $cat_map = ['commute'=>'通勤','food'=>'外卖','social'=>'社交','work'=>'工作','life'=>'生活','horror'=>'恐怖','love'=>'恋爱'];
            $anon = !empty($_POST['anonymous']);
            $ns = [
                'id' => time(), 'nickname' => $anon ? '匿名用户' : $username,
                'avatar' => '', 'content' => $text, 'category' => $cat_map[$ckey]??'生活',
                'categoryKey' => $ckey, 'time' => '刚刚', 'likes' => 0, 'comments' => 0,
                'reads' => 0, 'isLiked' => false, 'isUser' => true, 'author' => $username,
                'optionA' => $_POST['optionA'] ?? '有过', 'optionB' => $_POST['optionB'] ?? '没有',
                'anonymous' => $anon,
            ];
            // Handle image upload
            if (!empty($_FILES['image']['tmp_name'])) {
                if (!is_dir($F_IMAGES)) mkdir($F_IMAGES, 0755, true);
                $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $img_name = time() . '_' . rand(1000,9999) . '.' . $ext;
                move_uploaded_file($_FILES['image']['tmp_name'], $F_IMAGES . $img_name);
                $ns['image'] = '/moments/uploads/' . $img_name;
            }
            array_unshift($stories, $ns);
            save($F_STORIES, $stories);
        }
        header('Location: index.php?posted=1'); exit;
    }
    // Collect story
    if ($a === 'collect' && $sid) {
        $col = load($F_COLLECT);
        $ukey = $username . '_' . $sid;
        if (isset($col[$ukey])) { unset($col[$ukey]); } else { $col[$ukey] = $sid; }
        save($F_COLLECT, $col);
        header('Location: index.php#s'.$sid); exit;
    }
    // Report
    if ($a === 'report' && $sid && !empty($_POST['reason'])) {
        $r = load($F_REPORTS);
        $r[] = ['story_id'=>$sid, 'reporter'=>$username, 'reason'=>$_POST['reason'], 'time'=>date('Y-m-d H:i')];
        save($F_REPORTS, $r);
        header('Location: index.php?reported=1'); exit;
    }
    // Feedback
    if ($a === 'feedback' && !empty($_POST['content'])) {
        $fb = load($F_FEEDBACK);
        $fb[] = ['user'=>$username, 'type'=>$_POST['type']??'建议', 'content'=>$_POST['content'], 'time'=>date('Y-m-d H:i')];
        save($F_FEEDBACK, $fb);
        header('Location: index.php?feedback=1'); exit;
    }
}

// Load data
$stories = load($F_STORIES);
$comments = load($F_COMMENTS);
$votes = load($F_VOTES);
$collections = load($F_COLLECT);
$notifications = load($F_NOTIFS);
$page = $_GET['page'] ?? 'feed';

// Default stories
$defaults = [
    ['id'=>1,'nickname'=>'尴尬小李','content'=>'昨天同学聚会，去卫生间的时候走错，进了男厕所，还碰到了班长。最尴尬的是，我当时还说了一句"你怎么进女厕所了"，现在想起来都想找个地缝钻进去...','category'=>'社交','categoryKey'=>'social','time'=>'3小时前','likes'=>128,'reads'=>214,'optionA'=>'有过','optionB'=>'没有','defaultComments'=>[['id'=>101,'nickname'=>'社恐本人','content'=>'哈哈，我也有过类似经历！','time'=>'2小时前','likes'=>15],['id'=>102,'nickname'=>'厕所战神','content'=>'班长当时表情肯定很精彩','time'=>'1小时前','likes'=>23]]],
    ['id'=>2,'nickname'=>'外卖翻车王','content'=>'点了一份螺蛳粉，结果外卖小哥送到的时候汤全洒了，外卖袋里全是汤汁。最惨的是，我打开门的时候，汤汁顺着门缝流进了电梯...','category'=>'外卖','categoryKey'=>'food','time'=>'5小时前','likes'=>256,'reads'=>567,'optionA'=>'笑死','optionB'=>'同情','defaultComments'=>[['id'=>201,'nickname'=>'螺蛳粉爱好者','content'=>'隔着屏幕都闻到味了','time'=>'4小时前','likes'=>45]]],
    ['id'=>3,'nickname'=>'通勤倒霉蛋','content'=>'早上坐地铁，手机掉进了站台和轨道之间的缝隙里。工作人员花了半小时才帮我捞上来，结果手机屏幕朝下摔碎了','category'=>'通勤','categoryKey'=>'commute','time'=>'昨天','likes'=>342,'reads'=>890,'optionA'=>'太惨了','optionB'=>'哈哈哈','defaultComments'=>[['id'=>301,'nickname'=>'AI','content'=>'救命啊这也太社死了！','time'=>'昨天','likes'=>78]]],
    ['id'=>4,'nickname'=>'职场小白','content'=>'开会的时候太紧张，把老板叫成了爸爸...整个会议室瞬间安静了','category'=>'工作','categoryKey'=>'work','time'=>'2天前','likes'=>567,'reads'=>1234,'optionA'=>'理解','optionB'=>'离谱','defaultComments'=>[['id'=>401,'nickname'=>'老板本板','content'=>'儿子，明天来我办公室一趟','time'=>'2天前','likes'=>234]]],
    ['id'=>5,'nickname'=>'深夜崩溃','content'=>'半夜两点想吃泡面，穿睡衣下楼去便利店，结果遇到前女友挽着新男友...','category'=>'生活','categoryKey'=>'life','time'=>'3天前','likes'=>890,'reads'=>2345,'optionA'=>'社死','optionB'=>'淡定','defaultComments'=>[['id'=>501,'nickname'=>'睡衣收藏家','content'=>'海绵宝宝睡衣哪里买的？','time'=>'3天前','likes'=>345]]],
    ['id'=>6,'nickname'=>'恐怖室友','content'=>'半夜起来上厕所，没开灯，隐约看到客厅沙发上坐着一个人。我吓得差点尿裤子，结果是室友在敷面膜...黑色的那种','category'=>'恐怖','categoryKey'=>'horror','time'=>'4天前','likes'=>456,'reads'=>1800,'optionA'=>'吓死','optionB'=>'笑死','defaultComments'=>[]],
    ['id'=>7,'nickname'=>'告白翻车','content'=>'暗恋了半年的女生终于答应和我一起去看电影。我精心准备了告白词，结果电影院太黑，我把爆米花桶当成了她的手，握了整整半小时','category'=>'恋爱','categoryKey'=>'love','time'=>'5天前','likes'=>1023,'reads'=>4500,'optionA'=>'在一起','optionB'=>'太尴尬','defaultComments'=>[['id'=>701,'nickname'=>'爆米花本花','content'=>'散场后在一起了吗？在线等','time'=>'5天前','likes'=>567]]],
];

$all_stories = array_merge($stories, $defaults);
$cats = [['key'=>'all','name'=>'全部'],['key'=>'commute','name'=>'通勤'],['key'=>'food','name'=>'外卖'],['key'=>'social','name'=>'社交'],['key'=>'work','name'=>'工作'],['key'=>'life','name'=>'生活'],['key'=>'horror','name'=>'恐怖'],['key'=>'love','name'=>'恋爱']];
$cur = $_GET['cat'] ?? 'all';
$filtered = $cur === 'all' ? $all_stories : array_filter($all_stories, function($s) use ($cur) { return ($s['categoryKey']??'') === $cur; });

// Search
$search = trim($_GET['q'] ?? '');
if ($search) {
    $filtered = array_filter($filtered, function($s) use ($search) { return mb_stripos($s['content'], $search) !== false || mb_stripos($s['nickname'], $search) !== false; });
}

// My stories/collections for profile
$my_stories = $is_logged ? array_filter($all_stories, function($s) use ($username) { return ($s['author']??'') === $username; }) : [];
$my_collected_ids = $is_logged ? array_filter($collections, function($v, $k) use ($username) { return strpos($k, $username.'_') === 0; }, ARRAY_FILTER_USE_BOTH) : [];
$my_collected = array_filter($all_stories, function($s) use ($my_collected_ids) { return in_array($s['id'], $my_collected_ids); });
$my_notifs = $is_logged ? array_slice(array_reverse($notifications), 0, 30) : [];

// Helper: get total comments for a story
function get_comment_count($sid, $def, $comments) {
    $def_count = count($def['defaultComments'] ?? []);
    $user_count = isset($comments[$sid]) ? count($comments[$sid]) : 0;
    return $def_count + $user_count;
}
function get_all_comments($sid, $def, $comments) {
    $dc = $def['defaultComments'] ?? [];
    $uc = $comments[$sid] ?? [];
    return array_merge($dc, $uc);
}

?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>生活狼狈时刻 · AI花哥</title>
<style>
:root{--bg:#050507;--card:#101010;--border:#3d3a39;--green:#00d992;--rose:#fb7185;--cyan:#22d3ee;--gold:#fbbf24;--text:#f2f2f2;--text2:#b8b3b0;--text3:#8b949e;--radius:8px}
*{margin:0;padding:0;box-sizing:border-box}
body{background:var(--bg);color:var(--text);font-family:system-ui,'PingFang SC','Microsoft YaHei',sans-serif;min-height:100vh}
body::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background-image:linear-gradient(rgba(30,41,59,.3)1px,transparent 1px),linear-gradient(90deg,rgba(30,41,59,.3)1px,transparent 1px);background-size:60px 60px}

header{position:sticky;top:0;z-index:100;background:rgba(5,5,7,.92);backdrop-filter:blur(12px);border-bottom:1px solid var(--border);padding:0 24px}
.h-inner{max-width:900px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;height:56px}
.logo{font-size:18px;font-weight:700;color:var(--green);text-decoration:none;display:flex;align-items:center;gap:6px}
.dot{width:6px;height:6px;border-radius:50%;background:var(--green);animation:pulse 2s infinite;box-shadow:0 0 6px rgba(0,217,146,.3)}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(1.5)}}
.nav{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.nav a,.nav span{color:var(--text2);text-decoration:none;font-size:12px;font-weight:500}
.nav a:hover{color:var(--green)}
.nav .active{color:var(--rose)}
.nav .badge{background:var(--rose);color:#fff;border-radius:10px;padding:1px 6px;font-size:10px;margin-left:-4px}

/* Tabs */
.tabs{display:flex;gap:4px;margin-bottom:16px;background:var(--card);border-radius:var(--radius);padding:4px;border:1px solid var(--border)}
.tab{flex:1;text-align:center;padding:8px;border-radius:6px;font-size:13px;cursor:pointer;color:var(--text2);transition:all .2s;text-decoration:none}
.tab:hover,.tab.active{background:rgba(251,113,133,.1);color:var(--rose)}
.container{position:relative;z-index:1;max-width:900px;margin:0 auto;padding:20px}

/* Search */
.search-bar{display:flex;gap:8px;margin-bottom:16px}
.search-bar input{flex:1;background:var(--card);border:1px solid var(--border);border-radius:6px;color:var(--text);padding:8px 12px;font-size:13px;font-family:inherit;outline:none}
.search-bar input:focus{border-color:var(--rose)}
.search-bar button{padding:8px 16px;background:var(--rose);border:none;border-radius:6px;color:#fff;font-weight:600;cursor:pointer;font-size:13px;font-family:inherit}

/* Cats */
.cats{display:flex;gap:8px;overflow-x:auto;padding:4px 0 16px;scrollbar-width:none}
.cats::-webkit-scrollbar{display:none}
.cat{padding:6px 16px;border-radius:20px;font-size:13px;white-space:nowrap;cursor:pointer;border:1px solid var(--border);color:var(--text2);background:transparent;transition:all .2s;font-family:inherit;text-decoration:none}
.cat:hover,.cat.active{border-color:var(--rose);color:var(--rose);background:rgba(251,113,133,.06)}

/* Card */
.card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:20px;margin-bottom:14px}
.card:hover{border-color:rgba(255,255,255,.06)}
.card-header{display:flex;align-items:center;gap:10px;margin-bottom:10px}
.avatar{width:36px;height:36px;border-radius:50%;background:rgba(251,113,133,.1);display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;overflow:hidden}
.avatar img{width:100%;height:100%;object-fit:cover}
.user-info{flex:1}.nickname{font-size:14px;font-weight:600}.time-tag{font-size:11px;color:var(--text3)}
.cat-tag{padding:2px 8px;border-radius:10px;font-size:10px;background:rgba(251,113,133,.1);color:var(--rose);white-space:nowrap;font-family:'JetBrains Mono',monospace}
.anon-tag{background:rgba(167,139,250,.1);color:#a78bfa}
.content{font-size:14px;line-height:1.7;color:var(--text2);margin-bottom:10px;cursor:pointer}
.content.collapsed{display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
.content img{max-width:100%;border-radius:6px;margin-top:8px}

/* Vote */
.vote-box{display:flex;gap:8px;margin:10px 0}
.vote-btn{flex:1;padding:8px;border-radius:6px;border:1px solid var(--border);background:transparent;color:var(--text2);font-size:13px;cursor:pointer;font-family:inherit;position:relative;overflow:hidden;transition:all .2s}
.vote-btn:hover{border-color:var(--rose);color:var(--rose)}
.vote-btn.voted{border-color:var(--rose)}
.vote-bar{position:absolute;left:0;top:0;bottom:0;background:rgba(251,113,133,.15);z-index:0;transition:width .5s}
.vote-text{position:relative;z-index:1}
.vote-pct{font-size:10px;color:var(--rose);margin-left:4px}

.actions{display:flex;gap:16px;font-size:13px;color:var(--text3);flex-wrap:wrap}
.action{display:flex;align-items:center;gap:4px;cursor:pointer;background:none;border:none;color:var(--text3);font-size:13px;font-family:inherit;padding:0}
.action:hover{color:var(--rose)}.action.liked{color:var(--rose)}

/* Comments */
.comment-section{border-top:1px solid var(--border);padding-top:10px;margin-top:8px;display:none}
.comment-section.open{display:block}
.comment{background:rgba(255,255,255,.015);border-radius:6px;padding:10px;margin-bottom:6px}
.c-header{display:flex;align-items:center;gap:8px;margin-bottom:4px}
.c-nick{font-size:13px;font-weight:600}.c-time{font-size:10px;color:var(--text3)}
.c-content{font-size:13px;color:var(--text2);line-height:1.6}
.c-actions{display:flex;gap:12px;font-size:11px;color:var(--text3);margin-top:4px}
.c-actions span{cursor:pointer}.c-actions span:hover{color:var(--rose)}
.c-likes{font-size:11px;color:var(--text3)}
.reply-form{display:flex;gap:6px;margin:6px 0 6px 20px}
.reply-form input{flex:1;background:var(--bg);border:1px solid var(--border);border-radius:4px;color:var(--text);padding:6px 10px;font-size:12px;font-family:inherit;outline:none}
.reply-form button{padding:6px 12px;background:var(--rose);border:none;border-radius:4px;color:#fff;font-size:11px;cursor:pointer;font-family:inherit}
.replies{margin-left:20px;border-left:1px solid var(--border);padding-left:10px}
.reply-item{padding:6px 0}.reply-nick{font-size:12px;font-weight:600}.reply-content{font-size:12px;color:var(--text2)}

/* Forms */
.cf{display:flex;gap:8px;margin-top:10px}
.cf input{flex:1;background:var(--bg);border:1px solid var(--border);border-radius:6px;color:var(--text);padding:8px 12px;font-size:13px;font-family:inherit;outline:none}
.cf input:focus{border-color:var(--rose)}
.cf button{padding:8px 16px;background:var(--rose);border:none;border-radius:6px;color:#fff;font-weight:600;cursor:pointer;font-size:13px;font-family:inherit;white-space:nowrap}
.cf button:hover{box-shadow:0 0 12px rgba(251,113,133,.3)}

.post-box{background:var(--card);border:1px solid rgba(251,113,133,.15);border-radius:var(--radius);padding:20px;margin-bottom:20px}
.post-box h3{font-size:14px;color:var(--rose);margin-bottom:12px}
.post-box select,.post-box textarea,.post-box input[type=text]{width:100%;background:var(--bg);border:1px solid var(--border);border-radius:6px;color:var(--text);padding:8px 12px;font-size:13px;font-family:inherit;margin-bottom:8px;outline:none}
.post-box select:focus,.post-box textarea:focus,.post-box input:focus{border-color:var(--rose)}
.post-box textarea{min-height:80px;resize:vertical}
.post-box .row{display:flex;gap:8px;align-items:center;margin-bottom:8px}
.post-box .row label{font-size:12px;color:var(--text3);white-space:nowrap}
.post-box button{padding:8px 20px;background:var(--rose);border:none;border-radius:6px;color:#fff;font-weight:600;cursor:pointer;font-size:13px;font-family:inherit}

/* Profile */
.profile-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:20px;margin-bottom:14px}
.profile-card h3{font-size:15px;margin-bottom:10px;color:var(--rose)}
.stat-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
.stat-item{text-align:center;padding:12px;background:rgba(255,255,255,.02);border-radius:6px}
.stat-num{font-size:24px;font-weight:700;color:var(--rose)}
.stat-label{font-size:11px;color:var(--text3);margin-top:4px}

/* Report */
.report-section{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:24px;margin-top:32px}
.report-section h2{font-size:20px;margin-bottom:16px;color:var(--rose)}
.similarity-bar{display:flex;align-items:center;gap:10px;margin-bottom:10px}
.similarity-bar .label{width:60px;font-size:12px;color:var(--text2);text-align:right}
.similarity-bar .bar{flex:1;height:20px;background:rgba(255,255,255,.03);border-radius:10px;overflow:hidden}
.similarity-bar .fill{height:100%;background:var(--rose);border-radius:10px;transition:width 1s}

.fb-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:20px;margin-top:20px}
.fb-card textarea{width:100%;background:var(--bg);border:1px solid var(--border);border-radius:6px;color:var(--text);padding:10px;font-size:13px;font-family:inherit;outline:none;min-height:80px;resize:vertical}
.fb-card select{background:var(--bg);border:1px solid var(--border);border-radius:6px;color:var(--text);padding:8px;font-size:13px;margin-bottom:8px;font-family:inherit}

.login-hint{background:var(--card);border:1px dashed var(--border);border-radius:var(--radius);padding:14px;text-align:center;color:var(--text3);margin-bottom:16px;font-size:13px}
.login-hint a{color:var(--rose)}
.msg{background:rgba(0,217,146,.06);border:1px solid rgba(0,217,146,.15);color:var(--green);padding:8px 12px;border-radius:6px;font-size:12px;margin-bottom:16px}
.empty{text-align:center;padding:40px;color:var(--text3)}
footer{position:relative;z-index:1;text-align:center;padding:30px;border-top:1px solid var(--border);color:var(--text3);font-size:11px;margin-top:40px}
@media(max-width:768px){
body{padding:0;font-size:14px}
header{padding:0 12px}
.h-inner{height:48px}
.logo{font-size:16px}
.container{padding:20px 12px}
h1{font-size:20px}
.grid{grid-template-columns:1fr!important;gap:12px}
.card{min-height:auto;padding:16px}
.stats-row{grid-template-columns:1fr 1fr!important}
.chart-row{grid-template-columns:1fr!important}
.stat-card .value{font-size:24px}
.stat-card{padding:14px}
table{font-size:11px}
th,td{padding:6px 8px}
.btn{padding:8px 20px;font-size:13px}
nav a{font-size:12px;padding:4px 8px}
.btns{flex-wrap:wrap;gap:8px}
.controls{flex-direction:column;align-items:flex-start}
.preview-wrap canvas{max-width:100%!important}
canvas{max-width:100%}
.area{padding:30px 16px}
.area .icon{font-size:36px}
.area .txt{font-size:13px}
.login-box{margin:40px 12px;padding:32px 20px}
footer{padding:24px 12px;margin-top:32px}
}</style>
</head>
<body>
<header><div class="h-inner">
<a href="/" class="logo"><span class="dot"></span>生活狼狈时刻</a>
<div class="nav">
<a href="index.php" class="<?= $page==='feed'?'active':'' ?>">广场</a>
<?php if($is_logged): ?>
<a href="index.php?page=profile" class="<?= $page==='profile'?'active':'' ?>">我的</a>
<a href="index.php?page=notifications" class="<?= $page==='notifications'?'active':'' ?>">🔔<?php $unread=count(array_filter($notifications,function($n){ return !$n['read']; })); if($unread):?><span class="badge"><?=$unread?></span><?php endif;?></a>
<?php endif; ?>
<a href="index.php?page=report" class="<?= $page==='report'?'active':'' ?>">报告</a>
<span><?= $is_logged ? '👤 '.htmlspecialchars($username) : '<a href="/studio/login.php?redirect=/moments/">登录</a>' ?></span>
<a href="/">←</a>
</div>
</div></header>

<div class="container">
<?php if(isset($_GET['commented'])): ?><div class="msg">✅ 评论成功</div><?php endif; ?>
<?php if(isset($_GET['replied'])): ?><div class="msg">✅ 回复成功</div><?php endif; ?>
<?php if(isset($_GET['posted'])): ?><div class="msg">✅ 故事发布成功</div><?php endif; ?>
<?php if(isset($_GET['reported'])): ?><div class="msg">✅ 举报已提交</div><?php endif; ?>
<?php if(isset($_GET['feedback'])): ?><div class="msg">✅ 反馈已提交，谢谢！</div><?php endif; ?>

<?php if($page === 'feed'): ?>
<!-- FEED PAGE -->
<div class="search-bar">
<form method="get" style="display:flex;gap:8px;width:100%">
<input type="hidden" name="cat" value="<?= htmlspecialchars($cur) ?>">
<input name="q" placeholder="🔍 搜索故事..." value="<?= htmlspecialchars($search) ?>">
<button type="submit">搜索</button>
<?php if($search): ?><a href="index.php?cat=<?= $cur ?>" style="color:var(--text3);line-height:34px;font-size:13px">清除</a><?php endif; ?>
</form>
</div>

<?php if($is_logged): ?>
<div class="post-box">
<h3>📝 分享你的狼狈时刻</h3>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="action" value="story">
<select name="category">
<?php foreach(array_slice($cats,1) as $c): ?>
<option value="<?= $c['key'] ?>"><?= $c['name'] ?></option>
<?php endforeach; ?>
</select>
<textarea name="content" placeholder="写下你的故事...（至少10字）" required></textarea>
<div class="row">
<label>投票选项A:</label><input type="text" name="optionA" value="有过" style="flex:1">
<label>选项B:</label><input type="text" name="optionB" value="没有" style="flex:1">
</div>
<div class="row">
<label><input type="checkbox" name="anonymous" value="1"> 匿名发布</label>
<label style="margin-left:12px">📷 <input type="file" name="image" accept="image/*" style="font-size:12px;color:var(--text3)"></label>
</div>
<button type="submit">发布故事</button>
</form>
</div>
<?php else: ?>
<div class="login-hint">🔒 <a href="/studio/login.php?redirect=/moments/">登录</a> 后发布故事、评论、投票</div>
<?php endif; ?>

<div class="cats">
<?php foreach($cats as $c): ?>
<a href="index.php?cat=<?= $c['key'] ?><?= $search?'&q='.urlencode($search):'' ?>" class="cat<?= $cur===$c['key']?' active':'' ?>"><?= $c['name'] ?></a>
<?php endforeach; ?>
</div>

<?php if(empty($filtered)): ?><div class="empty">暂无故事</div><?php endif; ?>

<?php foreach($filtered as $s):
$sid = $s['id'];
$vkey = $username.'_'.$sid;
$my_vote = $votes[$vkey] ?? null;
$voteA = $s['optionA'] ?? '有过';
$voteB = $s['optionB'] ?? '没有';
$total_votes = count(array_filter($votes, function($v,$k) use ($sid) { $slen = strlen('_'.$sid); return substr($k, -$slen) === '_'.$sid; }, ARRAY_FILTER_USE_BOTH));
$votesA = count(array_filter($votes, function($v,$k) use ($sid) { $slen = strlen('_'.$sid); return substr($k, -$slen) === '_'.$sid && $v === 'A'; }, ARRAY_FILTER_USE_BOTH));
$votesB = count(array_filter($votes, function($v,$k) use ($sid) { $slen = strlen('_'.$sid); return substr($k, -$slen) === '_'.$sid && $v === 'B'; }, ARRAY_FILTER_USE_BOTH));
$pctA = $total_votes > 0 ? round($votesA/$total_votes*100) : 0;
$pctB = $total_votes > 0 ? round($votesB/$total_votes*100) : 0;
$is_collected = $is_logged && isset($collections[$username.'_'.$sid]);
$all_comments = get_all_comments($sid, $s, $comments);
$total_comments = count($all_comments);

// AI comments: add auto-generated ones if there are user comments
$ai_replies = [];
if (isset($comments[$sid]) && count($comments[$sid]) > 0) {
    $ai_pool = ['哈哈太真实了！','笑死我了😂','我也遇到过！','深有同感…','哈哈哈哈救命','抱抱你','太惨了但是好好笑','这就是生活啊'];
    if ($total_comments < 10) {
        $ai_replies[] = ['id'=>90000+$sid,'nickname'=>'AI🤖','content'=>$ai_pool[array_rand($ai_pool)],'time'=>'刚刚','likes'=>rand(5,50),'isAI'=>true];
    }
}
$all_comments = array_merge($all_comments, $ai_replies);
?>
<div class="card" id="s<?= $sid ?>">
<div class="card-header">
<div class="avatar"><?= $s['avatar'] ? '<img src="'.$s['avatar'].'">' : '😄' ?></div>
<div class="user-info">
<div class="nickname"><?= htmlspecialchars($s['nickname']) ?><?= ($s['anonymous']??false)?' <span class="cat-tag anon-tag">匿名</span>':'' ?></div>
<div class="time-tag"><?= htmlspecialchars($s['time']) ?></div>
</div>
<span class="cat-tag"><?= htmlspecialchars($s['category']) ?></span>
</div>

<div class="content collapsed" onclick="this.classList.toggle('collapsed')"><?= htmlspecialchars($s['content']) ?></div>
<?php if(!empty($s['image'])): ?><img src="<?= htmlspecialchars($s['image']) ?>" style="max-width:100%;border-radius:6px;margin-bottom:10px"><?php endif; ?>

<div class="vote-box">
<form method="post" style="flex:1;display:flex;gap:8px">
<input type="hidden" name="action" value="vote">
<input type="hidden" name="story_id" value="<?= $sid ?>">
<button type="submit" name="option" value="A" class="vote-btn <?= $my_vote==='A'?'voted':'' ?>" <?= $my_vote||!$is_logged?'disabled':'' ?>>
<div class="vote-bar" style="width:<?= $pctA ?>%"></div>
<span class="vote-text">👍 <?= htmlspecialchars($voteA) ?></span>
<span class="vote-pct"><?= $total_votes>0?$pctA.'%':'' ?></span>
</button>
<button type="submit" name="option" value="B" class="vote-btn <?= $my_vote==='B'?'voted':'' ?>" <?= $my_vote||!$is_logged?'disabled':'' ?>>
<div class="vote-bar" style="width:<?= $pctB ?>%"></div>
<span class="vote-text">👎 <?= htmlspecialchars($voteB) ?></span>
<span class="vote-pct"><?= $total_votes>0?$pctB.'%':'' ?></span>
</button>
</form>
</div>

<div class="actions">
<span class="action" onclick="this.classList.toggle('liked')">🤍 <?= $s['likes'] ?></span>
<span class="action" onclick="toggleComments(<?= $sid ?>)">💬 <?= $total_comments ?></span>
<span class="action">👁 <?= $s['reads'] ?></span>
<?php if($is_logged): ?>
<form method="post" style="display:inline"><input type="hidden" name="action" value="collect"><input type="hidden" name="story_id" value="<?= $sid ?>"><button class="action" type="submit"><?= $is_collected?'⭐':'☆' ?> 收藏</button></form>
<?php endif; ?>
<span class="action" onclick="document.getElementById('report-<?= $sid ?>').style.display='block'" style="font-size:11px">🚩</span>
</div>

<!-- Report form -->
<div id="report-<?= $sid ?>" style="display:none;margin-top:8px">
<form method="post" class="cf"><input type="hidden" name="action" value="report"><input type="hidden" name="story_id" value="<?= $sid ?>"><input name="reason" placeholder="举报原因..." required><button type="submit">提交</button></form>
</div>

<!-- Comments -->
<div class="comment-section" id="comments-<?= $sid ?>">
<?php foreach($all_comments as $c): ?>
<div class="comment">
<div class="c-header">
<span class="c-nick"><?= htmlspecialchars($c['nickname']) ?><?= ($c['isAI']??false)?' 🤖':'' ?></span>
<span class="c-time"><?= htmlspecialchars($c['time']) ?></span>
<?php if($is_logged): ?>
<form method="post" style="display:inline"><input type="hidden" name="action" value="clike"><input type="hidden" name="comment_id" value="<?= $c['id'] ?>"><input type="hidden" name="story_id" value="<?= $sid ?>"><button class="action" type="submit" style="font-size:11px">❤️ <?= $c['likes'] ?></button></form>
<?php endif; ?>
</div>
<div class="c-content"><?= htmlspecialchars($c['content']) ?></div>
<?php if($is_logged && !($c['isAI']??false)): ?>
<div style="margin-left:18px"><span onclick="this.nextElementSibling.style.display='flex'" style="font-size:11px;color:var(--text3);cursor:pointer">↩ 回复</span>
<form method="post" class="reply-form" style="display:none"><input type="hidden" name="action" value="reply"><input type="hidden" name="story_id" value="<?= $sid ?>"><input type="hidden" name="comment_id" value="<?= $c['id'] ?>"><input name="content" placeholder="回复..."><button type="submit">发送</button></form></div>
<?php endif; ?>
<?php if(!empty($c['replies'])): ?>
<div class="replies">
<?php foreach($c['replies'] as $r): ?>
<div class="reply-item"><span class="reply-nick"><?= htmlspecialchars($r['nickname']) ?></span>: <span class="reply-content"><?= htmlspecialchars($r['content']) ?></span></div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>
<?php endforeach; ?>
<?php if($is_logged): ?>
<form method="post" class="cf"><input type="hidden" name="action" value="comment"><input type="hidden" name="story_id" value="<?= $sid ?>"><input name="content" placeholder="写下你的评论..." required><button type="submit">发送</button></form>
<?php endif; ?>
</div>
</div>
<?php endforeach; ?>

<?php elseif($page === 'profile' && $is_logged): ?>
<!-- PROFILE PAGE -->
<div class="tabs">
<a href="index.php?page=profile&tab=stories" class="tab <?= ($_GET['tab']??'stories')==='stories'?'active':'' ?>">我的故事</a>
<a href="index.php?page=profile&tab=collections" class="tab <?= ($_GET['tab']??'')==='collections'?'active':'' ?>">我的收藏</a>
<a href="index.php?page=profile&tab=activity" class="tab <?= ($_GET['tab']??'')==='activity'?'active':'' ?>">互动记录</a>
</div>

<div class="profile-card">
<h3>📊 个人统计</h3>
<div class="stat-grid">
<div class="stat-item"><div class="stat-num"><?= count($my_stories) ?></div><div class="stat-label">发布故事</div></div>
<div class="stat-item"><div class="stat-num"><?= count($my_collected) ?></div><div class="stat-label">收藏故事</div></div>
<div class="stat-item"><div class="stat-num"><?= count($my_notifs) ?></div><div class="stat-label">互动通知</div></div>
</div>
</div>

<?php
$tab = $_GET['tab'] ?? 'stories';
$show_stories = $tab === 'stories' ? $my_stories : ($tab === 'collections' ? $my_collected : []);
if($tab === 'activity'):
?>
<div class="profile-card"><h3>🔔 互动通知</h3>
<?php foreach($my_notifs as $n): ?>
<div class="comment"><span class="c-nick"><?= htmlspecialchars($n['from']) ?></span> <span class="c-time"><?= $n['time'] ?></span><br><span style="font-size:12px;color:var(--text2)"><?= htmlspecialchars($n['content']) ?></span></div>
<?php endforeach; ?>
<?php if(empty($my_notifs)): ?><div class="empty">暂无通知</div><?php endif; ?>
</div>
<?php else: ?>
<?php if(empty($show_stories)): ?><div class="empty">暂无内容</div><?php endif; ?>
<?php foreach($show_stories as $s): ?>
<div class="card">
<div class="card-header"><div class="avatar">😄</div><div class="user-info"><div class="nickname"><?= htmlspecialchars($s['nickname']) ?></div><div class="time-tag"><?= htmlspecialchars($s['time']) ?></div></div><span class="cat-tag"><?= htmlspecialchars($s['category']) ?></span></div>
<div class="content"><?= htmlspecialchars($s['content']) ?></div>
<div class="actions"><span class="action">🤍 <?= $s['likes'] ?></span><span class="action">💬 <?= get_comment_count($s['id'], $s, $comments) ?></span></div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php elseif($page === 'notifications' && $is_logged): ?>
<!-- NOTIFICATIONS -->
<div class="profile-card"><h3>🔔 互动消息</h3>
<?php foreach($my_notifs as $n): ?>
<div class="comment" style="<?= $n['read']?'':'border-left:2px solid var(--rose)' ?>">
<span class="c-nick"><?= htmlspecialchars($n['from']) ?></span> <span class="c-time"><?= $n['time'] ?></span>
<div class="c-content"><?= htmlspecialchars($n['content']) ?></div>
</div>
<?php endforeach; ?>
<?php if(empty($my_notifs)): ?><div class="empty">暂无通知</div><?php endif; ?>
</div>

<?php elseif($page === 'report'): ?>
<!-- REPORT PAGE -->
<div class="report-section">
<h2>📊 全网狼狈指数</h2>
<p style="color:var(--text3);font-size:13px;margin-bottom:20px">基于 <?= count($all_stories)+count($stories) ?> 个故事分析</p>

<?php
$cats_stats = [];
foreach($all_stories as $s) {
    $ck = $s['categoryKey'] ?? 'other';
    if(!isset($cats_stats[$ck])) $cats_stats[$ck] = ['count'=>0,'name'=>$s['category']];
    $cats_stats[$ck]['count']++;
}
arsort($cats_stats);
foreach($cats_stats as $ck => $cs): 
$pct = round($cs['count']/count($all_stories)*100);
?>
<div class="similarity-bar">
<div class="label"><?= $cs['name'] ?></div>
<div class="bar"><div class="fill" style="width:<?= $pct ?>%"></div></div>
<div style="font-size:11px;color:var(--text3);width:40px"><?= $pct ?>%</div>
</div>
<?php endforeach; ?>

<div style="margin-top:24px;padding:16px;background:rgba(251,113,133,.05);border-radius:8px;border:1px solid rgba(251,113,133,.1)">
<p style="font-size:13px;color:var(--rose)">🧠 专家建议</p>
<p style="font-size:12px;color:var(--text2);margin-top:8px;line-height:1.8">数据显示，<strong>社交</strong>和<strong>工作</strong>是狼狈时刻的高发场景。记住：每个人都有尴尬时刻，笑一笑就过去了。适当自嘲是最高级的幽默。</p>
</div>
</div>

<?php if($is_logged): ?>
<div class="fb-card">
<h3 style="color:var(--rose);margin-bottom:12px">📝 反馈建议</h3>
<form method="post">
<input type="hidden" name="action" value="feedback">
<select name="type">
<option value="建议">建议</option><option value="Bug">Bug反馈</option><option value="其他">其他</option>
</select>
<textarea name="content" placeholder="你的建议..." required></textarea>
<button class="action" type="submit" style="margin-top:8px;padding:8px 16px;background:var(--rose);border:none;border-radius:6px;color:#fff;font-weight:600;cursor:pointer;font-family:inherit">提交反馈</button>
</form>
</div>
<?php endif; ?>

<?php endif; ?>
</div>

<footer>© 2026 生活狼狈时刻 · 生活总有狼狈时，笑一笑就过去了</footer>

<script>
function toggleComments(id){document.getElementById('comments-'+id).classList.toggle('open')}
var hash=window.location.hash;if(hash&&hash.startsWith('#s')){var el=document.getElementById('comments-'+hash.substring(2));if(el)el.classList.add('open');}
</script>
</body>
</html>
