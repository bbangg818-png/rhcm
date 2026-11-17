<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require ROOT . '/includes/layout.php';

$next = safe_next((string)($_GET['next'] ?? ($_SESSION['next'] ?? ($_COOKIE['nc_next'] ?? ''))));
if (me()) redirect($next);

/* Modo de teste local (config 'dev_login' => true) */
if (!empty($c['dev_login']) && isset($_GET['dev'])) {
    $n = preg_replace('/[^a-z0-9_]/i', '', (string)$_GET['dev']) ?: 'tester';
    login_user(['id' => 'dev_' . $n, 'username' => $n, 'global_name' => ucfirst($n)]);
    redirect($next);
}

if (discord_ready() && !isset($_GET['info'])) {
    $_SESSION['next'] = $next;
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;
    // cookie fallback — session อาจไม่ persist บน serverless
    set_temp_cookie('nc_state', $state);
    set_temp_cookie('nc_next', $next);
    redirect('https://discord.com/oauth2/authorize?' . http_build_query([
        'client_id' => $c['discord_oauth']['client_id'],
        'redirect_uri' => discord_redirect_uri(),
        'response_type' => 'code',
        'scope' => 'identify email guilds.join',
        'state' => $state,
        'prompt' => 'none',
    ]));
}

page_head('เข้าสู่ระบบ — ' . $c['brand_full'], ['active' => '']);
?>
<main class="store-wrap"><div class="container">
<div class="authbox">
  <h1 class="stitle" style="font-size:1.9rem">เข้าสู่ระบบ</h1>
  <?=flash_html()?>
  <?php if(!discord_ready()):?>
    <p class="ssub">ยังไม่เข้าสู่ระบบด้วย Discord</p>
    <?php if(!empty($c['dev_login'])):?>
      <div class="authbtns"><a class="btn btn-dc btn-lg" href="?dev=admin&amp;next=<?=urlencode($next)?>">เข้าสู่ระบบในฐานะแอดมิน (ทดสอบ)</a><a class="btn btn-ghost btn-lg" href="?dev=jogador&amp;next=<?=urlencode($next)?>">เข้าสู่ระบบในฐานะผู้เล่น (ทดสอบ)</a></div>
    <?php endif;?>
  <?php else:?>
    <p class="ssub">ใช้บัญชี Discord ของคุณเพื่อสมัครกิจกรรม และติดตามอัพเดทใหม่ๆ</p>
    <div class="authbtns"><a class="btn btn-dc btn-lg" href="https://discord.com/oauth2/authorize?<?=http_build_query(['client_id' => $c['discord_oauth']['client_id'], 'redirect_uri' => discord_redirect_uri(), 'response_type' => 'code', 'scope' => 'identify email guilds.join', 'state' => bin2hex(random_bytes(16)), 'prompt' => 'none'])?>"><?=dc_icon(20)?><span>ดำเนินการต่อด้วย Discord</span></a></div>
  <?php endif;?>
  <p class="fine">เราขอเพียงชื่อผู้ใช้ อวตาร และอีเมลของคุณเท่านั้น ไม่มีการเข้าถึงรหัสผ่านของคุณ</p>
</div></div></main>
<?php page_foot();
