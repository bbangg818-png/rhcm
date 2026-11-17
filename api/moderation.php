<?php
/**
 * Game Server Moderation API
 * เว็บตั้ง "เตะ" ผู้เล่น → game server มาดึงรายการแล้วเตะเอง
 *
 *   GET /api/moderation.php?key=<registry_key>
 *   → {"kicks": [{"name": "GAME_NAME", "until": 1730000000}, ...]}
 *
 * รายการเก็บใน settings 'mod_kicks' (JSON) — หมดอายุเองตาม until
 */
require __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$cfg = require ROOT . '/config.php';
$expected = (string)($cfg['registry_key'] ?? '');
$key = (string)($_GET['key'] ?? '');

if ($expected === '' || $key === '' || !hash_equals($expected, $key)) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

$raw = setting('mod_kicks', '[]');
$kicks = json_decode($raw, true);
if (!is_array($kicks)) $kicks = [];

$now = time();
// เก็บเฉพาะอันที่ยังไม่หมดอายุ
$kicks = array_values(array_filter($kicks, fn($k) => is_array($k) && (int)($k['until'] ?? 0) > $now));
if (count($kicks) !== count(json_decode($raw, true) ?: [])) {
    set_setting('mod_kicks', json_encode(array_values($kicks), JSON_UNESCAPED_UNICODE));
}

// แบนในเกม (ถาวร — เอาออกได้จากหน้าแอดมิน)
$bans = json_decode(setting('mod_bans', '[]'), true);
if (!is_array($bans)) $bans = [];
$bans = array_values(array_filter($bans, fn($b) => is_array($b) && trim((string)($b['name'] ?? '')) !== ''));

echo json_encode(['kicks' => $kicks, 'bans' => $bans], JSON_UNESCAPED_UNICODE);
