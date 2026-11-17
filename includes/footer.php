<?php $base = $base ?? ''; ?>
<footer class="footer">
    <div class="container">
        <div class="fg">
            <div>
                <div class="brand" style="margin-bottom:.8rem"><span
                        class="mark"><?= logo_html($c, $root, $base) ?></span><span><?= e(strtoupper($c['brand'])) ?></span>
                </div>
                <p class="muted" style="font-size:.85rem;max-width:280px">เซิร์ฟเวอร์เกมมือถือ พร้อม
                    <strong>ดาวน์โหลดหลายแพลตฟอร์ม</strong> และชุมชนที่กระตือรือร้น</p>
            </div>
            <div>
                <h5>เมนู</h5>
                <ul class="fl">
                    <li><a href="<?= $base ?>#hero">หน้าแรก</a></li>
                    <li><a href="<?= $base ?>events/">กิจกรรม</a></li>
                    <li><a href="<?= $base ?>#download">ดาวน์โหลด</a></li>
                </ul>
            </div>
            <div>
                <h5>ชุมชน</h5>
                <ul class="fl">
                    <li><a <?= ext($L['discord']) ?>>Discord</a></li>
                    <li><a <?= ext($L['tiktok']) ?>>TikTok</a></li>
                </ul>
            </div>
            <div>
                <h5>ดาวน์โหลด</h5>
                <ul class="fl">
                    <?php foreach ($c['platforms'] as $p):
                        $live = $p['status'] === 'live'; ?>
                        <li><?php if (!$live): ?><span class="muted"><?= e($p['name']) ?> (เร็วๆ
                                    นี้)</span><?php elseif (!empty($p['link'])): ?><a href="<?= e($p['link']) ?>"
                                    <?= ($p['page'] ?? false) ? ' target="_blank" rel="noopener noreferrer"' : '' ?>><?= e($p['name']) ?></a><?php else: ?><a href="#" data-toast
                                    data-msg="ยังไม่มีให้ดาวน์โหลด"><?= e($p['name']) ?></a><?php endif; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <p class="fb">© <?= date('Y') ?> <?= e($c['brand_full']) ?> สงวนลิขสิทธิ์</p>
    </div>
</footer>
<a class="fab" aria-label="เปิด Discord" <?= ext($L['discord']) ?>><svg width="26" height="26" viewBox="0 0 24 24"
        fill="#fff">
        <path
            d="M20.300 4.400a19.800 19.800 0 0 0-4.900-1.500c-.2.400-.4.900-.6 1.300a18.300 18.300 0 0 0-5.500 0c-.2-.4-.4-.9-.6-1.300A19.700 19.700 0 0 0 3.700 4.400C.5 9 -.3 13.600.1 18.100a19.900 19.900 0 0 0 6 3c.5-.6.900-1.300 1.200-2-.7-.3-1.300-.6-1.900-.9l.4-.3c3.900 1.800 8.200 1.800 12.100 0l.4.300c-.6.400-1.200.700-1.900.9.400.7.800 1.400 1.200 2a19.800 19.800 0 0 0 6-3c.5-5.200-.8-9.700-3.500-13.700M8 15.300c-1.200 0-2.200-1.100-2.200-2.400s1-2.400 2.200-2.400 2.200 1.100 2.200 2.400-1 2.400-2.200 2.400m8 0c-1.200 0-2.200-1.100-2.200-2.400s1-2.400 2.200-2.400 2.200 1.100 2.200 2.400-1 2.400-2.200 2.400" />
    </svg></a>
<div class="toast" id="toast" role="status">ยังไม่มีการอัพเดท</div>