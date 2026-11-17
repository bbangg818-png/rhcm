<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$admin = require_admin();

function admin_head(string $title, string $active): void {
    global $c, $root, $admin;
    $nav = ['index' => ['ภาพรวม', 'M3 12l9-9 9 9M5 10v10h14V10'], 'events' => ['กิจกรรม', 'M3 4h18v18H3zM16 2v4M8 2v4M3 10h18'],
            'content' => ['จัดการเนื้อหา', 'M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z'],
            'users' => ['ผู้ใช้', 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8'], 'gangs' => ['แก็ง', 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10zM9 12l2 2 4-4'], 'settings' => ['ตั้งค่าเว็บ', 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 0 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 0 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 0 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 0 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z']];
    ?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="color-scheme" content="dark">
    <meta name="robots" content="noindex,nofollow">
    <title><?=e($title)?> · แอดมิน</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@400;500;600&family=IBM+Plex+Mono&display=swap">
    <link rel="stylesheet" href="<?=url('css/admin.css')?>?v=3">
</head>
<body>
<aside class="side" id="side">
  <a class="sb" href="<?=url('admin/')?>">
    <span class="mark"><?=logo_html($c,$root,BASE.'/')?></span>
    <span><b><?=e($c['brand'])?></b><small>หลังบ้าน</small></span>
  </a>
  <nav>
    <?php foreach($nav as $k=>[$n,$d]):?>
    <a href="<?=url('admin/'.($k==='index'?'':$k))?>" class="<?=$active===$k?'on':''?>">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="<?=$d?>"/></svg>
      <?=$n?>
    </a>
    <?php endforeach;?>
  </nav>
  <div class="sf">
    <a href="<?=url('')?>" target="_blank">ดูหน้าเว็บ ↗</a>
    <div class="me">
      <img src="<?=e(avatar_url($admin,48))?>" alt="" width="28" height="28">
      <span><?=e(user_name($admin))?></span>
      <a href="<?=url('auth/logout')?>" title="ออกจากระบบ">ออก</a>
    </div>
  </div>
</aside>
<div class="main">
  <header class="top">
    <button class="menu" id="menu" aria-label="เมนู">☰</button>
    <h1><?=e($title)?></h1>
    <div class="topr" id="topr"></div>
  </header>
  <div class="body">
    <?php foreach(flashes() as [$t,$m]):?>
    <div class="fl fl-<?=e($t)?>"><?=e($m)?></div>
    <?php endforeach;?>
    <?php
}

function admin_foot(): void {
    $dcUsers = array_map(fn($r) => [
        'u' => $r['username'],
        'd' => $r['display_name'] ?? '',
        'g' => $r['game_name'] ?? '',
        'n' => mb_strtolower(($r['display_name'] ?? '') . ' ' . $r['username'] . ' ' . ($r['game_name'] ?? '') . ' ' . ($r['discord_id'] ?? ''))
    ], rows('SELECT username,display_name,game_name,discord_id FROM users ORDER BY username LIMIT 2000'));
    ?>
    <script type="application/json" id="dcUsers"><?=json_encode($dcUsers, JSON_UNESCAPED_UNICODE)?></script>
    <script>
    document.getElementById('menu').onclick=()=>document.getElementById('side').classList.toggle('open');
    document.querySelectorAll('[data-confirm]').forEach(f=>f.addEventListener('submit',e=>{if(!confirm(f.dataset.confirm))e.preventDefault()}));
    </script>
    <script src="<?=url('js/app.js')?>?v=6"></script>
</body>
</html>
<?php
}

function pill(string $t, string $col): string {
    return '<span class="pill" style="--c:' . e($col) . '">' . e($t) . '</span>';
}

function ev_state_pill(string $s): string {
    return pill(['live'=>'กำลังจัด','upcoming'=>'กำลังจะมาถึง','ended'=>'จบแล้ว','cancelled'=>'ยกเลิก','draft'=>'ฉบับร่าง'][$s], ['live'=>'#ef4444','upcoming'=>'#34d399','ended'=>'#6b7f94','cancelled'=>'#6b7f94','draft'=>'#f59e0b'][$s]);
}