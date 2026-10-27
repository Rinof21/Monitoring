<?php
/**
 * Seeder Data Terkini dari Database Aktif
 * Sesuai data pemantauan Universitas Tanjungpura (UNTAN)
 * Dihasilkan secara otomatis dari isi database monitor.sqlite
 */

function seedDatabase(PDO $db): void {
    // 1. Inisialisasi Pilihan Jenjang / Level
    $levels = array (
  0 => 
  array (
    'code' => 'S1',
    'name' => 'Sarjana (S1)',
    'badge_style' => 's1',
    'sort_order' => 1,
  ),
  1 => 
  array (
    'code' => 'S2',
    'name' => 'Magister (S2)',
    'badge_style' => 's2',
    'sort_order' => 2,
  ),
  2 => 
  array (
    'code' => 'Profesi',
    'name' => 'Pendidikan Profesi',
    'badge_style' => 'profesi',
    'sort_order' => 6,
  ),
  3 => 
  array (
    'code' => 'Unit',
    'name' => 'Unit Kerja / Layanan',
    'badge_style' => 'unit',
    'sort_order' => 7,
  ),
  4 => 
  array (
    'code' => 'Sistem',
    'name' => 'Sistem Informasi / Portal',
    'badge_style' => 'sistem',
    'sort_order' => 8,
  ),
  5 => 
  array (
    'code' => 'UPT',
    'name' => 'Unit Pelaksana Teknis',
    'badge_style' => 'upt',
    'sort_order' => 9,
  ),
  6 => 
  array (
    'code' => 'Lembaga',
    'name' => 'Lembaga / Biro Kampus',
    'badge_style' => 'lembaga',
    'sort_order' => 10,
  ),
);

    $insertLevel = $db->prepare("INSERT OR IGNORE INTO levels (code, name, badge_style, sort_order) VALUES (?, ?, ?, ?)");
    foreach ($levels as $lvl) {
        $insertLevel->execute([
            $lvl['code'],
            $lvl['name'],
            $lvl['badge_style'],
            $lvl['sort_order']
        ]);
    }

    // 2. Inisialisasi Fakultas / Unit & Daftar Website
    $faculties = array (
  0 => 
  array (
    'name' => 'Universitas Tanjungpura (Pusat & Layanan)',
    'code' => 'UNTAN',
    'sort_order' => 1,
    'items' => 
    array (
      0 => 
      array (
        'name' => 'Sistem Persuratan dan SK',
        'level' => 'Sistem',
        'url' => 'https://siska.untan.ac.id/',
        'notes' => 'Portal Layanan Akademik UNTAN',
        'last_status' => 'Aktif',
        'last_http_code' => 200,
        'last_response_time' => 180,
      ),
      1 => 
      array (
        'name' => 'Portal Utama Universitas Tanjungpura',
        'level' => 'Sistem',
        'url' => 'https://www.untan.ac.id/',
        'notes' => 'Website Universitas',
        'last_status' => 'Aktif',
        'last_http_code' => 200,
        'last_response_time' => 648,
      ),
      2 => 
      array (
        'name' => 'Sistem Informasi Akademik Terintegrasi',
        'level' => 'Unit',
        'url' => 'https://satu.untan.ac.id/gate/login',
        'notes' => '',
        'last_status' => 'Aktif',
        'last_http_code' => 200,
        'last_response_time' => 250,
      ),
      3 => 
      array (
        'name' => 'Informasi Publik',
        'level' => 'Unit',
        'url' => 'https://ppid.untan.ac.id/beranda/',
        'notes' => '',
        'last_status' => 'Aktif',
        'last_http_code' => 200,
        'last_response_time' => 1266,
      ),
      4 => 
      array (
        'name' => 'Presensi, Cuti, Izin dan Tugas Luar',
        'level' => 'Unit',
        'url' => 'https://siremun.untan.ac.id/',
        'notes' => '',
        'last_status' => 'Aktif',
        'last_http_code' => 200,
        'last_response_time' => 172,
      ),
      5 => 
      array (
        'name' => 'Tracer Study',
        'level' => 'Unit',
        'url' => 'https://tracerstudyalumni.untan.ac.id/login',
        'notes' => '',
        'last_status' => 'Aktif',
        'last_http_code' => 200,
        'last_response_time' => 281,
      ),
      6 => 
      array (
        'name' => 'Presensi Work From Home',
        'level' => 'Unit',
        'url' => 'https://wfh.untan.ac.id/',
        'notes' => '',
        'last_status' => 'Aktif',
        'last_http_code' => 200,
        'last_response_time' => 172,
      ),
      7 => 
      array (
        'name' => 'Prestasi Mahasiswa',
        'level' => 'Unit',
        'url' => 'https://simkatmawa.untan.ac.id/id/',
        'notes' => '',
        'last_status' => 'Aktif',
        'last_http_code' => 200,
        'last_response_time' => 1099,
      ),
      8 => 
      array (
        'name' => 'Repository Universitas',
        'level' => 'Unit',
        'url' => 'https://perpustakaan.untan.ac.id/',
        'notes' => '',
        'last_status' => 'Aktif',
        'last_http_code' => 200,
        'last_response_time' => 231,
      ),
      9 => 
      array (
        'name' => 'Survey Pengguna Lulusan',
        'level' => 'Unit',
        'url' => 'https://usersurvey.untan.ac.id/',
        'notes' => '',
        'last_status' => 'Error Server',
        'last_http_code' => 404,
        'last_response_time' => 249,
      ),
      10 => 
      array (
        'name' => 'Pendaftaran Mahasiswa Baru',
        'level' => 'Unit',
        'url' => 'https://scmb.untan.ac.id/id',
        'notes' => '',
        'last_status' => 'Aktif',
        'last_http_code' => 200,
        'last_response_time' => 790,
      ),
      11 => 
      array (
        'name' => 'Beban Kerja Dosen',
        'level' => 'Unit',
        'url' => 'https://bkd.untan.ac.id/',
        'notes' => '',
        'last_status' => 'Aktif',
        'last_http_code' => 200,
        'last_response_time' => 36,
      ),
      12 => 
      array (
        'name' => 'LMS/eLearning mahasiswa',
        'level' => 'Unit',
        'url' => 'https://edlink.id/',
        'notes' => '',
        'last_status' => 'Aktif',
        'last_http_code' => 200,
        'last_response_time' => 125,
      ),
    ),
  ),
  1 => 
  array (
    'name' => 'Fakultas Kedokteran',
    'code' => 'FK',
    'sort_order' => 5,
    'items' => 
    array (
      0 => 
      array (
        'name' => 'Website Fakultas',
        'level' => 'Sistem',
        'url' => 'https://kedokteran.untan.ac.id/',
        'notes' => 'Neelavm Untan',
        'last_status' => 'Aktif',
        'last_http_code' => 200,
        'last_response_time' => 1924,
      ),
      1 => 
      array (
        'name' => 'Silat',
        'level' => 'Sistem',
        'url' => 'https://silatfk.untan.ac.id/',
        'notes' => 'vps untan',
        'last_status' => 'Aktif',
        'last_http_code' => 200,
        'last_response_time' => 136,
      ),
      2 => 
      array (
        'name' => 'Bagian Keperawatan',
        'level' => 'Sistem',
        'url' => 'https://keperawatan.untan.ac.id/',
        'notes' => '',
        'last_status' => 'Aktif',
        'last_http_code' => 200,
        'last_response_time' => 1282,
      ),
      3 => 
      array (
        'name' => 'Bagian Farmasi',
        'level' => 'Sistem',
        'url' => 'https://farmasi.untan.ac.id/',
        'notes' => '',
        'last_status' => 'Aktif',
        'last_http_code' => 200,
        'last_response_time' => 899,
      ),
      4 => 
      array (
        'name' => 'Prodi  s1 kedokteran',
        'level' => 'S1',
        'url' => 'https://sistemik.untan.ac.id/',
        'notes' => '',
        'last_status' => 'Aktif',
        'last_http_code' => 200,
        'last_response_time' => 49,
      ),
      5 => 
      array (
        'name' => 'Prodi S1 Keperawatan',
        'level' => 'S1',
        'url' => 'https://saturasi.untan.ac.id/',
        'notes' => '',
        'last_status' => 'Error Server',
        'last_http_code' => 500,
        'last_response_time' => 57,
      ),
      6 => 
      array (
        'name' => 'Prodi S1 Farmasi',
        'level' => 'S1',
        'url' => 'https://suspensi.untan.ac.id/',
        'notes' => '',
        'last_status' => 'Aktif',
        'last_http_code' => 200,
        'last_response_time' => 85,
      ),
      7 => 
      array (
        'name' => 'Reservasi Ruang Sidang (Siperus)',
        'level' => 'Unit',
        'url' => 'http://203.24.51.238:8015/',
        'notes' => '',
        'last_status' => 'Aktif',
        'last_http_code' => 200,
        'last_response_time' => 264,
      ),
    ),
  ),
  2 => 
  array (
    'name' => 'Unit & Layanan Kampus',
    'code' => 'UNIT',
    'sort_order' => 6,
    'items' => 
    array (
      0 => 
      array (
        'name' => 'SKP dan Log Harian',
        'level' => 'Unit',
        'url' => 'https://osdm.kemdiktisaintek.go.id/skp/site/login',
        'notes' => '',
        'last_status' => 'Aktif',
        'last_http_code' => 200,
        'last_response_time' => 301,
      ),
    ),
  ),
);

    $insertCategory = $db->prepare("INSERT INTO categories (name, code, sort_order) VALUES (?, ?, ?)");
    $insertWebsite = $db->prepare("INSERT INTO websites (category_id, name, level, url, last_status, last_http_code, last_response_time, last_checked_at, notes) VALUES (?, ?, ?, ?, ?, ?, ?, datetime('now', 'localtime'), ?)");
    $insertLog = $db->prepare("INSERT INTO check_logs (website_id, http_code, response_time, status) VALUES (?, ?, ?, ?)");

    foreach ($faculties as $fac) {
        $insertCategory->execute([$fac['name'], $fac['code'], $fac['sort_order']]);
        $categoryId = $db->lastInsertId();

        foreach ($fac['items'] as $item) {
            $insertWebsite->execute([
                $categoryId,
                $item['name'],
                $item['level'],
                $item['url'],
                $item['last_status'],
                $item['last_http_code'],
                $item['last_response_time'],
                $item['notes'] ?? null
            ]);
            $webId = $db->lastInsertId();

            if ($item['last_http_code'] !== null) {
                $insertLog->execute([
                    $webId,
                    $item['last_http_code'],
                    $item['last_response_time'],
                    $item['last_status']
                ]);
            }
        }
    }
}
