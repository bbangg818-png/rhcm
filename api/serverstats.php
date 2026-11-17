<?php
/**
 * Game Server Stats API
 * Game server POST สถิติผู้เล่นทุก ~30 วินาที (หลายเซิร์ฟเวอร์ได้ แต่ละตัวส่ง name ของตัวเอง)
 *   POST /api/serverstats.php?key=<registry_key>  body: {"players":N,"room":"...","name":"..."}
 *   GET  /api/serverstats.php?key=<registry_key>  ดูค่าสะสมล่าสุด
 *
 * เก็บสถิติแยกตามชื่อเซิร์ฟเวอร์ (game_stats_map) แล้วรวมเป็น game_stats
 * เอนทรีที่ไม่อัปเดตเกิน 120 วินาทีถือว่าปิดแล้ว ไม่นับรวม
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

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $in = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($in)) { http_response_code(400); echo json_encode(['error' => 'bad json']); exit; }

    $name = mb_substr((string)($in['name'] ?? ''), 0, 40);
    if ($name === '') $name = 'server';

    $map = json_decode(setting('game_stats_map', '{}'), true);
    if (!is_array($map)) $map = [];
    $map[$name] = [
        'players' => max(0, min(500, (int)($in['players'] ?? 0))),
        'room'    => mb_substr((string)($in['room'] ?? ''), 0, 12),
        'code'    => mb_substr((string)($in['code'] ?? ''), 0, 12),
        'name'    => $name,
        'at'      => time(),
    ];
    set_setting('game_stats_map', json_encode($map, JSON_UNESCAPED_UNICODE));

    // รวมเฉพาะเซิร์ฟเวอร์ที่ยังส่งสถิติอยู่ (ไม่เกิน 120 วินาที)
    $now = time();
    $live = array_filter($map, fn($s) => ($now - (int)($s['at'] ?? 0)) <= 120);
    $total = 0;
    $top = null;
    foreach ($live as $s) {
        $total += (int)$s['players'];
        if ($top === null || (int)$s['players'] > (int)$top['players']) $top = $s;
    }
    $stats = [
        'players' => $total,
        'room'    => $top['room'] ?? '',
        'code'    => $top['code'] ?? '',
        'name'    => count($live) > 1 ? count($live) . ' servers' : ($top['name'] ?? 'server'),
        'servers' => count($live),
        'at'      => $now,
    ];
    set_setting('game_stats', json_encode($stats, JSON_UNESCAPED_UNICODE));

    echo json_encode(['ok' => true]);
    exit;
}

// GET: คืน game_stats รูปแบบเดิม (fields เดิม + servers = map แยกแต่ละเซิร์ฟเวอร์)
$out = json_decode(setting('game_stats', 'null'), true);
if (!is_array($out)) $out = [];
$map = json_decode(setting('game_stats_map', '{}'), true);
if (is_array($map)) {
    $now = time();
    $map = array_filter($map, fn($s) => ($now - (int)($s['at'] ?? 0)) <= 120);
    if ($map) $out['servers'] = $map;
}
echo $out ? json_encode($out, JSON_UNESCAPED_UNICODE) : 'null';
