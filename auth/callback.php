<?php
require dirname(__DIR__) . '/includes/bootstrap.php';

// debug: show fatal errors instead of white page (ลบออกเมื่อแก้เสร็จ)
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) { http_response_code(500); header('Content-Type: text/plain; charset=utf-8'); }
        echo "\nFATAL: {$e['message']} @ {$e['file']}:{$e['line']}\n";
    }
});
ini_set('display_errors', '1');
error_reporting(E_ALL);

try {
    $fail = function (string $m) { flash($m, 'err'); redirect(url('auth/login?info=1')); };
    if (isset($_GET['error'])) $fail('ยกเลิกการเข้าสู่ระบบ');
    $state = (string)($_GET['state'] ?? '');
    $expected = (string)($_SESSION['oauth_state'] ?? '') ?: (string)($_COOKIE['nc_state'] ?? '');
    if ($state === '' || $expected === '' || !hash_equals($expected, $state)) $fail('เซสชันเข้าสู่ระบบไม่ถูกต้อง กรุณาลองใหม่');
    unset($_SESSION['oauth_state']);
    clear_temp_cookie('nc_state');
    $code = (string)($_GET['code'] ?? '');
    if ($code === '' || !discord_ready()) $fail('ไม่สามารถเข้าสู่ระบบให้เสร็จสิ้น');

    [$st, $body] = http('POST', 'https://discord.com/api/oauth2/token',
        ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
        http_build_query([
            'client_id' => $c['discord_oauth']['client_id'],
            'client_secret' => $c['discord_oauth']['client_secret'],
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => discord_redirect_uri(),
        ]));
    $tok = json_decode($body, true);
    if ($st !== 200 || empty($tok['access_token'])) $fail('Discord ปฏิเสธการเข้าสู่ระบบ (' . $st . ') ตรวจสอบ client_id, secret และ Redirect URI');

    [$st, $body] = http('GET', 'https://discord.com/api/users/@me', ['Authorization: Bearer ' . $tok['access_token']]);
    $d = json_decode($body, true);
    if ($st !== 200 || empty($d['id'])) $fail('ไม่สามารถอ่านโปรไฟล์ Discord ของคุณได้');

    login_user($d);

    /* เพิ่มสมาชิกเข้า Discord server อัตโนมัติ (ต้องตั้ง bot_token + scope guilds.join) */
    $gid = $c['discord_oauth']['guild_id'] ?? '';
    $bt  = $c['discord_oauth']['bot_token'] ?? '';
    if ($gid !== '' && $bt !== '') {
        http('PUT', 'https://discord.com/api/guilds/' . $gid . '/members/' . $d['id'],
            ['Authorization: Bot ' . $bt, 'Content-Type: application/json'],
            json_encode(['access_token' => $tok['access_token']]));
    }

    $next = safe_next((string)($_SESSION['next'] ?? '') ?: (string)($_COOKIE['nc_next'] ?? ''));
    unset($_SESSION['next']);
    clear_temp_cookie('nc_next');
    flash('ยินดีต้อนรับ, ' . user_name(me()) . '!');
    redirect($next);
} catch (Throwable $e) {
    if (!headers_sent()) { http_response_code(500); header('Content-Type: text/plain; charset=utf-8'); }
    echo "LOGIN ERROR: " . $e->getMessage() . "\n" . $e->getFile() . ":" . $e->getLine() . "\n" . $e->getTraceAsString();
    exit;
}
