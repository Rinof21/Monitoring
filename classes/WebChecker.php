<?php
/**
 * WebChecker Service
 * Bertanggung jawab melakukan HTTP request (cURL) untuk mendeteksi status, HTTP code, dan latensi (ms).
 */

require_once __DIR__ . '/../config/database.php';

class WebChecker {
    /**
     * Melakukan pengecekan satu URL secara detail
     */
    public static function check(string $url): array {
        $startTime = microtime(true);
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_COOKIEFILE => '', // In-memory cookie jar untuk menangani redirect SSO (Sevima, OAuth, dll)
            CURLOPT_ENCODING => '',   // Mendukung gzip/deflate otomatis
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36',
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        curl_close($ch);

        $responseTime = (int) round($totalTime * 1000);
        if ($responseTime <= 0) {
            $responseTime = (int) round((microtime(true) - $startTime) * 1000);
        }

        // Tentukan status dan pesan
        $status = 'Aktif';
        $errorMessage = null;

        if ($curlErrno !== 0 || $httpCode === 0) {
            $httpCode = $httpCode ?: 0;
            if ($curlErrno === CURLE_OPERATION_TIMEDOUT) {
                $status = 'Timeout';
                $errorMessage = 'Request Timeout (> 10 detik)';
            } else {
                $status = 'Error Server';
                $errorMessage = $curlError ?: 'Gagal terhubung ke server atau domain tidak aktif';
            }
        } elseif ($httpCode >= 200 && $httpCode < 400) {
            $status = 'Aktif';
        } elseif ($httpCode >= 400) {
            $status = 'Error Server';
            $errorMessage = self::getHttpErrorMessage($httpCode);
        }

        return [
            'url' => $url,
            'http_code' => $httpCode,
            'response_time' => $responseTime,
            'status' => $status,
            'error_message' => $errorMessage,
            'checked_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Memperbarui status website tertentu di database SQLite
     */
    public static function checkAndUpdate(int $websiteId): ?array {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id, name, url FROM websites WHERE id = ?");
        $stmt->execute([$websiteId]);
        $site = $stmt->fetch();

        if (!$site) {
            return null;
        }

        $result = self::check($site['url']);

        // Update tabel websites
        $updateStmt = $db->prepare("
            UPDATE websites 
            SET last_status = ?, 
                last_http_code = ?, 
                last_response_time = ?, 
                last_checked_at = datetime('now', 'localtime'),
                updated_at = datetime('now', 'localtime')
            WHERE id = ?
        ");
        $updateStmt->execute([
            $result['status'],
            $result['http_code'],
            $result['response_time'],
            $site['id']
        ]);

        // Catat ke log riwayat
        $logStmt = $db->prepare("
            INSERT INTO check_logs (website_id, http_code, response_time, status, error_message)
            VALUES (?, ?, ?, ?, ?)
        ");
        $logStmt->execute([
            $site['id'],
            $result['http_code'],
            $result['response_time'],
            $result['status'],
            $result['error_message']
        ]);

        return array_merge($site, $result);
    }

    /**
     * Melakukan pengecekan paralel (multi-cURL) untuk banyak website sekaligus
     * Sangat cepat dibandingkan loop sequential
     */
    public static function checkAllBatch(?int $categoryId = null): array {
        $db = Database::getConnection();
        $sql = "SELECT id, name, url, category_id FROM websites WHERE is_active = 1";
        $params = [];
        if ($categoryId) {
            $sql .= " AND category_id = ?";
            $params[] = $categoryId;
        }
        $sql .= " ORDER BY category_id ASC, id ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $websites = $stmt->fetchAll();

        if (empty($websites)) {
            return [];
        }

        // Gunakan chunking (maks 6 request konkuren sekaligus) agar tidak membebani server/firewall host
        $concurrency = 6;
        $chunks = array_chunk($websites, $concurrency);
        $results = [];

        $updateStmt = $db->prepare("
            UPDATE websites 
            SET last_status = ?, 
                last_http_code = ?, 
                last_response_time = ?, 
                last_checked_at = datetime('now', 'localtime'),
                updated_at = datetime('now', 'localtime')
            WHERE id = ?
        ");
        $logStmt = $db->prepare("
            INSERT INTO check_logs (website_id, http_code, response_time, status, error_message)
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($chunks as $chunk) {
            $mh = curl_multi_init();
            $curlHandles = [];
            $startTimes = [];

            foreach ($chunk as $site) {
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $site['url'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HEADER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 12,
                    CURLOPT_CONNECTTIMEOUT => 6,
                    CURLOPT_COOKIEFILE => '', // In-memory cookie handling
                    CURLOPT_ENCODING => '',
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36',
                ]);
                curl_multi_add_handle($mh, $ch);
                $curlHandles[$site['id']] = $ch;
                $startTimes[$site['id']] = microtime(true);
            }

            // Eksekusi paralel per batch
            $running = null;
            do {
                $status = curl_multi_exec($mh, $running);
                if ($running) {
                    curl_multi_select($mh, 0.1);
                }
            } while ($running > 0 && $status == CURLM_OK);

            // Tangkap status error transfer sesungguhnya dari multi handle
            $transferErrors = [];
            while ($info = curl_multi_info_read($mh)) {
                foreach ($curlHandles as $id => $ch) {
                    if ($info['handle'] === $ch) {
                        $transferErrors[$id] = $info['result'];
                    }
                }
            }

            foreach ($chunk as $site) {
                $id = $site['id'];
                $ch = $curlHandles[$id];

                $curlErrno = $transferErrors[$id] ?? curl_errno($ch);
                $curlError = curl_error($ch);
                $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);

                $responseTime = (int) round($totalTime * 1000);
                if ($responseTime <= 0) {
                    $responseTime = (int) round((microtime(true) - $startTimes[$id]) * 1000);
                }

                $status = 'Aktif';
                $errorMessage = null;

                if ($curlErrno !== 0 || $httpCode === 0) {
                    $httpCode = $httpCode ?: 0;
                    if ($curlErrno === CURLE_OPERATION_TIMEDOUT) {
                        $status = 'Timeout';
                        $errorMessage = 'Request Timeout (> 12 detik)';
                    } else {
                        $status = 'Error Server';
                        $errorMessage = $curlError ?: 'Gagal terhubung ke host atau domain tidak aktif';
                    }
                } elseif ($httpCode >= 200 && $httpCode < 400) {
                    $status = 'Aktif';
                } elseif ($httpCode >= 400) {
                    $status = 'Error Server';
                    $errorMessage = self::getHttpErrorMessage($httpCode);
                }

                // Update ke DB
                $updateStmt->execute([$status, $httpCode, $responseTime, $id]);
                $logStmt->execute([$id, $httpCode, $responseTime, $status, $errorMessage]);

                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);

                $results[] = [
                    'id' => $id,
                    'name' => $site['name'],
                    'url' => $site['url'],
                    'category_id' => $site['category_id'],
                    'status' => $status,
                    'http_code' => $httpCode,
                    'response_time' => $responseTime,
                    'error_message' => $errorMessage,
                    'checked_at' => date('Y-m-d H:i:s')
                ];
            }

            curl_multi_close($mh);
        }

        return $results;
    }

    private static function getHttpErrorMessage(int $code): string {
        $messages = [
            400 => '400 Bad Request',
            401 => '401 Unauthorized',
            403 => '403 Forbidden',
            404 => '404 Not Found',
            405 => '405 Method Not Allowed',
            408 => '408 Request Timeout',
            500 => '500 Internal Server Error',
            502 => '502 Bad Gateway',
            503 => '503 Service Unavailable',
            504 => '504 Gateway Timeout',
            520 => '520 Web Server Unknown Error (Cloudflare)',
            521 => '521 Web Server Is Down (Cloudflare)',
            522 => '522 Connection Timed Out (Cloudflare)',
            523 => '523 Origin Is Unreachable (Cloudflare)',
            524 => '524 A Timeout Occurred (Cloudflare)',
            525 => '525 SSL Handshake Failed (Cloudflare)',
            526 => '526 Invalid SSL Certificate (Cloudflare)',
            530 => '530 Cloudflare Tunnel / Origin Error',
        ];
        return $messages[$code] ?? "HTTP Error {$code}";
    }
}
