<?php
ini_set('zlib.output_compression', 'Off');
@set_time_limit(0);
require __DIR__ . '/includes/bootstrap.php';

$u = require_login();
if (empty($u['game_name'])) {
    flash('กรุณาลงทะเบียนเซิร์ฟเวอร์ก่อน', 'err');
    redirect(url('account/'));
}

$launcherJson = <<<'LAUNCHER_JSON'
{
  "language": "th",
  "name": "ไทย",
  "about": "The GTA V port's launcher, trainer and character creator in English, a text each. Write each one's translation after it; \"\" keeps the English. Keep every %@, %d, %lu, %llu, %.1f and the like: the game puts a value there (a name, a number) in that order; to change their order write %1$@, %2$@... (the n-th in the English). \\n is a line break. Text in capitals (IMPORT MOD) is shown in capitals. A few texts are put together from pieces in the code: those show in English until the code builds them whole. Write your language's code in \"language\" (es, pt-BR, ru...) and its name in \"name\" (Español), save the file under a name of its own (launcher_es.json) in the languages folder: Files > On My iPhone (iPad) > GTA V > launcherlang, or ~/Documents/Rockstar Games/GTA V/launcherlang on a Mac. Then pick it in the launcher's Settings > Language: it applies at once.",
  "launcher": {
   "%.1f GB": "",
   "About": "เกี่ยวกับ",
   "Add to Home Screen": "เพิ่มไปยังหน้าจอหลัก",
   "Advanced Settings": "การตั้งค่าขั้นสูง",
   "Allow": "อนุญาต",
   "Allowed": "อนุญาตแล้ว",
   "Always On": "เปิดตลอดเวลา",
   "Apply": "นำไปใช้",
   "Back": "ย้อนกลับ",
   "Cancel": "ยกเลิก",
   "Change": "เปลี่ยน",
   "Character Creator": "สร้างตัวละคร",
   "Check for Updates": "ตรวจสอบอัปเดต",
   "Clear": "ล้าง",
   "Close": "ปิด",
   "Confirm": "ยืนยัน",
   "Connect": "เชื่อมต่อ",
   "Continue": "ดำเนินการต่อ",
   "Copy": "คัดลอก",
   "Create": "สร้าง",
   "Custom": "กำหนดเอง",
   "Default": "ค่าเริ่มต้น",
   "Delete": "ลบ",
   "Disable": "ปิดใช้งาน",
   "Dismiss": "ปิดทิ้ง",
   "Done": "เสร็จสิ้น",
   "Download": "ดาวน์โหลด",
   "Edit": "แก้ไข",
   "Enable": "เปิดใช้งาน",
   "Error": "ข้อผิดพลาด",
   "Exit": "ออก",
   "Export": "ส่งออก",
   "Failed": "ล้มเหลว",
   "File": "ไฟล์",
   "Files": "ไฟล์",
   "Finish": "เสร็จสิ้น",
   "Game": "เกม",
   "General": "ทั่วไป",
   "Help": "ช่วยเหลือ",
   "Import": "นำเข้า",
   "Install": "ติดตั้ง",
   "Language": "ภาษา",
   "Loading": "กำลังโหลด",
   "Login": "เข้าสู่ระบบ",
   "Logout": "ออกจากระบบ",
   "Manage": "จัดการ",
   "Manual": "คู่มือ",
   "More": "เพิ่มเติม",
   "Network": "เครือข่าย",
   "Next": "ถัดไป",
   "No": "ไม่",
   "None": "ไม่มี",
   "OK": "ตกลง",
   "Offline": "ออฟไลน์",
   "Online": "ออนไลน์",
   "Open": "เปิด",
   "Options": "ตัวเลือก",
   "Password": "รหัสผ่าน",
   "Pause": "หยุดชั่วคราว",
   "Play": "เล่น",
   "Please Wait": "กรุณารอสักครู่",
   "Previous": "ก่อนหน้า",
   "Quit": "ออก",
   "Refresh": "รีเฟรช",
   "Remove": "ลบออก",
   "Rename": "เปลี่ยนชื่อ",
   "Reset": "รีเซ็ต",
   "Restart": "เริ่มใหม่",
   "Resume": "ดำเนินการต่อ",
   "Save": "บันทึก",
   "Search": "ค้นหา",
   "Select": "เลือก",
   "Settings": "การตั้งค่า",
   "Share": "แชร์",
   "Skip": "ข้าม",
   "Start": "เริ่ม",
   "Stop": "หยุด",
   "Success": "สำเร็จ",
   "Sync": "ซิงค์",
   "System": "ระบบ",
   "Terms": "เงื่อนไข",
   "Try Again": "ลองอีกครั้ง",
   "Update": "อัปเดต",
   "User": "ผู้ใช้",
   "Username": "ชื่อผู้ใช้",
   "Version": "เวอร์ชัน",
   "Video": "วิดีโอ",
   "Warning": "คำเตือน",
   "Yes": "ใช่"
  },
  "trainer": {
   "Places": "จุดบันทึก",
   "Save": "บันทึก",
   "Load": "โหลด",
   "Delete": "ลบ",
   "Name": "ชื่อ",
   "Cancel": "ยกเลิก",
   "Confirm": "ยืนยัน",
   "Saved": "บันทึกแล้ว",
   "Loaded": "โหลดแล้ว",
   "Deleted": "ลบแล้ว"
  },
  "creator": {
   "Appearance": "รูปลักษณ์",
   "Face": "ใบหน้า",
   "Body": "ร่างกาย",
   "Hair": "ทรงผม",
   "Clothing": "เสื้อผ้า",
   "Accessories": "เครื่องประดับ",
   "Tattoos": "สัก",
   "Save": "บันทึก",
   "Load": "โหลด",
   "Randomize": "สุ่ม",
   "Reset": "รีเซ็ต",
   "Cancel": "ยกเลิก",
   "Confirm": "ยืนยัน"
  }
}
LAUNCHER_JSON;

