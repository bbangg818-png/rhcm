<?php
require dirname(__DIR__) . '/includes/bootstrap.php';

$u = require_login();

$cfg = $c['tiktok_oauth'] ?? [];
if (empty($cfg['client_key']) || empty($cfg['client_secret'])) {
    flash('TikTok OAuth ยังไม่ได้ตั้งค่า', 'err');
    redirect(url('account/'));
}

$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';

if (empty($code) || empty($state) || $state !== ($_SESSION['tiktok_oauth_state'] ?? '')) {
    flash('การยืนยันตัวตน TikTok ล้มเหลว', 'err');
    redirect(url('account/'));
}

unset($_SESSION['tiktok_oauth_state']);
$next = $_SESSION['tiktok_oauth_next'] ?? url('account/');
unset($_SESSION['tiktok_oauth_next']);

$redirect_uri = $cfg['redirect_uri'] ?: url('auth/tiktok-callback');

// Exchange code for access token
$token_url = 'https://open.tiktokapis.com/v2/oauth/token/';
$post_data = [
    'client_key' => $cfg['client_key'],
    'client_secret' => $cfg['client_secret'],
    'code' => $code,
    'grant_type' => 'authorization_code',
    'redirect_uri' => $redirect_uri,
];

$ch = curl_init($token_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    flash('ไม่สามารถรับ access token จาก TikTok ได้', 'err');
    redirect($next);
}

$token_data = json_decode($response, true);
if (empty($token_data['access_token'])) {
    flash('ไม่สามารถรับ access token จาก TikTok ได้', 'err');
    redirect($next);
}

// Get user info
$user_url = 'https://open.tiktokapis.com/v2/user/info/?fields=username,display_name,avatar_url';
$ch = curl_init($user_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token_data['access_token'],
]);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    flash('ไม่สามารถรับข้อมูลผู้ใช้จาก TikTok ได้', 'err');
    redirect($next);
}

$user_data = json_decode($response, true);
if (empty($user_data['data']['user']['username'])) {
    flash('ไม่สามารถรับข้อมูลผู้ใช้จาก TikTok ได้', 'err');
    redirect($next);
}

$tiktok_username = '@' . $user_data['data']['user']['username'];
$access_token = $token_data['access_token'];

// Update user's TikTok username access token
q('UPDATE users SET tiktok_username=?, tiktok_access_token=? WHERE id=?', [$tiktok_username, $access_token, $u['id']]);

flash('เชื่อมต่อ TikTok สำเร็จ: ' . $tiktok_username);
redirect($next);
