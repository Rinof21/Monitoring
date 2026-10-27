# 🌐 Sistem Monitoring Status Website (Fakultas & Program Studi)

Aplikasi web monitoring ketersediaan website (uptime & latency checker) dengan pengelompokan hierarki Fakultas/Unit dan Program Studi, dilengkapi indikator visual aktivitas pengecekan, HTTP status code checker, dan manajemen CRUD berbasis database **SQLite**.

---

## 🚀 Fitur Utama

1. **Tampilan Antarmuka (UI/UX) Modern & Responsif**:
   - Kolom tabel utama: **NO**, **FAKULTAS / PRODI**, **URL**, **Status**, **Kode HTTP**, **Respon (ms)**, dan **Aksi**.
   - **Hierarki & Pengelompokan**: Kategori utama (contoh: *Fakultas Hukum*, *Fakultas Ekonomi dan Bisnis*, dll.) dengan sub-item program studi (*S1 - Ilmu Hukum*, *S2 - Magister Hukum*, dll.).
   - **Indikator Status Visual**: Banner di atas header tabel yang dinamis menampilkan `"Sedang mengecek status web... (x/y diperiksa)"` dilengkapi progress bar & pulse animation saat pengecekan berlangsung.
   - **Status Berwarna & Informatif**: Teks hijau untuk status **"Aktif"** (HTTP 200–399) dan merah untuk **"Error Server"** (HTTP 403, 500, dll.) atau **"Timeout"**.
   - **Meter Latensi Visual**: Bar indikator kecepatan respon dalam milidetik (ms) dengan indikator cepat (<250ms), sedang (250–600ms), dan lambat (>600ms).

2. **Backend Checker Berkecepatan Tinggi**:
   - Mendukung **Multi-cURL Asinkron/Paralel**: Memeriksa puluhan website secara bersamaan dalam hitungan detik tanpa membebani browser atau membuat server timeout.
   - Pengecekan individual on-demand (tombol reload per baris).
   - Fitur **"Uji URL"** sebelum disimpan pada form modal.

3. **CRUD Manajemen URL & Fakultas**:
   - Tambah website baru dengan pilihan Fakultas/Unit (atau tambah fakultas baru secara instan).
   - Edit nama, jenjang (S1/S2/S3/D3/Unit), URL, dan catatan.
   - Hapus website dengan modal konfirmasi aman.

4. **Background Automation (Cron / Task Scheduler)**:
   - Dilengkapi script CLI `cron/check_all.php` untuk dijalankan otomatis di background via Windows Task Scheduler atau Linux Cron.

---

## 📁 Struktur Folder Proyek

```
Monitoring-App/
├── index.php                 # Tampilan utama Dashboard (HTML5, Bootstrap 5.3, Modals)
├── config/
│   └── database.php          # Koneksi SQLite PDO & auto-migration/seeder
├── database/
│   ├── schema.sql            # Skema tabel SQLite (categories, websites, check_logs)
│   ├── seed.php              # Data awal realistis (Fakultas & Prodi di Indonesia)
│   └── monitor.sqlite        # Database SQLite (dibuat otomatis pada first-run)
├── classes/
│   └── WebChecker.php        # Service cURL HTTP checker (single & multi-cURL paralel)
├── api/
│   ├── websites.php          # RESTful API CRUD Website & data hierarki
│   ├── check.php             # API eksekusi pengecekan (single, batch, ping test)
│   └── stats.php             # API metrik KPI ringkasan dashboard
├── cron/
│   └── check_all.php         # Script CLI untuk otomasi background scheduler
├── assets/
│   ├── css/
│   │   └── style.css         # Styling modern, glassmorphism, pulse animation, latency bar
│   └── js/
│       └── app.js            # Frontend logic (AJAX, dynamic rendering, auto-refresh)
└── README.md                 # Dokumentasi proyek
```

---

## 🗄️ Skema Database SQLite (`database/schema.sql`)

```sql
CREATE TABLE IF NOT EXISTS categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    code TEXT,
    sort_order INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS websites (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    category_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    url TEXT NOT NULL,
    level TEXT DEFAULT 'S1',
    last_status TEXT DEFAULT 'Belum Dicek',
    last_http_code INTEGER DEFAULT NULL,
    last_response_time INTEGER DEFAULT NULL,
    last_checked_at DATETIME DEFAULT NULL,
    is_active INTEGER DEFAULT 1,
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS check_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    website_id INTEGER NOT NULL,
    http_code INTEGER,
    response_time INTEGER,
    status TEXT,
    error_message TEXT,
    checked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (website_id) REFERENCES websites(id) ON DELETE CASCADE
);
```

---

## ⚡ Cara Menjalankan

### Cara 1: Menggunakan Laragon (Sangat Mudah)
Karena proyek ini berada di direktori `c:\laragon\www\Monitoring-App`:
1. Buka aplikasi **Laragon** lalu klik **"Start All"**.
2. Akses di browser:
   - `http://monitoring-app.test` atau
   - `http://localhost/Monitoring-App/`

### Cara 2: Menggunakan PHP Built-in Server
Buka terminal / PowerShell di folder proyek:
```bash
php -S 127.0.0.1:8000
```
Buka browser dan akses: `http://127.0.0.1:8000`

### Menjalankan Pengecekan Otomatis (Cron / Task Scheduler)
Jalankan perintah ini di CLI untuk memeriksa seluruh website dan memperbarui status di database:
```bash
php cron/check_all.php
```
Di Windows, Anda dapat menambahkan perintah ini ke **Windows Task Scheduler** setiap 5 atau 15 menit.

---

## 💡 Portabilitas ke Framework Lain (Laravel / Node.js)
Jika ingin memindahkan logika ini ke:
- **Laravel**: Pindahkan skema SQL ke Laravel Migration, buat Model `Category` dan `Website`, jadikan `WebChecker.php` sebagai Service Class atau Job, dan gunakan `resources/views/monitoring.blade.php`.
- **Node.js Express**: Gunakan paket `better-sqlite3` atau `sequelize`, dan gunakan `axios` atau `node-fetch` dengan `Promise.all` untuk multi-checking paralel.
