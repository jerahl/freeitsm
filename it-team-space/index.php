<?php
/**
 * IT Team Space — internal IT team dashboard hub.
 * A single-pane landing with a Home overview (quick links, announcements,
 * system status, projects, docs, meetings) and per-discipline team pages.
 *
 * Ported from the Claude Design "IT Team Space" canvas. Content is curated
 * sample data for now; data hooks can be wired to live modules later.
 */
session_start();
require_once '../config.php';
require_once '../includes/i18n.php';
I18n::initFromSession();

$current_page = 'dashboard';
$path_prefix = '../';

$analyst_name = $_SESSION['analyst_name'] ?? 'Analyst';
// Initials from the first two words of the name.
$__parts = preg_split('/\s+/', trim($analyst_name));
$analyst_initials = strtoupper(substr($__parts[0] ?? 'A', 0, 1) . (isset($__parts[1]) ? substr($__parts[1], 0, 1) : ''));
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(I18n::getLocale()); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Desk - IT Team Space</title>
    <link rel="stylesheet" href="../assets/css/inbox.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        .its-shell {
            display: flex;
            height: calc(100vh - 48px);
            width: 100%;
            overflow: hidden;
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            background: #0E1116;
            color: #E8EAED;
            -webkit-font-smoothing: antialiased;
        }
        .its-shell * { box-sizing: border-box; }
        .its-aside {
            width: 266px; flex: none; height: 100%;
            background: #16181D; border-right: 1px solid #24272E;
            display: flex; flex-direction: column;
        }
        /* ID-scoped (not class) so the app-wide [class*="-main"]/[class*="-nav"]
           theme overrides don't recolor this module's own dark surfaces. */
        #itsMain { flex: 1; height: 100%; overflow-y: auto; position: relative; }
        #itsMain::-webkit-scrollbar, #itsNav::-webkit-scrollbar { width: 10px; height: 10px; }
        #itsMain::-webkit-scrollbar-thumb, #itsNav::-webkit-scrollbar-thumb { background: #2A2D34; border-radius: 99px; border: 3px solid transparent; background-clip: content-box; }
        #itsMain::-webkit-scrollbar-thumb:hover, #itsNav::-webkit-scrollbar-thumb:hover { background: #3A3F49; background-clip: content-box; }
        #itsNav { flex: 1; overflow-y: auto; padding: 2px 10px 14px 10px; }
        .its-shell a { text-decoration: none; color: inherit; }
        .its-hoverable { transition: background .12s, border-color .12s, box-shadow .12s, transform .12s; }
    </style>
    <link rel="stylesheet" href="../assets/css/zabbix-theme.css">
    <link rel="stylesheet" href="../assets/css/zabbix-theme-generated.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="its-shell">
        <!-- SIDEBAR -->
        <aside class="its-aside">
            <div style="padding:14px 12px 8px 12px">
                <div class="its-hoverable" style="display:flex;align-items:center;gap:9px;padding:7px 8px;border-radius:9px">
                    <div style="width:26px;height:26px;border-radius:7px;background:linear-gradient(135deg,#E45959,#D40000);display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;font-weight:800;flex:none">IT</div>
                    <div style="min-width:0">
                        <div style="font-size:13.5px;font-weight:700;line-height:1.15;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#F2F4F6">IT Team Space</div>
                        <div style="font-size:11px;color:#7E848D;line-height:1.2">Internal hub</div>
                    </div>
                </div>
            </div>
            <div style="padding:2px 12px 10px 12px">
                <div style="display:flex;align-items:center;gap:8px;padding:7px 10px;background:#0F1115;border:1px solid #24272E;border-radius:9px;color:#7E848D;font-size:13px;cursor:text">
                    <span style="font-size:13px">🔍</span><span>Search</span>
                    <span style="margin-left:auto;font-family:'IBM Plex Mono',monospace;font-size:10.5px;background:#1B1E24;border:1px solid #2A2D34;border-radius:5px;padding:1px 5px;color:#7E848D">⌘K</span>
                </div>
            </div>
            <nav id="itsNav"></nav>
            <div style="padding:10px 12px;border-top:1px solid #24272E;display:flex;align-items:center;gap:9px">
                <div style="width:28px;height:28px;border-radius:50%;background:#E45959;color:#fff;display:flex;align-items:center;justify-content:center;font-size:11.5px;font-weight:700;flex:none"><?php echo htmlspecialchars($analyst_initials); ?></div>
                <div style="min-width:0;flex:1">
                    <div style="font-size:13px;font-weight:600;line-height:1.15;color:#F2F4F6;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?php echo htmlspecialchars($analyst_name); ?></div>
                    <div style="font-size:11px;color:#7E848D">IT Team</div>
                </div>
            </div>
        </aside>

        <!-- MAIN -->
        <main id="itsMain"></main>
    </div>

    <script>
    (function () {
        const ACCENT = '#E45959';
        const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const av = (c, s) => `width:${s}px;height:${s}px;border-radius:50%;background:${c};color:#fff;display:flex;align-items:center;justify-content:center;font-size:${Math.round(s*0.42)}px;font-weight:700;flex:none`;

        const state = { activePage: 'home', tab: 'all', search: '', projFilter: 'all' };

        // ---- data ----
        const team = [
            { initials: 'MC', name: 'Marcus Chen', role: 'Network Lead', color: '#7499FF' },
            { initials: 'PS', name: 'Priya Shah', role: 'Security Lead', color: '#E45959' },
            { initials: 'DA', name: 'Dana Alvarez', role: 'Sr. Technician', color: '#34AF67' },
            { initials: 'RT', name: 'Robin Tate', role: 'Systems Admin', color: '#FFA059' },
            { initials: 'SO', name: 'Sam Ortiz', role: 'Database Admin', color: '#5BC0DE' },
            { initials: 'LP', name: 'Lee Park', role: 'Help Desk', color: '#9AA7FF' },
        ];
        const lk = (n) => team.find(t => t.initials === n) || { color: '#7499FF', name: n, role: '', initials: n };

        const teamDefs = [
            { id: 'network', icon: '🌐', label: 'Network', count: '12' },
            { id: 'security', icon: '🔐', label: 'Security', count: '8' },
            { id: 'technician', icon: '🔧', label: 'Technician', count: '21' },
            { id: 'admin', icon: '⚙️', label: 'Admin', count: '9' },
            { id: 'database', icon: '🗄️', label: 'Database', count: '6' },
            { id: 'change', icon: '🔄', label: 'Change Mgmt', count: '7' },
        ];

        const links = [
            { icon: '🎫', title: 'Help Desk', meta: 'Freshservice', c: '#7499FF' },
            { icon: '🖥️', title: 'Remote Support', meta: 'ScreenConnect', c: '#5BC0DE' },
            { icon: '📡', title: 'Network Monitor', meta: 'Zabbix', c: '#34AF67' },
            { icon: '👥', title: 'Active Directory', meta: 'Users & groups', c: '#7499FF' },
            { icon: '🔑', title: 'Password Reset', meta: 'Self-service', c: '#FFA059' },
            { icon: '📦', title: 'Asset Inventory', meta: 'Snipe-IT', c: '#5BC0DE' },
            { icon: '🔒', title: 'VPN Console', meta: 'FortiGate', c: '#E45959' },
            { icon: '📊', title: 'Status Page', meta: 'Live uptime', c: '#34AF67' },
        ];

        const tabDefs = [
            { key: 'all', label: 'Overview' },
            { key: 'ann', label: 'Announcements' },
            { key: 'proj', label: 'Projects' },
            { key: 'docs', label: 'Docs' },
            { key: 'meet', label: 'Meetings' },
        ];

        const tagMeta = { Maintenance: { c: '#7499FF' }, Update: { c: '#34AF67' }, Security: { c: '#E45959' } };
        const tagPill = (t) => { const m = tagMeta[t] || tagMeta.Update; return `font-family:'IBM Plex Mono',monospace;font-size:10px;letter-spacing:.04em;text-transform:uppercase;padding:2px 7px;border-radius:5px;font-weight:500;background:${m.c}26;color:${m.c}`; };
        const annRaw = [
            { tag: 'Maintenance', pinned: true, date: 'Jun 28', title: 'Quarterly security patching — Sat 6/28, 6–10 AM', body: 'Servers and core switches reboot in waves. Expect brief SIS and Wi-Fi interruptions. No action needed from staff.', author: 'Priya Shah', initials: 'PS', c: '#E45959' },
            { tag: 'Update', pinned: false, date: 'Jun 20', title: 'New laptop imaging workflow is live', body: 'Autopilot replaces the old SCCM task sequence. Imaging time is down from 90 to ~25 minutes. Runbook updated.', author: 'Marcus Chen', initials: 'MC', c: '#7499FF' },
            { tag: 'Security', pinned: false, date: 'Jun 17', title: 'May phishing simulation results are in', body: 'Click rate dropped to 4.2% district-wide. Two buildings flagged for a follow-up training module.', author: 'Dana Alvarez', initials: 'DA', c: '#34AF67' },
        ];

        const stMeta = {
            operational: { c: '#34AF67', label: 'Operational' },
            degraded: { c: '#FFC859', label: 'Degraded' },
            maintenance: { c: '#7499FF', label: 'Maintenance' },
            down: { c: '#E45959', label: 'Down' },
        };
        // System status is live from Zabbix (host groups → worst active problem).
        let liveStatus = { state: 'loading', systems: null, error: '', at: '' };
        function statusRowsHtml(systems) {
            if (!systems.length) return `<div style="padding:14px;text-align:center;color:#7E848D;font-size:13px">No monitored host groups.</div>`;
            return systems.map((x, i) => { const m = stMeta[x.status] || stMeta.operational; return `<div style="display:flex;align-items:center;gap:10px;padding:11px 14px;${i?'border-top:1px solid #23262C':''}"><span style="width:8px;height:8px;border-radius:50%;background:${m.c};flex:none;box-shadow:0 0 0 3px ${m.c}33"></span><span style="font-size:13.5px;font-weight:500;flex:1;color:#D6D9DE">${esc(x.name)}</span><span style="font-family:'IBM Plex Mono',monospace;font-size:10.5px;padding:2px 8px;border-radius:6px;background:${m.c}22;color:${m.c}">${esc(m.label)}</span></div>`; }).join('');
        }
        function statusPanelInner() {
            if (liveStatus.state === 'loading') return `<div style="padding:16px;text-align:center;color:#6E747D;font-size:12.5px">Loading status…</div>`;
            if (liveStatus.state === 'unconfigured') return `<div style="padding:16px;color:#9BA1A9;font-size:12.5px;line-height:1.5">Zabbix isn't configured yet. Set it up in <a href="../system/zabbix/" style="color:${ACCENT}">System → Zabbix</a> to see live status.</div>`;
            if (liveStatus.state === 'error') return `<div style="padding:16px;color:#E45959;font-size:12.5px">${esc(liveStatus.error || 'Could not load status.')}</div>`;
            return statusRowsHtml(liveStatus.systems || []);
        }
        function statusMetaText() {
            if (liveStatus.state === 'ok') return 'Live from Zabbix · ' + (liveStatus.at || 'just now');
            if (liveStatus.state === 'loading') return 'Connecting to Zabbix…';
            if (liveStatus.state === 'unconfigured') return 'Not configured';
            return 'Status unavailable';
        }
        function updateStatusPanel() {
            const el = document.getElementById('itsStatus'); if (el) el.innerHTML = statusPanelInner();
            const meta = document.getElementById('itsStatusMeta'); if (meta) meta.textContent = statusMetaText();
        }
        async function fetchStatus() {
            try {
                const res = await fetch('../api/it-team-space/get_status.php', { credentials: 'same-origin' });
                const d = await res.json();
                if (d.success) liveStatus = { state: 'ok', systems: d.systems || [], at: new Date().toLocaleTimeString() };
                else if (d.configured === false) liveStatus = { state: 'unconfigured' };
                else liveStatus = { state: 'error', error: d.error };
            } catch (e) { liveStatus = { state: 'error', error: e.message }; }
            updateStatusPanel();
        }

        const projMeta = {
            'In progress': { c: '#7499FF' }, 'Planning': { c: '#97AAB3' },
            'Blocked': { c: '#E45959' }, 'Done': { c: '#34AF67' },
        };
        const priColor = { High: '#E45959', Med: '#FFA059', Low: '#97AAB3' };
        const projRaw = [
            { name: 'District-wide Wi-Fi 6 upgrade', status: 'In progress', pct: 62, owner: 'MC', pri: 'High' },
            { name: 'Chromebook refresh — 2,400 units', status: 'In progress', pct: 40, owner: 'DA', pri: 'High' },
            { name: 'Zero-trust rollout — phase 2', status: 'Planning', pct: 15, owner: 'PS', pri: 'Med' },
            { name: 'PowerSchool SSO integration', status: 'Blocked', pct: 30, owner: 'RT', pri: 'High' },
            { name: 'Help Desk SLA revamp', status: 'In progress', pct: 75, owner: 'LP', pri: 'Med' },
            { name: 'Server room HVAC replacement', status: 'Done', pct: 100, owner: 'MC', pri: 'Low' },
        ];
        const chipDefs = [{ k: 'all', l: 'All' }, { k: 'In progress', l: 'In progress' }, { k: 'Planning', l: 'Planning' }, { k: 'Blocked', l: 'Blocked' }, { k: 'Done', l: 'Done' }];

        const docs = [
            { icon: '📘', title: 'New hire IT onboarding', meta: 'Checklist · 14 steps' },
            { icon: '🌐', title: 'Network topology & VLANs', meta: 'Updated 3 days ago' },
            { icon: '🚨', title: 'Incident response playbook', meta: 'Sev 1–3 procedures' },
            { icon: '🧾', title: 'Software licensing matrix', meta: '38 products tracked' },
            { icon: '📺', title: 'Classroom AV troubleshooting', meta: 'Top 12 issues' },
            { icon: '💾', title: 'Backup & recovery runbook', meta: 'RPO / RTO targets' },
        ];

        const meetRaw = [
            { mon: 'JUN', day: '22', title: 'IT weekly sync', meta: 'Agenda + action items · Mon', c: '#7499FF' },
            { mon: 'JUN', day: '18', title: 'Security review', meta: 'Phishing + access audit', c: '#E45959' },
            { mon: 'JUN', day: '16', title: 'Vendor: Cisco renewal', meta: 'Licensing & quotes', c: '#34AF67' },
            { mon: 'JUN', day: '12', title: 'Sprint planning', meta: 'Wi-Fi 6 + Chromebook tracks', c: '#5BC0DE' },
        ];

        const pageDefs = {
            network: { icon: '🌐', title: 'Network', hue: '#7499FF', subtitle: 'Switching, routing, wireless and connectivity across all 24 district buildings.', owner: 'Marcus Chen', ownerInit: 'MC', status: 'operational', statusLabel: 'All systems normal', updated: '2 days ago',
                resources: [ { icon: '🗺️', title: 'VLAN & subnet map', desc: 'Live IPAM with per-building scopes' }, { icon: '🧱', title: 'Firewall rule base', desc: 'FortiGate policies & change log' }, { icon: '🔀', title: 'Switch inventory', desc: '312 access + 18 core switches' }, { icon: '📡', title: 'Wi-Fi heatmaps', desc: 'Ekahau surveys by campus' } ],
                docs: [{ icon: '📶', title: 'Wireless config standard', meta: 'SSID & VLAN naming' }, { icon: '🛠️', title: 'Switch replacement runbook', meta: 'Step-by-step' }, { icon: '🚨', title: 'Outage escalation path', meta: 'ISP + vendor contacts' }, { icon: '🗺️', title: 'Cabling & MDF/IDF map', meta: 'Per building' }],
                projects: [ { name: 'Wi-Fi 6 upgrade — phase 2', status: 'In progress', pct: 62, owner: 'MC', pri: 'High' }, { name: 'Core switch refresh (Bldg C)', status: 'Planning', pct: 18, owner: 'RT', pri: 'Med' }, { name: 'Fiber backbone to annex', status: 'Blocked', pct: 35, owner: 'MC', pri: 'High' } ],
                tasks: [ { title: 'Push AP firmware district-wide', due: 'Jun 27', owner: 'MC', done: false }, { title: 'Audit unused switch ports', due: 'Jul 2', owner: 'RT', done: false }, { title: 'Document VLAN 40 re-map', due: 'Jun 25', owner: 'LP', done: true }, { title: 'ISP contract renewal review', due: 'Jul 10', owner: 'MC', done: false } ],
                contacts: ['MC', 'RT', 'LP'] },
            security: { icon: '🔐', title: 'Security', hue: '#E45959', subtitle: 'Identity, endpoint protection, monitoring and incident response for the district.', owner: 'Priya Shah', ownerInit: 'PS', status: 'degraded', statusLabel: '1 open investigation', updated: '5 hours ago',
                resources: [ { icon: '🛡️', title: 'SIEM dashboard', desc: 'Microsoft Sentinel alerts' }, { icon: '🎣', title: 'Phishing reports', desc: 'Sim results + reported emails' }, { icon: '👤', title: 'Access reviews', desc: 'Quarterly entitlement audit' }, { icon: '📜', title: 'Certificate tracker', desc: 'Expiry calendar & renewals' } ],
                docs: [{ icon: '🚨', title: 'Incident response playbook', meta: 'Sev 1–3 procedures' }, { icon: '🔑', title: 'MFA enrollment guide', meta: 'Staff & student' }, { icon: '📋', title: 'Data breach checklist', meta: 'FERPA-aligned' }, { icon: '🛡️', title: 'Hardening baselines', meta: 'CIS benchmarks' }],
                projects: [ { name: 'Zero-trust rollout — phase 2', status: 'Planning', pct: 15, owner: 'PS', pri: 'High' }, { name: 'EDR deployment to all endpoints', status: 'In progress', pct: 70, owner: 'RT', pri: 'High' }, { name: 'Quarterly access review — Q3', status: 'In progress', pct: 45, owner: 'PS', pri: 'Med' } ],
                tasks: [ { title: 'Investigate flagged sign-ins', due: 'Jun 24', owner: 'PS', done: false }, { title: 'Rotate service-account credentials', due: 'Jun 30', owner: 'RT', done: false }, { title: 'Publish June phishing results', due: 'Jun 23', owner: 'PS', done: true }, { title: 'Renew wildcard TLS certificate', due: 'Jul 5', owner: 'MC', done: false } ],
                contacts: ['PS', 'RT', 'MC'] },
            technician: { icon: '🔧', title: 'Technician', hue: '#34AF67', subtitle: 'Field support, device imaging, repairs and the day-to-day help desk queue.', owner: 'Dana Alvarez', ownerInit: 'DA', status: 'operational', statusLabel: 'Queue healthy', updated: '1 hour ago',
                resources: [ { icon: '💿', title: 'Imaging guides', desc: 'Autopilot, iPad & Chromebook' }, { icon: '🎫', title: 'Ticket queue', desc: '23 open · 4 awaiting parts' }, { icon: '🎒', title: 'Loaner inventory', desc: 'Carts, hotspots & spares' }, { icon: '🧰', title: 'Repair log', desc: 'Warranty & RMA tracking' } ],
                docs: [{ icon: '📺', title: 'Classroom AV troubleshooting', meta: 'Top 12 issues' }, { icon: '🖨️', title: 'Printer / copier setup', meta: 'By building' }, { icon: '📦', title: 'Asset check-in/out', meta: 'Snipe-IT workflow' }, { icon: '💿', title: 'Imaging guide (Autopilot)', meta: 'Step-by-step' }],
                projects: [ { name: 'Chromebook refresh — 2,400 units', status: 'In progress', pct: 40, owner: 'DA', pri: 'High' }, { name: 'Classroom AV standardization', status: 'In progress', pct: 55, owner: 'LP', pri: 'Med' }, { name: 'Summer device collection', status: 'Planning', pct: 10, owner: 'DA', pri: 'Low' } ],
                tasks: [ { title: 'Image 60 staff laptops (Autopilot)', due: 'Jun 26', owner: 'DA', done: false }, { title: 'Clear awaiting-parts tickets', due: 'Jun 25', owner: 'LP', done: false }, { title: 'Restock loaner cart — Bldg A', due: 'Jun 24', owner: 'LP', done: true }, { title: 'Update repair / RMA log', due: 'Jun 28', owner: 'DA', done: false } ],
                contacts: ['DA', 'LP', 'MC'] },
            admin: { icon: '⚙️', title: 'Admin', hue: '#FFA059', subtitle: 'Microsoft 365 administration, licensing, procurement and IT policy.', owner: 'Robin Tate', ownerInit: 'RT', status: 'maintenance', statusLabel: 'Patch window Sat', updated: '3 days ago',
                resources: [ { icon: '☁️', title: 'M365 admin center', desc: 'Tenant, groups & Intune' }, { icon: '🧾', title: 'Licensing matrix', desc: '38 products & seat counts' }, { icon: '🛒', title: 'Procurement tracker', desc: 'POs, quotes & E-rate' }, { icon: '📑', title: 'IT policies', desc: 'AUP, BYOD & data retention' } ],
                docs: [{ icon: '👥', title: 'Onboarding / offboarding', meta: 'Account lifecycle' }, { icon: '💵', title: 'Budget & E-rate calendar', meta: 'FY26 deadlines' }, { icon: '📝', title: 'Change request form', meta: 'Approval workflow' }, { icon: '☁️', title: 'Intune policy reference', meta: 'Device baselines' }],
                projects: [ { name: 'PowerSchool SSO integration', status: 'Blocked', pct: 30, owner: 'RT', pri: 'High' }, { name: 'Intune policy baseline', status: 'In progress', pct: 65, owner: 'RT', pri: 'Med' }, { name: 'E-rate FY26 filing', status: 'Planning', pct: 25, owner: 'RT', pri: 'High' } ],
                tasks: [ { title: 'Reconcile M365 license seats', due: 'Jun 27', owner: 'RT', done: false }, { title: 'Approve Q3 procurement POs', due: 'Jun 25', owner: 'RT', done: false }, { title: 'Offboard 3 separated staff', due: 'Jun 23', owner: 'PS', done: true }, { title: 'Update AUP for board review', due: 'Jul 8', owner: 'SO', done: false } ],
                contacts: ['RT', 'PS', 'SO'] },
            database: { icon: '🗄️', title: 'Database', hue: '#5BC0DE', subtitle: 'Student information systems, reporting, backups and data integrations.', owner: 'Sam Ortiz', ownerInit: 'SO', status: 'operational', statusLabel: 'Backups verified', updated: '8 hours ago',
                resources: [ { icon: '🏫', title: 'SIS schema reference', desc: 'PowerSchool tables & views' }, { icon: '💾', title: 'Backup jobs', desc: 'Nightly · last verified 2 AM' }, { icon: '🔎', title: 'Query library', desc: 'Saved reports & SQL snippets' }, { icon: '🔗', title: 'Integration map', desc: 'Clever, Canvas & state feeds' } ],
                docs: [{ icon: '💾', title: 'Backup & recovery runbook', meta: 'RPO / RTO targets' }, { icon: '📊', title: 'State reporting calendar', meta: 'Submission windows' }, { icon: '🔐', title: 'Data access grants', meta: 'Role-based approval' }, { icon: '🔎', title: 'Saved query library', meta: 'SQL snippets' }],
                projects: [ { name: 'Clever roster sync overhaul', status: 'In progress', pct: 50, owner: 'SO', pri: 'High' }, { name: 'State reporting automation', status: 'In progress', pct: 35, owner: 'SO', pri: 'Med' }, { name: 'Backup target migration', status: 'Planning', pct: 20, owner: 'RT', pri: 'Med' } ],
                tasks: [ { title: 'Verify nightly backup integrity', due: 'Jun 24', owner: 'SO', done: true }, { title: 'Optimize slow SIS report query', due: 'Jun 29', owner: 'SO', done: false }, { title: 'Grant Canvas data-feed access', due: 'Jun 26', owner: 'RT', done: false }, { title: 'Prep EOY state submission', due: 'Jul 12', owner: 'SO', done: false } ],
                contacts: ['SO', 'RT', 'PS'] },
            change: { icon: '🔄', title: 'Change Management', hue: '#FFC859', subtitle: 'Planned changes, approvals, maintenance windows and post-change review across IT.', owner: 'Robin Tate', ownerInit: 'RT', status: 'maintenance', statusLabel: '3 changes scheduled', updated: '6 hours ago',
                resources: [ { icon: '📋', title: 'Change calendar', desc: 'Upcoming windows & freezes' }, { icon: '✅', title: 'Approval board (CAB)', desc: '4 awaiting review' }, { icon: '📝', title: 'Change request form', desc: 'Standard, normal & emergency' }, { icon: '🔁', title: 'Rollback playbooks', desc: 'Per-system recovery steps' } ],
                docs: [{ icon: '📑', title: 'Change management policy', meta: 'Risk tiers & SLAs' }, { icon: '🗓️', title: 'Maintenance window guide', meta: 'Scheduling & comms' }, { icon: '🔍', title: 'Post-implementation review', meta: 'Template & log' }, { icon: '🔁', title: 'Rollback playbooks', meta: 'Per-system' }],
                projects: [ { name: 'Q3 patch window planning', status: 'Planning', pct: 30, owner: 'RT', pri: 'High' }, { name: 'CAB workflow in ticketing', status: 'In progress', pct: 60, owner: 'RT', pri: 'Med' }, { name: 'Rollback playbook library', status: 'In progress', pct: 45, owner: 'MC', pri: 'Med' } ],
                tasks: [ { title: 'Review 4 pending change requests', due: 'Jun 24', owner: 'RT', done: false }, { title: 'Schedule Sat maintenance comms', due: 'Jun 25', owner: 'PS', done: false }, { title: 'Close out June change log', due: 'Jun 30', owner: 'RT', done: false }, { title: 'PIR for SIS upgrade', due: 'Jun 27', owner: 'MC', done: true } ],
                contacts: ['RT', 'MC', 'PS'] },
        };

        function greetingText() {
            const h = new Date().getHours();
            const part = h < 12 ? 'morning' : (h < 18 ? 'afternoon' : 'evening');
            return `Good ${part}, team 👋`;
        }

        // ---- sidebar nav ----
        function navItem(active) { return `display:flex;align-items:center;gap:9px;padding:7px 10px;border-radius:8px;cursor:pointer;font-size:13.5px;font-weight:${active?600:500};color:${active?'#F2F4F6':'#9BA1A9'};background:${active?'rgba(228,89,89,.15)':'transparent'};box-shadow:${active?'inset 2px 0 0 '+ACCENT:'none'}`; }
        function renderNav() {
            const home = state.activePage === 'home';
            let h = `<div class="its-click its-hoverable" data-nav="home" style="${navItem(home)}"><span style="font-size:15px;width:18px;text-align:center">🏠</span><span>Home</span></div>`;
            h += `<div style="font-family:'IBM Plex Mono',monospace;font-size:10.5px;letter-spacing:.08em;color:#5F656E;text-transform:uppercase;padding:16px 10px 5px 10px">Team pages</div>`;
            teamDefs.forEach(p => {
                const active = state.activePage === p.id;
                h += `<div class="its-click its-hoverable" data-nav="${p.id}" style="${navItem(active)}"><span style="font-size:15px;width:18px;text-align:center">${p.icon}</span><span>${esc(p.label)}</span><span style="margin-left:auto;font-family:'IBM Plex Mono',monospace;font-size:10.5px;color:#6E747D">${esc(p.count)}</span></div>`;
            });
            return h;
        }

        // ---- shared bits ----
        function projectsTable(list) {
            let rows = list.map(p => {
                const m = projMeta[p.status]; const oc = lk(p.owner).color;
                return `<div class="its-hoverable" style="display:grid;grid-template-columns:1.8fr .8fr 1.1fr .7fr;gap:12px;padding:13px 16px;border-bottom:1px solid #23262C;align-items:center;cursor:pointer">
                    <div style="display:flex;align-items:center;gap:9px;min-width:0"><span style="width:8px;height:8px;border-radius:50%;background:${priColor[p.pri]};flex:none"></span><span style="font-size:13.5px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#E3E6EA">${esc(p.name)}</span></div>
                    <div><span style="display:inline-block;font-size:11.5px;font-weight:600;padding:2px 9px;border-radius:6px;background:${m.c}22;color:${m.c}">${esc(p.status)}</span></div>
                    <div style="display:flex;align-items:center;gap:8px"><div style="flex:1;height:6px;background:#2A2D34;border-radius:99px;overflow:hidden"><div style="width:${p.pct}%;height:100%;background:${m.c};border-radius:99px"></div></div><span style="font-family:'IBM Plex Mono',monospace;font-size:11px;color:#9BA1A9;width:32px;text-align:right">${p.pct}%</span></div>
                    <div style="display:flex;align-items:center;gap:7px"><div style="${av(oc,24)}">${esc(p.owner)}</div></div>
                </div>`;
            }).join('');
            return `<div style="background:#1B1E24;border:1px solid #2A2D34;border-radius:12px;overflow:hidden">
                <div style="display:grid;grid-template-columns:1.8fr .8fr 1.1fr .7fr;gap:12px;padding:9px 16px;background:#15171C;border-bottom:1px solid #2A2D34;font-family:'IBM Plex Mono',monospace;font-size:10.5px;letter-spacing:.05em;text-transform:uppercase;color:#6E747D"><div>Project</div><div>Status</div><div>Progress</div><div>Owner</div></div>
                ${rows || `<div style="padding:22px;text-align:center;color:#7E848D;font-size:13px">No projects in this status.</div>`}
            </div>`;
        }

        function linksGrid() {
            const q = state.search.trim().toLowerCase();
            const filtered = links.filter(l => !q || l.title.toLowerCase().includes(q) || l.meta.toLowerCase().includes(q));
            if (!filtered.length) return `<div style="grid-column:1/-1;padding:24px;text-align:center;color:#7E848D;font-size:13px;background:#1B1E24;border:1px dashed #2A2D34;border-radius:12px">No tools match that search.</div>`;
            return filtered.map(l => `<a href="#" class="its-hoverable" style="display:flex;flex-direction:column;gap:9px;padding:14px;background:#1B1E24;border:1px solid #2A2D34;border-radius:12px;cursor:pointer">
                <div style="width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:19px;background:${l.c}22;border:1px solid ${l.c}40">${l.icon}</div>
                <div><div style="font-size:13.5px;font-weight:600;line-height:1.2;color:#EDEFF2">${esc(l.title)}</div><div style="font-size:11.5px;color:#7E848D;margin-top:2px">${esc(l.meta)}</div></div>
            </a>`).join('');
        }

        // ---- home view ----
        function renderHome() {
            const showAnn = state.tab === 'all' || state.tab === 'ann';
            const showProj = state.tab === 'all' || state.tab === 'proj';
            const showDocs = state.tab === 'all' || state.tab === 'docs';
            const showMeet = state.tab === 'all' || state.tab === 'meet';

            const memberAvatars = team.map((m, i) => `<div style="${av(m.color,30)};border:2px solid #0E1116;margin-left:${i?-8:0}px;font-size:11px">${esc(m.initials)}</div>`).join('');

            const tabsHtml = tabDefs.map(t => { const active = state.tab === t.key; return `<div class="its-click" data-tab="${t.key}" style="padding:9px 13px;font-size:13.5px;font-weight:${active?700:500};color:${active?ACCENT:'#7E848D'};border-bottom:2px solid ${active?ACCENT:'transparent'};margin-bottom:-1px;cursor:pointer">${esc(t.label)}</div>`; }).join('');

            let annStatus = '';
            if (showAnn) {
                const annHtml = annRaw.map(a => `<div class="its-hoverable" style="background:#1B1E24;border:1px solid #2A2D34;border-radius:12px;padding:14px 16px">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px"><span style="${tagPill(a.tag)}">${esc(a.tag)}</span>${a.pinned?'<span style="font-size:11px;color:#FFC859">📌 Pinned</span>':''}<span style="margin-left:auto;font-family:\\'IBM Plex Mono\\',monospace;font-size:11px;color:#6E747D">${esc(a.date)}</span></div>
                    <div style="font-size:14.5px;font-weight:600;line-height:1.3;color:#EDEFF2">${esc(a.title)}</div>
                    <div style="font-size:13px;color:#9BA1A9;margin-top:4px;line-height:1.45">${esc(a.body)}</div>
                    <div style="display:flex;align-items:center;gap:7px;margin-top:9px"><div style="${av(a.c,20)}">${esc(a.initials)}</div><span style="font-size:12px;color:#7E848D">${esc(a.author)}</span></div>
                </div>`).join('');
                annStatus = `<div style="display:grid;grid-template-columns:1.4fr 1fr;gap:18px;margin-top:24px">
                    <div><div style="font-size:15px;font-weight:700;margin-bottom:12px;color:#F2F4F6">📣 Announcements</div><div style="display:flex;flex-direction:column;gap:10px">${annHtml}</div></div>
                    <div><div style="font-size:15px;font-weight:700;margin-bottom:12px;color:#F2F4F6">🟢 System status</div><div id="itsStatus" style="background:#1B1E24;border:1px solid #2A2D34;border-radius:12px;overflow:hidden">${statusPanelInner()}</div><div id="itsStatusMeta" style="font-family:'IBM Plex Mono',monospace;font-size:11px;color:#6E747D;margin-top:8px;text-align:right">${statusMetaText()}</div></div>
                </div>`;
            }

            let projSection = '';
            if (showProj) {
                const chips = chipDefs.map(c => { const active = state.projFilter === c.k; return `<div class="its-click its-hoverable" data-chip="${esc(c.k)}" style="font-size:12px;font-weight:${active?600:500};padding:4px 11px;border-radius:99px;cursor:pointer;border:1px solid ${active?ACCENT:'#2A2D34'};background:${active?ACCENT:'#1B1E24'};color:${active?'#fff':'#9BA1A9'}">${esc(c.l)}</div>`; }).join('');
                const filtered = projRaw.filter(p => state.projFilter === 'all' || p.status === state.projFilter);
                projSection = `<div style="margin-top:30px"><div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;flex-wrap:wrap"><div style="font-size:15px;font-weight:700;color:#F2F4F6">🚧 Active projects</div><div style="display:flex;gap:6px;margin-left:6px">${chips}</div></div>${projectsTable(filtered)}</div>`;
            }

            let docsMeet = '';
            const docsCol = showDocs ? `<div><div style="font-size:15px;font-weight:700;margin-bottom:12px;color:#F2F4F6">📚 Knowledge base</div><div style="display:flex;flex-direction:column;gap:8px">${docs.map(d => `<a href="#" class="its-hoverable" style="display:flex;align-items:center;gap:11px;padding:11px 14px;background:#1B1E24;border:1px solid #2A2D34;border-radius:10px;cursor:pointer"><span style="font-size:18px">${d.icon}</span><div style="flex:1;min-width:0"><div style="font-size:13.5px;font-weight:600;line-height:1.2;color:#E3E6EA">${esc(d.title)}</div><div style="font-size:11.5px;color:#7E848D;margin-top:1px">${esc(d.meta)}</div></div><span style="color:#5F656E;font-size:13px">→</span></a>`).join('')}</div></div>` : '';
            const meetCol = showMeet ? `<div><div style="font-size:15px;font-weight:700;margin-bottom:12px;color:#F2F4F6">🗓️ Meeting notes</div><div style="display:flex;flex-direction:column;gap:8px">${meetRaw.map(mt => `<a href="#" class="its-hoverable" style="display:flex;align-items:center;gap:12px;padding:11px 14px;background:#1B1E24;border:1px solid #2A2D34;border-radius:10px;cursor:pointer"><div style="width:42px;height:42px;border-radius:9px;flex:none;display:flex;flex-direction:column;align-items:center;justify-content:center;background:${mt.c}22;color:${mt.c}"><div style="font-size:9.5px;font-family:'IBM Plex Mono',monospace;line-height:1;opacity:.85">${esc(mt.mon)}</div><div style="font-size:16px;font-weight:800;line-height:1">${esc(mt.day)}</div></div><div style="flex:1;min-width:0"><div style="font-size:13.5px;font-weight:600;line-height:1.2;color:#E3E6EA">${esc(mt.title)}</div><div style="font-size:11.5px;color:#7E848D;margin-top:1px">${esc(mt.meta)}</div></div></a>`).join('')}</div></div>` : '';
            if (docsCol || meetCol) docsMeet = `<div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:30px">${docsCol}${meetCol}</div>`;

            return `<div>
                <div style="height:148px;background:linear-gradient(120deg,#1B1E24 0%,#15171C 55%,#241317 100%);position:relative;overflow:hidden;border-bottom:1px solid #24272E">
                    <div style="position:absolute;inset:0;background-image:radial-gradient(circle at 18% 130%,rgba(228,89,89,.22),transparent 40%),radial-gradient(circle at 82% -30%,rgba(116,153,255,.16),transparent 45%)"></div>
                    <div style="position:absolute;right:30px;top:24px;font-family:'IBM Plex Mono',monospace;font-size:11px;color:rgba(232,234,237,.55);letter-spacing:.05em">SY 2025–26 · Internal</div>
                </div>
                <div style="max-width:980px;margin:0 auto;padding:0 56px 60px 56px">
                    <div style="font-size:60px;line-height:1;margin-top:-40px;position:relative;filter:drop-shadow(0 6px 16px rgba(0,0,0,.5))">🖥️</div>
                    <h1 style="font-size:34px;font-weight:800;letter-spacing:-.02em;margin:14px 0 6px 0;color:#F4F6F8">IT Team Space</h1>
                    <p style="font-size:15px;color:#9BA1A9;margin:0;max-width:560px;line-height:1.5">The internal hub for the technology team — tools, status, projects, runbooks and notes, all in one place.</p>
                    <div style="display:flex;align-items:center;gap:14px;margin-top:16px;flex-wrap:wrap">
                        <div style="display:flex">${memberAvatars}</div>
                        <span style="font-size:12.5px;color:#7E848D">6 team members</span>
                        <span style="font-size:12.5px;color:#4A4F57">·</span>
                        <span style="font-family:'IBM Plex Mono',monospace;font-size:11.5px;color:#7E848D">Edited 2h ago</span>
                    </div>
                    <div style="margin-top:8px;font-size:18px;font-weight:700;padding-top:26px;color:#F2F4F6">${esc(greetingText())}</div>

                    <div style="display:flex;align-items:center;justify-content:space-between;margin-top:22px;margin-bottom:12px">
                        <div style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#6E747D;font-family:'IBM Plex Mono',monospace">Quick links</div>
                        <div style="display:flex;align-items:center;gap:8px;background:#1B1E24;border:1px solid #2A2D34;border-radius:9px;padding:6px 11px;width:230px">
                            <span style="font-size:12px;color:#6E747D">🔍</span>
                            <input id="itsSearch" value="${esc(state.search)}" placeholder="Filter tools…" style="border:none;outline:none;background:transparent;font-family:inherit;font-size:13px;color:#E8EAED;width:100%" />
                        </div>
                    </div>
                    <div id="itsLinks" style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px">${linksGrid()}</div>

                    <div style="display:flex;gap:4px;margin-top:34px;border-bottom:1px solid #24272E">${tabsHtml}</div>
                    ${annStatus}
                    ${projSection}
                    ${docsMeet}
                </div>
            </div>`;
        }

        // ---- team page view ----
        function renderTeam(id) {
            const pd = pageDefs[id]; const m = stMeta[pd.status];
            const resources = pd.resources.map(r => `<a href="#" class="its-hoverable" style="display:flex;gap:12px;padding:15px;background:#1B1E24;border:1px solid #2A2D34;border-radius:12px;cursor:pointer"><div style="width:40px;height:40px;border-radius:10px;flex:none;display:flex;align-items:center;justify-content:center;font-size:20px;background:${pd.hue}22;border:1px solid ${pd.hue}40">${r.icon}</div><div style="min-width:0"><div style="font-size:14px;font-weight:600;line-height:1.25;color:#EDEFF2">${esc(r.title)}</div><div style="font-size:12px;color:#7E848D;margin-top:3px;line-height:1.4">${esc(r.desc)}</div></div></a>`).join('');
            const tasks = pd.tasks.map((t, i) => { const oc = lk(t.owner).color; return `<div style="display:flex;align-items:center;gap:11px;padding:11px 14px;${i?'border-top:1px solid #23262C':''}"><span style="font-size:16px;color:${t.done?pd.hue:'#5F656E'};flex:none">${t.done?'☑':'☐'}</span><div style="flex:1;min-width:0"><div style="font-size:13.5px;font-weight:500;${t.done?'color:#6E747D;text-decoration:line-through':'color:#D6D9DE'}">${esc(t.title)}</div></div><span style="font-family:'IBM Plex Mono',monospace;font-size:11px;color:#6E747D;flex:none">${esc(t.due)}</span><div style="${av(oc,22)}">${esc(t.owner)}</div></div>`; }).join('');
            const contacts = pd.contacts.map((init, i) => { const p = lk(init); return `<div style="display:flex;align-items:center;gap:10px;padding:11px 14px;${i?'border-top:1px solid #23262C':''}"><div style="${av(p.color,32)}">${esc(p.initials)}</div><div style="min-width:0"><div style="font-size:13px;font-weight:600;line-height:1.2;color:#E3E6EA">${esc(p.name)}</div><div style="font-size:11.5px;color:#7E848D">${esc(p.role)}</div></div></div>`; }).join('');
            const kbDocs = pd.docs.map(d => `<a href="#" class="its-hoverable" style="display:flex;align-items:center;gap:11px;padding:11px 14px;background:#1B1E24;border:1px solid #2A2D34;border-radius:10px;cursor:pointer"><span style="font-size:17px">${d.icon}</span><div style="flex:1"><div style="font-size:13.5px;font-weight:600;color:#E3E6EA">${esc(d.title)}</div><div style="font-size:11.5px;color:#7E848D;margin-top:1px">${esc(d.meta)}</div></div><span style="color:#5F656E">→</span></a>`).join('');
            const activeCount = pd.projects.filter(p => p.status !== 'Done').length;

            return `<div>
                <div style="height:120px;background:linear-gradient(120deg,${pd.hue}33 0%,#15171C 65%);position:relative;overflow:hidden;border-bottom:1px solid #24272E"><div style="position:absolute;inset:0;background-image:radial-gradient(circle at 85% -30%,${pd.hue}33,transparent 50%)"></div></div>
                <div style="max-width:920px;margin:0 auto;padding:0 56px 60px 56px">
                    <div class="its-click" data-nav="home" style="display:inline-flex;align-items:center;gap:6px;margin-top:18px;font-size:12.5px;color:#7E848D;cursor:pointer">← Home / <span style="font-weight:600;color:#9BA1A9">Team pages</span></div>
                    <div style="font-size:54px;line-height:1;margin-top:8px">${pd.icon}</div>
                    <h1 style="font-size:30px;font-weight:800;letter-spacing:-.02em;margin:12px 0 6px 0;color:#F4F6F8">${esc(pd.title)}</h1>
                    <p style="font-size:14.5px;color:#9BA1A9;margin:0;max-width:560px;line-height:1.5">${esc(pd.subtitle)}</p>
                    <div style="display:flex;align-items:center;gap:14px;margin-top:14px;flex-wrap:wrap">
                        <div style="display:flex;align-items:center;gap:7px"><div style="${av(pd.hue,24)}">${esc(pd.ownerInit)}</div><span style="font-size:12.5px;color:#7E848D">${esc(pd.owner)} · Lead</span></div>
                        <span style="font-family:'IBM Plex Mono',monospace;font-size:11px;padding:3px 9px;border-radius:6px;background:${m.c}22;color:${m.c}">${esc(pd.statusLabel)}</span>
                        <span style="font-family:'IBM Plex Mono',monospace;font-size:11.5px;color:#6E747D">Updated ${esc(pd.updated)}</span>
                    </div>

                    <div style="font-family:'IBM Plex Mono',monospace;font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:#6E747D;margin:30px 0 12px 0">Key resources</div>
                    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px">${resources}</div>

                    <div style="display:flex;align-items:center;gap:10px;margin:34px 0 12px 0"><div style="font-family:'IBM Plex Mono',monospace;font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:#6E747D">Projects</div><span style="font-family:'IBM Plex Mono',monospace;font-size:10.5px;color:#9BA1A9;background:#22252C;border-radius:6px;padding:2px 7px">${activeCount} active</span></div>
                    ${projectsTable(pd.projects)}

                    <div style="display:grid;grid-template-columns:1.5fr 1fr;gap:24px;margin-top:32px">
                        <div><div style="font-family:'IBM Plex Mono',monospace;font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:#6E747D;margin-bottom:12px">Tasks</div><div style="background:#1B1E24;border:1px solid #2A2D34;border-radius:12px;overflow:hidden">${tasks}</div></div>
                        <div><div style="font-family:'IBM Plex Mono',monospace;font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:#6E747D;margin-bottom:12px">Team</div><div style="background:#1B1E24;border:1px solid #2A2D34;border-radius:12px;overflow:hidden">${contacts}</div></div>
                    </div>

                    <div style="font-family:'IBM Plex Mono',monospace;font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:#6E747D;margin:34px 0 12px 0">Knowledge base</div>
                    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px">${kbDocs}</div>
                </div>
            </div>`;
        }

        // ---- render + events ----
        const navEl = document.getElementById('itsNav');
        const mainEl = document.getElementById('itsMain');

        function render() {
            navEl.innerHTML = renderNav();
            mainEl.innerHTML = state.activePage === 'home' ? renderHome() : renderTeam(state.activePage);
            const search = document.getElementById('itsSearch');
            if (search) {
                search.addEventListener('input', (e) => {
                    state.search = e.target.value;
                    const grid = document.getElementById('itsLinks');
                    if (grid) grid.innerHTML = linksGrid();
                });
            }
        }

        document.addEventListener('click', (e) => {
            const nav = e.target.closest('[data-nav]');
            if (nav) { state.activePage = nav.getAttribute('data-nav'); if (state.activePage === 'home') { state.tab = 'all'; } mainEl.scrollTop = 0; render(); return; }
            const tab = e.target.closest('[data-tab]');
            if (tab) { state.tab = tab.getAttribute('data-tab'); render(); return; }
            const chip = e.target.closest('[data-chip]');
            if (chip) { state.projFilter = chip.getAttribute('data-chip'); render(); return; }
        });

        // hover affordance without per-element JS
        document.addEventListener('mouseover', (e) => { const h = e.target.closest('.its-hoverable'); if (h) h.style.filter = 'brightness(1.06)'; });
        document.addEventListener('mouseout', (e) => { const h = e.target.closest('.its-hoverable'); if (h) h.style.filter = ''; });

        render();
        fetchStatus();
        setInterval(fetchStatus, 60000); // refresh live system status each minute
    })();
    </script>
</body>
</html>
