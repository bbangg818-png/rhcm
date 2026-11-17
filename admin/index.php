<?php
require __DIR__ . '/_layout.php';
$nowU = gmdate('Y-m-d H:i:s');
$st = [
 ['ผู้ใช้ทั้งหมด', val('SELECT COUNT(*) FROM users'), '+' . val("SELECT COUNT(*) FROM users WHERE created_at > ?", [gmdate('Y-m-d H:i:s', time()-7*86400)]) . ' ใน 7 วัน'],
 ['กิจกรรมที่กำลังจะมาถึง', val("SELECT COUNT(*) FROM events WHERE status='published' AND ends_at > ?", [$nowU]), val("SELECT COUNT(*) FROM events WHERE status='published' AND starts_at<=? AND ends_at>?", [$nowU,$nowU]) . ' กำลังจัดอยู่'],
 ['กิจกรรมทั้งหมด', val('SELECT COUNT(*) FROM events'), 'รวมฉบับร่างและยกเลิก'],
];
$ev = rows("SELECT e.*,(SELECT COUNT(*) FROM registrations r WHERE r.event_id=e.id) n FROM events e WHERE ends_at > ? AND status!='cancelled' ORDER BY starts_at LIMIT 6", [$nowU]);
admin_head('ภาพรวม', 'index'); ?>
<div class="stats"><?php foreach($st as [$l,$v,$s]):?><div class="stat"><small><?=$l?></small><b><?=e((string)$v)?></b><span><?=e($s)?></span></div><?php endforeach;?></div>
<section class="panel"><div class="ph"><h2>สถานะที่เก็บข้อมูล</h2></div>
<table><tbody>
<tr><td>DATA_DIR</td><td><code><?=e($DATA_DIR)?></code></td></tr>
<tr><td>เขียนได้</td><td><?=$DATA_DIR && is_writable($DATA_DIR)?'<span class="pill" style="--c:#34d399">ได้</span>':'<span class="pill" style="--c:#ef4444">ไม่ได้ (ข้อมูลจะหาย!)</span>'?></td></tr>
<tr><td>ไฟล์ DB</td><td><code><?=e($DATA_DIR.'/app.sqlite')?></code> <?=is_file($DATA_DIR.'/app.sqlite')?'':'<span class="muted">(ยังไม่มี)</span>'?></td></tr>
<tr><td>NC_DATA_DIR env</td><td><code><?=e(getenv('NC_DATA_DIR') ?: '(ไม่ได้ตั้ง)')?></code></td></tr>
</tbody></table></section>
<section class="panel"><div class="ph"><h2>กิจกรรมที่กำลังจะมาถึง</h2><a class="btn sm" href="events?id=0">+ สร้างกิจกรรม</a></div>
<table><thead><tr><th>กิจกรรม</th><th>เวลา</th><th class="n">ลงชื่อ</th><th>สถานะ</th></tr></thead><tbody>
<?php foreach($ev as $e):?><tr><td><a href="events?id=<?=$e['id']?>"><?=e($e['title'])?></a></td><td><?=e(fmt_full($e['starts_at']))?></td><td class="n"><?=$e['n']?><?=$e['capacity']?'/'.$e['capacity']:''?></td><td><?=ev_state_pill(ev_state($e))?></td></tr><?php endforeach;?>
<?php if(!$ev):?><tr><td colspan="4" class="empty">ยังไม่มีกิจกรรม</td></tr><?php endif;?></tbody></table></section>
<?php admin_foot();
