<?php
/**
 * Script untuk menambahkan kategori & website Universitas Tanjungpura ke database yang sedang berjalan
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/WebChecker.php';

$db = Database::getConnection();

// 1. Cek apakah kategori Universitas Tanjungpura sudah ada
$stmt = $db->prepare("SELECT id FROM categories WHERE name LIKE '%Tanjungpura%' OR code = 'UNTAN'");
$stmt->execute();
$untanCatId = $stmt->fetchColumn();

if (!$untanCatId) {
    // Geser sort_order kategori lain
    $db->exec("UPDATE categories SET sort_order = sort_order + 1 WHERE sort_order >= 1");
    
    // Tambah kategori utama UNTAN
    $insertCat = $db->prepare("INSERT INTO categories (name, code, sort_order) VALUES (?, ?, 1)");
    $insertCat->execute(['Universitas Tanjungpura (Pusat & Layanan)', 'UNTAN']);
    $untanCatId = $db->lastInsertId();
    echo "Kategori Universitas Tanjungpura berhasil dibuat (ID: $untanCatId)\n";
} else {
    echo "Kategori Universitas Tanjungpura sudah ada (ID: $untanCatId)\n";
}

// 2. Jika ada item ID 25 (yang sebelumnya salah masuk ke Kedokteran), pindahkan ke kategori UNTAN
$stmt = $db->prepare("SELECT id, name FROM websites WHERE id = 25");
$stmt->execute();
if ($stmt->fetch()) {
    $db->prepare("UPDATE websites SET category_id = ?, name = 'SISKA - Sistem Informasi Akademik', level = 'Sistem', notes = 'Portal Layanan Akademik UNTAN' WHERE id = 25")
       ->execute([$untanCatId]);
    echo "Website ID 25 (SISKA) berhasil dipindahkan ke kategori Universitas Tanjungpura\n";
}

// 3. Tambah website resmi UNTAN lainnya jika belum ada
$untanWebsites = [
    [
        'name' => 'Portal Utama Universitas Tanjungpura',
        'level' => 'Sistem',
        'url' => 'https://www.untan.ac.id/',
        'notes' => 'Website Resmi Universitas Tanjungpura Pontianak'
    ],
    [
        'name' => 'SISKA - Sistem Informasi Akademik',
        'level' => 'Sistem',
        'url' => 'https://siska.untan.ac.id/',
        'notes' => 'Portal Layanan Akademik UNTAN'
    ],
    [
        'name' => 'SISTEMIK - Sistem Terintegrasi',
        'level' => 'Sistem',
        'url' => 'https://sistemik.untan.ac.id/',
        'notes' => 'Sistem Layanan Terintegrasi UNTAN'
    ],
    [
        'name' => 'Perpustakaan Universitas Tanjungpura',
        'level' => 'Unit',
        'url' => 'https://library.untan.ac.id/',
        'notes' => 'UPT Perpustakaan UNTAN'
    ]
];

$insertWeb = $db->prepare("
    INSERT INTO websites (category_id, name, level, url, last_status, last_http_code, last_response_time, last_checked_at, notes)
    VALUES (?, ?, ?, ?, ?, ?, ?, datetime('now', 'localtime'), ?)
");

$checkExist = $db->prepare("SELECT COUNT(*) FROM websites WHERE url = ?");

foreach ($untanWebsites as $site) {
    $checkExist->execute([$site['url']]);
    if ($checkExist->fetchColumn() == 0) {
        $check = WebChecker::check($site['url']);
        $insertWeb->execute([
            $untanCatId,
            $site['name'],
            $site['level'],
            $site['url'],
            $check['status'],
            $check['http_code'],
            $check['response_time'],
            $site['notes']
        ]);
        echo "Menambahkan: {$site['name']} ({$site['url']}) -> {$check['status']} ({$check['http_code']})\n";
    }
}

echo "Sinkronisasi Universitas Tanjungpura selesai!\n";
