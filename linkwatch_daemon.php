<?php
/**
 * linkwatch_daemon.php — รันค้างไว้ เช็ค Discord channel ทุก 30 วินาที
 * ใช้: C:\xampp\php\php.exe linkwatch_daemon.php
 * (ปิดด้วย Ctrl+C — ควรใส่ใน Task Scheduler / run on startup ถ้าจะให้รันตลอด)
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }

require __DIR__ . '/includes/bootstrap.php';
require_once ROOT . '/includes/linkwatch.php';

$interval = 30; // วินาที
echo '[' . date('Y-m-d H:i:s') . "] linkwatch daemon started — poll ทุก {$interval}s (Ctrl+C เพื่อหยุด)\n";

while (true) {
    try {
        $r = linkwatch_check();
        $ts = (new DateTime('now', new DateTimeZone('Asia/Bangkok')))->format('H:i:s');
        if (!empty($r['ok'])) {
            if (!empty($r['changed'])) {
                echo "[$ts] UPDATED -> {$r['link']} (โดย {$r['by']})\n";
            }
            // ไม่เปลี่ยน = เงียบ ไม่ต้องพิมพ์ทุก 30 วิ
        } else {
            echo "[$ts] error: {$r['error']}\n";
        }
    } catch (Throwable $e) {
        echo '[' . date('H:i:s') . '] exception: ' . $e->getMessage() . "\n";
    }
    sleep($interval);
}
