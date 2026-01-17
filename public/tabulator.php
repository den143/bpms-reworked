<?php
// public/tabulator.php
// Purpose: The Event Manager's view of the tabulation results.
// Note: The actual "Tabulator" role uses 'tabulator_dashboard.php' instead.

require_once __DIR__ . '/../app/core/guard.php';
requireLogin();
requireRole('Event Manager'); // <--- RESTRICTED TO MANAGER ONLY
require_once __DIR__ . '/../app/config/database.php';

$user_id = $_SESSION['user_id'];

// 1. GET ACTIVE EVENT
$evt_sql = "SELECT id, title FROM events WHERE status = 'Active' LIMIT 1";
$event = $conn->query($evt_sql)->fetch_assoc();

if (!$event) {
    die("<div style='text-align:center; padding:50px; font-family:sans-serif; color:#6b7280;'>
            <h1><i class='fas fa-calendar-times'></i> No Active Event</h1>
            <p>You must set an event to 'Active' status in the Settings or Dashboard first.</p>
            <a href='dashboard.php'>Go to Dashboard</a>
         </div>");
}
$event_id = $event['id'];

// 2. GET ROUNDS
$rnd_sql = "SELECT id, title, status FROM rounds WHERE event_id = $event_id AND is_deleted = 0 ORDER BY ordering";
$rounds = $conn->query($rnd_sql)->fetch_all(MYSQLI_ASSOC);

$current_round_id = isset($_GET['round_id']) ? (int)$_GET['round_id'] : ($rounds[0]['id'] ?? 0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tabulation - <?= htmlspecialchars($event['title']) ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=14">
    <link rel="stylesheet" href="./assets/fontawesome/css/all.min.css">
    <style>
        :root { --gold: #F59E0B; --dark: #111827; --success: #059669; }
        
        /* --- LAYOUT --- */
        .tab-nav { display: flex; gap: 10px; margin-bottom: 20px; overflow-x: auto; padding-bottom: 5px; }
        .tab-item { padding: 10px 20px; background: #fff; border: 1px solid #ddd; border-radius: 8px; text-decoration: none; color: #374151; font-weight: 600; white-space: nowrap; transition: 0.2s; }
        .tab-item:hover { background: #f9fafb; border-color: #ccc; }
        .tab-item.active { background: var(--dark); color: white; border-color: var(--dark); }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 15px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border-top: 4px solid var(--gold); }
        
        .judge-status-list { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
        .j-badge { font-size: 11px; padding: 4px 10px; border-radius: 15px; background: #f3f4f6; color: #6b7280; font-weight: bold; border: 1px solid #e5e7eb; display: flex; align-items: center; gap: 5px; }
        .j-badge.submitted { background: #d1fae5; color: #065f46; border-color: #34d399; }

        /* --- CONTROLS --- */
        .control-toolbar { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 15px; margin-bottom: 15px; background: #fff; padding: 10px 15px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border: 1px solid #e5e7eb; }
        .view-switcher { display: flex; background: #f3f4f6; padding: 4px; border-radius: 8px; gap: 2px; }
        .view-btn { padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; font-weight: bold; font-size: 13px; transition: 0.2s; color: #6b7280; background: transparent; }
        .view-btn.active { background: white; color: var(--dark); box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        
        .action-group { display: flex; gap: 10px; }
        .action-btn { background: white; border: 1px solid #d1d5db; padding: 8px 16px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 8px; font-weight: 600; color: #374151; transition: 0.2s; font-size: 13px; }
        .action-btn:hover { background: #f9fafb; border-color: #9ca3af; }
        .lock-btn { background: var(--dark); color: white; border: none; padding: 8px 20px; border-radius: 6px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.2s; font-size: 13px; }
        .lock-btn:disabled { background: #4b5563; cursor: not-allowed; opacity: 0.7; }

        /* --- TABLES --- */
        .tally-card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .table-responsive { overflow-x: auto; width: 100%; }
        .tally-table { width: 100%; border-collapse: collapse; min-width: 800px; }
        .tally-table th { background: #f8fafc; padding: 12px; text-align: center; font-size: 11px; color: #64748b; text-transform: uppercase; border: 1px solid #e2e8f0; }
        .tally-table td { padding: 10px; text-align: center; border: 1px solid #e2e8f0; font-size: 13px; }

        /* --- AUDIT SPECIFIC --- */
        .seg-title { margin: 0; color: #1f2937; font-size: 18px; border-left: 5px solid #F59E0B; padding-left: 10px; }
        .seg-weight { color: #6b7280; font-weight: normal; font-size: 14px; }
        .segment-block { margin-bottom: 15px; padding-top: 20px; border-top: 1px dashed #e5e7eb; }
        .segment-block:first-child { border-top: none; padding-top: 0; }
        .judge-header { background: #f3f4f6; font-weight: bold; border-left: 2px solid #ccc !important; }
        .crit-header { font-size: 10px; background: #fff; color: #888; }
        .audit-sticky-col { background: #f9fafb; font-weight: bold; position: sticky; left: 0; z-index: 2; border-right: 2px solid #ddd !important; }

        /* --- HELPERS --- */
        .rank-box { width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; border-radius: 50%; margin: 0 auto; font-weight: 800; font-size: 14px; background: #f3f4f6; color: #666; transition: 0.3s; }
        
        .row-qualified .rank-box { 
            background: var(--gold); 
            color: white; 
            box-shadow: 0 2px 5px rgba(245, 158, 11, 0.5); 
            border: 1px solid #d97706;
            font-weight: 900;
        }

        .row-eliminated .rank-box {
            background: #e5e7eb;
            color: #9ca3af;
        }

        .c-img { width: 35px; height: 35px; border-radius: 50%; object-fit: cover; border: 2px solid #f3f4f6; margin-right:10px; vertical-align:middle; }
        .hidden { display: none !important; }
        
        .status-badge { font-size: 10px; padding: 3px 8px; border-radius: 12px; font-weight: 800; text-transform: uppercase; }
        .badge-q { background: #059669; color: white; }
        .badge-e { background: #e5e7eb; color: #6b7280; border: 1px solid #d1d5db; }
        .badge-pending { background: #f3f4f6; color: #9ca3af; border: 1px solid #e5e7eb; }
        
        .row-qualified { background-color: #ecfdf5 !important; border-left: 5px solid #059669; }
        .row-eliminated { background-color: #fff !important; color: #6b7280; }

        /* --- AWARDS GRID --- */
        .awards-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; padding: 20px; }
        .award-tile { background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; }
        .manual-select { width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; margin-top: 5px; }

        /* --- PRINT STYLES --- */
        @media print {
            @page { size: landscape; margin: 0.5cm; }
            body { background: white; color: black; font-family: 'Times New Roman', serif; }
            .sidebar, .navbar, .tab-nav, .stats-grid, .control-toolbar, .menu-toggle { display: none !important; }
            .main-wrapper, .content-area, .container { margin: 0 !important; padding: 0 !important; width: 100% !important; display: block !important; }
            .tally-card, .award-tile { border: 1px solid #000 !important; box-shadow: none !important; margin-bottom: 20px; }
            .tally-table th, .tally-table td { border: 1px solid #000 !important; color: #000 !important; }
            
            #auditView:not(.hidden) { display: block !important; }
            .segment-block { page-break-inside: avoid; border-top: 2px solid #000; padding-top: 10px; }
            
            .tally-card::before {
                content: "OFFICIAL RESULT SHEET: <?= strtoupper(htmlspecialchars($event['title'])) ?>";
                display: block; text-align: center; font-weight: bold; font-size: 16pt; margin-bottom: 20px;
            }
        }
    </style>
</head>
<body>

<div id="sidebarOverlay" class="sidebar-overlay" onclick="toggleSidebar()"></div>

<div class="main-wrapper">
    
    <?php include_once __DIR__ . '/../app/views/partials/sidebar.php'; ?>

    <div class="content-area">
        <div class="navbar" style="background:var(--dark); color:white; padding:15px 25px;">
            <div style="display:flex; align-items:center; gap: 15px;">
                <button class="menu-toggle" onclick="toggleSidebar()" style="color: white;">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="navbar-title" style="color:white;">Official Tabulation</div>
            </div>
            <div style="font-size:12px; color:#34d399; font-weight:bold;">
                <i class="fas fa-circle"></i> SYSTEM READY
            </div>
        </div>

        <div class="container" style="padding:20px;">
            
            <?php if (empty($rounds)): ?>
                <div style="text-align:center; padding:40px;"><h3>No Rounds Found</h3></div>
            <?php else: ?>

            <div class="tab-nav">
                <?php foreach ($rounds as $r): ?>
                    <a href="?round_id=<?= $r['id'] ?>" class="tab-item <?= $r['id'] == $current_round_id ? 'active' : '' ?>">
                        <?= htmlspecialchars($r['title']) ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div style="font-size:11px; color:#64748b; font-weight:bold; text-transform:uppercase;">Judge Progress</div>
                    <div class="judge-status-list" id="judgeStatusList">
                        <span style="color:#999; font-size:12px;">Click Refresh to load...</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div style="font-size:11px; color:#64748b; font-weight:bold; text-transform:uppercase;">Round Status</div>
                    <div id="roundStatusText" style="font-weight:800; font-size:1.2rem; margin-top:5px;">--</div>
                </div>
            </div>

            <div class="control-toolbar">
                <div class="view-switcher">
                    <button class="view-btn active" id="btnSum" onclick="switchView('summary')">Leaderboard</button>
                    <button class="view-btn" id="btnAud" onclick="switchView('audit')">Audit Matrix</button>
                    <button class="view-btn" id="btnAwd" onclick="switchView('awards')">Special Awards</button>
                </div>
                
                <div class="action-group">
                    <button class="action-btn" id="btnPrint" onclick="window.print()">
                        <i class="fas fa-print"></i> Print
                    </button>
                    <button class="action-btn" onclick="fetchTally()">
                        <i class="fas fa-sync-alt"></i> Refresh Data
                    </button>
                    <button id="btnLock" class="lock-btn" onclick="lockRound()" disabled>
                        Waiting...
                    </button>
                </div>
            </div>

            <div class="tally-card" id="summaryView">
                <div class="table-responsive">
                    <table class="tally-table">
                        <thead><tr id="tableHeader"></tr></thead>
                        <tbody id="tableBody"><tr><td colspan="10" style="padding:40px;">Click 'Refresh Data' to load scores.</td></tr></tbody>
                    </table>
                </div>
            </div>

            <div class="tally-card hidden" id="auditView" style="padding:20px;">
                <div id="auditTablesContainer"></div>
            </div>

            <div class="hidden" id="awardsView">
                <div class="awards-grid" id="awardsGrid"></div>
            </div>

            <?php endif; ?>

        </div>
    </div>
</div>

<script>
    function toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        if (sidebar.style.left === '0px') {
            sidebar.style.left = '-280px'; 
            overlay.style.display = 'none';
        } else {
            sidebar.style.left = '0px'; 
            overlay.style.display = 'block';
        }
    }

    const ROUND_ID = <?= $current_round_id ?>;
    const EVENT_ID = <?= $event_id ?>;
    const TALLY_API = "../api/tally.php";
    const ROUNDS_API = "../api/rounds.php"; 
    const AWARDS_API = "../api/awards_tally.php";
    
    let currentData = null;
    let allContestants = []; 

    function switchView(view) {
        document.getElementById('summaryView').classList.toggle('hidden', view !== 'summary');
        document.getElementById('auditView').classList.toggle('hidden', view !== 'audit');
        document.getElementById('awardsView').classList.toggle('hidden', view !== 'awards');
        
        document.getElementById('btnSum').classList.toggle('active', view === 'summary');
        document.getElementById('btnAud').classList.toggle('active', view === 'audit');
        document.getElementById('btnAwd').classList.toggle('active', view === 'awards');

        if(view === 'awards') fetchAwards();
    }

    async function fetchTally() {
        if (!ROUND_ID) return;
        
        const btnIcon = document.querySelector('.fa-sync-alt');
        if(btnIcon) btnIcon.classList.add('fa-spin');

        try {
            const res = await fetch(`${TALLY_API}?round_id=${ROUND_ID}&audit=true`);
            currentData = await res.json();
            
            if (currentData.status === 'success') {
                renderJudgeStatus(currentData.judges, currentData.submitted_judges || []);
                renderSummaryTable();
                renderAuditTable(); 
                
                document.getElementById('roundStatusText').innerText = currentData.round_status;
                updateLockButton(currentData);
            }
        } catch (e) { 
            console.error(e); 
            alert("Connection Error");
        } finally { 
            if(btnIcon) btnIcon.classList.remove('fa-spin'); 
        }
    }

    function updateLockButton(data) {
        const btn = document.getElementById('btnLock');
        if (data.round_status === 'Completed') {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-check-circle"></i> LOCKED';
            btn.style.background = '#4b5563'; 
        } else {
            const totalJ = data.judges.length;
            const subJ = (data.submitted_judges || []).length;
            
            if(totalJ > 0 && subJ === totalJ) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-lock"></i> LOCK ROUND';
                btn.style.background = '#059669'; 
            } else {
                btn.disabled = true;
                btn.innerHTML = `Waiting (${subJ}/${totalJ})`;
                btn.style.background = '#6b7280';
            }
        }
    }

    async function fetchAwards() {
        try {
            const res = await fetch(`${AWARDS_API}?event_id=${EVENT_ID}`);
            const data = await res.json();
            if(data.status === 'success') {
                allContestants = data.contestants;
                renderAwards(data.awards);
            }
        } catch(e) { console.error(e); }
    }

    function renderAwards(awards) {
        const grid = document.getElementById('awardsGrid');
        if (awards.length === 0) {
            grid.innerHTML = '<div style="grid-column:1/-1; padding:20px; text-align:center;">No awards found.</div>';
            return;
        }

        grid.innerHTML = awards.map(item => {
            const aw = item.award;
            const win = item.winner;
            let winnerHTML = '';

            if (aw.selection_method === 'Manual') {
                let options = `<option value="">-- Select Winner --</option>`;
                allContestants.forEach(c => {
                    const sel = (win && c.id == win.id) ? 'selected' : '';
                    options += `<option value="${c.id}" ${sel}>${c.name}</option>`;
                });
                winnerHTML = `<select class="manual-select" onchange="saveManualWinner(${aw.id}, this.value)">${options}</select>`;
            } else {
                if (win) {
                    winnerHTML = `<div style="color:green; font-weight:bold; text-align:center;"><i class="fas fa-trophy"></i> ${win.name}</div>`;
                } else {
                    winnerHTML = `<div style="color:#d97706; font-style:italic; text-align:center;">Calculating...</div>`;
                }
            }

            return `
            <div class="award-tile">
                <h4>${aw.title}</h4>
                <div style="font-size:11px; color:#666; margin-bottom:10px;">${aw.description || ''}</div>
                ${winnerHTML}
            </div>`;
        }).join('');
    }

    async function saveManualWinner(awardId, contestantId) {
        if(!contestantId) return;
        try {
            await fetch(AWARDS_API, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ award_id: awardId, contestant_id: contestantId, event_id: EVENT_ID })
            });
            fetchAwards(); 
        } catch(e) { alert("Save failed"); }
    }

    function renderJudgeStatus(allJudges, submittedIds) {
        const list = document.getElementById('judgeStatusList');
        list.innerHTML = allJudges.map(j => {
            const isDone = submittedIds.includes(parseInt(j.id));
            return `<span class="j-badge ${isDone ? 'submitted' : ''}">${isDone ? '<i class="fas fa-check"></i>' : '<i class="fas fa-hourglass-start"></i>'} ${j.name}</span>`;
        }).join('');
    }

    function renderSummaryTable() {
        if(!currentData) return;
        const { judges, ranking, qualifiers, round_status } = currentData;
        const thead = document.getElementById('tableHeader');
        const tbody = document.getElementById('tableBody');

        thead.innerHTML = `<th>Rank</th><th style="text-align:left;">Contestant</th>${judges.map(j => `<th>${j.name}</th>`).join('')}<th>Final</th><th>Status</th>`;

        if (ranking.length === 0) {
            tbody.innerHTML = `<tr><td colspan="${judges.length + 4}" style="padding:40px; color:#999;">No scores recorded yet.</td></tr>`;
            return;
        }

        tbody.innerHTML = ranking.map(row => {
            const c = row.contestant;
            const rank = row.rank;
            const isCompleted = (round_status === 'Completed');
            const isQualified = (rank <= qualifiers);
            
            let rowClass = "";
            let badgeHTML = `<span class="status-badge badge-pending">PENDING</span>`;

            if (isCompleted) {
                if (isQualified) {
                    rowClass = "row-qualified";
                    badgeHTML = `<span class="status-badge badge-q">QUALIFIED</span>`;
                } else {
                    rowClass = "row-eliminated";
                    badgeHTML = `<span class="status-badge badge-e">ELIMINATED</span>`;
                }
            }

            let rankHTML = `<div class="rank-box">${rank}</div>`;

            return `
            <tr class="${rowClass}">
                <td>${rankHTML}</td>
                <td class="contestant-cell">
                    <img src="assets/uploads/contestants/${c.photo}" class="c-img" onerror="this.src='assets/images/default_user.png'">
                    <div><b>${c.name}</b></div>
                </td>
                ${judges.map(j => `<td style="font-size:13px;">${row.judge_scores[j.id] !== undefined ? parseFloat(row.judge_scores[j.id]).toFixed(2) : '--'}</td>`).join('')}
                <td class="final-score">${parseFloat(row.final_score).toFixed(2)}</td>
                <td>${badgeHTML}</td>
            </tr>`;
        }).join('');
    }

    function renderAuditTable() {
        if(!currentData.audit) return;
        const { audit, judges, ranking } = currentData;
        const container = document.getElementById('auditTablesContainer');
        
        let html = '';

        audit.segments.forEach(seg => {
            const segCriteria = audit.criteria.filter(cr => cr.segment_id == seg.id);
            
            html += `<div class="segment-block">`;
            html += `<h3 class="seg-title">${seg.title} <span class="seg-weight">(${seg.weight_percent}%)</span></h3>`;
            html += `<div class="table-responsive"><table class="tally-table">`;
            
            html += `<thead><tr>`;
            html += `<th rowspan="2" class="audit-sticky-col" style="background:#f9fafb;">Contestant</th>`;
            judges.forEach(j => {
                html += `<th colspan="${segCriteria.length}" class="judge-header">${j.name}</th>`;
            });
            html += `</tr><tr>`;
            judges.forEach(j => {
                segCriteria.forEach(crit => {
                    html += `<th class="crit-header">${crit.title}</th>`;
                });
            });
            html += `</tr></thead>`;
            
            html += `<tbody>`;
            ranking.forEach(row => {
                const c = row.contestant;
                const cid = c.detail_id; 
                
                html += `<tr>`;
                html += `<td class="audit-sticky-col"><b>${c.name}</b></td>`;
                
                judges.forEach(j => {
                    segCriteria.forEach((crit, idx) => {
                        let score = '-';
                        if(audit.scores[cid] && audit.scores[cid][j.id] && audit.scores[cid][j.id][crit.id]) {
                            score = audit.scores[cid][j.id][crit.id];
                        }
                        const borderStyle = (idx === 0) ? 'border-left: 2px solid #ccc;' : '';
                        html += `<td style="${borderStyle}">${score}</td>`;
                    });
                });
                html += `</tr>`;
            });
            html += `</tbody></table></div></div><br>`;
        });

        container.innerHTML = html;
    }

    async function lockRound() {
        if (!confirm("Are you sure you want to LOCK this round? This cannot be undone.")) return;
        const btn = document.getElementById('btnLock');
        btn.disabled = true;

        try {
            const formData = new FormData();
            formData.append('action', 'lock');
            formData.append('round_id', ROUND_ID);

            const res = await fetch(ROUNDS_API, { method: 'POST', body: formData });
            const result = await res.json();
            
            if (result.status === 'success') { 
                alert("Round Locked Successfully!"); 
                fetchTally(); 
            } else { 
                alert("Error: " + result.message); 
                btn.disabled = false;
            }
        } catch (e) { alert("Network Error"); btn.disabled = false; }
    }

    // Initial Load
    fetchTally();
</script>

</body>
</html>