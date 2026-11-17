<?php
require __DIR__ . '/_layout.php';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

/* export CSV dos inscritos */
if ($id && isset($_GET['export'])) {
    $ev = row('SELECT * FROM events WHERE id=?', [$id]);
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="registrants-event-'.$id.'.csv"');
    $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF"); fputcsv($o, ['discord_id', 'username', 'display_name', 'registered_at']);
    foreach (rows('SELECT u.discord_id,u.username,u.display_name,r.created_at FROM registrations r JOIN users u ON u.id=r.user_id WHERE r.event_id=? ORDER BY r.created_at', [$id]) as $r) fputcsv($o, [$r['discord_id'], $r['username'], $r['display_name'], fmt_full($r['created_at'])]);
    exit;
}

if (is_post()) {
    csrf_check();
    $a = $_POST['do'] ?? '';
    if ($a === 'save') {
        $eid = (int)$_POST['id'];
        $s = input_to_utc((string)$_POST['starts_at']); $en = input_to_utc((string)$_POST['ends_at']);
        $title = trim((string)$_POST['title']);
        $err = null;
        if ($title === '') $err = 'กรุณากรอกชื่อกิจกรรม';
        elseif (!$s || !$en) $err = 'วันเวลาไม่ถูกต้อง';
        elseif ($en <= $s) $err = 'เวลาจบต้องหลังเวลาเริ่ม';
        if ($err) { flash($err, 'err'); $_SESSION['form'] = $_POST; redirect('events?id=' . $eid); }
        $cat = array_key_exists($_POST['category'], EVENT_CATS) ? $_POST['category'] : 'roleplay';
        $stt = in_array($_POST['status'], ['published', 'draft', 'cancelled'], true) ? $_POST['status'] : 'draft';
        $f = [$title, $cat, trim($_POST['summary']), trim($_POST['description']), trim($_POST['location']), trim($_POST['reward']), $s, $en, max(0, (int)$_POST['capacity']), $stt];
        if ($eid) { q('UPDATE events SET title=?,category=?,summary=?,description=?,location=?,reward=?,starts_at=?,ends_at=?,capacity=?,status=? WHERE id=?', [...$f, $eid]); }
        else { q('INSERT INTO events(title,category,summary,description,location,reward,starts_at,ends_at,capacity,status,created_by,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)', [...$f, $admin['id'], gmdate('Y-m-d H:i:s')]); $eid = (int)db()->lastInsertId(); }
        flash('บันทึกกิจกรรมแล้ว'); redirect('events?id=' . $eid);
    }
    if ($a === 'delete') { q('DELETE FROM events WHERE id=?', [(int)$_POST['id']]); flash('ลบกิจกรรมแล้ว'); redirect('events'); }
    if ($a === 'kick') { q('DELETE FROM registrations WHERE event_id=? AND user_id=?', [(int)$_POST['id'], (int)$_POST['uid']]); flash('ลบผู้ลงชื่อแล้ว'); redirect('events?id=' . (int)$_POST['id']); }
}

