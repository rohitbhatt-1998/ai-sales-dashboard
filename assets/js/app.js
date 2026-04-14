/**
 * AI Sales Calling Assistant — Core Frontend JS
 */

// -------------------------------------------------------
// Utilities
// -------------------------------------------------------
const $ = (sel, ctx = document) => ctx.querySelector(sel);
const $$ = (sel, ctx = document) => [...ctx.querySelectorAll(sel)];

function toast(msg, type = 'info', duration = 4000) {
  const icons = { success: '✓', error: '✗', warning: '⚠', info: 'ℹ' };
  const container = document.getElementById('toastContainer');
  if (!container) return;

  const el = document.createElement('div');
  el.className = `toast ${type}`;
  el.innerHTML = `<span>${icons[type] || 'ℹ'}</span><span>${msg}</span>`;
  container.appendChild(el);

  setTimeout(() => {
    el.style.opacity = '0';
    el.style.transition = 'opacity .3s';
    setTimeout(() => el.remove(), 300);
  }, duration);
}

async function apiPost(url, data = {}) {
  data.csrf_token = CSRF_TOKEN;
  const resp = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  });
  return resp.json();
}

async function apiGet(url) {
  const resp = await fetch(url);
  return resp.json();
}

function loading(el, state = true, originalText = '') {
  if (!el) return;
  if (state) {
    el.dataset.original = el.innerHTML;
    el.innerHTML = '<span class="call-spinner"></span> Please wait…';
    el.disabled = true;
  } else {
    el.innerHTML = el.dataset.original || originalText;
    el.disabled = false;
  }
}

// -------------------------------------------------------
// Clock
// -------------------------------------------------------
function startClock() {
  const el = document.getElementById('topbarTime');
  if (!el) return;
  const tick = () => {
    const now = new Date();
    el.textContent = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
  };
  tick();
  setInterval(tick, 1000);
}

// -------------------------------------------------------
// Sidebar toggle
// -------------------------------------------------------
function initSidebar() {
  const sidebar = document.getElementById('sidebar');
  const mobileBtn = document.getElementById('mobileMenuBtn');
  if (!mobileBtn || !sidebar) return;

  mobileBtn.addEventListener('click', () => sidebar.classList.toggle('open'));

  // Close on outside click
  document.addEventListener('click', (e) => {
    if (window.innerWidth <= 900 && sidebar.classList.contains('open') &&
        !sidebar.contains(e.target) && e.target !== mobileBtn) {
      sidebar.classList.remove('open');
    }
  });
}

// -------------------------------------------------------
// Modal
// -------------------------------------------------------
const Modal = {
  open(title, bodyHtml, footerHtml = '', large = false) {
    const overlay = document.getElementById('modalOverlay');
    const box = document.getElementById('modalBox');
    document.getElementById('modalTitle').textContent = title;
    document.getElementById('modalBody').innerHTML = bodyHtml;
    document.getElementById('modalFooter').innerHTML = footerHtml;
    box.classList.toggle('modal-lg', large);
    overlay.style.display = 'flex';
  },
  close() {
    const overlay = document.getElementById('modalOverlay');
    overlay.style.display = 'none';
  }
};

function initModal() {
  const overlay = document.getElementById('modalOverlay');
  const closeBtn = document.getElementById('modalClose');
  if (closeBtn) closeBtn.addEventListener('click', Modal.close);
  if (overlay) {
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) Modal.close();
    });
  }
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') Modal.close();
  });
}

// -------------------------------------------------------
// Queue stats updater
// -------------------------------------------------------
async function updateQueueIndicator() {
  try {
    const data = await apiGet(`${BASE_URL}/api/calls.php?action=queue_stats`);
    if (data.success) {
      const count = data.data.pending;
      document.getElementById('queueCount').textContent = count;
      const dot = document.querySelector('.queue-dot');
      if (dot) dot.classList.toggle('active', count > 0);
    }
  } catch (_) {}
}

