<?php
/**
 * Dashboard Monitoring Status Website
 * Antarmuka Utama dengan Pengelompokan Fakultas/Prodi, Indikator Visual, dan Manajemen CRUD
 */
require_once __DIR__ . '/classes/Auth.php';
// Inisialisasi koneksi database & auto-migration/seed jika belum ada
$db = Database::getConnection();
$isLoggedIn = Auth::check();
$currentUser = Auth::user();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Status Website - Universitas Tanjungpura (UNTAN)</title>
    
    <!-- Google Fonts: Plus Jakarta Sans & JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@500;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5.3 CSS & Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">

    <!-- Status Login untuk Javascript -->
    <script>
        const IS_LOGGED_IN = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;
        const CURRENT_USER = <?php echo json_encode($currentUser); ?>;
    </script>
</head>
<body>

    <!-- Header Navbar -->
    <header class="app-header py-3 sticky-top">
        <div class="container-fluid px-xxl-5 px-xl-4 px-3">
            <div class="d-flex align-items-center justify-content-between gap-3">
                <!-- Brand Title -->
                <div class="d-flex align-items-center gap-3">
                    <div class="brand-badge">
                        <i class="bi bi-activity fs-4"></i>
                    </div>
                    <div>
                        <h1 class="h5 mb-0 fw-bold text-dark">Monitoring Status Website - UNTAN</h1>
                        <span class="text-muted small">Pemantauan Ketersediaan Layanan Web Universitas Tanjungpura</span>
                    </div>
                </div>

                <!-- Grouped Action Buttons (Selalu Rapi Berjajar di Kanan) -->
                <div class="d-flex align-items-center gap-2 flex-nowrap">
                    <?php if ($isLoggedIn): ?>
                        <!-- Tombol Export XLSX (Hanya Admin) -->
                        <button type="button" class="btn btn-header-action btn-outline-success" id="btnExportXlsx" onclick="exportToXlsx()" title="Export data status website ke Excel (.xlsx)">
                            <i class="bi bi-file-earmark-excel-fill text-success"></i>
                            <span>Export XLSX</span>
                        </button>
                    <?php endif; ?>

                    <!-- Tombol Cek Semua (Semua Pengunjung) -->
                    <button type="button" class="btn btn-header-action btn-outline-primary" id="btnCheckAll" onclick="checkAllWebsites()">
                        <i class="bi bi-arrow-clockwise"></i>
                        <span>Cek Semua Sekarang</span>
                    </button>

                    <!-- Tombol Panduan Status (Toggle Sidebar Kanan) -->
                    <button type="button" class="btn btn-header-action btn-light border text-dark" id="btnOpenGuide" onclick="toggleGuideSidebar()" title="Buka/Tutup Panduan Status di Sisi Kanan">
                        <i class="bi bi-book-half text-primary"></i>
                        <span>Panduan</span>
                    </button>
                    
                    <?php if ($isLoggedIn): ?>
                        <!-- Tombol Pengaturan Master Data (Hanya Admin) -->
                        <button type="button" class="btn btn-header-action btn-light border text-dark" onclick="openMasterSettingsModal()">
                            <i class="bi bi-gear-fill text-secondary"></i>
                            <span>Pengaturan</span>
                        </button>

                        <!-- Tombol Tambah Website (Hanya Admin) -->
                        <button type="button" class="btn btn-header-action btn-primary shadow-sm" onclick="openAddModal()">
                            <i class="bi bi-plus-lg"></i>
                            <span>Tambah Website</span>
                        </button>

                        <!-- Profil Admin & Tombol Logout -->
                        <div class="d-flex align-items-center gap-1 ps-1 border-start">
                            <button type="button" class="btn btn-header-action btn-outline-danger" onclick="handleLogout()" title="Keluar dari sesi Administrator">
                                <i class="bi bi-box-arrow-right"></i>
                                <span>Logout</span>
                            </button>
                        </div>
                    <?php else: ?>
                        <!-- Tombol Login Admin (Saat Belum Login) -->
                        <button type="button" class="btn btn-header-action btn-primary shadow-sm" onclick="openLoginModal()" title="Masuk sebagai Administrator">
                            <i class="bi bi-box-arrow-in-right"></i>
                            <span>Login Admin</span>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container-fluid px-xxl-5 px-xl-4 px-3 py-4">

        <!-- 1. Ringkasan Metrik KPI (Dapat Diklik untuk Filter Cepat) -->
        <section class="row g-3 mb-4" aria-label="Statistik Monitoring">
            <div class="col-6 col-lg-3">
                <div class="metric-card metric-card-clickable d-flex align-items-center gap-3" id="cardStatTotal" role="button" tabindex="0" title="Klik untuk menampilkan semua website" onclick="filterByMetricCard('all')">
                    <div class="metric-icon-box metric-icon-total">
                        <i class="bi bi-globe2"></i>
                    </div>
                    <div class="flex-grow-1">
                        <span class="text-muted small fw-semibold d-block">TOTAL WEBSITE</span>
                        <span class="fs-4 fw-bold text-dark" id="statTotal">-</span>
                    </div>
                    <span class="metric-filter-badge"><i class="bi bi-funnel-fill"></i> Filter</span>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="metric-card metric-card-clickable d-flex align-items-center gap-3" id="cardStatActive" role="button" tabindex="0" title="Klik untuk memfilter website Aktif (200)" onclick="filterByMetricCard('active')">
                    <div class="metric-icon-box metric-icon-active">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                    <div class="flex-grow-1">
                        <span class="text-muted small fw-semibold d-block">WEBSITE AKTIF (200)</span>
                        <span class="fs-4 fw-bold text-success" id="statActive">-</span>
                    </div>
                    <span class="metric-filter-badge"><i class="bi bi-funnel-fill"></i> Filter</span>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="metric-card metric-card-clickable d-flex align-items-center gap-3" id="cardStatError" role="button" tabindex="0" title="Klik untuk menampilkan semua website yang Error Server" onclick="filterByMetricCard('error')">
                    <div class="metric-icon-box metric-icon-error">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                    </div>
                    <div class="flex-grow-1">
                        <span class="text-muted small fw-semibold d-block">ERROR SERVER (4xx/5xx)</span>
                        <span class="fs-4 fw-bold text-danger" id="statError">-</span>
                    </div>
                    <span class="metric-filter-badge"><i class="bi bi-funnel-fill"></i> Filter</span>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="metric-card d-flex align-items-center gap-3" id="cardStatLatency">
                    <div class="metric-icon-box metric-icon-latency">
                        <i class="bi bi-lightning-charge-fill"></i>
                    </div>
                    <div class="flex-grow-1">
                        <span class="text-muted small fw-semibold d-block">RATA-RATA RESPON</span>
                        <span class="fs-4 fw-bold text-primary" id="statAvgLatency">-</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- 2. Filter & Search Toolbar + Jam Realtime & Auto Refresh -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
            <div class="d-flex flex-wrap align-items-center gap-2 flex-grow-1" style="max-width: 650px;">
                <div class="input-group shadow-sm" style="max-width: 300px;">
                    <span class="input-group-text bg-white border-end-0 text-muted">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" class="form-control border-start-0 ps-0" id="searchInput" placeholder="Cari Fakultas, Prodi, atau URL...">
                </div>

                <select class="form-select shadow-sm" id="filterCategory" style="max-width: 200px;">
                    <option value="all">Semua Fakultas / Unit</option>
                </select>

                <select class="form-select shadow-sm" id="filterStatus" style="max-width: 150px;">
                    <option value="all">Semua Status</option>
                    <option value="active">Hanya Aktif</option>
                    <option value="error">Hanya Error Server</option>
                </select>
            </div>

            <!-- Jam Realtime, Auto Refresh, & Terakhir Diperiksa -->
            <div class="d-flex align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2 px-3 py-1 bg-white rounded-pill border shadow-sm small">
                    <i class="bi bi-clock-history text-primary"></i>
                    <span id="liveClock" class="fw-semibold text-dark">--:--:-- WIB</span>
                </div>

                <div class="form-check form-switch mb-0 bg-white px-3 py-1 rounded-pill border shadow-sm small d-flex align-items-center gap-2">
                    <input class="form-check-input ms-0 me-1 mt-0" type="checkbox" id="autoRefreshSwitch">
                    <label class="form-check-label text-secondary mb-0" for="autoRefreshSwitch">Auto-refresh (60s)</label>
                </div>

                <div class="text-muted small ps-1">
                    <i class="bi bi-arrow-repeat me-1 text-secondary"></i> Terakhir dicek: <span id="statLastChecked" class="fw-semibold text-dark">-</span>
                </div>
            </div>
        </div>

        <!-- Progress Bar saat pemeriksaan berlangsung -->
        <div class="checker-progress mb-3" id="checkerProgressContainer" style="display: none;">
            <div class="checker-progress-bar" id="checkerProgressBar" style="width: 0%;"></div>
        </div>

        <!-- 3.5 Navigasi Tab Kategori / Fakultas (Mencegah Scroll Panjang) -->
        <div class="category-tabs-wrapper mb-3" id="categoryTabsWrapper">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary text-white rounded-pill px-2 py-1"><i class="bi bi-folder2-open me-1"></i>Kategori</span>
                    <span class="fw-bold text-dark small">Pilih Fakultas / Unit Kerja:</span>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div class="small text-muted" id="tabItemsSummary">
                        Menampilkan semua website
                    </div>
                    <?php if ($isLoggedIn): ?>
                    <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3 py-1 fw-semibold d-inline-flex align-items-center gap-1 shadow-sm" onclick="exportToXlsx()" title="Download Laporan Status ke Excel (.xlsx)">
                        <i class="bi bi-file-earmark-excel-fill"></i>
                        <span>Export XLSX</span>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <ul class="nav nav-pills custom-category-tabs" id="categoryTabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" data-category-id="all" type="button">
                        <i class="bi bi-grid-fill"></i>
                        <span>Semua Fakultas / Unit</span>
                        <span class="tab-count-badge" id="badgeCountAll">0</span>
                    </button>
                </li>
            </ul>
        </div>

        <!-- 4. Area Konten Utama: Tabel Monitoring (Kiri) & Panduan Status (Kanan) -->
        <div class="row g-4 align-items-start" id="monitoringMainRow">
            <!-- Kolom Kiri: Tabel Utama Monitoring -->
            <div class="col-12 col-xl-9 col-lg-8" id="tableColumn">
                <div class="monitoring-card">
                    <div class="table-responsive">
                        <table class="table table-monitoring align-middle" id="monitoringTable">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width: 50px;">NO</th>
                                    <th style="width: 280px;">FAKULTAS / PRODI</th>
                                    <th style="min-width: 240px;">URL</th>
                                    <th style="width: 140px;">STATUS</th>
                                    <th style="width: 110px;">KODE HTTP</th>
                                    <th style="width: 130px;">RESPON (MS)</th>
                                    <?php if ($isLoggedIn): ?>
                                    <th class="text-end" style="width: 120px;" id="thAction">AKSI</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody id="monitoringTableBody">
                                <tr>
                                    <td colspan="<?php echo $isLoggedIn ? 7 : 6; ?>" class="text-center py-5 text-muted">
                                        <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                        Memuat data monitoring...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Kolom Kanan: Panduan Status HTTP & Waktu Respon (Sticky Sidebar) -->
            <div class="col-12 col-xl-3 col-lg-4" id="guideSidebarColumn">
                <aside class="guide-sidebar-card sticky-sidebar" aria-label="Panduan Kode Status">
                    <!-- Header Sidebar Panduan -->
                    <div class="d-flex align-items-center justify-content-between pb-3 mb-3 border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <div class="sidebar-icon-box">
                                <i class="bi bi-book-half text-primary"></i>
                            </div>
                            <div>
                                <h2 class="h6 fw-bold mb-0 text-dark">Panduan Status</h2>
                                <span class="text-muted" style="font-size: 0.75rem;">Kamus Kode &amp; Respon</span>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-light border text-secondary rounded-pill px-2 py-1" onclick="toggleGuideSidebar()" title="Sembunyikan Panduan">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>

                    <!-- Penjelasan Singkat Kode 200 (OK) -->
                    <div class="alert alert-success border-success-subtle p-3 mb-3 rounded-3 shadow-2xs">
                        <div class="d-flex align-items-start gap-2">
                            <i class="bi bi-check-circle-fill text-success fs-5 flex-shrink-0 mt-0"></i>
                            <div>
                                <span class="fw-bold text-success d-block small mb-1">Kode 200 (OK) = Normal</span>
                                <p class="small text-dark mb-0 lh-sm" style="font-size: 0.8rem;">
                                    Permintaan berhasil. Server sehat, online, dan halaman dapat dimuat tanpa gangguan.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Kumpulan Kartu Status HTTP Vertikal Compact -->
                    <div class="guide-status-list mb-3">
                        <span class="text-muted text-uppercase fw-bold d-block mb-2" style="font-size: 0.7rem; letter-spacing: 0.5px;">Arti Kode HTTP</span>
                        
                        <div class="guide-compact-item mb-2">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <span class="guide-badge badge-http-200">200 OK</span>
                                <span class="badge bg-success-subtle text-success border border-success-subtle">Aktif / Berhasil</span>
                            </div>
                            <p class="text-muted small mb-0 lh-sm">Website normal, data terkirim lengkap, dan dapat dibuka lancar.</p>
                        </div>

                        <div class="guide-compact-item mb-2">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <span class="guide-badge badge-http-300">301 / 302</span>
                                <span class="badge bg-info-subtle text-info border border-info-subtle">Redirect</span>
                            </div>
                            <p class="text-muted small mb-0 lh-sm">Dialihkan otomatis (misal HTTP ke HTTPS atau SSO).</p>
                        </div>

                        <div class="guide-compact-item mb-2">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <span class="guide-badge badge-http-400">403 Forbidden</span>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Akses Dibatasi</span>
                            </div>
                            <p class="text-muted small mb-0 lh-sm">Akses ditolak (butuh hak login khusus, token, atau firewall).</p>
                        </div>

                        <div class="guide-compact-item mb-2">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <span class="guide-badge badge-http-400">404 Not Found</span>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Tidak Ada</span>
                            </div>
                            <p class="text-muted small mb-0 lh-sm">Alamat URL atau berkas halaman tidak ditemukan / dihapus.</p>
                        </div>

                        <div class="guide-compact-item mb-2">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <span class="guide-badge badge-http-500">500 / 502 / 530</span>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Error Server</span>
                            </div>
                            <p class="text-muted small mb-0 lh-sm">Kesalahan skrip internal, database error, atau Bad Gateway.</p>
                        </div>

                        <div class="guide-compact-item mb-2">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <span class="guide-badge badge-http-0">ERR / Timeout</span>
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Down / Timeout</span>
                            </div>
                            <p class="text-muted small mb-0 lh-sm">Server tidak merespon (&gt; 12 detik) atau domain mati.</p>
                        </div>
                    </div>

                    <!-- Keterangan Satuan Respon (ms) -->
                    <div class="p-3 bg-light rounded-3 border">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="bi bi-speedometer2 text-primary"></i>
                            <span class="fw-bold small text-dark">Waktu Respon (ms)</span>
                        </div>
                        <p class="text-muted small mb-2 lh-sm" style="font-size: 0.78rem;">
                            Waktu yang dibutuhkan server untuk merespon dalam milidetik (1s = 1.000ms).
                        </p>
                        <div class="d-flex flex-column gap-2 small">
                            <div class="d-flex align-items-center justify-content-between">
                                <span class="badge bg-success-subtle text-success fw-bold px-2 py-1">&lt; 300 ms</span>
                                <span class="text-muted">Cepat (Sangat Baik)</span>
                            </div>
                            <div class="d-flex align-items-center justify-content-between">
                                <span class="badge bg-warning-subtle text-warning fw-bold px-2 py-1">300 - 800 ms</span>
                                <span class="text-muted">Sedang (Standar Wajar)</span>
                            </div>
                            <div class="d-flex align-items-center justify-content-between">
                                <span class="badge bg-danger-subtle text-danger fw-bold px-2 py-1">&gt; 800 ms</span>
                                <span class="text-muted">Lambat (Perlu Perhatian)</span>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </main>

    <!-- Modal: Login Administrator -->
    <div class="modal fade" id="loginModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <!-- Header Modal Login -->
                <div class="modal-header border-0 pb-0 pt-4 px-4 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <div class="brand-badge" style="width: 42px; height: 42px; font-size: 1.25rem;">
                            <i class="bi bi-shield-lock-fill text-primary"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0 text-dark">Login Admin</h5>
                            <span class="text-muted small">Monitoring Status Web UNTAN</span>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>

                <div class="modal-body px-4 pt-3 pb-4">
                    <p class="text-secondary small mb-3">
                        Masuk sebagai administrator untuk mengelola data website, mengakses menu pengaturan, dan mendownload laporan Excel.
                    </p>

                    <!-- Alert Akun Default -->
                    <div class="alert alert-primary border-primary-subtle d-flex align-items-start gap-2 p-2 mb-3 rounded-3 small">
                        <i class="bi bi-info-circle-fill text-primary mt-1 flex-shrink-0"></i>
                        <div class="lh-sm">
                            <span class="fw-bold d-block text-primary">Akun Default Sistem:</span>
                            <span class="text-dark">Username: <strong>admin</strong> | Password: <strong>admin123</strong></span>
                        </div>
                    </div>

                    <form id="loginForm" onsubmit="handleLoginSubmit(event)">
                        <!-- Username -->
                        <div class="mb-3">
                            <label for="loginUsername" class="form-label small fw-semibold text-secondary">Username</label>
                            <div class="input-group shadow-sm">
                                <span class="input-group-text bg-white border-end-0 text-muted">
                                    <i class="bi bi-person-fill"></i>
                                </span>
                                <input type="text" class="form-control border-start-0" id="loginUsername" name="username" placeholder="Masukkan username admin" required autocomplete="username">
                            </div>
                        </div>

                        <!-- Password -->
                        <div class="mb-3">
                            <label for="loginPassword" class="form-label small fw-semibold text-secondary">Password</label>
                            <div class="input-group shadow-sm">
                                <span class="input-group-text bg-white border-end-0 text-muted">
                                    <i class="bi bi-lock-fill"></i>
                                </span>
                                <input type="password" class="form-control border-start-0 border-end-0" id="loginPassword" name="password" placeholder="Masukkan password" required autocomplete="current-password">
                                <button type="button" class="btn btn-white bg-white border border-start-0 text-muted" id="btnTogglePassword" onclick="togglePasswordVisibility()" title="Lihat/Sembunyikan Password">
                                    <i class="bi bi-eye" id="togglePasswordIcon"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary fw-semibold py-2 rounded-3 shadow-sm d-flex align-items-center justify-content-center gap-2" id="btnLoginSubmit">
                                <i class="bi bi-box-arrow-in-right fs-5"></i>
                                <span>Masuk ke Akun</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Tambah & Edit Website -->
    <div class="modal fade" id="websiteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="websiteForm">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="websiteModalTitle">Tambah Website Monitoring</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="websiteId" name="id">

                        <!-- Pilihan Fakultas / Unit -->
                        <div class="mb-3">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <label class="form-label fw-semibold small text-muted mb-0">Fakultas / Unit Kerja <span class="text-danger">*</span></label>
                                <button type="button" class="btn btn-sm btn-link p-0 text-primary text-decoration-none small fw-semibold" onclick="openMasterSettingsModal('tab-faculty')">
                                    <i class="bi bi-gear-fill me-1"></i>Kelola Fakultas
                                </button>
                            </div>
                            <select class="form-select" id="websiteCategory" name="category_id" required>
                                <option value="">-- Pilih Fakultas / Unit --</option>
                            </select>
                        </div>

                        <!-- Input Fakultas Baru jika memilih opsi tambah baru -->
                        <div class="mb-3" id="newCategoryInputGroup" style="display: none;">
                            <label class="form-label fw-semibold small text-primary">Nama Fakultas / Unit Baru</label>
                            <input type="text" class="form-control" id="newCategoryName" placeholder="Contoh: Fakultas Ilmu Komputer">
                        </div>

                        <!-- Jenjang & Nama Program Studi -->
                        <div class="row g-2 mb-3">
                            <div class="col-4">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <label class="form-label fw-semibold small text-muted mb-0">Jenjang</label>
                                    <button type="button" class="btn btn-sm btn-link p-0 text-primary text-decoration-none small fw-semibold" onclick="openMasterSettingsModal('tab-level')">
                                        <i class="bi bi-gear-fill me-1"></i>Kelola
                                    </button>
                                </div>
                                <select class="form-select" id="websiteLevel" name="level">
                                    <option value="S1">S1</option>
                                    <option value="S2">S2</option>
                                    <option value="S3">S3</option>
                                    <option value="D3">D3</option>
                                    <option value="D4">D4</option>
                                    <option value="Profesi">Profesi</option>
                                    <option value="Unit">Unit/Layanan</option>
                                    <option value="Sistem">Sistem</option>
                                </select>
                            </div>
                            <div class="col-8">
                                <label class="form-label fw-semibold small text-muted mb-1">Nama Program Studi / Website <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="websiteName" name="name" placeholder="Contoh: S1 - Ilmu Hukum" required>
                            </div>
                        </div>

                        <!-- URL Website -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-muted">URL Lengkap Website <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="url" class="form-control" id="websiteUrl" name="url" placeholder="https://law.ui.ac.id" required>
                                <button class="btn btn-outline-secondary" type="button" id="btnTestUrl" title="Uji apakah URL dapat diakses">
                                    <i class="bi bi-broadcast me-1"></i> Uji URL
                                </button>
                            </div>
                            <div class="form-text small">Harus diawali dengan <code>http://</code> atau <code>https://</code>.</div>
                        </div>

                        <!-- Hasil Uji Koneksi URL -->
                        <div id="urlTestResultBox" class="url-test-result-box mb-3"></div>

                        <!-- Catatan Tambahan (Opsional) -->
                        <div class="mb-2">
                            <label class="form-label fw-semibold small text-muted">Keterangan / Catatan Tambahan</label>
                            <input type="text" class="form-control" id="websiteNotes" name="notes" placeholder="Opsional: Server Pusat, Cloudflare, dll.">
                        </div>
                    </div>
                    <div class="modal-footer bg-light rounded-bottom-4">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4" id="btnSubmitWebsite">Simpan Data</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Konfirmasi Hapus -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-body text-center p-4">
                    <div class="text-danger mb-3">
                        <i class="bi bi-trash3-fill fs-1"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Hapus Website?</h5>
                    <p class="text-muted small mb-4">
                        Apakah Anda yakin ingin menghapus <strong id="deleteTargetName">website ini</strong> dari daftar pemantauan? Tindakan ini tidak dapat dibatalkan.
                    </p>
                    <div class="d-flex justify-content-center gap-2">
                        <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                        <button type="button" class="btn btn-danger rounded-pill px-3" id="btnConfirmDelete" onclick="confirmDeleteWebsite()">Ya, Hapus</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Pengaturan Master Data (CRUD Fakultas & Jenjang) -->
    <div class="modal fade" id="masterSettingsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <div class="brand-badge p-2 rounded-3" style="width: 36px; height: 36px;">
                            <i class="bi bi-gear-fill fs-6"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold mb-0">Pengaturan Master Data</h5>
                            <span class="text-muted small">Kelola Fakultas/Unit Kerja & Pilihan Jenjang</span>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>

                <div class="modal-body p-0">
                    <!-- Nav Tabs -->
                    <ul class="nav nav-tabs px-3 pt-2 bg-light border-bottom" id="settingsTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active fw-semibold" id="tab-faculty-btn" data-bs-toggle="tab" data-bs-target="#tab-faculty" type="button" role="tab">
                                <i class="bi bi-diagram-3-fill me-1 text-primary"></i> Fakultas & Unit Kerja
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-semibold" id="tab-level-btn" data-bs-toggle="tab" data-bs-target="#tab-level" type="button" role="tab">
                                <i class="bi bi-tags-fill me-1 text-success"></i> Pilihan Jenjang / Level
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content p-4" id="settingsTabContent">
                        <!-- TAB 1: KELOLA FAKULTAS -->
                        <div class="tab-pane fade show active" id="tab-faculty" role="tabpanel">
                            <!-- Form Tambah / Edit Fakultas -->
                            <div class="card border mb-4 bg-light shadow-none">
                                <div class="card-body p-3">
                                    <h6 class="fw-bold mb-3 text-dark d-flex align-items-center justify-content-between" id="facultyFormTitle">
                                        <span><i class="bi bi-plus-circle me-1 text-primary"></i> Tambah Fakultas / Unit Baru</span>
                                        <button type="button" class="btn btn-sm btn-outline-secondary d-none" id="btnCancelEditFaculty" onclick="resetFacultyForm()">Batal Edit</button>
                                    </h6>
                                    <form id="facultyForm">
                                        <input type="hidden" id="facultyId">
                                        <div class="row g-2">
                                            <div class="col-12 col-md-6">
                                                <label class="form-label small fw-semibold text-muted">Nama Fakultas / Unit Kerja <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control form-control-sm" id="facultyName" placeholder="Contoh: Fakultas Teknik" required>
                                            </div>
                                            <div class="col-6 col-md-3">
                                                <label class="form-label small fw-semibold text-muted">Kode Singkatan</label>
                                                <input type="text" class="form-control form-control-sm text-uppercase" id="facultyCode" placeholder="Contoh: FT">
                                            </div>
                                            <div class="col-6 col-md-3">
                                                <label class="form-label small fw-semibold text-muted">Urutan</label>
                                                <input type="number" class="form-control form-control-sm" id="facultySortOrder" value="0" min="0">
                                            </div>
                                            <div class="col-12 text-end mt-2">
                                                <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3" id="btnSaveFaculty">
                                                    <i class="bi bi-check2 me-1"></i> Simpan Fakultas
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <!-- Tabel Daftar Fakultas -->
                            <h6 class="fw-bold mb-2 text-dark">Daftar Fakultas / Unit yang Terdaftar</h6>
                            <div class="table-responsive border rounded-3 bg-white">
                                <table class="table table-sm table-hover align-middle mb-0" id="facultyTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-3" style="width: 50px;">NO</th>
                                            <th>NAMA FAKULTAS / UNIT</th>
                                            <th style="width: 100px;">KODE</th>
                                            <th style="width: 80px;" class="text-center">URUTAN</th>
                                            <th style="width: 120px;" class="text-center">WEBSITE</th>
                                            <th style="width: 110px;" class="text-end pe-3">AKSI</th>
                                        </tr>
                                    </thead>
                                    <tbody id="facultyTableBody">
                                        <tr>
                                            <td colspan="6" class="text-center py-3 text-muted">Memuat data fakultas...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- TAB 2: KELOLA JENJANG -->
                        <div class="tab-pane fade" id="tab-level" role="tabpanel">
                            <!-- Form Tambah / Edit Jenjang -->
                            <div class="card border mb-4 bg-light shadow-none">
                                <div class="card-body p-3">
                                    <h6 class="fw-bold mb-3 text-dark d-flex align-items-center justify-content-between" id="levelFormTitle">
                                        <span><i class="bi bi-plus-circle me-1 text-success"></i> Tambah Pilihan Jenjang Baru</span>
                                        <button type="button" class="btn btn-sm btn-outline-secondary d-none" id="btnCancelEditLevel" onclick="resetLevelForm()">Batal Edit</button>
                                    </h6>
                                    <form id="levelForm">
                                        <input type="hidden" id="levelId">
                                        <div class="row g-2">
                                            <div class="col-6 col-md-3">
                                                <label class="form-label small fw-semibold text-muted">Kode Jenjang <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control form-control-sm" id="levelCode" placeholder="Contoh: D4" required>
                                            </div>
                                            <div class="col-6 col-md-4">
                                                <label class="form-label small fw-semibold text-muted">Nama Lengkap Jenjang <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control form-control-sm" id="levelName" placeholder="Contoh: Sarjana Terapan (D4)" required>
                                            </div>
                                            <div class="col-6 col-md-3">
                                                <label class="form-label small fw-semibold text-muted">Gaya / Tema Badge</label>
                                                <select class="form-select form-select-sm" id="levelBadgeStyle">
                                                    <option value="s1">Biru Muda (S1)</option>
                                                    <option value="s2">Ungu (S2)</option>
                                                    <option value="s3">Magenta (S3)</option>
                                                    <option value="d3">Hijau (D3)</option>
                                                    <option value="d4">Toska (D4)</option>
                                                    <option value="profesi">Oranye (Profesi)</option>
                                                    <option value="unit">Kuning / Gold (Unit)</option>
                                                    <option value="sistem">Abu-abu Gelap (Sistem)</option>
                                                    <option value="upt">Indigo (UPT)</option>
                                                    <option value="lembaga">Pink (Lembaga)</option>
                                                </select>
                                            </div>
                                            <div class="col-6 col-md-2">
                                                <label class="form-label small fw-semibold text-muted">Urutan</label>
                                                <input type="number" class="form-control form-control-sm" id="levelSortOrder" value="0" min="0">
                                            </div>
                                            <div class="col-12 text-end mt-2">
                                                <button type="submit" class="btn btn-sm btn-success rounded-pill px-3" id="btnSaveLevel">
                                                    <i class="bi bi-check2 me-1"></i> Simpan Jenjang
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <!-- Tabel Daftar Jenjang -->
                            <h6 class="fw-bold mb-2 text-dark">Daftar Pilihan Jenjang & Badge</h6>
                            <div class="table-responsive border rounded-3 bg-white">
                                <table class="table table-sm table-hover align-middle mb-0" id="levelTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-3" style="width: 110px;">BADGE</th>
                                            <th>NAMA LENGKAP JENJANG</th>
                                            <th style="width: 90px;" class="text-center">URUTAN</th>
                                            <th style="width: 120px;" class="text-center">DIGUNAKAN</th>
                                            <th style="width: 110px;" class="text-end pe-3">AKSI</th>
                                        </tr>
                                    </thead>
                                    <tbody id="levelTableBody">
                                        <tr>
                                            <td colspan="5" class="text-center py-3 text-muted">Memuat data jenjang...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light border-top">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Selesai & Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- SweetAlert2 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- SheetJS (xlsx) for Export to Excel -->
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

    <!-- Custom Application JS -->
    <script src="assets/js/app.js?v=<?= time() ?>"></script>
</body>
</html>
