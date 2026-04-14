<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/CallService.php';
require_once __DIR__ . '/../includes/layout.php';

Auth::check();

renderHead('Call Queue');
?>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:16px;margin-bottom:20px" id="queueMetrics">
    <div class="metric-card info">
        <div class="metric-icon">🔄</div>
        <div class="metric-value" id="qm-total">—</div>
        <div class="metric-label">Total in Queue</div>
    </div>
    <div class="metric-card warm">
        <div class="metric-icon">⏳</div>
        <div class="metric-value" id="qm-pending">—</div>
        <div class="metric-label">Pending</div>
    </div>
    <div class="metric-card">
        <div class="metric-icon">⚡</div>
        <div class="metric-value" id="qm-processing">—</div>
        <div class="metric-label">Processing</div>
    </div>
    <div class="metric-card success">
        <div class="metric-icon">✅</div>
        <div class="metric-value" id="qm-done">—</div>
        <div class="metric-label">Done</div>
    </div>
    <div class="metric-card hot">
        <div class="metric-icon">✗</div>
        <div class="metric-value" id="qm-failed">—</div>
        <div class="metric-label">Failed</div>
    </div>
</div>

<div class="flex gap-2 mb-4">
    <button class="btn btn-primary" onclick="processNext(this)">▶ Process Next Call</button>
    <button class="btn btn-warning" onclick="retryFailed(this)">🔄 Retry Failed</button>
    <button class="btn btn-secondary" onclick="loadQueue()">↻ Refresh</button>
</div>

<div id="processResult" style="margin-bottom:16px"></div>

<div class="card">
    <div class="card-header">
        <span class="card-title">📋 Queue Items</span>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Lead</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th>Attempt</th>
                    <th>Queued At</th>
                    <th>Processed At</th>
                </tr>
            </thead>
            <tbody id="queueBody">
                <tr><td colspan="7"><div class="page-spinner"><div class="spinner"></div></div></td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
async function loadQueue() {
    // Load stats
    const statsData = await apiGet(`${BASE_URL}/api/calls.php?action=queue_stats`);
    if (statsData.success) {
        const s = statsData.data;
        document.getElementById('qm-total').textContent = s.total;
        document.getElementById('qm-pending').textContent = s.pending;
        document.getElementById('qm-processing').textContent = s.processing;
        document.getElementById('qm-done').textContent = s.done;
        document.getElementById('qm-failed').textContent = s.failed;
    }

    // Load queue items
    const data = await apiGet(`${BASE_URL}/api/queue_items.php`);
    const tbody = document.getElementById('queueBody');

    if (!data.success || !data.data.length) {
        tbody.innerHTML = `<tr><td colspan="7">
            <div class="empty-state">
                <div class="empty-state-icon">🔄</div>
                <h3>Queue is Empty</h3>
                <p>Select leads in the Leads page and click "Start AI Calling" to add them here.</p>
            </div>
        </td></tr>`;
        return;
    }

    tbody.innerHTML = data.data.map((item, i) => `
        <tr>
            <td class="text-sm text-muted">${i + 1}</td>
            <td>
                <div class="font-medium">${escHtml(item.lead_name)}</div>
            </td>
            <td class="font-medium">${escHtml(item.phone)}</td>
            <td>${statusBadgeHtml(item.status)}</td>
            <td class="text-sm">${item.attempt} / <?= QUEUE_MAX_RETRIES ?></td>
            <td class="text-sm text-muted">${escHtml(item.created_at)}</td>
            <td class="text-sm text-muted">${escHtml(item.processed_at || '—')}</td>
        </tr>
    `).join('');
}

async function processNext(btn) {
    loading(btn, true);
    const res = await apiPost(`${BASE_URL}/api/calls.php?action=process_queue`, {});
    loading(btn, false);

    const div = document.getElementById('processResult');
    if (res.status === 'idle') {
        div.innerHTML = `<div class="alert alert-info">ℹ️ Queue is empty. Nothing to process.</div>`;
    } else if (res.success) {
        div.innerHTML = `<div class="alert alert-success">✅ Call processed. Outcome: <strong>${res.outcome || 'done'}</strong></div>`;
        loadQueue();
    } else {
        div.innerHTML = `<div class="alert alert-error">✗ ${escHtml(res.error || 'Processing failed')}</div>`;
    }
    setTimeout(() => { div.innerHTML = ''; }, 5000);
}

async function retryFailed(btn) {
    loading(btn, true);
    const res = await apiPost(`${BASE_URL}/api/calls.php?action=retry_failed`, {});
    loading(btn, false);
    toast(res.success ? `✅ ${res.retried} calls re-queued for retry.` : 'Retry failed.', res.success ? 'success' : 'error');
    loadQueue();
}

// Auto-refresh every 10s
document.addEventListener('DOMContentLoaded', () => {
    loadQueue();
    setInterval(loadQueue, 10000);
});
</script>

<?php renderFoot(); ?>
