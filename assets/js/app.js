/**
 * Monitoring-App Frontend Logic
 * Live Status Checker, Grouped Hierarchy Table, CRUD, and Statistics
 */

const API = {
    websites: 'api/websites.php',
    check: 'api/check.php',
    stats: 'api/stats.php',
    categories: 'api/categories.php',
    levels: 'api/levels.php'
};

let appState = {
    groupedData: [],
    rawData: [],
    categories: [],
    levels: [],
    isCheckingAll: false,
    autoRefreshInterval: null,
    searchQuery: '',
    selectedCategory: 'all',
    selectedStatus: 'all',
    viewMode: localStorage.getItem('preferredViewMode') || (window.innerWidth < 768 ? 'card' : 'table'),
    isLoggedIn: typeof IS_LOGGED_IN !== 'undefined' ? IS_LOGGED_IN : false
};

// Inisialisasi saat DOM siap
document.addEventListener('DOMContentLoaded', () => {
    initApp();
    setupEventListeners();
});

function initApp() {
    loadData();
    initGuideSidebarState();
    updateLiveClock();
    setInterval(updateLiveClock, 1000);
    initScrollToTop();
}

function updateLiveClock() {
    const el = document.getElementById('liveClock');
    if (el) {
        const now = new Date();
        el.innerText = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) + ' WIB';
    }
}

// Muat data dari server
async function loadData() {
    try {
        const [websitesRes, statsRes, levelsRes] = await Promise.all([
            fetch(API.websites),
            fetch(API.stats),
            fetch(API.levels)
        ]);

        const websitesData = await websitesRes.json();
        const statsData = await statsRes.json();
        const levelsData = await levelsRes.json();

        if (levelsData.success) {
            appState.levels = levelsData.data || [];
            populateLevelDropdown();
        }

        if (websitesData.success) {
            appState.groupedData = websitesData.grouped || [];
            appState.rawData = websitesData.raw || [];
            appState.categories = websitesData.categories || [];
            renderCategoryTabs();
            renderTable();
            populateCategoryDropdowns();
        }

        if (statsData.success) {
            renderStats(statsData.stats);
            updateMetricCardActiveStates();
        }
    } catch (err) {
        console.error('Gagal memuat data:', err);
    }
}

// Render statistik KPI
function renderStats(stats) {
    document.getElementById('statTotal').innerText = stats.total ?? 0;
    document.getElementById('statActive').innerText = stats.active ?? 0;
    document.getElementById('statError').innerText = stats.error ?? 0;
    document.getElementById('statAvgLatency').innerText = (stats.avg_latency || 0) + ' ms';
    document.getElementById('statLastChecked').innerText = stats.last_checked ? formatDateTime(stats.last_checked) : '-';
    
    // Status indikator atas jika tidak sedang mengecek
    if (!appState.isCheckingAll) {
        setIndicatorIdle(stats.last_checked);
    }
}

function setIndicatorIdle(lastCheckedTime) {
    const banner = document.getElementById('statusIndicatorBanner');
    const dot = document.getElementById('indicatorDot');
    const text = document.getElementById('indicatorText');
    const subText = document.getElementById('indicatorSubText');
    const progressContainer = document.getElementById('checkerProgressContainer');

    if (banner) banner.classList.remove('checking-active');
    if (dot) dot.className = 'live-pulse-dot pulse-idle';
    if (text) text.innerText = 'Sistem Pemantauan Siaga';
    if (subText) subText.innerText = 'Terakhir diperbarui: ' + (lastCheckedTime ? formatDateTime(lastCheckedTime) : 'Baru saja');
    if (progressContainer) progressContainer.style.display = 'none';
}

function setIndicatorChecking(current, total) {
    const banner = document.getElementById('statusIndicatorBanner');
    const dot = document.getElementById('indicatorDot');
    const text = document.getElementById('indicatorText');
    const subText = document.getElementById('indicatorSubText');
    const progressContainer = document.getElementById('checkerProgressContainer');
    const progressBar = document.getElementById('checkerProgressBar');

    if (banner) banner.classList.add('checking-active');
    if (dot) dot.className = 'live-pulse-dot pulse-checking';
    if (text) text.innerText = 'Sedang mengecek status web...';
    
    if (total > 0) {
        const percent = Math.round((current / total) * 100);
        if (subText) subText.innerText = `Memeriksa ${current} dari ${total} website (${percent}%)`;
        if (progressBar) progressBar.style.width = percent + '%';
        if (progressContainer) progressContainer.style.display = 'block';
    } else {
        if (subText) subText.innerText = 'Mengirim request HTTP ke seluruh server...';
        if (progressBar) progressBar.style.width = '40%';
        if (progressContainer) progressContainer.style.display = 'block';
    }
}

