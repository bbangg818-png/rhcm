<?php
require __DIR__ . '/includes/bootstrap.php';
require ROOT . '/includes/layout.php';
$icons = require ROOT . '/includes/icons.php';
$vars = ['version' => $c['version'], 'apk_file' => $c['apk_file']];

/* ลิงก์ iOS จาก api/linkwatch.php (Discord auto-update) — มีใน DB แล้วใช้แทน config.php */
$_iosLink = setting('ios_link');
if ($_iosLink !== '') {
    foreach ($c['platforms'] as &$_p) {
        if ($_p['key'] === 'ios') {
            $_p['link'] = $_iosLink;
            $_p['updated'] = (string) setting('ios_link_updated', $_p['updated'] ?? '');
        }
    }
    unset($_p);
}
$title = $c['brand_full'] . ' — ดาวน์โหลดเกมและกิจกรรมชุมชน';
$desc = 'ศูนย์รวมเกมมือถือ ' . $c['brand_full'] . ' ดาวน์โหลดได้หลายแพลตฟอร์ม เข้าร่วมกิจกรรม พูดคุยกับชุมชน และติดตามอัปเดตล่าสุด';
$heroOk = asset_exists($c['hero_image'], $root);
$ld = [
  '@context' => 'https://schema.org',
  '@graph' => [
    ['@type' => 'WebSite', 'url' => $c['site_url'], 'name' => $c['brand_full'], 'inLanguage' => 'th'],
    ['@type' => 'SoftwareApplication', 'name' => $c['brand_full'] . ' APK', 'operatingSystem' => 'Android', 'applicationCategory' => 'GameApplication', 'softwareVersion' => $c['version'], 'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'BRL']],
    ['@type' => 'Organization', 'name' => $c['brand_full'], 'url' => $c['site_url'], 'sameAs' => array_values(array_filter([$L['discord'], $L['whatsapp'], $L['instagram'], $L['tiktok']]))]
  ]
];
$head = '<link rel="canonical" href="' . e($c['site_url']) . '"><meta property="og:type" content="website"><meta property="og:title" content="' . e($title) . '"><meta property="og:description" content="' . e($desc) . '">'
  . ($heroOk ? '<meta property="og:image" content="' . e($c['site_url'] . $c['hero_image']) . '">' : '')
  . '<meta name="twitter:card" content="summary_large_image"><script type="application/ld+json">' . json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . '</script>';

/* dados dinâmicos */
$evAll = rows("SELECT e.*, (SELECT COUNT(*) FROM registrations r WHERE r.event_id=e.id) AS n FROM events e WHERE status='published' AND ends_at > ? ORDER BY starts_at ASC LIMIT 6", [gmdate('Y-m-d H:i:s')]);
$liveNow = array_values(array_filter($evAll, fn($e) => ev_state($e) === 'live'));
$next = array_slice($evAll, 0, 4);
$players = (int) val('SELECT COUNT(*) FROM users');
$wk = (int) val("SELECT COUNT(*) FROM events WHERE status='published' AND starts_at BETWEEN ? AND ?", [gmdate('Y-m-d H:i:s'), gmdate('Y-m-d H:i:s', time() + 7 * 86400)]);
$wd = discord_widget();
$u = me();

page_head($title, ['home' => true, 'active' => 'home', 'desc' => $desc, 'head' => $head]);
?>
<main>
  <section class="hero" id="hero">
    <div class="hbg" id="hbg"></div>
    <div class="container hwrap" id="hc">
      <div class="hgrid">
        <?php $w = explode(' ', $c['brand'], 2); ?>
        <div class="htitle-link">
          <h1 class="htitle"><?= e($w[0]) ?><?php if (isset($w[1])): ?> <span class="c"><?= e($w[1]) ?></span><?php endif; ?>
          </h1>
        </div>
        <a class="btn btn-lg" href="#download"><?= dl_icon(18) ?><span>Download</span></a>
      </div>
      <div class="hstats">
        <?php if ($liveNow):
          $le = $liveNow[0]; ?><a class="hs hs-live" href="<?= url('events/view?id=' . $le['id']) ?>"><span
              class="live"><i></i>กำลังจัด</span><b><?= e($le['title']) ?></b><small>เข้าร่วมกิจกรรม
              →</small></a><?php endif; ?>
        <?php if ($wd): ?>
          <div class="hs"><b><?= $wd['online'] ?></b><small>ออนไลน์บน Discord</small></div><?php endif; ?>
        <div class="hs"><b><?= $players ?></b><small>ผู้เล่นที่ลงทะเบียน</small></div>
        <div class="hs"><b><?= $wk ?></b><small>กิจกรรมใน 7 วันข้างหน้า</small></div>
      </div>
    </div>
    <a href="#download" class="scroll" aria-label="เลื่อนลง"><span>เลื่อนลง</span><svg width="16" height="16"
        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <polyline points="6 9 12 15 18 9" />
      </svg></a>
  </section>

  <section class="section" id="eventos">
    <div class="container">
      <div class="shead rv evtop">
        <div>
          <h2 class="stitle">กำหนดการ <span class="accent">กิจกรรม</span></h2>
          <p class="ssub">สิ่งที่กำลัง <strong>จัดอยู่</strong> และสิ่งที่กำลังจะมาถึงในเมือง</p>
        </div><a class="btn btn-sm btn-ghost" href="<?= url('events/') ?>">ดูกิจกรรมทั้งหมด</a>
      </div>
      <div class="evlist rv">
        <?php foreach ($next as $e):
          $s = ev_state($e);
          [$cn, $cc] = ev_cat($e['category']);
          $d = local_dt($e['starts_at']); ?>
          <a class="evr is-<?= $s ?>" href="<?= url('events/view?id=' . $e['id']) ?>" style="--cc:<?= e($cc) ?>">
            <div class="evd">
              <b><?= $d->format('d') ?></b><span><?= pt_month((int) $d->format('n')) ?></span><small><?= pt_wday((int) $d->format('w')) ?></small>
            </div>
            <div class="evb">
              <div class="evm"><span class="chip" style="--cc:<?= e($cc) ?>"><?= e($cn) ?></span><?php if ($s === 'live'): ?><span
                    class="live"><i></i>กำลังจัด</span><?php endif; ?></div>
              <h3><?= e($e['title']) ?></h3>
              <p><?= e($e['summary']) ?></p>
              <div class="evf">
                <span><?= icon('clock') ?><?= e($d->format('H:i')) ?></span><span><?= icon('pin') ?><?= e($e['location']) ?></span><?php if ($e['reward'] !== ''): ?><span><?= icon('gift') ?><?= e($e['reward']) ?></span><?php endif; ?>
              </div>
            </div>
            <div class="evs">
              <div class="cap">
                <span><?= $e['n'] ?><?= $e['capacity'] > 0 ? '/' . $e['capacity'] : '' ?></span><small>ผู้ลงทะเบียน</small></div>
            </div>
          </a>
        <?php endforeach; ?>
        <?php if (!$next): ?>
          <div class="empty"><strong>ยังไม่มีกิจกรรมที่กำหนดไว้ในขณะนี้</strong><span>ติดตาม Discord
              เพื่อรับข่าวสาร</span></div><?php endif; ?>
      </div>
    </div>
  </section>

  <section class="section" id="download">
    <div class="container">
      <div class="shead rv">
        <h2 class="stitle">Down<span class="accent">load</span></h2>
        <p class="ssub">เลือก <strong>แพลตฟอร์ม</strong> ของคุณแล้วเล่นได้เลย</p>
      </div>
      <div class="rv">
        <div class="dtrack" id="dtrack">
          <?php usort($c['platforms'], fn($a, $b) => ($b['status'] === 'live') <=> ($a['status'] === 'live'));
          foreach ($c['platforms'] as $p):
            $live = $p['status'] === 'live'; ?>
            <div class="dcard<?= $live ? '' : ' soon' ?>">
              <div class="dsym"><?= $icons[$p['key']] ?? '' ?></div>
              <div>
                <h3 class="dt"><?= e($p['name']) ?> <span class="<?= $live ? 'accent' : 'muted' ?>"><?= e($p['tag']) ?></span></h3>
                <span class="dm"><?= str_replace('{version}', e($c['version']), $p['meta']) ?></span>
                <p class="dd"><?= e($p['desc']) ?></p>
                <ul class="feat"><?php foreach ($p['features'] as $f): ?>
                    <li><?= chk() ?><?= e($f) ?></li><?php endforeach; ?>
                </ul>
              </div>
              <?php if ($live): ?>
                <div class="dlbtns">
                  <?= apk_btn($c, 'btn btn-sm', 'ดาวน์โหลด ' . ($p['ext'] ?? 'APK'), 14, $p['file'] ?? null, $p['link'] ?? null, $p['page'] ?? false) ?>    <?php $ex = $p['extra'] ?? (isset($p['data']) ? [$p['data']] : []);
                      foreach ($ex as $d):
                        if (!empty($d['need_reg']) && !($u && !empty($u['game_name']))): ?><a class="btn btn-sm btn-ghost"
                        href="<?= e($u ? url('account/') : url('auth/login')) ?>"
                        title="กรุณาลงทะเบียนเซิร์ฟเวอร์ก่อน"><span><?= e($d['label'] ?? 'Data') ?></span></a><?php else: ?><?= apk_btn($c, 'btn btn-sm', $d['label'] ?? 'Data', 14, $d['file'] ?? null, $d['link'] ?? null, $d['page'] ?? false) ?><?php endif; ?><?php endforeach; ?>
                  <?php if (!empty($p['updated'])): ?><div class="muted"
                      style="font-size:.75rem;margin-top:.45rem;width:100%">อัปเดตล่าสุด:
                      <?= e($p['updated']) ?></div><?php endif; ?>
                </div><?php else: ?><span class="badge">เร็วๆ นี้</span><?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>

  <?php
  $tiktok_videos = [];
  $selected_user_ids = setting('tiktok_live_users') ? json_decode(setting('tiktok_live_users'), true) : [];
  if (!empty($selected_user_ids)) {
    $tiktok_users = rows("SELECT id, tiktok_username, tiktok_access_token FROM users WHERE id IN (" . implode(',', array_map('intval', $selected_user_ids)) . ") AND tiktok_access_token != ''");
    foreach ($tiktok_users as $tu) {
      $videos = get_tiktok_videos($tu['tiktok_access_token']);
      foreach ($videos as $v) {
        $v['tiktok_username'] = $tu['tiktok_username'];
        $tiktok_videos[] = $v;
      }
    }
  }

  $tl = trim((string) ($c['tiktok_live'] ?? ''));
  if ($tl !== '' && empty($tiktok_videos)) {
    if (!str_contains($tl, 'tiktok.com'))
      $tl = 'https://www.tiktok.com/@' . ltrim($tl, '@');
    $tl = rtrim($tl, '/');
    if (!str_ends_with($tl, '/live'))
      $tl .= '/live';
  }
  ?>
  <section class="section" id="mockups">
    <div class="container">
      <div class="shead rv">
        <h2 class="stitle">ชมการ <span class="accent">เล่นเกม</span></h2>
        <p class="ssub">ไลฟ์สดบน <strong>TikTok</strong> — ติดแฮชแท็ก <a
            href="https://www.tiktok.com/tag/รวมหัวcommunity" target="_blank" rel="noopener noreferrer"
            style="color:var(--cyan)"><strong>#รวมหัวcommunity</strong></a> ในไลฟ์</p>
      </div>
      <div class="rv tiktok-live">
        <?php if (!empty($tiktok_videos)): ?>
          <div class="dtrack shots rv">
            <?php foreach (array_slice($tiktok_videos, 0, 6) as $video): ?>
              <div class="shot">
                <div class="shotimg">
                  <?php if (!empty($video['embed_html'])): ?>
                    <?= $video['embed_html'] ?>
                  <?php elseif (!empty($video['share_url'])): ?>
                    <blockquote class="tiktok-embed" cite="<?= e($video['share_url']) ?>" data-embed-from="oembed"
                      style="max-width:360px;min-width:280px;">
                      <section></section>
                    </blockquote>
                  <?php endif; ?>
                </div>
                <span><?= e($video['video_description'] ?? 'วิดีโอ TikTok') ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php elseif ($tl): ?>
          <blockquote class="tiktok-embed" cite="<?= e($tl) ?>" data-embed-from="oembed"
            style="max-width:360px;min-width:280px;">
            <section></section>
          </blockquote>
          <script async src="https://www.tiktok.com/embed.js"></script>
        <?php else: ?>
          <div class="empty"><strong>ไลฟ์สดเร็วๆ นี้</strong><span>ติดตาม TikTok ของเราเพื่อไม่พลาดการไลฟ์
              #รวมหัวcommunity</span></div>
        <?php endif; ?>
      </div>
    </div>
  </section>
  <?php if ($c['screenshots']): ?>
    <section class="section" id="samples">
      <div class="container">
        <div class="shead rv">
          <h2 class="stitle">ตัวอย่าง <span class="accent">เกม</span></h2>
          <p class="ssub">บรรยากาศและภาพจาก<strong>ในเกม</strong></p>
        </div>
        <div class="dtrack shots rv">
          <?php foreach ($c['screenshots'] as $s): ?>
            <div class="shot">
              <div class="shotimg<?= !empty($s['video']) ? ' vid' : '' ?>">
                <?php if (!empty($s['video']) && asset_exists($s['video'], $root)): ?>
                  <div class="vidbg"><video src="<?= e(url($s['video'])) ?>" autoplay muted loop playsinline preload="auto"
                      aria-hidden="true"></video></div><video class="vidfg" src="<?= e(url($s['video'])) ?>" autoplay muted loop
                    playsinline preload="auto"></video><?php elseif (asset_exists($s['file'], $root)): ?><img
                    src="<?= e(url($s['file'])) ?>" alt="<?= e($s['title']) ?>" loading="lazy" decoding="async"><?php endif; ?>
              </div><span><?= e($s['title']) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <?php if (false): // ซ่อนวิธีเล่นชั่วคราว ?>
    <section class="section" id="tutorial">
      <div class="container">
        <div class="shead rv">
          <h2 class="stitle">วิธี <span class="accent">เล่น</span></h2>
          <p class="ssub">เพียง <strong><?= count($c['steps']) ?> ขั้นตอนง่ายๆ</strong> เพื่อเข้าสู่เมือง</p>
        </div>
        <div class="steps">
          <?php foreach ($c['steps'] as $i => $s): ?>
            <div class="step rv" style="transition-delay:<?= $i * .08 ?>s">
              <div class="sic"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                  stroke-width="2.5"><?php foreach (explode('|', $s[2]) as $d):
                    if (preg_match('/^[\d. ]+$/', $d)): ?>
                      <polyline points="<?= e($d) ?>" /><?php else: ?>
                      <path d="<?= e($d) ?>" /><?php endif; endforeach; ?>
                </svg></div>
              <div>
                <h4><strong><?= $i + 1 ?>.</strong> <?= e($s[0]) ?></h4><span><?= fill($s[1], $vars) ?></span>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  <?php endif; ?>

</main>

<?php page_foot(['home' => true]);
