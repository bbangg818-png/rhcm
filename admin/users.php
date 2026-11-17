<?php
require __DIR__ . '/_layout.php';
if (is_post()) {
    csrf_check();
    $uid = (int) $_POST['id'];
    $a = $_POST['do'] ?? '';
    if ($uid === (int) $admin['id'] && $a !== 'game_name') {
        flash('ไม่สามารถแก้ไขบัญชีของตัวเองได้', 'err');
        redirect('users');
    }
    if ($a === 'role') {
        q('UPDATE users SET role=? WHERE id=?', [$_POST['role'] === 'admin' ? 'admin' : 'user', $uid]);
        flash('เปลี่ยนสิทธิ์แล้ว');
    }
    if ($a === 'ban') {
        q('UPDATE users SET banned=? WHERE id=?', [(int) $_POST['ban'], $uid]);
        flash($_POST['ban'] ? 'แบนผู้ใช้แล้ว' : 'ปลดแบนแล้ว');
    }
    if ($a === 'game_name') {
        $gn = trim((string) ($_POST['game_name'] ?? ''));
        if (mb_strlen($gn) > 24)
            $gn = mb_substr($gn, 0, 24);
        if ($gn !== '' && val('SELECT id FROM users WHERE game_name=? AND id<>?', [$gn, $uid])) {
            flash('ชื่อในเกมนี้มีคนใช้อยู่แล้ว', 'err');
        } else {
            q('UPDATE users SET game_name=? WHERE id=?', [$gn, $uid]);
            flash('อัปเดตชื่อในเกมแล้ว' . ($gn !== '' ? ': ' . $gn : ' (ล้างชื่อออก)'));
        }
    }
    if ($a === 'kick_game') {
        $grow = row('SELECT game_name FROM users WHERE id=?', [$uid]);
        $gn = trim((string) ($grow['game_name'] ?? ''));
        if ($gn === '') {
            flash('ผู้ใช้นี้ยังไม่ได้ตั้งชื่อในเกม', 'err');
        } else {
            $kicks = json_decode(setting('mod_kicks', '[]'), true);
            if (!is_array($kicks)) $kicks = [];
            $kicks = array_values(array_filter($kicks, fn($k) => ($k['name'] ?? '') !== $gn));
            $kicks[] = ['name' => $gn, 'until' => time() + 600, 'by' => (string) $admin['discord_id']];
            set_setting('mod_kicks', json_encode($kicks, JSON_UNESCAPED_UNICODE));
            flash('สั่งเตะ ' . $gn . ' ออกจากเซิร์ฟเวอร์เกมแล้ว (มีผลภายใน ~1 นาที)');
        }
    }
    if ($a === 'unkick_game') {
        $grow = row('SELECT game_name FROM users WHERE id=?', [$uid]);
        $gn = trim((string) ($grow['game_name'] ?? ''));
        if ($gn === '') {
            flash('ผู้ใช้นี้ยังไม่ได้ตั้งชื่อในเกม', 'err');
        } else {
            $kicks = json_decode(setting('mod_kicks', '[]'), true);
            if (!is_array($kicks)) $kicks = [];
            $kicks = array_values(array_filter($kicks, fn($k) => ($k['name'] ?? '') !== $gn));
            set_setting('mod_kicks', json_encode($kicks, JSON_UNESCAPED_UNICODE));
            flash('ยกเลิกคำสั่งเตะ ' . $gn . ' แล้ว');
        }
    }
    if ($a === 'ban_game' || $a === 'unban_game') {
        $grow = row('SELECT game_name FROM users WHERE id=?', [$uid]);
        $gn = trim((string) ($grow['game_name'] ?? ''));
        if ($gn === '') {
            flash('ผู้ใช้นี้ยังไม่ได้ตั้งชื่อในเกม', 'err');
        } else {
            $bans = json_decode(setting('mod_bans', '[]'), true);
            if (!is_array($bans)) $bans = [];
            if ($a === 'ban_game') {
                $bans = array_values(array_filter($bans, fn($b) => ($b['name'] ?? '') !== $gn));
                $bans[] = ['name' => $gn, 'by' => (string) $admin['discord_id']];
                set_setting('mod_bans', json_encode($bans, JSON_UNESCAPED_UNICODE));
                flash('แบน ' . $gn . ' ในเกมแล้ว (มีผลภายใน ~1 นาที)');
            } else {
                $bans = array_values(array_filter($bans, fn($b) => ($b['name'] ?? '') !== $gn));
                set_setting('mod_bans', json_encode($bans, JSON_UNESCAPED_UNICODE));
                flash('ปลดแบน ' . $gn . ' ในเกมแล้ว');
            }
        }
    }
    redirect('users' . (!empty($_GET['q']) ? '?q=' . urlencode($_GET['q']) : ''));
}
$s = trim((string) ($_GET['q'] ?? ''));
$normName = fn($n) => preg_replace('/\s+/u', '', mb_strtolower(trim((string) $n)));
$gameBans = array_map($normName, array_map(fn($b) => $b['name'] ?? '', (array) (json_decode(setting('mod_bans', '[]'), true) ?: [])));
$gameKicks = array_map($normName, array_map(fn($k) => $k['name'] ?? '', array_filter((array) (json_decode(setting('mod_kicks', '[]'), true) ?: []), fn($k) => (int) ($k['until'] ?? 0) > time())));
$list = $s !== '' ? rows("SELECT u.*,(SELECT COUNT(*) FROM registrations r WHERE r.user_id=u.id) regs FROM users u WHERE username LIKE ? OR display_name LIKE ? OR game_name LIKE ? OR discord_id=? ORDER BY last_login DESC LIMIT 200", ["%$s%", "%$s%", "%$s%", $s])
    : rows('SELECT u.*,(SELECT COUNT(*) FROM registrations r WHERE r.user_id=u.id) regs FROM users u ORDER BY last_login DESC LIMIT 200');