// Render tabel / kartu utama dengan hierarki Fakultas / Prodi
function renderTable() {
    const tbody = document.getElementById('monitoringTableBody');
    const cardsContainer = document.getElementById('monitoringCardsContainer');
    const tableContainer = document.getElementById('monitoringTableContainer');
    const btnViewCard = document.getElementById('btnViewCard');
    const btnViewTable = document.getElementById('btnViewTable');

    // Update state tombol toggle tampilan
    if (btnViewCard && btnViewTable) {
        if (appState.viewMode === 'card') {
            btnViewCard.classList.add('active');
            btnViewCard.classList.remove('text-secondary');
            btnViewTable.classList.remove('active');
            btnViewTable.classList.add('text-secondary');
        } else {
            btnViewTable.classList.add('active');
            btnViewTable.classList.remove('text-secondary');
            btnViewCard.classList.remove('active');
            btnViewCard.classList.add('text-secondary');
        }
    }

    if (appState.viewMode === 'card') {
        if (tableContainer) tableContainer.classList.add('d-none');
        if (cardsContainer) {
            cardsContainer.classList.remove('d-none');
            cardsContainer.innerHTML = '';
            cardsContainer.classList.remove('table-fade-in');
            void cardsContainer.offsetWidth; // trigger reflow
            cardsContainer.classList.add('table-fade-in');
        }
    } else {
        if (cardsContainer) cardsContainer.classList.add('d-none');
        if (tableContainer) tableContainer.classList.remove('d-none');
        if (tbody) {
            tbody.innerHTML = '';
            tbody.classList.remove('table-fade-in');
            void tbody.offsetWidth; // trigger reflow
            tbody.classList.add('table-fade-in');
        }
    }

    const query = appState.searchQuery.toLowerCase().trim();
    const catFilter = appState.selectedCategory;
    const statusFilter = appState.selectedStatus;

    let globalCounter = 0;
    let visibleGroupCount = 0;

    appState.groupedData.forEach(group => {
        // Filter kategori jika dipilih
        if (catFilter !== 'all' && group.id != catFilter) {
            return;
        }

        // Filter item berdasarkan pencarian & status
        const filteredItems = group.items.filter(item => {
            const matchQuery = !query || 
                item.name.toLowerCase().includes(query) || 
                item.url.toLowerCase().includes(query) ||
                (group.name && group.name.toLowerCase().includes(query));

            const matchStatus = statusFilter === 'all' || 
                (statusFilter === 'active' && item.last_status === 'Aktif') ||
                (statusFilter === 'error' && item.last_status !== 'Aktif' && item.last_status !== 'Belum Dicek');

            return matchQuery && matchStatus;
        });

        if (filteredItems.length === 0) {
            return;
        }

        visibleGroupCount++;

        if (appState.viewMode === 'card') {
            // Mode Kartu untuk Mobile
            const groupHeaderEl = document.createElement('div');
            groupHeaderEl.className = 'faculty-card-group-header mb-2 mt-3 first:mt-0';
            groupHeaderEl.innerHTML = `
                <div class="d-flex align-items-center justify-content-between py-2 px-3 bg-light rounded-3 border">
                    <span class="faculty-badge">
                        <i class="bi bi-diagram-3-fill text-primary"></i>
                        <span>${escapeHtml(group.name)}</span>
                        ${group.code ? `<span class="badge bg-secondary-subtle text-secondary ms-1">${escapeHtml(group.code)}</span>` : ''}
                    </span>
                    <span class="badge bg-white border text-muted small fw-semibold">${filteredItems.length} website</span>
                </div>
            `;
            cardsContainer.appendChild(groupHeaderEl);

            filteredItems.forEach(item => {
                globalCounter++;
                const cardDiv = document.createElement('div');
                cardDiv.innerHTML = generateCardHtml(globalCounter, item);
                cardsContainer.appendChild(cardDiv.firstElementChild);
            });
        } else {
            // Mode Tabel Tradisional
            const groupHeaderRow = document.createElement('tr');
            groupHeaderRow.className = 'faculty-group-row';
            const colSpan = appState.isLoggedIn ? 7 : 6;
            groupHeaderRow.innerHTML = `
                <td colspan="${colSpan}">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <span class="faculty-badge">
                                <i class="bi bi-diagram-3-fill text-primary"></i>
                                ${escapeHtml(group.name)}
                                ${group.code ? `<span class="badge bg-secondary-subtle text-secondary ms-1">${escapeHtml(group.code)}</span>` : ''}
                            </span>
                            <span class="text-muted small">(${filteredItems.length} website dipantau)</span>
                        </div>
                    </div>
                </td>
            `;
            tbody.appendChild(groupHeaderRow);

            filteredItems.forEach(item => {
                globalCounter++;
                const tr = document.createElement('tr');
                tr.id = `row-web-${item.id}`;
                tr.className = 'prodi-item-row';
                tr.innerHTML = generateRowHtml(globalCounter, item);
                tbody.appendChild(tr);
            });
        }
    });

    if (visibleGroupCount === 0) {
        let emptyMsg = 'Tidak ada website atau fakultas yang sesuai dengan kriteria filter.';
        let iconClass = 'bi-search text-secondary';
        if (statusFilter === 'error') {
            emptyMsg = 'Tidak ada website yang mengalami gangguan / error server. Semua layanan berjalan lancar! 🎉';
            iconClass = 'bi-check-circle text-success';
        }

        const emptyHtml = `
            <div class="text-center py-5 text-muted">
                <i class="bi ${iconClass} fs-1 d-block mb-2 opacity-50"></i>
                <p class="mb-0">${emptyMsg}</p>
            </div>
        `;

        if (appState.viewMode === 'card' && cardsContainer) {
            cardsContainer.innerHTML = emptyHtml;
        } else if (tbody) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="${appState.isLoggedIn ? 7 : 6}">
                        ${emptyHtml}
                    </td>
                </tr>
            `;
        }
    }
}

// Beralih antara Tampilan Kartu & Tampilan Tabel
function setViewMode(mode) {
    if (appState.viewMode === mode) return;
    appState.viewMode = mode;
    localStorage.setItem('preferredViewMode', mode);
    renderTable();
}

// Menghasilkan HTML Kartu untuk Mode Mobile / Kartu
function generateCardHtml(counter, item) {
    const isAktif = item.last_status === 'Aktif';
    const statusClass = isAktif ? 'active' : (item.last_status === 'Timeout' ? 'timeout' : 'error');
    const statusIcon = isAktif ? 'check-circle-fill' : 'exclamation-triangle-fill';
    const statusText = item.last_status || 'Belum Dicek';

    const code = item.last_http_code || 0;
    let codeBadgeClass = 'badge-http-0';
    if (code >= 200 && code < 300) codeBadgeClass = 'badge-http-200';
    else if (code >= 300 && code < 400) codeBadgeClass = 'badge-http-300';
    else if (code >= 400 && code < 500) codeBadgeClass = 'badge-http-400';
    else if (code >= 500) codeBadgeClass = 'badge-http-500';

    const latency = item.last_response_time || 0;
    let latencyClass = 'fast';
    let latencyPercent = Math.min(100, Math.max(10, (latency / 1000) * 100));
    if (latency > 600) latencyClass = 'slow';
    else if (latency > 250) latencyClass = 'moderate';

    const levelCode = item.level || 'S1';
    const foundLevel = (appState.levels || []).find(l => l.code.toLowerCase() === levelCode.toLowerCase());
    const levelClass = foundLevel ? foundLevel.badge_style : levelCode.toLowerCase();

    return `
        <div class="website-card mb-2" id="row-web-${item.id}">
            <div class="d-flex align-items-center justify-content-between gap-2 mb-2 w-100" style="min-width: 0;">
                <div class="d-flex align-items-center gap-1 gap-sm-2 min-w-0" style="min-width: 0; flex: 1 1 auto; overflow: hidden;">
                    <span class="card-item-counter flex-shrink-0">#${counter}</span>
                    <span class="level-tag ${levelClass} flex-shrink-0">${escapeHtml(item.level || 'S1')}</span>
                    <span class="prodi-title text-truncate fw-bold text-dark" style="min-width: 0; display: block;" title="${escapeHtml(item.name)}">${escapeHtml(item.name)}</span>
                </div>
                <span class="badge-status ${statusClass} flex-shrink-0 ms-auto" id="status-badge-${item.id}">
                    <span class="status-dot"></span>
                    <span>${escapeHtml(statusText)}</span>
                </span>
            </div>

            <div class="mb-2">
                <a href="${escapeHtml(item.url)}" target="_blank" rel="noopener noreferrer" class="url-link d-inline-flex align-items-center gap-1 text-truncate w-100" title="${escapeHtml(item.url)}">
                    <i class="bi bi-box-arrow-up-right text-muted flex-shrink-0"></i>
                    <span class="text-truncate">${escapeHtml(item.url)}</span>
                </a>
                ${item.notes ? `<div class="text-muted small mt-1"><i class="bi bi-info-circle me-1"></i>${escapeHtml(item.notes)}</div>` : ''}
            </div>

            <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge-http ${codeBadgeClass}" id="code-badge-${item.id}">
                        ${code > 0 ? code : 'ERR'}
                    </span>
                    <div class="latency-container" id="latency-box-${item.id}">
                        <div class="latency-value ${latencyClass}">
                            ${latency > 0 ? latency + ' ms' : '-'}
                        </div>
                        <div class="latency-bar-bg" style="width: 70px;">
                            <div class="latency-bar-fill ${latencyClass}" style="width: ${latencyPercent}%;"></div>
                        </div>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-1">
                    <button type="button" class="btn btn-sm btn-light border rounded-pill px-2 py-1 text-primary d-inline-flex align-items-center gap-1" title="Cek Ulang Status" onclick="checkSingleWebsite(${item.id})">
                        <i class="bi bi-arrow-clockwise" id="recheck-icon-${item.id}"></i>
                        <span class="small fw-semibold">Cek</span>
                    </button>
                    ${appState.isLoggedIn ? `
                    <button type="button" class="btn-action-icon" title="Edit Website" onclick="openEditModal(${item.id})">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button type="button" class="btn-action-icon btn-delete" title="Hapus Website" onclick="openDeleteModal(${item.id})">
                        <i class="bi bi-trash3"></i>
                    </button>
                    ` : ''}
                </div>
            </div>
        </div>
    `;
}

// Menghasilkan HTML untuk setiap baris data mode Tabel
function generateRowHtml(counter, item) {
    const isAktif = item.last_status === 'Aktif';
    const statusClass = isAktif ? 'active' : (item.last_status === 'Timeout' ? 'timeout' : 'error');
    const statusIcon = isAktif ? 'check-circle-fill' : 'exclamation-triangle-fill';
    const statusText = item.last_status || 'Belum Dicek';

    // Kode HTTP
    const code = item.last_http_code || 0;
    let codeBadgeClass = 'badge-http-0';
    if (code >= 200 && code < 300) codeBadgeClass = 'badge-http-200';
    else if (code >= 300 && code < 400) codeBadgeClass = 'badge-http-300';
    else if (code >= 400 && code < 500) codeBadgeClass = 'badge-http-400';
    else if (code >= 500) codeBadgeClass = 'badge-http-500';

    // Latensi
    const latency = item.last_response_time || 0;
    let latencyClass = 'fast';
    let latencyPercent = Math.min(100, Math.max(10, (latency / 1000) * 100));
    if (latency > 600) latencyClass = 'slow';
    else if (latency > 250) latencyClass = 'moderate';

    // Level tag
    const levelCode = item.level || 'S1';
    const foundLevel = (appState.levels || []).find(l => l.code.toLowerCase() === levelCode.toLowerCase());
    const levelClass = foundLevel ? foundLevel.badge_style : levelCode.toLowerCase();

    return `
        <td class="text-center text-muted fw-semibold" style="width: 50px;">${counter}</td>
        <td>
            <div class="d-flex align-items-center">
                <i class="bi bi-arrow-return-right tree-branch-icon"></i>
                <span class="level-tag ${levelClass}">${escapeHtml(item.level || 'S1')}</span>
                <span class="prodi-title">${escapeHtml(item.name)}</span>
            </div>
            ${item.notes ? `<div class="text-muted small ps-4 ms-2 mt-1"><i class="bi bi-info-circle me-1"></i>${escapeHtml(item.notes)}</div>` : ''}
        </td>
        <td>
            <a href="${escapeHtml(item.url)}" target="_blank" rel="noopener noreferrer" class="url-link" title="${escapeHtml(item.url)}">
                <i class="bi bi-box-arrow-up-right text-muted"></i>
                ${escapeHtml(item.url)}
            </a>
        </td>
        <td>
            <span class="badge-status ${statusClass}" id="status-badge-${item.id}">
                <span class="status-dot"></span>
                <span>${escapeHtml(statusText)}</span>
            </span>
        </td>
        <td>
            <span class="badge-http ${codeBadgeClass}" id="code-badge-${item.id}">
                ${code > 0 ? code : 'ERR'}
            </span>
        </td>
        <td>
            <div class="latency-container" id="latency-box-${item.id}">
                <div class="latency-value ${latencyClass}">
                    ${latency > 0 ? latency + ' ms' : '-'}
                </div>
                <div class="latency-bar-bg">
                    <div class="latency-bar-fill ${latencyClass}" style="width: ${latencyPercent}%;"></div>
                </div>
            </div>
        </td>
        <td class="text-end" style="width: 130px;">
            <div class="d-flex justify-content-end gap-1">
                <button type="button" class="btn-action-icon btn-recheck" title="Cek Ulang Status" onclick="checkSingleWebsite(${item.id})">
                    <i class="bi bi-arrow-clockwise" id="recheck-icon-${item.id}"></i>
                </button>
                ${appState.isLoggedIn ? `
                <button type="button" class="btn-action-icon" title="Edit Website" onclick="openEditModal(${item.id})">
                    <i class="bi bi-pencil"></i>
                </button>
                <button type="button" class="btn-action-icon btn-delete" title="Hapus Website" onclick="openDeleteModal(${item.id})">
                    <i class="bi bi-trash3"></i>
                </button>
                ` : ''}
            </div>
        </td>
    `;
}

// Cek ulang satu website secara langsung
async function checkSingleWebsite(id) {
    const icon = document.getElementById(`recheck-icon-${id}`);
    if (icon) icon.classList.add('spin');

    try {
        const res = await fetch(`${API.check}?id=${id}`);
        const data = await res.json();

        if (data.success && data.data) {
            updateRowUi(id, data.data);
            // Refresh statistik
            fetch(API.stats).then(r => r.json()).then(s => s.success && renderStats(s.stats));
        } else {
            alert('Gagal mengecek: ' + (data.message || 'Error server'));
        }
    } catch (err) {
        console.error('Error single check:', err);
    } finally {
        if (icon) icon.classList.remove('spin');
    }
}

// Cek SEMUA website secara batch dengan progress bar
async function checkAllWebsites() {
    if (appState.isCheckingAll) return;
    appState.isCheckingAll = true;

    const btn = document.getElementById('btnCheckAll');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = `<i class="bi bi-arrow-clockwise spin me-2"></i>Mengecek...`;
    }

    setIndicatorChecking(0, 0);

    try {
        const res = await fetch(`${API.check}?action=check_all`);
        const data = await res.json();

        if (data.success) {
            setIndicatorChecking(data.total_checked, data.total_checked);
            setTimeout(() => {
                loadData();
                setIndicatorIdle(new Date().toISOString());
            }, 600);
        } else {
            alert('Gagal mengecek semua website: ' + data.message);
            setIndicatorIdle();
        }
    } catch (err) {
        console.error('Batch check error:', err);
        setIndicatorIdle();
    } finally {
        appState.isCheckingAll = false;
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = `<i class="bi bi-arrow-clockwise me-2"></i>Cek Semua Sekarang`;
        }
    }
}

// Update UI baris setelah pengecekan tunggal
function updateRowUi(id, data) {
    // Update local state
    const item = appState.rawData.find(w => w.id == id);
    if (item) {
        item.last_status = data.status;
        item.last_http_code = data.http_code;
        item.last_response_time = data.response_time;
        item.last_checked_at = data.checked_at;
    }

    const row = document.getElementById(`row-web-${id}`);
    if (!row) return;

    // Status Badge
    const statusBadge = document.getElementById(`status-badge-${id}`);
    if (statusBadge) {
        const isAktif = data.status === 'Aktif';
        const statusClass = isAktif ? 'active' : (data.status === 'Timeout' ? 'timeout' : 'error');
        statusBadge.className = `badge-status ${statusClass}`;
        statusBadge.innerHTML = `<span class="status-dot"></span><span>${escapeHtml(data.status)}</span>`;
    }

    // HTTP Code Badge
    const codeBadge = document.getElementById(`code-badge-${id}`);
    if (codeBadge) {
        const code = data.http_code;
        let codeClass = 'badge-http-0';
        if (code >= 200 && code < 300) codeClass = 'badge-http-200';
        else if (code >= 300 && code < 400) codeClass = 'badge-http-300';
        else if (code >= 400 && code < 500) codeClass = 'badge-http-400';
        else if (code >= 500) codeClass = 'badge-http-500';

        codeBadge.className = `badge-http ${codeClass}`;
        codeBadge.innerText = code > 0 ? code : 'ERR';
    }

    // Latency Box
    const latencyBox = document.getElementById(`latency-box-${id}`);
    if (latencyBox) {
        const latency = data.response_time || 0;
        let latencyClass = 'fast';
        let latencyPercent = Math.min(100, Math.max(10, (latency / 1000) * 100));
        if (latency > 600) latencyClass = 'slow';
        else if (latency > 250) latencyClass = 'moderate';

        latencyBox.innerHTML = `
            <div class="latency-value ${latencyClass}">
                ${latency > 0 ? latency + ' ms' : '-'}
            </div>
            <div class="latency-bar-bg">
                <div class="latency-bar-fill ${latencyClass}" style="width: ${latencyPercent}%;"></div>
            </div>
        `;
    }

    // Highlight row sebentar
    row.style.transition = 'background-color 0.4s ease';
    row.style.backgroundColor = '#ecfdf5';
    setTimeout(() => {
        row.style.backgroundColor = '';
    }, 1200);
}

// Render Tab Kategori / Fakultas (Mencegah scroll panjang)
function renderCategoryTabs() {
    const tabsContainer = document.getElementById('categoryTabs');
    if (!tabsContainer) return;

    tabsContainer.innerHTML = '';

    const totalCount = appState.rawData ? appState.rawData.length : 0;
    const isAllActive = appState.selectedCategory === 'all';

    // 1. Tab Semua Fakultas / Unit
    const allLi = document.createElement('li');
    allLi.className = 'nav-item';
    allLi.innerHTML = `
        <button class="nav-link ${isAllActive ? 'active' : ''}" data-category-id="all" type="button">
            <i class="bi bi-grid-fill"></i>
            <span>Semua Fakultas / Unit</span>
            <span class="tab-count-badge">${totalCount}</span>
        </button>
    `;
    tabsContainer.appendChild(allLi);

    // 2. Tab per Fakultas / Unit dari groupedData
    (appState.groupedData || []).forEach(group => {
        const isActive = String(appState.selectedCategory) === String(group.id);
        const count = group.items ? group.items.length : 0;

        let icon = 'bi-building';
        const nameLower = (group.name || '').toLowerCase();
        if (nameLower.includes('kedokteran')) icon = 'bi-heart-pulse-fill';
        else if (nameLower.includes('teknik')) icon = 'bi-cpu-fill';
        else if (nameLower.includes('hukum')) icon = 'bi-briefcase-fill';
        else if (nameLower.includes('ekonomi') || nameLower.includes('bisnis')) icon = 'bi-graph-up-arrow';
        else if (nameLower.includes('pertanian')) icon = 'bi-flower1';
        else if (nameLower.includes('mipa')) icon = 'bi-calculator-fill';
        else if (nameLower.includes('unit') || nameLower.includes('layanan')) icon = 'bi-layers-fill';
        else if (nameLower.includes('tanjungpura') || nameLower.includes('pusat')) icon = 'bi-bank2';

        const li = document.createElement('li');
        li.className = 'nav-item';
        li.innerHTML = `
            <button class="nav-link ${isActive ? 'active' : ''}" data-category-id="${group.id}" type="button">
                <i class="bi ${icon}"></i>
                <span>${escapeHtml(group.name)}</span>
                <span class="tab-count-badge">${count}</span>
            </button>
        `;
        tabsContainer.appendChild(li);
    });

    // Pasang click listener pada tiap tombol tab
    tabsContainer.querySelectorAll('button[data-category-id]').forEach(btn => {
        btn.addEventListener('click', () => {
            const catId = btn.getAttribute('data-category-id');
            selectCategoryTab(catId);
        });
    });

    updateTabSummaryText();
}

// Handler pemilihan tab kategori
function selectCategoryTab(catId) {
    appState.selectedCategory = catId;

    // Update active class pada tab buttons
    const tabsContainer = document.getElementById('categoryTabs');
    if (tabsContainer) {
        tabsContainer.querySelectorAll('button[data-category-id]').forEach(btn => {
            if (btn.getAttribute('data-category-id') === String(catId)) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });
    }

    // Sinkronkan dropdown filter jika ada
    const filterSelect = document.getElementById('filterCategory');
    if (filterSelect && filterSelect.value !== String(catId)) {
        filterSelect.value = catId;
    }

    // Smooth scroll tab yang aktif ke tengah viewport tabs pada mobile
    if (tabsContainer) {
        const activeBtn = tabsContainer.querySelector(`button[data-category-id="${catId}"]`);
        if (activeBtn) {
            activeBtn.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
        }
    }

    updateTabSummaryText();
    renderTable();
}

// Update teks ringkasan kategori & status di samping kanan tabs
function updateTabSummaryText() {
    const summaryEl = document.getElementById('tabItemsSummary');
    if (!summaryEl) return;

    let statusBadge = '';
    if (appState.selectedStatus === 'error') {
        statusBadge = ' <span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="bi bi-exclamation-triangle-fill me-1"></i>Error Server</span>';
    } else if (appState.selectedStatus === 'active') {
        statusBadge = ' <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check-circle-fill me-1"></i>Aktif (200)</span>';
    }

    if (appState.selectedCategory === 'all') {
        let count = 0;
        (appState.rawData || []).forEach(item => {
            const matchStatus = appState.selectedStatus === 'all' ||
                (appState.selectedStatus === 'active' && item.last_status === 'Aktif') ||
                (appState.selectedStatus === 'error' && item.last_status !== 'Aktif' && item.last_status !== 'Belum Dicek');
            if (matchStatus) count++;
        });

        if (appState.selectedStatus === 'all') {
            summaryEl.innerHTML = `Menampilkan semua <strong>${count} website</strong> dari seluruh unit`;
        } else {
            summaryEl.innerHTML = `Menampilkan <strong>${count} website</strong>${statusBadge} dari seluruh unit`;
        }
    } else {
        const group = (appState.groupedData || []).find(g => String(g.id) === String(appState.selectedCategory));
        if (group) {
            let count = 0;
            (group.items || []).forEach(item => {
                const matchStatus = appState.selectedStatus === 'all' ||
                    (appState.selectedStatus === 'active' && item.last_status === 'Aktif') ||
                    (appState.selectedStatus === 'error' && item.last_status !== 'Aktif' && item.last_status !== 'Belum Dicek');
                if (matchStatus) count++;
            });
            summaryEl.innerHTML = `Menampilkan <strong>${count} website</strong>${statusBadge} pada <strong>${escapeHtml(group.name)}</strong>`;
        }
    }
}

// Filter cepat berdasarkan klik pada Card Metrik KPI (Total, Aktif, Error)
function filterByMetricCard(targetStatus) {
    if (targetStatus === 'error') {
        // Toggle: jika sudah 'error', klik lagi untuk kembali ke 'all'
        if (appState.selectedStatus === 'error') {
            appState.selectedStatus = 'all';
        } else {
            appState.selectedStatus = 'error';
            // Reset kategori ke 'all' agar SEMUA website error dari seluruh unit langsung ditampilkan
            selectCategoryTab('all');
        }
    } else if (targetStatus === 'active') {
        if (appState.selectedStatus === 'active') {
            appState.selectedStatus = 'all';
        } else {
            appState.selectedStatus = 'active';
            selectCategoryTab('all');
        }
    } else {
        // Target 'all' (Total Website)
        appState.selectedStatus = 'all';
        selectCategoryTab('all');
    }

    // Sinkronkan dropdown filter status di toolbar
    const filterStatusSelect = document.getElementById('filterStatus');
    if (filterStatusSelect) {
        filterStatusSelect.value = appState.selectedStatus;
    }

    updateMetricCardActiveStates();
    updateTabSummaryText();
    renderTable();

    // Scroll mulus ke tabel jika pengguna berada di luar viewport tabel
    const tableEl = document.getElementById('monitoringTable');
    if (tableEl) {
        const rect = tableEl.getBoundingClientRect();
        if (rect.top < 0 || rect.top > window.innerHeight) {
            tableEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }
}

// Update status aktif visual pada Metric Card KPI
function updateMetricCardActiveStates() {
    const cardTotal = document.getElementById('cardStatTotal');
    const cardActive = document.getElementById('cardStatActive');
    const cardError = document.getElementById('cardStatError');

    if (cardTotal) cardTotal.classList.remove('active-filter-total');
    if (cardActive) cardActive.classList.remove('active-filter-active');
    if (cardError) cardError.classList.remove('active-filter-error');

    if (appState.selectedStatus === 'error') {
        if (cardError) cardError.classList.add('active-filter-error');
    } else if (appState.selectedStatus === 'active') {
        if (cardActive) cardActive.classList.add('active-filter-active');
    } else {
        if (cardTotal) cardTotal.classList.add('active-filter-total');
    }
}

// Populate dropdown kategori di form modal & filter
function populateCategoryDropdowns() {
    const filterSelect = document.getElementById('filterCategory');
    const modalSelect = document.getElementById('websiteCategory');

    if (filterSelect) {
        const currentVal = filterSelect.value;
        filterSelect.innerHTML = `<option value="all">Semua Fakultas / Unit</option>`;
        appState.categories.forEach(c => {
            filterSelect.innerHTML += `<option value="${c.id}">${escapeHtml(c.name)}</option>`;
        });
        filterSelect.value = currentVal;
    }

    if (modalSelect) {
        const currentVal = modalSelect.value;
        modalSelect.innerHTML = `<option value="">-- Pilih Fakultas / Unit --</option>`;
        appState.categories.forEach(c => {
            modalSelect.innerHTML += `<option value="${c.id}">${escapeHtml(c.name)}</option>`;
        });
        modalSelect.innerHTML += `<option value="new">+ Tambah Fakultas Baru...</option>`;
        if (currentVal) modalSelect.value = currentVal;
    }
}

// Populate dropdown pilihan jenjang di modal tambah/edit
function populateLevelDropdown() {
    const levelSelect = document.getElementById('websiteLevel');
    if (!levelSelect) return;

    const currentVal = levelSelect.value;
    levelSelect.innerHTML = '';
    (appState.levels || []).forEach(l => {
        levelSelect.innerHTML += `<option value="${escapeHtml(l.code)}">${escapeHtml(l.name)} (${escapeHtml(l.code)})</option>`;
    });

    if (currentVal && levelSelect.querySelector(`option[value="${currentVal}"]`)) {
        levelSelect.value = currentVal;
    }
}

// Setup Event Listeners
function setupEventListeners() {
    // Search
    const searchInput = document.getElementById('searchInput');
    const btnClearSearch = document.getElementById('btnClearSearch');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            appState.searchQuery = e.target.value;
            if (btnClearSearch) {
                if (e.target.value.trim().length > 0) {
                    btnClearSearch.classList.remove('d-none');
                } else {
                    btnClearSearch.classList.add('d-none');
                }
            }
            renderTable();
        });
    }

    // Filter Kategori (Dropdown)
    const filterCategory = document.getElementById('filterCategory');
    if (filterCategory) {
        filterCategory.addEventListener('change', (e) => {
            selectCategoryTab(e.target.value);
        });
    }

    // Filter Status
    const filterStatus = document.getElementById('filterStatus');
    if (filterStatus) {
        filterStatus.addEventListener('change', (e) => {
            appState.selectedStatus = e.target.value;
            updateMetricCardActiveStates();
            updateTabSummaryText();
            renderTable();
        });
    }

    // Keyboard support (Enter / Space) untuk metric cards
    document.querySelectorAll('.metric-card-clickable').forEach(card => {
        card.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                card.click();
            }
        });
    });

    // Auto-refresh switch
    const autoRefreshSwitch = document.getElementById('autoRefreshSwitch');
    if (autoRefreshSwitch) {
        autoRefreshSwitch.addEventListener('change', (e) => {
            if (e.target.checked) {
                appState.autoRefreshInterval = setInterval(() => {
                    checkAllWebsites();
                }, 60000); // Setiap 60 detik
            } else {
                clearInterval(appState.autoRefreshInterval);
            }
        });
    }

    // Dropdown pilih kategori di modal (deteksi opsi baru)
    const modalCategorySelect = document.getElementById('websiteCategory');
    const newCategoryInputGroup = document.getElementById('newCategoryInputGroup');
    if (modalCategorySelect && newCategoryInputGroup) {
        modalCategorySelect.addEventListener('change', (e) => {
            if (e.target.value === 'new') {
                newCategoryInputGroup.style.display = 'block';
                document.getElementById('newCategoryName').required = true;
                document.getElementById('newCategoryName').focus();
            } else {
                newCategoryInputGroup.style.display = 'none';
                document.getElementById('newCategoryName').required = false;
            }
        });
    }

    // Form Tambah/Edit Website
    const websiteForm = document.getElementById('websiteForm');
    if (websiteForm) {
        websiteForm.addEventListener('submit', handleWebsiteFormSubmit);
    }

    // Tombol Uji Koneksi URL
    const btnTestUrl = document.getElementById('btnTestUrl');
    if (btnTestUrl) {
        btnTestUrl.addEventListener('click', handleTestUrl);
    }

    // Form Master Fakultas
    const facultyForm = document.getElementById('facultyForm');
    if (facultyForm) {
        facultyForm.addEventListener('submit', handleFacultySubmit);
    }

    // Form Master Jenjang
    const levelForm = document.getElementById('levelForm');
    if (levelForm) {
        levelForm.addEventListener('submit', handleLevelSubmit);
    }
}

// Buka Modal Tambah
function openAddModal() {
    document.getElementById('websiteModalTitle').innerText = 'Tambah Website Monitoring';
    document.getElementById('websiteForm').reset();
    document.getElementById('websiteId').value = '';
    document.getElementById('newCategoryInputGroup').style.display = 'none';
    document.getElementById('urlTestResultBox').style.display = 'none';

    const modal = new bootstrap.Modal(document.getElementById('websiteModal'));
    modal.show();
}

// Buka Modal Edit
async function openEditModal(id) {
    try {
        const res = await fetch(`${API.websites}?id=${id}`);
        const json = await res.json();

        if (json.success && json.data) {
            const d = json.data;
            document.getElementById('websiteModalTitle').innerText = 'Edit Website Monitoring';
            document.getElementById('websiteId').value = d.id;
            document.getElementById('websiteCategory').value = d.category_id;
            document.getElementById('websiteLevel').value = d.level || 'S1';
            document.getElementById('websiteName').value = d.name;
            document.getElementById('websiteUrl').value = d.url;
            document.getElementById('websiteNotes').value = d.notes || '';
            document.getElementById('newCategoryInputGroup').style.display = 'none';
            document.getElementById('urlTestResultBox').style.display = 'none';

            const modal = new bootstrap.Modal(document.getElementById('websiteModal'));
            modal.show();
        }
    } catch (err) {
        alert('Gagal mengambil data website: ' + err.message);
    }
}

// Uji koneksi URL sebelum disimpan
async function handleTestUrl() {
    const urlInput = document.getElementById('websiteUrl');
    const resultBox = document.getElementById('urlTestResultBox');
    const url = urlInput.value.trim();

    if (!url) {
        alert('Silakan ketikkan URL terlebih dahulu');
        urlInput.focus();
        return;
    }

    const btn = document.getElementById('btnTestUrl');
    btn.disabled = true;
    btn.innerHTML = `<i class="bi bi-arrow-clockwise spin me-1"></i> Menguji...`;

    resultBox.style.display = 'block';
    resultBox.className = 'url-test-result-box text-muted';
    resultBox.innerHTML = `<i class="bi bi-arrow-repeat spin me-2"></i> Mengirim HTTP request ke ${escapeHtml(url)}...`;

    try {
        const res = await fetch(`${API.check}?test_url=${encodeURIComponent(url)}`);
        const data = await res.json();

        if (data.success && data.result) {
            const r = data.result;
            const isOk = r.http_code >= 200 && r.http_code < 400;
            resultBox.className = `url-test-result-box ${isOk ? 'alert-success border-success-subtle text-success' : 'alert-danger border-danger-subtle text-danger'}`;
            resultBox.innerHTML = `
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <strong>${isOk ? '✓ Terhubung Sukses' : '✕ Gangguan / Error'}</strong>: 
                        Status: <b>${escapeHtml(r.status)}</b>, HTTP <b>${r.http_code}</b>
                    </div>
                    <div class="fw-bold">${r.response_time} ms</div>
                </div>
                ${r.error_message ? `<div class="small mt-1 text-danger">${escapeHtml(r.error_message)}</div>` : ''}
            `;
        } else {
            resultBox.className = 'url-test-result-box alert-danger border-danger-subtle text-danger';
            resultBox.innerText = data.message || 'Gagal menguji koneksi';
        }
    } catch (err) {
        resultBox.className = 'url-test-result-box alert-danger border-danger-subtle text-danger';
        resultBox.innerText = 'Error: ' + err.message;
    } finally {
        btn.disabled = false;
        btn.innerHTML = `<i class="bi bi-broadcast me-1"></i> Uji URL`;
    }
}

// Submit Form Tambah / Edit
async function handleWebsiteFormSubmit(e) {
    e.preventDefault();

    const id = document.getElementById('websiteId').value;
    const categorySelect = document.getElementById('websiteCategory');
    const isNewCategory = categorySelect.value === 'new';

    const payload = {
        name: document.getElementById('websiteName').value.trim(),
        url: document.getElementById('websiteUrl').value.trim(),
        level: document.getElementById('websiteLevel').value,
        category_id: isNewCategory ? 0 : parseInt(categorySelect.value),
        new_category_name: isNewCategory ? document.getElementById('newCategoryName').value.trim() : '',
        notes: document.getElementById('websiteNotes').value.trim()
    };

    const isEdit = Boolean(id);
    if (isEdit) {
        payload.id = parseInt(id);
    }

    const submitBtn = document.getElementById('btnSubmitWebsite');
    submitBtn.disabled = true;
    submitBtn.innerHTML = `<i class="bi bi-arrow-clockwise spin me-1"></i> Menyimpan...`;

    try {
        const res = await fetch(API.websites, {
            method: isEdit ? 'PUT' : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const data = await res.json();
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('websiteModal')).hide();
            await loadData();
            showToast('success', isEdit ? 'Data website berhasil diperbarui' : 'Website baru berhasil ditambahkan');
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Gagal Menyimpan Website',
                text: data.message || 'Terjadi kesalahan sistem',
                confirmButtonColor: '#ef4444'
            });
        }
    } catch (err) {
        Swal.fire({
            icon: 'error',
            title: 'Kesalahan Sistem',
            text: err.message,
            confirmButtonColor: '#ef4444'
        });
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = `Simpan Data`;
    }
}

// Modal Konfirmasi Hapus Website dengan SweetAlert2
let deleteTargetId = null;

async function openDeleteModal(id, fallbackName = '') {
    const web = appState.rawData.find(w => w.id === id) || { name: fallbackName };
    const name = web.name || fallbackName || 'website ini';

    const result = await Swal.fire({
        title: 'Hapus Website?',
        html: `Apakah Anda yakin ingin menghapus <b>${escapeHtml(name)}</b> dari daftar pemantauan?<br><small class="text-muted">Data dan riwayat status website ini akan dihapus permanen.</small>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: '<i class="bi bi-trash3 me-1"></i> Ya, Hapus',
        cancelButtonText: 'Batal',
        reverseButtons: true,
        focusCancel: true
    });

    if (!result.isConfirmed) return;

    try {
        const res = await fetch(API.websites, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const data = await res.json();

        if (data.success) {
            await loadData();
            showToast('success', `Website "${escapeHtml(name)}" berhasil dihapus`);
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Gagal Menghapus',
                text: data.message || 'Terjadi kesalahan saat menghapus',
                confirmButtonColor: '#ef4444'
            });
        }
    } catch (err) {
        Swal.fire({
            icon: 'error',
            title: 'Kesalahan Jaringan',
            text: err.message,
            confirmButtonColor: '#ef4444'
        });
    }
}

