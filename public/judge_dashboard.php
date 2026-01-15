<?php
// judge_dashboard.php - Fixed Z-Index Layering
require_once __DIR__ . '/../app/core/guard.php';
require_once __DIR__ . '/../app/config/database.php';

requireLogin();
requireRole('Judge');

$judge_id = $_SESSION['user_id'];
$judge_name = $_SESSION['name'] ?? 'Judge';

// ==========================================
// 1. SECURITY CHECK
// ==========================================
$evt_sql = "SELECT id, title FROM events WHERE status = 'Active' LIMIT 1";
$evt_res = $conn->query($evt_sql);
$global_event = $evt_res->fetch_assoc();

if (!$global_event) {
    die("<div style='text-align:center;padding:50px;'><h2>No Active Event</h2><a href='logout.php'>Logout</a></div>");
}

// Am I assigned?
$assign_stmt = $conn->prepare("SELECT id FROM event_judges WHERE event_id = ? AND judge_id = ? AND status = 'Active' AND is_deleted = 0");
$assign_stmt->bind_param("ii", $global_event['id'], $judge_id);
$assign_stmt->execute();

if ($assign_stmt->get_result()->num_rows === 0) {
    die("<div style='text-align:center;padding:50px;'><h2>Access Denied</h2><p>Not assigned to this event.</p><a href='logout.php'>Logout</a></div>");
}

$event_name = $global_event['title'];
$event_id = $global_event['id'];

// ==========================================
// 2. CHECK FOR ACTIVE ROUND
// ==========================================
$query = "SELECT r.id as round_id, r.title as round_title, r.ordering
          FROM rounds r
          WHERE r.event_id = ? AND r.status = 'Active' AND r.is_deleted = 0 LIMIT 1";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $event_id);
$stmt->execute();
$active = $stmt->get_result()->fetch_assoc();

$round_id = 0;
$segments_data = []; 
$contestants = [];
$draft_scores = []; 
$is_locked = false; 
$has_active_round = false;

