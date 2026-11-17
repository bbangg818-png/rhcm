<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$_SESSION = [];
session_destroy();
session_start();
clear_uid_cookie();
flash('ออกจากระบบแล้ว');
redirect(url(''));
