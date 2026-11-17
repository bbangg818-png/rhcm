<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require ROOT . '/includes/layout.php';
$u = require_login();

if (is_post()) {
    csrf_check();
    $a = $_POST['do'] ?? '';
    if ($a === 'leave') {
        q('DELETE FROM registrations WHERE event_id=? AND user_id=?', [(int)$_POST['id'], $u['id']]); flash('ยกเลิกการลงทะเบียนแล้ว');
    } elseif ($a === 'display_name') {
        $dn = trim((string)$_POST['display_name']);
        if ($dn === '' || mb_strlen($dn) > 32) {
            flash('ชื่อแสดงไม่ถูกต้อง (ไม่เกิน 32 ตัวอักษร)', 'err');
        } else {
            q('UPDATE users SET display_name=? WHERE id=?', [$dn, $u['id']]); flash('บันทึกชื่อแสดงแล้ว');
        }
    } elseif ($a === 'game_name') {
        if (!empty($u['game_name'])) { flash('คุณลงทะเบียนชื่อในเกมไปแล้ว ไม่สามารถเปลี่ยนแปลงได้', 'err'); }
        else {
        $gn = trim((string)$_POST['game_name']);
        if ($gn === '' || mb_strlen($gn) > 19 || !preg_match('/^[\p{L}\p{N}_\-\. ]+$/u', $gn)) {
            flash('ชื่อในเกมไม่ถูกต้อง (ใช้ได้เฉพาะตัวอักษร ตัวเลข _ - . เว้นวรรค ไม่เกิน 19 ตัว)', 'err');
        } elseif (strtoupper($gn) === 'PLAYER') {
            flash('ไม่สามารถใช้ชื่อ PLAYER ได้ กรุณาใช้ชื่ออื่น', 'err');
        } elseif (val('SELECT id FROM users WHERE game_name=? AND id<>?', [$gn, $u['id']])) {
            flash('ชื่อในเกมนี้มีคนใช้แล้ว กรุณาใช้ชื่ออื่น', 'err');
        } else {
            q('UPDATE users SET game_name=? WHERE id=?', [$gn, $u['id']]); flash('ลงทะเบียนชื่อในเกมแล้ว: ' . $gn);
            if (!empty($c['profile_json'])) {
                $pj = $c['profile_json'];
                if ($pj[0] !== '/' && !preg_match('/^[A-Za-z]:[\\\\\\/]/', $pj)) $pj = ROOT . '/' . $pj;
                @file_put_contents($pj, json_encode(['name' => $gn, 'server' => $c['server_address'] ?? ''], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }
        }
        }
    } elseif ($a === 'tiktok') {
        $tk = trim((string)$_POST['tiktok_username']);
        if ($tk !== '' && !preg_match('/^@?[\w\.\-]+$/', $tk)) {
            flash('Username TikTok ไม่ถูกต้อง', 'err');
        } else {
            q('UPDATE users SET tiktok_username=? WHERE id=?', [$tk, $u['id']]); flash('บันทึก TikTok แล้ว: ' . ($tk ?: 'ไม่ได้เชื่อมต่อ'));
        }
    }
    redirect(url('account/'));
}

$regs = rows('SELECT e.* FROM registrations r JOIN events e ON e.id=r.event_id WHERE r.user_id=? ORDER BY e.starts_at DESC', [$u['id']]);

page_head('บัญชีของฉัน — ' . $c['brand_full']);
?>
<main class="store-wrap"><div class="container">
<?=flash_html()?>
<div class="prof">
  <img src="<?=e(avatar_url($u,128))?>" alt="" width="72" height="72">
  <div><h1 class="stitle" style="font-size:1.9rem"><?=e(user_name($u))?></h1>
  <p class="muted">@<?=e($u['username'])?> · สมาชิกตั้งแต่ <?=e(local_dt($u['created_at'])->format('d/m/Y'))?><?=$u['role']==='admin'?' · <span class="accent">แอดมิน</span>':''?><?=!empty($u['game_name'])?' · ชื่อในเกม: <strong class="accent">'.e($u['game_name']).'</strong>':''?></p></div>
  <?php if(is_admin()):?><a class="btn btn-sm btn-ghost" href="<?=url('admin/')?>" style="margin-left:auto">แดชบอร์ดแอดมิน</a><?php endif;?>
</div>

<section class="srvreg"><details open><summary>เปลี่ยนชื่อแสดง</summary>
<form method="post"><?=csrf_field()?><input type="hidden" name="do" value="display_name">
<input name="display_name" maxlength="32" placeholder="ชื่อแสดง" value="<?=e($u['display_name'])?>" required>
<button class="btn btn-sm">บันทึกชื่อแสดง</button></form>
<p class="muted" style="font-size:.78rem;margin-top:.6rem">ชื่อที่แสดงให้คนอื่นเห็น (ไม่เกิน 32 ตัวอักษร)</p></section>

<section class="srvreg"><details <?=empty($u['game_name'])?'open':''?>><summary>ลงทะเบียนเซิร์ฟเวอร์</summary>
<?php if(!empty($u['game_name'])):?><p style="margin:.6rem 0">ชื่อในเกมของคุณ: <strong class="accent"><?=e($u['game_name'])?></strong> <button type="button" class="btn btn-sm ghost" style="margin-left:.4rem" onclick="navigator.clipboard.writeText(this.dataset.name).then(()=>{this.textContent='คัดลอกแล้ว';setTimeout(()=>this.textContent='คัดลอก',1500)}).catch(()=>{this.textContent='คัดลอกไม่สำเร็จ'})" data-name="<?=e($u['game_name'])?>">คัดลอก</button></p><p class="muted" style="font-size:.78rem">ลงทะเบียนได้เพียงครั้งเดียว ไม่สามารถเปลี่ยนแปลงได้</p><?php else:?>
<form method="post"><?=csrf_field()?><input type="hidden" name="do" value="game_name">
<input name="game_name" maxlength="19" placeholder="ชื่อในเกม" required>
<button class="btn btn-sm">บันทึกชื่อในเกม</button></form>
<p class="muted" style="font-size:.78rem;margin-top:.6rem">กรอกได้เพียงครั้งเดียว · ใช้ตัวอักษร ไทย/อังกฤษ ตัวเลข _ - . เว้นวรรค ไม่เกิน 19 ตัว</p><?php endif;?></details></section>

<section class="srvreg"><details><summary>เชื่อมต่อ TikTok</summary>
<?php if(!empty($c['tiktok_oauth']['client_key'])):?>
  <?php if(!empty($u['tiktok_username'])):?>
    <p style="margin:.6rem 0">เชื่อมต่อกับ: <strong class="accent"><?=e($u['tiktok_username'])?></strong></p>
    <form method="post"><?=csrf_field()?><input type="hidden" name="do" value="tiktok"><input type="hidden" name="tiktok_username" value=""><button class="btn btn-sm btn-ghost">ยกเลิกการเชื่อมต่อ</button></form>
  <?php else:?>
    <a class="btn btn-sm block" href="<?=url('auth/tiktok')?>?next=<?=urlencode(url('account/'))?>">เชื่อมต่อด้วย TikTok</a>
  <?php endif;?>
<?php else:?>
  <form method="post"><?=csrf_field()?><input type="hidden" name="do" value="tiktok">
  <input name="tiktok_username" placeholder="@username" value="<?=e($u['tiktok_username'])?>">
  <button class="btn btn-sm">บันทึก TikTok</button></form>
  <p class="muted" style="font-size:.78rem;margin-top:.6rem">ใส่ @username TikTok ของคุณ (เช่น @username)</p>
<?php endif;?></section>

<section id="inscricoes" class="evsec"><h2 class="evh">การลงทะเบียนของฉัน <small><?=count($regs)?></small></h2>
<?php if(!$regs):?><div class="empty"><strong>คุณยังไม่ได้ลงทะเบียนกิจกรรมใดๆ</strong><a class="btn btn-sm" href="<?=url('events/')?>">ดูกำหนดการ</a></div>
<?php else:?><div class="tbl"><table>
<thead><tr><th>กิจกรรม</th><th>เวลา</th><th>สถานะ</th><th></th></tr></thead><tbody>
<?php foreach($regs as $e): $s=ev_state($e);?>
<tr><td><a href="<?=url('events/view?id='.$e['id'])?>"><strong><?=e($e['title'])?></strong></a></td><td><?=e(fmt_when($e['starts_at']))?></td>
<td><?=['live'=>'<span class="live"><i></i>กำลังจัด</span>','upcoming'=>'กำหนดแล้ว','ended'=>'จบแล้ว','cancelled'=>'ยกเลิก','draft'=>'—'][$s]?></td>
<td class="r"><?php if($s==='upcoming'):?><form method="post" onsubmit="return confirm('ยกเลิกการลงทะเบียน?')"><?=csrf_field()?><input type="hidden" name="do" value="leave"><input type="hidden" name="id" value="<?=$e['id']?>"><button class="lnk">ยกเลิก</button></form><?php endif;?></td></tr>
<?php endforeach;?></tbody></table></div><?php endif;?>
</section>

</div></main>
<?php page_foot();
