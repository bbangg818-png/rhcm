<?php
/**
 * ดาวน์โหลดไฟล์ rhcm-data.zip ที่สร้างไว้โดย download-data.php
 *
 * ทำไมต้องมีไฟล์นี้แยกจาก data/rhcm-data.zip ตรง ๆ:
 * data/.htaccess สั่ง "Require all denied" กับทั้งโฟลเดอร์ data/ (ถูกต้องแล้ว
 * เพราะมี session files อยู่ในนั้น) ถ้าลิงก์ชี้ไปตรง ๆ Apache จะตอบ 403 (HTML)
 * แทนไฟล์ zip จริง — ไฟล์นี้เลยอ่านไฟล์จากฝั่งเซิร์ฟเวอร์เอง (PHP อ่านได้
 * ต่อให้ Apache บล็อก static access) แล้วส่งออกไปพร้อม header ที่ถูกต้อง
 *
 * ไฟล์ zip ใหญ่ (~170MB) จึงต้อง:
 *  - ปลด max_execution_time (ไม่งั้นสคริปต์โดนฆ่ากลางทางตอนไฟล์ใหญ่/เน็ตช้า)
 *  - ปล่อย session lock ก่อนเริ่มส่งไฟล์ (ไม่งั้น request "Resume" ที่ใช้
 *    session เดียวกันจะค้างรอจนไทม์เอาต์ -> "Site wasn't available")
 *  - รองรับ HTTP Range request เพื่อให้ปุ่ม Resume ของเบราว์เซอร์ทำงานได้จริง
 */
ini_set('zlib.output_compression', 'Off');
require __DIR__ . '/includes/bootstrap.php';

$u = require_login();

// ปล่อย session lock ทันที หลังเช็คสิทธิ์เสร็จ — ไม่ต้องถือไว้ตลอดการส่งไฟล์
session_write_close();

$path = ROOT . '/data/rhcm-data.zip';
if (!is_file($path)) {
    http_response_code(404);
    exit('ไม่พบไฟล์ ZIP กรุณากดสร้างไฟล์ใหม่อีกครั้ง');
}

// เคลียร์ output buffer ทั้งหมด (bootstrap.php เปิด ob_start ไว้) ป้องกันขยะปนกับไฟล์ binary
while (ob_get_level() > 0) {
    ob_end_clean();
}

// ไฟล์ใหญ่ + เน็ตช้า อาจใช้เวลานานกว่า max_execution_time ปกติ (30s) — ปลดให้ไม่จำกัด
@set_time_limit(0);
@ignore_user_abort(true);
// กัน Apache mod_deflate บีบอัดซ้อน response นี้ (ถ้าบีบแล้วไม่ปรับ Content-Length
// ให้ตรง ไฟล์ปลายทางจะเพี้ยน แตกไม่ได้ ทั้งที่ดาวน์โหลด "สำเร็จ")
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', '1');
}
header('Content-Encoding: identity');

$filename = 'rhcm-data.zip';
$size     = filesize($path);
$start    = 0;
$end      = $size - 1;

header('Content-Description: File Transfer');
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Transfer-Encoding: binary');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('Accept-Ranges: bytes'); // บอกเบราว์เซอร์ว่า resume/โหลดบางส่วนได้

$isRange = false;
if (!empty($_SERVER['HTTP_RANGE'])) {
    // รูปแบบ: bytes=START-END
    if (preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
        $isRange = true;
        if ($m[1] !== '') $start = (int)$m[1];
        if ($m[2] !== '') $end = (int)$m[2];
        if ($end > $size - 1) $end = $size - 1;
        if ($start > $end || $start < 0) {
            http_response_code(416); // Range Not Satisfiable
            header('Content-Range: bytes */' . $size);
            exit;
        }
    }
}

$length = $end - $start + 1;

if ($isRange) {
    http_response_code(206); // Partial Content
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
} else {
    http_response_code(200);
}
header('Content-Length: ' . $length);

$fp = fopen($path, 'rb');
if ($fp === false) {
    http_response_code(500);
    exit('เปิดไฟล์ไม่สำเร็จ');
}
fseek($fp, $start);

$chunkSize  = 1024 * 1024; // 1MB ต่อรอบ กันหน่วยความจำบวมกับไฟล์ใหญ่
$bytesLeft  = $length;
while ($bytesLeft > 0 && !feof($fp)) {
    if (connection_aborted()) break;
    $read = ($bytesLeft > $chunkSize) ? $chunkSize : $bytesLeft;
    echo fread($fp, $read);
    flush();
    $bytesLeft -= $read;
}
fclose($fp);
exit;
