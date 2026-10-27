<?php
/**
 * API Endpoint untuk Statistik Ringkasan / Dashboard KPI
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getConnection();

    // Total website aktif yang dimonitor
    $totalWebsites = (int) $db->query("SELECT COUNT(*) FROM websites WHERE is_active = 1")->fetchColumn();

    // Website dengan status Aktif (HTTP 200 - 399)
    $activeCount = (int) $db->query("SELECT COUNT(*) FROM websites WHERE is_active = 1 AND last_status = 'Aktif'")->fetchColumn();

    // Website dengan status Error Server / Gangguan
    $errorCount = (int) $db->query("SELECT COUNT(*) FROM websites WHERE is_active = 1 AND last_status != 'Aktif' AND last_status != 'Belum Dicek'")->fetchColumn();

    // Rata-rata waktu respon (ms) dari website yang aktif
    $avgResponseTime = (int) round((float) $db->query("SELECT AVG(last_response_time) FROM websites WHERE is_active = 1 AND last_response_time > 0")->fetchColumn());

    // Waktu pengecekan terakhir
    $lastChecked = $db->query("SELECT MAX(last_checked_at) FROM websites WHERE is_active = 1")->fetchColumn();

    // Persentase Uptime / Kesehatan Sistem
    $healthRate = $totalWebsites > 0 ? round(($activeCount / $totalWebsites) * 100, 1) : 100;

    // Daftar Kategori
    $categories = $db->query("SELECT id, name, code FROM categories ORDER BY sort_order ASC, name ASC")->fetchAll();

    echo json_encode([
        'success' => true,
        'stats' => [
            'total' => $totalWebsites,
            'active' => $activeCount,
            'error' => $errorCount,
            'avg_latency' => $avgResponseTime,
            'last_checked' => $lastChecked ?: 'Belum pernah',
            'health_rate' => $healthRate
        ],
        'categories' => $categories
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gagal mengambil statistik: ' . $e->getMessage()]);
}
