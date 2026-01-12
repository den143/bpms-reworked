<?php
require_once __DIR__ . '/../app/core/guard.php';
require_once __DIR__ . '/../app/config/database.php';

requireLogin();
requireRole('Judge');

$judge_id = $_SESSION['user_id'];

// 1. Get Active Context (Event & Round)
// UPDATED: 'title' instead of 'name', added 'is_deleted' checks
$query = "SELECT r.id as round_id, r.title as round_title, e.id as event_id, e.title as event_name 
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

if (!$active) die("No active round found for your assigned event.");

$round_id = $active['round_id'];
$event_id = $active['event_id'];

// 2. Fetch Segments
$seg_q = "SELECT id, title, description FROM segments WHERE round_id = ? AND is_deleted = 0 ORDER BY ordering";
$stmt_s = $conn->prepare($seg_q);
$stmt_s->bind_param("i", $round_id);
$stmt_s->execute();
$segments_raw = $stmt_s->get_result()->fetch_all(MYSQLI_ASSOC);

// 3. Fetch Criteria
$crit_q = "SELECT id, segment_id, title, description, max_score 
           FROM criteria 
           WHERE segment_id IN (SELECT id FROM segments WHERE round_id = ?) 
           AND is_deleted = 0
           ORDER BY ordering";
$stmt_crit = $conn->prepare($crit_q);
$stmt_crit->bind_param("i", $round_id);
$stmt_crit->execute();
$criteria_raw = $stmt_crit->get_result()->fetch_all(MYSQLI_ASSOC);

$segments_data = [];
foreach ($segments_raw as $s) {
    $s['criteria'] = array_values(array_filter($criteria_raw, fn($c) => $c['segment_id'] == $s['id']));
    $segments_data[$s['id']] = $s;
}

// 4. Fetch Contestants
// UPDATED: Table 'event_contestants' (aliased as ec)
$cont_q = "SELECT u.id, u.name, ec.photo, ec.age, ec.hometown, ec.contestant_number
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

$contestants = [];
foreach ($contestants_res as $c) {
    // Use actual contestant number from DB
    $c['display_number'] = $c['contestant_number']; 
    $contestants[] = $c;
}

// 5. Fetch Existing Drafts
// Note: Assuming 'scores' table uses 'score_value' column based on previous code
$scores_res = $conn->query("SELECT contestant_id, criteria_id, score_value FROM scores WHERE judge_id = $judge_id AND round_id = $round_id");
$draft_scores = [];
if($scores_res) {
    while($r = $scores_res->fetch_assoc()) {
        $draft_scores[$r['contestant_id']][$r['criteria_id']] = $r['score_value'];
    }
}

// Check for Comments Table existence before querying
$draft_comments = [];
$check_tbl = $conn->query("SHOW TABLES LIKE 'segment_comments'");
if($check_tbl && $check_tbl->num_rows > 0) {
    $comm_res = $conn->query("SELECT contestant_id, segment_id, comment_text FROM segment_comments WHERE judge_id = $judge_id AND round_id = $round_id");
    if($comm_res) {
        while($r = $comm_res->fetch_assoc()) {
            $draft_comments[$r['contestant_id']][$r['segment_id']] = $r['comment_text'];
        }
    }
}

