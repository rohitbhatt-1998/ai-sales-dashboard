<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/LeadService.php';
require_once __DIR__ . '/../includes/layout.php';

Auth::check();

$filters = [
    'status' => sanitize($_GET['status'] ?? ''),
    'city'   => sanitize($_GET['city'] ?? ''),
    'search' => sanitize($_GET['search'] ?? ''),
];

$page    = max(1, (int)($_GET['page'] ?? 1));
$result  = LeadService::getLeads($filters, $page, 25);
$leads   = $result['leads'];
$pg      = $result['pagination'];

// Get unique cities for filter
$cities = DB::fetchAll('SELECT DISTINCT city FROM leads WHERE city IS NOT NULL AND city != "" ORDER BY city');

$statuses = ['new', 'calling', 'connected', 'no_answer', 'called', 'hot', 'warm', 'cold'];

renderHead('Leads Management');
?>

<!-- Toolbar -->
<div class="flex justify-between items-center mb-4 flex-wrap gap-3">
    <div class="flex gap-2">
        <button class="btn btn-primary" onclick="openAddLead()">+ Add Lead</button>
        <button class="btn btn-secondary" onclick="openImportCsv()">📂 Import CSV</button>
    </div>
    <div class="flex gap-2 items-center">
        <button class="btn btn-success" id="bulkCallBtn" onclick="startBulkCall()" disabled>
            📞 Start AI Calling (<span id="selectedCount">0</span>)
        </button>
    </div>
</div>

<!-- Call Status Bar -->
<div id="callStatusBar" style="display:none"></div>

