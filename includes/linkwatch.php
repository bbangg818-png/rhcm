<?php
/**
 * linkwatch_check() — เช็ค messages ล่าสุดใน Discord channel แล้วอัปเดตลิงก์ iOS ลง settings
 * ใช้ร่วมกันโดย api/linkwatch.php (เรียกผ่าน HTTP) และ linkwatch_daemon.php (CLI loop)
 */

const LW_GUILD   = '1549465252339388466';
const LW_CHANNEL = '1560658299169411162';

function linkwatch_check(): array {
    global $c;
    $token = trim((string) ($c['discord_oauth']['user_token'] ?? ''));
    if ($token === '') {
        return ['ok' => false, 'error' => 'ยังไม่ได้ตั้ง discord_oauth.user_token ใน config.php'];
    }

    $ch = curl_init('https://discord.com/api/v9/channels/' . LW_CHANNEL . '/messages?limit=20');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . $token,
            'Accept: application/json',
            'User-Agent: DiscordBot (https://rhcm-online.wasmer.app, 1.0)',
        ],
    ]);
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false || $code !== 200) {
        return ['ok' => false, 'error' => "Discord API ตอบ $code"];
    }
    $msgs = json_decode($raw, true);
    if (!is_array($msgs)) {
        return ['ok' => false, 'error' => 'อ่าน messages ไม่ได้'];
    }

    // messages เรียงใหม่→เก่าอยู่แล้ว — เอาอันล่าสุดที่มี iOS + gofile
    foreach ($msgs as $m) {
        $content = (string) ($m['content'] ?? '');
        if (!preg_match('/ios/i', $content)) continue;
        if (!preg_match('#https?://gofile\.io/\S+#i', $content, $mm)) continue;
        $link = rtrim($mm[0], ")]}>\"'");
        $by = (string) ($m['author']['username'] ?? '');

        if (setting('ios_link') === $link) {
            return ['ok' => true, 'changed' => false, 'link' => $link];
        }
        $ts = (new DateTime('now', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d H:i');
        set_setting('ios_link', $link);
        set_setting('ios_link_updated', $ts);
        return ['ok' => true, 'changed' => true, 'link' => $link, 'updated' => $ts, 'by' => $by];
    }

    return ['ok' => true, 'changed' => false, 'error' => 'ไม่พบลิงก์ iOS ใน channel'];
}