async function confirmDeleteWebsite() {
    if (!deleteTargetId) return;
    openDeleteModal(deleteTargetId);
    deleteTargetId = null;
}

// Utility: format tanggal jam
function formatDateTime(dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr.replace(' ', 'T'));
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
}

// Utility: escape HTML
function escapeHtml(str) {
    if (!str) return '';
    return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// Toast Notifikasi SweetAlert2
function showToast(icon, title) {
    if (typeof Swal !== 'undefined') {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.onmouseenter = Swal.stopTimer;
                toast.onmouseleave = Swal.resumeTimer;
            }
        });
        Toast.fire({ icon, title });
    }
}

/* ==========================================================================
   MASTER DATA SETTINGS (CRUD FAKULTAS & JENJANG)
   ========================================================================== */

let returnToWebsiteModal = false;

// Buka Modal Pengaturan Master Data
function openMasterSettingsModal(activeTab = 'tab-faculty') {
    // Cek apakah websiteModal sedang terbuka
    const webModalEl = document.getElementById('websiteModal');
    if (webModalEl && webModalEl.classList.contains('show')) {
        returnToWebsiteModal = true;
        const inst = bootstrap.Modal.getInstance(webModalEl);
        if (inst) inst.hide();
    } else {
        returnToWebsiteModal = false;
    }

    // Aktifkan tab yang dipilih
    setTimeout(() => {
        if (activeTab === 'tab-level') {
            const tabBtn = document.getElementById('tab-level-btn');
            if (tabBtn) bootstrap.Tab.getOrCreateInstance(tabBtn).show();
        } else {
            const tabBtn = document.getElementById('tab-faculty-btn');
            if (tabBtn) bootstrap.Tab.getOrCreateInstance(tabBtn).show();
        }

        // Muat data tabel di modal
        loadFacultyTable();
        loadLevelTable();

        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('masterSettingsModal'));
        modal.show();
    }, 200);
}

