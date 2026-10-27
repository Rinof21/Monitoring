-- Schema Database SQLite untuk Monitoring App

CREATE TABLE IF NOT EXISTS categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    code TEXT,
    sort_order INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS levels (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    badge_style TEXT DEFAULT 's1', -- s1, s2, s3, unit, sistem, dll.
    sort_order INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS websites (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    category_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    url TEXT NOT NULL,
    level TEXT DEFAULT 'S1', -- S1, S2, S3, D3, D4, Lembaga, dll.
    last_status TEXT DEFAULT 'Belum Dicek', -- Aktif, Error Server, Timeout, Gangguan
    last_http_code INTEGER DEFAULT NULL,
    last_response_time INTEGER DEFAULT NULL, -- Latensi dalam milidetik (ms)
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

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    name TEXT NOT NULL,
    role TEXT DEFAULT 'admin',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_websites_category ON websites(category_id);
CREATE INDEX IF NOT EXISTS idx_websites_status ON websites(last_status);
CREATE INDEX IF NOT EXISTS idx_check_logs_website ON check_logs(website_id);
CREATE INDEX IF NOT EXISTS idx_check_logs_date ON check_logs(checked_at);
CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);

