<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/LeadService.php';
require_once __DIR__ . '/../includes/layout.php';

Auth::check();

$metrics  = LeadService::getDashboardMetrics();
$activity = LeadService::getCallActivity(7);

// Recent call logs
$recentLogs = DB::fetchAll(
    'SELECT cl.*, l.name as lead_name, l.phone as lead_phone
     FROM call_logs cl JOIN leads l ON l.id = cl.lead_id
     ORDER BY cl.created_at DESC LIMIT 8'
);

// Hot leads
$hotLeads = DB::fetchAll(
    "SELECT * FROM leads WHERE status = 'hot' ORDER BY score DESC LIMIT 5"
);

renderHead('Dashboard');
?>

<div class="metrics-grid">
    <div class="metric-card primary">
        <div class="metric-icon">👥</div>
        <div class="metric-value"><?= number_format($metrics['total_leads']) ?></div>
        <div class="metric-label">Total Leads</div>
    </div>
    <div class="metric-card info">
        <div class="metric-icon">📞</div>
        <div class="metric-value"><?= number_format($metrics['calls_today']) ?></div>
        <div class="metric-label">Calls Today</div>
    </div>
    <div class="metric-card success">
        <div class="metric-icon">✅</div>
        <div class="metric-value"><?= number_format($metrics['connected_calls']) ?></div>
        <div class="metric-label">Connected Calls</div>
    </div>
    <div class="metric-card hot">
        <div class="metric-icon">🔥</div>
        <div class="metric-value"><?= number_format($metrics['hot_leads']) ?></div>
        <div class="metric-label">Hot Leads</div>
    </div>
    <div class="metric-card warm">
        <div class="metric-icon">🌤</div>
        <div class="metric-value"><?= number_format($metrics['warm_leads']) ?></div>
        <div class="metric-label">Warm Leads</div>
    </div>
    <div class="metric-card">
        <div class="metric-icon">📊</div>
        <div class="metric-value"><?= h($metrics['conversion_rate']) ?></div>
        <div class="metric-label">Conversion Rate</div>
    </div>
    <div class="metric-card">
        <div class="metric-icon">🔄</div>
        <div class="metric-value"><?= number_format($metrics['queue_pending']) ?></div>
        <div class="metric-label">Queue Pending</div>
    </div>
    <div class="metric-card">
        <div class="metric-icon">✨</div>
        <div class="metric-value"><?= number_format($metrics['new_leads']) ?></div>
        <div class="metric-label">New Leads</div>
    </div>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:20px">

    <!-- Call Activity Chart -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">📈 Call Activity (Last 7 Days)</span>
        </div>
        <div class="card-body">
            <?php if (empty($activity)): ?>
            <div class="empty-state" style="padding:30px 0">
                <div class="empty-state-icon">📉</div>
                <p>No call data yet. Start making calls!</p>
            </div>
            <?php else:
                $maxVal = max(array_map(fn($r) => (int)$r['total'], $activity)) ?: 1;
            ?>
            <div class="chart-bars">
                <?php foreach ($activity as $row):
                    $completedH = round(((int)$row['completed'] / $maxVal) * 100);
                    $naH = round(((int)$row['no_answer'] / $maxVal) * 100);
                ?>
                <div class="chart-bar-group">
                    <div title="Completed: <?= $row['completed'] ?>" class="chart-bar completed" style="height:<?= $completedH ?>%"></div>
                    <div title="No Answer: <?= $row['no_answer'] ?>" class="chart-bar no_answer" style="height:<?= $naH ?>%"></div>
                    <div class="chart-label"><?= date('d M', strtotime($row['date'])) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="flex gap-3 mt-3" style="font-size:12px;color:var(--text2)">
                <span style="display:flex;align-items:center;gap:5px"><span style="width:10px;height:10px;border-radius:2px;background:var(--primary);display:inline-block"></span> Completed</span>
                <span style="display:flex;align-items:center;gap:5px"><span style="width:10px;height:10px;border-radius:2px;background:#e2e8f0;display:inline-block"></span> No Answer</span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Lead Score Distribution -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">🎯 Lead Scores</span>
        </div>
        <div class="card-body">
            <?php
            $total = $metrics['hot_leads'] + $metrics['warm_leads'] + $metrics['cold_leads'] ?: 1;
            $bars = [
                ['Hot Leads',  $metrics['hot_leads'],  'hot',  '🔥'],
                ['Warm Leads', $metrics['warm_leads'], 'warm', '🌤'],
                ['Cold Leads', $metrics['cold_leads'], 'cold', '❄️'],
            ];
            foreach ($bars as [$label, $val, $cls, $icon]):
                $pct = round(($val / $total) * 100);
            ?>
            <div style="margin-bottom:14px">
                <div class="flex justify-between items-center mb-1">
                    <span style="font-size:13px"><?= $icon ?> <?= $label ?></span>
                    <span style="font-size:13px;font-weight:600"><?= $val ?> <span class="text-muted">(<?= $pct ?>%)</span></span>
                </div>
                <div class="progress-bar-wrap">
                    <div class="progress-bar <?= $cls ?>" style="width:<?= $pct ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">

    <!-- Recent Call Logs -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">📋 Recent Calls</span>
            <a href="<?= BASE_URL ?>/pages/call_logs.php" class="btn btn-sm btn-secondary">View All</a>
        </div>
        <?php if (empty($recentLogs)): ?>
        <div class="empty-state" style="padding:30px">
            <div class="empty-state-icon">📞</div>
            <p>No calls made yet.</p>
        </div>
        <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Lead</th>
                        <th>Status</th>
                        <th>Duration</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentLogs as $log): ?>
                    <tr>
                        <td>
                            <div class="font-medium"><?= h($log['lead_name']) ?></div>
                            <div class="text-sm text-muted"><?= h($log['lead_phone']) ?></div>
                        </td>
                        <td><?= statusBadge($log['status']) ?></td>
                        <td><?= formatDuration((int)$log['duration']) ?></td>
                        <td>
                            <button class="btn btn-sm btn-secondary" onclick="viewCallLog(<?= (int)$log['id'] ?>)">
                                View
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Hot Leads -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">🔥 Hot Leads</span>
            <a href="<?= BASE_URL ?>/pages/leads.php?status=hot" class="btn btn-sm btn-secondary">View All</a>
        </div>
        <?php if (empty($hotLeads)): ?>
        <div class="empty-state" style="padding:30px">
            <div class="empty-state-icon">🔥</div>
            <p>No hot leads yet. Start making AI calls!</p>
        </div>
        <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Lead</th>
                        <th>City</th>
                        <th>Score</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($hotLeads as $lead): ?>
                    <tr>
                        <td>
                            <div class="font-medium"><?= h($lead['name']) ?></div>
                            <div class="text-sm text-muted"><?= h($lead['phone']) ?></div>
                        </td>
                        <td class="text-sm"><?= h($lead['city']) ?: '—' ?></td>
                        <td>
                            <div class="progress-bar-wrap" style="width:60px">
                                <div class="progress-bar hot" style="width:<?= min(100, (int)$lead['score']) ?>%"></div>
                            </div>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-success" onclick="callLead(<?= (int)$lead['id'] ?>, '<?= h(addslashes($lead['name'])) ?>')">
                                📞 Call
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</div>

<script>
async function callLead(leadId, leadName) {
    if (!confirm(`Start AI call for ${leadName}?`)) return;
    const btn = event.target;
    loading(btn, true);
    try {
        const res = await apiPost(`${BASE_URL}/api/calls.php?action=single`, { lead_id: leadId });
        if (res.success) {
            toast(`✅ Call initiated for ${leadName}! Outcome: ${res.outcome || 'processing'}`, 'success');
            setTimeout(() => location.reload(), 2000);
        } else {
            toast(`Error: ${res.error || 'Call failed'}`, 'error');
        }
    } catch (e) {
        toast('Network error. Please try again.', 'error');
    }
    loading(btn, false);
}
</script>

<?php renderFoot(); ?>
