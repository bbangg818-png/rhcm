<?php
require __DIR__ . '/_layout.php';
if (is_post()) {
    csrf_check();
    foreach (['apk', 'discord', 'whatsapp', 'instagram', 'tiktok'] as $k) {
        $v = trim((string)($_POST['link_' . $k] ?? ''));
        if ($v !== '' && !preg_match('#^https?://#i', $v)) { flash('ลิงก์ "' . $k . '" ต้องขึ้นต้นด้วย http:// หรือ https://', 'err'); redirect('settings'); }
        set_setting('link_' . $k, $v);
    }
    foreach (['version', 'apk_file', 'brand', 'brand_full', 'initial', 'site_url', 'currency', 'timezone', 'logo', 'hero_image', 'announcement', 'tiktok_live'] as $k) set_setting($k, trim((string)$_POST[$k]));
    // Save TikTok live users
    $tiktok_live_users = $_POST['tiktok_live_users'] ?? [];
    set_setting('tiktok_live_users', json_encode($tiktok_live_users));
    @unlink(ROOT . '/data/widget.json');
    flash('บันทึกการตั้งค่าแล้ว'); redirect('settings');
}
admin_head('ตั้งค่าเว็บ', 'settings'); ?>
<form method="post" class="frm wide"><?=csrf_field()?>
<section class="panel pad"><h2>ประกาศบนเว็บ</h2><p class="hint">ข้อความแถบด้านบนของทุกหน้า เว้นว่างไว้เพื่อซ่อน</p>
<label>ข้อความ<input name="announcement" maxlength="200" value="<?=e(setting('announcement'))?>" placeholder="เช่น เซิร์ฟเวอร์ปิดปรับปรุง 03:00–04:00 น."></label></section>
<section class="panel pad"><h2>ลิงก์</h2><p class="hint">เว้นว่าง = ปุ่มจะแสดง "ยังไม่ได้ตั้งค่าลิงก์" ค่าที่กรอกที่นี่จะทับค่าใน config.php</p>
<div class="g2"><?php foreach(['apk'=>'ลิงก์ดาวน์โหลด APK','discord'=>'Discord (invite)','whatsapp'=>'WhatsApp','instagram'=>'Instagram','tiktok'=>'TikTok'] as $k=>$n):?><label><?=$n?><input type="url" name="link_<?=$k?>" value="<?=e($c['links'][$k])?>" placeholder="https://"></label><?php endforeach;?></div></section>
<section class="panel pad"><h2>ข้อมูลเซิร์ฟเวอร์</h2>
<div class="g2"><label>ชื่อเต็มเซิร์ฟเวอร์<input name="brand_full" value="<?=e($c['brand_full'])?>"></label><label>ชื่อสั้น (logo/เมนู)<input name="brand" value="<?=e($c['brand'])?>"></label></div>
<div class="g3"><label>ตัวอักษรโลโก้<input name="initial" value="<?=e($c['initial'])?>" maxlength="4"></label><label>เวอร์ชัน<input name="version" value="<?=e($c['version'])?>"></label><label>ชื่อไฟล์ APK<input name="apk_file" value="<?=e($c['apk_file'])?>"></label></div>
<div class="g3"><label>สกุลเงิน<input name="currency" value="<?=e($c['currency'])?>"></label><label>เขตเวลา (timezone)<input name="timezone" value="<?=e($c['timezone'])?>" placeholder="Asia/Bangkok"></label><label>URL เว็บ<input name="site_url" value="<?=e($c['site_url'])?>" placeholder="https://"></label></div></section>
<section class="panel pad"><h2>รูปภาพ</h2><p class="hint">path ไฟล์ในเว็บ เช่น assets/images/logo.png (ว่าง = ใช้ตัวอักษรย่อ)</p>
<div class="g2"><label>โลโก้<input name="logo" value="<?=e($c['logo'])?>"></label><label>รูปพื้นหลัง hero<input name="hero_image" value="<?=e($c['hero_image'])?>"></label></div></section>
<section class="panel pad"><h2>TikTok Live</h2><p class="hint">เลือก user ที่เชื่อมต่อ TikTok เพื่อดึงวิดีโอมาแสดง (เลือกได้หลายคน)</p>
<?php
$tiktok_users = rows("SELECT id, username, display_name, tiktok_username FROM users WHERE tiktok_username != '' ORDER BY tiktok_username ASC");
$selected_users = setting('tiktok_live_users') ? json_decode(setting('tiktok_live_users'), true) : [];
?>
<?php if($tiktok_users): ?>
<div class="g3">
<?php foreach($tiktok_users as $tu): ?>
<label style="display:flex;align-items:center;gap:8px;padding:8px;background:var(--bg2);border-radius:8px;cursor:pointer;">
<input type="checkbox" name="tiktok_live_users[]" value="<?=$tu['id']?>" <?=in_array($tu['id'], $selected_users)?'checked':''?>>
<span><strong><?=e($tu['tiktok_username'])?></strong> (<?=e($tu['display_name'] ?: $tu['username'])?>)</span>
</label>
<?php endforeach; ?>
</div>
<?php else: ?>
<p class="muted">ยังไม่มี user ที่เชื่อมต่อ TikTok</p>
<?php endif; ?></section>
<section class="panel pad"><h2>สถานะ Discord Login</h2>
<p class="hint"><?=discord_ready()?'<span class="ok">● ตั้งค่าแล้ว</span> — Redirect URI ที่ต้องเพิ่มใน Discord Developer Portal:':'<span class="bad">● ยังไม่ได้ตั้งค่า</span> — ใส่ client_id / client_secret ใน config.php แล้วเพิ่ม Redirect URI นี้ใน Discord Developer Portal:'?></p><p><code><?=e(discord_redirect_uri())?></code></p></section>
<div class="acts"><button class="btn">บันทึกทั้งหมด</button></div></form>
<?php admin_foot();
