<?php
require __DIR__ . '/_layout.php';

$TYPES = [
    'platforms'   => 'แพลตฟอร์ม',
    'steps'       => 'ขั้นตอนการเล่น',
    'screenshots' => 'รูปภาพเกม',
];
$type = $_GET['type'] ?? 'platforms';
if (!isset($TYPES[$type])) redirect('content?type=platforms');
$list = $c[$type] ?? [];

function item_label(string $type, $it): string {
    return match($type) {
        'platforms'   => ($it['name'] ?? '?') . ' (' . ($it['key'] ?? '?') . ')',
        'steps'       => (string)($it[0] ?? '?'),
        'faq'         => mb_strimwidth((string)($it[0] ?? '?'), 0, 60, '…'),
        'screenshots' => (string)($it['title'] ?? ($it[1] ?? '?')),
        default       => '?',
    };
}
function save_list(string $type, array $list): void {
    set_setting('cfg_' . $type, json_encode(array_values($list), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}
function back(string $type): never { redirect('content?type=' . $type); }

if (is_post()) {
    csrf_check();
    $a = $_POST['do'] ?? '';
    if ($a === 'delete') {
        unset($list[(int)$_POST['id']]);
        save_list($type, $list); flash('ลบรายการแล้ว'); back($type);
    }
    if ($a === 'save') {
        $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
        if ($type === 'platforms') {
            $item = [
                'key'      => preg_replace('/[^a-z0-9_]/', '', (string)$_POST['key']) ?: 'android',
                'name'     => trim((string)$_POST['name']),
                'tag'      => trim((string)$_POST['tag']),
                'meta'     => trim((string)$_POST['meta']),
                'status'   => in_array($_POST['status'] ?? '', ['live', 'soon'], true) ? $_POST['status'] : 'soon',
                'file'     => trim((string)$_POST['file']),
                'ext'      => trim((string)$_POST['ext']),
                'link'     => trim((string)$_POST['link']),
                'page'     => isset($_POST['page']),
                'desc'     => trim((string)$_POST['desc']),
                'features' => array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string)$_POST['features'])))),
            ];
            $exs = [];
            foreach ((array)($_POST['ex'] ?? []) as $row) {
                $lb = trim((string)($row['label'] ?? '')); $lk = trim((string)($row['link'] ?? ''));
                if ($lb === '' && $lk === '') continue;
                $exs[] = ['label' => $lb !== '' ? $lb : 'Data', 'file' => trim((string)($row['file'] ?? '')), 'ext' => trim((string)($row['ext'] ?? '')) ?: 'ZIP', 'link' => $lk, 'page' => !empty($row['page']), 'need_reg' => !empty($row['need_reg'])];
            }
            if ($exs) $item['extra'] = $exs;
        } elseif ($type === 'steps') {
            $item = [trim((string)$_POST['f0']), trim((string)$_POST['f1']), trim((string)$_POST['f2'])];
        } elseif ($type === 'faq') {
            $item = [trim((string)$_POST['f0']), trim((string)$_POST['f1'])];
        } else {
            $item = ['file' => trim((string)$_POST['f0']), 'title' => trim((string)$_POST['f1']), 'video' => trim((string)($_POST['f2'] ?? ''))];
            if ($item['video'] === '') unset($item['video']);
        }
        if ($type === 'platforms' && $id !== null && isset($list[$id]['data']) && !isset($item['extra']) && !isset($list[$id]['extra'])) {
            $item['data'] = $list[$id]['data'];
        }
        if ($id !== null && isset($list[$id])) $list[$id] = $item; else $list[] = $item;
        save_list($type, $list); flash('บันทึกแล้ว'); back($type);
    }
}

/* ───── ฟอร์มแก้ไข/เพิ่ม ───── */
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : null;
$new = isset($_GET['new']);
$showForm = $new || ($editId !== null && isset($list[$editId]));
$it = $showForm ? ($editId !== null && !$new ? $list[$editId] : null) : null;

