<?php
/**
 * Backend API untuk Export Laporan Monitoring Status Website ke Microsoft Excel (.xlsx)
 * Menghasilkan file OpenXML Spreadsheet (.xlsx) murni yang kompatibel dengan
 * Microsoft Excel, WPS Office, LibreOffice Calc, dan Google Sheets.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Auth.php';

// Proteksi: Ekspor XLSX hanya diizinkan untuk administrator yang telah login
Auth::requireLogin();

// Helper deteksi folder Downloads pengguna
function getUserDownloadsFolder(): string {
    $userProfile = getenv('USERPROFILE');
    if ($userProfile && is_dir($userProfile . '/Downloads')) {
        return str_replace('\\', '/', $userProfile . '/Downloads');
    }
    if (is_dir('C:/Users/rino/Downloads')) {
        return 'C:/Users/rino/Downloads';
    }
    return '';
}

// 1. Tangani aksi khusus: Membuka file langsung di Excel
if (isset($_GET['action']) && $_GET['action'] === 'open') {
    header('Content-Type: application/json; charset=utf-8');
    $filename = basename($_GET['file'] ?? '');
    $userDownloads = getUserDownloadsFolder();
    $targetPath = $userDownloads . '/' . $filename;

    if (!$filename || !file_exists($targetPath)) {
        echo json_encode(['success' => false, 'message' => 'Berkas Excel tidak ditemukan di folder Downloads: ' . $filename]);
        exit;
    }

    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        pclose(popen('start "" ' . escapeshellarg(str_replace('/', '\\', $targetPath)), 'r'));
    }

    echo json_encode(['success' => true, 'message' => 'Membuka berkas di Microsoft Excel...']);
    exit;
}

// 2. Tangani aksi khusus: Membuka folder Downloads di File Explorer
if (isset($_GET['action']) && $_GET['action'] === 'folder') {
    header('Content-Type: application/json; charset=utf-8');
    $filename = basename($_GET['file'] ?? '');
    $userDownloads = getUserDownloadsFolder();
    $targetPath = $userDownloads . '/' . $filename;

    if (!$filename || !file_exists($targetPath)) {
        echo json_encode(['success' => false, 'message' => 'Berkas Excel tidak ditemukan di folder Downloads']);
        exit;
    }

    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        pclose(popen('explorer.exe /select,' . escapeshellarg(str_replace('/', '\\', $targetPath)), 'r'));
    }

    echo json_encode(['success' => true, 'message' => 'Membuka folder Downloads di File Explorer...']);
    exit;
}

// Ambil parameter filter
$categoryId = isset($_GET['category']) ? trim($_GET['category']) : 'all';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$action = isset($_GET['action']) ? trim($_GET['action']) : 'download';

$db = Database::getConnection();

// 3. Ambil data kategori untuk mapping nama tab/fakultas
$catStmt = $db->query("SELECT id, name, code FROM categories ORDER BY sort_order ASC, name ASC");
$allCategories = $catStmt->fetchAll();
$categoryMap = [];
foreach ($allCategories as $c) {
    $categoryMap[$c['id']] = $c['name'];
}

$scopeName = 'Semua Fakultas / Unit Kerja';
if ($categoryId !== 'all' && is_numeric($categoryId)) {
    if (isset($categoryMap[(int)$categoryId])) {
        $scopeName = $categoryMap[(int)$categoryId];
    }
}

// 4. Query data website sesuai filter
$sql = "
    SELECT w.*, c.name AS category_name, c.code AS category_code 
    FROM websites w
    JOIN categories c ON w.category_id = c.id
    WHERE 1=1
";
$params = [];

if ($categoryId !== 'all' && is_numeric($categoryId)) {
    $sql .= " AND w.category_id = ?";
    $params[] = (int)$categoryId;
}

if ($statusFilter === 'active' || $statusFilter === 'Aktif') {
    $sql .= " AND w.last_status = 'Aktif'";
} elseif ($statusFilter === 'error' || $statusFilter === 'Error') {
    $sql .= " AND (w.last_status != 'Aktif' OR w.last_status IS NULL)";
}

if (!empty($searchQuery)) {
    $sql .= " AND (w.name LIKE ? OR w.url LIKE ? OR c.name LIKE ? OR w.level LIKE ?)";
    $like = '%' . $searchQuery . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql .= " ORDER BY c.sort_order ASC, c.name ASC, w.id ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$websites = $stmt->fetchAll();

// 5. Hitung metrik KPI
$totalWeb = count($websites);
$activeWeb = 0;
$totalLatency = 0;
$latencyCount = 0;

foreach ($websites as $w) {
    if ($w['last_status'] === 'Aktif') {
        $activeWeb++;
    }
    if ($w['last_response_time'] !== null && is_numeric($w['last_response_time'])) {
        $totalLatency += (int)$w['last_response_time'];
        $latencyCount++;
    }
}
$errorWeb = $totalWeb - $activeWeb;
$avgLatency = $latencyCount > 0 ? round($totalLatency / $latencyCount) : 0;

$now = new DateTime('now', new DateTimeZone('Asia/Pontianak'));
$dateFormatted = $now->format('d F Y, H:i:s') . ' WIB';
$fileDateStamp = $now->format('Y-m-d');

// Helper XML sanitizer
function xmlEscape(?string $str): string {
    if ($str === null) return '';
    $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string)$str);
    return htmlspecialchars($clean, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

// Helper untuk konversi index kolom numerik (1 => A, 2 => B, dll.)
function colName(int $idx): string {
    $letters = '';
    while ($idx > 0) {
        $remainder = ($idx - 1) % 26;
        $letters = chr(65 + $remainder) . $letters;
        $idx = (int)(($idx - $remainder) / 26);
    }
    return $letters;
}

// 6. Susun XML untuk Sheet 1 (Status Website)
$sheet1Data = '<sheetData>';

// Header Row
$headers = [
    'No',
    'Fakultas / Unit Kerja',
    'Jenjang',
    'Nama Program Studi / Website',
    'URL Website',
    'Status Pemantauan',
    'Kode Status HTTP',
    'Respon (ms)',
    'Waktu Terakhir Dicek',
    'Keterangan / Catatan'
];

$sheet1Data .= '<row r="1" ht="28" customHeight="1">';
foreach ($headers as $colIdx => $h) {
    $cellRef = colName($colIdx + 1) . '1';
    $sheet1Data .= '<c r="' . $cellRef . '" s="1" t="inlineStr"><is><t>' . xmlEscape($h) . '</t></is></c>';
}
$sheet1Data .= '</row>';

// Data Rows
$rowNum = 2;
foreach ($websites as $idx => $w) {
    $no = $idx + 1;
    $fakultas = $w['category_name'] ?? '-';
    $jenjang = $w['level'] ?? 'S1';
    $nama = $w['name'] ?? '-';
    $url = $w['url'] ?? '';
    $status = $w['last_status'] ?? 'Belum Dicek';
    $httpCode = $w['last_http_code'] ? (string)$w['last_http_code'] : 'ERR / 0';
    $latency = ($w['last_response_time'] !== null && is_numeric($w['last_response_time'])) ? (int)$w['last_response_time'] : '-';
    $checkedAt = $w['last_checked_at'] ?? '-';
    $notes = $w['notes'] ?? '';

    // Style sel: status badge (3 = aktif/hijau, 4 = error/merah, 2 = biasa)
    $statusStyle = ($status === 'Aktif') ? 3 : 4;

    $sheet1Data .= '<row r="' . $rowNum . '" ht="' . ($status === 'Aktif' ? '22' : '22') . '" customHeight="1">';
    $sheet1Data .= '<c r="A' . $rowNum . '" s="5"><v>' . $no . '</v></c>';
    $sheet1Data .= '<c r="B' . $rowNum . '" s="2" t="inlineStr"><is><t>' . xmlEscape($fakultas) . '</t></is></c>';
    $sheet1Data .= '<c r="C' . $rowNum . '" s="5" t="inlineStr"><is><t>' . xmlEscape($jenjang) . '</t></is></c>';
    $sheet1Data .= '<c r="D' . $rowNum . '" s="2" t="inlineStr"><is><t>' . xmlEscape($nama) . '</t></is></c>';
    $sheet1Data .= '<c r="E' . $rowNum . '" s="2" t="inlineStr"><is><t>' . xmlEscape($url) . '</t></is></c>';
    $sheet1Data .= '<c r="F' . $rowNum . '" s="' . $statusStyle . '" t="inlineStr"><is><t>' . xmlEscape($status) . '</t></is></c>';
    $sheet1Data .= '<c r="G' . $rowNum . '" s="5" t="inlineStr"><is><t>' . xmlEscape($httpCode) . '</t></is></c>';
    
    if (is_numeric($latency)) {
        $sheet1Data .= '<c r="H' . $rowNum . '" s="6"><v>' . $latency . '</v></c>';
    } else {
        $sheet1Data .= '<c r="H' . $rowNum . '" s="5" t="inlineStr"><is><t>-</t></is></c>';
    }
    
    $sheet1Data .= '<c r="I' . $rowNum . '" s="5" t="inlineStr"><is><t>' . xmlEscape($checkedAt) . '</t></is></c>';
    $sheet1Data .= '<c r="J' . $rowNum . '" s="2" t="inlineStr"><is><t>' . xmlEscape($notes) . '</t></is></c>';
    $sheet1Data .= '</row>';

    $rowNum++;
}
$sheet1Data .= '</sheetData>';

$sheet1Xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <cols>
    <col min="1" max="1" width="7" customWidth="1"/>
    <col min="2" max="2" width="34" customWidth="1"/>
    <col min="3" max="3" width="12" customWidth="1"/>
    <col min="4" max="4" width="38" customWidth="1"/>
    <col min="5" max="5" width="46" customWidth="1"/>
    <col min="6" max="6" width="18" customWidth="1"/>
    <col min="7" max="7" width="18" customWidth="1"/>
    <col min="8" max="8" width="15" customWidth="1"/>
    <col min="9" max="9" width="22" customWidth="1"/>
    <col min="10" max="10" width="32" customWidth="1"/>
  </cols>
  ' . $sheet1Data . '
</worksheet>';

// 7. Susun XML untuk Sheet 2 (Ringkasan Metrik KPI)
$kpiRows = [
    ['Parameter Laporan', 'Informasi Sistem Monitoring'],
    ['Aplikasi', 'Monitoring Status Website - Universitas Tanjungpura (UNTAN)'],
    ['Cakupan Laporan', $scopeName],
    ['Waktu Pengunduhan Data', $dateFormatted],
    ['Total Website Terdaftar', (string)$totalWeb . ' website'],
    ['Website Online / Aktif (HTTP 200)', (string)$activeWeb . ' website'],
    ['Website Gangguan / Error (HTTP 4xx/5xx/ERR)', (string)$errorWeb . ' website'],
    ['Tingkat Ketersediaan (Availability)', ($totalWeb > 0 ? round(($activeWeb / $totalWeb) * 100, 1) : 0) . ' %'],
    ['Rata-rata Waktu Respon Server', (string)$avgLatency . ' ms']
];

$sheet2Data = '<sheetData>';
foreach ($kpiRows as $kIdx => $row) {
    $rNum = $kIdx + 1;
    $isHeader = ($kIdx === 0);
    $style = $isHeader ? 1 : 2;
    $sheet2Data .= '<row r="' . $rNum . '" ht="' . ($isHeader ? '28' : '22') . '" customHeight="1">';
    $sheet2Data .= '<c r="A' . $rNum . '" s="' . $style . '" t="inlineStr"><is><t>' . xmlEscape($row[0]) . '</t></is></c>';
    $sheet2Data .= '<c r="B' . $rNum . '" s="' . $style . '" t="inlineStr"><is><t>' . xmlEscape($row[1]) . '</t></is></c>';
    $sheet2Data .= '</row>';
}
$sheet2Data .= '</sheetData>';

$sheet2Xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <cols>
    <col min="1" max="1" width="36" customWidth="1"/>
    <col min="2" max="2" width="60" customWidth="1"/>
  </cols>
  ' . $sheet2Data . '
</worksheet>';

// 8. Style definitions (Fonts, Fills, Borders, Alignments)
$stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="5">
    <font><name val="Segoe UI"/><sz val="11"/><color rgb="FF1E293B"/></font>
    <font><b/><name val="Segoe UI"/><sz val="11"/><color rgb="FFFFFFFF"/></font>
    <font><b/><name val="Segoe UI"/><sz val="11"/><color rgb="FF15803D"/></font>
    <font><b/><name val="Segoe UI"/><sz val="11"/><color rgb="FFB91C1C"/></font>
    <font><name val="Segoe UI"/><sz val="11"/><color rgb="FF475569"/></font>
  </fonts>
  <fills count="5">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF0F172A"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFDCFCE7"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFFEE2E2"/></patternFill></fill>
  </fills>
  <borders count="2">
    <border><left/><right/><top/><bottom/></border>
    <border>
      <left style="thin"><color rgb="FFE2E8F0"/></left>
      <right style="thin"><color rgb="FFE2E8F0"/></right>
      <top style="thin"><color rgb="FFE2E8F0"/></top>
      <bottom style="thin"><color rgb="FFE2E8F0"/></bottom>
    </border>
  </borders>
  <cellStyleXfs count="1">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
  </cellStyleXfs>
  <cellXfs count="7">
    <!-- 0: Default -->
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
    <!-- 1: Header Navy -->
    <xf numFmtId="0" fontId="1" fillId="2" borderId="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">
      <alignment horizontal="center" vertical="center" wrapText="1"/>
    </xf>
    <!-- 2: Regular Left Bordered -->
    <xf numFmtId="0" fontId="0" fillId="0" borderId="1" applyBorder="1" applyAlignment="1">
      <alignment vertical="center"/>
    </xf>
    <!-- 3: Aktif Green Badge -->
    <xf numFmtId="0" fontId="2" fillId="3" borderId="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">
      <alignment horizontal="center" vertical="center"/>
    </xf>
    <!-- 4: Error Red Badge -->
    <xf numFmtId="0" fontId="3" fillId="4" borderId="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">
      <alignment horizontal="center" vertical="center"/>
    </xf>
    <!-- 5: Regular Center Bordered -->
    <xf numFmtId="0" fontId="0" fillId="0" borderId="1" applyBorder="1" applyAlignment="1">
      <alignment horizontal="center" vertical="center"/>
    </xf>
    <!-- 6: Regular Right / Number Bordered -->
    <xf numFmtId="0" fontId="0" fillId="0" borderId="1" applyBorder="1" applyAlignment="1">
      <alignment horizontal="right" vertical="center"/>
    </xf>
  </cellXfs>
</styleSheet>';

$contentTypesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>';

$relsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';

$workbookRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>';

$workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Status Website" sheetId="1" r:id="rId1"/>
    <sheet name="Ringkasan KPI" sheetId="2" r:id="rId2"/>
  </sheets>
</workbook>';

// 9. Kemas ke dalam berkas ZIP (.xlsx)
$tempZipFile = tempnam(sys_get_temp_dir(), 'untan_xlsx_');
$zip = new ZipArchive();
if ($zip->open($tempZipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    echo "Gagal mengemas berkas Excel";
    exit;
}

$zip->addFromString('[Content_Types].xml', $contentTypesXml);
$zip->addFromString('_rels/.rels', $relsXml);
$zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRelsXml);
$zip->addFromString('xl/workbook.xml', $workbookXml);
$zip->addFromString('xl/styles.xml', $stylesXml);
$zip->addFromString('xl/worksheets/sheet1.xml', $sheet1Xml);
$zip->addFromString('xl/worksheets/sheet2.xml', $sheet2Xml);
$zip->close();

// 10. Tentukan nama file yang rapi dan aman
$safeScope = preg_replace('/[^a-zA-Z0-9]/', '_', $scopeName);
$safeScope = preg_replace('/_+/', '_', $safeScope);
$safeScope = trim($safeScope, '_');
if (strlen($safeScope) > 30) {
    $safeScope = substr($safeScope, 0, 30);
}
$filename = "Monitoring_Website_UNTAN_{$safeScope}_{$fileDateStamp}.xlsx";

// 11. Simpan salinan langsung ke folder Downloads komputer pengguna
$userDownloads = getUserDownloadsFolder();
$savedPath = '';
if ($userDownloads && is_dir($userDownloads)) {
    $savedPath = $userDownloads . '/' . $filename;
    @copy($tempZipFile, $savedPath);
}

// 12. Jika dipanggil dengan mode JSON API (action=generate)
if ($action === 'generate') {
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'filename' => $filename,
        'saved_path' => $savedPath ? str_replace('/', '\\', $savedPath) : '',
        'size' => filesize($tempZipFile),
        'total_websites' => $totalWeb,
        'scope' => $scopeName,
        'download_url' => 'api/export.php?category=' . urlencode($categoryId) . '&status=' . urlencode($statusFilter) . '&search=' . urlencode($searchQuery)
    ]);
    @unlink($tempZipFile);
    exit;
}

// 13. Kirim HTTP Headers resmi untuk unduhan file Excel via browser
if (ob_get_level()) ob_end_clean();

header('Content-Description: File Transfer');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Length: ' . filesize($tempZipFile));

readfile($tempZipFile);
@unlink($tempZipFile);
exit;