// Kembalikan ke modal tambah website jika sebelumnya dibuka dari sana
document.addEventListener('DOMContentLoaded', () => {
    const settingsModalEl = document.getElementById('masterSettingsModal');
    if (settingsModalEl) {
        settingsModalEl.addEventListener('hidden.bs.modal', () => {
            if (returnToWebsiteModal) {
                returnToWebsiteModal = false;
                setTimeout(() => {
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('websiteModal')).show();
                }, 200);
            }
        });
    }
});

// --------------------------------------------------------------------------
// 1. CRUD FAKULTAS / UNIT KERJA
// --------------------------------------------------------------------------

async function loadFacultyTable() {
    const tbody = document.getElementById('facultyTableBody');
    if (!tbody) return;

    try {
        const res = await fetch(API.categories);
        const json = await res.json();

        if (json.success && Array.isArray(json.data)) {
            appState.categories = json.data;
            populateCategoryDropdowns();

            if (json.data.length === 0) {
                tbody.innerHTML = `<tr><td colspan="6" class="text-center py-3 text-muted">Belum ada fakultas/unit kerja.</td></tr>`;
                return;
            }

            tbody.innerHTML = '';
            json.data.forEach((cat, idx) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="ps-3 text-muted fw-semibold">${idx + 1}</td>
                    <td class="fw-bold text-dark">${escapeHtml(cat.name)}</td>
                    <td>${cat.code ? `<span class="badge bg-secondary-subtle text-secondary px-2 py-1">${escapeHtml(cat.code)}</span>` : '<span class="text-muted">-</span>'}</td>
                    <td class="text-center text-muted">${cat.sort_order || 0}</td>
                    <td class="text-center">
                        <span class="badge bg-primary-subtle text-primary px-2 py-1">${cat.websites_count || 0} web</span>
                    </td>
                    <td class="text-end pe-3">
                        <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 me-1" onclick="editFaculty(${cat.id})">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="deleteFaculty(${cat.id})">
                            <i class="bi bi-trash3"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center py-3 text-danger">Gagal memuat data: ${err.message}</td></tr>`;
    }
}

async function handleFacultySubmit(e) {
    e.preventDefault();

    const id = document.getElementById('facultyId').value;
    const name = document.getElementById('facultyName').value.trim();
    const code = document.getElementById('facultyCode').value.trim().toUpperCase();
    const sortOrder = parseInt(document.getElementById('facultySortOrder').value) || 0;

    const isEdit = Boolean(id);
    const payload = { name, code, sort_order: sortOrder };
    if (isEdit) payload.id = parseInt(id);

    const btn = document.getElementById('btnSaveFaculty');
    btn.disabled = true;

    try {
        const res = await fetch(API.categories, {
            method: isEdit ? 'PUT' : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.success) {
            resetFacultyForm();
            await loadFacultyTable();
            await loadData(); // refresh tabel utama & dropdown
            showToast('success', isEdit ? 'Fakultas / Unit berhasil diperbarui' : 'Fakultas / Unit baru berhasil ditambahkan');
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Gagal Menyimpan Fakultas',
                text: data.message || 'Terjadi kesalahan sistem',
                confirmButtonColor: '#ef4444'
            });
        }
    } catch (err) {
        Swal.fire({
            icon: 'error',
            title: 'Kesalahan Sistem',
            text: err.message,
            confirmButtonColor: '#ef4444'
        });
    } finally {
        btn.disabled = false;
    }
}

function editFaculty(id) {
    const cat = appState.categories.find(c => c.id === id);
    if (!cat) return;

    document.getElementById('facultyId').value = cat.id;
    document.getElementById('facultyName').value = cat.name || '';
    document.getElementById('facultyCode').value = cat.code || '';
    document.getElementById('facultySortOrder').value = cat.sort_order || 0;

    document.getElementById('facultyFormTitle').innerHTML = `
        <span class="text-primary"><i class="bi bi-pencil-square me-1"></i> Edit Fakultas: ${escapeHtml(cat.name)}</span>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetFacultyForm()">Batal Edit</button>
    `;
    document.getElementById('btnSaveFaculty').innerHTML = `<i class="bi bi-check2 me-1"></i> Perbarui Fakultas`;
    document.getElementById('facultyName').focus();
}

function resetFacultyForm() {
    document.getElementById('facultyForm').reset();
    document.getElementById('facultyId').value = '';
    document.getElementById('facultyFormTitle').innerHTML = `
        <span><i class="bi bi-plus-circle me-1 text-primary"></i> Tambah Fakultas / Unit Baru</span>
        <button type="button" class="btn btn-sm btn-outline-secondary d-none" id="btnCancelEditFaculty" onclick="resetFacultyForm()">Batal Edit</button>
    `;
    document.getElementById('btnSaveFaculty').innerHTML = `<i class="bi bi-check2 me-1"></i> Simpan Fakultas`;
}

async function deleteFaculty(id, fallbackName = '', fallbackCount = 0) {
    const cat = appState.categories.find(c => c.id === id) || { name: fallbackName, websites_count: fallbackCount };
    const name = cat.name || fallbackName || 'Fakultas / Unit';
    const count = parseInt(cat.websites_count !== undefined ? cat.websites_count : fallbackCount) || 0;

    if (count > 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Tidak Dapat Dihapus',
            html: `Fakultas / Unit <b>${escapeHtml(name)}</b> saat ini masih memuat <b>${count} website</b> yang dipantau.<br><br><span class="text-muted small">Silakan hapus atau pindahkan website ke unit lain terlebih dahulu sebelum menghapus fakultas ini.</span>`,
            confirmButtonText: 'Saya Mengerti',
            confirmButtonColor: '#3b82f6',
            customClass: {
                confirmButton: 'rounded-pill px-4'
            }
        });
        return;
    }

    const result = await Swal.fire({
        title: 'Hapus Fakultas / Unit?',
        html: `Apakah Anda yakin ingin menghapus <b>${escapeHtml(name)}</b>?<br><span class="text-muted small">Data fakultas/unit ini akan dihapus permanen dari sistem.</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: '<i class="bi bi-trash3 me-1"></i> Ya, Hapus',
        cancelButtonText: 'Batal',
        reverseButtons: true,
        focusCancel: true
    });

    if (!result.isConfirmed) return;

    try {
        const res = await fetch(API.categories, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const data = await res.json();

        if (data.success) {
            await loadFacultyTable();
            await loadData();
            showToast('success', `Fakultas "${escapeHtml(name)}" berhasil dihapus`);
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Gagal Menghapus',
                text: data.message || 'Terjadi kesalahan sistem',
                confirmButtonColor: '#ef4444'
            });
        }
    } catch (err) {
        Swal.fire({
            icon: 'error',
            title: 'Kesalahan Jaringan',
            text: err.message,
            confirmButtonColor: '#ef4444'
        });
    }
}

// --------------------------------------------------------------------------
// 2. CRUD PILIHAN JENJANG / LEVEL
// --------------------------------------------------------------------------

async function loadLevelTable() {
    const tbody = document.getElementById('levelTableBody');
    if (!tbody) return;

    try {
        const res = await fetch(API.levels);
        const json = await res.json();

        if (json.success && Array.isArray(json.data)) {
            appState.levels = json.data;
            populateLevelDropdown();

            if (json.data.length === 0) {
                tbody.innerHTML = `<tr><td colspan="5" class="text-center py-3 text-muted">Belum ada pilihan jenjang.</td></tr>`;
                return;
            }

            tbody.innerHTML = '';
            json.data.forEach((lvl) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="ps-3">
                        <span class="level-tag ${lvl.badge_style}">${escapeHtml(lvl.code)}</span>
                    </td>
                    <td class="fw-bold text-dark">${escapeHtml(lvl.name)}</td>
                    <td class="text-center text-muted">${lvl.sort_order || 0}</td>
                    <td class="text-center">
                        <span class="badge bg-secondary-subtle text-secondary px-2 py-1">${lvl.websites_count || 0} web</span>
                    </td>
                    <td class="text-end pe-3">
                        <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 me-1" onclick="editLevel(${lvl.id})">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="deleteLevel(${lvl.id})">
                            <i class="bi bi-trash3"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="5" class="text-center py-3 text-danger">Gagal memuat data: ${err.message}</td></tr>`;
    }
}

