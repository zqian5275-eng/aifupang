<?php
// 百度主动推送 - 每天最多10条
// 用法: php baidu_push.php [url1] [url2] ...
$api = 'http://data.zz.baidu.com/urls?site=https://www.aifupang.com&token=K2OmqyY140PlqxeY';
$urls = array_slice($argv, 1);
if (empty($urls)) { die("请提供URL\n"); }
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/plain'));
curl_setopt($ch, CURLOPT_POSTFIELDS, implode("\n", $urls));
$res = curl_exec($ch);
curl_close($ch);
echo $res . "\n";