// -------------------------------------------------------
// Format duration
// -------------------------------------------------------
function formatDuration(secs) {
  if (!secs) return '—';
  const m = Math.floor(secs / 60);
  const s = secs % 60;
  return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}

function statusBadgeHtml(status) {
  const map = {
    new:       ['New',       'badge-new'],
    calling:   ['Calling',   'badge-calling'],
    connected: ['Connected', 'badge-connected'],
    no_answer: ['No Answer', 'badge-noanswer'],
    called:    ['Called',    'badge-called'],
    hot:       ['🔥 Hot',    'badge-hot'],
    warm:      ['🌤 Warm',   'badge-warm'],
    cold:      ['❄️ Cold',   'badge-cold'],
    queued:    ['Queued',    'badge-queued'],
    failed:    ['Failed',    'badge-failed'],
    completed: ['Completed', 'badge-completed'],
  };
  const [label, cls] = map[status] || [status, 'badge-default'];
  return `<span class="badge ${cls}">${label}</span>`;
}

// -------------------------------------------------------
// Call log detail viewer
// -------------------------------------------------------
async function viewCallLog(logId) {
  Modal.open('Call Log Detail', '<div class="page-spinner"><div class="spinner"></div></div>', '', true);
  try {
    const data = await apiGet(`${BASE_URL}/api/calls.php?action=log_detail&id=${logId}`);
    if (!data.success) { Modal.close(); toast('Failed to load call log.', 'error'); return; }
    const log = data.data;

    const scoreHtml = log.lead_score
      ? `<span class="score-chip ${log.lead_score}">${log.lead_score === 'hot' ? '🔥' : log.lead_score === 'warm' ? '🌤' : '❄️'} ${log.lead_score.charAt(0).toUpperCase() + log.lead_score.slice(1)}</span>`
      : '—';

    const transcriptHtml = log.transcript
      ? log.transcript.split('\n').map(line => {
          const cls = line.startsWith('AI:') ? 'transcript-line-ai' : 'transcript-line-lead';
          return `<div class="${cls}">${escHtml(line)}</div>`;
        }).join('')
      : '<em>No transcript available.</em>';

    const summaryHtml = log.summary
      ? `<div class="summary-box">💡 <strong>AI Summary:</strong> ${escHtml(log.summary)}</div>`
      : '';

    const body = `
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
        <div><div class="text-sm text-muted">Lead Name</div><div class="font-medium">${escHtml(log.lead_name)}</div></div>
        <div><div class="text-sm text-muted">Phone</div><div class="font-medium">${escHtml(log.lead_phone)}</div></div>
        <div><div class="text-sm text-muted">Status</div><div>${statusBadgeHtml(log.status)}</div></div>
        <div><div class="text-sm text-muted">Duration</div><div class="font-medium">${formatDuration(parseInt(log.duration))}</div></div>
        <div><div class="text-sm text-muted">Lead Score</div><div>${scoreHtml}</div></div>
        <div><div class="text-sm text-muted">Called At</div><div class="font-medium text-sm">${log.created_at || '—'}</div></div>
      </div>
      <div class="form-label">Transcript</div>
      <div class="transcript-box">${transcriptHtml}</div>
      ${summaryHtml}
    `;
    Modal.open('Call Log — ' + escHtml(log.lead_name), body, '<button class="btn btn-secondary" onclick="Modal.close()">Close</button>', true);
  } catch (e) {
    toast('Error loading log.', 'error');
  }
}

function escHtml(str) {
  const d = document.createElement('div');
  d.textContent = str || '';
  return d.innerHTML;
}

// -------------------------------------------------------
// Init
// -------------------------------------------------------
document.addEventListener('DOMContentLoaded', () => {
  startClock();
  initSidebar();
  initModal();
  updateQueueIndicator();
  setInterval(updateQueueIndicator, 15000);

  // Page-specific init
  if (typeof pageInit === 'function') pageInit();
});