admin_head($TYPES[$type] . ' — เนื้อหา', 'content');
?>
<div class="tabs"><?php foreach($TYPES as $k=>$n):?><a href="content?type=<?=$k?>" class="<?=$type===$k?'on':''?>"><?=$n?></a><?php endforeach;?></div>
<?php if(!$showForm):?>
<div class="bar2"><span class="hint"><?=count($list)?> รายการ</span><a class="btn" href="content?type=<?=$type?>&new=1">+ เพิ่ม</a></div>
<section class="panel"><table><thead><tr><th>รายการ</th><th></th></tr></thead><tbody>
<?php foreach($list as $i=>$it2):?><tr><td><?=e(item_label($type,$it2))?></td>
<td class="n" style="white-space:nowrap"><a class="btn sm ghost" href="content?type=<?=$type?>&edit=<?=$i?>">แก้ไข</a>
<form method="post" style="display:inline" data-confirm="ลบรายการนี้?"><?=csrf_field()?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?=$i?>"><button class="btn sm red">ลบ</button></form></td></tr><?php endforeach;?>
<?php if(!$list):?><tr><td colspan="2" class="empty">ยังไม่มีรายการ</td></tr><?php endif;?></tbody></table></section>
<?php else:?>
<?php
$fval = function(int $i, $def='') use ($it, $type) {
    if ($it === null) return $def;
    if ($type === 'platforms') return $it[$i] ?? $def;
    if ($type === 'screenshots') return $i === 0 ? ($it['file'] ?? $def) : ($it['title'] ?? $def);
    return $it[$i] ?? $def;
};
$pv = fn(string $k,$def='') => $it[$k] ?? $def;
?>
<form method="post" class="frm wide panel pad"><?=csrf_field()?><input type="hidden" name="do" value="save">
<?php if($editId!==null && !$new):?><input type="hidden" name="id" value="<?=$editId?>"><?php endif;?>
<h2><?=$new?'เพิ่ม':'แก้ไข'?>: <?=e($TYPES[$type])?></h2>
<?php if($type==='platforms'):?>
<div class="g3">
<label>key (icon)<input name="key" required value="<?=e($pv('key','android'))?>" list="pkeys"><datalist id="pkeys"><?php foreach(['android','ios','windows','mac'] as $k):?><option value="<?=$k?>"><?php endforeach;?></datalist></label>
<label>ชื่อ<input name="name" required value="<?=e($pv('name'))?>"></label>
<label>แท็ก<input name="tag" value="<?=e($pv('tag'))?>" placeholder="iPhone"></label>
</div>
<div class="g2">
<label>meta (HTML)<input name="meta" value="<?=e($pv('meta'))?>" placeholder="ไฟล์ <strong>IPA</strong>"></label>
<label>สถานะ<select name="status"><?php foreach(['live'=>'ใช้งานได้','soon'=>'เร็วๆ นี้'] as $k=>$n):?><option value="<?=$k?>" <?=$pv('status','soon')===$k?'selected':''?>><?=$n?></option><?php endforeach;?></select></label>
</div>
<div class="g3">
<label>ไฟล์<input name="file" value="<?=e($pv('file'))?>" placeholder="app.ipa"></label>
<label>นามสกุล<input name="ext" value="<?=e($pv('ext','APK'))?>" placeholder="IPA"></label>
<label class="chk" style="align-self:end"><input type="checkbox" name="page" <?=$pv('page')?'checked':''?>> เปิดเป็นหน้าเว็บ (ไม่บังคับโหลด)</label>
</div>
<label>ลิงก์ดาวน์โหลด<input type="url" name="link" value="<?=e($pv('link'))?>" placeholder="https://"></label>
<label>คำอธิบาย<textarea name="desc" rows="2"><?=e($pv('desc'))?></textarea></label>
<label>ฟีเจอร์ (บรรทัดละ 1 ข้อ)<textarea name="features" rows="4"><?=e(implode("\n",(array)$pv('features',[])))?></textarea></label>
<label>ปุ่มเสริม (กด "+ เพิ่มปุ่ม" เพื่อเพิ่ม)</label>
<div id="exlist">
<?php $exrows = ($it['extra'] ?? []); $exrows[] = []; foreach($exrows as $xi=>$ex):?>
<div class="exrow">
<div class="exin">
<input name="ex[<?=$xi?>][label]" placeholder="ชื่อปุ่ม" value="<?=e($ex['label']??'')?>">
<input name="ex[<?=$xi?>][ext]" placeholder="นามสกุล" value="<?=e($ex['ext']??'ZIP')?>">
<input name="ex[<?=$xi?>][link]" type="url" placeholder="https://" value="<?=e($ex['link']??'')?>">
</div>
<div class="exct"><label class="chk"><input type="checkbox" name="ex[<?=$xi?>][page]" value="1" <?=!empty($ex['page'])?'checked':''?>> เปิดเป็นหน้าเว็บ (ไม่บังคับโหลด)</label>
<label class="chk"><input type="checkbox" name="ex[<?=$xi?>][need_reg]" value="1" <?=!empty($ex['need_reg'])?'checked':''?>> ต้องลงทะเบียนเซิร์ฟเวอร์ก่อน</label>
<button type="button" class="btn sm red" onclick="this.closest('.exrow').remove()">ลบ</button></div>
</div>
<?php endforeach;?>
</div>
<button type="button" class="btn sm ghost" id="exadd">+ เพิ่มปุ่ม</button>
<script>document.getElementById('exadd')?.addEventListener('click',()=>{const l=document.getElementById('exlist'),i='n'+Date.now(),d=document.createElement('div');d.className='exrow';
d.innerHTML='<div class="exin"><input name="ex['+i+'][label]" placeholder="ชื่อปุ่ม"><input name="ex['+i+'][ext]" placeholder="นามสกุล" value="ZIP"><input name="ex['+i+'][link]" type="url" placeholder="https://"></div><div class="exct"><label class="chk"><input type="checkbox" name="ex['+i+'][page]" value="1"> เปิดเป็นหน้าเว็บ (ไม่บังคับโหลด)</label><label class="chk"><input type="checkbox" name="ex['+i+'][need_reg]" value="1"> ต้องลงทะเบียนเซิร์ฟเวอร์ก่อน</label><button type="button" class="btn sm red" onclick="this.closest(\'.exrow\').remove()">ลบ</button></div>';
l.appendChild(d)})</script>
<?php elseif($type==='steps'):
$ICONS = [
 'download' => 'M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4|M12 15V3|7 10 12 15 17 10',
 'phone'    => 'M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z',
 'shield'   => 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z|M9 12l2 2 4-4',
 'user'     => 'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2|M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8',
 'check'    => 'M20 6 9 17l-5-5',
 'play'     => 'M6 4l14 8-14 8z',
 'gear'     => 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z|M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z',
];
?>
<label>ชื่อขั้นตอน<input name="f0" required value="<?=e($fval(0))?>"></label>
<label>คำอธิบาย<textarea name="f1" rows="2" placeholder="พิมพ์ข้อความธรรมดา — รองรับ HTML เช่น <strong>ตัวหนา</strong>"><?=e($fval(1))?></textarea></label>
<div class="hint" style="margin:-.4rem 0 .6rem">ตัวแปรที่แทรกได้:
<button type="button" class="btn sm ghost" onclick="ins('{apk_file}')">{apk_file}</button>
<button type="button" class="btn sm ghost" onclick="ins('{version}')">{version}</button>
<span class="muted" style="font-size:.8rem">(กดเพื่อแทรกลงในช่องคำอธิบาย)</span></div>
<label>ไอคอน</label>
<select id="iconpick" style="margin-bottom:.5rem"><option value="">— เลือกไอคอนสำเร็จรูป —</option><?php foreach($ICONS as $ik=>$iv):?><option value="<?=e($iv)?>" <?=($it&&$fval(2))===$iv?'selected':''?>><?=$ik?></option><?php endforeach;?></select>
<input type="hidden" name="f2" id="f2icon" value="<?=e($fval(2))?>">
<script>
document.getElementById('iconpick')?.addEventListener('change',e=>{if(e.target.value)document.getElementById('f2icon').value=e.target.value});
function ins(v){const t=document.querySelector('textarea[name=f1]');t.value=(t.value?t.value+' ':'')+v;t.focus()}
</script>
<?php elseif($type==='faq'):?>
<label>คำถาม<input name="f0" required value="<?=e($fval(0))?>"></label>
<label>คำตอบ (HTML)<textarea name="f1" rows="5"><?=e($fval(1))?></textarea></label>
<?php else:?>
<label>ไฟล์รูป<input name="f0" required value="<?=e($fval(0))?>" placeholder="assets/images/shot1.jpg"></label>
<label>คำอธิบายภาพ<input name="f1" required value="<?=e($fval(1))?>"></label>
<label>วิดีโอ (ไม่บังคับ — path .mp4 ในเว็บ)<input name="f2" value="<?=e($fval(2, $it['video'] ?? ''))?>" placeholder="assets/images/video1.mp4"></label>
<?php endif;?>
<div class="acts"><button class="btn">บันทึก</button><a class="btn ghost" href="content?type=<?=$type?>">กลับ</a></div>
</form>
<?php endif;?>
<?php admin_foot();
