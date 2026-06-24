<?php
/**
 * System - Zabbix integration settings
 * Configure the Zabbix JSON-RPC connection used by Service Status > Zabbix.
 * Stored in system_settings (token encrypted at rest); replaces the need to
 * edit config.php / Docker env.
 */
session_start();
require_once '../../config.php';
require_once '../../includes/i18n.php';
I18n::initFromSession();

$current_page = 'zabbix';
$path_prefix = '../../';
$translationNamespaces = ['common', 'system'];
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(I18n::getLocale()); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Desk - Zabbix</title>
    <link rel="stylesheet" href="../../assets/css/inbox.css">
    <style>
        .zbxs-container { height: calc(100vh - 48px); overflow-y: auto; padding: 30px 20px; }
        .page-title { font-size: 22px; font-weight: 600; color: #333; margin: 0 0 6px 0; }
        .page-subtitle { font-size: 13px; color: #888; margin: 0 0 30px 0; }

        .settings-card { background: #fff; border-radius: 8px; padding: 24px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); margin-bottom: 24px; max-width: 720px; }
        .settings-card h3 { font-size: 15px; font-weight: 600; color: #333; margin: 0 0 4px 0; }
        .settings-card .card-desc { font-size: 13px; color: #888; margin: 0 0 20px 0; line-height: 1.5; }

        .form-field { margin-bottom: 18px; }
        .form-field label { display: block; font-size: 13px; font-weight: 600; color: #444; margin-bottom: 4px; }
        .form-field .hint { font-size: 12px; color: #999; font-weight: 400; margin: 0 0 6px 0; }
        .form-field input[type=text], .form-field input[type=password], .form-field input[type=number], .form-field select {
            width: 100%; padding: 9px 11px; border: 1px solid #ddd; border-radius: 5px; font-size: 13px; font-family: inherit; box-sizing: border-box; background: #fff; color: #333;
        }
        .form-field input:focus, .form-field select:focus { outline: none; border-color: #546e7a; }
        .field-row { display: flex; gap: 16px; }
        .field-row .form-field { flex: 1; }

        .setting-row { display: flex; align-items: center; gap: 16px; margin-bottom: 4px; }
        .setting-label { flex: 1; font-size: 13px; color: #555; }
        .setting-label strong { display: block; color: #333; margin-bottom: 2px; }
        .switch { position: relative; display: inline-block; width: 44px; height: 24px; flex: none; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .switch .slider { position: absolute; cursor: pointer; inset: 0; background: #ccc; border-radius: 24px; transition: .2s; }
        .switch .slider:before { content: ""; position: absolute; height: 18px; width: 18px; left: 3px; bottom: 3px; background: #fff; border-radius: 50%; transition: .2s; }
        .switch input:checked + .slider { background: #546e7a; }
        .switch input:checked + .slider:before { transform: translateX(20px); }

        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; border-radius: 6px; font-size: 13px; font-weight: 600; border: none; cursor: pointer; transition: all 0.15s; }
        .btn-primary { background: #546e7a; color: #fff; }
        .btn-primary:hover { background: #455a64; }
        .btn-test { background: #fff; color: #546e7a; border: 1px solid #cfd8dc; }
        .btn-test:hover { background: #f5f7fa; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .save-area { margin-top: 22px; display: flex; align-items: center; gap: 12px; }

        .test-result { font-size: 12px; padding: 8px 12px; border-radius: 5px; display: none; }
        .test-result.ok { display: inline-block; background: #e8f5e9; color: #2e7d32; }
        .test-result.err { display: inline-block; background: #ffebee; color: #c62828; }
        .save-msg { font-size: 12px; color: #2e7d32; display: none; }

        .info-note { background: #f5f7fa; border: 1px solid #e0e0e0; border-radius: 6px; padding: 14px 16px; font-size: 12px; color: #666; line-height: 1.6; max-width: 720px; }
        .info-note strong { color: #333; }
        .info-note code { background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 1px 5px; }
        .info-note a { color: #546e7a; }
    </style>
    <link rel="stylesheet" href="../../assets/css/zabbix-theme.css">
    <link rel="stylesheet" href="../../assets/css/zabbix-theme-generated.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="zbxs-container">
        <h1 class="page-title">Zabbix</h1>
        <p class="page-subtitle">Connect a Zabbix server (6.0+) so Service Status &rsaquo; Zabbix can show live problems and host availability.</p>

        <div class="settings-card">
            <h3>Connection</h3>
            <p class="card-desc">In Zabbix, create an API token under <strong>Users &rsaquo; API tokens</strong> for a user with read access to the hosts and problems you want surfaced.</p>

            <div class="form-field">
                <label for="zUrl">Frontend URL</label>
                <p class="hint">Base URL without <code>/api_jsonrpc.php</code> — e.g. <code>https://zabbix.example.com</code></p>
                <input type="text" id="zUrl" placeholder="https://zabbix.example.com" autocomplete="off">
            </div>

            <div class="form-field">
                <label for="zToken">API token</label>
                <p class="hint" id="tokenHint">Paste the API token. It is encrypted at rest.</p>
                <input type="password" id="zToken" placeholder="" autocomplete="off">
            </div>

            <div class="field-row">
                <div class="form-field">
                    <label for="zRefresh">Auto-refresh (seconds)</label>
                    <p class="hint">0 disables auto-refresh</p>
                    <input type="number" id="zRefresh" min="0" max="3600" value="30">
                </div>
                <div class="form-field">
                    <label for="zMinSev">Minimum severity</label>
                    <p class="hint">Lowest severity shown</p>
                    <select id="zMinSev">
                        <option value="0">Not classified (all)</option>
                        <option value="1">Information</option>
                        <option value="2">Warning</option>
                        <option value="3">Average</option>
                        <option value="4">High</option>
                        <option value="5">Disaster</option>
                    </select>
                </div>
            </div>

            <div class="setting-row">
                <div class="setting-label">
                    <strong>Verify SSL certificate</strong>
                    Validate the Zabbix server's TLS certificate (recommended in production).
                </div>
                <label class="switch"><input type="checkbox" id="zVerify"><span class="slider"></span></label>
            </div>

            <div class="save-area">
                <button class="btn btn-primary" id="saveBtn">Save</button>
                <button class="btn btn-test" id="testBtn">Test connection</button>
                <span class="test-result" id="testResult"></span>
                <span class="save-msg" id="saveMsg">Saved</span>
            </div>
        </div>

        <div class="info-note">
            <strong>Note:</strong> these settings replace the <code>ZABBIX_*</code> values in <code>config.php</code> / Docker env. Those are still used as a fallback until you save here. Once configured, open <a href="../../service-status/zabbix.php">Service Status &rsaquo; Zabbix</a> to view the dashboard.
        </div>
    </div>

    <script>
        const API = '../../api/system/';
        const MASK_PLACEHOLDER = '••••••••';

        function el(id) { return document.getElementById(id); }

        async function load() {
            try {
                const res = await fetch(API + 'get_zabbix_settings.php', { credentials: 'same-origin' });
                const d = await res.json();
                if (!d.success) return;
                el('zUrl').value = d.url || '';
                el('zRefresh').value = (d.refresh != null ? d.refresh : 30);
                el('zMinSev').value = String(d.min_severity != null ? d.min_severity : 0);
                el('zVerify').checked = !!d.verify_ssl;
                if (d.has_token) {
                    el('zToken').placeholder = d.masked_token || MASK_PLACEHOLDER;
                    el('tokenHint').textContent = 'A token is saved — leave blank to keep it, or paste a new one to replace it.';
                }
            } catch (e) { /* ignore */ }
        }

        function payload() {
            return {
                url: el('zUrl').value.trim(),
                token: el('zToken').value,            // blank = no change (server-side)
                refresh: parseInt(el('zRefresh').value, 10) || 0,
                min_severity: parseInt(el('zMinSev').value, 10) || 0,
                verify_ssl: el('zVerify').checked ? 1 : 0
            };
        }

        async function save() {
            const btn = el('saveBtn'); btn.disabled = true;
            el('saveMsg').style.display = 'none';
            try {
                const res = await fetch(API + 'save_zabbix_settings.php', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload())
                });
                const d = await res.json();
                if (d.success) {
                    el('zToken').value = '';
                    el('saveMsg').style.display = 'inline';
                    setTimeout(() => { el('saveMsg').style.display = 'none'; }, 2500);
                    load();
                } else {
                    showTest(false, d.error || 'Save failed');
                }
            } catch (e) { showTest(false, e.message); }
            btn.disabled = false;
        }

        function showTest(ok, msg) {
            const r = el('testResult');
            r.className = 'test-result ' + (ok ? 'ok' : 'err');
            r.textContent = msg;
        }

        async function test() {
            const btn = el('testBtn'); btn.disabled = true;
            showTest(true, 'Testing…');
            try {
                const res = await fetch(API + 'test_zabbix.php', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload())
                });
                const d = await res.json();
                if (d.success) showTest(true, 'Connected — Zabbix ' + (d.version || 'OK'));
                else showTest(false, d.error || 'Connection failed');
            } catch (e) { showTest(false, e.message); }
            btn.disabled = false;
        }

        el('saveBtn').addEventListener('click', save);
        el('testBtn').addEventListener('click', test);
        load();
    </script>
</body>
</html>