/* ───── ฟอร์ม ───── */
if ($id !== null) {
    $e = $id ? row('SELECT * FROM events WHERE id=?', [$id]) : null;
    if ($id && !$e) { flash('ไม่พบกิจกรรม', 'err'); redirect('events'); }
    $d = $_SESSION['form'] ?? null; unset($_SESSION['form']);
    $v = fn($k, $def = '') => $d[$k] ?? ($e[$k] ?? $def);
    $startIn = $d['starts_at'] ?? ($e ? utc_to_input($e['starts_at']) : local_dt(gmdate('Y-m-d H:i:s', time() + 86400))->format('Y-m-d\T20:00'));
    $endIn = $d['ends_at'] ?? ($e ? utc_to_input($e['ends_at']) : local_dt(gmdate('Y-m-d H:i:s', time() + 86400 + 7200))->format('Y-m-d\T22:00'));
    $regs = $e ? rows('SELECT u.*,r.created_at rc FROM registrations r JOIN users u ON u.id=r.user_id WHERE r.event_id=? ORDER BY r.created_at', [$id]) : [];
    admin_head($e ? 'แก้ไขกิจกรรม' : 'สร้างกิจกรรมใหม่', 'events'); ?>
<div class="cols form-cols">
<form method="post" class="panel pad frm"><?=csrf_field()?><input type="hidden" name="do" value="save"><input type="hidden" name="id" value="<?=(int)$id?>">
  <label>ชื่อกิจกรรม<input name="title" required maxlength="120" value="<?=e($v('title'))?>" placeholder="เช่น แข่งรถสตรีท: สนาม รวมหัว Community"></label>
  <div class="g2"><label>หมวดหมู่<select name="category"><?php foreach(EVENT_CATS as $k=>[$n]):?><option value="<?=$k?>" <?=$v('category','roleplay')===$k?'selected':''?>><?=e($n)?></option><?php endforeach;?></select></label>
  <label>สถานะ<select name="status"><?php foreach(['published'=>'เผยแพร่','draft'=>'ฉบับร่าง (ซ่อน)','cancelled'=>'ยกเลิก'] as $k=>$n):?><option value="<?=$k?>" <?=$v('status','published')===$k?'selected':''?>><?=$n?></option><?php endforeach;?></select></label></div>
  <label>คำอธิบายสั้น (โชว์ในรายการ)<input name="summary" maxlength="160" value="<?=e($v('summary'))?>"></label>
  <label>รายละเอียดและกติกา<textarea name="description" rows="7"><?=e($v('description'))?></textarea></label>
  <div class="g2"><label>เริ่ม <small>(เวลา <?=e($tz->getName())?>)</small><input type="datetime-local" name="starts_at" required value="<?=e($startIn)?>"></label>
  <label>จบ<input type="datetime-local" name="ends_at" required value="<?=e($endIn)?>"></label></div>
  <div class="g3"><label>สถานที่ในเกม<input name="location" value="<?=e($v('location'))?>"></label>
  <label>รางวัล<input name="reward" value="<?=e($v('reward'))?>"></label>
  <label>จำนวนที่รับ <small>(0 = ไม่จำกัด)</small><input type="number" name="capacity" min="0" value="<?=e($v('capacity',0))?>"></label></div>
  <div class="acts"><button class="btn">บันทึก</button><a class="btn ghost" href="events.php">กลับ</a>
  <?php if($e):?><a class="btn ghost" href="<?=url('events/view?id='.$id)?>" target="_blank">ดูหน้าเว็บ ↗</a><?php endif;?></div>
</form>
<?php if($e):?>
<div class="stack"><section class="panel"><div class="ph"><h2>ผู้ลงชื่อ (<?=count($regs)?><?=$e['capacity']?'/'.$e['capacity']:''?>)</h2><?php if($regs):?><a class="btn sm ghost" href="events.php?id=<?=$id?>&export=1">ดาวน์โหลด CSV</a><?php endif;?></div>
<table><tbody><?php foreach($regs as $r):?><tr><td><img class="av" src="<?=e(avatar_url($r,48))?>" alt="" width="24" height="24"> <?=e(user_name($r))?><br><small>@<?=e($r['username'])?> · <?=e($r['discord_id'])?></small></td>
<td class="n"><form method="post" data-confirm="ลบ <?=e(user_name($r))?> ออกจากกิจกรรม?">
<?=csrf_field()?><input type="hidden" name="do" value="kick"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="uid" value="<?=$r['id']?>"><button class="btn sm red">ลบ</button></form></td></tr><?php endforeach;?>
<?php if(!$regs):?><tr><td class="empty">ยังไม่มีผู้ลงชื่อ</td></tr><?php endif;?></tbody></table></section>
<section class="panel pad danger"><h2>ลบกิจกรรม</h2><p class="hint">ลบกิจกรรมพร้อมรายชื่อผู้ลงชื่อทั้งหมด กู้คืนไม่ได้ หากแค่ไม่ต้องการให้แสดง ให้เปลี่ยนสถานะเป็น "ยกเลิก" แทน</p>
<form method="post" data-confirm="ลบกิจกรรมนี้ถาวร?"><?=csrf_field()?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?=$id?>"><button class="btn red sm">ลบกิจกรรม</button></form></section></div>
<?php endif;?></div>
<?php admin_foot(); exit; }

/* ───── รายการ ───── */
$list = rows('SELECT e.*,(SELECT COUNT(*) FROM registrations r WHERE r.event_id=e.id) n FROM events e ORDER BY starts_at DESC');
admin_head('กิจกรรม', 'events'); ?>
<div class="bar2"><span class="hint"><?=count($list)?> กิจกรรม</span><a class="btn" href="events.php?id=0">+ สร้างกิจกรรม</a></div>
<section class="panel"><table><thead><tr><th>กิจกรรม</th><th>หมวด</th><th>เริ่ม</th><th class="n">ลงชื่อ</th><th>สถานะ</th></tr></thead><tbody>
<?php foreach($list as $e): [$cn,$cc]=ev_cat($e['category']);?><tr><td><a href="events.php?id=<?=$e['id']?>"><strong><?=e($e['title'])?></strong></a><br><small><?=e($e['location'])?></small></td><td><?=pill($cn,$cc)?></td><td><?=e(fmt_full($e['starts_at']))?></td><td class="n"><?=$e['n']?><?=$e['capacity']?'/'.$e['capacity']:''?></td><td><?=ev_state_pill(ev_state($e))?></td></tr><?php endforeach;?>
<?php if(!$list):?><tr><td colspan="5" class="empty">ยังไม่มีกิจกรรม — กด "สร้างกิจกรรม" เพื่อเริ่ม</td></tr><?php endif;?></tbody></table></section>
<?php admin_foot();
