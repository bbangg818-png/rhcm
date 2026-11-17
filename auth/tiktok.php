<?php
require dirname(__DIR__) . '/includes/bootstrap.php';

$u = require_login();

$cfg = $c['tiktok_oauth'] ?? [];
if (empty($cfg['client_key']) || empty($cfg['client_secret'])) {
    flash('TikTok OAuth ยังไม่ได้ตั้งค่า', 'err');
    redirect(url('account/'));
}

$state = bin2hex(random_bytes(16));
$_SESSION['tiktok_oauth_state'] = $state;
$_SESSION['tiktok_oauth_next'] = $_GET['next'] ?? url('account/');

$redirect_uri = $cfg['redirect_uri'] ?: url('auth/tiktok-callback');
$scope = 'user.info.basic,user.info.profile,user.info.stats,video.list,video.manage,video.upload';

$auth_url = 'https://www.tiktok.com/v2/auth/authorize/?' . http_build_query([
    'client_key' => $cfg['client_key'],
    'scope' => $scope,
    'redirect_uri' => $redirect_uri,
    'state' => $state,
    'response_type' => 'code',
]);

redirect($auth_url);
