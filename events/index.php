<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require ROOT . '/includes/layout.php';

$filter = $_GET['f'] ?? 'all';
$now = gmdate('Y-m-d H:i:s');
$all = rows("SELECT e.*, (SELECT COUNT(*) FROM registrations r WHERE r.event_id=e.id) AS n FROM events e WHERE status IN ('published','cancelled') ORDER BY starts_at ASC");
$live = $upcoming = $ended = [];
foreach ($all as $e) {
    $s = ev_state($e);
    if ($s === 'live') $live[] = $e;
    elseif ($s === 'upcoming') $upcoming[] = $e;
    else $ended[] = $e;
}
$ended = array_reverse($ended);
$uid = me()['id'] ?? null;
$mine = $uid ? array_column(rows('SELECT event_id FROM registrations WHERE user_id=?', [$uid]), 'event_id') : [];

function ev_row(array $e, array $mine): void {
    $s = ev_state($e); [$cn, $cc] = ev_cat($e['category']); $d = local_dt($e['starts_at']);
    $full = $e['capacity'] > 0 && $e['n'] >= $e['capacity'];
    ?>
<a class="evr is-<?=$s?>" href="<?=url('events/view?id='.$e['id'])?>" style="--cc:<?=e($cc)?>" data-cat="<?=e($e['category'])?>">
  <div class="evd"><b><?=$d->format('d')?></b><span><?=pt_month((int)$d->format('n'))?></span><small><?=pt_wday((int)$d->format('w'))?></small></div>
  <div class="evb">
    <div class="evm"><span class="chip" style="--cc:<?=e($cc)?>"><?=e($cn)?></span>
      <?php if($s==='live'):?><span class="live"><i></i>กำลังจัด</span><?php elseif($s==='cancelled'):?><span class="tagx">ยกเลิก</span><?php elseif($s==='ended'):?><span class="tagx">จบแล้ว</span><?php endif;?>
      <?php if(in_array($e['id'],$mine)):?><span class="tagok">✓ ลงทะเบียนแล้ว</span><?php endif;?></div>
    <h3><?=e($e['title'])?></h3>
    <p><?=e($e['summary'])?></p>
    <div class="evf"><span><?=icon('clock')?><?=e($d->format('H:i'))?></span><span><?=icon('pin')?><?=e($e['location'])?></span><?php if($e['reward']!==''):?><span><?=icon('gift')?><?=e($e['reward'])?></span><?php endif;?></div>
  </div>
  <div class="evs"><?php if($e['capacity']>0):?><div class="cap"><span><?=$e['n']?>/<?=$e['capacity']?></span><div class="bar"><i style="width:<?=min(100,round($e['n']/$e['capacity']*100))?>%"></i></div><small><?=$full?'เต็มแล้ว':'ที่นั่ง'?></small></div><?php else:?><div class="cap"><span><?=$e['n']?></span><small>ผู้ลงทะเบียน</small></div><?php endif;?></div>
</a>
<?php }

page_head('กิจกรรม — ' . $c['brand_full'], ['active' => 'events', 'desc' => 'กำหนดการกิจกรรมของ ' . $c['brand_full'] . ': แข่งรถ แอคชัน สุ่มรางวัล และค่ำคืนปาร์ตี้']);
?>
<main class="store-wrap"><div class="container">
<div class="shead"><h1 class="stitle">กำหนดการ <span class="accent">กิจกรรม</span></h1>
<p class="ssub">เวลาแสดงตามเขตเวลา <strong>กรุงเทพฯ</strong> เข้าสู่ระบบด้วย Discord เพื่อสมัคร</p></div>
<?=flash_html()?>

<?php if($live):?>
<section class="evsec"><h2 class="evh"><span class="live"><i></i>กำลังจัด</span> กำลังเกิดขึ้นตอนนี้</h2>
<div class="evlist"><?php foreach($live as $e) ev_row($e,$mine);?></div></section>
<?php endif;?>

<section class="evsec">
<div class="evtools"><h2 class="evh">กิจกรรมที่จะมาถึง <small><?=count($upcoming)?></small></h2>
<div class="chips" id="catf" role="group" aria-label="กรองตามหมวดหมู่"><button class="on" data-c="">ทั้งหมด</button><?php foreach(EVENT_CATS as $k=>[$n,$col]):?><button data-c="<?=$k?>" style="--cc:<?=$col?>"><?=e($n)?></button><?php endforeach;?></div></div>
<div class="evlist" id="evup"><?php foreach($upcoming as $e) ev_row($e,$mine);?></div>
<?php if(!$upcoming):?><div class="empty"><strong>ยังไม่มีกิจกรรมที่กำหนดไว้</strong><span>เข้าร่วม Discord เพื่อดูว่าอะไรจะมาถึง</span></div><?php endif;?>
<div class="empty" id="evnone" hidden><strong>ไม่มีในหมวดนี้</strong><span>ลองตัวกรองอื่นดู</span></div>
</section>

<?php if($ended):?>
<section class="evsec"><h2 class="evh">จบแล้ว</h2>
<div class="evlist"><?php foreach(array_slice($ended,0,5) as $e) ev_row($e,$mine);?></div></section>
<?php endif;?>
</div></main>
<?php page_foot(['scripts' => '<script>
const f=document.getElementById("catf"),L=[...document.querySelectorAll("#evup .evr")],N=document.getElementById("evnone");
if(f)f.onclick=e=>{const b=e.target.closest("button");if(!b)return;f.querySelectorAll("button").forEach(x=>x.classList.toggle("on",x===b));let n=0;L.forEach(r=>{const s=!b.dataset.c||r.dataset.cat===b.dataset.c;r.hidden=!s;n+=s});N.hidden=n>0||!L.length};
</script>']);
