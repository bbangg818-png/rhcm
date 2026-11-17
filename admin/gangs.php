<?php
require __DIR__ . '/_layout.php';

// ป้องกัน browser cache
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

if (is_post()) {
    csrf_check();
    $gid = (int)($_POST['id'] ?? 0);
    $a = $_POST['do'] ?? '';
    if ($gid && in_array($a, ['approve', 'reject', 'delete'], true)) {
        if ($a === 'delete') {
            // ลบสมาชิกทั้งหมดก่อนลบแก็ง
            q('DELETE FROM gang_members WHERE gang_id=?', [$gid]);
            q('DELETE FROM gangs WHERE id=?', [$gid]);
            flash('ลบแก็งแล้ว');
        } else {
            $st = $a === 'approve' ? 'approved' : 'rejected';
            // ถ้าปฏิเสธ ให้ลบสมาชิกทั้งหมดออกจากแก็ง
            if ($a === 'reject') {
                q('DELETE FROM gang_members WHERE gang_id=?', [$gid]);
            }
            q('UPDATE gangs SET status=?, reviewed_at=? WHERE id=?', [$st, gmdate('Y-m-d H:i:s'), $gid]);
            flash($a === 'approve' ? 'อนุมัติแก็งแล้ว' : 'ปฏิเสธแก็งแล้ว');
        }
    }
    redirect('gangs' . (!empty($_GET['f']) ? '?f=' . urlencode($_GET['f']) : ''));
}

$f = $_GET['f'] ?? 'pending';
$where = in_array($f, ['pending', 'approved', 'rejected', 'all'], true) ? ($f === 'all' ? '1=1' : "g.status='" . $f . "'") : "g.status='pending'";
$list = rows("SELECT g.*, u.username AS leader_name, u.display_name AS leader_dn, u.avatar AS leader_av, u.discord_id AS leader_did,
                     (SELECT COUNT(*) FROM gang_members m WHERE m.gang_id=g.id) AS n
              FROM gangs g JOIN users u ON u.id=g.leader_id
              WHERE $where ORDER BY g.created_at DESC LIMIT 200");
$counts = ['pending' => (int)val("SELECT COUNT(*) FROM gangs WHERE status='pending'"),
           'approved' => (int)val("SELECT COUNT(*) FROM gangs WHERE status='approved'"),
           'rejected' => (int)val("SELECT COUNT(*) FROM gangs WHERE status='rejected'"),
           'all' => (int)val("SELECT COUNT(*) FROM gangs")];

admin_head('แก็ง', 'gangs'); ?>
<div class="chips" style="margin-bottom:.8rem">
<?php foreach (['pending' => 'รออนุมัติ', 'approved' => 'อนุมัติแล้ว', 'rejected' => 'ถูกปฏิเสธ', 'all' => 'ทั้งหมด'] as $k => $n): ?>
<a class="chip <?= $f === $k ? 'on' : '' ?>" href="?f=<?= $k ?>"><?= $n ?> (<?= $counts[$k] ?? '' ?>)</a>
<?php endforeach; ?>
</div>
<section class="panel">
    <table>
        <thead><tr><th>แก็ง</th><th>TAG</th><th>หัวหน้า</th><th class="n">สมาชิก</th><th>สร้างเมื่อ</th><th>สถานะ</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($list as $g): ?>
            <tr>
                <td><?php if (!empty($g['logo'])): ?><img src="<?= e(gang_logo_src($g['logo'])) ?>" alt="" width="32" height="32" style="border-radius:50%;object-fit:cover;vertical-align:middle;margin-right:.4rem"><?php endif; ?><strong><?= e($g['name']) ?></strong><?php if (!empty($g['description'])): ?><br><small class="muted"><?= e(mb_strimwidth($g['description'], 0, 80, '…')) ?></small><?php endif; ?></td>
                <td><?= e($g['tag'] !== '' ? '[' . $g['tag'] . ']' : '—') ?></td>
                <td><img class="av" src="<?= e(avatar_url(['discord_id' => $g['leader_did'], 'avatar' => $g['leader_av']], 48)) ?>" alt="" width="24" height="24"> <?= e($g['leader_dn'] ?: $g['leader_name']) ?></td>
                <td class="n"><?= $g['n'] ?></td>
                <td><?= e(fmt_full($g['created_at'])) ?></td>
                <td><?= pill(['pending' => 'รออนุมัติ', 'approved' => 'อนุมัติ', 'rejected' => 'ปฏิเสธ'][$g['status']] ?? $g['status'], ['pending' => '#f59e0b', 'approved' => '#10b981', 'rejected' => '#ef4444'][$g['status']] ?? '#6b7f94') ?></td>
                <td class="n"><div class="act">
                    <a class="btn sm ghost" href="<?= url('gangs/view?id=' . $g['id']) ?>" target="_blank">ดู</a>
                    <?php if ($g['status'] === 'pending'): ?>
                    <form method="post" class="inl"><?= csrf_field() ?><input type="hidden" name="do" value="approve"><input type="hidden" name="id" value="<?= $g['id'] ?>"><button class="btn sm" style="background:#10b981;color:#fff">อนุมัติ</button></form>
                    <form method="post" class="inl" data-confirm="ปฏิเสธแก็ง <?= e($g['name']) ?>?"><?= csrf_field() ?><input type="hidden" name="do" value="reject"><input type="hidden" name="id" value="<?= $g['id'] ?>"><button class="btn sm warn">ปฏิเสธ</button></form>
                    <?php elseif ($g['status'] === 'rejected'): ?>
                    <form method="post" class="inl"><?= csrf_field() ?><input type="hidden" name="do" value="approve"><input type="hidden" name="id" value="<?= $g['id'] ?>"><button class="btn sm" style="background:#10b981;color:#fff">อนุมัติ</button></form>
                    <?php endif; ?>
                    <form method="post" class="inl" data-confirm="ลบแก็ง <?= e($g['name']) ?> ถาวร?"><?= csrf_field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= $g['id'] ?>"><button class="btn sm red">ลบ</button></form>
                </div></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$list): ?><tr><td colspan="7" class="empty">ไม่มีแก็งในหมวดนี้</td></tr><?php endif; ?>
        </tbody>
    </table>
</section>
<?php admin_foot();
