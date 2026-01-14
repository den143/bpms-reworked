<?php
// judge_dashboard.php - Fixed Slider & Logic

require_once __DIR__ . '/../app/core/guard.php';
require_once __DIR__ . '/../app/config/database.php';

requireLogin();
requireRole('Judge');

$judge_id = $_SESSION['user_id'];
$judge_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Judge';

// 0. Fetch Judge Name if needed
if ($judge_name === 'Judge') {
    $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->bind_param("i", $judge_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    if ($res) $judge_name = $res['name'];
}

// 0.5 Get Event Name
$event_name = "OFFICIAL PAGEANT";
$evt_sql = "SELECT title FROM events WHERE status = 'Active' LIMIT 1";
$evt_res = $conn->query($evt_sql);
if ($evt_res && $row = $evt_res->fetch_assoc()) $event_name = $row['title'];

// 1. Check for Active Round
$query = "SELECT r.id as round_id, r.title as round_title, e.id as event_id
          FROM event_judges ej
          JOIN events e ON ej.event_id = e.id
          JOIN rounds r ON r.event_id = e.id
          WHERE ej.judge_id = ? 
            AND e.status = 'Active' 
            AND r.status = 'Active'
            AND ej.status = 'Active'
            AND ej.is_deleted = 0
            AND r.is_deleted = 0
          LIMIT 1";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $judge_id);
$stmt->execute();
$active = $stmt->get_result()->fetch_assoc();

// Init variables
$round_id = 0; $event_id = 0;
$segments_data = []; $contestants = [];
$draft_scores = []; $draft_comments = [];
$is_locked = false; $has_active_round = false;

if ($active) {
    $has_active_round = true;
    $round_id = $active['round_id'];
    $event_id = $active['event_id'];

    // 2. Get Segments
    $seg_q = "SELECT id, title, description FROM segments WHERE round_id = ? AND is_deleted = 0 ORDER BY ordering";
    $stmt_s = $conn->prepare($seg_q);
    $stmt_s->bind_param("i", $round_id);
    $stmt_s->execute();
    $segments_raw = $stmt_s->get_result()->fetch_all(MYSQLI_ASSOC);

    // 3. Get Criteria
    $crit_q = "SELECT id, segment_id, title, description, max_score 
               FROM criteria 
               WHERE segment_id IN (SELECT id FROM segments WHERE round_id = ?) 
               AND is_deleted = 0
               ORDER BY ordering";
    $stmt_crit = $conn->prepare($crit_q);
    $stmt_crit->bind_param("i", $round_id);
    $stmt_crit->execute();
    $criteria_raw = $stmt_crit->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($segments_raw as $s) {
        $s['criteria'] = [];
        foreach ($criteria_raw as $c) {
            if ($c['segment_id'] == $s['id']) $s['criteria'][] = $c;
        }
        $segments_data[$s['id']] = $s;
    }

    // 4. Get Contestants
    $cont_q = "SELECT ec.id, u.name, ec.photo, ec.age, ec.hometown, ec.contestant_number
               FROM event_contestants ec 
               JOIN users u ON ec.user_id = u.id 
               WHERE ec.event_id = ? 
                AND u.status = 'Active' 
                AND ec.status IN ('Active', 'Qualified')
                AND ec.is_deleted = 0
               ORDER BY ec.contestant_number ASC"; 

    $stmt_c = $conn->prepare($cont_q);
    $stmt_c->bind_param("i", $event_id);
    $stmt_c->execute();
    $contestants_res = $stmt_c->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($contestants_res as $c) {
        $c['display_number'] = $c['contestant_number']; 
        $contestants[] = $c;
    }

    // 5. Get Saved Scores
    $scores_sql = "SELECT s.contestant_id, s.criteria_id, s.score_value 
                   FROM scores s
                   JOIN criteria c ON s.criteria_id = c.id
                   JOIN segments seg ON c.segment_id = seg.id
                   WHERE s.judge_id = ? AND seg.round_id = ?";

    $stmt_scores = $conn->prepare($scores_sql);
    $stmt_scores->bind_param("ii", $judge_id, $round_id);
    $stmt_scores->execute();
    $scores_res = $stmt_scores->get_result();

    while($r = $scores_res->fetch_assoc()) {
        $draft_scores[$r['contestant_id']][$r['criteria_id']] = $r['score_value'];
    }

    // 6. Get Saved Comments
    $comm_sql = "SELECT jc.contestant_id, jc.segment_id, jc.comment 
                 FROM judge_comments jc
                 JOIN segments seg ON jc.segment_id = seg.id
                 WHERE jc.judge_id = ? AND seg.round_id = ?";

    $stmt_comm = $conn->prepare($comm_sql);
    $stmt_comm->bind_param("ii", $judge_id, $round_id);
    $stmt_comm->execute();
    $comm_res = $stmt_comm->get_result();

    while($r = $comm_res->fetch_assoc()) {
        $draft_comments[$r['contestant_id']][$r['segment_id']] = $r['comment'];
    }

    // Check Lock
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
        :root { --gold: #F59E0B; --dark: #111827; --success: #059669; --wip: #eab308; --gray: #d1d5db; }
        body { background: #f3f4f6; font-family: sans-serif; margin: 0; padding-bottom: 90px; -webkit-tap-highlight-color: transparent; }
        
        /* Header */
        .header { 
            background: var(--dark); color: white; padding: 15px 20px; 
            position: sticky; top: 0; z-index: 1000; 
            display: flex; justify-content: space-between; align-items: center; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        
        /* Tabs */
        .tabs { display: flex; overflow-x: auto; background: white; padding: 12px 10px; gap: 10px; border-bottom: 1px solid #ddd; position: sticky; top: 70px; z-index: 999; scrollbar-width: none; }
        .tabs::-webkit-scrollbar { display: none; }
        .tab { padding: 8px 16px; background: #f3f4f6; border-radius: 20px; font-weight: bold; cursor: pointer; white-space: nowrap; border: 1px solid #e5e7eb; font-size: 13px; color: #4b5563; transition: 0.2s; }
        .tab.active { background: var(--gold); color: white; border-color: var(--gold); box-shadow: 0 2px 4px rgba(0,0,0,0.1); }

        /* Contestant Grid */
        .contestant-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 15px; padding: 20px; }
        
        .c-card { background: white; border-radius: 12px; padding: 15px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05); cursor: pointer; position: relative; border: 2px solid transparent; transition: transform 0.1s; }
        .c-card:active { transform: scale(0.98); }
        .c-card img { width: 85px; height: 85px; border-radius: 50%; object-fit: cover; margin-bottom: 10px; border: 3px solid #f3f4f6; }
        
        .status-badge {
            position: absolute; top: 10px; right: 10px;
            padding: 4px 8px; border-radius: 6px;
            font-size: 10px; font-weight: 800; text-transform: uppercase;
            display: none; color: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .c-card.scored { border-color: var(--success); background: #f0fdf4; }
        .c-card.scored .status-badge.done { display: block; background: var(--success); }
        
        .c-card.partial { border-color: var(--wip); background: #fefce8; }
        .c-card.partial .status-badge.wip { display: block; background: var(--wip); }

        .c-number { font-size: 1.2rem; font-weight: 900; color: var(--gold); display: block; line-height: 1; margin-bottom: 4px; }
        .c-name { font-weight: bold; font-size: 0.9rem; color: var(--dark); display: block; line-height: 1.2; }
        .c-place { font-size: 0.75rem; color: #6b7280; display: block; margin-top: 4px; }

        /* Overlay */
        .scoring-overlay { background: white; position: fixed; top: 0; bottom: 0; left: 0; right: 0; z-index: 2000; padding: 20px; overflow-y: auto; display: none; }
        
        .score-header { display: flex; align-items: center; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px solid #e5e7eb; position: sticky; top: -20px; background: white; z-index: 10; padding-top: 10px; }
        .back-btn { font-size: 1.2rem; color: var(--dark); margin-right: 15px; cursor: pointer; padding: 10px; border-radius: 50%; background: #f3f4f6; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; }
        
        /* Criteria Item */
        .crit-item { background: white; padding: 20px 15px; border-radius: 12px; margin-bottom: 20px; border: 1px solid #e5e7eb; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .crit-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px; }
        .crit-title { font-weight: 800; font-size: 1rem; color: var(--dark); }
        .crit-max { font-size: 0.8rem; background: #e5e7eb; padding: 2px 8px; border-radius: 10px; color: #4b5563; font-weight: bold; }
        
        /* INPUT + SLIDER */
        .input-group { display: flex; align-items: center; gap: 15px; margin-top: 15px; }
        .score-input { width: 70px; padding: 10px; border: 2px solid #ddd; border-radius: 8px; font-size: 1.3rem; text-align: center; font-weight: bold; color: var(--dark); }
        .score-input:focus { border-color: var(--gold); outline: none; background: #fffbeb; }
        
        /* Custom Slider with Dynamic Fill & Fix */
        .score-slider { 
            flex-grow: 1; 
            height: 10px; 
            border-radius: 5px; 
            background: #d1d5db; 
            outline: none; 
            cursor: pointer;
            touch-action: none; /* CRITICAL: Prevents scroll interference */
        }
        .score-slider::-webkit-slider-thumb { -webkit-appearance: none; width: 24px; height: 24px; border-radius: 50%; background: white; border: 2px solid var(--gold); box-shadow: 0 2px 4px rgba(0,0,0,0.2); transition: transform 0.1s; }
        .score-slider::-webkit-slider-thumb:active { transform: scale(1.2); }

        /* Footer */
        .footer { position: fixed; bottom: 0; left: 0; width: 100%; background: white; padding: 15px 20px; box-shadow: 0 -4px 10px rgba(0,0,0,0.05); text-align: center; z-index: 2001; box-sizing: border-box; }
        .btn-submit { padding: 14px; border-radius: 10px; font-weight: bold; border: none; cursor: pointer; background: var(--success); color: white; width: 100%; font-size: 1rem; letter-spacing: 0.5px; box-shadow: 0 4px 6px rgba(5, 150, 105, 0.2); transition: 0.2s; }
        .btn-submit:disabled { background: #9ca3af; cursor: not-allowed; box-shadow: none; opacity: 0.7; }
        
        /* Utils */
        .hidden { display: none !important; }
        .logout-btn { color: white; text-decoration: none; font-size: 11px; background: #dc2626; padding: 6px 12px; border-radius: 6px; font-weight: bold; letter-spacing: 0.5px; }
        .input-error { border-color: #dc2626 !important; background: #fef2f2 !important; color: #dc2626; }
        .err-msg { color: #dc2626; font-size: 11px; font-weight: bold; margin-top: 5px; display: none; }

        #saveStatus { font-size: 11px; font-weight: bold; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.5px; }
        #saveStatus.saved { color: var(--success); }
        
        .waiting-screen { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 60vh; color: #6b7280; text-align: center; padding: 20px; }
        .waiting-icon { font-size: 40px; color: #d1d5db; margin-bottom: 20px; }
    </style>
</head>
<body>

<div class="header">
   <div>
        <div style="font-size: 10px; color: var(--gold); letter-spacing: 1.2px; font-weight: 800; text-transform: uppercase;">
            <?= htmlspecialchars($event_name) ?>
        </div>
        <div style="font-size: 16px; font-weight: 800; margin-top: 2px;">
            <?= htmlspecialchars($judge_name) ?>
        </div>
        <div style="font-size: 11px; opacity: 0.7; margin-top: 2px;">
            <?= $has_active_round ? htmlspecialchars($active['round_title']) : 'Standby Mode' ?>
        </div>
    </div>

    <div style="text-align: right; display:flex; flex-direction:column; align-items:flex-end; gap:5px;">
        <?php if ($has_active_round): ?>
            <div id="saveStatus">Saved</div>
        <?php endif; ?>
        <a href="logout.php" class="logout-btn" onclick="return confirm('Log out?')">EXIT</a>
    </div>
</div>

<?php if (!$has_active_round): ?>
    <div class="waiting-screen">
        <i class="fas fa-coffee waiting-icon"></i>
        <h2 style="margin:0 0 10px 0; color:var(--dark);">Waiting for Round</h2>
        <p style="font-size:14px; margin:0;">Please standby for the coordinator to start the next segment.</p>
        <button onclick="location.reload()" style="margin-top:25px; padding:10px 25px; background:white; border:1px solid #d1d5db; border-radius:20px; font-weight:bold; color:var(--dark); cursor:pointer;">
            <i class="fas fa-sync-alt"></i> Refresh
        </button>
    </div>
<?php else: ?>

    <div class="tabs">
        <?php foreach ($segments_data as $s): ?>
            <button class="tab" onclick="setSegment(<?= $s['id'] ?>)" id="tab-<?= $s['id'] ?>"><?= htmlspecialchars($s['title']) ?></button>
        <?php endforeach; ?>
    </div>

    <div class="contestant-grid" id="contestantGrid">
        <?php foreach ($contestants as $c): ?>
            <div class="c-card" id="card-<?= $c['id'] ?>" onclick="openScoring(<?= $c['id'] ?>)">
                <div class="status-badge done"><i class="fas fa-check"></i> DONE</div>
                <div class="status-badge wip">WIP</div>
                
                <img src="assets/uploads/contestants/<?= $c['photo'] ?>" onerror="this.src='assets/images/default_user.png'">
                <div class="c-number">#<?= $c['display_number'] ?></div>
                <div class="c-name"><?= htmlspecialchars($c['name']) ?></div>
                <div class="c-place"><?= htmlspecialchars($c['hometown']) ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="scoring-overlay" id="scoringOverlay">
        <div class="score-header">
            <div class="back-btn" onclick="closeScoring()"><i class="fas fa-arrow-left"></i></div>
            <div style="flex-grow:1;">
                <div id="viewName" style="font-weight:900; font-size:1.1rem; line-height:1.2;"></div>
                <div id="viewSegmentTitle" style="color: var(--gold); font-size: 0.85rem; font-weight: bold; text-transform: uppercase; margin-top:2px;"></div>
            </div>
        </div>

        <div id="criteriaContainer"></div>

        <div style="margin-top: 25px; padding-bottom: 100px;">
            <label style="font-weight: bold; display: block; margin-bottom: 8px; font-size: 0.9rem;">Private Notes (Optional):</label>
            <textarea id="segmentComment" class="score-input" style="height: 80px; width: 100%; text-align: left; font-weight: normal; font-size: 1rem;" placeholder="Type here..." oninput="handleInput(this)"></textarea>
        </div>
    </div>

    <div class="footer">
        <?php if (!$is_locked): ?>
            <button class="btn-submit" id="submitBtn" onclick="validateAndSubmit()" disabled>
                SUBMIT FINAL SCORES <i class="fas fa-paper-plane" style="margin-left:5px;"></i>
            </button>
        <?php else: ?>
            <button class="btn-submit" style="background:#d1d5db; color:#6b7280;" disabled>
                <i class="fas fa-lock"></i> SCORES LOCKED
            </button>
        <?php endif; ?>
    </div>

    <script>
    const segments = <?= json_encode($segments_data) ?>;
    const contestants = <?= json_encode($contestants) ?>;
    const drafts = { 
        scores: <?= json_encode($draft_scores) ?> || {}, 
        comments: <?= json_encode($draft_comments) ?> || {} 
    };
    const roundId = <?= $round_id ?>;
    const isLocked = <?= $is_locked ? 'true' : 'false' ?>; 
    
    let currentSegId = Object.keys(segments)[0];
    let activeCId = null;
    let saveTimeout;

    // --- Tab Switching ---
    function setSegment(id) {
        currentSegId = id;
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.getElementById('tab-' + id).classList.add('active');
        checkCompletion(); 
    }

    // --- Open Scoring Modal ---
    function openScoring(cid) {
        activeCId = cid;
        const cont = contestants.find(c => c.id == cid);
        const seg = segments[currentSegId];
        
        document.getElementById('viewName').innerText = `#${cont.display_number} ${cont.name}`;
        document.getElementById('viewSegmentTitle').innerText = seg.title;
        
        // 1. Determine if we should disable inputs
        const disabledAttr = isLocked ? 'disabled' : ''; 

        let html = '';
        if(seg.criteria && seg.criteria.length > 0) {
            seg.criteria.forEach(crit => {
                const val = (drafts.scores[cid] && drafts.scores[cid][crit.id]) ? drafts.scores[cid][crit.id] : 0;
                
                html += `
                    <div class="crit-item">
                        <div class="crit-top">
                            <span class="crit-title">${crit.title}</span>
                            <span class="crit-max">Max: ${crit.max_score}</span>
                        </div>
                        <div style="font-size:12px; color:#6b7280; margin-bottom:10px;">${crit.description || ''}</div>
                        
                        <div class="input-group">
                            <input type="range" class="score-slider sync-slider" 
                                   data-crit="${crit.id}" min="0" max="${crit.max_score}" step="1" 
                                   value="${val}" oninput="syncInputs(this, 'slider')" ${disabledAttr}>
                                   
                            <input type="number" class="score-input crit-input sync-num" 
                                   data-crit="${crit.id}" min="0" max="${crit.max_score}" step="0.01"
                                   value="${val}" oninput="syncInputs(this, 'number')" ${disabledAttr}>
                        </div>
                        <div class="err-msg"></div>
                    </div>`;
            });
        } else {
            html = '<p style="text-align:center; color:#999;">No criteria available.</p>';
        }
        
        document.getElementById('criteriaContainer').innerHTML = html;
        
        const commentBox = document.getElementById('segmentComment');
        commentBox.value = (drafts.comments[cid] && drafts.comments[cid][currentSegId]) ? drafts.comments[cid][currentSegId] : '';
        
        // 4. Disable the comment box too
        commentBox.disabled = isLocked; 
        
        // Initialize slider fills
        document.querySelectorAll('.sync-slider').forEach(s => updateSliderFill(s));

        document.getElementById('scoringOverlay').style.display = 'block';
    }

    // --- Sync Logic (Slider <-> Number) ---
    function syncInputs(el, source) {
        const container = el.closest('.input-group');
        const slider = container.querySelector('.sync-slider');
        const numInput = container.querySelector('.sync-num');
        
        if(source === 'slider') {
            numInput.value = el.value;
            updateSliderFill(slider);
            handleInput(numInput); 
        } else {
            if(el.value !== '' && !isNaN(el.value)) {
                slider.value = el.value;
                updateSliderFill(slider);
            }
            handleInput(el);
        }
    }

    // --- Dynamic Slider Gold Fill ---
    function updateSliderFill(slider) {
        const min = parseFloat(slider.min) || 0;
        const max = parseFloat(slider.max) || 100;
        const val = parseFloat(slider.value) || 0;
        const percent = ((val - min) / (max - min)) * 100;
        
        slider.style.background = `linear-gradient(to right, #F59E0B 0%, #F59E0B ${percent}%, #d1d5db ${percent}%, #d1d5db 100%)`;
    }

    // --- Validation & Saving ---
    function handleInput(el) {
        if(el.classList.contains('crit-input')) {
            const max = parseFloat(el.getAttribute('max'));
            let val = parseFloat(el.value);
            const msg = el.closest('.crit-item').querySelector('.err-msg');

            if (val > max) {
                el.classList.add('input-error');
                msg.innerText = "Value exceeds max (" + max + ")";
                msg.style.display = 'block';
            } else if (val < 0) {
                el.classList.add('input-error');
                msg.innerText = "Negative values not allowed";
                msg.style.display = 'block';
            } else {
                el.classList.remove('input-error');
                msg.style.display = 'none';
            }
        }
        syncLocalDraft(); 
        saveDraft();
    }

    function syncLocalDraft() {
        if (!activeCId) return;
        const scores = {};
        document.querySelectorAll('.crit-input').forEach(i => {
            if(i.value !== '') {
                let val = parseFloat(i.value);
                let max = parseFloat(i.getAttribute('max'));
                
                // STRICT SAVE: Only save valid numbers
                if (!isNaN(val) && val >= 0 && val <= max) {
                    scores[i.dataset.crit] = val;
                }
            }
        });
        const comment = document.getElementById('segmentComment').value;
        
        if(!drafts.scores[activeCId]) drafts.scores[activeCId] = {};
        Object.assign(drafts.scores[activeCId], scores); 
        
        if(!drafts.comments[activeCId]) drafts.comments[activeCId] = {};
        drafts.comments[activeCId][currentSegId] = comment;
    }

    function saveDraft(immediate = false) {
        if(!activeCId || <?= $is_locked ? 'true' : 'false' ?>) return;
        
        const statusEl = document.getElementById('saveStatus');
        statusEl.innerText = "Saving...";
        statusEl.classList.remove('saved');
        clearTimeout(saveTimeout);

        const performSave = async () => {
            const scores = drafts.scores[activeCId] || {};
            const comment = (drafts.comments[activeCId] && drafts.comments[activeCId][currentSegId]) || "";
            
            try {
                await fetch('../api/save_draft.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        round_id: roundId,
                        contestant_id: activeCId,
                        segment_id: currentSegId,
                        scores: scores,
                        comment: comment
                    })
                });
                statusEl.innerText = "Saved";
                statusEl.classList.add('saved');
                checkCompletion(); 
            } catch(e) { statusEl.innerText = "Error"; }
        };

        if (immediate) performSave();
        else saveTimeout = setTimeout(performSave, 800); 
    }

    function closeScoring() {
        if (activeCId) { syncLocalDraft(); saveDraft(true); }
        activeCId = null;
        document.getElementById('scoringOverlay').style.display = 'none';
        checkCompletion();
    }

    // --- CHECK COMPLETION (Fixed Logic: 0 is NOT done) ---
    function checkCompletion() {
        const seg = segments[currentSegId];
        if (!seg || !seg.criteria) return;

        let globalDone = true;

        contestants.forEach(c => {
            const card = document.getElementById('card-' + c.id);
            let filledCount = 0;
            let totalCount = seg.criteria.length;
            
            seg.criteria.forEach(crit => {
                let val = drafts.scores[c.id]?.[crit.id];
                // FIXED: Must be > 0 to count as filled
                if (val !== undefined && val !== "" && val !== null && parseFloat(val) > 0) {
                    filledCount++;
                }
            });

            card.classList.remove('scored', 'partial');
            
            // Only mark DONE if ALL criteria are filled > 0
            if (totalCount > 0 && filledCount === totalCount) {
                card.classList.add('scored');
            } else if (filledCount > 0) {
                card.classList.add('partial');
            }
        });

        // Global Check for Button
        Object.values(segments).forEach(s => {
            if (s.criteria) {
                s.criteria.forEach(crit => {
                    contestants.forEach(c => {
                        let val = drafts.scores[c.id]?.[crit.id];
                        if (val === undefined || val === "" || val === null || parseFloat(val) <= 0) {
                            globalDone = false;
                        }
                    });
                });
            }
        });

        const btn = document.getElementById('submitBtn');
        if(btn) btn.disabled = !globalDone;
    }

    function validateAndSubmit() {
        if (confirm("Confirm Final Submission?\n\nThis will lock your scores. You cannot edit them afterwards.")) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '../api/submit_scores.php';
            const input = document.createElement('input');
            input.type = 'hidden'; input.name = 'round_id'; input.value = roundId;
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        }
    }

    // Init
    if(Object.keys(segments).length > 0) {
        setSegment(currentSegId);
    }
    </script>
<?php endif; ?>

</body>
</html>