<?php
/**
 * Núcleo do sistema: sessão, banco SQLite, autenticação, CSRF, configurações.
 * Toda página começa com: require __DIR__.'/includes/bootstrap.php';
 */
define('ROOT', dirname(__DIR__));
// buffer output so PHP warnings/notices never break header('Location:') redirects
// (serverless FS มักเขียน session file ไม่ได้ → warning → redirect พัง → หน้าขาว)
if (!ob_get_level()) ob_start();
$root = ROOT;
$c = require ROOT . '/config.php';
require ROOT . '/includes/helpers.php';

date_default_timezone_set('UTC');           // banco sempre em UTC
mb_internal_encoding('UTF-8');

/* ───────── URL base (funciona em /, em subpasta, XAMPP, etc.) ───────── */
$_doc = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
$_me  = realpath(ROOT) ?: ROOT;
$_base = ($_doc && strpos(str_replace('\\', '/', $_me), str_replace('\\', '/', $_doc)) === 0)
    ? substr(str_replace('\\', '/', $_me), strlen(str_replace('\\', '/', $_doc))) : '';
define('BASE', rtrim($_base, '/'));
function url(string $p = ''): string { return BASE . '/' . ltrim($p, '/'); }
function origin(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}
function redirect(string $to): never { header('Location: ' . $to); exit; }

/* ───────── โฟลเดอร์ข้อมูล (override ได้สำหรับ serverless/volume) ───────── */
$DATA_DIR = ($c['data_dir'] ?? '') !== '' ? rtrim($c['data_dir'], '/\\') : ROOT . '/data';
if (!is_dir($DATA_DIR)) @mkdir($DATA_DIR, 0775, true);

/* ───────── Sessão ───────── */
if (session_status() === PHP_SESSION_NONE) {
    if (($c['data_dir'] ?? '') !== '') {
        $sdir = $DATA_DIR . '/sessions';
        if (!is_dir($sdir)) @mkdir($sdir, 0775, true);
        @ini_set('session.save_path', $sdir);
    }
    session_name('nc_sess');
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 365, 'path' => '/', 'httponly' => true,
        'samesite' => 'Lax', 'secure' => str_starts_with(origin(), 'https'),
    ]);
    session_start();
}

/* ───────── Banco de dados (MySQL/MariaDB) ───────── */
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    global $c;
    $cfg = $c['db'] ?? [];
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $cfg['host'] ?? 'localhost',
        $cfg['port'] ?? 3306,
        $cfg['name'] ?? 'rhcm_community',
        $cfg['charset'] ?? 'utf8mb4'
    );
    $pdo = new PDO($dsn, $cfg['user'] ?? 'root', $cfg['pass'] ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ]);
    try { migrate($pdo); } catch (Throwable $e) { /* FS อ่านอย่างเดียว — ข้าม migrate */ }
    return $pdo;
}
function q(string $sql, array $args = []): PDOStatement { $s = db()->prepare($sql); $s->execute($args); return $s; }
function row(string $sql, array $args = []): ?array { $r = q($sql, $args)->fetch(); return $r ?: null; }
function rows(string $sql, array $args = []): array { return q($sql, $args)->fetchAll(); }
function val(string $sql, array $args = []) { $r = q($sql, $args)->fetch(PDO::FETCH_NUM); return $r ? $r[0] : null; }

