<?php
/** Layout público compartilhado: page_head() / page_foot() */
function page_head(string $title, array $o = []): void {
    global $c, $root, $L;
    $home = !empty($o['home']); $active = $o['active'] ?? '';
    $desc = $o['desc'] ?? ($c['brand_full'] . ' — ดาวน์โหลดเกมและกิจกรรมชุมชน');
    $u = me(); $ann = trim((string)setting('announcement', ''));
    $heroOk = asset_exists($c['hero_image'], $root);
    $anchor = fn($h) => ($home ? '' : url('')) . $h;
    ?><!DOCTYPE html>
<html lang="th"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="color-scheme" content="dark"><meta name="theme-color" content="#070d0a">
<title><?=e($title)?></title>
<meta name="description" content="<?=e($desc)?>">
<?php if(asset_exists($c['logo'],$root)):?><link rel="icon" href="<?=e(url($c['logo']))?>"><?php endif;?>
<?=$o['head'] ?? ''?>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;700;800&family=Plus+Jakarta+Sans:wght@400;500;700&family=Space+Mono&family=Chakra+Petch:wght@500;600&family=IBM+Plex+Sans+Thai:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="<?=url('css/style.css')?>?v=4">
<link rel="stylesheet" href="<?=url('css/app.css')?>?v=4">
<?php if($heroOk):?><style>:root{--hero-img:url('<?=e(url($c['hero_image']))?>')}</style><?php endif;?>
</head>
<body class="<?=$home?'is-home':'is-inner'?><?=$ann!==""?' has-annc':''?>">
<?php if($home):?>
<div class="prog" aria-hidden="true"><div id="prog"></div></div>
<div class="pbg" aria-hidden="true"><div class="pl pgrid" data-s=".05"></div><div class="pl pcity" data-s=".03"></div><div class="pl orb o1" data-s=".02"></div><div class="pl orb o2" data-s="-.03"></div></div>
<?php endif;?>
<?php if($ann!==''):?><div class="annc"><span class="annc-tag">ประกาศ</span><span><?=e($ann)?></span></div><?php endif;?>
<header class="hdr<?=$home?'':' show'?>" id="hdr"><div class="container hrow">
<a class="brand" href="<?=url('')?>"><span class="mark"><?=logo_html($c,$root,BASE.'/')?></span></a>
<nav class="nav" id="nav">
<a href="<?=url('')?>" <?=$active==='home'?'class="on"':''?>>หน้าแรก</a>
<a href="<?=url('events/')?>" <?=$active==='events'?'class="on"':''?>>กิจกรรม</a>
<a href="<?=url('gangs/')?>" <?=$active==='gangs'?'class="on"':''?>>แก็ง</a>
<a href="<?=$anchor('#download')?>">ดาวน์โหลด</a>
</nav>
<div class="hacts">
<?php if($u):?>
  <div class="um" id="um">
    <button class="um-btn" id="umb" aria-haspopup="menu" aria-expanded="false"><img src="<?=e(avatar_url($u,64))?>" alt="" width="28" height="28"><span><?=e(user_name($u))?></span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="6 9 12 15 18 9"/></svg></button>
    <div class="um-pop" role="menu">
      <a href="<?=url('account/')?>" role="menuitem">บัญชีของฉัน</a>
      <?php if(is_admin()):?><a href="<?=url('admin/')?>" role="menuitem" class="adm">แดชบอร์ดแอดมิน</a><?php endif;?>
      <a href="<?=url('auth/logout')?>" role="menuitem">ออกจากระบบ</a>
    </div>
  </div>
<?php else:?>
  <a class="btn btn-sm btn-dc" href="<?=url('auth/login')?>?next=<?=urlencode($_SERVER['REQUEST_URI']??'/')?>"><?=dc_icon(16)?><span>เข้าสู่ระบบ</span></a>
<?php endif;?>
</div>
<button class="burger" id="burger" aria-label="เปิดเมนู" aria-expanded="false">☰</button>
</div></header>
<?php
}

function flash_html(): string {
    $f = flashes();
    if (!$f) return '';
    return '<script>window.__flashMessages = ' . json_encode($f, JSON_UNESCAPED_UNICODE) . ';</script>';
}

function page_foot(array $o = []): void {
    global $c, $root, $L;
    $base = BASE . '/';
    include $root . '/includes/footer.php';
    ?>
<script src="<?=url('js/app.js')?>?v=4"></script>
<?php if(!empty($o['home'])):?><script src="<?=url('js/main.js')?>?v=7"></script><?php endif;?>
<?=$o['scripts'] ?? ''?>
<script>
document.addEventListener('contextmenu', e => e.preventDefault());
document.addEventListener('dragstart', e => e.preventDefault());
</script>
</body></html>
<?php
}
