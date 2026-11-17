<?php
/**
 * linkwatch.php — เช็คข้อความล่าสุดใน Discord channel แล้วอัปเดตลิงก์ iOS
 * เรียก: /api/linkwatch.php?key=REGISTRY_KEY   (หรือเรียกตอน login เป็น admin)
 * ต้องใส่ discord_oauth.user_token ใน config.php (token บัญชีที่อยู่ใน server นั้น)
 */
require __DIR__ . '/../includes/bootstrap.php';
require_once ROOT . '/includes/linkwatch.php';
header('Content-Type: application/json; charset=utf-8');

$u = me();
$byKey = isset($_GET['key']) && hash_equals((string) $c['registry_key'], (string) $_GET['key']);
if (!($byKey || ($u && !empty($u['is_admin'])))) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
}

echo json_encode(linkwatch_check(), JSON_UNESCAPED_UNICODE);
