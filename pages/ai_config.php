<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/layout.php';

Auth::requireAdmin();

renderHead('AI Configuration');
?>

<div style="max-width:820px">

<div class="alert alert-info mb-4">
    ℹ️ Configure the AI calling behavior, language style, and integrations below. Changes take effect on the next call.
</div>

<!-- Conversation Settings -->
<div class="card mb-4">
    <div class="card-header">
        <span class="card-title">💬 Conversation Settings</span>
    </div>
    <div class="card-body">
        <form id="conversationForm">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Language Style</label>
                    <select name="language_style" class="form-control" id="lang_style">
                        <option value="english">English</option>
                        <option value="hinglish">Hinglish (Hindi + English)</option>
                    </select>
                    <div class="form-hint">Determines the language mix for AI conversation.</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Tone</label>
                    <select name="tone" class="form-control" id="tone">
                        <option value="friendly">Friendly</option>
                        <option value="formal">Formal</option>
                        <option value="persuasive">Persuasive</option>
                        <option value="casual">Casual</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Opening Script</label>
                <textarea name="opening_script" class="form-control" id="opening_script" rows="3"
                    placeholder="Hello! This is an AI assistant calling on behalf of our sales team. Am I speaking with {{lead_name}}?"></textarea>
                <div class="form-hint">Use <code>{{lead_name}}</code> to personalize with the lead's name.</div>
            </div>

            <div class="form-group">
                <label class="form-label">Question Flow</label>
                <textarea name="question_flow" class="form-control" id="question_flow" rows="6"
                    placeholder="One question per line…"></textarea>
                <div class="form-hint">Enter one question per line. The AI will ask them in sequence.</div>
            </div>

            <div class="form-group">
                <label class="form-label">Closing Statement</label>
                <textarea name="closing_statement" class="form-control" id="closing_statement" rows="3"
                    placeholder="Thank you for your time {{lead_name}}!"></textarea>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-primary" onclick="saveConfig('conversationForm', this)">
                    💾 Save Conversation Settings
                </button>
                <button type="button" class="btn btn-secondary" onclick="previewScript()">👁 Preview Script</button>
            </div>
        </form>
    </div>
</div>

<!-- Call Behavior -->
<div class="card mb-4">
    <div class="card-header">
        <span class="card-title">⚙️ Call Behavior</span>
    </div>
    <div class="card-body">
        <form id="behaviorForm">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Max Retry Attempts</label>
                    <select name="max_retries" class="form-control" id="max_retries">
                        <option value="1">1 attempt</option>
                        <option value="2">2 attempts</option>
                        <option value="3">3 attempts</option>
                    </select>
                    <div class="form-hint">Number of retry attempts for unanswered calls.</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Delay Between Calls (seconds)</label>
                    <input type="number" name="call_delay_seconds" class="form-control" id="call_delay_seconds"
                        min="2" max="60" value="5"/>
                    <div class="form-hint">Pause between sequential calls in bulk mode.</div>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-primary" onclick="saveConfig('behaviorForm', this)">
                    💾 Save Call Behavior
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Integrations -->
<div class="card mb-4">
    <div class="card-header">
        <span class="card-title">🔗 Integrations</span>
    </div>
    <div class="card-body">
        <div class="alert alert-warning mb-4">
            ⚠️ API keys are encrypted and masked after saving. These are required for real AI calling. Without them, the system runs in <strong>Mock/Demo mode</strong>.
        </div>

        <form id="integrationsForm">
            <div class="form-group">
                <label class="form-label">AI Provider</label>
                <select name="ai_provider" class="form-control" id="ai_provider">
                    <option value="mock">Mock (Demo Mode — no real calls)</option>
                    <option value="openrouter">OpenRouter (recommended)</option>
                    <option value="cohere">Cohere</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Provider API Key</label>
                <input type="password" name="ai_api_key" class="form-control" id="ai_api_key"
                    placeholder="Paste OpenRouter/Cohere key…" autocomplete="off"/>
                <div class="form-hint">Used for generating transcript summaries (OpenRouter or Cohere).</div>
            </div>

            <hr style="border:none;border-top:1px solid var(--border);margin:20px 0"/>

            <div class="form-label" style="font-size:14px;font-weight:600;margin-bottom:12px">📞 Twilio (Real Phone Calls)</div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Account SID</label>
                    <input type="text" name="twilio_account_sid" class="form-control" id="twilio_account_sid"
                        placeholder="ACxxxxxxxxxxxxxxxx" autocomplete="off"/>
                </div>
                <div class="form-group">
                    <label class="form-label">Auth Token</label>
                    <input type="password" name="twilio_auth_token" class="form-control" id="twilio_auth_token"
                        placeholder="••••••••••••" autocomplete="off"/>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Twilio Phone Number</label>
                <input type="text" name="twilio_phone_number" class="form-control" id="twilio_phone_number"
                    placeholder="+1 555 000 1234"/>
                <div class="form-hint">Must be a Twilio number with voice capability.</div>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-primary" onclick="saveConfig('integrationsForm', this)">
                    💾 Save Integrations
                </button>
            </div>
        </form>
    </div>
</div>

</div>

<script>
// Load current config on page load
async function loadConfig() {
    const data = await apiGet(`${BASE_URL}/api/config.php`);
    if (!data.success) return;
    const c = data.data;

    // Populate fields
    const fields = ['language_style','tone','opening_script','question_flow','closing_statement',
                    'max_retries','call_delay_seconds','ai_provider','ai_api_key',
                    'twilio_account_sid','twilio_auth_token','twilio_phone_number'];
    fields.forEach(f => {
        const el = document.getElementById(f);
        if (el && c[f] !== undefined) el.value = c[f];
    });
}

async function saveConfig(formId, btn) {
    loading(btn, true);
    const form = document.getElementById(formId);
    const fd = new FormData(form);
    const data = Object.fromEntries(fd.entries());
    data.csrf_token = CSRF_TOKEN;

    const res = await apiPost(`${BASE_URL}/api/config.php`, data);
    loading(btn, false);

    if (res.success) {
        toast('✅ ' + res.message, 'success');
    } else {
        toast(res.error || 'Save failed.', 'error');
    }
}

function previewScript() {
    const opening  = document.getElementById('opening_script').value.replace(/{{lead_name}}/g, '<strong>John Doe</strong>');
    const questions = document.getElementById('question_flow').value
        .split('\n').filter(l => l.trim())
        .map(q => `<div style="padding:8px 0;border-bottom:1px solid var(--border)">🤖 ${escHtml(q)}</div>`).join('');
    const closing = document.getElementById('closing_statement').value.replace(/{{lead_name}}/g, '<strong>John Doe</strong>');

    Modal.open('Script Preview', `
        <div class="form-label">Opening</div>
        <div class="summary-box">${opening}</div>
        <div class="form-label mt-3">Questions</div>
        <div style="border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;margin-bottom:12px">
            ${questions || '<div style="padding:12px;color:var(--text3)">No questions configured.</div>'}
        </div>
        <div class="form-label">Closing</div>
        <div class="summary-box">${closing}</div>
    `, '<button class="btn btn-secondary" onclick="Modal.close()">Close</button>', true);
}

document.addEventListener('DOMContentLoaded', loadConfig);
</script>

<?php renderFoot(); ?>
