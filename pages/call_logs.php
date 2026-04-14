<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/layout.php';

Auth::check();

renderHead('Call Logs');
?>

<div class="card">
    <!-- Filters -->
    <div class="filter-bar">
        <div class="search-box">
            <span class="search-icon">🔍</span>
            <input type="text" class="form-control" id="searchInput" placeholder="Search lead name or phone…"/>
        </div>
        <select class="form-control" id="statusFilter">
            <option value="">All Statuses</option>
            <option value="completed">Completed</option>
            <option value="no_answer">No Answer</option>
            <option value="calling">Calling</option>
            <option value="failed">Failed</option>
        </select>
        <input type="date" class="form-control" id="dateFilter" style="width:150px"/>
        <select class="form-control" id="scoreFilter">
            <option value="">All Scores</option>
            <option value="hot">🔥 Hot</option>
            <option value="warm">🌤 Warm</option>
            <option value="cold">❄️ Cold</option>
        </select>
        <button class="btn btn-secondary btn-sm" onclick="loadLogs(1)">Filter</button>
        <button class="btn btn-secondary btn-sm" onclick="clearFilters()">Clear</button>
    </div>

    <!-- Table -->
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Lead</th>
                    <th>Status</th>
                    <th>Score</th>
                    <th>Duration</th>
                    <th>Summary</th>
                    <th>Called At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="logsBody">
                <tr><td colspan="8"><div class="page-spinner"><div class="spinner"></div></div></td></tr>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div id="paginationBar" class="pagination" style="display:none"></div>
</div>

<script>
let currentPage = 1;

function clearFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('statusFilter').value = '';
    document.getElementById('dateFilter').value = '';
    document.getElementById('scoreFilter').value = '';
    loadLogs(1);
}

async function loadLogs(page = 1) {
    currentPage = page;
    const search = document.getElementById('searchInput').value;
    const status = document.getElementById('statusFilter').value;
    const date   = document.getElementById('dateFilter').value;
    const score  = document.getElementById('scoreFilter').value;

    const params = new URLSearchParams({ action: 'logs', page, status, date });
    const resp = await apiGet(`${BASE_URL}/api/calls.php?${params}`);
    if (!resp.success) { toast('Failed to load logs.', 'error'); return; }

    const logs = resp.data.logs;
    const pg   = resp.data.pagination;
    const tbody = document.getElementById('logsBody');

    if (!logs.length) {
        tbody.innerHTML = `<tr><td colspan="8">
            <div class="empty-state"><div class="empty-state-icon">📋</div><h3>No Call Logs</h3><p>Calls will appear here after you start the AI calling system.</p></div>
        </td></tr>`;
        document.getElementById('paginationBar').style.display = 'none';
        return;
    }

    tbody.innerHTML = logs.map((log, i) => {
        const scoreHtml = log.lead_score
            ? `<span class="score-chip ${log.lead_score}">${log.lead_score === 'hot' ? '🔥' : log.lead_score === 'warm' ? '🌤' : '❄️'} ${cap(log.lead_score)}</span>`
            : '—';
        const summary = log.summary ? truncate(log.summary, 60) : '<em class="text-muted">—</em>';
        return `<tr>
            <td class="text-sm text-muted">${(pg.offset + i + 1)}</td>
            <td>
                <div class="font-medium">${escHtml(log.lead_name)}</div>
                <div class="text-sm text-muted">${escHtml(log.lead_phone)}</div>
            </td>
            <td>${statusBadgeHtml(log.status)}</td>
            <td>${scoreHtml}</td>
            <td class="font-medium">${formatDuration(parseInt(log.duration))}</td>
            <td class="text-sm text-muted" style="max-width:200px">${escHtml(summary)}</td>
            <td class="text-sm text-muted">${escHtml(log.created_at)}</td>
            <td>
                <button class="btn btn-sm btn-secondary" onclick="viewCallLog(${log.id})">
                    📋 View
                </button>
            </td>
        </tr>`;
    }).join('');

    // Pagination
    const pbar = document.getElementById('paginationBar');
    if (pg.total_pages > 1) {
        pbar.style.display = 'flex';
        let pHtml = `<div class="pagination-info">Showing ${pg.offset + 1}–${Math.min(pg.offset + pg.per_page, pg.total)} of ${pg.total}</div>`;
        if (pg.has_prev) pHtml += `<button class="page-btn" onclick="loadLogs(${pg.current - 1})">‹</button>`;
        for (let p = Math.max(1, pg.current - 2); p <= Math.min(pg.total_pages, pg.current + 2); p++) {
            pHtml += `<button class="page-btn ${p === pg.current ? 'active' : ''}" onclick="loadLogs(${p})">${p}</button>`;
        }
        if (pg.has_next) pHtml += `<button class="page-btn" onclick="loadLogs(${pg.current + 1})">›</button>`;
        pbar.innerHTML = pHtml;
    } else {
        pbar.style.display = 'none';
    }
}

function cap(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }
function truncate(s, n) { return s.length > n ? s.substring(0, n) + '…' : s; }

document.getElementById('searchInput').addEventListener('keydown', e => { if (e.key === 'Enter') loadLogs(1); });

document.addEventListener('DOMContentLoaded', () => loadLogs(1));
</script>

<?php renderFoot(); ?>