// ตรวจสอบ JSON ก่อนนำไปใส่ใน ZIP
$decoded = json_decode($launcherJson, true);
if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    exit('ไฟล์ launcher_th.json ไม่ถูกต้อง');
}

// สร้าง profile.json จากชื่อในเว็บของผู้ใช้
$profile = [
    'name' => $u['game_name'],
    'server' => $c['server_address'] ?? 'th-bkk05.altare.global:25074',
];
$profileJson = json_encode(
    $profile,
    JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
);

if ($profileJson === false) {
    http_response_code(500);
    exit('สร้าง profile.json ไม่สำเร็จ');
}

// สร้างไฟล์ ZIP ชั่วคราว
$tmp = tempnam(sys_get_temp_dir(), 'rhcm-');
if ($tmp === false) {
    http_response_code(500);
    exit('สร้างไฟล์ชั่วคราวไม่สำเร็จ');
}
@unlink($tmp);
$tmp .= '.zip';

$zip = new ZipArchive();
if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    @unlink($tmp);
    http_response_code(500);
    exit('สร้างไฟล์ ZIP ไม่สำเร็จ');
}

// เพิ่มไฟล์ใหม่ลง ZIP โดยตรง ไม่ต้องมีโฟลเดอร์ต้นทาง
$ok1 = $zip->addFromString(
    'GTA V/launcherlang/launcher_th.json',
    $launcherJson
);
$ok2 = $zip->addFromString(
    'GTA V/Rockstar Games/GTA V/Online/profile.json',
    $profileJson
);