<!-- Leads Card -->
<div class="card">
    <!-- Filters -->
    <div class="filter-bar">
        <div class="search-box">
            <span class="search-icon">🔍</span>
            <input type="text" class="form-control" id="searchInput"
                placeholder="Search name, phone, email…"
                value="<?= h($filters['search']) ?>"/>
        </div>

        <select class="form-control" id="statusFilter">
            <option value="">All Statuses</option>
            <?php foreach ($statuses as $s): ?>
            <option value="<?= $s ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>>
                <?= ucwords(str_replace('_', ' ', $s)) ?>
            </option>
            <?php endforeach; ?>
        </select>

        <select class="form-control" id="cityFilter">
            <option value="">All Cities</option>
            <?php foreach ($cities as $c): ?>
            <option value="<?= h($c['city']) ?>" <?= $filters['city'] === $c['city'] ? 'selected' : '' ?>>
                <?= h($c['city']) ?>
            </option>
            <?php endforeach; ?>
        </select>

        <button class="btn btn-secondary btn-sm" onclick="applyFilters()">Filter</button>
        <button class="btn btn-secondary btn-sm" onclick="clearFilters()">Clear</button>
    </div>

    <!-- Table -->
    <div class="table-wrapper">
        <table id="leadsTable">
            <thead>
                <tr>
                    <th class="col-check">
                        <input type="checkbox" id="selectAll" title="Select All"/>
                    </th>
                    <th>#</th>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>City</th>
                    <th>Company</th>
                    <th>Status</th>
                    <th>Score</th>
                    <th>Calls</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="leadsBody">
                <?php if (empty($leads)): ?>
                <tr>
                    <td colspan="10">
                        <div class="empty-state">
                            <div class="empty-state-icon">👥</div>
                            <h3>No Leads Found</h3>
                            <p>Add leads manually or import a CSV file to get started.</p>
                            <button class="btn btn-primary" onclick="openAddLead()">+ Add First Lead</button>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($leads as $i => $lead): ?>
                <tr data-id="<?= $lead['id'] ?>">
                    <td class="col-check">
                        <input type="checkbox" class="lead-check" value="<?= $lead['id'] ?>" />
                    </td>
                    <td class="text-muted text-sm"><?= ($pg['offset'] + $i + 1) ?></td>
                    <td>
                        <div class="font-medium"><?= h($lead['name']) ?></div>
                        <?php if ($lead['email']): ?>
                        <div class="text-sm text-muted"><?= h($lead['email']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="font-medium"><?= h($lead['phone']) ?></td>
                    <td><?= h($lead['city']) ?: '—' ?></td>
                    <td class="text-sm"><?= h($lead['company']) ?: '—' ?></td>
                    <td><?= statusBadge($lead['status']) ?></td>
                    <td>
                        <?php if ($lead['score'] > 0): ?>
                        <div class="progress-bar-wrap" style="width:50px">
                            <div class="progress-bar <?= $lead['status'] ?>" style="width:<?= min(100, $lead['score']) ?>%"></div>
                        </div>
                        <?php else: echo '—'; endif; ?>
                    </td>
                    <td class="text-sm"><?= (int)$lead['call_count'] ?></td>
                    <td>
                        <div class="table-actions">
                            <button class="btn btn-sm btn-success" onclick="callLead(<?= (int)$lead['id'] ?>, '<?= h(addslashes($lead['name'])) ?>')">
                                📞
                            </button>
                            <button class="btn btn-sm btn-secondary" onclick="editLead(<?= (int)$lead['id'] ?>)">
                                ✏️
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deleteLead(<?= (int)$lead['id'] ?>, '<?= h(addslashes($lead['name'])) ?>')">
                                🗑
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($pg['total_pages'] > 1): ?>
    <div class="pagination">
        <div class="pagination-info">
            Showing <?= $pg['offset'] + 1 ?>–<?= min($pg['offset'] + $pg['per_page'], $pg['total']) ?>
            of <?= number_format($pg['total']) ?> leads
        </div>
        <?php if ($pg['has_prev']): ?>
        <button class="page-btn" onclick="gotoPage(<?= $pg['current'] - 1 ?>)">‹</button>
        <?php endif; ?>
        <?php for ($p = max(1, $pg['current'] - 2); $p <= min($pg['total_pages'], $pg['current'] + 2); $p++): ?>
        <button class="page-btn <?= $p === $pg['current'] ? 'active' : '' ?>" onclick="gotoPage(<?= $p ?>)"><?= $p ?></button>
        <?php endfor; ?>
        <?php if ($pg['has_next']): ?>
        <button class="page-btn" onclick="gotoPage(<?= $pg['current'] + 1 ?>)">›</button>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
// ---- Checkbox / Bulk Select ----
const selectAll = document.getElementById('selectAll');
const bulkCallBtn = document.getElementById('bulkCallBtn');
const selectedCount = document.getElementById('selectedCount');

function updateBulkBtn() {
    const checked = document.querySelectorAll('.lead-check:checked');
    selectedCount.textContent = checked.length;
    bulkCallBtn.disabled = checked.length === 0;
}

selectAll.addEventListener('change', () => {
    document.querySelectorAll('.lead-check').forEach(cb => cb.checked = selectAll.checked);
    updateBulkBtn();
});

document.addEventListener('change', e => {
    if (e.target.classList.contains('lead-check')) updateBulkBtn();
});

// ---- Filters ----
function applyFilters() {
    const s = document.getElementById('searchInput').value;
    const st = document.getElementById('statusFilter').value;
    const ct = document.getElementById('cityFilter').value;
    const params = new URLSearchParams({ search: s, status: st, city: ct, page: 1 });
    window.location.href = '?' + params.toString();
}

function clearFilters() {
    window.location.href = '<?= BASE_URL ?>/pages/leads.php';
}

document.getElementById('searchInput').addEventListener('keydown', e => {
    if (e.key === 'Enter') applyFilters();
});

function gotoPage(p) {
    const params = new URLSearchParams(window.location.search);
    params.set('page', p);
    window.location.search = params.toString();
}

// ---- Add Lead Modal ----
function openAddLead(lead = null) {
    const isEdit = lead !== null;
    const body = `
        <form id="leadForm">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Name <span class="required">*</span></label>
                    <input name="name" class="form-control" value="${escHtml(lead?.name || '')}" required placeholder="Full Name"/>
                    <div class="form-error" id="err-name"></div>
                </div>
                <div class="form-group">
                    <label class="form-label">Phone <span class="required">*</span></label>
                    <input name="phone" class="form-control" value="${escHtml(lead?.phone || '')}" required placeholder="+91 98765 43210"/>
                    <div class="form-error" id="err-phone"></div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input name="email" type="email" class="form-control" value="${escHtml(lead?.email || '')}" placeholder="email@example.com"/>
                </div>
                <div class="form-group">
                    <label class="form-label">City</label>
                    <input name="city" class="form-control" value="${escHtml(lead?.city || '')}" placeholder="Mumbai"/>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Company</label>
                    <input name="company" class="form-control" value="${escHtml(lead?.company || '')}" placeholder="Company Name"/>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control">${escHtml(lead?.notes || '')}</textarea>
            </div>
        </form>`;

    const footer = `
        <button class="btn btn-secondary" onclick="Modal.close()">Cancel</button>
        <button class="btn btn-primary" onclick="submitLeadForm(${isEdit ? lead.id : 'null'})">
            ${isEdit ? 'Update Lead' : 'Add Lead'}
        </button>`;

    Modal.open(isEdit ? 'Edit Lead' : 'Add New Lead', body, footer);
}

async function editLead(id) {
    const data = await apiGet(`${BASE_URL}/api/leads.php?action=get&id=${id}`);
    if (data.success) openAddLead(data.data);
    else toast('Failed to load lead.', 'error');
}

async function submitLeadForm(id = null) {
    const form = document.getElementById('leadForm');
    const fd = new FormData(form);
    const payload = Object.fromEntries(fd.entries());
    payload.csrf_token = CSRF_TOKEN;

    ['name','phone'].forEach(f => {
        const el = document.getElementById('err-' + f);
        if (el) el.textContent = '';
        const input = form.querySelector(`[name="${f}"]`);
        if (input) input.classList.remove('error');
    });

    let res;
    if (id) {
        const resp = await fetch(`${BASE_URL}/api/leads.php?id=${id}`, {
            method: 'PUT',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        res = await resp.json();
    } else {
        res = await apiPost(`${BASE_URL}/api/leads.php`, payload);
    }

    if (res.success) {
        toast(id ? '✅ Lead updated.' : '✅ Lead added.', 'success');
        Modal.close();
        setTimeout(() => location.reload(), 800);
    } else if (res.errors) {
        Object.entries(res.errors).forEach(([field, msg]) => {
            const el = document.getElementById('err-' + field);
            if (el) el.textContent = msg;
            const input = form.querySelector(`[name="${field}"]`);
            if (input) input.classList.add('error');
        });
    } else {
        toast(res.error || 'Error saving lead.', 'error');
    }
}

// ---- Delete Lead ----
async function deleteLead(id, name) {
    if (!confirm(`Delete lead "${name}"? This will also remove all call logs.`)) return;
    const res = await fetch(`${BASE_URL}/api/leads.php?id=${id}`, {
        method: 'DELETE',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ csrf_token: CSRF_TOKEN })
    });
    const data = await res.json();
    if (data.success) {
        toast('Lead deleted.', 'success');
        document.querySelector(`tr[data-id="${id}"]`)?.remove();
    } else {
        toast('Failed to delete lead.', 'error');
    }
}

// ---- CSV Import ----
function openImportCsv() {
    const body = `
        <div class="upload-zone" id="uploadZone" onclick="document.getElementById('csvFile').click()">
            <div class="upload-zone-icon">📂</div>
            <div class="upload-zone-text">
                <strong>Click to upload</strong> or drag & drop<br/>
                CSV file with columns: name, phone, email, city, company
            </div>
            <input type="file" id="csvFile" accept=".csv,text/csv" style="display:none"/>
        </div>
        <div class="mt-3 text-sm text-muted">
            ℹ️ First row must be headers. Duplicate phone numbers will be skipped. Max <?= MAX_CSV_ROWS ?> rows.
        </div>
        <div id="csvResult" style="margin-top:12px"></div>
    `;
    Modal.open('Import Leads from CSV', body,
        '<button class="btn btn-secondary" onclick="Modal.close()">Close</button>' +
        '<button class="btn btn-primary" id="importBtn" onclick="submitCsv()">Import</button>'
    );

    const zone = document.getElementById('uploadZone');
    const fileInput = document.getElementById('csvFile');

    fileInput.addEventListener('change', () => {
        if (fileInput.files[0]) {
            zone.querySelector('.upload-zone-text').innerHTML =
                `📄 <strong>${escHtml(fileInput.files[0].name)}</strong><br/><span style="color:var(--success)">Ready to import</span>`;
        }
    });

    zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('dragging'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('dragging'));
    zone.addEventListener('drop', e => {
        e.preventDefault();
        zone.classList.remove('dragging');
        const file = e.dataTransfer.files[0];
        if (file) {
            const dt = new DataTransfer();
            dt.items.add(file);
            fileInput.files = dt.files;
            fileInput.dispatchEvent(new Event('change'));
        }
    });
}

async function submitCsv() {
    const fileInput = document.getElementById('csvFile');
    if (!fileInput.files[0]) { toast('Please select a CSV file.', 'warning'); return; }

    const btn = document.getElementById('importBtn');
    loading(btn, true);

    const fd = new FormData();
    fd.append('csv_file', fileInput.files[0]);
    fd.append('csrf_token', CSRF_TOKEN);

    const resp = await fetch(`${BASE_URL}/api/leads.php?action=import_csv`, { method: 'POST', body: fd });
    const data = await resp.json();
    loading(btn, false);

    const resultDiv = document.getElementById('csvResult');
    if (data.success) {
        resultDiv.innerHTML = `<div class="alert alert-success">
            ✅ Import complete! <strong>${data.imported}</strong> imported, <strong>${data.skipped}</strong> skipped.
            ${data.errors.length ? '<br/><small>' + data.errors.map(e => escHtml(e)).join('<br/>') + '</small>' : ''}
        </div>`;
        setTimeout(() => location.reload(), 2500);
    } else {
        resultDiv.innerHTML = `<div class="alert alert-error">✗ ${escHtml(data.error || 'Import failed')}</div>`;
    }
}

// ---- Single Call ----
async function callLead(leadId, name) {
    if (!confirm(`Start AI call for ${name}?`)) return;
    const btn = event.target;
    loading(btn, true);

    const bar = document.getElementById('callStatusBar');
    bar.className = 'call-status-bar calling';
    bar.style.display = 'flex';
    bar.innerHTML = '<div class="call-spinner"></div> Calling ' + escHtml(name) + '…';

    try {
        const res = await apiPost(`${BASE_URL}/api/calls.php?action=single`, { lead_id: leadId });
        if (res.success) {
            const provider = res.provider || 'system';
            if (provider === 'twilio') {
                bar.className = 'call-status-bar connected';
                bar.innerHTML = `✅ Call to ${escHtml(name)} started via Twilio. Status will update from callbacks shortly.`;
                toast('Call started for ' + name, 'success');
            } else {
                bar.className = 'call-status-bar ' + (res.outcome === 'connected' ? 'completed' : 'failed');
                bar.innerHTML = `✅ Call to ${escHtml(name)} completed. Outcome: <strong>${res.outcome}</strong>. Score: <strong>${res.score || '—'}</strong>`;
                toast('Call completed for ' + name, 'success');
            }
            setTimeout(() => location.reload(), 3000);
        } else {
            bar.className = 'call-status-bar failed';
            bar.innerHTML = '✗ Call failed: ' + escHtml(res.error || 'Unknown error');
            toast('Call failed', 'error');
        }
    } catch (e) {
        bar.style.display = 'none';
        toast('Network error.', 'error');
    }
    loading(btn, false);
}

// ---- Bulk Call ----
async function startBulkCall() {
    const checked = [...document.querySelectorAll('.lead-check:checked')];
    const leadIds = checked.map(cb => parseInt(cb.value));
    if (!leadIds.length) return;

    if (!confirm(`Start AI calling for ${leadIds.length} selected lead(s)?`)) return;

    const btn = document.getElementById('bulkCallBtn');
    loading(btn, true);

    const bar = document.getElementById('callStatusBar');
    bar.className = 'call-status-bar calling';
    bar.style.display = 'flex';
    bar.innerHTML = `<div class="call-spinner"></div> Adding ${leadIds.length} leads to call queue…`;

    try {
        const res = await apiPost(`${BASE_URL}/api/calls.php?action=bulk`, { lead_ids: leadIds });
        if (res.success) {
            bar.className = 'call-status-bar completed';
            bar.innerHTML = `✅ ${res.queued} leads added to queue. Calls are processing sequentially.`;
            toast(res.message, 'success');
            setTimeout(() => location.reload(), 3000);
        } else {
            bar.className = 'call-status-bar failed';
            bar.innerHTML = '✗ ' + escHtml(res.error);
            toast(res.error, 'error');
        }
    } catch (e) {
        toast('Network error.', 'error');
    }
    loading(btn, false);
}
</script>

<?php renderFoot(); ?>
