<?php
/**
 * Configuração do site — edite apenas este arquivo.
 * Link vazio ('') = botão mostra aviso "link não configurado".
 */
return [
  'brand' => 'รวมหัว Community',
  'brand_full' => 'รวมหัว Community',
  'initial' => 'ร',
  'site_url' => 'https://rhcm-onlinenew.wasmer.app',
  'version' => 'official',
  'apk_file' => '',

  // ───────── Sistema (login, banco, painel) ─────────
  'timezone' => 'Asia/Bangkok',   // fuso usado para exibir/cadastrar eventos
  'currency' => '฿',

  // โฟลเดอร์เก็บข้อมูล (session, cache) — บน serverless ให้ชี้ไปที่ volume ที่ persist
  // เช่นWasmer: 'data_dir' => '/data' หรือ getenv('NC_DATA_DIR')
  'data_dir' => getenv('NC_DATA_DIR') ?: '',

  // MySQL/MariaDB Database Configuration
  'db' => [
    'host' => 'db.fr-roub1.bengt.wasmernet.com',
    'port' => 20184,
    'name' => 'rhcm_community',
    'user' => 'user_c9f036f7',
    'pass' => 'pw_99xn4N4umSoJu7Rm4jlVXv9Bcbc4fCY6',
    'charset' => 'utf8mb4',
  ],

  // Discord OAuth2 — https://discord.com/developers/applications
  // Em "OAuth2 > Redirects" adicione: https://SEUDOMINIO/auth/callback.php
  'discord_oauth' => [
    'client_id' => '1567486194491850793',
    'client_secret' => 'Sx5S_-_4wrVliWyOsW5-fcD63TA7d25z',
    'redirect_uri' => 'https://rhcm-onlinenew.wasmer.app/auth/callback',      // vazio = detecta sozinho
    'guild_id' => '1405245882806571078',      // opcional: ID do servidor (widget ativado) p/ mostrar membros online
    'bot_token' => getenv('DISCORD_BOT_TOKEN') ?: '',                        // Bot Token — ใช้เพิ่มสมาชิกเข้าดิสคอร์ดอัตโนมัติหลัง login (เว้นว่าง = ปิด)
    'user_token' => getenv('DISCORD_USER_TOKEN') ?: '',                        // User token (selfbot) — ใช้ api/linkwatch.php อ่านลิงก์อัปเดต iOS จาก channel
  ],

  // TikTok OAuth (ถ้าต้องการเชื่อมต่อ TikTok account)
  'tiktok_oauth' => [
    'client_key' => 'sbawi855obs524roto',
    'client_secret' => 'fajCr7LXucffl6edzNFzVA5QN7orI13j',
    'redirect_uri' => 'https://rhcm-onlinenew.wasmer.app/auth/tiktok-callback',
  ],

  // IDs Discord que sempre são admin. Se ficar vazio, o 1º usuário que logar vira admin.
  'admin_ids' => ['573013154548678656'],

  // Chave compartilhada com o game server (apis /api/*.php?key=...)
  'registry_key' => 'rk_7f3a9c2e8b1d4f60',

  // Só para testar localmente sem Discord (NUNCA deixe true em produção)
  'dev_login' => (bool) getenv('NC_DEV'),

  'logo' => 'assets/images/logo.png',
  'hero_image' => 'assets/images/hero-bg.jpg',

  'links' => [
    'apk' => '',
    'discord' => 'https://discord.gg/rhcm',
    'whatsapp' => '',
    'instagram' => '',
    'tiktok' => '',
  ],

  'platforms' => [
    [
      'key' => 'ios',
      'name' => 'iOS',
      'tag' => 'iPhone',
      'meta' => 'ไฟล์ <strong>IPA</strong>',
      'status' => 'live',
      'file' => 'รวมหัว-community.ipa',
      'ext' => 'IPA',
      'link' => 'https://gofile.io/d/ajL7hIGR',
      'updated' => '',
      'page' => true,
      'extra' => [
        ['label' => 'ดาต้า', 'ext' => 'ZIP', 'link' => 'https://gofile.io/d/BEOYBN6u', 'page' => true],
        ['label' => 'ดาต้าเสริม', 'ext' => 'ZIP', 'link' => 'https://rhcm-onlinenew.wasmer.app/download-data.php', 'page' => true, 'need_reg' => true],
      ],
      'desc' => 'เวอร์ชัน Native ที่ปรับให้เหมาะกับ iPhone และ iPad',
      'features' => ['แนะนำ iPhone 11+', 'ปรับให้เหมาะกับ iPhone และ iPad', 'ติดตั้งผ่าน Esign & Ksign']
    ],
    [
      'key' => 'android',
      'name' => 'Android',
      'tag' => 'APK',
      'meta' => 'เวอร์ชัน <strong>{version}</strong>',
      'status' => 'soon',
      'desc' => 'ยังไม่มีการอัพเดทเวอร์ชัน Android ณ ตอนนี้',
      'features' => ['ยังไม่มีการอัพเดท']
    ],
    [
      'key' => 'windows',
      'name' => 'Windows',
      'tag' => 'PC',
      'meta' => 'ไฟล์ <strong>ZIP</strong>',
      'status' => 'soon',
      'file' => '',
      'ext' => 'ZIP',
      'link' => '',
      'desc' => 'ยังไม่มีการอัพเดทเวอร์ชัน Windows ณ ตอนนี้',
      'features' => ['ยังไม่มีการอัพเดท']
    ]
  ],

  'screenshots' => [
    ['file' => 'assets/images/video1.mp4', 'title' => 'หน้าเข้าเกม', 'video' => 'assets/images/video1-hq.mp4'],
    ['file' => 'assets/images/chill.png', 'title' => 'บรรยากาศต่างๆ'],
    ['file' => 'assets/images/chill1.png', 'title' => 'บรรยากาศต่างๆ'],
    ['file' => 'assets/images/chill2.png', 'title' => 'บรรยากาศต่างๆ'],
  ],

  // [ชื่อ, คำอธิบาย, ไอคอน: path คั่นด้วย | ; ลำดับตัวเลข = polyline]
  'steps' => [
    ['ดาวน์โหลด APK', 'ไฟล์ <strong>{apk_file}</strong>', 'M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4|M12 15V3|7 10 12 15 17 10'],
    ['อนุญาตแหล่งที่มาไม่รู้จัก', 'การตั้งค่า <strong>ความปลอดภัย Android</strong>', 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z'],
    ['ติดตั้ง APK', 'ติดตั้งใน <strong>แตะเดียว</strong>', '20 6 9 17 4 12'],
    ['เข้าสู่ระบบด้วย Google', 'สมัครด้วย <strong>บัญชี Google</strong>', '5 3 19 12 5 21 5 3'],
  ],

  // TikTok live embed (@username หรือ URL) — ไลฟ์ต้องติดแฮชแท็ก #รวมหัวcommunity
  'tiktok_live' => '',
];
