<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require ROOT . '/includes/layout.php';

$u = me();

/* เก็บ roster จาก POST: [name, discord] pairs */
function roster_from_post(): array {
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

/* สร้างแก็งใหม่ (ต้อง login + ยังไม่อยู่แก็งไหน) */
if (is_post() && $u) {
    csrf_check();
    if (($_POST['do'] ?? '') === 'create') {
        // ตรวจสอบว่า user อยู่ในแก็งอื่นหรือมีแก็งที่รออนุมัติอยู่หรือไม่
        $inGang = val('SELECT 1 FROM gang_members WHERE user_id=?', [$u['id']]);
        $hasPendingGang = val('SELECT 1 FROM gangs WHERE leader_id=? AND status=\'pending\'', [$u['id']]);
        
        if ($inGang) {
            flash('คุณอยู่ในแก็งอยู่แล้ว', 'err');
        } elseif ($hasPendingGang) {
            flash('คุณมีคำขอสร้างแก็งที่รออนุมัติอยู่ กรุณารอแอดมินตรวจสอบ', 'err');
        } else {
            $name = trim((string)($_POST['name'] ?? ''));
            $tag  = mb_strtoupper(mb_substr(trim((string)($_POST['tag'] ?? '')), 0, 5));
            $roster = roster_from_post();
            if (mb_strlen($name) < 3 || mb_strlen($name) > 10) {
                flash('ชื่อแก็งต้องยาว 3-10 ตัวอักษร', 'err');
            } elseif ($tag === '') {
                flash('กรุณากรอก TAG ของแก็ง', 'err');
            } elseif (val('SELECT id FROM gangs WHERE name=?', [$name])) {
                flash('ชื่อแก็งนี้มีคนใช้แล้ว', 'err');
            } elseif (!array_filter($roster, fn($r) => $r['role'] === 'leader' && $r['name'] !== '' && $r['discord'] !== '')) {
                flash('กรุณากรอกชื่อและ Discord ของหัวหน้าแก็งให้ครบ', 'err');
            } elseif (!array_filter($roster, fn($r) => $r['role'] === 'vice' && $r['name'] !== '' && $r['discord'] !== '')) {
                flash('กรุณากรอกชื่อและ Discord ของรองประธานแก็งให้ครบ', 'err');
            } elseif (count(array_filter($roster, fn($r) => $r['role'] === 'member' && $r['name'] !== '' && $r['discord'] !== '')) < 3) {
                flash('กรุณากรอกชื่อและ Discord ของสมาชิกแก็งให้ครบ 3 คน', 'err');
            } else {
                $logo = gang_logo_upload($_FILES['logo'] ?? []);
                q('INSERT INTO gangs(name,tag,description,roster,logo,leader_id,status,created_at) VALUES(?,?,?,?,?,?,?,?)',
                  [$name, $tag, '', json_encode($roster, JSON_UNESCAPED_UNICODE), $logo ?? '', $u['id'], 'pending', gmdate('Y-m-d H:i:s')]);
                $gid = (int)db()->lastInsertId();
                q('INSERT INTO gang_members(gang_id,user_id,role,joined_at) VALUES(?,?,?,?)',
                  [$gid, $u['id'], 'leader', gmdate('Y-m-d H:i:s')]);
                flash('สร้างแก็ง ' . $name . ' แล้ว — รอแอดมินอนุมัติ');
                redirect(url('gangs/view?id=' . $gid));
            }
        }
    }
    redirect(url('gangs/'));
}

$myGang = $u ? row('SELECT g.* FROM gangs g JOIN gang_members m ON m.gang_id=g.id WHERE m.user_id=?', [$u['id']]) : null;
$gangs = rows("SELECT g.*, g.roster,
                       u.username AS leader_name, u.display_name AS leader_dn, u.avatar AS leader_av, u.discord_id AS leader_did
                FROM gangs g JOIN users u ON u.id=g.leader_id
                WHERE g.status='approved' ORDER BY g.name ASC");

// Calculate member count from roster
foreach ($gangs as &$g) {
    $roster = json_decode((string)($g['roster'] ?? ''), true);
    if (!is_array($roster)) $roster = [];
    $roster = array_values(array_filter($roster, fn($r) => is_array($r) && (trim((string)($r['name'] ?? '')) !== '' || trim((string)($r['discord'] ?? '')) !== '')));
    $g['n'] = count($roster);
}
unset($g);

$dcUsers = array_map(fn($r) => ['u' => $r['username'], 'd' => $r['display_name'] ?? '', 'n' => mb_strtolower(($r['display_name'] ?? '') . ' ' . $r['username'])],
                      rows('SELECT username, display_name FROM users ORDER BY username LIMIT 2000'));

page_head('แก็ง — ' . $c['brand_full'], ['active' => 'gangs', 'desc' => 'รวมแก็งของ ' . $c['brand_full']]);
?>
<main class="store-wrap"><div class="container">
<div class="shead"><h1 class="stitle">แก็ง<span class="accent">ชุมชน</span></h1>
<p class="ssub">รวมแก็งของ <?=e($c['brand_full'])?> — ต้องได้รับอนุมัติจากแอดมินก่อน</p></div>
<?=flash_html()?>

<section class="srvreg"><details <?=(!$u || !$myGang)?'open':''?>><summary>สร้างแก็งใหม่</summary>
<?php if(!$u):?>
<p class="muted" style="margin:.6rem 0">กรุณา<a href="<?=url('auth/login')?>?next=<?=urlencode(url('gangs/'))?>">เข้าสู่ระบบด้วย Discord</a>ก่อนสร้างแก็ง</p>
<?php elseif($myGang):?>
<p class="muted" style="margin:.6rem 0">คุณอยู่ในแก็ง <strong class="accent"><?=e($myGang['name'])?></strong> แล้ว</p>
<?php else:?>
<form method="post" class="gangform" enctype="multipart/form-data"><?=csrf_field()?><input type="hidden" name="do" value="create">

<div class="gcols glogo">
<div class="gf-sec">
<div class="gf-title">ข้อมูลแก็ง</div>
<div class="gtop">
<input name="name" maxlength="10" placeholder="ชื่อแก็ง (3-10 ตัวอักษร)" required>
<input name="tag" maxlength="5" placeholder="TAG (1-5 ตัวอักษร)" style="text-transform:uppercase" required>
</div>
</div>

<div class="gf-sec">
<div class="gf-title">Logo แก็ง <small>ไม่บังคับ — jpg/png/webp ≤2MB</small></div>
<input type="file" name="logo" accept="image/jpeg,image/png,image/webp,image/gif">
</div>
</div>

<div class="gcols">
<div class="gf-sec">
<div class="gf-title">ผู้นำแก็ง</div>
<div class="gf-grid" style="grid-template-columns:1fr">
<div class="gf-card">
<span class="gf-lab">หัวหน้าแก็ง</span>
<input name="leader_name" maxlength="32" placeholder="ชื่อในเกม" required>
<input name="leader_discord" class="dca" maxlength="64" placeholder="Discord" style="margin-top:.5rem" required>
</div>
<div class="gf-card" style="margin-top:.6rem">
<span class="gf-lab">รองประธานแก็ง</span>
<input name="vice_name" maxlength="32" placeholder="ชื่อในเกม" required>
<input name="vice_discord" class="dca" maxlength="64" placeholder="Discord" style="margin-top:.5rem" required>
</div>
</div>
</div>

<div class="gf-sec">
<div class="gf-title">สมาชิกแก็ง <small>3 คน</small></div>
<?php for($i=1;$i<=3;$i++):?>
<div class="memrow">
<span class="memno"><?=$i?></span>
<input name="m_name[]" maxlength="32" placeholder="ชื่อในเกม" required>
<input name="m_discord[]" class="dca" maxlength="64" placeholder="Discord" required>
</div>
<?php endfor;?>
</div>
</div>

<div class="gf-foot">
<button class="btn btn-sm">ส่งคำขอสร้างแก็ง</button>
<span class="muted" style="font-size:.78rem">ชื่อแก็งต้องไม่ซ้ำ · ต้องรอแอดมินอนุมัติ</span>
</div></form>
<?php endif;?></details></section>

<?php if($myGang):?>
<section class="srvreg"><p style="margin:.4rem 0">คุณอยู่ในแก็ง: <a href="<?=url('gangs/view?id='.$myGang['id'])?>"><strong class="accent"><?=e($myGang['name'])?></strong></a>
<?php if($myGang['status']==='pending'):?> <span class="muted">(รอแอดมินอนุมัติ)</span><?php elseif($myGang['status']==='rejected'):?> <span class="muted">(ถูกปฏิเสธ)</span><?php endif;?></p></section>
<?php endif;?>

<section class="evsec"><h2 class="evh">รายชื่อแก็งใน รวมหัว Community<small><?=count($gangs)?></small></h2>
<?php if(!$gangs):?><div class="empty"><strong>ยังไม่มีแก็ง</strong><span>เป็นคนแรกที่สร้างแก็งของคุณ</span></div>
<?php else:?><div class="tbl"><table>
<thead><tr><th>แก็ง</th><th>TAG</th><th>หัวหน้า</th><th class="n">สมาชิก</th><th></th></tr></thead><tbody>
<?php foreach($gangs as $g):?>
<tr><td><?php if(!empty($g['logo'])):?><img src="<?=e(gang_logo_src($g['logo']))?>" alt="" width="36" height="36" style="border-radius:50%;object-fit:cover;vertical-align:middle;margin-right:.5rem"><?php endif;?><strong><?=e($g['name'])?></strong></td>
<td><?=e($g['tag']!=='')?'['.$g['tag'].']':'—'?></td>
<td><img class="av" src="<?=e(avatar_url(['discord_id'=>$g['leader_did'],'avatar'=>$g['leader_av']],48))?>" alt="" width="24" height="24"> <?=e($g['leader_dn'] ?: $g['leader_name'])?></td>
<td class="n"><?=$g['n']?></td>
<td class="r"><a class="btn btn-sm btn-ghost" href="<?=url('gangs/view?id='.$g['id'])?>">ดูแก็ง</a></td></tr>
<?php endforeach;?></tbody></table></div><?php endif;?>
</section>
<script type="application/json" id="dcUsers"><?=json_encode($dcUsers, JSON_UNESCAPED_UNICODE)?></script>
</div></main>
<?php page_foot();