async function handleLevelSubmit(e) {
    e.preventDefault();

    const id = document.getElementById('levelId').value;
    const code = document.getElementById('levelCode').value.trim();
    const name = document.getElementById('levelName').value.trim();
    const badgeStyle = document.getElementById('levelBadgeStyle').value;
    const sortOrder = parseInt(document.getElementById('levelSortOrder').value) || 0;

    const isEdit = Boolean(id);
    const payload = { code, name, badge_style: badgeStyle, sort_order: sortOrder };
    if (isEdit) payload.id = parseInt(id);

    const btn = document.getElementById('btnSaveLevel');
    btn.disabled = true;

    try {
        const res = await fetch(API.levels, {
            method: isEdit ? 'PUT' : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.success) {
            resetLevelForm();
            await loadLevelTable();
            await loadData(); // refresh tabel utama & dropdown
            showToast('success', isEdit ? 'Pilihan jenjang berhasil diperbarui' : 'Pilihan jenjang baru berhasil ditambahkan');
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Gagal Menyimpan Jenjang',
                text: data.message || 'Terjadi kesalahan sistem',
                confirmButtonColor: '#ef4444'
            });
        }
    } catch (err) {
        Swal.fire({
            icon: 'error',
            title: 'Kesalahan Sistem',
            text: err.message,
            confirmButtonColor: '#ef4444'
        });
    } finally {
        btn.disabled = false;
    }
}

