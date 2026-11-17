<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require ROOT . '/includes/layout.php';

$id = (int)($_GET['id'] ?? 0);
$e = row('SELECT * FROM events WHERE id=?', [$id]);
if (!$e || ($e['status'] === 'draft' && !is_admin())) { http_response_code(404); flash('ไม่พบกิจกรรมนี้', 'err'); redirect(url('events/')); }
$s = ev_state($e);

if (is_post()) {
    csrf_check();
    $u = require_login();
    $act = $_POST['do'] ?? '';
    $n = ev_count($id);
    if ($act === 'join') {
        if ($s !== 'upcoming' && $s !== 'live') flash('ปิดรับสมัครสำหรับกิจกรรมนี้แล้ว', 'err');
        elseif (ev_registered($id, $u['id'])) flash('คุณลงทะเบียนแล้ว', 'warn');
        elseif ($e['capacity'] > 0 && $n >= $e['capacity']) flash('กิจกรรมเต็มแล้ว', 'err');
        elseif (empty($u['game_name'])) flash('กรุณาลงทะเบียนเซิร์ฟเวอร์ก่อน (ใส่ชื่อในเกม)', 'err');
        else { q('INSERT INTO registrations(event_id,user_id,created_at) VALUES(?,?,?)', [$id, $u['id'], gmdate('Y-m-d H:i:s')]); flash('ลงทะเบียนสำเร็จ! แล้วพบกันในเกม'); }
    } elseif ($act === 'leave') {
        q('DELETE FROM registrations WHERE event_id=? AND user_id=?', [$id, $u['id']]); flash('ยกเลิกการลงทะเบียนแล้ว');
    }
    redirect(url('events/view?id=' . $id));
}

$u = me(); $n = ev_count($id); [$cn, $cc] = ev_cat($e['category']);
$joined = ev_registered($id, $u['id'] ?? null);
$full = $e['capacity'] > 0 && $n >= $e['capacity'];
$people = rows('SELECT u.* FROM registrations r JOIN users u ON u.id=r.user_id WHERE r.event_id=? ORDER BY r.created_at LIMIT 24', [$id]);
$startTs = strtotime($e['starts_at'] . ' UTC'); $endTs = strtotime($e['ends_at'] . ' UTC');

page_head($e['title'] . ' — ' . $c['brand_full'], ['active' => 'events', 'desc' => $e['summary']]);
?>
<main class="store-wrap"><div class="container">
<a class="back" href="<?=url('events/')?>">← กิจกรรมทั้งหมด</a>
<?=flash_html()?>
<div class="evdet" style="--cc:<?=e($cc)?>">
  <article>
    <div class="evm"><span class="chip" style="--cc:<?=e($cc)?>"><?=e($cn)?></span>
      <?php if($s==='live'):?><span class="live"><i></i>กำลังจัด</span><?php elseif($s==='cancelled'):?><span class="tagx">ยกเลิก</span><?php elseif($s==='ended'):?><span class="tagx">จบแล้ว</span><?php elseif($s==='draft'):?><span class="tagx">ฉบับร่าง</span><?php endif;?></div>
    <h1 class="stitle evtitle"><?=e($e['title'])?></h1>
    <p class="evlead"><?=e($e['summary'])?></p>
    <div class="prose"><?=nl2br(e($e['description']))?></div>
    <?php if($people):?>
    <h2 class="evh" style="margin-top:2.5rem">ใครจะไป <small><?=$n?></small></h2>
    <div class="faces"><?php foreach($people as $p):?><img src="<?=e(avatar_url($p,64))?>" alt="<?=e(user_name($p))?>" title="<?=e(user_name($p))?>" width="36" height="36" loading="lazy"><?php endforeach;?><?php if($n>count($people)):?><span class="more">+<?=$n-count($people)?></span><?php endif;?></div>
    <?php endif;?>
  </article>
  <aside class="evside">
    <dl class="facts">
      <div><dt><?=icon('cal')?>วันที่</dt><dd><?=e(fmt_when($e['starts_at']))?></dd></div>
      <div><dt><?=icon('clock')?>สิ้นสุด</dt><dd><?=e(fmt_full($e['ends_at']))?></dd></div>
      <div><dt><?=icon('pin')?>สถานที่</dt><dd><?=e($e['location']?:'จะแจ้งภายหลัง')?></dd></div>
      <?php if($e['reward']!==''):?><div><dt><?=icon('gift')?>รางวัล</dt><dd><?=e($e['reward'])?></dd></div><?php endif;?>
      <div><dt><?=icon('users')?>ที่นั่ง</dt><dd><?=$e['capacity']>0?"$n / {$e['capacity']}":"$n คนลงทะเบียน · ไม่จำกัด"?></dd></div>
    </dl>
    <?php if($s==='upcoming'):?><div class="cd" data-t="<?=$startTs?>"><small>เริ่มในอีก</small><b id="cd">--</b></div>
    <?php elseif($s==='live'):?><div class="cd now" data-t="<?=$endTs?>"><small>สิ้นสุดในอีก</small><b id="cd">--</b></div><?php endif;?>

    <?php if($s==='upcoming'||$s==='live'):?>
      <?php if(!$u):?>
        <a class="btn btn-dc btn-lg block" href="<?=url('auth/login')?>?next=<?=urlencode($_SERVER['REQUEST_URI'])?>"><?=dc_icon(18)?><span>เข้าสู่ระบบเพื่อสมัคร</span></a>
      <?php elseif(empty($u['game_name'])):?>
        <a class="btn btn-lg block" href="<?=url('account/')?>">ลงทะเบียนเซิร์ฟเวอร์ก่อน</a>
        <p class="muted" style="font-size:.8rem;margin-top:.5rem">ต้องใส่ชื่อในเกมก่อนจึงจะเข้าร่วมกิจกรรมได้</p>
      <?php elseif($joined):?>
        <div class="joined">✓ คุณลงทะเบียนแล้ว</div>
        <form method="post" onsubmit="return confirm('ยกเลิกการลงทะเบียนของคุณ?')"><?=csrf_field()?><input type="hidden" name="do" value="leave"><button class="btn btn-ghost btn-sm block">ยกเลิกการลงทะเบียน</button></form>
      <?php elseif($full):?>
        <button class="btn btn-lg block" disabled>กิจกรรมเต็มแล้ว</button>
      <?php else:?>
        <form method="post"><?=csrf_field()?><input type="hidden" name="do" value="join"><button class="btn btn-lg block">ลงทะเบียน</button></form>
      <?php endif;?>
    <?php elseif($s==='cancelled'):?><p class="fine">กิจกรรมนี้ถูกยกเลิกโดยทีมงาน</p>
    <?php else:?><p class="fine">กิจกรรมนี้จบลงแล้ว</p><?php endif;?>
  </aside>
</div></div></main>
<?php page_foot(['scripts'=>'<script>
const cd=document.querySelector(".cd");if(cd){const t=+cd.dataset.t*1000,o=document.getElementById("cd");const p=n=>String(n).padStart(2,"0");
(function f(){let s=Math.max(0,Math.floor((t-Date.now())/1000));if(!s){location.reload();return}const d=Math.floor(s/86400),h=Math.floor(s%86400/3600),m=Math.floor(s%3600/60);o.textContent=(d?d+" วัน ":"")+p(h)+":"+p(m)+":"+p(s%60);setTimeout(f,1000)})()}
</script>']);
