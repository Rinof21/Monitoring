<?php
/**
 * Authentication Service
 * Mengatur sesi login, verifikasi kredensial admin, dan proteksi endpoint API
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

class Auth {
    /**
     * Memeriksa apakah user saat ini sedang login
     */
    public static function check(): bool {
        return !empty($_SESSION['user_id']);
    }

    /**
     * Mengambil data user yang sedang login
     */
    public static function user(): ?array {
        if (!self::check()) {
            return null;
        }
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['user_username'] ?? '',
            'name' => $_SESSION['user_name'] ?? 'Administrator',
            'role' => $_SESSION['user_role'] ?? 'admin'
        ];
    }

    /**
     * Memastikan user login, jika tidak kirim HTTP 401 dan hentikan eksekusi
     */
    public static function requireLogin(): void {
        if (!self::check()) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Akses ditolak. Fitur ini hanya dapat diakses oleh administrator yang telah login.'
            ]);
            exit;
        }
    }

    /**
     * Proses autentikasi login dengan username & password
     */
    public static function attempt(string $username, string $password): array {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            return [
                'success' => false,
                'message' => 'Username atau password yang Anda masukkan salah.'
            ];
        }

        // Simpan sesi
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_username'] = $user['username'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];

        return [
            'success' => true,
            'message' => 'Login berhasil!',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'name' => $user['name'],
                'role' => $user['role']
            ]
        ];
    }

    /**
     * Proses logout & pembersihan sesi
     */
    public static function logout(): void {
        $_SESSION = [];
        if (!headers_sent() && ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }
}
