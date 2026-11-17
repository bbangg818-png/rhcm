<?php
/**
 * Admin Game Names API
 * Game server ดึงลิสต์ชื่อในเกมของผู้ใช้ที่มีสิทธิ์ admin (role='admin')
 * ไว้ใช้คัดเลือก host ประจำเซสชัน — เฉพาะชื่อในลิสต์นี้เท่านั้นที่เป็น host pin ได้
 *
 *   GET /api/admins.php?key=<registry_key>
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

$names = rows("SELECT game_name FROM users WHERE role='admin' AND game_name <> ''");
echo json_encode(['names' => array_map(fn($r) => $r['game_name'], $names)], JSON_UNESCAPED_UNICODE);