function editLevel(id) {
    const lvl = appState.levels.find(l => l.id === id);
    if (!lvl) return;

    document.getElementById('levelId').value = lvl.id;
    document.getElementById('levelCode').value = lvl.code || '';
    document.getElementById('levelName').value = lvl.name || '';
    document.getElementById('levelBadgeStyle').value = lvl.badge_style || 's1';
    document.getElementById('levelSortOrder').value = lvl.sort_order || 0;

    document.getElementById('levelFormTitle').innerHTML = `
        <span class="text-success"><i class="bi bi-pencil-square me-1"></i> Edit Jenjang: ${escapeHtml(lvl.code)}</span>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetLevelForm()">Batal Edit</button>
    `;
    document.getElementById('btnSaveLevel').innerHTML = `<i class="bi bi-check2 me-1"></i> Perbarui Jenjang`;
    document.getElementById('levelName').focus();
}

function resetLevelForm() {
    document.getElementById('levelForm').reset();
    document.getElementById('levelId').value = '';
    document.getElementById('levelFormTitle').innerHTML = `
        <span><i class="bi bi-plus-circle me-1 text-success"></i> Tambah Pilihan Jenjang Baru</span>
        <button type="button" class="btn btn-sm btn-outline-secondary d-none" id="btnCancelEditLevel" onclick="resetLevelForm()">Batal Edit</button>
    `;
    document.getElementById('btnSaveLevel').innerHTML = `<i class="bi bi-check2 me-1"></i> Simpan Jenjang`;
}

