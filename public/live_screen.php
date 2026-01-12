<?php
// bpms/public/live_screen.php
require_once __DIR__ . '/../app/core/guard.php';
requireLogin();

if (!in_array($_SESSION['role'], ['Event Manager', 'Tabulator', 'Judge Coordinator'])) {
    die("<h1>Access Denied</h1>");
}

require_once __DIR__ . '/../app/config/database.php';

$uid = $_SESSION['user_id'];
$event_name = "BPMS Live";
$event_id = 0;

// Fetch Event Context
if ($_SESSION['role'] === 'Event Manager') {
    // UPDATED: Column 'manager_id' and 'title'
    $stmt = $conn->prepare("SELECT id, title FROM events WHERE manager_id = ? AND status = 'Active' LIMIT 1");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $evt = $stmt->get_result()->fetch_assoc();
} else {
    // UPDATED: Table 'event_teams' and Column 'title'
    $stmt = $conn->prepare("
        SELECT e.id, e.title 
        FROM events e 
        JOIN event_teams et ON e.id = et.event_id 
        WHERE et.user_id = ? AND et.status = 'Active' AND e.status = 'Active'
        LIMIT 1
    ");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $evt = $stmt->get_result()->fetch_assoc();
}

if ($evt) {
    $event_id = $evt['id'];
    $event_name = $evt['title'];
} else {
    die("<div style='color:white;text-align:center;padding:50px;background:#111;font-family:sans-serif;'><h1>No Active Event Found</h1></div>");
}

// Fetch Rounds (Filtered by is_deleted=0)
$r_stmt = $conn->prepare("SELECT id, title, status FROM rounds WHERE event_id = ? AND is_deleted = 0 ORDER BY ordering");
$r_stmt->bind_param("i", $event_id);
$r_stmt->execute();
$rounds = $r_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LIVE: <?= htmlspecialchars($event_name) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    /* --- BASE CINEMATIC LAYOUT --- */
    body { margin: 0; background: #0f172a; color: white; font-family: 'Segoe UI', sans-serif; height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
    
    /* Header Animation */
    #header { height: 15vh; display: flex; flex-direction: column; justify-content: center; align-items: center; background: linear-gradient(to bottom, #1e293b, #0f172a); border-bottom: 4px solid #F59E0B; z-index: 10; box-shadow: 0 10px 30px rgba(0,0,0,0.5); transition: margin-top 0.5s; }
    h1 { font-size: 2vh; letter-spacing: 0.3em; color: #94a3b8; margin:0; text-transform: uppercase; }
    h2 { font-size: 5vh; margin: 5px 0 0 0; color: #fff; font-weight: 800; text-transform: uppercase; text-shadow: 0 2px 10px rgba(0,0,0,0.5); }
    
    /* Status Pills */
    .status-pill { padding: 5px 15px; border-radius: 20px; font-size: 12px; font-weight: bold; margin-top: 10px; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.3); }
    .pill-active { background: #059669; color: white; animation: pulse 2s infinite; }
    .pill-locked { background: #dc2626; color: white; }
    .pill-standby { background: #475569; color: #cbd5e1; }

    @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(5, 150, 105, 0.7); } 70% { box-shadow: 0 0 0 10px rgba(5, 150, 105, 0); } 100% { box-shadow: 0 0 0 0 rgba(5, 150, 105, 0); } }

    /* --- STAGE --- */
    #stage { flex-grow: 1; position: relative; overflow-y: auto; overflow-x: hidden; padding-top: 20px; display: flex; flex-direction: column; align-items: center; background: radial-gradient(circle at center, #1e293b 0%, #0f172a 70%); }
    
    .table-header { width: 85%; display: flex; padding: 15px 30px; margin-bottom: 10px; color: #64748b; font-weight: 700; text-transform: uppercase; font-size: 1vw; letter-spacing: 2px; border-bottom: 1px solid #334155; }
    .th-rank { width: 80px; text-align: center; }
    .th-img  { width: 90px; } 
    .th-name { flex-grow: 1; padding-left: 20px; }
    .th-score { width: 180px; text-align: right; }

    .ranking-container { position: relative; width: 85%; transition: height 0.5s; }

    /* --- RANK CARDS --- */
    .rank-row {
        position: absolute; 
        width: 100%;
        height: 80px; /* Fixed Height */
        display: flex;
        align-items: center;
        background: rgba(30, 41, 59, 0.6);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: 12px;
        padding: 0 30px;
        box-sizing: border-box;
        margin-bottom: 10px;
        transition: top 1.5s cubic-bezier(0.34, 1.56, 0.64, 1), background 0.3s, opacity 0.5s, transform 0.5s, border 0.3s;
        backdrop-filter: blur(5px);
    }

    /* Columns */
    .cell-rank { width: 80px; text-align: center; font-weight: 900; font-size: 2.2em; color: #64748b; font-family: 'Segoe UI', sans-serif; text-shadow: 0 2px 4px rgba(0,0,0,0.3); }
    .cell-img  { width: 90px; display: flex; justify-content: center; }
    .t-avatar { width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 3px solid #334155; box-shadow: 0 4px 6px rgba(0,0,0,0.3); }
    .cell-name { flex-grow: 1; font-weight: 700; font-size: 1.8vw; padding-left: 20px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #f1f5f9; }
    .cell-score { width: 180px; text-align: right; font-weight: 800; font-size: 2vw; color: #F59E0B; font-family: 'Courier New', monospace; letter-spacing: -1px; }

    /* --- MODES & ANIMATIONS --- */
    
    /* 1. STANDBY: Blur scores, Hide Rank */
    .mode-standby .cell-score span { filter: blur(20px); opacity: 0; }
    .mode-standby .cell-rank { opacity: 0; transform: scale(0.5); }
    .mode-standby .rank-row { background: rgba(255,255,255,0.02); }

    /* 2. REVEAL: Show scores, Show Rank */
    .mode-reveal .cell-rank { color: #fff; transition-delay: 0.5s; }
    .mode-reveal .rank-row { background: rgba(59, 130, 246, 0.1); border-color: rgba(59, 130, 246, 0.3); }
    .mode-reveal .rank-1 .cell-rank { color: #F59E0B; text-shadow: 0 0 20px rgba(245, 158, 11, 0.5); }

    /* 3. VERDICT: High-Contrast Highlights */
    .mode-verdict.row-qualified { 
        border: 3px solid #22c55e !important; 
        background: rgba(6, 95, 70, 0.6) !important; 
        box-shadow: 0 0 30px rgba(34, 197, 94, 0.5);
        z-index: 10;
        transform: scale(1.02);
    }
    
    .mode-verdict.row-qualified .cell-name { color: #ffffff; text-shadow: 0 0 10px rgba(34, 197, 94, 0.8); }
    .mode-verdict.row-qualified .cell-rank { color: #4ade80; }

    .mode-verdict.row-eliminated { 
        opacity: 0.2; 
        filter: grayscale(100%); 
        border: 1px solid #334155; 
        background: rgba(15, 23, 42, 0.5);
        transform: scale(0.98);
    }

    /* --- CONTROL BAR (HOVER LOGIC) --- */
    #control-bar { 
        height: 60px; background: #020617; border-top: 1px solid #1e293b; 
        display: flex; justify-content: center; align-items: center; gap: 15px; z-index: 1000;
        transition: transform 0.4s ease-out, opacity 0.4s ease-out;
    }
    
    /* The "Invisible Trigger" at bottom of screen */
    #fs-trigger { 
        position: fixed; bottom: 0; left: 0; width: 100%; height: 20px; 
        z-index: 999; display: none; 
    }
    
    /* Fullscreen Active States */
    body.fs-active #fs-trigger { display: block; }
    
    body.fs-active #control-bar { 
        position: fixed; bottom: 0; width: 100%; 
        transform: translateY(100%); opacity: 0; 
    }
    
    /* Reveal Bar on Hover */
    body.fs-active #fs-trigger:hover ~ #control-bar, 
    body.fs-active #control-bar:hover { 
        transform: translateY(0); opacity: 1; 
    }

    body.fs-active #header { margin-top: -15vh; } /* Hide Header in FS */

    .ctrl-group { display: flex; background: #1e293b; padding: 4px; border-radius: 6px; border: 1px solid #334155; }
    .ctrl-btn { background: transparent; color: #94a3b8; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 6px; font-size: 13px; transition: 0.2s; }
    .ctrl-btn:hover { color: white; background: rgba(255,255,255,0.05); }
    .ctrl-btn.active { background: #3b82f6; color: white; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
    
    select { background: #0f172a; color: white; border: 1px solid #334155; padding: 8px; border-radius: 4px; outline: none; }

</style>
</head>
<body>

<div id="header">
    <h1><?= htmlspecialchars($event_name) ?></h1>
    <h2 id="round-display">SELECT ROUND</h2>
    <div id="status-pill" class="status-pill pill-standby"><i class="fas fa-pause"></i> STANDBY</div>
</div>

<div id="stage">
    <div class="table-header">
        <div class="th-rank">#</div>
        <div class="th-img"></div> <div class="th-name">CANDIDATE</div>
        <div class="th-score">SCORE</div>
    </div>

    <div class="ranking-container" id="list-container">
        <div style="color:#475569; text-align:center; padding-top:100px; font-size:1.2rem;">
            <i class="fas fa-satellite-dish fa-spin"></i> Connecting to Mainframe...
        </div>
    </div>
</div>

<div id="fs-trigger"></div>

<div id="control-bar">
    <select id="round-selector" onchange="manualChangeRound()">
        <option value="">-- Select Round --</option>
        <?php foreach($rounds as $r): ?>
            <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['title']) ?></option>
        <?php endforeach; ?>
    </select>

    <div class="ctrl-group">
        <button class="ctrl-btn active" id="btn-standby" onclick="setMode('standby')">STANDBY</button>
        <button class="ctrl-btn" id="btn-reveal" onclick="setMode('reveal')">LIVE</button>
        <button class="ctrl-btn" id="btn-verdict" onclick="setMode('verdict')">VERDICT</button>
    </div>

    <button class="ctrl-btn" onclick="toggleFullScreen()" style="margin-left: 20px;">
        <i class="fas fa-expand"></i> FULLSCREEN
    </button>
</div>

<script>
    // Configuration
    const ROW_HEIGHT = 90; // 80px card + 10px margin
    const POLL_INTERVAL = 3000; // 3 seconds

    // State
    let currentRoundId = null;
    let pollTimer = null;
    let currentMode = 'standby'; 
    let contestantsData = [];
    let cutOffCount = 5;
    let roundStatus = 'Pending';

    // --- 1. CORE LOGIC ---

    function manualChangeRound() {
        currentRoundId = document.getElementById('round-selector').value;
        if(!currentRoundId) return;
        
        // Reset View
        setMode('standby');
        contestantsData = []; 
        document.getElementById('list-container').innerHTML = '<div style="text-align:center; padding:50px; color:#666;">Loading...</div>';
        
        // Update Title
        const sel = document.getElementById('round-selector');
        document.getElementById('round-display').innerText = sel.options[sel.selectedIndex].text;

        // Start Polling
        if(pollTimer) clearInterval(pollTimer);
        fetchData();
        pollTimer = setInterval(fetchData, POLL_INTERVAL);
    }

    async function fetchData() {
        if(!currentRoundId) return;
        
        try {
            const res = await fetch(`../api/tally.php?round_id=${currentRoundId}`);
            const data = await res.json();

            if(data.status === 'success') {
                contestantsData = data.ranking;
                cutOffCount = parseInt(data.qualifiers) || 5;
                roundStatus = data.round_status;

                updateStatusUI(); // Update the pill badge
                renderRows(); // Physical DOM update
            }
        } catch(e) {
            console.error("Sync Error:", e);
        }
    }

    // --- 2. RENDER ENGINE ---

    function renderRows() {
        const container = document.getElementById('list-container');
        
        // A. Create DOM elements if they don't exist
        if(container.children.length !== contestantsData.length) {
            container.innerHTML = ''; // Full rebuild if size changes
            container.style.height = (contestantsData.length * ROW_HEIGHT) + 'px';
            
            contestantsData.forEach(item => {
                const c = item.contestant;
                const div = document.createElement('div');
                div.id = `row-${c.detail_id || c.id}`;
                div.className = `rank-row mode-${currentMode}`;
                div.innerHTML = `
                    <div class="cell-rank"></div>
                    <div class="cell-img"><img src="./assets/uploads/contestants/${c.photo}" class="t-avatar" onerror="this.src='./assets/images/default_user.png'"></div>
                    <div class="cell-name">${c.name}</div>
                    <div class="cell-score"><span>0.00</span></div>
                `;
                container.appendChild(div);
            });
        }

        // B. Sort Data based on Mode
        let sortedList = [...contestantsData];
        if (currentMode === 'standby') {
            // Sort by Number (Neutral)
            sortedList.sort((a, b) => a.contestant.contestant_number - b.contestant.contestant_number);
        } else {
            // Sort by Rank (Winner on top)
            sortedList.sort((a, b) => parseFloat(a.rank) - parseFloat(b.rank));
        }

        // C. Animate Positions & Update Content
        sortedList.forEach((item, index) => {
            const c = item.contestant;
            const el = document.getElementById(`row-${c.detail_id || c.id}`);
            
            if(el) {
                // Move Row
                el.style.top = (index * ROW_HEIGHT) + 'px';
                
                // Update Text
                el.querySelector('.cell-score span').innerText = parseFloat(item.final_score).toFixed(2);
                
                // Rank Display
                const rankEl = el.querySelector('.cell-rank');
                if (currentMode === 'standby') rankEl.innerText = c.contestant_number; // Show # in standby
                else rankEl.innerText = item.rank; // Show Rank in results

                // Update Classes (State)
                el.className = `rank-row mode-${currentMode}`;
                
                // Special Class for Rank 1
                if (item.rank == 1) el.classList.add('rank-1');
                else el.classList.remove('rank-1');

                // Verdict Highlighting
                if (currentMode === 'verdict') {
                    if (index < cutOffCount) el.classList.add('row-qualified');
                    else el.classList.add('row-eliminated');
                } else {
                    el.classList.remove('row-qualified', 'row-eliminated');
                }
            }
        });
    }

    // --- 3. UI CONTROLS ---

    function setMode(mode) {
        if(currentMode === mode) return; // No change
        currentMode = mode;
        
        // Update Buttons
        document.querySelectorAll('.ctrl-btn').forEach(b => b.classList.remove('active'));
        document.getElementById(`btn-${mode}`).classList.add('active');
        
        renderRows(); // Trigger animation
    }

    function updateStatusUI() {
        const pill = document.getElementById('status-pill');
        if(roundStatus === 'Active') {
            pill.className = 'status-pill pill-active';
            pill.innerHTML = '<i class="fas fa-circle"></i> LIVE SCORING';
        } else if(roundStatus === 'Completed') {
            pill.className = 'status-pill pill-locked';
            pill.innerHTML = '<i class="fas fa-lock"></i> OFFICIAL RESULTS';
        } else {
            pill.className = 'status-pill pill-standby';
            pill.innerHTML = '<i class="fas fa-pause"></i> PENDING';
        }
    }

    function toggleFullScreen() {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen();
            document.body.classList.add('fs-active');
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen();
                document.body.classList.remove('fs-active');
            }
        }
    }

    // Auto-Select first round on load
    window.addEventListener('DOMContentLoaded', () => {
        const sel = document.getElementById('round-selector');
        if(sel.options.length > 1) {
            sel.selectedIndex = 1;
            manualChangeRound();
        }
    });

</script>
</body>
</html>