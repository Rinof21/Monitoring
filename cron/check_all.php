<?php
/**
 * CLI Background Worker / Cron Script
 * Menjalankan pengecekan semua website secara terjadwal
 * Jalankan via terminal: php cron/check_all.php
 */

if (php_sapi_name() !== 'cli' && !isset($_GET['secret_token'])) {
    die("Script ini hanya dapat dijalankan melalui CLI (Command Line Interface) atau token terautentikasi.\n");
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/WebChecker.php';

echo "[" . date('Y-m-d H:i:s') . "] Memulai pengecekan berkala seluruh website...\n";

$start = microtime(true);
$results = WebChecker::checkAllBatch();
$duration = round(microtime(true) - $start, 2);

$total = count($results);
$active = 0;
$error = 0;

foreach ($results as $r) {
    if ($r['status'] === 'Aktif') {
        $active++;
    } else {
        $error++;
    }
}

echo "--------------------------------------------------------\n";
echo "Hasil Monitoring:\n";
echo "Total Website : $total\n";
echo "Aktif         : $active\n";
echo "Gangguan/Down : $error\n";
echo "Durasi Cek    : {$duration}s\n";
echo "--------------------------------------------------------\n";
echo "[" . date('Y-m-d H:i:s') . "] Selesai.\n";
