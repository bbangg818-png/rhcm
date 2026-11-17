<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require ROOT . '/includes/layout.php';

$u = me();
$id = (int)($_GET['id'] ?? 0);
$g = $id ? row('SELECT g.*, u.username AS leader_name, u.display_name AS leader_dn, u.avatar AS leader_av, u.discord_id AS leader_did
               FROM gangs g JOIN users u ON u.id=g.leader_id WHERE g.id=?', [$id]) : null;
if (!$g) { http_response_code(404); exit('ไม่พบแก็ง'); }

$roster = json_decode((string)($g['roster'] ?? ''), true);
if (!is_array($roster)) $roster = [];
$roster = array_values(array_filter($roster, fn($r) => is_array($r) && (trim((string)($r['name'] ?? '')) !== '' || trim((string)($r['discord'] ?? '')) !== '')));
$memberCount = count($roster);

$myMembership = $u ? row('SELECT * FROM gang_members WHERE gang_id=? AND user_id=?', [$g['id'], $u['id']]) : null;
$members = rows("SELECT m.user_id, m.role, m.joined_at, m.prev_display_name, m.renamed_at, u.username, u.display_name, u.avatar, u.discord_id, u.game_name
                 FROM gang_members m JOIN users u ON u.id=m.user_id WHERE m.gang_id=?
                 ORDER BY FIELD(m.role,'leader','vice','officer','member'), m.joined_at", [$g['id']]);
$myOtherGang = $u && !$myMembership ? val('SELECT gang_id FROM gang_members WHERE user_id=?', [$u['id']]) : null;
$isLeader = $myMembership && $myMembership['role'] === 'leader';
$canManage = $myMembership && in_array($myMembership['role'], ['leader', 'vice'], true);
$myRequest = $u && !$myMembership ? val('SELECT 1 FROM gang_join_requests WHERE gang_id=? AND user_id=?', [$g['id'], $u['id']]) : null;

// สร้าง mapping จาก discord_id ไปยัง game_name สำหรับสมาชิกที่ล็อกอิน
$discordToGameName = [];
foreach ($members as $m) {
    if (!empty($m['game_name']) && !empty($m['discord_id'])) {
        $discordToGameName[$m['discord_id']] = $m['game_name'];
    }
}

/* หัวแก็ง/รองประธานแก้ไข roster */
function roster_post(): array {
    $out = [];
    $push = function (string $role, string $name, string $disc) use (&$out) {
        $name = trim($name); $disc = trim($disc);
        if ($name !== '' || $disc !== '') $out[] = ['role' => $role, 'name' => mb_substr($name, 0, 32), 'discord' => mb_substr($disc, 0, 64)];
    };
    $push('leader', (string)($_POST['leader_name'] ?? ''), (string)($_POST['leader_discord'] ?? ''));
    $push('vice', (string)($_POST['vice_name'] ?? ''), (string)($_POST['vice_discord'] ?? ''));
    foreach ((array)($_POST['m_name'] ?? []) as $i => $mn) {
        $push('member', (string)$mn, (string)($_POST['m_discord'][$i] ?? ''));
    }
    return $out;
}

if (is_post() && $u) {
    csrf_check();
    $a = $_POST['do'] ?? '';

    if ($a === 'join_request' && $g['status'] === 'approved' && !$myMembership && !$myOtherGang && !$myRequest) {
        q('INSERT INTO gang_join_requests(gang_id,user_id,created_at) VALUES(?,?,?)', [$g['id'], $u['id'], gmdate('Y-m-d H:i:s')]);
        flash('ส่งคำขอเข้าร่วมแก็ง ' . $g['name'] . ' แล้ว — รอหัวหน้า/รองประธานอนุมัติ');
    } elseif ($a === 'cancel_request' && $myRequest) {
        q('DELETE FROM gang_join_requests WHERE gang_id=? AND user_id=?', [$g['id'], $u['id']]);
        flash('ยกเลิกคำขอแล้ว');
    } elseif ($a === 'approve_req' && $canManage) {
        $rid = (int)($_POST['uid'] ?? 0);
        if ($rid && !val('SELECT 1 FROM gang_members WHERE gang_id=? AND user_id=?', [$g['id'], $rid])) {
            q('INSERT INTO gang_members(gang_id,user_id,role,joined_at) VALUES(?,?,?,?)', [$g['id'], $rid, 'member', gmdate('Y-m-d H:i:s')]);
            q('DELETE FROM gang_join_requests WHERE gang_id=? AND user_id=?', [$g['id'], $rid]);
            // เพิ่มชื่อเข้า roster ด้วย
            $user = row('SELECT display_name, game_name, discord_id FROM users WHERE id=?', [$rid]);
            if ($user) {
                $roster[] = ['role' => 'member', 'name' => $user['game_name'] ?: $user['display_name'], 'discord' => $user['discord_id']];
                q('UPDATE gangs SET roster=? WHERE id=?', [json_encode($roster, JSON_UNESCAPED_UNICODE), $g['id']]);
            }
            flash('อนุมัติสมาชิกแล้ว');
        }
    } elseif ($a === 'reject_req' && $canManage) {
        $rid = (int)($_POST['uid'] ?? 0);
        q('DELETE FROM gang_join_requests WHERE gang_id=? AND user_id=?', [$g['id'], $rid]);
        flash('ปฏิเสธคำขอแล้ว');
    } elseif ($a === 'rename_member' && $canManage) {
        $rid = (int)($_POST['uid'] ?? 0);
        $newName = trim((string)($_POST['new_name'] ?? ''));
        $target = $rid ? row('SELECT m.role, m.prev_display_name, m.renamed_at, u.display_name FROM gang_members m JOIN users u ON u.id=m.user_id WHERE m.gang_id=? AND m.user_id=?', [$g['id'], $rid]) : null;
        if (!$target || $target['role'] === 'leader') {
            flash('ไม่สามารถเปลี่ยนชื่อนี้ได้', 'err');
        } elseif ($target['renamed_at'] && strtotime($target['renamed_at']) > time() - 30 * 86400) {
            $days = (int)ceil((strtotime($target['renamed_at']) + 30 * 86400 - time()) / 86400);
            flash('เปลี่ยนชื่อได้อีกครั้งในอีก ' . $days . ' วัน', 'err');
        } elseif (mb_strlen($newName) < 2 || mb_strlen($newName) > 32) {
            flash('ชื่อต้องยาว 2-32 ตัวอักษร', 'err');
        } elseif ($g['tag'] !== '' && mb_stripos($newName, $g['tag']) === false) {
            flash('ชื่อต้องมี TAG ของแก็ง [' . $g['tag'] . '] กำกับ', 'err');
        } else {
            if ($target['prev_display_name'] === null) {
                q('UPDATE gang_members SET prev_display_name=? WHERE gang_id=? AND user_id=?', [$target['display_name'], $g['id'], $rid]);
            }
            q('UPDATE users SET display_name=? WHERE id=?', [$newName, $rid]);
            q('UPDATE gang_members SET renamed_at=? WHERE gang_id=? AND user_id=?', [gmdate('Y-m-d H:i:s'), $g['id'], $rid]);
            flash('เปลี่ยนชื่อสมาชิกแล้ว (เปลี่ยนได้อีกครั้งใน 30 วัน)');
        }
    } elseif ($a === 'remove_member' && $canManage) {
        $rid = (int)($_POST['uid'] ?? 0);
        $target = $rid ? row('SELECT role, prev_display_name FROM gang_members WHERE gang_id=? AND user_id=?', [$g['id'], $rid]) : null;
        if ($target && $target['role'] !== 'leader' && !($isLeader === false && $target['role'] === 'vice')) {
            if ($target['prev_display_name'] !== null) {
                q('UPDATE users SET display_name=? WHERE id=?', [$target['prev_display_name'], $rid]);
            }
            q('DELETE FROM gang_members WHERE gang_id=? AND user_id=?', [$g['id'], $rid]);
            flash('ลบสมาชิกออกจากแก็งแล้ว (คืนชื่อเดิม)');
        }
    } elseif ($a === 'add_member' && $canManage) {
        $mName = mb_substr(trim((string)($_POST['m_name'] ?? '')), 0, 32);
        $mDisc = mb_substr(trim((string)($_POST['m_discord'] ?? '')), 0, 64);
        if ($mName === '' && $mDisc === '') {
            flash('กรุณากรอกชื่อหรือ Discord ของสมาชิก', 'err');
        } else {
            $roster[] = ['role' => 'member', 'name' => $mName, 'discord' => $mDisc];
            q('UPDATE gangs SET roster=? WHERE id=?', [json_encode($roster, JSON_UNESCAPED_UNICODE), $g['id']]);
            flash('เพิ่มสมาชิกเข้าแก็งแล้ว');
        }
    } elseif ($a === 'set_role' && $isLeader) {
        $rid = (int)($_POST['uid'] ?? 0);
        $role = in_array($_POST['role'] ?? '', ['vice', 'member'], true) ? $_POST['role'] : '';
        if ($rid && $role && val('SELECT 1 FROM gang_members WHERE gang_id=? AND user_id=? AND role=?', [$g['id'], $rid, 'member'])) {
            q("UPDATE gang_members SET role=? WHERE gang_id=? AND user_id=?", [$role, $g['id'], $rid]);
            flash('อัปเดตตำแหน่งแล้ว');
        }
    } elseif ($a === 'update_roster_name' && $canManage) {
        $idx = (int)($_POST['roster_idx'] ?? -1);
        $newName = mb_substr(trim((string)($_POST['new_name'] ?? '')), 0, 32);
        $newDiscord = mb_substr(trim((string)($_POST['new_discord'] ?? '')), 0, 64);
        if ($idx >= 0 && isset($roster[$idx])) {
            if ($newName !== '' || $newDiscord !== '') {
                $roster[$idx]['name'] = $newName;
                $roster[$idx]['discord'] = $newDiscord;
                q('UPDATE gangs SET roster=? WHERE id=?', [json_encode($roster, JSON_UNESCAPED_UNICODE), $g['id']]);
                flash('อัปเดตชื่อในรายชื่อแก็งแล้ว');
            } else {
                flash('กรุณากรอกชื่อหรือ Discord', 'err');
            }
        } else {
            flash('ไม่พบรายการในรายชื่อ', 'err');
        }
    } elseif ($a === 'delete_roster_entry' && $canManage) {
        $idx = (int)($_POST['roster_idx'] ?? -1);
        if ($idx >= 0 && isset($roster[$idx])) {
            $role = $roster[$idx]['role'];
            if ($role !== 'leader' && !($isLeader === false && $role === 'vice')) {
                array_splice($roster, $idx, 1);
                q('UPDATE gangs SET roster=? WHERE id=?', [json_encode($roster, JSON_UNESCAPED_UNICODE), $g['id']]);
                flash('ลบรายการออกจากรายชื่อแก็งแล้ว');
            } else {
                flash('ไม่สามารถลบหัวหน้าหรือรองประธานแก็งได้', 'err');
            }
        } else {
            flash('ไม่พบรายการในรายชื่อ', 'err');
        }
    } elseif ($a === 'leave_gang' && $myMembership) {
        if ($myMembership['role'] === 'leader') {
            flash('หัวหน้าแก็งไม่สามารถออกจากแก็งได้ กรุณายุบแก็งหรือแต่งตั้งหัวหน้าคนใหม่', 'err');
        } else {
            if ($myMembership['prev_display_name'] !== null) {
                q('UPDATE users SET display_name=? WHERE id=?', [$myMembership['prev_display_name'], $u['id']]);
            }
            q('DELETE FROM gang_members WHERE gang_id=? AND user_id=?', [$g['id'], $u['id']]);
            flash('ออกจากแก็งแล้ว');
        }
    } elseif ($a === 'edit_roster' && $canManage) {
        $rosterNew = roster_post();
        if (!array_filter($rosterNew, fn($r) => $r['role'] === 'leader' && $r['name'] !== '')) {
            flash('กรุณาใส่ชื่อหัวหน้าแก็ง', 'err');
        } else {
            $newLogo = gang_logo_upload($_FILES['logo'] ?? []);
            q('UPDATE gangs SET roster=?, logo=COALESCE(?, logo) WHERE id=?',
              [json_encode($rosterNew, JSON_UNESCAPED_UNICODE), $newLogo, $g['id']]);
            flash('อัปเดตรายชื่อแก็งแล้ว');
        }
    } elseif ($a === 'disband' && $isLeader) {
        foreach (rows('SELECT user_id, prev_display_name FROM gang_members WHERE gang_id=? AND prev_display_name IS NOT NULL', [$g['id']]) as $rm) {
            q('UPDATE users SET display_name=? WHERE id=?', [$rm['prev_display_name'], $rm['user_id']]);
        }
        q('DELETE FROM gangs WHERE id=?', [$g['id']]);
        flash('ยุบแก็ง ' . $g['name'] . ' แล้ว');
        redirect(url('gangs/'));
    }
    redirect(url('gangs/view?id=' . $g['id']));
}

$joinRequests = $canManage ? rows("SELECT r.user_id, r.created_at, u.username, u.display_name, u.avatar, u.discord_id
                                   FROM gang_join_requests r JOIN users u ON u.id=r.user_id
                                   WHERE r.gang_id=? ORDER BY r.created_at", [$g['id']]) : [];

$statusLabel = ['pending' => 'รอแอดมินอนุมัติ', 'approved' => 'อนุมัติแล้ว', 'rejected' => 'ถูกปฏิเสธ'][$g['status']];
$statusColor = ['pending' => '#f59e0b', 'approved' => '#10b981', 'rejected' => '#ef4444'][$g['status']];
$rv = function (string $role) use ($roster) { return array_values(array_filter($roster, fn($r) => $r['role'] === $role)); };
$roleLabel = ['leader' => 'หัวหน้า', 'vice' => 'รองประธาน', 'officer' => 'ผู้ช่วย', 'member' => 'สมาชิก'];
$dcUsers = array_map(fn($r) => ['u' => $r['username'], 'd' => $r['display_name'] ?? '', 'n' => mb_strtolower(($r['display_name'] ?? '') . ' ' . $r['username'])],
                      rows('SELECT username, display_name FROM users ORDER BY username LIMIT 2000'));

page_head($g['name'] . ' — แก็ง — ' . $c['brand_full'], ['active' => 'gangs']);
?>
<main class="store-wrap"><div class="container">
<div class="shead"><h1 class="stitle"><?php if(!empty($g['logo'])):?><img src="<?=e(gang_logo_src($g['logo']))?>" alt="" width="56" height="56" style="border-radius:50%;object-fit:cover;vertical-align:middle;margin-right:.5rem"><?php endif;?><?=e($g['name'])?> <?php if($g['tag']!==''):?><span class="accent">[<?=e($g['tag'])?>]</span><?php endif;?></h1>
<p class="ssub">สมาชิก <strong><?=$memberCount?></strong> คน · <span style="color:<?=$statusColor?>"><?=$statusLabel?></span></p></div>
<?=flash_html()?>

<section class="srvreg"><div class="prof" style="margin-bottom:.8rem;justify-content:space-between">
<div style="display:flex;align-items:center;gap:1.2rem">
<img src="<?=e(avatar_url(['discord_id'=>$g['leader_did'],'avatar'=>$g['leader_av']],64))?>" alt="" width="48" height="48">
<div><strong>หัวแก็ง</strong><br><span class="muted"><?=e($g['leader_dn'] ?: $g['leader_name'])?> (@<?=e($g['leader_name'])?>)</span></div>
</div>
<?php if($u && $g['status']==='approved' && !$myMembership):?>
<div style="text-align:right">
<?php if($myOtherGang):?><p class="muted" style="font-size:.85rem">คุณอยู่ในแก็งอื่นอยู่แล้ว</p>
<?php elseif($myRequest):?>
<form method="post"><?=csrf_field()?><input type="hidden" name="do" value="cancel_request"><button class="btn btn-sm btn-ghost">ยกเลิกคำขอเข้าร่วม</button></form>
<p class="muted" style="font-size:.78rem;margin-top:.4rem">คำขอของคุณรอหัวหน้า/รองประธานแก็งอนุมัติอยู่</p>
<?php else:?>
<form method="post"><?=csrf_field()?><input type="hidden" name="do" value="join_request"><button class="btn btn-sm">ขอเข้าร่วมแก็ง</button></form>
<?php endif;?>
</div>
<?php elseif($myMembership && $myMembership['role'] !== 'leader'):?>
<div style="text-align:right">
<form method="post" data-confirm="ออกจากแก็ง <?=e($g['name'])?>?"><?=csrf_field()?><input type="hidden" name="do" value="leave_gang"><button class="btn btn-sm red">ออกจากแก็ง</button></form>
</div>
<?php elseif($g['status']!=='approved' && !$myMembership):?>
<div style="text-align:right"><p class="muted" style="font-size:.85rem">แก็งนี้ยังไม่เปิดรับสมาชิก</p></div>
<?php endif;?>
</div></section>

<section class="srvreg"><h2 class="evh" style="margin-bottom:.6rem">รายชื่อแก็ง</h2>
<?php if(!$roster):?><p class="muted">ยังไม่มีรายชื่อ</p><?php else:
$labels = ['leader' => 'หัวหน้าแก็ง', 'vice' => 'รองประธานแก็ง', 'member' => 'สมาชิกแก็ง'];
$idx = 1;
foreach ($labels as $role => $label): 
    $rows = $rv($role); 
    if (!$rows) continue; 
?>
<h3 class="muted" style="font-size:.85rem;margin:.8rem 0 .3rem"><?=$label?> (<?=count($rows)?>)</h3>
<div class="tbl"><table><tbody>
<?php foreach($rows as $r):?>
<?php
$displayName = $r['name'];
// ถ้า discord ตรงกับสมาชิกที่ล็อกอิน ให้แสดง game_name แทน
if (!empty($r['discord']) && isset($discordToGameName[$r['discord']])) {
    $displayName = $discordToGameName[$r['discord']];
}
$rosterIdx = array_search($r, $roster, true);
?>
<tr><td class="n"><?=$idx++?></td><td><?=e($displayName)?></td><td class="r muted">Discord: <?=e($r['discord'] !== '' ? $r['discord'] : '—')?></td>
<?php if($canManage):?><td class="r">
<div class="act">
<?php if($r['role'] !== 'leader' && !($isLeader === false && $r['role'] === 'vice')):?>
<button class="btn sm btn-ghost" onclick="editRoster(<?=$rosterIdx?>,'<?=e($r['name'])?>','<?=e($r['discord'])?>')">แก้ไข</button>
<form method="post" class="inl" data-confirm="ลบรายการนี้ออกจากรายชื่อ?"><?=csrf_field()?><input type="hidden" name="do" value="delete_roster_entry"><input type="hidden" name="roster_idx" value="<?=$rosterIdx?>"><button class="btn sm warn">ลบ</button></form>
<?php else:?>
<button class="btn sm btn-ghost" onclick="editRoster(<?=$rosterIdx?>,'<?=e($r['name'])?>','<?=e($r['discord'])?>')">แก้ไข</button>
<?php endif;?>
</div>
</td><?php endif;?>
</tr>
<?php endforeach;?>
</tbody></table></div>
<?php endforeach; endif;?></section>

<?php if($canManage && $joinRequests):?>
<section class="srvreg"><h2 class="evh" style="margin-bottom:.6rem">คำขอเข้าร่วม (<?=count($joinRequests)?>)</h2>
<div class="tbl"><table>
<thead><tr><th>ผู้ขอ</th><th>ขอเมื่อ</th><th></th></tr></thead><tbody>
<?php foreach($joinRequests as $r):?>
<tr><td><img class="av" src="<?=e(avatar_url($r,48))?>" alt="" width="24" height="24"> <?=e($r['display_name'] ?: $r['username'])?> <small class="muted">@<?=e($r['username'])?></small></td>
<td><?=e(fmt_full($r['created_at']))?></td>
<td class="r"><div class="act">
<form method="post" class="inl"><?=csrf_field()?><input type="hidden" name="do" value="approve_req"><input type="hidden" name="uid" value="<?=$r['user_id']?>"><button class="btn sm" style="background:#10b981;color:#fff">อนุมัติ</button></form>
<form method="post" class="inl" data-confirm="ปฏิเสธคำขอของ <?=e($r['username'])?>?"><?=csrf_field()?><input type="hidden" name="do" value="reject_req"><input type="hidden" name="uid" value="<?=$r['user_id']?>"><button class="btn sm warn">ปฏิเสธ</button></form>
</div></td></tr>
<?php endforeach;?></tbody></table></div>
</section>
<?php endif;?>

<?php if($canManage):?>
<section class="srvreg" style="margin-top:1.5rem">
<h2 class="evh" style="margin-bottom:.8rem">จัดการแก็ง (หัวหน้า/รองประธาน)</h2>
<form method="post" class="gangform" enctype="multipart/form-data"><?=csrf_field()?><input type="hidden" name="do" value="edit_roster">
<?php if($isLeader):?>
<div class="grow"><label class="muted" style="font-size:.8rem">Logo แก็ง (ใส่ไฟล์ใหม่เพื่อเปลี่ยน)
<input type="file" name="logo" accept="image/jpeg,image/png,image/webp,image/gif"></label></div>
<?php endif;?>

<div class="gcols">
<div>
<fieldset style="border:1px solid var(--line);border-radius:12px;padding:.8rem">
<legend class="muted" style="font-size:.8rem;padding:0 .4rem">หัวหน้าแก็ง</legend>
<div class="grow"><input name="leader_name" maxlength="32" placeholder="ชื่อหัวแก็ง" value="<?=e($rv('leader')[0]['name'] ?? '')?>" <?=!$isLeader ? 'readonly' : ''?>></div>
<div class="grow"><input name="leader_discord" class="dca" maxlength="64" placeholder="ชื่อ Discord" value="<?=e($rv('leader')[0]['discord'] ?? '')?>" <?=!$isLeader ? 'readonly' : ''?>></div>
</fieldset>

<fieldset style="border:1px solid var(--line);border-radius:12px;padding:.8rem;margin-top:.8rem">
<legend class="muted" style="font-size:.8rem;padding:0 .4rem">รองประธานแก็ง (ไม่บังคับ)</legend>
<div class="grow"><input name="vice_name" maxlength="32" placeholder="ชื่อรองประธาน" value="<?=e($rv('vice')[0]['name'] ?? '')?>" <?=!$isLeader ? 'readonly' : ''?>></div>
<div class="grow"><input name="vice_discord" class="dca" maxlength="64" placeholder="ชื่อ Discord" value="<?=e($rv('vice')[0]['discord'] ?? '')?>" <?=!$isLeader ? 'readonly' : ''?>></div>
</fieldset>
</div>

<div>
<fieldset style="border:1px solid var(--line);border-radius:12px;padding:.8rem;height:100%">
<legend class="muted" style="font-size:.8rem;padding:0 .4rem">สมาชิกแก็ง</legend>
<div id="memRows">
<?php $mems = $rv('member'); $n = max(count($mems), 3); for($i=0;$i<$n;$i++): $m = $mems[$i] ?? ['name'=>'','discord'=>'']; ?>
<div class="mrow">
<input name="m_name[]" maxlength="32" placeholder="ชื่อในเกม" value="<?=e($m['name'])?>">
<input name="m_discord[]" class="dca" maxlength="64" placeholder="Discord" value="<?=e($m['discord'])?>">
<button type="button" class="memdel" title="ลบแถวนี้" onclick="this.parentElement.remove()">✕</button>
</div>
<?php endfor;?>
</div>
<button type="button" id="addMem" class="btn btn-sm btn-ghost" style="margin-top:.6rem">+ เพิ่มช่องสมาชิก</button>
</fieldset>
</div>
</div>
<button class="btn btn-sm" style="margin-top:.8rem">บันทึกรายชื่อ</button></form>
<?php if($isLeader):?>
<form method="post" style="margin-top:.8rem" data-confirm="ยุบแก็ง <?=e($g['name'])?>? สมาชิกทั้งหมดจะถูกลบ"><?=csrf_field()?><input type="hidden" name="do" value="disband"><button class="btn btn-sm red">ยุบแก็ง</button></form>
<?php endif;?>
</section>
<?php endif;?>
<script type="application/json" id="dcUsers"><?=json_encode($dcUsers, JSON_UNESCAPED_UNICODE)?></script>
</div></main>
<?php $tagHint = $g['tag'] !== '' ? '[' . $g['tag'] . '] ' : ''; ?>
<?php page_foot(['scripts' => '<script>document.querySelectorAll("[data-confirm]").forEach(f=>f.addEventListener("submit",e=>{if(!confirm(f.dataset.confirm))e.preventDefault()}));
document.querySelectorAll("[data-rename]").forEach(b=>b.onclick=()=>{const n=prompt("ชื่อใหม่ของสมาชิก (ต้องมี TAG ' . e($tagHint) . 'กำกับ):",b.dataset.name);if(n===null)return;document.getElementById("renameUid").value=b.dataset.rename;document.getElementById("renameName").value=n.trim();document.getElementById("renameForm").submit()});
const am=document.getElementById("addMem");if(am)am.onclick=()=>{const d=document.createElement("div");d.className="mrow";const i1=document.createElement("input");i1.name="m_name[]";i1.maxLength=32;i1.placeholder="ชื่อในเกม";const i2=document.createElement("input");i2.name="m_discord[]";i2.className="dca";i2.maxLength=64;i2.placeholder="Discord";const b=document.createElement("button");b.type="button";b.className="memdel";b.title="ลบแถวนี้";b.textContent="✕";b.onclick=()=>d.remove();d.append(i1,i2,b);document.getElementById("memRows").appendChild(d)};
function editRoster(idx,name,disc){const n=prompt("ชื่อในเกม:",name);if(n===null)return;const d=prompt("Discord:",disc);if(d===null)return;const f=document.createElement("form");f.method="post";f.innerHTML=\'<input type="hidden" name="do" value="update_roster_name"><input type="hidden" name="roster_idx" value="\'+idx+\'"><input type="hidden" name="new_name" value="\'+n+\'"><input type="hidden" name="new_discord" value="\'+d+\'">\';document.body.appendChild(f);f.submit();}</script>']);
