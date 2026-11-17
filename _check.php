<?php
$c = require 'C:/xampp/htdocs/config.php';
$d = $c['db'];
$p = new PDO('mysql:host=' . $d['host'] . ';port=' . $d['port'] . ';dbname=' . $d['name'] . ';charset=utf8mb4', $d['user'], $d['pass']);
echo "events: ", $p->query("SELECT COUNT(*) FROM events")->fetchColumn(), "\n";
echo "seeded: ", var_export($p->query("SELECT v FROM settings WHERE k='seeded'")->fetchColumn(), true), "\n";
echo "titles:\n";
foreach ($p->query("SELECT id,title,starts_at FROM events ORDER BY id DESC LIMIT 15") as $r) {
    echo $r['id'], ' | ', mb_substr($r['title'], 0, 30), ' | ', $r['starts_at'], "\n";
}
