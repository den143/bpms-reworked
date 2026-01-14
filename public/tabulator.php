<?php
require_once __DIR__ . '/../app/core/guard.php';
requireLogin();
requireRole(['Event Manager', 'Tabulator']);
require_once __DIR__ . '/../app/config/database.php';

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// 2. GET ACTIVE EVENT
$evt_sql = "SELECT id, title FROM events WHERE status = 'Active' LIMIT 1";
$event = $conn->query($evt_sql)->fetch_assoc();

if (!$event) {
    die("<div style='text-align:center; padding:50px; font-family:sans-serif; color:#6b7280;'>
            <h1><i class='fas fa-calendar-times'></i> No Active Event</h1>
            <p>The Event Manager must set an event to 'Active' status first.</p>
            <a href='logout.php'>Logout</a>
         </div>");
}
$event_id = $event['id'];

// 3. GET ROUNDS
$rnd_sql = "SELECT id, title, status FROM rounds WHERE event_id = $event_id AND is_deleted = 0 ORDER BY ordering";
$rounds = $conn->query($rnd_sql)->fetch_all(MYSQLI_ASSOC);

// Default to the first round if none selected
$current_round_id = isset($_GET['round_id']) ? (int)$_GET['round_id'] : ($rounds[0]['id'] ?? 0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tabulation - <?= htmlspecialchars($event['title']) ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=10">
    <link rel="stylesheet" href="./assets/fontawesome/css/all.min.css">
    <style>
        :root { --gold: #F59E0B; --dark: #111827; --success: #059669; }
        
        /* --- LAYOUT UTILS --- */
        .tab-nav { display: flex; gap: 10px; margin-bottom: 20px; overflow-x: auto; padding-bottom: 5px; }
        .tab-item { padding: 10px 20px; background: #fff; border: 1px solid #ddd; border-radius: 8px; text-decoration: none; color: #374151; font-weight: 600; white-space: nowrap; transition: 0.2s; }
        .tab-item:hover { background: #f9fafb; border-color: #ccc; }
        .tab-item.active { background: var(--dark); color: white; border-color: var(--dark); }

        /* --- STATS GRID --- */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 15px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border-top: 4px solid var(--gold); }
        
        .judge-status-list { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
        .j-badge { font-size: 11px; padding: 4px 10px; border-radius: 15px; background: #f3f4f6; color: #6b7280; font-weight: bold; border: 1px solid #e5e7eb; display: flex; align-items: center; gap: 5px; }
        .j-badge.submitted { background: #d1fae5; color: #065f46; border-color: #34d399; }

        /* --- CONTROL TOOLBAR --- */
        .control-toolbar { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 15px; margin-bottom: 15px; background: #fff; padding: 10px 15px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border: 1px solid #e5e7eb; }
        
        .view-switcher { display: flex; background: #f3f4f6; padding: 4px; border-radius: 8px; gap: 2px; }
        .view-btn { padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; font-weight: bold; font-size: 13px; transition: 0.2s; color: #6b7280; background: transparent; }
        .view-btn:hover { color: var(--dark); }
        .view-btn.active { background: white; color: var(--dark); box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        
        .action-group { display: flex; gap: 10px; }
        .action-btn { background: white; border: 1px solid #d1d5db; padding: 8px 16px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 8px; font-weight: 600; color: #374151; transition: 0.2s; font-size: 13px; }
        .action-btn:hover { background: #f9fafb; border-color: #9ca3af; }
        
        .lock-btn { background: var(--dark); color: white; border: none; padding: 8px 20px; border-radius: 6px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.2s; font-size: 13px; }
        .lock-btn:hover { background: #1f2937; }
        .lock-btn:disabled { background: #4b5563; cursor: not-allowed; opacity: 0.7; }

        /* --- TABLES --- */
        .tally-card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .table-responsive { overflow-x: auto; width: 100%; }
        .tally-table { width: 100%; border-collapse: collapse; min-width: 800px; }
        .tally-table th { background: #f8fafc; padding: 12px; text-align: center; font-size: 11px; color: #64748b; text-transform: uppercase; border: 1px solid #e2e8f0; }
        .tally-table td { padding: 10px; text-align: center; border: 1px solid #e2e8f0; font-size: 13px; }

        /* Audit View Colors */
        .audit-header-main { background: var(--dark) !important; color: white !important; font-size: 13px !important; }
        .audit-header-sub { background: #f1f5f9 !important; font-weight: bold; }
        .audit-na { color: #dc2626; font-weight: bold; font-size: 11px; }
        .audit-weighted { background: #fffbeb; font-weight: bold; color: #b45309; }

        .rank-box { width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; border-radius: 50%; margin: 0 auto; font-weight: 800; font-size: 14px; }
        .rank-1 .rank-box { background: var(--gold); color: white; box-shadow: 0 2px 4px rgba(245, 158, 11, 0.4); }
        .rank-2 .rank-box { background: #94a3b8; color: white; }
        .rank-3 .rank-box { background: #b45309; color: white; }

        .contestant-cell { display: flex; align-items: center; gap: 10px; text-align: left; padding-left: 15px !important; }
        .c-img { width: 35px; height: 35px; border-radius: 50%; object-fit: cover; border: 2px solid #f3f4f6; }
        .final-score { font-weight: 900; color: var(--dark); font-size: 16px; }
        
        .hidden { display: none !important; }
        
        /* Helper for Audit Print Container - ALWAYS Hidden on Screen */
        #auditPrintContainer { display: none; }

        /* --- NEW STYLES FOR AWARDS --- */
        .awards-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; padding: 20px; }
        .award-tile { background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; position: relative; transition: transform 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .award-tile:hover { transform: translateY(-2px); box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .award-tile h4 { margin: 0 0 5px 0; color: var(--dark); display: flex; justify-content: space-between; align-items: center; font-size: 15px; font-weight: 700; }
        .award-badge { font-size: 10px; padding: 3px 8px; border-radius: 10px; background: #e0f2fe; color: #0369a1; text-transform: uppercase; letter-spacing: 0.5px; }
        .award-winner-box { margin-top: 15px; padding: 15px; background: #f9fafb; border-radius: 8px; text-align: center; font-weight: bold; border: 1px dashed #d1d5db; min-height: 80px; display: flex; align-items: center; justify-content: center; flex-direction: column; }
        .winner-auto { color: var(--success); display: flex; flex-direction: column; align-items: center; gap: 5px; width: 100%; }
        .winner-pending { color: #d97706; font-style: italic; font-size: 12px; }
        .manual-select { width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; margin-top: 5px; font-size: 13px; cursor: pointer; }

        /* --- TABULATOR SPECIFIC SIDEBAR --- */
        .sidebar-tabulator { background-color: #111827; }

        /* --- STATUS VISUALS --- */
        .row-qualified { background-color: #ecfdf5 !important; border-left: 5px solid #059669; }
        .row-eliminated { background-color: #fff !important; color: #6b7280; }
        
        .status-badge { 
            font-size: 10px; padding: 3px 8px; border-radius: 12px; 
            font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;
        }
        .badge-q { background: #059669; color: white; }
        .badge-e { background: #e5e7eb; color: #6b7280; border: 1px solid #d1d5db; }
        .badge-pending { background: #f3f4f6; color: #9ca3af; border: 1px solid #e5e7eb; } /* Neutral Badge */

        /* ======================== */
        /* PRINT STYLES (FOOLPROOF) */
        /* ======================== */
        @media print {
            @page { size: landscape; margin: 0.5cm; }
            body { font-family: 'Times New Roman', serif; background: white; color: black; }

            /* 1. HIDE ALL UI CHROME */
            .sidebar, .navbar, .tab-nav, .stats-grid, .control-toolbar, .action-btn, #sidebarOverlay, .menu-toggle { display: none !important; }
            
            /* 2. RESET CONTAINERS */
            .main-wrapper, .content-area, .container { 
                margin: 0 !important; padding: 0 !important; width: 100% !important; max-width: 100% !important; box-shadow: none !important; display: block !important;
            }
            .tally-card { border: none !important; box-shadow: none !important; overflow: visible !important; }
            .table-responsive { overflow: visible !important; }

            /* 3. TABLE STYLING FOR PRINT */
            .tally-table { width: 100% !important; font-size: 10pt !important; table-layout: fixed; border-collapse: collapse; }
            .tally-table th, .tally-table td { border: 1px solid #000 !important; color: #000 !important; padding: 4px !important; }
            .rank-box { border: 1px solid #000; color: #000 !important; background: none !important; box-shadow: none !important; }

            /* 4. VIEW LOGIC - KEY FIX */
            #summaryView:not(.hidden) { display: block !important; }
            #auditView:not(.hidden) { display: block !important; }
            #auditView:not(.hidden) #auditTableScreen { display: none !important; }
            #auditView:not(.hidden) #auditPrintContainer { display: block !important; }

            #awardsView:not(.hidden) { display: block !important; }
            #awardsView:not(.hidden) .awards-grid { display: block !important; padding: 0 !important; }
            #awardsView:not(.hidden) .award-tile { page-break-inside: avoid; border: 1px solid #000; margin-bottom: 10px; box-shadow: none !important; }
            #awardsView:not(.hidden) .manual-select { display: none !important; } 
            
            /* 5. HEADER & FOOTER */
            .tally-card::before, #awardsView::before {
                content: "OFFICIAL RESULT SHEET: <?= strtoupper(htmlspecialchars($event['title'])) ?>";
                display: block; text-align: center; font-weight: bold; font-size: 16pt; margin-bottom: 5px; 
            }
            .tally-card::after, #awardsView::after {
                content: "\A\A__________________________\A Tabulator Signature \A\A __________________________\A Judge Coordinator Signature";
                white-space: pre; display: block; margin-top: 40px; font-weight: bold; page-break-inside: avoid; text-align: center;
            }

            /* 6. SEGMENT STYLING */
            .print-segment-block { margin-bottom: 25px; page-break-inside: avoid; }
            .print-segment-title { font-size: 12pt; font-weight: bold; margin-bottom: 5px; text-transform: uppercase; border-bottom: 1px solid #000; display: inline-block; }

            /* --- STATUS VISUALS --- */
            .row-qualified { background-color: #ecfdf5 !important; border-left: 5px solid #059669; }
            .row-eliminated { background-color: #fff !important; color: #000; }
        }
    </style>
</head>
<body>

<div id="sidebarOverlay" class="sidebar-overlay" onclick="toggleSidebar()"></div>

<div class="main-wrapper">
    
    <?php if ($user_role === 'Event Manager'): ?>
        <?php include_once __DIR__ . '/../app/views/partials/sidebar.php'; ?>
    <?php else: ?>
        <div class="sidebar sidebar-tabulator">
            <div class="sidebar-header">
                <img src="assets/images/BPMS_logo.png" alt="BPMS Logo" class="sidebar-logo">
                <div class="brand-text">
                    <div class="brand-name">BPMS</div>
                    <div class="brand-subtitle">Tabulation Panel</div>
                </div>
            </div>
            <ul class="sidebar-menu">
                <li><a href="tabulator.php" class="active"><i class="fas fa-calculator"></i> <span>Score Sheet</span></a></li>
            </ul>
            
            <div class="sidebar-footer">
                <a href="settings.php">
                    <i class="fas fa-cog"></i> <span>Settings</span>
                </a>
                <a href="logout.php" onclick="return confirm('Confirm Logout?');">
                    <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
                </a>
            </div>
        </div>
    <?php endif; ?>

    <div class="content-area">
        <div class="navbar" style="background:var(--dark); color:white; padding:15px 25px;">
            <div style="display:flex; align-items:center; gap: 15px;">
                <button class="menu-toggle" onclick="toggleSidebar()" style="color: white;">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="navbar-title" style="color:white;">Official Tabulation</div>
            </div>

            <div id="liveIndicator" style="font-size:12px; color:#34d399; font-weight:bold; display:flex; align-items:center; gap:5px;">
                <i class="fas fa-circle fa-beat"></i> LIVE SYSTEM
            </div>
        </div>

        <div class="container" style="padding:20px;">
            
            <?php if (empty($rounds)): ?>
                <div style="text-align:center; padding:40px; background:white; border-radius:8px;">
                    <h3>No Rounds Configured</h3>
                    <p>Please ask the Event Manager to set up rounds and criteria.</p>
                </div>
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
                        <span style="color:#999; font-size:12px;">Loading judges...</span>
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
                        <i class="fas fa-print"></i> Print Leaderboard
                    </button>
                    <button class="action-btn" onclick="fetchTally()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                    <button id="btnLock" class="lock-btn" onclick="lockRound()">
                        <i class="fas fa-lock"></i> LOCK ROUND
                    </button>
                </div>
            </div>

            <div class="tally-card" id="summaryView">
                <div class="table-responsive">
                    <table class="tally-table">
                        <thead><tr id="tableHeader"></tr></thead>
                        <tbody id="tableBody"><tr><td colspan="10" style="padding:40px;">Initializing Tally System...</td></tr></tbody>
                    </table>
                </div>
            </div>

            <div class="tally-card hidden" id="auditView">
                <div class="table-responsive">
                    <table class="tally-table" id="auditTableScreen"></table>
                </div>
                <div id="auditPrintContainer"></div>
            </div>

            <div class="hidden" id="awardsView">
                <div class="awards-grid" id="awardsGrid">
                </div>
            </div>

            <?php endif; ?>

        </div>
    </div>
</div>

<script>
    // MOBILE SIDEBAR TOGGLE
    function toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        if (sidebar.style.left === '0px') {
            sidebar.style.left = '-280px'; // Close
            overlay.style.display = 'none';
        } else {
            sidebar.style.left = '0px'; // Open
            overlay.style.display = 'block';
        }
    }

    const ROUND_ID = <?= $current_round_id ?>;
    const EVENT_ID = <?= $event_id ?>;
    
    const TALLY_API_URL = "../api/tally.php";
    const ACTION_API_URL = "../api/rounds.php"; 
    const AWARDS_API = "../api/awards_tally.php";
    
    let currentData = null;
    let allContestants = []; 

    // --- TAB SWITCHING ---
    function switchView(view) {
        document.getElementById('summaryView').classList.toggle('hidden', view !== 'summary');
        document.getElementById('auditView').classList.toggle('hidden', view !== 'audit');
        document.getElementById('awardsView').classList.toggle('hidden', view !== 'awards');
        
        document.getElementById('btnSum').classList.toggle('active', view === 'summary');
        document.getElementById('btnAud').classList.toggle('active', view === 'audit');
        document.getElementById('btnAwd').classList.toggle('active', view === 'awards');

        const printBtn = document.getElementById('btnPrint');
        if (view === 'summary') {
            printBtn.innerHTML = '<i class="fas fa-print"></i> Print Leaderboard';
        } else if (view === 'audit') {
            printBtn.innerHTML = '<i class="fas fa-print"></i> Print Audit Matrix';
        } else {
            printBtn.innerHTML = '<i class="fas fa-print"></i> Print Award Results';
        }

        if(view === 'awards') fetchAwards();
    }

    // --- MAIN TALLY FETCH ---
    async function fetchTally() {
        if (!ROUND_ID) return;
        const btnRefreshIcon = document.querySelector('.fa-sync-alt');
        if(btnRefreshIcon) btnRefreshIcon.classList.add('fa-spin');

        try {
            // FIX 1: Added "&audit=true" here. 
            // This tells the backend to calculate and return the matrix data.
            const res = await fetch(`${TALLY_API_URL}?round_id=${ROUND_ID}&audit=true`);
            
            currentData = await res.json();
            
            if (currentData.status === 'success') {
                renderJudgeStatus(currentData.judges, currentData.submitted_judges || []);
                renderSummaryTable();
                
                // This will now work because currentData.audit is no longer null
                renderAuditTable(); 
                
                document.getElementById('roundStatusText').innerText = currentData.round_status;
                
                const btn = document.getElementById('btnLock');
                if (currentData.round_status === 'Completed') {
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-check-circle"></i> LOCKED';
                    btn.style.background = '#4b5563'; 
                } else {
                    const totalJ = currentData.judges.length;
                    const subJ = (currentData.submitted_judges || []).length;
                    
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
        } catch (e) { console.error(e); }
        finally { if(btnRefreshIcon) btnRefreshIcon.classList.remove('fa-spin'); }
    }

    // --- AWARDS FETCH ---
    async function fetchAwards() {
        try {
            const res = await fetch(`${AWARDS_API}?event_id=${EVENT_ID}`);
            const data = await res.json();
            
            // FIX 2: Added better error handling to show why awards might be blank
            if(data.status === 'success') {
                allContestants = data.contestants;
                renderAwards(data.awards);
            } else {
                console.error("API Error:", data);
                document.getElementById('awardsGrid').innerHTML = '<div style="padding:20px; text-align:center; color:red;">Error loading awards.</div>';
            }
        } catch(e) { 
            console.error("Award Network Error", e); 
            document.getElementById('awardsGrid').innerHTML = '<div style="padding:20px; text-align:center; color:red;">Network Error. Check console.</div>';
        }
    }

    function renderAwards(awards) {
        const grid = document.getElementById('awardsGrid');
        if (awards.length === 0) {
            grid.innerHTML = '<div style="text-align:center; grid-column:1/-1; padding:20px; color:#666;">No awards configured for this event.</div>';
            return;
        }

        grid.innerHTML = awards.map(item => {
            const aw = item.award;
            const win = item.winner;
            let winnerHTML = '';

            // FIX: Changed 'source_type' to 'selection_method' to match Database
            if (aw.selection_method === 'Manual') {
                let options = `<option value="">-- Select Winner --</option>`;
                allContestants.forEach(c => {
                    // Check if this contestant is the current winner
                    const sel = (win && c.id == win.id) ? 'selected' : '';
                    options += `<option value="${c.id}" ${sel}>${c.name}</option>`;
                });
                
                winnerHTML = `
                    <select class="manual-select" onchange="saveManualWinner(${aw.id}, this.value)">${options}</select>
                    ${win ? `<div style="margin-top:5px; font-size:12px; color:green;">Selected: <b>${win.name}</b></div>` : ''}
                `;

            } else {
                // Logic for Calculated Awards (Highest Scores / Votes)
                if (win) {
                    winnerHTML = `<div class="winner-auto"><i class="fas fa-trophy" style="font-size:1.2em; color:gold;"></i> <span>${win.name}</span></div>`;
                    if(win.total_score) winnerHTML += `<div style="font-size:11px; margin-top:4px;">Score: ${parseFloat(win.total_score).toFixed(2)}</div>`;
                    if(win.votes) winnerHTML += `<div style="font-size:11px; margin-top:4px;">Votes: ${win.votes}</div>`;
                } else {
                    winnerHTML = `<div class="winner-pending">Waiting for results...</div>`;
                }
            }

            return `
            <div class="award-tile">
                <h4>${aw.title} <span class="award-badge">${aw.selection_method.replace('_', ' ')}</span></h4>
                <div style="font-size:11px; color:#666; margin-bottom:8px;">${aw.description || ''}</div>
                <div class="award-winner-box">${winnerHTML}</div>
            </div>`;
        }).join('');
    }

    async function saveManualWinner(awardId, contestantId) {
        if(!contestantId) return;
        try {
            const res = await fetch(AWARDS_API, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    award_id: awardId,
                    contestant_id: contestantId,
                    event_id: EVENT_ID
                })
            });
            const d = await res.json();
            if(d.status === 'success') {
                fetchAwards(); // Refresh
            } else {
                alert("Error saving winner");
            }
        } catch(e) { alert("Save failed"); }
    }

    // --- RENDER FUNCTIONS ---
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

        // 1. Build Header
        thead.innerHTML = `
            <th>Rank</th>
            <th style="text-align:left;">Contestant</th>
            ${judges.map(j => `<th>${j.name}</th>`).join('')}
            <th>Final</th>
            <th>Status</th> 
        `;

        if (ranking.length === 0) {
            tbody.innerHTML = `<tr><td colspan="${judges.length + 4}" style="padding:40px; color:#999;">No scores recorded yet.</td></tr>`;
            return;
        }

        // 2. Build Rows
        tbody.innerHTML = ranking.map((row, index) => {
            const c = row.contestant;
            const rank = row.rank;
            const isCompleted = (round_status === 'Completed');
            
            // Logic: Top N Qualify
            const isQualified = (rank <= qualifiers);
            
            // Visual Classes
            let rowClass = "";
            let badgeHTML = "";

            if (isCompleted) {
                // Round Locked: Show Winners vs Losers
                if (isQualified) {
                    rowClass = "row-qualified";
                    badgeHTML = `<span class="status-badge badge-q">QUALIFIED</span>`;
                } else {
                    rowClass = "row-eliminated";
                    badgeHTML = `<span class="status-badge badge-e">ELIMINATED</span>`;
                }
            } else {
                // Round Active: No predictions, just Pending
                rowClass = ""; 
                badgeHTML = `<span class="status-badge badge-pending">PENDING</span>`;
            }

            // Rank Box Logic (Always show rank, but style varies)
            let rankHTML = '';
            if (isQualified || !isCompleted) {
                // Show colorful circle for Winners or Everyone (if pending)
                rankHTML = `<div class="rank-box" style="${rowClass.includes('eliminated') ? 'background:#ccc;' : ''}">${rank}</div>`;
            } else {
                // Plain text for Eliminated people (only after lock)
                rankHTML = `<span style="font-weight:bold; font-size:14px; color:#666;">${rank}</span>`;
            }

            return `
            <tr class="${rowClass}">
                <td>${rankHTML}</td>
                
                <td class="contestant-cell">
                    <img src="assets/uploads/contestants/${c.photo}" class="c-img" onerror="this.src='assets/images/default_user.png'">
                    <div>
                        <span style="font-weight:bold; display:block;">${c.name}</span>
                    </div>
                </td>

                ${judges.map(j => `
                    <td style="font-size:13px; color:inherit;">
                        ${row.judge_scores[j.id] !== undefined ? parseFloat(row.judge_scores[j.id]).toFixed(2) : '--'}
                    </td>
                `).join('')}

                <td class="final-score" style="color:inherit;">
                    ${parseFloat(row.final_score).toFixed(2)}
                </td>

                <td>${badgeHTML}</td>
            </tr>`;
        }).join('');
    }

    function renderAuditTable() {
        if(!currentData.audit) return;
        const { audit, judges, ranking } = currentData;
        const tableScreen = document.getElementById('auditTableScreen');
        const containerPrint = document.getElementById('auditPrintContainer');
        
        // --- 1. SCREEN VERSION (Wide) ---
        let htmlScreen = `<thead><tr><th rowspan="2" class="audit-header-main">Contestant Details</th>`;
        audit.segments.forEach(seg => {
            const segCriteria = audit.criteria.filter(cr => cr.segment_id == seg.id);
            const colSpan = judges.length * (segCriteria.length + 1); 
            // FIX: Changed weight_percentage to weight_percent
            htmlScreen += `<th colspan="${colSpan}" class="audit-header-main">${seg.title} (${seg.weight_percent}%)</th>`;
        });
        htmlScreen += `</tr><tr>`;

        audit.segments.forEach(seg => {
            const segCriteria = audit.criteria.filter(cr => cr.segment_id == seg.id);
            judges.forEach(j => {
                htmlScreen += `<th colspan="${segCriteria.length + 1}" class="audit-header-sub" style="border-right:2px solid #ccc">${j.name}</th>`;
            });
        });

        htmlScreen += `</tr><tr><th class="audit-header-sub">Name</th>`;
        audit.segments.forEach(seg => {
            const segCriteria = audit.criteria.filter(cr => cr.segment_id == seg.id);
            judges.forEach(j => {
                 segCriteria.forEach(crit => { htmlScreen += `<th style="font-size:10px; color:#666;">${crit.title.substring(0,8)}..</th>`; });
                 htmlScreen += `<th class="audit-weighted" style="border-right:2px solid #ccc">Total</th>`;
            });
        });
        htmlScreen += `</tr></thead><tbody>`;

        ranking.forEach(row => {
            const c = row.contestant;
            htmlScreen += `<tr><td class="contestant-cell"><b>${c.name}</b></td>`;
            audit.segments.forEach(seg => {
                const segCriteria = audit.criteria.filter(cr => cr.segment_id == seg.id);
                // FIX: Changed weight_percentage to weight_percent
                const weight = parseFloat(seg.weight_percent) / 100;
                
                judges.forEach(j => {
                    let segRawSum = 0;
                    let hasAllScores = true;
                    segCriteria.forEach(crit => {
                        const cid = c.detail_id || c.user_id; 
                        const score = audit.scores[cid]?.[j.id]?.[crit.id];
                        
                        if (score !== undefined) {
                            htmlScreen += `<td>${score}</td>`;
                            segRawSum += parseFloat(score);
                        } else {
                            htmlScreen += `<td class="audit-na">N/A</td>`;
                            hasAllScores = false;
                        }
                    });
                    const weighted = (segRawSum * weight).toFixed(2);
                    htmlScreen += `<td class="audit-weighted" style="border-right:2px solid #ccc">${hasAllScores ? weighted : '<span class="audit-na">N/A</span>'}</td>`;
                });
            });
            htmlScreen += `</tr>`;
        });
        htmlScreen += `</tbody>`;
        tableScreen.innerHTML = htmlScreen;


        // --- 2. PRINT VERSION (Segment-by-Segment) ---
        let printHTML = "";
        
        audit.segments.forEach(seg => {
            const segCriteria = audit.criteria.filter(cr => cr.segment_id == seg.id);
            // FIX: Changed weight_percentage to weight_percent
            const weight = parseFloat(seg.weight_percent) / 100;

            printHTML += `<div class="print-segment-block">`;
            // FIX: Changed weight_percentage to weight_percent
            printHTML += `<div class="print-segment-title">AUDIT: ${seg.title} (${seg.weight_percent}%)</div>`;
            printHTML += `<table class="tally-table"><thead><tr><th rowspan="2">Contestant</th>`;
            
            judges.forEach(j => {
                printHTML += `<th colspan="${segCriteria.length + 1}" style="text-align:center;">${j.name}</th>`;
            });
            printHTML += `</tr><tr>`;
            
            judges.forEach(j => {
                 segCriteria.forEach(crit => { printHTML += `<th style="font-size:8pt;">${crit.title}</th>`; });
                 printHTML += `<th>Wtd.</th>`;
            });
            printHTML += `</tr></thead><tbody>`;

            ranking.forEach(row => {
                const c = row.contestant;
                printHTML += `<tr><td><b>${c.name}</b></td>`;

                judges.forEach(j => {
                    let segRawSum = 0;
                    let hasAllScores = true;
                    segCriteria.forEach(crit => {
                        const cid = c.detail_id || c.user_id;
                        const score = audit.scores[cid]?.[j.id]?.[crit.id];
                        if (score !== undefined) {
                            printHTML += `<td>${score}</td>`;
                            segRawSum += parseFloat(score);
                        } else {
                            printHTML += `<td>-</td>`;
                            hasAllScores = false;
                        }
                    });
                    const weighted = (segRawSum * weight).toFixed(2);
                    printHTML += `<td><b>${hasAllScores ? weighted : '-'}</b></td>`;
                });
                printHTML += `</tr>`;
            });

            printHTML += `</tbody></table></div>`;
        });
        
        containerPrint.innerHTML = printHTML;
    }
    // --- LOCK ROUND LOGIC ---
    async function lockRound() {
        if (!confirm("CONFIRM FINALIZATION?\n\nThis will lock the scores and promote winners to the next round.")) return;
        const btn = document.getElementById('btnLock');
        btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Processing...';
        btn.disabled = true;

        const formData = new FormData();
        formData.append('action', 'lock');
        formData.append('round_id', ROUND_ID);

        try {
            const res = await fetch(ACTION_API_URL, {
                method: 'POST',
                body: formData
            });
            const result = await res.json();
            
            if (result.status === 'success') { 
                alert("Success! Round Completed."); 
                fetchTally(); 
            } else { 
                alert("Error: " + result.message); 
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-lock"></i> LOCK ROUND';
            }
        } catch (e) { 
            alert("Network Error"); 
            btn.disabled = false;
        }
    }

    // Init
    setInterval(fetchTally, 5000);
    fetchTally();
</script>

</body>
</html>