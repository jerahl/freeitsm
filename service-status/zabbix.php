<?php
/**
 * Service Status Module — Zabbix Dashboard
 * Dark, Zabbix-styled view of live problems and host availability,
 * pulled from the Zabbix JSON-RPC API via api/service-status/get_zabbix.php.
 */
session_start();
require_once '../config.php';
require_once '../includes/i18n.php';
I18n::initFromSession();

$current_page = 'zabbix';
$path_prefix = '../';
$translationNamespaces = ['common', 'service-status'];

$refreshSeconds = defined('ZABBIX_REFRESH_SECONDS') ? (int) ZABBIX_REFRESH_SECONDS : 30;
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(I18n::getLocale()); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Desk - Zabbix</title>
    <script>window.translations = <?php echo json_encode(I18n::exportForJs($translationNamespaces), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;</script>
    <script src="../assets/js/i18n.js"></script>
    <link rel="stylesheet" href="../assets/css/inbox.css">
    <style>
        /* ── Zabbix-style dark dashboard ─────────────────────────────────────
           Zabbix's default "dark" theme: deep slate canvas, muted panels,
           and the standard severity palette. Scoped to .zbx-* so it doesn't
           bleed into the shared (light) app chrome / header. */
        .zbx-wrap {
            height: calc(100vh - 48px);
            overflow-y: auto;
            background: #0e1726;
            color: #cbd5e1;
            padding: 20px 24px 40px;
            font-size: 13px;
        }
        .zbx-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .zbx-title {
            font-size: 18px;
            font-weight: 600;
            color: #f1f5f9;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .zbx-title .zbx-dot { width: 10px; height: 10px; border-radius: 50%; background: #E45959; box-shadow: 0 0 8px #E45959; }
        .zbx-meta { display: flex; align-items: center; gap: 14px; font-size: 12px; color: #64748b; }
        .zbx-version { color: #475569; }
        .zbx-refresh-btn {
            background: #1b2740;
            border: 1px solid #2b3a5a;
            border-radius: 6px;
            padding: 5px 12px;
            font-size: 12px;
            color: #94a3b8;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .zbx-refresh-btn:hover { background: #233152; color: #cbd5e1; }
        .zbx-refresh-btn.spinning svg { animation: zbx-spin 0.8s linear infinite; }
        @keyframes zbx-spin { from { transform: rotate(0); } to { transform: rotate(360deg); } }

        /* ── Summary tiles ──────────────────────────────────────────────── */
        .zbx-tiles {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-bottom: 22px;
        }
        .zbx-tile {
            background: #15203a;
            border: 1px solid #233152;
            border-radius: 8px;
            padding: 14px 16px;
            border-left: 4px solid #334155;
        }
        .zbx-tile .zbx-tile-count { font-size: 28px; font-weight: 700; line-height: 1; color: #f1f5f9; }
        .zbx-tile .zbx-tile-label { font-size: 11px; text-transform: uppercase; letter-spacing: .5px; color: #94a3b8; margin-top: 6px; }
        .zbx-tile.zbx-host-ok   { border-left-color: #4caf50; }
        .zbx-tile.zbx-host-down { border-left-color: #E45959; }

        /* ── Panel + problems table ─────────────────────────────────────── */
        .zbx-panel {
            background: #15203a;
            border: 1px solid #233152;
            border-radius: 8px;
            overflow: hidden;
        }
        .zbx-panel-head {
            padding: 12px 16px;
            border-bottom: 1px solid #233152;
            font-size: 13px;
            font-weight: 600;
            color: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        table.zbx-table { width: 100%; border-collapse: collapse; }
        table.zbx-table th {
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .4px;
            color: #64748b;
            font-weight: 600;
            padding: 9px 16px;
            border-bottom: 1px solid #233152;
            background: #111a2e;
        }
        table.zbx-table td {
            padding: 9px 16px;
            border-bottom: 1px solid #1c2840;
            color: #cbd5e1;
            vertical-align: middle;
        }
        table.zbx-table tr:last-child td { border-bottom: none; }
        table.zbx-table tr:hover td { background: #19243f; }
        .zbx-sev {
            display: inline-block;
            padding: 2px 9px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            color: #1a1a1a;
            white-space: nowrap;
        }
        .zbx-host { color: #e2e8f0; font-weight: 500; }
        .zbx-ack {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .4px;
            padding: 1px 6px;
            border-radius: 3px;
            border: 1px solid #2b3a5a;
            color: #94a3b8;
        }
        .zbx-ack.yes { color: #4caf50; border-color: #2e5a32; }
        .zbx-age { color: #64748b; white-space: nowrap; }

        /* ── States ─────────────────────────────────────────────────────── */
        .zbx-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }
        .zbx-state h2 { color: #cbd5e1; font-size: 16px; margin: 0 0 8px; }
        .zbx-state code { background: #1b2740; padding: 2px 6px; border-radius: 4px; color: #93c5fd; }
        .zbx-state.error h2 { color: #E45959; }
        .zbx-empty-row td { text-align: center; color: #64748b; padding: 30px; }
    </style>
    <link rel="stylesheet" href="../assets/css/zabbix-theme.css">
</head>
<body>
    <?php require_once 'includes/header.php'; ?>

    <div class="zbx-wrap">
        <div class="zbx-topbar">
            <div class="zbx-title">
                <span class="zbx-dot" id="zbxStatusDot"></span>
                Zabbix Monitoring
                <span class="zbx-version" id="zbxVersion"></span>
            </div>
            <div class="zbx-meta">
                <span id="zbxUpdated">Loading…</span>
                <button class="zbx-refresh-btn" id="zbxRefresh" title="Refresh now">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="23 4 23 10 17 10"></polyline>
                        <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                    </svg>
                    Refresh
                </button>
            </div>
        </div>

        <div id="zbxContent">
            <div class="zbx-state"><h2>Loading…</h2></div>
        </div>
    </div>

    <script>
        const ZBX_REFRESH_MS = <?php echo $refreshSeconds > 0 ? $refreshSeconds * 1000 : 0; ?>;
        const ZBX_API = '../api/service-status/get_zabbix.php';
        let zbxTimer = null;

        function esc(s) {
            return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
            }[c]));
        }

        function relAge(clock) {
            if (!clock) return '';
            const secs = Math.max(0, Math.floor(Date.now() / 1000) - clock);
            const d = Math.floor(secs / 86400);
            const h = Math.floor((secs % 86400) / 3600);
            const m = Math.floor((secs % 3600) / 60);
            if (d > 0) return d + 'd ' + h + 'h';
            if (h > 0) return h + 'h ' + m + 'm';
            if (m > 0) return m + 'm';
            return secs + 's';
        }

        function renderTiles(data) {
            const sev = data.severities || {};
            // Severity tiles ordered Disaster→Not classified (most urgent first).
            const order = [5, 4, 3, 2, 1, 0];
            let tiles = '';
            order.forEach(level => {
                const meta = sev[level] || {};
                const count = (data.counts && data.counts[level]) || 0;
                tiles += `
                    <div class="zbx-tile" style="border-left-color:${esc(meta.color || '#334155')}">
                        <div class="zbx-tile-count" style="color:${count > 0 ? esc(meta.color) : '#475569'}">${count}</div>
                        <div class="zbx-tile-label">${esc(meta.name || ('Sev ' + level))}</div>
                    </div>`;
            });
            const hostsUp = (data.hostsTotal || 0) - (data.hostsDown || 0);
            tiles += `
                <div class="zbx-tile zbx-host-ok">
                    <div class="zbx-tile-count" style="color:#4caf50">${hostsUp}</div>
                    <div class="zbx-tile-label">Hosts up</div>
                </div>
                <div class="zbx-tile ${data.hostsDown > 0 ? 'zbx-host-down' : ''}">
                    <div class="zbx-tile-count" style="color:${data.hostsDown > 0 ? '#E45959' : '#475569'}">${data.hostsDown || 0}</div>
                    <div class="zbx-tile-label">Hosts down</div>
                </div>`;
            return `<div class="zbx-tiles">${tiles}</div>`;
        }

        function renderProblems(data) {
            const rows = (data.problems || []).map(p => `
                <tr>
                    <td><span class="zbx-sev" style="background:${esc(p.severityColor)}">${esc(p.severityName)}</span></td>
                    <td class="zbx-host">${esc(p.host) || '<span style="color:#475569">—</span>'}</td>
                    <td>${esc(p.name)}</td>
                    <td class="zbx-age">${esc(relAge(p.clock))}</td>
                    <td><span class="zbx-ack ${p.acknowledged ? 'yes' : ''}">${p.acknowledged ? 'Ack' : 'No'}</span></td>
                </tr>`).join('');
            const body = rows || `<tr class="zbx-empty-row"><td colspan="5">No active problems 🎉</td></tr>`;
            return `
                <div class="zbx-panel">
                    <div class="zbx-panel-head">
                        <span>Active Problems</span>
                        <span style="color:#64748b;font-weight:400">${data.totalProblems || 0} total</span>
                    </div>
                    <table class="zbx-table">
                        <thead>
                            <tr><th>Severity</th><th>Host</th><th>Problem</th><th>Age</th><th>Ack</th></tr>
                        </thead>
                        <tbody>${body}</tbody>
                    </table>
                </div>`;
        }

        function render(data) {
            document.getElementById('zbxContent').innerHTML = renderTiles(data) + renderProblems(data);
            document.getElementById('zbxVersion').textContent = data.version ? ('v' + data.version) : '';
            const dot = document.getElementById('zbxStatusDot');
            const hasIssues = (data.totalProblems || 0) > 0 || (data.hostsDown || 0) > 0;
            dot.style.background = hasIssues ? '#E45959' : '#4caf50';
            dot.style.boxShadow = '0 0 8px ' + (hasIssues ? '#E45959' : '#4caf50');
        }

        function renderMessage(opts) {
            document.getElementById('zbxContent').innerHTML =
                `<div class="zbx-state ${opts.error ? 'error' : ''}">
                    <h2>${opts.title}</h2>
                    <div>${opts.body || ''}</div>
                 </div>`;
        }

        async function loadZabbix(manual) {
            const btn = document.getElementById('zbxRefresh');
            if (manual) btn.classList.add('spinning');
            try {
                const res = await fetch(ZBX_API, { credentials: 'same-origin' });
                const json = await res.json();
                if (json.success) {
                    render(json);
                    document.getElementById('zbxUpdated').textContent =
                        'Updated ' + new Date().toLocaleTimeString();
                } else if (json.configured === false) {
                    renderMessage({
                        title: 'Zabbix is not configured',
                        body: 'Set <code>ZABBIX_API_URL</code> and <code>ZABBIX_API_TOKEN</code> in <code>config.php</code> to connect your Zabbix server.'
                    });
                    document.getElementById('zbxUpdated').textContent = 'Not configured';
                } else {
                    renderMessage({ error: true, title: 'Could not load Zabbix data', body: esc(json.error || 'Unknown error') });
                    document.getElementById('zbxUpdated').textContent = 'Error';
                }
            } catch (e) {
                renderMessage({ error: true, title: 'Could not reach the server', body: esc(e.message) });
            } finally {
                if (manual) setTimeout(() => btn.classList.remove('spinning'), 400);
            }
        }

        document.getElementById('zbxRefresh').addEventListener('click', () => loadZabbix(true));
        loadZabbix();
        if (ZBX_REFRESH_MS > 0) {
            zbxTimer = setInterval(loadZabbix, ZBX_REFRESH_MS);
        }
    </script>
</body>
</html>