admin_head('ผู้ใช้', 'users'); ?>
<form class="bar2" method="get"><input class="search dca" name="q" data-nolock value="<?= e($s) ?>"
        placeholder="ค้นหาชื่อ, ชื่อในเกม หรือ Discord ID"><button class="btn ghost">ค้นหา</button></form>
<section class="panel">
    <table>
        <thead>
            <tr>
                <th>ผู้ใช้</th>
                <th>ชื่อในเกม</th>
                <th class="n">กิจกรรม</th>
                <th>เข้าล่าสุด</th>
                <th>สิทธิ์</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($list as $u):
                $self = $u['id'] == $admin['id']; ?>
                <tr class="<?= $u['banned'] ? 'dim' : '' ?>">
                    <td><img class="av" src="<?= e(avatar_url($u, 48)) ?>" alt="" width="28" height="28">
                        <strong><?= e(user_name($u)) ?></strong>
                        <?= $u['banned'] ? pill('ถูกแบน', '#ef4444') : '' ?><br><small>@<?= e($u['username']) ?> ·
                            <?= e($u['discord_id']) ?></small></td>
                    <td><?php if ($u['game_name'] !== ''): ?><strong
                                class="accent"><?= e($u['game_name']) ?></strong><?php else: ?><span class="muted">—
                                ยังไม่ลงทะเบียน —</span><?php endif; ?>
                            <details style="display:inline">
                                <summary class="muted" style="cursor:pointer;font-size:.75rem">
                                    <?= e($u['game_name'] !== '' ? 'แก้ไข' : 'ตั้งชื่อ') ?></summary>
                                <form method="post" class="inl" style="margin-top:.3rem"><?= csrf_field() ?><input type="hidden"
                                        name="do" value="game_name"><input type="hidden" name="id" value="<?= $u['id'] ?>"><input
                                        name="game_name" maxlength="24" value="<?= e($u['game_name']) ?>" placeholder="ชื่อในเกม"
                                        required style="width:9rem"><button class="btn sm ghost">บันทึก</button></form>
                            </details>
                    </td>
                    <td class="n"><?= $u['regs'] ?></td>
                    <td><?= e(fmt_full($u['last_login'])) ?></td>
                    <td><?php if ($self): ?><?= pill('แอดมิน (คุณ)', '#10b981') ?><?php else: ?>
                            <form method="post" class="inl" data-confirm="เปลี่ยนสิทธิ์ของ <?= e(user_name($u)) ?>?">
                                <?= csrf_field() ?><input type="hidden" name="do" value="role"><input type="hidden" name="id"
                                    value="<?= $u['id'] ?>"><select name="role" onchange="this.form.requestSubmit()">
                                    <option value="user" <?= $u['role'] === 'user' ? 'selected' : '' ?>>ผู้เล่น</option>
                                    <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>แอดมิน</option>
                                </select></form><?php endif; ?>
                    </td>
                    <td class="n"><?php if (!$self): ?><div class="act">
                            <?php if ($u['game_name'] !== ''): ?>
                            <form method="post" data-confirm="สั่งเตะ <?= e($u['game_name']) ?> ออกจากเซิร์ฟเวอร์เกม?"><?= csrf_field() ?><input type="hidden"
                                    name="do" value="kick_game"><input type="hidden" name="id" value="<?= $u['id'] ?>"><button
                                    class="btn sm warn"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>เตะ</button></form>
                            <?php if (in_array($normName($u['game_name']), $gameKicks, true)): ?>
                            <form method="post" data-confirm="ยกเลิกคำสั่งเตะ <?= e($u['game_name']) ?>?">
                                <?= csrf_field() ?><input type="hidden" name="do" value="unkick_game"><input type="hidden" name="id"
                                    value="<?= $u['id'] ?>"><button class="btn sm ghost">ยกเลิกเตะ</button>
                            </form>
                            <?php endif; ?>
                            <?php $inGameBan = in_array($normName($u['game_name']), $gameBans, true); ?>
                            <form method="post" data-confirm="<?= $inGameBan ? 'ปลดแบน' : 'แบน' ?> <?= e($u['game_name']) ?> ในเกม?(แบนแล้วเข้าเซิร์ฟเวอร์เกมไม่ได้)">
                                <?= csrf_field() ?><input type="hidden" name="do" value="<?= $inGameBan ? 'unban_game' : 'ban_game' ?>"><input type="hidden" name="id"
                                    value="<?= $u['id'] ?>"><button
                                    class="btn sm <?= $inGameBan ? 'ghost' : 'red' ?>"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M5.6 5.6l12.8 12.8"/></svg><?= $inGameBan ? 'ปลดแบนเกม' : 'แบนในเกม' ?></button>
                            </form>
                            <?php endif; ?>
                            <form method="post" data-confirm="<?= $u['banned'] ? 'ปลดแบน' : 'แบน' ?> <?= e(user_name($u)) ?>?">
                                <?= csrf_field() ?><input type="hidden" name="do" value="ban"><input type="hidden" name="id"
                                    value="<?= $u['id'] ?>"><input type="hidden" name="ban" value="<?= $u['banned'] ? 0 : 1 ?>"><button
                                    class="btn sm <?= $u['banned'] ? 'ghost' : 'red' ?>"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M5.6 5.6l12.8 12.8"/></svg><?= $u['banned'] ? 'ปลดแบน' : 'แบน' ?></button>
                            </form></div><?php endif; ?>
                    </td>
                </tr><?php endforeach; ?>
            <?php if (!$list): ?>
                <tr>
                    <td colspan="6" class="empty">ไม่พบผู้ใช้</td>
                </tr><?php endif; ?>
        </tbody>
    </table>
</section>
<?php admin_foot();
