<?php
/**
 * RESTful API untuk CRUD Pilihan Jenjang / Level Pendidikan & Unit
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = Database::getConnection();

if ($method !== 'GET') {
    Auth::requireLogin();
}

// Inisialisasi tabel levels jika belum ada & seeder default
$db->exec("
    CREATE TABLE IF NOT EXISTS levels (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT NOT NULL UNIQUE,
        name TEXT NOT NULL,
        badge_style TEXT DEFAULT 's1',
        sort_order INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

// Cek apakah tabel levels kosong, jika kosong isi data default
$levelCount = (int) $db->query("SELECT COUNT(*) FROM levels")->fetchColumn();
if ($levelCount === 0) {
    $defaults = [
        ['S1', 'Sarjana (S1)', 's1', 1],
        ['S2', 'Magister (S2)', 's2', 2],
        ['S3', 'Doktor (S3)', 's3', 3],
        ['D3', 'Diploma III (D3)', 'd3', 4],
        ['D4', 'Sarjana Terapan (D4)', 'd4', 5],
        ['Profesi', 'Pendidikan Profesi', 'profesi', 6],
        ['Unit', 'Unit Kerja / Layanan', 'unit', 7],
        ['Sistem', 'Sistem Informasi / Portal', 'sistem', 8],
        ['UPT', 'Unit Pelaksana Teknis', 'upt', 9],
        ['Lembaga', 'Lembaga / Biro Kampus', 'lembaga', 10],
    ];
    $stmt = $db->prepare("INSERT OR IGNORE INTO levels (code, name, badge_style, sort_order) VALUES (?, ?, ?, ?)");
    foreach ($defaults as $d) {
        $stmt->execute($d);
    }
}

try {
    // GET: Ambil semua data jenjang beserta jumlah website yang menggunakannya
    if ($method === 'GET') {
        $stmt = $db->query("
            SELECT l.*, COUNT(w.id) AS websites_count
            FROM levels l
            LEFT JOIN websites w ON LOWER(l.code) = LOWER(w.level)
            GROUP BY l.id
            ORDER BY l.sort_order ASC, l.name ASC
        ");
        $levels = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'data' => $levels
        ]);
        exit;
    }

    // POST: Tambah jenjang baru
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $code = trim($input['code'] ?? '');
        $name = trim($input['name'] ?? '');
        $badgeStyle = trim($input['badge_style'] ?? 's1');
        $sortOrder = (int) ($input['sort_order'] ?? 0);

        if (empty($code) || empty($name)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Kode Singkatan dan Nama Jenjang wajib diisi']);
            exit;
        }

        // Cek duplikasi kode
        $check = $db->prepare("SELECT COUNT(*) FROM levels WHERE LOWER(code) = LOWER(?)");
        $check->execute([$code]);
        if ($check->fetchColumn() > 0) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => "Kode jenjang '{$code}' sudah ada"]);
            exit;
        }

        $stmt = $db->prepare("INSERT INTO levels (code, name, badge_style, sort_order) VALUES (?, ?, ?, ?)");
        $stmt->execute([$code, $name, $badgeStyle, $sortOrder]);

        echo json_encode([
            'success' => true,
            'message' => 'Jenjang baru berhasil ditambahkan',
            'id' => $db->lastInsertId()
        ]);
        exit;
    }

    // PUT: Update jenjang
    if ($method === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int) ($input['id'] ?? 0);
        $code = trim($input['code'] ?? '');
        $name = trim($input['name'] ?? '');
        $badgeStyle = trim($input['badge_style'] ?? 's1');
        $sortOrder = (int) ($input['sort_order'] ?? 0);

        if (!$id || empty($code) || empty($name)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Data jenjang tidak lengkap']);
            exit;
        }

        $stmt = $db->prepare("UPDATE levels SET code = ?, name = ?, badge_style = ?, sort_order = ? WHERE id = ?");
        $stmt->execute([$code, $name, $badgeStyle, $sortOrder, $id]);

        echo json_encode([
            'success' => true,
            'message' => 'Data jenjang berhasil diperbarui'
        ]);
        exit;
    }

    // DELETE: Hapus jenjang
    if ($method === 'DELETE') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_GET;
        $id = (int) ($input['id'] ?? 0);

        if (!$id) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'ID jenjang tidak valid']);
            exit;
        }

        // Cek apakah jenjang ada dan sedang digunakan oleh website
        $checkStmt = $db->prepare("SELECT code FROM levels WHERE id = ?");
        $checkStmt->execute([$id]);
        $lvlCode = $checkStmt->fetchColumn();

        if ($lvlCode) {
            $webCheck = $db->prepare("SELECT COUNT(*) FROM websites WHERE LOWER(level) = LOWER(?)");
            $webCheck->execute([$lvlCode]);
            $usedCount = (int) $webCheck->fetchColumn();
            if ($usedCount > 0) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => "Pilihan jenjang '{$lvlCode}' tidak dapat dihapus karena sedang digunakan oleh {$usedCount} website."
                ]);
                exit;
            }
        }

        $stmt = $db->prepare("DELETE FROM levels WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode([
            'success' => true,
            'message' => 'Pilihan jenjang berhasil dihapus'
        ]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method tidak diizinkan']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan server: ' . $e->getMessage()]);
}
