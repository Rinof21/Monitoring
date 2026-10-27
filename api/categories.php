<?php
/**
 * RESTful API untuk CRUD Kategori / Fakultas / Unit Kerja
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = Database::getConnection();

if ($method !== 'GET') {
    Auth::requireLogin();
}

try {
    // GET: Ambil daftar seluruh kategori beserta jumlah website di dalamnya
    if ($method === 'GET') {
        $stmt = $db->query("
            SELECT c.*, COUNT(w.id) AS websites_count 
            FROM categories c
            LEFT JOIN websites w ON c.id = w.category_id
            GROUP BY c.id
            ORDER BY c.sort_order ASC, c.name ASC
        ");
        $categories = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'data' => $categories
        ]);
        exit;
    }

    // POST: Tambah kategori baru
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $name = trim($input['name'] ?? '');
        $code = strtoupper(trim($input['code'] ?? ''));
        $sortOrder = (int) ($input['sort_order'] ?? 0);

        if (empty($name)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Nama Fakultas / Unit Kerja wajib diisi']);
            exit;
        }

        // Cek duplikasi nama
        $check = $db->prepare("SELECT COUNT(*) FROM categories WHERE LOWER(name) = LOWER(?)");
        $check->execute([$name]);
        if ($check->fetchColumn() > 0) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Nama Fakultas / Unit ini sudah terdaftar']);
            exit;
        }

        $stmt = $db->prepare("INSERT INTO categories (name, code, sort_order) VALUES (?, ?, ?)");
        $stmt->execute([$name, $code ?: null, $sortOrder]);
        $newId = $db->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => 'Fakultas / Unit berhasil ditambahkan',
            'id' => $newId
        ]);
        exit;
    }

    // PUT: Update kategori
    if ($method === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int) ($input['id'] ?? 0);
        $name = trim($input['name'] ?? '');
        $code = strtoupper(trim($input['code'] ?? ''));
        $sortOrder = (int) ($input['sort_order'] ?? 0);

        if (!$id || empty($name)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'ID dan Nama Fakultas / Unit wajib diisi']);
            exit;
        }

        $stmt = $db->prepare("UPDATE categories SET name = ?, code = ?, sort_order = ? WHERE id = ?");
        $stmt->execute([$name, $code ?: null, $sortOrder, $id]);

        echo json_encode([
            'success' => true,
            'message' => 'Fakultas / Unit berhasil diperbarui'
        ]);
        exit;
    }

    // DELETE: Hapus kategori
    if ($method === 'DELETE') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_GET;
        $id = (int) ($input['id'] ?? 0);

        if (!$id) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'ID kategori tidak valid']);
            exit;
        }

        // Cek apakah ada website di dalamnya
        $countStmt = $db->prepare("SELECT COUNT(*) FROM websites WHERE category_id = ?");
        $countStmt->execute([$id]);
        $webCount = (int) $countStmt->fetchColumn();

        if ($webCount > 0 && empty($input['force'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'has_websites' => true,
                'websites_count' => $webCount,
                'message' => "Fakultas/Unit ini masih memiliki {$webCount} website yang dipantau. Harap pindahkan atau hapus website terlebih dahulu."
            ]);
            exit;
        }

        $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode([
            'success' => true,
            'message' => 'Fakultas / Unit berhasil dihapus'
        ]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method tidak diizinkan']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan server: ' . $e->getMessage()]);
}
