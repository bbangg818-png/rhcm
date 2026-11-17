<?php
/* Front controller — รับ request แล้ว map ไปยังไฟล์ PHP จริง (URL สะอาด ไม่มี .php) */
$root = __DIR__;
$uri  = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH) ?: '/';
$path = '/' . ltrim($path, '/');
if ($path === '' || $path === false) $path = '/';

if (preg_match('#^/(data|includes|router\.php$)#', $path) || preg_match('#/config\.php$#', $path)) {
    http_response_code(404); exit('404 Not Found');
}

$file = $root . $path;
if (is_dir($file)) $file = rtrim($file, '/') . '/index.php';
elseif (!str_ends_with(strtolower($file), '.php') && !is_file($file)) $file .= '.php';
if (!is_file($file) || !str_ends_with(strtolower($file), '.php')) {
    http_response_code(404); exit('404 Not Found');
}
$real = realpath($file);
$rr   = realpath($root);
if ($real === false || $rr === false || !str_starts_with($real, $rr)) {
    http_response_code(404); exit('404 Not Found');
}
chdir(dirname($real));
require $real;