function migrate(PDO $p): void {
    $p->exec("
    CREATE TABLE IF NOT EXISTS users (
      id INT AUTO_INCREMENT PRIMARY KEY,
      discord_id VARCHAR(255) NOT NULL UNIQUE,
      username VARCHAR(255) NOT NULL,
      display_name VARCHAR(255),
      avatar VARCHAR(255),
      email VARCHAR(255),
      role VARCHAR(50) NOT NULL DEFAULT 'user',
      banned TINYINT NOT NULL DEFAULT 0,
      created_at DATETIME NOT NULL,
      last_login DATETIME NOT NULL,
      game_name VARCHAR(255) NOT NULL DEFAULT '',
      tiktok_username VARCHAR(255) NOT NULL DEFAULT '',
      tiktok_access_token TEXT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Add tiktok_username column if it doesn't exist (for existing users)
    try {
        $p->exec("ALTER TABLE users ADD COLUMN tiktok_username VARCHAR(255) NOT NULL DEFAULT ''");
    } catch (Throwable $e) {
        // Column already exists, ignore error
    }

    // Add tiktok_access_token column if it doesn't exist (for existing users)
    try {
        $p->exec("ALTER TABLE users ADD COLUMN tiktok_access_token TEXT NULL");
    } catch (Throwable $e) {
        // Column already exists, ignore error
    }

    // Add avatar_checked_at column for Discord avatar sync
    try {
        $p->exec("ALTER TABLE users ADD COLUMN avatar_checked_at INT NOT NULL DEFAULT 0");
    } catch (Throwable $e) {
        // Column already exists, ignore error
    }

    // Remove game_name_ip column if it exists (no longer needed)
    try {
        $p->exec("ALTER TABLE users DROP COLUMN game_name_ip");
    } catch (Throwable $e) {
        // Column doesn't exist, ignore error
    }

    // ครั้งล่าสุดที่เช็คว่ายังอยู่ใน Discord server (กัน spam API)
    try {
        $p->exec("ALTER TABLE users ADD COLUMN guild_checked_at INT NOT NULL DEFAULT 0");
    } catch (Throwable $e) {
        // Column already exists, ignore error
    }

    // roster สมาชิกแก็ง (JSON) สำหรับตารางเก่า
    try {
        $p->exec("ALTER TABLE gangs ADD COLUMN roster TEXT NULL");
    } catch (Throwable $e) {
        // Column already exists, ignore error
    }
    try {
        $p->exec("ALTER TABLE gangs MODIFY logo MEDIUMTEXT NULL");
    } catch (Throwable $e) {
        // Column type already updated, ignore error
    }
    try {
        $p->exec("ALTER TABLE gang_members ADD COLUMN prev_display_name VARCHAR(255) NULL");
    } catch (Throwable $e) {
        // Column already exists, ignore error
    }
    try {
        $p->exec("ALTER TABLE gang_members ADD COLUMN renamed_at DATETIME NULL");
    } catch (Throwable $e) {
        // Column already exists, ignore error
    }

    try {
    $p->exec("
    CREATE TABLE IF NOT EXISTS events (
      id INT AUTO_INCREMENT PRIMARY KEY,
      title VARCHAR(255) NOT NULL,
      category VARCHAR(50) NOT NULL DEFAULT 'roleplay',
      summary TEXT NOT NULL,
      description TEXT NOT NULL,
      location VARCHAR(255) NOT NULL DEFAULT '',
      reward VARCHAR(255) NOT NULL DEFAULT '',
      starts_at DATETIME NOT NULL,
      ends_at DATETIME NOT NULL,
      capacity INT NOT NULL DEFAULT 0,
      status VARCHAR(50) NOT NULL DEFAULT 'published',
      created_by INT,
      created_at DATETIME NOT NULL,
      FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE INDEX IF NOT EXISTS ix_events_start ON events(starts_at);

    CREATE TABLE IF NOT EXISTS registrations (
      event_id INT NOT NULL,
      user_id INT NOT NULL,
      created_at DATETIME NOT NULL,
      PRIMARY KEY (event_id, user_id),
      FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
      FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS settings (
      k VARCHAR(255) PRIMARY KEY,
      v TEXT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    ");
    } catch (Throwable $e) { /* ตารางหลักน่าจะสร้างไว้แล้ว */ }

    // แยก exec — ถ้าคำสั่งก่อนหน้า error จะไม่ลาก gangs ไปด้วย
    try {
        $p->exec("
        CREATE TABLE IF NOT EXISTS gangs (
          id INT AUTO_INCREMENT PRIMARY KEY,
          name VARCHAR(64) NOT NULL,
          tag VARCHAR(16) NOT NULL DEFAULT '',
          description TEXT NULL,
          roster TEXT NULL,
          logo MEDIUMTEXT NULL,
          leader_id INT NOT NULL,
          status VARCHAR(20) NOT NULL DEFAULT 'pending',
          created_at DATETIME NOT NULL,
          reviewed_at DATETIME NULL,
          FOREIGN KEY (leader_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS gang_members (
          gang_id INT NOT NULL,
          user_id INT NOT NULL,
          role VARCHAR(20) NOT NULL DEFAULT 'member',
          joined_at DATETIME NOT NULL,
          prev_display_name VARCHAR(255) NULL,
          renamed_at DATETIME NULL,
          PRIMARY KEY (gang_id, user_id),
          FOREIGN KEY (gang_id) REFERENCES gangs(id) ON DELETE CASCADE,
          FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS gang_join_requests (
          gang_id INT NOT NULL,
          user_id INT NOT NULL,
          created_at DATETIME NOT NULL,
          PRIMARY KEY (gang_id, user_id),
          FOREIGN KEY (gang_id) REFERENCES gangs(id) ON DELETE CASCADE,
          FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    } catch (Throwable $e) { /* ตารางอาจสร้างแล้ว */ }

    try {
        if (!$p->query("SELECT 1 FROM settings WHERE k='seeded'")->fetch()) seed($p);
    } catch (Throwable $e) { /* settings อาจยังไม่มี */ }
}

function seed(PDO $p): void {
    $now = time();
    $ev = [
      ['แข่งรถถนน: สนาม รวมหัว Community','corrida','เริ่มที่จัตุรัส Principale ผ่าน 12 ด่านทั่วเมือง','เส้นทางถนนพร้อม 12 ด่านผ่านทั่วเมือง ยานพาหนะอิสระ (อนุญาตให้ใช้รถสปอร์ต) ห้ามใช้อาวุธและห้ามชนโดยตั้งใจ เข้าเส้นชัยที่สนามบิน Los Santos สามอันดับแรกได้รับเงินรางวัลและผู้ชนะได้แท็กนักแข่งคนพิเศษ','จัตุรัส Principale','฿ 50,000 + แท็กนักแข่ง',-0.5,2,30],
      ['ปล้นธนาคารกลาง','acao','แก๊งปะทะตำรวจ จำนวนที่นั่งจำกัดต่อฝ่าย','กิจกรรมแอคชันที่ทีมงานจัดขึ้น แก๊งที่ลงทะเบียนพยายามเปิดตู้นิรภัยของธนาคารกลาง ขณะที่ตำรวจป้องกัน ต้องเปิดไมค์และเปิดเสียง 3D กฎการเล่นตามบทบาทและ death rules มีผลตลอดเวลา','ธนาคารกลาง','฿ 120,000 ให้ทีมที่ชนะ',22,3,40],
      ['สุ่มรางวัล VIP ทอง','sorteio','เข้าร่วมแล้วลุ้นรับ VIP ทอง 30 วัน 3 รางวัล','เพียงลงทะเบียนและอยู่ในห้องเสียง Discord ระหว่างการจับรางวัลสด จะสุ่ม VIP ทอง 30 วัน 3 รางวัล','Discord — ห้องเสียง','VIP ทอง 3 รางวัล (30 วัน)',70,1,0],
      ['ทัวร์นาเมนต์ดริฟต์','corrida','รอบคัดเลือกแบบคู่ รอบชิงชนะเลิศเปิดให้ทุกคน','รอบคัดเลือกแบบคู่ที่ลานจอดเรือ คะแนนจากมุม โค้ง และความลื่นไหลที่ทีมงานประเมิน รถดริฟต์ในร้านค้าไม่มีแต้มต่อ — ทุกคนแข่งด้วยรถคันเดียวกัน','ท่าเรือ Los Santos','แพ็กเกจยานพาหนะ + ฿ 30,000',118,3,24],
      ['ค่ำคืนปาร์ตี้: งานเต้นรำของเมือง','roleplay','พบปะชุมชนพร้อมกิจกรรมสร้างสรรค์และการประกวดแฟนตาซี','กิจกรรมสังคมเปิดให้ทุกคน ประกวดตัวละครยอดเยี่ยม ยานพาหนะยอดเยี่ยม และเรื่องราวยอดเยี่ยม ห้ามใช้อาวุธระหว่างกิจกรรม','ศาลากลาง','รางวัลเงินแยกตามหมวด',-52,4,0],
    ];
    $st = $p->prepare('INSERT INTO events(title,category,summary,description,location,reward,starts_at,ends_at,capacity,status,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)');
    foreach ($ev as $e) {
        $s = $now + (int)($e[6] * 3600);
        $st->execute([$e[0],$e[1],$e[2],$e[3],$e[4],$e[5],gmdate('Y-m-d H:i:s',$s),gmdate('Y-m-d H:i:s',$s+$e[7]*3600),$e[8],'published',gmdate('Y-m-d H:i:s')]);
    }
    $p->exec("REPLACE INTO settings(k,v) VALUES('seeded','1')");
}

/* อัปโหลดรูป logo แก็ง — คืน data URI (เก็บใน DB ไม่พึ่งไฟล์) หรือ null */
function gang_logo_upload(array $f): ?string {
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
    if (($f['size'] ?? 0) > 2 * 1024 * 1024) return null;
    $info = @getimagesize($f['tmp_name'] ?? '');
    if (!$info) return null;
    $mime = ['image/jpeg' => 'image/jpeg', 'image/png' => 'image/png', 'image/webp' => 'image/webp', 'image/gif' => 'image/gif'][$info['mime']] ?? null;
    if (!$mime) return null;
    $data = @file_get_contents($f['tmp_name']);
    return $data === false ? null : 'data:' . $mime . ';base64,' . base64_encode($data);
}

/* src สำหรับแสดง logo — data URI ใช้ตรง ๆ, path เก่าใช้ url() */
function gang_logo_src(?string $logo): string {
    if (!$logo) return '';
    return str_starts_with($logo, 'data:') ? $logo : url($logo);
}

/* ───────── Configurações editáveis no painel (sobrescrevem config.php) ───────── */
function setting(string $k, $def = '') { $v = val('SELECT v FROM settings WHERE k=?', [$k]); return $v === null ? $def : $v; }
function set_setting(string $k, string $v): void { q('REPLACE INTO settings(k,v) VALUES(?,?)', [$k, $v]); }

foreach (['apk','discord','whatsapp','instagram','tiktok'] as $_k) {
    $_v = setting('link_' . $_k, null);
    if ($_v !== null) $c['links'][$_k] = $_v;
}
foreach (['version','apk_file','brand','brand_full','initial','site_url','currency','timezone','logo','hero_image','tiktok_live'] as $_k) {
    $_v = setting($_k, null); if ($_v !== null && $_v !== '') $c[$_k] = $_v;
}
/* เนื้อหาที่แก้ไขจากหน้าแอดมิน (จัดเก็บเป็น JSON ใน settings) */
foreach (['platforms','steps','screenshots'] as $_k) {
    $_v = setting('cfg_' . $_k, null);
    if ($_v) { $_j = json_decode($_v, true); if (is_array($_j)) $c[$_k] = $_j; }
}
$L = $c['links'];
$tz = new DateTimeZone($c['timezone'] ?? 'America/Sao_Paulo');

/* ───────── Cookie auth (serverless: session อาจไม่ persist) ───────── */
function auth_secret(): string {
    global $c;
    return (string)($c['discord_oauth']['client_secret'] ?? 'nc-insecure-dev-secret');
}
function cookie_uid(): ?int {
    $v = (string)($_COOKIE['nc_uid'] ?? '');
    if (!preg_match('/^(\d+)\.([a-f0-9]{32})$/', $v, $m)) return null;
    $id = (int)$m[1];
    $expect = substr(hash_hmac('sha256', 'uid|' . $id, auth_secret()), 0, 32);
    return hash_equals($expect, $m[2]) ? $id : null;
}
function set_uid_cookie(int $uid): void {
    $sig = substr(hash_hmac('sha256', 'uid|' . $uid, auth_secret()), 0, 32);
    setcookie('nc_uid', $uid . '.' . $sig, [
        'expires' => time() + 365 * 86400, 'path' => '/', 'httponly' => true,
        'samesite' => 'Lax', 'secure' => str_starts_with(origin(), 'https'),
    ]);
}
function clear_uid_cookie(): void {
    setcookie('nc_uid', '', [
        'expires' => time() - 3600, 'path' => '/', 'httponly' => true,
        'samesite' => 'Lax', 'secure' => str_starts_with(origin(), 'https'),
    ]);
}
function set_temp_cookie(string $name, string $value, int $ttl = 600): void {
    setcookie($name, $value, [
        'expires' => time() + $ttl, 'path' => '/', 'httponly' => true,
        'samesite' => 'Lax', 'secure' => str_starts_with(origin(), 'https'),
    ]);
}
function clear_temp_cookie(string $name): void {
    setcookie($name, '', [
        'expires' => time() - 3600, 'path' => '/', 'httponly' => true,
        'samesite' => 'Lax', 'secure' => str_starts_with(origin(), 'https'),
    ]);
}

/* ───────── Segurança: CSRF, flash ───────── */
function csrf_token(): string {
    if (!empty($_SESSION['csrf'])) return $_SESSION['csrf'];
    // session อาจไม่ persist บน serverless → derive จาก session_id (cookie ฝั่ง browser ยังอยู่)
    $t = substr(hash_hmac('sha256', 'csrf|' . session_id() . '|' . (cookie_uid() ?: 0), auth_secret()), 0, 48);
    $_SESSION['csrf'] = $t;
    return $t;
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['_csrf'] ?? ''))) {
        http_response_code(419); exit('เซสชันหมดอายุ กรุณากลับไปแล้วลองใหม่อีกครั้ง');
    }
}
function flash(string $msg, string $type = 'ok'): void { $_SESSION['flash'][] = [$type, $msg]; }
function flashes(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }
function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'; }
function safe_next(string $n): string {
    // só aceita caminhos internos do próprio site
    if ($n === '' || $n[0] !== '/' || str_starts_with($n, '//') || str_contains($n, '\\')) return url('');
    return $n;
}

/* ───────── Autenticação ───────── */
/** เช็คว่ายังเป็นสมาชิก Discord server ไหม (bot token) — null = เช็คไม่ได้, true/false = อยู่/ไม่อยู่ */
function discord_guild_member(array $u): ?bool {
    global $c;
    $gid = (string)($c['discord_oauth']['guild_id'] ?? '');
    $bt = (string)($c['discord_oauth']['bot_token'] ?? '');
    $did = (string)($u['discord_id'] ?? '');
    if ($gid === '' || $bt === '' || !ctype_digit($gid) || !ctype_digit($did)) return null;
    [$code] = http('GET', 'https://discord.com/api/guilds/' . $gid . '/members/' . $did,
        ['Authorization: Bot ' . $bt]);
    if ($code === 404) return false;
    if ($code === 200) return true;
    return null; // API ล่ม/429 → ไม่เตะออก
}
function me(): ?array {
    static $u = false;
    if ($u !== false) return $u;
    $u = null;
    $uid = $_SESSION['uid'] ?? cookie_uid();
    if ($uid) {
        $u = row('SELECT * FROM users WHERE id=?', [$uid]);
        if (!$u || $u['banned']) { $u = null; unset($_SESSION['uid']); clear_uid_cookie(); }
        elseif (discord_ready() && time() - (int)($u['guild_checked_at'] ?? 0) > 600) {
            // เช็คสมาชิก Discord ทุก 10 นาที — ออกเซิร์ฟเวอร์แล้ว = เตะออกจากเว็บ
            $in = discord_guild_member($u);
            q('UPDATE users SET guild_checked_at=? WHERE id=?', [time(), $u['id']]);
            if ($in === false) {
                $u = null;
                unset($_SESSION['uid']);
                clear_uid_cookie();
                flash('คุณออกจากเซิร์ฟเวอร์ Discord แล้ว กรุณาเข้าสู่ระบบใหม่', 'warn');
            }
        }
        // อัปเดต avatar จาก Discord ทุก 1 ชั่วโมง
        if ($u && discord_ready() && time() - (int)($u['avatar_checked_at'] ?? 0) > 3600) {
            global $c;
            $bt = $c['discord_oauth']['bot_token'] ?? '';
            if ($bt !== '') {
                [$code, $body] = http('GET', 'https://discord.com/api/users/' . $u['discord_id'], ['Authorization: Bot ' . $bt]);
                if ($code === 200) {
                    $data = json_decode($body, true);
                    if (!empty($data['avatar']) && $data['avatar'] !== $u['avatar']) {
                        q('UPDATE users SET avatar=?, avatar_checked_at=? WHERE id=?', [$data['avatar'], time(), $u['id']]);
                        $u['avatar'] = $data['avatar'];
                    } else {
                        q('UPDATE users SET avatar_checked_at=? WHERE id=?', [time(), $u['id']]);
                    }
                }
            }
        }
    }
    return $u;
}
function is_admin(): bool { $u = me(); return $u && $u['role'] === 'admin'; }
function require_login(): array {
    $u = me();
    if (!$u) { flash('กรุณาเข้าสู่ระบบด้วย Discord เพื่อดำเนินการต่อ', 'warn'); redirect(url('auth/login') . '?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/')); }
    return $u;
}
function require_admin(): array {
    $u = require_login();
    if ($u['role'] !== 'admin') { http_response_code(403); exit('ปฏิเสธการเข้าถึง'); }
    return $u;
}
function avatar_url(array $u, int $size = 64): string {
    if (!empty($u['avatar'])) return 'https://cdn.discordapp.com/avatars/' . $u['discord_id'] . '/' . $u['avatar'] . '.png?size=' . $size;
    $i = ctype_digit($u['discord_id']) ? (int)(((int)$u['discord_id'] >> 22) % 6) : 0;
    return 'https://cdn.discordapp.com/embed/avatars/' . $i . '.png';
}
function user_name(array $u): string { return $u['display_name'] ?: $u['username']; }

function login_user(array $d): void {
    global $c;
    $id = (string)$d['id'];
    $now = gmdate('Y-m-d H:i:s');
    $name = $d['global_name'] ?? null;
    $existing = row('SELECT * FROM users WHERE discord_id=?', [$id]);
    if ($existing) {
        if ($existing['banned']) { flash('บัญชีนี้ถูกระงับการใช้งาน', 'err'); redirect(url('')); }
        q('UPDATE users SET username=?,display_name=?,avatar=?,email=?,last_login=? WHERE id=?',
          [$d['username'], $name, $d['avatar'] ?? null, $d['email'] ?? null, $now, $existing['id']]);
        $uid = (int)$existing['id'];
    } else {
        $first = (int)val("SELECT COUNT(*) FROM users WHERE role='admin'") === 0;
        q('INSERT INTO users(discord_id,username,display_name,avatar,email,role,created_at,last_login,tiktok_username,tiktok_access_token) VALUES(?,?,?,?,?,?,?,?,?,?)',
          [$id, $d['username'], $name, $d['avatar'] ?? null, $d['email'] ?? null, $first ? 'admin' : 'user', $now, $now, '', '']);
        $uid = (int)db()->lastInsertId();
    }
    if (in_array($id, array_map('strval', $c['admin_ids'] ?? []), true)) q("UPDATE users SET role='admin' WHERE id=?", [$uid]);
    session_regenerate_id(true);
    $_SESSION['uid'] = $uid;
    set_uid_cookie($uid);
}

/* ───────── HTTP (curl, com fallback) ───────── */
function http(string $method, string $url, array $headers = [], ?string $body = null): array {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers, CURLOPT_USERAGENT => 'NovaCapitalSite/1.0']);
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        $out = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        return [$code, (string)$out];
    }
    $ctx = stream_context_create(['http' => ['method' => $method, 'header' => implode("\r\n", $headers), 'content' => $body ?? '', 'timeout' => 10, 'ignore_errors' => true]]);
    $out = @file_get_contents($url, false, $ctx);
    $code = 0; if (!empty($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) $code = (int)$m[1];
    return [$code, (string)$out];
}

/* ───────── Discord ───────── */
function discord_ready(): bool { global $c; return ($c['discord_oauth']['client_id'] ?? '') !== '' && ($c['discord_oauth']['client_secret'] ?? '') !== ''; }
function discord_redirect_uri(): string { global $c; return ($c['discord_oauth']['redirect_uri'] ?? '') ?: origin() . url('auth/callback.php'); }

/** Membros online no servidor (widget do Discord, cache de 5 min). */
function discord_widget(): ?array {
    global $c;
    $gid = $c['discord_oauth']['guild_id'] ?? '';
    if ($gid === '' || !ctype_digit($gid)) return null;
    global $DATA_DIR;
    $f = $DATA_DIR . '/widget.json';
    if (is_file($f) && time() - filemtime($f) < 300) return json_decode((string)file_get_contents($f), true) ?: null;
    if (!is_writable($DATA_DIR)) return null; // FS อ่านอย่างเดียว — ไม่เรียก API ทุก request
    [$code, $body] = http('GET', 'https://discord.com/api/guilds/' . $gid . '/widget.json');
    $j = $code === 200 ? json_decode($body, true) : null;
    $out = $j ? ['online' => (int)($j['presence_count'] ?? 0), 'invite' => $j['instant_invite'] ?? null] : null;
    @file_put_contents($f, json_encode($out));
    return $out;
}

/* ───────── Formatação ───────── */
function local_dt(string $utc): DateTime { global $tz; return (new DateTime($utc, new DateTimeZone('UTC')))->setTimezone($tz); }
function pt_month(int $n): string { return ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'][$n]; }
function pt_wday(int $n): string { return ['อา.','จ.','อ.','พ.','พฤ.','ศ.','ส.'][$n]; }
function fmt_when(string $utc): string { $d = local_dt($utc); return pt_wday((int)$d->format('w')) . ', ' . $d->format('d') . ' ' . pt_month((int)$d->format('n')) . ' · ' . $d->format('H:i'); }
function fmt_full(string $utc): string { $d = local_dt($utc); return $d->format('d/m/Y H:i'); }
/** datetime-local (fuso do servidor de jogo) → UTC */
function input_to_utc(string $v): ?string {
    global $tz; if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $v)) return null;
    return (new DateTime(str_replace('T', ' ', $v) . ':00', $tz))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}
function utc_to_input(string $utc): string { return local_dt($utc)->format('Y-m-d\TH:i'); }

/* ───────── Eventos ───────── */
const EVENT_CATS = [
    'corrida'  => ['แข่งรถ',  '#f59e0b'],
    'acao'     => ['แอคชัน',  '#ef4444'],
    'roleplay' => ['ปาร์ตี้', '#10b981'],
    'sorteio'  => ['สุ่มรางวัล','#a855f7'],
    'torneio'  => ['ทัวร์นาเมนต์','#34d399'],
    'staff'    => ['ชุมชน',   '#65a30d'],
];
function ev_cat(string $k): array { return EVENT_CATS[$k] ?? ['กิจกรรม', '#10b981']; }
/** live | upcoming | ended | cancelled | draft */
function ev_state(array $e): string {
    if ($e['status'] === 'cancelled') return 'cancelled';
    if ($e['status'] === 'draft') return 'draft';
    $n = gmdate('Y-m-d H:i:s');
    if ($e['starts_at'] <= $n && $e['ends_at'] > $n) return 'live';
    return $e['starts_at'] > $n ? 'upcoming' : 'ended';
}
function ev_count(int $id): int { return (int)val('SELECT COUNT(*) FROM registrations WHERE event_id=?', [$id]); }
function ev_registered(int $eid, ?int $uid): bool { return $uid ? (bool)val('SELECT 1 FROM registrations WHERE event_id=? AND user_id=?', [$eid, $uid]) : false; }

/* ───────── TikTok ───────── */
function get_tiktok_videos(string $access_token): array {
    if (empty($access_token)) return [];

    $url = 'https://open.tiktokapis.com/v2/video/list/?fields=id,video_description,cover_image_url,embed_html,share_url';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) return [];

    $data = json_decode($response, true);
    return $data['data']['videos'] ?? [];
}
