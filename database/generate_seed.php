<?php
require_once __DIR__ . '/../config/database.php';
$db = Database::getConnection();

$categories = $db->query("SELECT * FROM categories ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
$levels = $db->query("SELECT * FROM levels ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
$websites = $db->query("SELECT * FROM websites ORDER BY category_id ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($categories) . " categories, " . count($levels) . " levels, " . count($websites) . " websites.\n";

// Group websites by category_id
$grouped = [];
foreach ($categories as $cat) {
    $catId = $cat['id'];
    $catWebs = array_values(array_filter($websites, fn($w) => $w['category_id'] == $catId));
    $grouped[] = [
        'name' => $cat['name'],
        'code' => $cat['code'],
        'sort_order' => (int) $cat['sort_order'],
        'items' => array_map(function($w) {
            return [
                'name' => $w['name'],
                'level' => $w['level'],
                'url' => $w['url'],
                'notes' => $w['notes'] ?? '',
                'last_status' => $w['last_status'] ?? 'Belum Dicek',
                'last_http_code' => $w['last_http_code'] !== null ? (int)$w['last_http_code'] : null,
                'last_response_time' => $w['last_response_time'] !== null ? (int)$w['last_response_time'] : null
            ];
        }, $catWebs)
    ];
}

$cleanLevels = array_map(function($l) {
    return [
        'code' => $l['code'],
        'name' => $l['name'],
        'badge_style' => $l['badge_style'],
        'sort_order' => (int)$l['sort_order']
    ];
}, $levels);

$phpCode = "<?php\n";
$phpCode .= "/**\n";
$phpCode .= " * Seeder Data Terkini dari Database Aktif\n";
$phpCode .= " * Sesuai data pemantauan Universitas Tanjungpura (UNTAN)\n";
$phpCode .= " * Dihasilkan secara otomatis dari isi database monitor.sqlite\n";
$phpCode .= " */\n\n";
$phpCode .= "function seedDatabase(PDO \$db): void {\n";

// Levels seeder
$phpCode .= "    // 1. Inisialisasi Pilihan Jenjang / Level\n";
$phpCode .= "    \$levels = " . var_export($cleanLevels, true) . ";\n\n";
$phpCode .= "    \$insertLevel = \$db->prepare(\"INSERT OR IGNORE INTO levels (code, name, badge_style, sort_order) VALUES (?, ?, ?, ?)\");\n";
$phpCode .= "    foreach (\$levels as \$lvl) {\n";
$phpCode .= "        \$insertLevel->execute([\n";
$phpCode .= "            \$lvl['code'],\n";
$phpCode .= "            \$lvl['name'],\n";
$phpCode .= "            \$lvl['badge_style'],\n";
$phpCode .= "            \$lvl['sort_order']\n";
$phpCode .= "        ]);\n";
$phpCode .= "    }\n\n";

// Faculties & Websites seeder
$phpCode .= "    // 2. Inisialisasi Fakultas / Unit & Daftar Website\n";
$phpCode .= "    \$faculties = " . var_export($grouped, true) . ";\n\n";
$phpCode .= "    \$insertCategory = \$db->prepare(\"INSERT INTO categories (name, code, sort_order) VALUES (?, ?, ?)\");\n";
$phpCode .= "    \$insertWebsite = \$db->prepare(\"INSERT INTO websites (category_id, name, level, url, last_status, last_http_code, last_response_time, last_checked_at, notes) VALUES (?, ?, ?, ?, ?, ?, ?, datetime('now', 'localtime'), ?)\");\n";
$phpCode .= "    \$insertLog = \$db->prepare(\"INSERT INTO check_logs (website_id, http_code, response_time, status) VALUES (?, ?, ?, ?)\");\n\n";
$phpCode .= "    foreach (\$faculties as \$fac) {\n";
$phpCode .= "        \$insertCategory->execute([\$fac['name'], \$fac['code'], \$fac['sort_order']]);\n";
$phpCode .= "        \$categoryId = \$db->lastInsertId();\n\n";
$phpCode .= "        foreach (\$fac['items'] as \$item) {\n";
$phpCode .= "            \$insertWebsite->execute([\n";
$phpCode .= "                \$categoryId,\n";
$phpCode .= "                \$item['name'],\n";
$phpCode .= "                \$item['level'],\n";
$phpCode .= "                \$item['url'],\n";
$phpCode .= "                \$item['last_status'],\n";
$phpCode .= "                \$item['last_http_code'],\n";
$phpCode .= "                \$item['last_response_time'],\n";
$phpCode .= "                \$item['notes'] ?? null\n";
$phpCode .= "            ]);\n";
$phpCode .= "            \$webId = \$db->lastInsertId();\n\n";
$phpCode .= "            if (\$item['last_http_code'] !== null) {\n";
$phpCode .= "                \$insertLog->execute([\n";
$phpCode .= "                    \$webId,\n";
$phpCode .= "                    \$item['last_http_code'],\n";
$phpCode .= "                    \$item['last_response_time'],\n";
$phpCode .= "                    \$item['last_status']\n";
$phpCode .= "                ]);\n";
$phpCode .= "            }\n";
$phpCode .= "        }\n";
$phpCode .= "    }\n";
$phpCode .= "}\n";

file_put_contents(__DIR__ . '/../database/seed.php', $phpCode);
echo "Berhasil memperbarui database/seed.php dari database aktif!\n";
