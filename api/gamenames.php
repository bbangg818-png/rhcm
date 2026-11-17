<?php
/**
 * Game Name Registry API
 * Game server ดึงลิสต์ชื่อในเกมที่ลงทะเบียนไว้เพื่อตรวจคนเข้าเซิร์ฟเวอร์
 *
 *   GET /api/gamenames.php?key=<registry_key>
 *   → {"names": ["NAME1", "NAME2", ...]}
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

$names = rows("SELECT game_name FROM users WHERE game_name <> ''");
echo json_encode(['names' => array_map(fn($r) => $r['game_name'], $names)], JSON_UNESCAPED_UNICODE);
