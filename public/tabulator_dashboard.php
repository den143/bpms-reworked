<?php
require_once __DIR__ . '/../app/core/guard.php';
requireLogin();
requireRole('Tabulator'); // STRICT: Only Tabulators can access this
require_once __DIR__ . '/../app/config/database.php';

$user_id = $_SESSION['user_id'];

// 1. GET ACTIVE EVENT
$evt_sql = "SELECT id, title FROM events WHERE status = 'Active' LIMIT 1";
$event = $conn->query($evt_sql)->fetch_assoc();

if (!$event) {
    die("<div style='text-align:center; padding:50px; font-family:sans-serif; color:#6b7280; background:#f3f4f6; height:100vh; display:flex; flex-direction:column; justify-content:center; align-items:center;'>
            <h1 style='font-size:2rem; margin-bottom:10px;'><i class='fas fa-calendar-times'></i> No Active Event</h1>
            <p style='margin-bottom:20px;'>Please wait for the Event Manager to activate the event.</p>
            <a href='logout.php' style='background:#111827; color:white; padding:10px 20px; text-decoration:none; border-radius:6px;'>Logout</a>
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Tabulation - <?= htmlspecialchars($event['title']) ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=15">
    <link rel="stylesheet" href="./assets/fontawesome/css/all.min.css">
    <style>
        :root { --gold: #F59E0B; --dark: #111827; --success: #059669; --danger: #ef4444; }
        
        body { background-color: #f3f4f6; margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        
        /* --- NAVBAR (Mobile Optimized) --- */
        .navbar {
            background: var(--dark);
            color: white;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between; /* Space between Logout and Title */
            padding: 0 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .btn-logout {
            background: rgba(239, 68, 68, 0.15);
            color: #fca5a5;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            padding: 8px 12px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(239, 68, 68, 0.3);
            transition: all 0.2s;
        }
        .btn-logout:hover { background: rgba(239, 68, 68, 0.3); color: white; }

        .navbar-title { font-size: 16px; font-weight: 700; text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        
        /* --- LAYOUT --- */
        .container { max-width: 1400px; margin: 0 auto; padding: 15px; }

        /* Round Tabs */
        .tab-nav { display: flex; gap: 8px; margin-bottom: 15px; overflow-x: auto; padding-bottom: 5px; -webkit-overflow-scrolling: touch; scrollbar-width: none; }
        .tab-nav::-webkit-scrollbar { display: none; }
        .tab-item { 
            padding: 10px 16px; background: white; border: 1px solid #d1d5db; 
            border-radius: 20px; text-decoration: none; color: #4b5563; 
            font-weight: 600; font-size: 13px; white-space: nowrap; transition: 0.2s; 
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .tab-item.active { background: var(--dark); color: white; border-color: var(--dark); }

        /* Stats Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-bottom: 15px; }
        .stat-card { background: white; padding: 15px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border-left: 4px solid var(--gold); }
        .stat-label { font-size: 11px; color: #6b7280; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px; }
        
        .judge-status-list { display: flex; flex-wrap: wrap; gap: 6px; }
        .j-badge { font-size: 11px; padding: 4px 8px; border-radius: 4px; background: #f3f4f6; color: #6b7280; font-weight: 600; border: 1px solid #e5e7eb; display: flex; align-items: center; gap: 4px; }
        .j-badge.submitted { background: #d1fae5; color: #065f46; border-color: #34d399; }

        /* --- CONTROL TOOLBAR (Responsive) --- */
        .control-toolbar { 
            background: white; padding: 10px; border-radius: 8px; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 15px; 
            display: flex; flex-wrap: wrap; gap: 10px; justify-content: space-between; 
        }
        
        .view-switcher { display: flex; background: #f3f4f6; padding: 3px; border-radius: 6px; gap: 2px; }
        .view-btn { flex: 1; padding: 8px 12px; border: none; background: none; cursor: pointer; font-size: 13px; font-weight: 600; color: #6b7280; border-radius: 4px; transition: 0.2s; }
        .view-btn.active { background: white; color: var(--dark); box-shadow: 0 1px 2px rgba(0,0,0,0.1); }

        .action-group { display: flex; gap: 8px; }
        .action-btn { 
            background: white; border: 1px solid #d1d5db; padding: 8px 14px; 
            border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 6px; 
            font-weight: 600; color: #374151; font-size: 13px; text-decoration: none;
        }
        .action-btn:hover { background: #f9fafb; }
        
        .lock-btn { 
            background: var(--dark); color: white; border: none; padding: 8px 16px; 
            border-radius: 6px; font-weight: 700; cursor: pointer; 
            display: flex; align-items: center; gap: 6px; font-size: 13px; 
        }
        .lock-btn:disabled { background: #6b7280; cursor: not-allowed; opacity: 0.8; }

        /* --- TABLES (Mobile Scrollable) --- */
        .tally-card { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; width: 100%; }
        .tally-table { width: 100%; border-collapse: collapse; min-width: 600px; /* Force scroll on small screens */ }
        
        .tally-table th { background: #f8fafc; padding: 12px 10px; text-align: center; font-size: 11px; color: #475569; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; font-weight: 700; white-space: nowrap; }
        .tally-table td { padding: 10px; text-align: center; border-bottom: 1px solid #f1f5f9; font-size: 13px; color: #1e293b; }
        .tally-table tr:last-child td { border-bottom: none; }

        /* Rank Box */
        .rank-box { width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; border-radius: 50%; margin: 0 auto; font-weight: 800; font-size: 13px; background: #f3f4f6; color: #6b7280; }
        .row-qualified { background-color: #f0fdf4; }
        .row-qualified .rank-box { background: var(--gold); color: white; box-shadow: 0 2px 4px rgba(245, 158, 11, 0.4); }
        .row-eliminated { background-color: #fff; opacity: 0.7; }

        .c-img { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 2px solid #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-right: 8px; vertical-align: middle; }
        .contestant-cell { text-align: left !important; display: flex; align-items: center; }

        /* Badges */
        .status-badge { font-size: 10px; padding: 3px 8px; border-radius: 10px; font-weight: 700; text-transform: uppercase; display: inline-block; }
        .badge-q { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .badge-e { background: #f3f4f6; color: #6b7280; border: 1px solid #e5e7eb; }
        .badge-pending { background: #fff7ed; color: #c2410c; border: 1px solid #ffedd5; }

        /* Sticky Columns for Audit */
        .audit-sticky-col { position: sticky; left: 0; background: #fff; z-index: 2; border-right: 2px solid #e5e7eb !important; }
        .tally-table th.audit-sticky-col { background: #f8fafc; }

        .hidden { display: none !important; }

        /* --- MOBILE MEDIA QUERY --- */
        @media (max-width: 768px) {
            .navbar { padding: 0 10px; }
            .navbar-title { font-size: 14px; max-width: 150px; }
            .btn-logout span { display: none; } /* Hide text, show icon only */
            
            .control-toolbar { flex-direction: column; gap: 10px; }
            .view-switcher, .action-group { width: 100%; }
            .action-group { justify-content: space-between; }
            .action-btn, .lock-btn { flex: 1; justify-content: center; }
            
            /* Tweak Audit Table for Mobile */
            .tally-table th, .tally-table td { padding: 8px 5px; font-size: 12px; }
            .c-img { width: 28px; height: 28px; margin-right: 5px; }
        }

        /* --- PRINT --- */
        @media print {
            .navbar, .control-toolbar, .tab-nav, .stats-grid, .btn-logout { display: none !important; }
            body { background: white; }
            .container { padding: 0; max-width: 100%; }
            .tally-card { border: 1px solid #000; box-shadow: none; }
            .tally-table th, .tally-table td { border: 1px solid #000 !important; color: black !important; }
            .row-qualified { background-color: #eee !important; }
            .row-qualified .rank-box { color: black !important; border: 1px solid black; }
            
            /* Header for Print */
            .tally-card::before {
                content: "OFFICIAL RESULT SHEET: <?= strtoupper(htmlspecialchars($event['title'])) ?>";
                display: block; text-align: center; font-weight: bold; font-size: 14pt; padding: 20px; border-bottom: 2px solid black;
            }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="logout.php" class="btn-logout" onclick="return confirm('Exit Tabulation?')">
        <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
    </a>
    <div class="navbar-title">Tabulation Dashboard</div>
    <div style="width: 70px;"></div> </nav>

<div class="container">
    
    <?php if (empty($rounds)): ?>
        <div style="text-align:center; padding:40px; color:#666;"><h3>No Rounds Active</h3></div>
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
            <div class="stat-label">Judge Progress</div>
            <div class="judge-status-list" id="judgeStatusList">
                <span style="color:#999; font-size:11px;">Loading...</span>
            </div>
        </div>
        <div class="stat-card" style="border-left-color: var(--success);">
            <div class="stat-label">Round Status</div>
            <div id="roundStatusText" style="font-weight:800; font-size:1.1rem;">--</div>
        </div>
    </div>

    <div class="control-toolbar">
        <div class="view-switcher">
            <button class="view-btn active" id="btnSum" onclick="switchView('summary')">Leaderboard</button>
            <button class="view-btn" id="btnAud" onclick="switchView('audit')">Audit</button>
            <button class="view-btn" id="btnAwd" onclick="switchView('awards')">Awards</button>
        </div>
        
        <div class="action-group">
            <button class="action-btn" onclick="fetchTally()">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
            <button class="action-btn" onclick="window.print()">
                <i class="fas fa-print"></i> Print
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
                <tbody id="tableBody"><tr><td colspan="10" style="padding:40px;">Loading Data...</td></tr></tbody>
            </table>
        </div>
    </div>

    <div class="tally-card hidden" id="auditView" style="padding:15px;">
        <div id="auditTablesContainer"></div>
    </div>

    <div class="hidden" id="awardsView">
        <div class="awards-grid" id="awardsGrid"></div>
    </div>

    <?php endif; ?>

</div>

<script>
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
        } catch (e) { console.error(e); } 
        finally { if(btnIcon) btnIcon.classList.remove('fa-spin'); }
    }

    function updateLockButton(data) {
        const btn = document.getElementById('btnLock');
        if(!btn) return; 

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
                btn.innerHTML = `Wait (${subJ}/${totalJ})`;
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
                let options = `<option value="">-- Select --</option>`;
                allContestants.forEach(c => {
                    const sel = (win && c.id == win.id) ? 'selected' : '';
                    options += `<option value="${c.id}" ${sel}>${c.name}</option>`;
                });
                winnerHTML = `<select style="width:100%; padding:8px; border-radius:6px; border:1px solid #ddd;" onchange="saveManualWinner(${aw.id}, this.value)">${options}</select>`;
            } else {
                if (win) {
                    winnerHTML = `<div style="color:green; font-weight:bold; text-align:center;"><i class="fas fa-trophy"></i> ${win.name}</div>`;
                } else {
                    winnerHTML = `<div style="color:#d97706; font-style:italic; text-align:center;">Calculating...</div>`;
                }
            }

            return `
            <div style="background:white; padding:15px; border-radius:8px; border:1px solid #eee;">
                <h4 style="margin:0 0 5px 0;">${aw.title}</h4>
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
            const icon = isDone ? '<i class="fas fa-check"></i>' : '<i class="fas fa-clock"></i>';
            const cls = isDone ? 'submitted' : '';
            return `<span class="j-badge ${cls}">${icon} ${j.name}</span>`;
        }).join('');
    }

    function renderSummaryTable() {
        if(!currentData) return;
        const { judges, ranking, qualifiers, round_status } = currentData;
        const thead = document.getElementById('tableHeader');
        const tbody = document.getElementById('tableBody');

        thead.innerHTML = `<th>#</th><th style="text-align:left;">Contestant</th>${judges.map(j => `<th>${j.name}</th>`).join('')}<th>Final</th><th>Status</th>`;

        if (ranking.length === 0) {
            tbody.innerHTML = `<tr><td colspan="${judges.length + 4}" style="padding:30px; color:#999;">Waiting for scores...</td></tr>`;
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

            return `
            <tr class="${rowClass}">
                <td><div class="rank-box">${rank}</div></td>
                <td class="contestant-cell">
                    <img src="assets/uploads/contestants/${c.photo}" class="c-img" onerror="this.src='assets/images/default_user.png'">
                    <div><b>${c.name}</b></div>
                </td>
                ${judges.map(j => `<td style="font-size:13px;">${row.judge_scores[j.id] !== undefined ? parseFloat(row.judge_scores[j.id]).toFixed(2) : '--'}</td>`).join('')}
                <td style="font-weight:bold;">${parseFloat(row.final_score).toFixed(2)}</td>
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
            html += `<th rowspan="2" class="audit-sticky-col">Contestant</th>`;
            judges.forEach(j => {
                html += `<th colspan="${segCriteria.length}" style="background:#f1f5f9; border-left:1px solid #ccc;">${j.name}</th>`;
            });
            html += `</tr><tr>`;
            judges.forEach(j => {
                segCriteria.forEach(crit => {
                    html += `<th style="font-size:10px; color:#64748b;">${crit.title}</th>`;
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
                        const borderStyle = (idx === 0) ? 'border-left: 1px solid #ccc;' : '';
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