// Check Lock Status
$status_res = $conn->query("SELECT status FROM judge_round_status WHERE round_id = $round_id AND judge_id = $judge_id");
$is_locked = ($status_res && $row = $status_res->fetch_assoc()) ? ($row['status'] === 'Submitted') : false;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Judge Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --gold: #F59E0B; --dark: #111827; --success: #059669; }
        body { background: #f3f4f6; font-family: 'Segoe UI', sans-serif; margin: 0; padding-bottom: 90px; }
        
        .header { 
            background: var(--dark); color: white; padding: 15px; 
            position: sticky; top: 0; z-index: 1000; 
            display: flex; justify-content: space-between; align-items: center; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        .tabs { display: flex; overflow-x: auto; background: white; padding: 10px; gap: 10px; border-bottom: 1px solid #ddd; position: sticky; top: 60px; z-index: 999; }
        .tab { padding: 8px 18px; background: #eee; border-radius: 20px; font-weight: bold; cursor: pointer; white-space: nowrap; font-size: 0.85rem; border: none; transition: 0.2s; }
        .tab.active { background: var(--gold); color: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }

        .contestant-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px; padding: 15px; }
        .c-card { background: white; border-radius: 10px; padding: 12px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05); cursor: pointer; border: 2px solid transparent; transition: 0.2s; position: relative; }
        .c-card img { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; margin-bottom: 8px; border: 3px solid #f3f4f6; }
        .c-card.active { border-color: var(--gold); background: #fffbeb; }
        .c-card.scored { border-color: var(--success); }
        .c-card.scored::after { content: '\f00c'; font-family: "Font Awesome 6 Free"; font-weight: 900; position: absolute; top: 5px; right: 5px; background: var(--success); color: white; width: 20px; height: 20px; border-radius: 50%; font-size: 10px; display: flex; align-items: center; justify-content: center; }
        
        .c-info-name { font-weight: bold; font-size: 0.95rem; display: block; color: var(--dark); line-height: 1.2; }
        .c-info-sub { font-size: 0.75rem; color: #6b7280; display: block; margin-top: 4px; }

        .scoring-overlay { background: white; position: fixed; top: 120px; bottom: 80px; left: 0; right: 0; z-index: 900; padding: 20px; overflow-y: auto; display: none; }
        .crit-item { background: #f9fafb; padding: 15px; border-radius: 8px; margin-bottom: 15px; border: 1px solid #e5e7eb; }
        .crit-title { font-weight: bold; font-size: 1rem; color: var(--dark); display: flex; justify-content: space-between; }
        .crit-desc { font-size: 0.8rem; color: #6b7280; margin: 4px 0 10px 0; line-height: 1.4; }
        .score-input { width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 1.4rem; text-align: center; font-weight: bold; color: var(--dark); box-sizing: border-box; }
        .score-input:focus { border-color: var(--gold); outline: none; background: #fffbeb; }
        
        .footer { position: fixed; bottom: 0; width: 100%; background: white; padding: 15px; box-shadow: 0 -2px 10px rgba(0,0,0,0.1); text-align: center; display: flex; gap: 12px; justify-content: center; z-index: 1001; box-sizing: border-box; }
        .btn { padding: 12px 24px; border-radius: 8px; font-weight: bold; border: none; cursor: pointer; transition: 0.2s; font-size: 1rem; }
        .btn-success { background: var(--success); color: white; width: 100%; max-width: 320px; }
        .btn-back { background: #4b5563; color: white; }
        .hidden { display: none !important; }

        .logout-btn { color: white; text-decoration: none; font-size: 0.75rem; background: #dc2626; padding: 6px 12px; border-radius: 6px; font-weight: bold; display: flex; align-items: center; gap: 5px; }
        .logout-btn:hover { background: #b91c1c; }

        .crit-input.invalid { border-color: #dc2626 !important; background-color: #fef2f2 !important; }
        .error-hint { color: #dc2626; font-size: 0.75rem; margin-top: 4px; font-weight: bold; display: none; }
        
        /* Save Indicator */
        #saveStatus { font-size: 0.75rem; font-weight: bold; color: #9ca3af; transition: color 0.3s; }
        #saveStatus.saving { color: var(--gold); }
        #saveStatus.saved { color: var(--success); }
        #saveStatus.error { color: #dc2626; }
    </style>
</head>
<body>

<div class="header">
   <div class="header-title-group">
        <div style="font-size: 0.7rem; color: var(--gold); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 2px;">
            <?= htmlspecialchars($active['event_name']) ?>
        </div>
        <div style="font-weight: 800; font-size: 1.1rem; line-height: 1;">
            <?= htmlspecialchars($active['round_title']) ?>
        </div>
    </div>

    <div style="display: flex; align-items: center; gap: 15px;">
        <div id="saveStatus">All changes saved</div>
        <a href="logout.php" class="logout-btn" onclick="return confirm('Exit the judging panel?')">
            <i class="fas fa-sign-out-alt"></i> EXIT
        </a>
    </div>
</div>

<div class="tabs" id="segmentTabs">
    <?php foreach ($segments_data as $s): ?>
        <button class="tab" onclick="setSegment(<?= $s['id'] ?>)" id="tab-<?= $s['id'] ?>"><?= htmlspecialchars($s['title']) ?></button>
    <?php endforeach; ?>
</div>

<div class="contestant-grid" id="contestantGrid">
    <?php foreach ($contestants as $c): ?>
        <div class="c-card" id="card-<?= $c['id'] ?>" onclick="openScoring(<?= $c['id'] ?>)">
            <img src="assets/uploads/contestants/<?= $c['photo'] ?>" onerror="this.src='assets/images/default_user.png'">
            <span class="c-info-name">#<?= $c['display_number'] ?> <?= htmlspecialchars($c['name']) ?></span>
            <span class="c-info-sub"><?= htmlspecialchars($c['hometown']) ?></span>
        </div>
    <?php endforeach; ?>
</div>

<div class="scoring-overlay" id="scoringOverlay">
    <div style="margin-bottom: 20px; border-bottom: 2px solid var(--gold); padding-bottom: 12px;">
        <h2 id="viewName" style="margin:0; font-size: 1.4rem;"></h2>
        <div id="viewSegmentTitle" style="font-weight: 800; color: var(--gold); margin-top:4px; font-size: 0.9rem; text-transform: uppercase;"></div>
        <p id="viewSegmentDesc" style="font-size: 0.8rem; color: #6b7280; font-style: italic; margin-top: 5px;"></p>
    </div>

    <div id="criteriaContainer"></div>

    <div style="margin-top: 25px; padding-bottom: 20px;">
        <label style="font-weight: bold; display: block; margin-bottom: 8px; color: var(--dark);">Notes / Comments:</label>
        <textarea id="segmentComment" class="score-input" style="text-align: left; font-size: 1rem; height: 90px; font-weight: normal;" placeholder="Optional notes..." onchange="saveDraft()"></textarea>
    </div>
</div>

<div class="footer">
    <button class="btn btn-back hidden" id="backBtn" onclick="closeScoring()">← BACK</button>
    <?php if (!$is_locked): ?>
        <button class="btn btn-success" id="submitBtn" onclick="validateAndSubmit()">SUBMIT FINAL SCORES <i class="fas fa-paper-plane" style="margin-left:8px;"></i></button>
    <?php else: ?>
        <button class="btn" style="background:#d1d5db; color:#6b7280; cursor:not-allowed;" disabled>ROUND SUBMITTED <i class="fas fa-lock"></i></button>
    <?php endif; ?>
</div>

<script>
const segments = <?= json_encode($segments_data) ?>;
const contestants = <?= json_encode($contestants) ?>;
// Use empty objects if null to prevent JS errors
const drafts = { 
    scores: <?= json_encode($draft_scores) ?> || {}, 
    comments: <?= json_encode($draft_comments) ?> || {} 
};
const roundId = <?= $round_id ?>;
let currentSegId = Object.keys(segments)[0];
let activeCId = null;

function setSegment(id) {
    currentSegId = id;
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.getElementById('tab-' + id).classList.add('active');
    
    if(activeCId) {
        openScoring(activeCId); 
    } else {
        updateCardStatus();
    }
}

function openScoring(cid) {
    activeCId = cid;
    const cont = contestants.find(c => c.id == cid);
    const seg = segments[currentSegId];
    
    document.querySelectorAll('.c-card').forEach(c => c.classList.remove('active'));
    document.getElementById('card-' + cid).classList.add('active');
    
    document.getElementById('viewName').innerText = `#${cont.display_number} ${cont.name}`;
    document.getElementById('viewSegmentTitle').innerText = seg.title;
    document.getElementById('viewSegmentDesc').innerText = seg.description || '';
    
    let html = '';
    if(seg.criteria && seg.criteria.length > 0) {
        seg.criteria.forEach(crit => {
            const val = (drafts.scores[cid] && drafts.scores[cid][crit.id]) ? drafts.scores[cid][crit.id] : '';
            html += `
                <div class="crit-item">
                    <div class="crit-title">
                        ${crit.title}
                        <span style="font-size:0.8rem; color:#6b7280; font-weight:normal;">Max: ${crit.max_score}</span>
                    </div>
                    <div class="crit-desc">${crit.description || ''}</div>
                    <input type="number" step="0.01" class="score-input crit-input" 
                           data-crit="${crit.id}" min="0" max="${crit.max_score}" 
                           value="${val}" 
                           onkeyup="validateInput(this)" 
                           onchange="saveDraft()" 
                           <?= $is_locked ? 'readonly' : '' ?>>
                    <div class="error-hint">Value cannot exceed ${crit.max_score}</div>
                </div>`;
        });
    } else {
        html = '<p style="text-align:center; padding:20px; color:#6b7280;">No criteria set for this segment.</p>';
    }
    
    document.getElementById('criteriaContainer').innerHTML = html;
    document.getElementById('segmentComment').value = (drafts.comments[cid] && drafts.comments[cid][currentSegId]) ? drafts.comments[cid][currentSegId] : '';
    
    document.getElementById('contestantGrid').classList.add('hidden');
    document.getElementById('scoringOverlay').style.display = 'block';
    document.getElementById('backBtn').classList.remove('hidden');
    document.getElementById('submitBtn').classList.add('hidden');
    window.scrollTo(0, 0);
}

function validateInput(el) {
    const max = parseFloat(el.getAttribute('max'));
    let val = parseFloat(el.value);
    const hint = el.nextElementSibling;

    if (val > max) {
        el.classList.add('invalid');
        hint.style.display = 'block';
        // Optional: Reset to max immediately
        // el.value = max; 
    } else {
        el.classList.remove('invalid');
        hint.style.display = 'none';
    }
}

function closeScoring() {
    activeCId = null;
    document.querySelectorAll('.c-card').forEach(c => c.classList.remove('active'));
    document.getElementById('contestantGrid').classList.remove('hidden');
    document.getElementById('scoringOverlay').style.display = 'none';
    document.getElementById('backBtn').classList.add('hidden');
    document.getElementById('submitBtn').classList.remove('hidden');
    updateCardStatus();
}

function updateCardStatus() {
    contestants.forEach(c => {
        const card = document.getElementById('card-' + c.id);
        const seg = segments[currentSegId];
        // Check if all criteria in this segment have a score
        const isComplete = seg.criteria && seg.criteria.length > 0 && 
                           seg.criteria.every(crit => {
                               return drafts.scores[c.id] && 
                                      drafts.scores[c.id][crit.id] !== undefined && 
                                      drafts.scores[c.id][crit.id] !== "";
                           });
        
        if(isComplete) card.classList.add('scored');
        else card.classList.remove('scored');
    });
}

let saveTimeout;
function saveDraft() {
    if(!activeCId || <?= $is_locked ? 'true' : 'false' ?>) return;
    
    const statusEl = document.getElementById('saveStatus');
    statusEl.innerText = "Saving...";
    statusEl.className = "saving";

    clearTimeout(saveTimeout);
    saveTimeout = setTimeout(async () => {
        // Gather scores
        const scores = {};
        document.querySelectorAll('.crit-input').forEach(i => {
            if(i.value !== '') {
                let val = parseFloat(i.value);
                let max = parseFloat(i.getAttribute('max'));
                if(val > max) val = max; // Enforce cap on save
                scores[i.dataset.crit] = val;
            }
        });
        
        const comment = document.getElementById('segmentComment').value;
        
        // Update Local State
        if(!drafts.scores[activeCId]) drafts.scores[activeCId] = {};
        Object.assign(drafts.scores[activeCId], scores);
        
        if(!drafts.comments[activeCId]) drafts.comments[activeCId] = {};
        drafts.comments[activeCId][currentSegId] = comment;

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

            statusEl.innerText = "All changes saved";
            statusEl.className = "saved";
        } catch(e) { 
            statusEl.innerText = "Save Error!";
            statusEl.className = "error";
        }
    }, 1000); // 1 second debounce
}

function validateAndSubmit() {
    let missingCount = 0;
    
    // Check all contestants across all segments
    Object.values(segments).forEach(s => {
        if (s.criteria) {
            s.criteria.forEach(crit => {
                contestants.forEach(c => {
                    if (!drafts.scores[c.id] || 
                        drafts.scores[c.id][crit.id] === undefined || 
                        drafts.scores[c.id][crit.id] === "") {
                        missingCount++;
                    }
                });
            });
        }
    });

    if (missingCount > 0) {
        alert(`Incomplete: You are missing scores for ${missingCount} criteria items.`);
        return;
    }

    if (confirm("FINAL SUBMISSION:\nThis will lock your scores and you cannot edit them later.\n\nProceed?")) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '../api/submit_scores.php';

        const inputRound = document.createElement('input');
        inputRound.type = 'hidden';
        inputRound.name = 'round_id';
        inputRound.value = roundId;

        form.appendChild(inputRound);
        document.body.appendChild(form);
        form.submit();
    }
}

// Init
setSegment(currentSegId);
</script>

</body>
</html>