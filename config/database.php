<?php
/**
 * Database Connection & Initialization Helper
 * Menggunakan SQLite via PDO dengan auto-migration dan auto-seed
 */

class Database {
    private static ?PDO $pdo = null;
    private static string $dbFile = __DIR__ . '/../database/monitor.sqlite';

    public static function getConnection(): PDO {
        if (self::$pdo === null) {
            $dbDir = dirname(self::$dbFile);
            if (!is_dir($dbDir)) {
                mkdir($dbDir, 0777, true);
            }

            $isNewDatabase = !file_exists(self::$dbFile);

            try {
                self::$pdo = new PDO('sqlite:' . self::$dbFile);
                self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                self::$pdo->exec('PRAGMA foreign_keys = ON;');

                // Inisialisasi skema dan seed jika database baru dibuat
                if ($isNewDatabase) {
                    self::initializeDatabase();
                } else {
                    // Pastikan tabel users sudah ada untuk database lama
                    self::checkUsersTable();
                }
            } catch (PDOException $e) {
                die("Gagal menghubungkan ke database SQLite: " . $e->getMessage());
            }
        }
        return self::$pdo;
    }

    public static function initializeDatabase(): void {
        $db = self::$pdo;
        $schemaFile = __DIR__ . '/../database/schema.sql';
        if (file_exists($schemaFile)) {
            $sql = file_get_contents($schemaFile);
            $db->exec($sql);
        }

        // Jalankan seeder default jika tabel categories kosong
        $count = $db->query("SELECT COUNT(*) FROM categories")->fetchColumn();
        if ($count == 0) {
            require_once __DIR__ . '/../database/seed.php';
            seedDatabase($db);
        }

        self::checkUsersTable();
    }

    public static function checkUsersTable(): void {
        $db = self::$pdo;
        $db->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL,
                name TEXT NOT NULL,
                role TEXT DEFAULT 'admin',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        $userCount = (int) $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
        if ($userCount === 0) {
            $stmt = $db->prepare("INSERT INTO users (username, password, name, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                'admin',
                password_hash('admin123', PASSWORD_BCRYPT),
                'Administrator UNTAN',
                'admin'
            ]);
        }
    }
}