async function deleteLevel(id, fallbackCode = '', fallbackCount = 0) {
    const lvl = appState.levels.find(l => l.id === id) || { code: fallbackCode, websites_count: fallbackCount };
    const code = lvl.code || fallbackCode || 'Jenjang';
    const count = parseInt(lvl.websites_count !== undefined ? lvl.websites_count : fallbackCount) || 0;

    if (count > 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Tidak Dapat Dihapus',
            html: `Pilihan jenjang <b>${escapeHtml(code)}</b> saat ini sedang digunakan oleh <b>${count} website</b>.<br><br><span class="text-muted small">Harap ubah pilihan jenjang pada website terkait terlebih dahulu sebelum menghapus jenjang ini.</span>`,
            confirmButtonText: 'Saya Mengerti',
            confirmButtonColor: '#3b82f6',
            customClass: {
                confirmButton: 'rounded-pill px-4'
            }
        });
        return;
    }

    const result = await Swal.fire({
        title: 'Hapus Pilihan Jenjang?',
        html: `Apakah Anda yakin ingin menghapus pilihan jenjang <b>${escapeHtml(code)}</b>?<br><span class="text-muted small">Pilihan jenjang ini akan dihapus secara permanen dari daftar badge.</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: '<i class="bi bi-trash3 me-1"></i> Ya, Hapus',
        cancelButtonText: 'Batal',
        reverseButtons: true,
        focusCancel: true
    });

    if (!result.isConfirmed) return;

    try {
        const res = await fetch(API.levels, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const data = await res.json();

        if (data.success) {
            await loadLevelTable();
            await loadData();
            showToast('success', `Pilihan jenjang ${escapeHtml(code)} berhasil dihapus`);
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Gagal Menghapus',
                text: data.message || 'Terjadi kesalahan sistem',
                confirmButtonColor: '#ef4444'
            });
        }
    } catch (err) {
        Swal.fire({
            icon: 'error',
            title: 'Kesalahan Jaringan',
            text: err.message,
            confirmButtonColor: '#ef4444'
        });
    }
}

/* ==========================================================================
   EXPORT DATA MONITORING KE EXCEL (.XLSX) VIA SHEETJS
   ========================================================================== */

function getFilteredWebsites() {
    const query = (appState.searchQuery || '').toLowerCase().trim();
    const catFilter = appState.selectedCategory;
    const statusFilter = appState.selectedStatus;

    return (appState.rawData || []).filter(item => {
        // Filter kategori
        if (catFilter !== 'all' && item.category_id != catFilter) {
            return false;
        }

        // Filter status
        if (statusFilter === 'active' && item.last_status !== 'Aktif') return false;
        if (statusFilter === 'error' && item.last_status === 'Aktif') return false;

        // Filter search query
        if (query) {
            const cat = (appState.categories || []).find(c => c.id == item.category_id);
            const catName = cat ? cat.name.toLowerCase() : '';
            const match = item.name.toLowerCase().includes(query) ||
                          item.url.toLowerCase().includes(query) ||
                          catName.includes(query);
            if (!match) return false;
        }

        return true;
    });
}

async function exportToXlsx() {
    const totalAll = appState.rawData ? appState.rawData.length : 0;
    if (totalAll === 0) {
        Swal.fire({
            icon: 'info',
            title: 'Tidak Ada Data',
            text: 'Belum ada website yang terdaftar untuk diexport.',
            confirmButtonColor: '#3b82f6'
        });
        return;
    }

    const currentTabGroup = (appState.groupedData || []).find(g => String(g.id) === String(appState.selectedCategory));
    const currentTabName = appState.selectedCategory === 'all' 
        ? 'Semua Fakultas / Unit' 
        : (currentTabGroup ? currentTabGroup.name : 'Kategori Terpilih');

    const filteredList = getFilteredWebsites();
    const isFiltered = appState.selectedCategory !== 'all' || appState.selectedStatus !== 'all' || Boolean(appState.searchQuery);

    let exportCategory = 'all';
    let exportStatus = 'all';
    let exportSearch = '';
    let scopeName = 'Semua Fakultas / Unit';

    // Jika sedang dalam tampilan tab tertentu atau ada filter pencarian, tanyakan pilihan export
    if (isFiltered && filteredList.length !== totalAll) {
        const result = await Swal.fire({
            title: 'Export Laporan ke Excel (.xlsx)',
            html: `
                <p class="text-muted small mb-3">Pilih cakupan data yang ingin Anda unduh ke file spreadsheet Excel:</p>
                <div class="text-start p-3 bg-light rounded-3 border small mb-2">
                    <div class="mb-2 d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-funnel-fill text-primary me-2"></i><strong>Tampilan / Tab Aktif:</strong></span>
                        <span class="badge bg-primary rounded-pill">${filteredList.length} website</span>
                    </div>
                    <div class="text-muted small ps-3 mb-2">${escapeHtml(currentTabName)}</div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-collection-fill text-success me-2"></i><strong>Seluruh Data Sistem:</strong></span>
                        <span class="badge bg-success rounded-pill">${totalAll} website</span>
                    </div>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            denyButtonColor: '#0284c7',
            cancelButtonColor: '#64748b',
            confirmButtonText: `<i class="bi bi-file-earmark-spreadsheet me-1"></i> Export Data Aktif (${filteredList.length})`,
            showDenyButton: true,
            denyButtonText: `<i class="bi bi-collection me-1"></i> Export Semua (${totalAll})`,
            cancelButtonText: 'Batal',
            reverseButtons: true,
            customClass: {
                popup: 'rounded-4'
            }
        });

        if (result.isDismissed) return;

        if (result.isConfirmed) {
            exportCategory = appState.selectedCategory;
            exportStatus = appState.selectedStatus;
            exportSearch = appState.searchQuery || '';
            scopeName = currentTabName;
        } else if (result.isDenied) {
            exportCategory = 'all';
            exportStatus = 'all';
            exportSearch = '';
            scopeName = 'Semua Fakultas / Unit';
        }
    } else {
        exportCategory = appState.selectedCategory;
        exportStatus = appState.selectedStatus;
        exportSearch = appState.searchQuery || '';
        scopeName = currentTabName;
    }

    // Tampilkan modal loading pembuatan file Excel
    Swal.fire({
        title: 'Menyiapkan Laporan Excel...',
        html: `
            <div class="py-2 text-center">
                <div class="spinner-border text-success mb-3" role="status"></div>
                <div class="text-muted small">Sedang merakit spreadsheet OpenXML (.xlsx) dan menyimpan salinan ke folder Downloads...</div>
            </div>
        `,
        allowOutsideClick: false,
        showConfirmButton: false,
        customClass: { popup: 'rounded-4' }
    });

    try {
        const params = new URLSearchParams({
            action: 'generate',
            category: exportCategory,
            status: exportStatus,
            search: exportSearch,
            t: Date.now()
        });

        const res = await fetch(`api/export.php?${params.toString()}`);
        const data = await res.json();

        if (!data.success) {
            throw new Error(data.message || 'Gagal menghasilkan berkas Excel');
        }

        // Pemicu unduhan browser sebagai cadangan
        triggerNativeDownload(data.download_url, data.filename);

        // Tampilkan dialog sukses dengan tombol Buka Langsung di Excel
        Swal.fire({
            title: 'File Excel Berhasil Dibuat!',
            html: `
                <div class="text-center py-2">
                    <div class="mb-3 text-success">
                        <i class="bi bi-file-earmark-excel-fill" style="font-size: 3.8rem;"></i>
                    </div>
                    <p class="text-muted small mb-2">File laporan spreadsheet resmi telah siap dan tersimpan di folder <strong>Downloads</strong> Anda:</p>
                    <div class="p-2 mb-3 bg-light border rounded text-success fw-bold font-monospace small text-break">
                        <i class="bi bi-file-earmark-check me-1"></i> ${escapeHtml(data.filename)}
                    </div>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-success fw-bold py-2 rounded-3 shadow-sm d-flex align-items-center justify-content-center gap-2" onclick="openFileInExcel('${encodeURIComponent(data.filename)}')">
                            <i class="bi bi-box-arrow-up-right fs-5"></i> Buka Langsung di Microsoft Excel
                        </button>
                        <button type="button" class="btn btn-outline-primary fw-semibold py-2 rounded-3 d-flex align-items-center justify-content-center gap-2" onclick="openFolderInExplorer('${encodeURIComponent(data.filename)}')">
                            <i class="bi bi-folder2-open fs-5"></i> Tampilkan di Folder Downloads
                        </button>
                    </div>
                    <div class="mt-3 text-muted" style="font-size: 0.78rem;">
                        <i class="bi bi-check-circle-fill text-success me-1"></i> Format: Microsoft Excel (.xlsx) murni dengan 2 Sheet (Status & KPI).
                    </div>
                </div>
            `,
            icon: null,
            showConfirmButton: true,
            confirmButtonText: 'Selesai & Tutup',
            confirmButtonColor: '#64748b',
            customClass: { popup: 'rounded-4' }
        });

    } catch (err) {
        Swal.fire({
            icon: 'error',
            title: 'Gagal Membuat File Excel',
            text: err.message,
            confirmButtonColor: '#ef4444'
        });
    }
}

