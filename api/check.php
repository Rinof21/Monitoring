<?php
/**
 * API Endpoint untuk eksekusi pengecekan HTTP/Status
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/WebChecker.php';

$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$testUrl = $_GET['test_url'] ?? '';

try {
    // 1. Cek satu URL sementara untuk uji koneksi (di modal tambah/edit)
    if (!empty($testUrl)) {
        if (!filter_var($testUrl, FILTER_VALIDATE_URL)) {
            echo json_encode(['success' => false, 'message' => 'Format URL tidak valid']);
            exit;
        }
        $result = WebChecker::check($testUrl);
        echo json_encode([
            'success' => true,
            'result' => $result
        ]);
        exit;
    }

    // 2. Cek satu website yang sudah terdaftar di database
    if ($id > 0) {
        $result = WebChecker::checkAndUpdate($id);
        if (!$result) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Website tidak ditemukan']);
            exit;
        }
        echo json_encode([
            'success' => true,
            'message' => 'Pengecekan selesai',
            'data' => $result
        ]);
        exit;
    }

    // 3. Cek semua website secara paralel (Multi-cURL)
    if ($action === 'check_all') {
        $categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : null;
        $results = WebChecker::checkAllBatch($categoryId);
        echo json_encode([
            'success' => true,
            'message' => 'Semua website berhasil diperiksa',
            'total_checked' => count($results),
            'data' => $results
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parameter permintaan tidak valid']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gagal memproses pengecekan: ' . $e->getMessage()]);
}
