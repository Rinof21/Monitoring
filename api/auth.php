<?php
/**
 * Auth API Endpoint
 * Handles login, logout, and auth state check
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../classes/Auth.php';

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($action === 'login' || ($method === 'POST' && empty($action))) {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $username = trim($input['username'] ?? '');
        $password = trim($input['password'] ?? '');

        if (empty($username) || empty($password)) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Username dan password wajib diisi.'
            ]);
            exit;
        }

        $result = Auth::attempt($username, $password);
        if (!$result['success']) {
            http_response_code(401);
        }
        echo json_encode($result);
        exit;
    }

    if ($action === 'logout') {
        Auth::logout();
        echo json_encode([
            'success' => true,
            'message' => 'Logout berhasil.'
        ]);
        exit;
    }

    if ($action === 'check') {
        echo json_encode([
            'success' => true,
            'logged_in' => Auth::check(),
            'user' => Auth::user()
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Aksi autentikasi tidak valid.'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
    ]);
}