/**
 * Memicu pembukaan file Excel langsung di aplikasi Microsoft Excel
 */
async function openFileInExcel(filename) {
    showToast('info', 'Membuka Microsoft Excel...');
    try {
        const res = await fetch(`api/export.php?action=open&file=${filename}`);
        const json = await res.json();
        if (json.success) {
            showToast('success', 'Microsoft Excel berhasil dibuka!');
        } else {
            showToast('error', json.message);
        }
    } catch (err) {
        showToast('error', 'Gagal membuka Excel: ' + err.message);
    }
}

/**
 * Membuka Windows Explorer dan menyorot file di folder Downloads
 */
async function openFolderInExplorer(filename) {
    showToast('info', 'Membuka File Explorer...');
    try {
        const res = await fetch(`api/export.php?action=folder&file=${filename}`);
        const json = await res.json();
        if (json.success) {
            showToast('success', 'Folder Downloads berhasil dibuka!');
        } else {
            showToast('error', json.message);
        }
    } catch (err) {
        showToast('error', 'Gagal membuka explorer: ' + err.message);
    }
}

/**
 * Memicu unduhan file langsung dengan HTTP header Content-Disposition
 */
function triggerNativeDownload(url, filename) {
    const a = document.createElement('a');
    a.style.display = 'none';
    a.href = url;
    if (filename) a.setAttribute('download', filename);
    document.body.appendChild(a);
    a.click();

    setTimeout(() => {
        if (a.parentNode) {
            document.body.removeChild(a);
        }
    }, 2000);
}

/**
 * Toggle Tampilkan / Sembunyikan Sidebar Panduan Status
 * Di layar mobile (< 992px): Membuka / Menutup Bootstrap Offcanvas Drawer
 * Di layar desktop (>= 992px): Membuka / Menutup Kolom Sidebar Kanan
 */
function toggleGuideSidebar() {
    if (window.innerWidth < 992) {
        const offcanvasEl = document.getElementById('guideSidebarOffcanvas');
        if (offcanvasEl) {
            const bsOffcanvas = bootstrap.Offcanvas.getOrCreateInstance(offcanvasEl);
            bsOffcanvas.toggle();
        }
        return;
    }

    const sidebarCol = document.getElementById('guideSidebarColumn');
    const tableCol = document.getElementById('tableColumn');
    const btnOpenGuide = document.getElementById('btnOpenGuide');

    if (!sidebarCol || !tableCol) return;

    const isHidden = sidebarCol.style.display === 'none';

    if (isHidden) {
        // Tampilkan kembali sidebar
        sidebarCol.style.display = 'block';
        tableCol.className = 'col-12 col-xl-9 col-lg-8';
        if (btnOpenGuide) {
            btnOpenGuide.classList.remove('btn-outline-primary');
            btnOpenGuide.classList.add('btn-light');
        }
        localStorage.setItem('showGuideSidebar', '1');
    } else {
        // Sembunyikan sidebar (Tabel menjadi Full Width)
        sidebarCol.style.display = 'none';
        tableCol.className = 'col-12';
        if (btnOpenGuide) {
            btnOpenGuide.classList.remove('btn-light');
            btnOpenGuide.classList.add('btn-outline-primary');
        }
        localStorage.setItem('showGuideSidebar', '0');
    }
}

// Inisialisasi status tampilan sidebar dari localStorage (Hanya untuk desktop)
function initGuideSidebarState() {
    if (window.innerWidth < 992) return;
    const savedState = localStorage.getItem('showGuideSidebar');
    // Jika pengguna sebelumnya memilih menyembunyikan
    if (savedState === '0') {
        const sidebarCol = document.getElementById('guideSidebarColumn');
        const tableCol = document.getElementById('tableColumn');
        const btnOpenGuide = document.getElementById('btnOpenGuide');
        if (sidebarCol && tableCol) {
            sidebarCol.style.display = 'none';
            tableCol.className = 'col-12';
            if (btnOpenGuide) {
                btnOpenGuide.classList.remove('btn-light');
                btnOpenGuide.classList.add('btn-outline-primary');
            }
        }
    }
}

// Hapus teks pencarian dengan 1 klik (Kemudahan Mobile)
function clearSearch() {
    const searchInput = document.getElementById('searchInput');
    const btnClearSearch = document.getElementById('btnClearSearch');
    if (searchInput) {
        searchInput.value = '';
        searchInput.focus();
    }
    if (btnClearSearch) {
        btnClearSearch.classList.add('d-none');
    }
    appState.searchQuery = '';
    renderTable();
}

// Scroll mulus kembali ke atas halaman (Kemudahan Mobile)
function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Inisialisasi tombol scroll to top
function initScrollToTop() {
    const btn = document.getElementById('btnScrollTop');
    window.addEventListener('scroll', () => {
        if (!btn) return;
        if (window.scrollY > 300) {
            btn.style.display = 'flex';
        } else {
            btn.style.display = 'none';
        }
    }, { passive: true });
}

/**
 * ==========================================================================
 * SISTEM AUTENTIKASI ADMINISTRATOR
 * ==========================================================================
 */

// Buka Modal Login
function openLoginModal() {
    const modalEl = document.getElementById('loginModal');
    if (!modalEl) return;
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const form = document.getElementById('loginForm');
    if (form) form.reset();
    modal.show();
    setTimeout(() => {
        const u = document.getElementById('loginUsername');
        if (u) u.focus();
    }, 400);
}

// Toggle lihat / sembunyikan password pada input login
function togglePasswordVisibility() {
    const passInput = document.getElementById('loginPassword');
    const icon = document.getElementById('togglePasswordIcon');
    if (!passInput || !icon) return;

    if (passInput.type === 'password') {
        passInput.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        passInput.type = 'password';
        icon.className = 'bi bi-eye';
    }
}

// Proses submit form login admin via AJAX
async function handleLoginSubmit(event) {
    event.preventDefault();
    const btn = document.getElementById('btnLoginSubmit');
    const form = event.target;
    const formData = new FormData(form);
    const username = formData.get('username');
    const password = formData.get('password');

    if (!username || !password) {
        Swal.fire({
            icon: 'warning',
            title: 'Perhatian',
            text: 'Username dan password wajib diisi.',
            confirmButtonColor: '#3b82f6'
        });
        return;
    }

    if (btn) {
        btn.disabled = true;
        btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>Memverifikasi...`;
    }

    try {
        const res = await fetch('api/auth.php?action=login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, password })
        });
        const data = await res.json();

        if (data.success) {
            const modalEl = document.getElementById('loginModal');
            if (modalEl) {
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();
            }

            Swal.fire({
                icon: 'success',
                title: 'Login Berhasil!',
                text: `Selamat datang kembali, ${data.user.name || 'Administrator'}!`,
                timer: 1200,
                showConfirmButton: false
            }).then(() => {
                window.location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Gagal Masuk',
                text: data.message || 'Username atau password salah.',
                confirmButtonColor: '#ef4444'
            });
        }
    } catch (err) {
        Swal.fire({
            icon: 'error',
            title: 'Kesalahan Sistem',
            text: err.message,
            confirmButtonColor: '#ef4444'
        });
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = `<i class="bi bi-box-arrow-in-right fs-5"></i> <span>Masuk ke Akun</span>`;
        }
    }
}

// Proses logout admin
async function handleLogout() {
    const result = await Swal.fire({
        title: 'Konfirmasi Logout',
        text: 'Apakah Anda yakin ingin keluar dari sesi administrator?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: '<i class="bi bi-box-arrow-right me-1"></i> Ya, Logout',
        cancelButtonText: 'Batal',
        customClass: { popup: 'rounded-4' }
    });

    if (result.isConfirmed) {
        try {
            const res = await fetch('api/auth.php?action=logout', { method: 'POST' });
            const data = await res.json();
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil Logout',
                    text: 'Anda telah keluar dari mode admin.',
                    timer: 1000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.reload();
                });
            }
        } catch (err) {
            window.location.reload();
        }
    }
}


