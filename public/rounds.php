<?php
require_once __DIR__ . '/../app/core/guard.php';
requireLogin();
requireRole('Event Manager');
require_once __DIR__ . '/../app/config/database.php';

$manager_id = $_SESSION['user_id'];

// 1. Get Active Event
// UPDATED: Column 'title' instead of 'name'
$evt_sql = "SELECT id, title FROM events WHERE manager_id = ? AND status = 'Active' LIMIT 1";
$evt_stmt = $conn->prepare($evt_sql);
$evt_stmt->bind_param("i", $manager_id);
$evt_stmt->execute();
$active_event = $evt_stmt->get_result()->fetch_assoc();

$rounds = [];
$next_order = 1;
$previous_top_n = "N/A";

if ($active_event) {
    $event_id = $active_event['id'];
    // UPDATED: Added is_deleted check
    $r_query = $conn->query("SELECT * FROM rounds WHERE event_id = $event_id AND is_deleted = 0 ORDER BY ordering ASC");
    $rounds = $r_query->fetch_all(MYSQLI_ASSOC);

    // Auto-Calculate Next Order for the "Add" modal
    if (!empty($rounds)) {
        $last_round = end($rounds);
        $next_order = $last_round['ordering'] + 1;
        $previous_top_n = $last_round['contestants_to_advance'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Rounds - BPMS</title>
    <link rel="stylesheet" href="./assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* --- Layout & Utilities --- */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .btn-add { background-color: #F59E0B; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; }
        
        /* --- Card Design --- */
        .round-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; border-left: 5px solid transparent; transition: transform 0.2s; }
        .round-card:hover { transform: translateX(5px); }
        
        /* Status Colors */
        .round-card.active { border-left-color: #059669; background: #ecfdf5; }
        .round-card.pending { border-left-color: #d1d5db; }
        .round-card.completed { border-left-color: #3b82f6; background: #eff6ff; }

        .round-info h3 { margin: 0 0 5px 0; font-size: 18px; color: #111827; }
        .round-meta { font-size: 13px; color: #6b7280; display: flex; gap: 15px; }
        .round-meta span { display: flex; align-items: center; gap: 5px; }

        /* Badges */
        .status-badge { padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .status-active { background: #d1fae5; color: #065f46; border: 1px solid #34d399; animation: pulse 2s infinite; }
        .status-pending { background: #f3f4f6; color: #6b7280; }
        .status-completed { background: #dbeafe; color: #1e40af; }

        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(5, 150, 105, 0.4); } 70% { box-shadow: 0 0 0 10px rgba(5, 150, 105, 0); } 100% { box-shadow: 0 0 0 0 rgba(5, 150, 105, 0); } }

        /* --- Buttons --- */
        .actions { display: flex; gap: 10px; align-items: center; }
        
        .btn-icon { background: white; border: 1px solid #d1d5db; width: 32px; height: 32px; border-radius: 4px; cursor: pointer; color: #4b5563; display: flex; justify-content: center; align-items: center; text-decoration: none; transition: 0.2s; }
        .btn-icon:hover { background: #f3f4f6; color: #111827; }
        .btn-icon:disabled { opacity: 0.5; cursor: not-allowed; }

        .btn-control { border: none; padding: 8px 16px; border-radius: 6px; font-size: 12px; cursor: pointer; font-weight: bold; display: flex; align-items: center; gap: 6px; transition: 0.2s; }
        .btn-start { background: #2563eb; color: white; }
        .btn-start:hover { background: #1d4ed8; }
        .btn-lock { background: #059669; color: white; }
        .btn-lock:hover { background: #047857; }
        .btn-stop { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .btn-stop:hover { background: #fecaca; }

        /* --- Modal --- */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background: white; padding: 25px; width: 450px; border-radius: 12px; }
        .form-group { margin-bottom: 15px; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; }
        .info-alert { background:#eff6ff; padding:10px; border-radius:6px; font-size:12px; color:#1e40af; margin-bottom:15px; border-left: 3px solid #3b82f6; }
    </style>
</head>
<body>

<div class="main-wrapper">
    <?php require_once __DIR__ . '/../app/views/partials/sidebar.php'; ?>
    <div class="content-area">
        <div class="navbar"><div class="navbar-title">Manage Rounds</div></div>
        
        <div class="container">
            <div id="toast-container" class="toast-container"></div>

            <?php if (!$active_event): ?>
                <div style="text-align:center; padding: 40px; color: #6b7280; background:white; border-radius:8px;">
                    <i class="fas fa-calendar-times" style="font-size:40px; margin-bottom:10px;"></i>
                    <h2>No Active Event</h2>
                    <p>Please go to Settings to create an event first.</p>
                </div>
            <?php else: ?>

                <div class="page-header">
                    <div>
                        <h2 style="color:#111827;">Competition Rounds</h2>
                        <p style="color:#6b7280; font-size:14px;">Event: <strong><?= htmlspecialchars($active_event['title']) ?></strong></p>
                    </div>
                    <button class="btn-add" onclick="openAddModal()"><i class="fas fa-plus"></i> Add Round</button>
                </div>

                <?php if (empty($rounds)): ?>
                    <div style="text-align:center; padding:40px; color:#9ca3af; background:white; border-radius:8px; border:1px dashed #d1d5db;">
                        <i class="fas fa-list-ol" style="font-size:30px; margin-bottom:10px;"></i>
                        <p>No rounds configured. Add your first round (e.g. "Preliminaries").</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($rounds as $r): ?>
                        <div class="round-card <?= strtolower($r['status']) ?>">
                            <div class="round-info">
                                <h3>
                                    <?= htmlspecialchars($r['title']) ?> 
                                    <span class="status-badge status-<?= strtolower($r['status']) ?>">
                                        <?php if($r['status'] == 'Active'): ?><i class="fas fa-circle" style="font-size:8px;"></i><?php endif; ?>
                                        <?= $r['status'] ?>
                                    </span>
                                </h3>
                                <div class="round-meta">
                                    <span><i class="fas fa-sort-numeric-down"></i> Sequence: #<?= $r['ordering'] ?></span>
                                    <span><i class="fas fa-filter"></i> 
                                        <?= ($r['advancement_rule'] === 'winner') ? 'Final Winner' : 'Top ' . $r['contestants_to_advance'] . ' Advance' ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="actions">
                                <?php if ($r['status'] === 'Pending'): ?>
                                    <button onclick="controlRound(<?= $r['id'] ?>, 'start')" class="btn-control btn-start">
                                        <i class="fas fa-play"></i> START
                                    </button>
                                    
                                    <button class="btn-icon" onclick='openEditModal(<?= json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' title="Edit">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <a href="../api/rounds.php?action=delete&id=<?= $r['id'] ?>" class="btn-icon" style="color:#dc2626;" onclick="return confirm('Delete this round?')" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </a>

                                <?php elseif ($r['status'] === 'Active'): ?>
                                    <button onclick="controlRound(<?= $r['id'] ?>, 'lock')" class="btn-control btn-lock">
                                        <i class="fas fa-gavel"></i> LOCK
                                    </button>
                                    <button onclick="controlRound(<?= $r['id'] ?>, 'stop')" class="btn-control btn-stop" title="Emergency Stop">
                                        <i class="fas fa-stop"></i> STOP
                                    </button>

                                <?php else: ?>
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <span style="color:#059669; font-weight:bold; font-size:12px;">
                                            <i class="fas fa-check-circle"></i> LOCKED
                                        </span>
                                        
                                        <button onclick="controlRound(<?= $r['id'] ?>, 'reopen')" class="btn-control" style="background:#fff; border:1px solid #d1d5db; color:#4b5563;">
                                            <i class="fas fa-unlock-alt"></i> Re-Open
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>
</div>

<div id="addRoundModal" class="modal-overlay">
    <div class="modal-content">
        <h3 id="modalTitle" style="margin-bottom:15px;">Add New Round</h3>
        
        <?php if (!empty($rounds) && $previous_top_n !== "N/A"): ?>
        <div id="limitAlert" class="info-alert">
            <i class="fas fa-info-circle"></i> Previous Round Limit: <strong>Top <?= $previous_top_n ?></strong>.
            <br>Your new "Top N" should technically be lower than this.
        </div>
        <?php endif; ?>

        <form action="../api/rounds.php" method="POST" id="roundForm">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="event_id" value="<?= $active_event['id'] ?? '' ?>">
            <input type="hidden" name="round_id" id="round_id">
            
            <div class="form-group">
                <label>Round Title</label>
                <input type="text" name="title" id="r_title" class="form-control" placeholder="e.g. Preliminary Round" required>
            </div>
            
            <div class="form-group" style="display:flex; gap:10px;">
                <div style="flex:1;">
                    <label>Sequence #</label>
                    <input type="number" name="ordering" id="r_order" class="form-control" value="<?= $next_order ?>" required>
                </div>
                <div style="flex:1;">
                    <label>Advancement Rule</label>
                    <select name="advancement_rule" id="r_rule" class="form-control" onchange="toggleAdvanceInput()">
                        <option value="top_n">Elimination (Top N)</option>
                        <option value="winner">Final (Declare Winner)</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group" id="advanceGroup">
                <label>How many advance to next round?</label>
                <input type="number" name="contestants_to_advance" id="r_advance" class="form-control" value="5">
            </div>
            
            <div style="text-align:right; margin-top:20px;">
                <button type="button" onclick="closeModal('addRoundModal')" style="padding:10px 20px; border:none; background:#e5e7eb; border-radius:6px; cursor:pointer; font-weight:600; color:#374151;">Cancel</button>
                <button type="submit" style="padding:10px 20px; border:none; background:#F59E0B; color:white; border-radius:6px; font-weight:bold; cursor:pointer; margin-left:10px;">Save Round</button>
            </div>
        </form>
    </div>
</div>

<script>
    // --- UI HELPERS ---
    function openAddModal() {
        document.getElementById('formAction').value = 'add';
        document.getElementById('modalTitle').innerText = 'Add New Round';
        document.getElementById('roundForm').reset();
        document.getElementById('round_id').value = ''; 
        document.getElementById('r_order').value = "<?= $next_order ?>";
        
        const alert = document.getElementById('limitAlert');
        if(alert) alert.style.display = 'block';

        toggleAdvanceInput();
        document.getElementById('addRoundModal').style.display = 'flex';
    }

    function openEditModal(round) {
        document.getElementById('formAction').value = 'update';
        document.getElementById('modalTitle').innerText = 'Edit Round';
        document.getElementById('round_id').value = round.id;
        document.getElementById('r_title').value = round.title;
        document.getElementById('r_order').value = round.ordering;
        document.getElementById('r_rule').value = round.advancement_rule;
        document.getElementById('r_advance').value = round.contestants_to_advance;
        
        const alert = document.getElementById('limitAlert');
        if(alert) alert.style.display = 'none'; // Don't nag during edit
        
        toggleAdvanceInput();
        document.getElementById('addRoundModal').style.display = 'flex';
    }

    function closeModal(id) { document.getElementById(id).style.display = 'none'; }

    function toggleAdvanceInput() {
        const rule = document.getElementById('r_rule').value;
        const group = document.getElementById('advanceGroup');
        group.style.display = (rule === 'winner') ? 'none' : 'block';
    }

    // --- TRAFFIC CONTROLLER LOGIC ---
    async function controlRound(id, action) {
        let msg = "";
        if(action === 'start') msg = "START THIS ROUND?\n\n- Opens scoring for Judges.\n- Checks concurrency and weights.";
        if(action === 'lock') msg = "LOCK & PROMOTE?\n\n- Finalizes scores.\n- Promotes winners to 'Qualified'.\n- Eliminates the rest.";
        if(action === 'stop') msg = "EMERGENCY STOP?\n\n- Resets round to Pending.\n- Only do this if you started by mistake.";
        if (action === 'reopen') msg = "RE-OPEN ROUND?\n\n- Status will change to ACTIVE.\n- You can adjust scores or advancement rules.\n- You must LOCK it again to finalize results.";

        if(!confirm(msg)) return;

        // Visual feedback
        const btn = event.target.closest('button');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        btn.disabled = true;

        const formData = new FormData();
        formData.append('action', action);
        formData.append('round_id', id);

        try {
            const req = await fetch('../api/rounds.php', { method: 'POST', body: formData });
            const res = await req.json();

            if(res.status === 'success') {
                alert(res.message);
                location.reload();
            } else {
                alert("⛔ ACTION BLOCKED:\n" + res.message);
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        } catch(e) {
            alert("Network Error: Could not reach server.");
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    }

    // --- TOAST NOTIFICATIONS ---
    function showToast(message, type = 'success') {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `<i class="fas fa-${type==='success'?'check':'exclamation'}-circle"></i> <span>${message}</span>`;
        container.appendChild(toast);
        setTimeout(() => { toast.remove(); }, 3500);
    }
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('success')) showToast(urlParams.get('success'), 'success');
    if (urlParams.has('error')) showToast(urlParams.get('error'), 'error');
    
    // Clean URL
    if (urlParams.has('success') || urlParams.has('error')) {
        const newUrl = window.location.pathname;
        window.history.replaceState({}, document.title, newUrl);
    }
</script>

</body>
</html>