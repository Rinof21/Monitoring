<?php
/**
 * RESTful API untuk CRUD Website yang dimonitor
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/WebChecker.php';
require_once __DIR__ . '/../classes/Auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = Database::getConnection();

// Hanya izinkan GET untuk publik, aksi modifikasi (POST/PUT/DELETE) memerlukan login
if ($method !== 'GET') {
    Auth::requireLogin();
}

try {
    if ($method === 'GET') {
        // Ambil satu data spesifik jika ada ?id=
        if (isset($_GET['id'])) {
            $stmt = $db->prepare("
                SELECT w.*, c.name AS category_name, c.code AS category_code 
                FROM websites w
                JOIN categories c ON w.category_id = c.id
                WHERE w.id = ?
            ");
            $stmt->execute([(int)$_GET['id']]);
            $website = $stmt->fetch();

            if (!$website) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Website tidak ditemukan']);
                exit;
            }

            echo json_encode(['success' => true, 'data' => $website]);
            exit;
        }

        // Ambil semua website dengan pengelompokan hierarki Fakultas / Unit
        $categoryStmt = $db->query("SELECT * FROM categories ORDER BY sort_order ASC, name ASC");
        $categories = $categoryStmt->fetchAll();

        $websiteStmt = $db->query("
            SELECT w.*, c.name AS category_name, c.code AS category_code 
            FROM websites w
            JOIN categories c ON w.category_id = c.id
            ORDER BY c.sort_order ASC, c.name ASC, w.id ASC
        ");
        $websites = $websiteStmt->fetchAll();

        // Susun struktur hierarki
        $grouped = [];
        foreach ($categories as $cat) {
            $catId = $cat['id'];
            $grouped[$catId] = [
                'id' => $cat['id'],
                'name' => $cat['name'],
                'code' => $cat['code'],
                'items' => []
            ];
        }

        foreach ($websites as $web) {
            $catId = $web['category_id'];
            if (isset($grouped[$catId])) {
                $grouped[$catId]['items'][] = $web;
            }
        }

        echo json_encode([
            'success' => true,
            'grouped' => array_values($grouped),
            'raw' => $websites,
            'categories' => $categories
        ]);
        exit;
    }

    // POST: Tambah website baru
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

        $name = trim($input['name'] ?? '');
        $url = trim($input['url'] ?? '');
        $level = trim($input['level'] ?? 'S1');
        $categoryId = (int) ($input['category_id'] ?? 0);
        $newCategoryName = trim($input['new_category_name'] ?? '');
        $notes = trim($input['notes'] ?? '');

        if (empty($name) || empty($url)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Nama Prodi/Website dan URL wajib diisi']);
            exit;
        }

        // Validasi format URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Format URL tidak valid (harus menyertakan http:// atau https://)']);
            exit;
        }

        // Jika user memilih buat kategori baru
        if ($categoryId === 0 && !empty($newCategoryName)) {
            $catStmt = $db->prepare("INSERT INTO categories (name, sort_order) VALUES (?, 99)");
            $catStmt->execute([$newCategoryName]);
            $categoryId = (int)$db->lastInsertId();
        }

        if ($categoryId === 0) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Fakultas / Unit wajib dipilih']);
            exit;
        }

        // Cek langsung status awal URL yang baru ditambahkan
        $checkResult = WebChecker::check($url);

        $stmt = $db->prepare("
            INSERT INTO websites (category_id, name, level, url, last_status, last_http_code, last_response_time, last_checked_at, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, datetime('now', 'localtime'), ?)
        ");
        $stmt->execute([
            $categoryId,
            $name,
            $level,
            $url,
            $checkResult['status'],
            $checkResult['http_code'],
            $checkResult['response_time'],
            $notes
        ]);

        $newId = $db->lastInsertId();

        // Catat ke log
        $logStmt = $db->prepare("
            INSERT INTO check_logs (website_id, http_code, response_time, status, error_message)
            VALUES (?, ?, ?, ?, ?)
        ");
        $logStmt->execute([
            $newId,
            $checkResult['http_code'],
            $checkResult['response_time'],
            $checkResult['status'],
            $checkResult['error_message']
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Website berhasil ditambahkan dan diperiksa statusnya',
            'id' => $newId,
            'initial_status' => $checkResult
        ]);
        exit;
    }

    // PUT: Update website yang sudah ada
    if ($method === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int) ($input['id'] ?? 0);
        $name = trim($input['name'] ?? '');
        $url = trim($input['url'] ?? '');
        $level = trim($input['level'] ?? 'S1');
        $categoryId = (int) ($input['category_id'] ?? 0);
        $notes = trim($input['notes'] ?? '');

        if (!$id || empty($name) || empty($url) || !$categoryId) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
            exit;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Format URL tidak valid']);
            exit;
        }

        $stmt = $db->prepare("
            UPDATE websites 
            SET category_id = ?, name = ?, level = ?, url = ?, notes = ?, updated_at = datetime('now', 'localtime')
            WHERE id = ?
        ");
        $stmt->execute([$categoryId, $name, $level, $url, $notes, $id]);

        // Opsional: periksa ulang status setelah edit URL
        $checkResult = WebChecker::checkAndUpdate($id);

        echo json_encode([
            'success' => true,
            'message' => 'Data website berhasil diperbarui',
            'updated_data' => $checkResult
        ]);
        exit;
    }

    // DELETE: Hapus website
    if ($method === 'DELETE') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_GET;
        $id = (int) ($input['id'] ?? 0);

        if (!$id) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'ID website tidak valid']);
            exit;
        }

        $stmt = $db->prepare("DELETE FROM websites WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode([
            'success' => true,
            'message' => 'Website berhasil dihapus'
        ]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method tidak diizinkan']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan server: ' . $e->getMessage()]);
}