if ($active) {
    $has_active_round = true;
    $round_id = $active['round_id'];
    $current_order = (int)$active['ordering'];

    // Get Segments
    $seg_q = "SELECT id, title, description FROM segments WHERE round_id = ? AND is_deleted = 0 ORDER BY ordering";
    $stmt_s = $conn->prepare($seg_q);
    $stmt_s->bind_param("i", $round_id);
    $stmt_s->execute();
    $segments_raw = $stmt_s->get_result()->fetch_all(MYSQLI_ASSOC);

    // Get Criteria
    $crit_q = "SELECT id, segment_id, title, description, max_score 
               FROM criteria 
               WHERE segment_id IN (SELECT id FROM segments WHERE round_id = ?) 
               AND is_deleted = 0 ORDER BY ordering";
    $stmt_crit = $conn->prepare($crit_q);
    $stmt_crit->bind_param("i", $round_id);
    $stmt_crit->execute();
    $criteria_raw = $stmt_crit->get_result()->fetch_all(MYSQLI_ASSOC);

    // Organize Criteria
    foreach ($segments_raw as $s) {
        $s['criteria'] = [];
        foreach ($criteria_raw as $c) {
            if ($c['segment_id'] == $s['id']) $s['criteria'][] = $c;
        }
        $segments_data[$s['id']] = $s;
    }

    // 4. Get Contestants (Funnel Logic)
    if ($current_order == 1) {
        // ROUND 1: Show ALL Active Contestants
        // ADDED: AND ec.status = 'Active'
        $cont_q = "SELECT ec.id, u.name, ec.photo, ec.contestant_number
                   FROM event_contestants ec 
                   JOIN users u ON ec.user_id = u.id 
                   WHERE ec.event_id = ? 
                     AND ec.is_deleted = 0 
                     AND ec.status = 'Active'
                   ORDER BY ec.contestant_number ASC"; 
        $stmt_c = $conn->prepare($cont_q);
        $stmt_c->bind_param("i", $event_id);
    } else {
        // ROUND 2+: Show ONLY WINNERS from Previous Round
        $prev_order = $current_order - 1;
        
        $prev_q = $conn->query("SELECT id, qualify_count FROM rounds WHERE event_id = $event_id AND ordering = $prev_order");
        $prev_round = $prev_q->fetch_assoc();
        
        if ($prev_round) {
            $prev_rid = $prev_round['id'];
            $limit = $prev_round['qualify_count'];
            
            // Join with Rankings Table
            // ADDED: AND ec.status = 'Active'
            $cont_q = "SELECT ec.id, u.name, ec.photo, ec.contestant_number
                       FROM event_contestants ec 
                       JOIN users u ON ec.user_id = u.id
                       JOIN round_rankings rr ON ec.id = rr.contestant_id
                       WHERE ec.event_id = ? 
                         AND rr.round_id = ? 
                         AND rr.rank <= ? 
                         AND ec.is_deleted = 0
                         AND ec.status = 'Active'
                       ORDER BY rr.rank ASC";
            $stmt_c = $conn->prepare($cont_q);
            $stmt_c->bind_param("iii", $event_id, $prev_rid, $limit);
        } else {
            // Fallback
            $cont_q = "SELECT ec.id, u.name, ec.photo, ec.contestant_number 
                       FROM event_contestants ec 
                       JOIN users u ON ec.user_id = u.id 
                       WHERE ec.event_id = ? 
                         AND ec.is_deleted = 0
                         AND ec.status = 'Active'";
            $stmt_c = $conn->prepare($cont_q);
            $stmt_c->bind_param("i", $event_id);
        }
    }
    $stmt_c->execute();
    $contestants = $stmt_c->get_result()->fetch_all(MYSQLI_ASSOC);

    // Get Saved Scores
    $stmt_scores = $conn->prepare("SELECT s.contestant_id, s.criteria_id, s.score_value 
                   FROM scores s
                   JOIN criteria c ON s.criteria_id = c.id
                   JOIN segments seg ON c.segment_id = seg.id
                   WHERE s.judge_id = ? AND seg.round_id = ?");
    $stmt_scores->bind_param("ii", $judge_id, $round_id);
    $stmt_scores->execute();
    $res_scores = $stmt_scores->get_result();
    
    while($r = $res_scores->fetch_assoc()) {
        $draft_scores[$r['contestant_id']][$r['criteria_id']] = (float)$r['score_value'];
    }

    // Lock Status
    $status_res = $conn->query("SELECT status FROM judge_round_status WHERE round_id = $round_id AND judge_id = $judge_id");
    if ($status_res && $row = $status_res->fetch_assoc()) {
        $is_locked = ($row['status'] === 'Submitted');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"> 
    <title>Judge Panel</title>
    <link rel="stylesheet" href="./assets/fontawesome/css/all.min.css">
    <style>
        :root { --primary: #111827; --accent: #F59E0B; --success: #10b981; --bg: #f3f4f6; }
        
        /* FIX: Global Box Sizing */
        * { box-sizing: border-box; }

        body { 
            background: var(--bg); 
            font-family: 'Segoe UI', sans-serif; 
            margin: 0; 
            padding-bottom: 120px; /* Extra space for footer */
            -webkit-tap-highlight-color: transparent; 
        }
        
        /* Header: Z-Index 50 */
        .header { 
            background: var(--primary); color: white; padding: 15px; 
            position: sticky; top: 0; z-index: 50; 
            display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        .j-name { font-weight: bold; font-size: 1.1rem; }
        .j-role { font-size: 0.8rem; color: var(--accent); text-transform: uppercase; letter-spacing: 1px; }
        .logout { color: #f87171; text-decoration: none; font-size: 0.9rem; font-weight: bold; }

        .tabs-container { background: white; padding: 10px; position: sticky; top: 60px; z-index: 40; border-bottom: 1px solid #ddd; overflow-x: auto; white-space: nowrap; -webkit-overflow-scrolling: touch; }
        .tab-btn { display: inline-block; padding: 8px 16px; margin-right: 10px; background: #f3f4f6; border: 1px solid #e5e7eb; border-radius: 20px; color: #4b5563; font-weight: 600; font-size: 0.9rem; cursor: pointer; }
        .tab-btn.active { background: var(--accent); color: white; border-color: var(--accent); }

        .grid { display: grid; grid-template-columns: 1fr; gap: 15px; padding: 15px; }
        @media (min-width: 600px) { .grid { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); } }

        .card { background: white; border-radius: 12px; padding: 15px; display: flex; align-items: center; gap: 15px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); cursor: pointer; position: relative; border-left: 5px solid transparent; transition: transform 0.1s; }
        .card:active { transform: scale(0.98); background: #fafafa; }
        .card img { width: 70px; height: 70px; border-radius: 50%; object-fit: cover; border: 2px solid #eee; flex-shrink: 0; }
        .c-info { flex-grow: 1; }
        .c-num { font-size: 1.2rem; font-weight: 900; color: var(--primary); }
        .c-name { font-size: 1rem; font-weight: 600; line-height: 1.2; margin-bottom: 4px; }
        .status-text { font-size: 0.75rem; font-weight: bold; color: #9ca3af; display: flex; align-items: center; gap: 5px; }
        .card.done { border-left-color: var(--success); background: #ecfdf5; }
        .card.done .status-text { color: var(--success); }
        .card.done .c-num { color: var(--success); }

        /* --- Z-INDEX FIX: Modal must be higher than Footer --- */
        .modal { 
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
            background: #f9fafb; z-index: 2000; /* Highest priority */
            display: none; flex-direction: column; 
        }
        .modal-header { 
            background: white; padding: 15px; border-bottom: 1px solid #eee; 
            display: flex; align-items: center; gap: 15px; position: sticky; top: 0; 
            z-index: 2001; /* Above modal content */
        }
        .close-btn { font-size: 1.5rem; color: #4b5563; padding: 5px; cursor: pointer; }
        .modal-content { flex-grow: 1; overflow-y: auto; padding: 20px; padding-bottom: 100px; -webkit-overflow-scrolling: touch; }
        
        .crit-row { background: white; border-radius: 10px; padding: 20px; margin-bottom: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.03); border: 1px solid #eee; }
        .crit-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px; }
        .c-label { font-weight: bold; font-size: 1rem; color: #1f2937; }
        .c-desc { font-size: 0.85rem; color: #6b7280; margin-bottom: 10px; font-style: italic; }
        .c-max { background: #e5e7eb; font-size: 0.75rem; padding: 3px 8px; border-radius: 10px; font-weight: bold; }
        
        .control-area { display: flex; align-items: center; gap: 15px; }
        input[type=range] { flex-grow: 1; height: 8px; border-radius: 5px; background: #d1d5db; outline: none; }
        input[type=range]::-webkit-slider-thumb { -webkit-appearance: none; width: 28px; height: 28px; border-radius: 50%; background: var(--accent); border: 3px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3); }
        .score-box { width: 70px; padding: 10px; text-align: center; font-size: 1.2rem; font-weight: bold; border: 2px solid #d1d5db; border-radius: 8px; color: var(--primary); }
        .score-box:focus { border-color: var(--accent); outline: none; background: #fffbeb; }

        /* --- FOOTERS --- */
        /* Dashboard Footer: Z-Index 100 */
        .footer { 
            position: fixed; bottom: 0; left: 0; right: 0; 
            background: white; padding: 15px; border-top: 1px solid #eee; 
            text-align: center; z-index: 100; 
            box-shadow: 0 -4px 10px rgba(0,0,0,0.05);
        }

        /* Modal Save Bar: Z-Index 2001 (Inside Modal) */
        .save-bar { 
            position: fixed; bottom: 0; left: 0; right: 0; 
            background: white; padding: 15px; 
            box-shadow: 0 -4px 10px rgba(0,0,0,0.05); text-align: center;
            z-index: 2001; /* Must be higher than .modal */
        }
        
        .btn-save, .btn-final { 
            width: 100%; padding: 15px; border: none; border-radius: 10px; 
            font-weight: bold; font-size: 1.1rem; cursor: pointer;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .btn-save { background: var(--primary); color: white; }
        .btn-save:active { background: black; transform: scale(0.98); }

        .btn-final { background: var(--success); color: white; text-transform: uppercase; letter-spacing: 1px; transition: 0.3s; }
        .btn-final:disabled { background: #d1d5db; color: #9ca3af; cursor: not-allowed; opacity: 1; box-shadow: none; }

        #toast { position: fixed; bottom: 90px; left: 50%; transform: translateX(-50%); background: rgba(0,0,0,0.9); color: white; padding: 12px 25px; border-radius: 30px; font-size: 0.9rem; font-weight: bold; display: none; z-index: 3000; white-space: nowrap; }
    </style>
</head>
<body>

<div class="header">
    <div>
        <div class="j-role"><?= htmlspecialchars($event_name) ?></div>
        <div class="j-name"><?= htmlspecialchars($judge_name) ?></div>
    </div>
    <a href="logout.php" class="logout" onclick="return confirm('Logout?')"><i class="fas fa-sign-out-alt"></i> Exit</a>
</div>

<?php if (!$has_active_round): ?>
    <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; height:60vh; text-align:center; padding:30px;">
        <i class="fas fa-hourglass-half" style="font-size:3rem; color:#d1d5db; margin-bottom:20px;"></i>
        <h2 style="margin:0; color:#374151;">Round Standby</h2>
        <p style="color:#6b7280; margin:10px 0 30px;">Please wait for the Event Manager.</p>
        <button onclick="location.reload()" style="padding:12px 30px; background:white; border:2px solid #e5e7eb; border-radius:25px; font-weight:bold; color:#374151;">Tap to Refresh</button>
    </div>
<?php else: ?>

    <div class="tabs-container">
        <?php foreach ($segments_data as $s): ?>
            <button class="tab-btn" onclick="switchSegment(<?= $s['id'] ?>)" id="seg-btn-<?= $s['id'] ?>">
                <?= htmlspecialchars($s['title']) ?>
            </button>
        <?php endforeach; ?>
    </div>

    <div class="grid">
        <?php if(empty($contestants)): ?>
            <div style="grid-column: 1/-1; text-align:center; padding:40px; color:#999;">
                <p>No qualified contestants found for this round.</p>
            </div>
        <?php else: ?>
            <?php foreach ($contestants as $c): ?>
                <div class="card" id="card-<?= $c['id'] ?>" onclick="openScoring(<?= $c['id'] ?>)">
                    <img src="assets/uploads/contestants/<?= $c['photo'] ?>" onerror="this.src='assets/images/default_user.png'">
                    <div class="c-info">
                        <div class="c-num">#<?= $c['contestant_number'] ?></div>
                        <div class="c-name"><?= htmlspecialchars($c['name']) ?></div>
                        <div class="status-text" id="status-<?= $c['id'] ?>">
                            <i class="far fa-circle"></i> <span>Pending</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right" style="color:#d1d5db;"></i>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="footer">
        <?php if ($is_locked): ?>
            <button class="btn-final" disabled style="background:#4b5563;">
                <i class="fas fa-lock"></i> SUBMITTED
            </button>
        <?php else: ?>
            <form id="submitForm" action="../api/submit_scores.php" method="POST" onsubmit="return confirm('Are you sure? This cannot be undone.');">
                <input type="hidden" name="round_id" value="<?= $round_id ?>">
                <button type="submit" id="btnSubmitAll" class="btn-final" disabled>Finish Scoring to Submit</button>
            </form>
        <?php endif; ?>
    </div>

    <div id="scoringModal" class="modal">
        <div class="modal-header">
            <i class="fas fa-arrow-left close-btn" onclick="closeModal()"></i>
            <div>
                <div id="mName" style="font-weight:900; font-size:1.1rem; line-height:1;"></div>
                <div id="mSeg" style="font-size:0.8rem; color:#6b7280; font-weight:bold; margin-top:2px;"></div>
            </div>
        </div>
        <div class="modal-content" id="criteriaList"></div>
        <div class="save-bar">
            <?php if (!$is_locked): ?>
                <button class="btn-save" onclick="saveScores()">
                    <i class="fas fa-check"></i> SAVE SCORE
                </button>
            <?php else: ?>
                <div style="color:#ef4444; font-weight:bold; text-transform:uppercase;">View Only (Locked)</div>
            <?php endif; ?>
        </div>
    </div>

    <div id="toast">Saved!</div>

    <script>
        const segments = <?= json_encode($segments_data) ?>;
        const contestants = <?= json_encode($contestants) ?>;
        // 1. We start with DB scores (in case they switched devices)
        let dbScores = <?= json_encode($draft_scores) ?> || {};
        const isLocked = <?= $is_locked ? 'true' : 'false' ?>;
        const roundId = <?= $round_id ?>;
        const judgeId = <?= $judge_id ?>; // Needed for unique storage key

        // 2. We try to load "Fresh" scores from Browser Memory
        const storageKey = `bpms_scores_${judgeId}_${roundId}`;
        let localScores = JSON.parse(localStorage.getItem(storageKey)) || {};

        // 3. Merge: Local Storage wins (it's newer), fallback to DB
        // Deep merge logic simplified for this structure
        let savedScores = { ...dbScores }; 
        for (const cid in localScores) {
            if (!savedScores[cid]) savedScores[cid] = {};
            Object.assign(savedScores[cid], localScores[cid]);
        }

        let currentSegId = Object.keys(segments)[0];
        let currentCId = null;

        function switchSegment(id) {
            currentSegId = id;
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById('seg-btn-' + id).classList.add('active');
            refreshCardStatus();
        }

        function openScoring(cid) {
            currentCId = cid;
            const c = contestants.find(x => x.id == cid);
            const seg = segments[currentSegId];

            document.getElementById('mName').innerText = "#" + c.contestant_number + " " + c.name;
            document.getElementById('mSeg').innerText = seg.title;

            const container = document.getElementById('criteriaList');
            container.innerHTML = '';

            if (seg.criteria.length === 0) {
                container.innerHTML = '<div style="text-align:center; padding:40px; color:#999;">No criteria.</div>';
            } else {
                seg.criteria.forEach(crit => {
                    let val = '';
                    // Check our merged scores object
                    if (savedScores[cid] && savedScores[cid][crit.id] !== undefined) {
                        val = savedScores[cid][crit.id];
                    }

                    let descHtml = crit.description ? `<div class="c-desc">${crit.description}</div>` : '';

                    let html = `
                    <div class="crit-row">
                        <div class="crit-head">
                            <span class="c-label">${crit.title}</span>
                            <span class="c-max">Max: ${crit.max_score}</span>
                        </div>
                        ${descHtml}
                        <div class="control-area">
                            <input type="range" min="0" max="${crit.max_score}" step="1" value="${val || 0}" 
                                oninput="this.nextElementSibling.value = this.value" ${isLocked ? 'disabled' : ''}>
                            
                            <input type="number" class="score-box score-input" data-crit="${crit.id}" 
                                min="0" max="${crit.max_score}" step="0.1" value="${val}" 
                                oninput="this.previousElementSibling.value = this.value" 
                                placeholder="-" ${isLocked ? 'disabled' : ''}>
                        </div>
                    </div>`;
                    container.innerHTML += html;
                });
            }
            document.getElementById('scoringModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('scoringModal').style.display = 'none';
        }

        // --- UPDATED: SAVE TO LOCAL STORAGE ONLY ---
        function saveScores() {
            if(isLocked) return;

            const inputs = document.querySelectorAll('.score-input');
            const newScores = {};
            let valid = true;

            inputs.forEach(inp => {
                let valStr = inp.value;
                let val = parseFloat(valStr);
                let max = parseFloat(inp.getAttribute('max'));

                if (valStr === '') {
                    // Empty implies deletion
                } else if (isNaN(val) || val < 0 || val > max) {
                    alert("Invalid score: " + val + " (Max: " + max + ")");
                    valid = false;
                } else {
                    newScores[inp.dataset.crit] = val;
                }
            });

            if (!valid) return;

            if(!savedScores[currentCId]) savedScores[currentCId] = {};
            
            // Overwrite specific criteria in memory
            const seg = segments[currentSegId];
            seg.criteria.forEach(c => {
                if (newScores[c.id] !== undefined) {
                    savedScores[currentCId][c.id] = newScores[c.id];
                } else {
                    delete savedScores[currentCId][c.id];
                }
            });

            // 4. COMMIT TO LOCAL STORAGE (The Magic Part)
            localStorage.setItem(storageKey, JSON.stringify(savedScores));

            refreshCardStatus();
            updateSubmitButton();
            closeModal();
            showToast("Saved to Device (Draft)");
        }

        function refreshCardStatus() {
            const seg = segments[currentSegId];
            if(!seg) return;

            contestants.forEach(c => {
                const card = document.getElementById('card-' + c.id);
                const statusTxt = document.getElementById('status-' + c.id);
                
                let filled = 0;
                let total = seg.criteria.length;

                seg.criteria.forEach(crit => {
                    if (savedScores[c.id] && savedScores[c.id][crit.id] !== undefined && savedScores[c.id][crit.id] > 0) {
                        filled++;
                    }
                });

                if (total > 0 && filled === total) {
                    card.classList.add('done');
                    statusTxt.innerHTML = '<i class="fas fa-check-circle"></i> <span>Scored</span>';
                } else {
                    card.classList.remove('done');
                    if (filled > 0) {
                        statusTxt.innerHTML = `<i class="fas fa-adjust"></i> <span style="color:#F59E0B">In Progress (${filled}/${total})</span>`;
                    } else {
                        statusTxt.innerHTML = '<i class="far fa-circle"></i> <span>Pending</span>';
                    }
                }
            });
        }

        // --- UPDATED: SUBMIT ALL (SENDS EVERYTHING) ---
        async function submitAll(e) {
            e.preventDefault();
            if(!confirm('Are you sure? This will SUBMIT ALL scores to the server.')) return;

            const btn = document.getElementById('btnSubmitAll');
            btn.disabled = true;
            btn.innerText = "Submitting...";

            try {
                const response = await fetch('../api/submit_scores.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        round_id: roundId,
                        all_scores: savedScores // WE SEND THE WHOLE BLOCK HERE
                    })
                });

                const result = await response.json();

                if (result.success) {
                    // 5. CLEAR LOCAL STORAGE ON SUCCESS
                    localStorage.removeItem(storageKey);
                    alert("Submission Successful!");
                    location.reload();
                } else {
                    alert("Error: " + (result.error || "Unknown error"));
                    btn.disabled = false;
                    btn.innerText = "SUBMIT ALL";
                }
            } catch(err) {
                alert("Network Error. Check connection.");
                btn.disabled = false;
                btn.innerText = "SUBMIT ALL";
            }
        }

        function updateSubmitButton() {
            if (isLocked) return;
            const btn = document.getElementById('btnSubmitAll');
            if (!btn) return;

            let allComplete = true;
            
            for (const segId in segments) {
                const seg = segments[segId];
                for (const c of contestants) {
                    for (const crit of seg.criteria) {
                        const s = savedScores[c.id] ? savedScores[c.id][crit.id] : 0;
                        if (s === undefined || s <= 0) {
                            allComplete = false;
                            break;
                        }
                    }
                    if (!allComplete) break;
                }
                if (!allComplete) break;
            }

            btn.disabled = !allComplete;
            if(allComplete) {
                btn.innerHTML = "SUBMIT ALL";
                btn.style.backgroundColor = "var(--success)";
            } else {
                btn.innerHTML = "Finish Scoring to Submit";
                btn.style.backgroundColor = "#d1d5db";
            }
        }

        function showToast(msg) {
            const t = document.getElementById('toast');
            t.innerText = msg;
            t.style.display = 'block';
            setTimeout(() => t.style.display = 'none', 2000);
        }

        if(Object.keys(segments).length > 0) {
            switchSegment(currentSegId);
        }
        
        updateSubmitButton();
        
        // Attach the new submit handler
        const form = document.getElementById('submitForm');
        if(form) form.onsubmit = submitAll; 

    </script>
<?php endif; ?>

</body>
</html>