// สร้าง trainer_places.json
$trainerPlaces = [
    "places" => [
        [
            "x" => -4268.22,
            "id" => "6CB4E99F-FDD2-403F-B97D-6D0853C5B5FB",
            "y" => -4310.33,
            "z" => 149.68,
            "saved" => 1794717268,
            "heading" => 0.0,
            "name" => "รวมคอมมูนิตี้"
        ],
        [
            "x" => -4996.48,
            "id" => "07197408-C29E-4DB7-8E64-BFD6D2835E41",
            "y" => 6702.42,
            "z" => 91.45,
            "saved" => 1794717265,
            "heading" => 0.0,
            "name" => "แมพแข่งรถญีปุ่น"
        ],
        [
            "x" => -2862.419,
            "id" => "17207CA4-7A56-4144-81EC-86B2551D5D7E",
            "y" => -3774.104,
            "z" => 265.8,
            "saved" => 1794717218,
            "heading" => -116.20032501220703,
            "name" => "โซนนัว"
        ],
        [
            "x" => -3079.119,
            "id" => "25C7501E-7329-4FF9-A824-B3F97D2364C7",
            "y" => -3444.142,
            "z" => 265.8,
            "saved" => 1794717232,
            "heading" => -111.4768295288086,
            "name" => "วอโซนต่อย"
        ],
        [
            "x" => -2833.788,
            "id" => "C2577F28-963B-4196-9401-D2F4CD97B21D",
            "y" => -3123.612,
            "z" => 265.8,
            "saved" => 1794717240,
            "heading" => -83.71918487548828,
            "name" => "วอโซน1"
        ],
        [
            "x" => -3113.821,
            "id" => "363F4299-A4CA-484E-9411-C5A7A3404E62",
            "y" => -2813.359,
            "z" => 265.8,
            "saved" => 1794717250,
            "heading" => -86.59447479248047,
            "name" => "วอโซน2"
        ],
        [
            "x" => -2929.767,
            "id" => "6B634D47-2FD1-49EF-82B6-27F567F3542D",
            "y" => -2488.892,
            "z" => 265.8,
            "saved" => 1794717260,
            "heading" => -55.18479537963867,
            "name" => "วอโซน3"
        ],
        [
            "x" => -3135.647,
            "id" => "7C745E58-3GE2-50FG-93C7-38G678G4653E",
            "y" => -2219.117,
            "z" => 265.8,
            "saved" => 1794717260,
            "heading" => -55.184795379632675,
            "name" => "วอโซน4"
        ],
        [
            "x" => -2175.628173828125,
            "id" => "79860631-5DDB-4711-909E-05085B00859A",
            "y" => -2252.146484375,
            "z" => 576.9158325195312,
            "saved" => 1794638355,
            "heading" => -105.78414916992188,
            "name" => "Dome 1"
        ],
        [
            "x" => -3378.4185,
            "id" => "89860631-5DDB-4711-909E-05085B00859B",
            "y" => 2221.3096,
            "z" => 135.8394,
            "saved" => 1794638355,
            "heading" => 115.0805,
            "name" => "Dome 2"
        ],
        [
            "x" => -2174.415,
            "id" => "99860631-5DDB-4711-909E-05085B00859C",
            "y" => -2251.4863,
            "z" => 576.916,
            "saved" => 1794638355,
            "heading" => 158.9103,
            "name" => "Dome 3"
        ],
        [
            "x" => -2711.45,
            "id" => "A9860631-5DDB-4711-909E-05085B00859D",
            "y" => -1310.8521,
            "z" => 505.4073,
            "saved" => 1794638355,
            "heading" => 158.9103,
            "name" => "Dome 4"
        ],
        [
            "x" => -3524.688,
            "id" => "B9860631-5DDB-4711-909E-05085B00859E",
            "y" => 759.168,
            "z" => 262.3916,
            "saved" => 1794638355,
            "heading" => 158.9103,
            "name" => "Dome 5"
        ],
        [
            "x" => -5053.24267578125,
            "id" => "24519483-92CE-4DF7-94B4-A53F666165DA",
            "y" => 6634.39306640625,
            "z" => 92.62947082519531,
            "saved" => 1794643153,
            "heading" => -40.234764099121094,
            "name" => "กิจกรรม 01"
        ],
        [
            "x" => -1019.6155395507812,
            "id" => "9058F695-7A9D-4F37-8E54-CE7F0CD35B00",
            "y" => -2972.48828125,
            "z" => 13.947321891784668,
            "saved" => 1794674202,
            "heading" => 64.39799499511719,
            "name" => "สนามบินล่าง"
        ],
        [
            "x" => -1328.622314453125,
            "id" => "F6C792C8-7867-4E6A-8ECA-8D4AF79940E2",
            "y" => -1635.685546875,
            "z" => 3.03155779838562,
            "saved" => 1794674286,
            "heading" => 121.7071762084961,
            "name" => "ริมเล"
        ],
        [
            "x" => -1743.712890625,
            "id" => "2052E43C-FDEF-434D-9095-FBB052E48B44",
            "y" => -1119.5848388671875,
            "z" => 13.106417655944824,
            "saved" => 1794674376,
            "heading" => 136.91751098632812,
            "name" => "สวนสนุก"
        ],
        [
            "x" => -2646.2119140625,
            "id" => "8BF5BE8D-0C30-4C9D-96D7-E614A5BFE22A",
            "y" => 2717.46875,
            "z" => 16.677873611450195,
            "saved" => 1794674495,
            "heading" => -0.6145644187927246,
            "name" => "หลักสี่"
        ],
        [
            "x" => -1856.6588134765625,
            "id" => "741A9DC7-FD81-40F6-A175-3BF2849B9409",
            "y" => 3021.9296875,
            "z" => 32.810508728027344,
            "saved" => 1794713111,
            "heading" => 121.20736694335938,
            "name" => "ค่ายทหาร"
        ],
        [
            "x" => -2225.915283203125,
            "id" => "6C366248-5EE8-4B8A-B8F1-19911E67DBF8",
            "y" => 3200.090087890625,
            "z" => 32.80998229980469,
            "saved" => 1794713151,
            "heading" => -11.77383804321289,
            "name" => "โรงเก็บเครื่องบิน"
        ]
    ]
];
$trainerPlacesJson = json_encode($trainerPlaces, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$ok3 = $zip->addFromString(
    'GTA V/Rockstar Games/GTA V/trainer_places.json',
    $trainerPlacesJson
);

// เพิ่มไฟล์ rhcm-mods.zip
$modsZipPath = ROOT . '/datarhcm/rhcm-mods.zip';
$ok4 = true;
if (is_file($modsZipPath)) {
    $ok4 = $zip->addFile($modsZipPath, 'GTA V/rhcm-mods.zip');
}

$closed = $zip->close();

if (!$ok1 || !$ok2 || !$ok3 || !$ok4 || !$closed || !is_file($tmp) || filesize($tmp) <= 0) {
    @unlink($tmp);
    http_response_code(500);
    exit('สร้างไฟล์ ZIP ไม่สำเร็จ');
}

$size = filesize($tmp);
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="rhcm-data.zip"');
header('Content-Length: ' . $size);
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

readfile($tmp);
@unlink($tmp);
exit;
