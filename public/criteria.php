<?php
// DEBUGGING ON
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../app/core/guard.php';
requireLogin();
requireRole('Event Manager');
require_once __DIR__ . '/../app/config/database.php';

$manager_id = $_SESSION['user_id'];

// 1. Get Active Event
$evt_sql = "SELECT id, name FROM events WHERE user_id = ? AND status = 'Active' LIMIT 1";
$evt_stmt = $conn->prepare($evt_sql);
$evt_stmt->bind_param("i", $manager_id);
$evt_stmt->execute();
$active_event = $evt_stmt->get_result()->fetch_assoc();

$rounds = [];
$active_round_id = isset($_GET['round_id']) ? (int)$_GET['round_id'] : 0;
$active_segments = [];
$total_round_percentage = 0;
$next_seg_order = 1; 

if ($active_event) {
    $event_id = $active_event['id'];
    $r_query = $conn->query("SELECT * FROM rounds WHERE event_id = $event_id ORDER BY ordering ASC");
    if ($r_query) $rounds = $r_query->fetch_all(MYSQLI_ASSOC);

    if ($active_round_id === 0 && !empty($rounds)) {
        $active_round_id = $rounds[0]['id'];
    }

    if ($active_round_id > 0) {
        $s_query = $conn->query("SELECT * FROM segments WHERE round_id = $active_round_id ORDER BY ordering ASC");
        if ($s_query) {
            $active_segments = $s_query->fetch_all(MYSQLI_ASSOC);
            
            // Calculate Next Segment Order
            if (!empty($active_segments)) {
                $last_seg = end($active_segments);
                $next_seg_order = $last_seg['ordering'] + 1;
            }

            foreach ($active_segments as &$seg) {
                $sid = $seg['id'];
                $c_query = $conn->query("SELECT * FROM criteria WHERE segment_id = $sid ORDER BY ordering ASC");
                $seg['criteria'] = $c_query ? $c_query->fetch_all(MYSQLI_ASSOC) : [];
                
                $total_round_percentage += $seg['weight_percentage'];
                $seg['total_points'] = 0;
                foreach ($seg['criteria'] as $c) { $seg['total_points'] += $c['max_score']; }
            }
            // [CRITICAL FIX] Break the reference to prevent data leaking in the HTML loop
            unset($seg);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Segments & Criteria - BPMS</title>
    <link rel="stylesheet" href="./assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Shared Styles */
        .btn-add { background-color: #F59E0B; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; }
        .btn-icon { background: white; border: 1px solid #d1d5db; width: 32px; height: 32px; border-radius: 4px; cursor: pointer; color: #4b5563; display: flex; justify-content: center; align-items: center; text-decoration: none; }
        .btn-icon:hover { background: #f3f4f6; color: #111827; }
        .btn-sm-primary { background: #3b82f6; color: white; border:none; padding:5px 10px; border-radius:4px; font-size:12px; cursor:pointer; }
        
        /* Layout */
        .tabs-wrapper { display: flex; gap: 5px; border-bottom: 2px solid #e5e7eb; margin-bottom: 25px; overflow-x: auto; }
        .tab-item { padding: 12px 25px; background: #f9fafb; border: 1px solid #e5e7eb; border-bottom: none; border-radius: 8px 8px 0 0; cursor: pointer; font-weight: 600; color: #6b7280; text-decoration: none; white-space: nowrap; }
        .tab-item.active { background: white; color: #F59E0B; border-color: #e5e7eb; border-bottom: 2px solid white; margin-bottom: -2px; }

        .status-bar { background: white; padding: 15px 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; border-left: 5px solid #d1d5db; }
        .status-bar.valid { border-left-color: #059669; background: #ecfdf5; }
        .status-bar.invalid { border-left-color: #dc2626; background: #fef2f2; }

        .segment-card { background: white; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 30px; overflow: hidden; border: 1px solid #e5e7eb; }
        .segment-header { background: #fff; padding: 20px; border-bottom: 1px solid #f3f4f6; display: flex; justify-content: space-between; align-items: flex-start; }
        .segment-title h3 { margin: 0; font-size: 18px; color: #111827; display:flex; align-items:center; }
        .weight-badge { background: #1f2937; color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; margin-left: 10px; }
        .segment-desc { color: #6b7280; font-size: 14px; margin-top: 5px; font-style: italic; display: block; }

        .criteria-table { width: 100%; border-collapse: collapse; }
        .criteria-table th { background: #f9fafb; padding: 12px 20px; text-align: left; font-size: 12px; color: #6b7280; text-transform: uppercase; }
        .criteria-table td { padding: 15px 20px; border-bottom: 1px solid #f3f4f6; color: #374151; font-size: 14px; vertical-align:middle; }
        .crit-desc { display: block; font-size: 13px; color: #9ca3af; margin-top: 4px; font-style: italic; }

        .actions { display: flex; gap: 5px; }

        /* Modal */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background: white; padding: 30px; width: 450px; border-radius: 12px; }
        .form-group { margin-bottom: 15px; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; }
    </style>
</head>
<body>

<div class="main-wrapper">
    <?php require_once __DIR__ . '/../app/views/partials/sidebar.php'; ?>
    <div class="content-area">
        <div class="navbar"><div class="navbar-title">Segments & Criteria</div></div>
        
        <div class="container">
            <div id="toast-container" class="toast-container"></div>

            <?php if (!$active_event): ?>
                <div style="text-align:center; padding: 40px; color: #6b7280;"><h2>No Active Event</h2></div>
            <?php else: ?>

                <?php if (empty($rounds)): ?>
                    <div style="text-align:center; padding:30px; background:white; border-radius:8px;">
                        <p>No rounds found. Please <a href="rounds.php" style="color:#2563eb; font-weight:bold;">Create a Round</a> first.</p>
                    </div>
                <?php else: ?>
                    <div class="tabs-wrapper">
                        <?php foreach ($rounds as $r): ?>
                            <a href="?round_id=<?= $r['id'] ?>" class="tab-item <?= ($r['id'] == $active_round_id) ? 'active' : '' ?>">
                                <?= htmlspecialchars($r['title']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <?php $is_valid = ($total_round_percentage == 100); ?>
                    <div class="status-bar <?= $is_valid ? 'valid' : 'invalid' ?>">
                        <div>
                            <strong style="display:block; font-size:16px;">Round Composition</strong>
                            <span style="font-size:13px; color:#6b7280;">Total Weight must be exactly 100%.</span>
                        </div>
                        <div style="text-align:right;">
                            <span style="font-size:24px; font-weight:bold; color: <?= $is_valid ? '#059669' : '#dc2626' ?>">
                                <?= $total_round_percentage ?>%
                            </span>
                        </div>
                    </div>

                    <div style="text-align:right; margin-bottom:20px;">
                        <button class="btn-add" onclick="openAddSegmentModal(<?= $next_seg_order ?>)">+ Add New Segment</button>
                    </div>

                    <?php if (empty($active_segments)): ?>
                        <div style="text-align:center; padding:40px; color:#9ca3af; border:2px dashed #e5e7eb; border-radius:8px;">
                            <p>No segments in this round yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($active_segments as $seg_row): // Renamed variable to prevent reference bugs ?>
                            <div class="segment-card">
                                <div class="segment-header">
                                    <div class="segment-title">
                                        <h3>
                                            <?= htmlspecialchars($seg_row['title']) ?>
                                            <span class="weight-badge"><?= $seg_row['weight_percentage'] ?>%</span>
                                        </h3>
                                        <?php if (!empty($seg_row['description'])): ?>
                                            <span class="segment-desc"><?= htmlspecialchars($seg_row['description']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="actions">
                                        <?php 
                                            $next_crit_order = 1;
                                            if (!empty($seg_row['criteria'])) {
                                                $last_c = end($seg_row['criteria']);
                                                $next_crit_order = $last_c['ordering'] + 1;
                                            }
                                        ?>
                                        <button class="btn-sm-primary" onclick="openAddCriteriaModal(<?= $seg_row['id'] ?>, <?= $next_crit_order ?>)">+ Criteria</button>
                                        
                                        <button class="btn-icon" onclick='openEditSegmentModal(<?= json_encode($seg_row) ?>)' title="Edit Segment">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        
                                        <form action="../api/criteria.php" method="POST" onsubmit="return confirm('Delete Segment?');" style="display:inline;">
                                            <input type="hidden" name="action" value="delete_segment">
                                            <input type="hidden" name="segment_id" value="<?= $seg_row['id'] ?>">
                                            <input type="hidden" name="round_id" value="<?= $active_round_id ?>">
                                            <button type="submit" class="btn-icon" style="color:#dc2626; border-color:#fee2e2;" title="Delete Segment">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                <table class="criteria-table">
                                    <thead><tr><th>Criteria Name</th><th>Max</th><th>Order</th><th>Action</th></tr></thead>
                                    <tbody>
                                        <?php if (empty($seg_row['criteria'])): ?>
                                            <tr><td colspan="4" style="text-align:center; padding:15px; color:#9ca3af;">No criteria.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($seg_row['criteria'] as $crit): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= htmlspecialchars($crit['title']) ?></strong>
                                                        <?php if (!empty($crit['description'])): ?>
                                                            <span class="crit-desc"><?= htmlspecialchars($crit['description']) ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?= $crit['max_score'] ?></td>
                                                    <td><?= $crit['ordering'] ?></td>
                                                    <td>
                                                        <div class="actions">
                                                            <button class="btn-icon" onclick='openEditCriteriaModal(<?= json_encode($crit) ?>)' title="Edit Criteria">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <form action="../api/criteria.php" method="POST" onsubmit="return confirm('Remove criteria?');" style="display:inline;">
                                                                <input type="hidden" name="action" value="delete_criteria">
                                                                <input type="hidden" name="criteria_id" value="<?= $crit['id'] ?>">
                                                                <input type="hidden" name="round_id" value="<?= $active_round_id ?>">
                                                                <button type="submit" class="btn-icon" style="color:#dc2626; border-color:#fee2e2;" title="Delete">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <tr style="background:#f9fafb; font-weight:bold; font-size:12px;">
                                                <td colspan="4" style="text-align:right;">Total: <?= $seg_row['total_points'] ?> / 100</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="segmentModal" class="modal-overlay">
    <div class="modal-content">
        <h3 id="segModalTitle">Add Segment</h3>
        <form action="../api/criteria.php" method="POST" id="segForm">
            <input type="hidden" name="action" id="segAction" value="add_segment">
            <input type="hidden" name="round_id" value="<?= $active_round_id ?>">
            <input type="hidden" name="segment_id" id="seg_id">
            
            <div class="form-group"><label>Title</label><input type="text" name="title" id="seg_title" class="form-control" required></div>
            <div class="form-group"><label>Description</label><input type="text" name="description" id="seg_desc" class="form-control"></div>
            <div class="form-group"><label>Weight (%)</label><input type="number" step="0.01" name="weight_percentage" id="seg_weight" class="form-control" required></div>
            <div class="form-group"><label>Order</label><input type="number" name="ordering" id="seg_order" class="form-control" value="1"></div>
            
            <div style="text-align:right;">
                <button type="button" onclick="closeModal('segmentModal')" class="btn-icon" style="width:auto; padding:5px 15px; display:inline-block;">Cancel</button>
                <button type="submit" class="btn-add" style="padding:8px 15px; font-size:14px;">Save</button>
            </div>
        </form>
    </div>
</div>

<div id="criteriaModal" class="modal-overlay">
    <div class="modal-content">
        <h3 id="critModalTitle">Add Criteria</h3>
        <form action="../api/criteria.php" method="POST" id="critForm">
            <input type="hidden" name="action" id="critAction" value="add_criteria">
            <input type="hidden" name="round_id" value="<?= $active_round_id ?>">
            <input type="hidden" name="segment_id" id="crit_seg_id">
            <input type="hidden" name="criteria_id" id="crit_id">
            
            <div class="form-group"><label>Title</label><input type="text" name="title" id="crit_title" class="form-control" required></div>
            <div class="form-group"><label>Description</label><input type="text" name="description" id="crit_desc" class="form-control"></div>
            <div class="form-group"><label>Max Score</label><input type="number" step="0.01" name="max_score" id="crit_max" class="form-control" value="50"></div>
            <div class="form-group"><label>Order</label><input type="number" name="ordering" id="crit_order" class="form-control" value="1"></div>
            
            <div style="text-align:right;">
                <button type="button" onclick="closeModal('criteriaModal')" class="btn-icon" style="width:auto; padding:5px 15px; display:inline-block;">Cancel</button>
                <button type="submit" class="btn-add" style="padding:8px 15px; font-size:14px;">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
    function closeModal(id) { document.getElementById(id).style.display = 'none'; }

    // SEGMENT MODAL LOGIC
    function openAddSegmentModal(suggestedOrder) {
        document.getElementById('segAction').value = 'add_segment';
        document.getElementById('segModalTitle').innerText = 'Add Segment';
        document.getElementById('segForm').reset();
        document.getElementById('seg_order').value = suggestedOrder; 
        document.getElementById('segmentModal').style.display = 'flex';
    }
    
    function openEditSegmentModal(seg) {
        document.getElementById('segAction').value = 'update_segment';
        document.getElementById('segModalTitle').innerText = 'Edit Segment';
        document.getElementById('seg_id').value = seg.id;
        document.getElementById('seg_title').value = seg.title;
        document.getElementById('seg_desc').value = seg.description;
        document.getElementById('seg_weight').value = seg.weight_percentage;
        document.getElementById('seg_order').value = seg.ordering;
        document.getElementById('segmentModal').style.display = 'flex';
    }

    // CRITERIA MODAL LOGIC
    function openAddCriteriaModal(segId, suggestedOrder) {
        document.getElementById('critAction').value = 'add_criteria';
        document.getElementById('critModalTitle').innerText = 'Add Criteria';
        document.getElementById('critForm').reset();
        document.getElementById('crit_seg_id').value = segId;
        document.getElementById('crit_order').value = suggestedOrder; 
        document.getElementById('criteriaModal').style.display = 'flex';
    }

    function openEditCriteriaModal(crit) {
        document.getElementById('critAction').value = 'update_criteria';
        document.getElementById('critModalTitle').innerText = 'Edit Criteria';
        document.getElementById('crit_id').value = crit.id;
        document.getElementById('crit_seg_id').value = crit.segment_id; 
        
        document.getElementById('crit_title').value = crit.title;
        document.getElementById('crit_desc').value = crit.description;
        document.getElementById('crit_max').value = crit.max_score;
        document.getElementById('crit_order').value = crit.ordering;
        document.getElementById('criteriaModal').style.display = 'flex';
    }

    // Toast Logic
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('success') || urlParams.has('error')) {
        const msg = urlParams.get('success') || urlParams.get('error');
        const type = urlParams.has('success') ? 'success' : 'error';
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerText = msg;
        container.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
        window.history.replaceState({}, document.title, window.location.pathname + "?round_id=<?= $active_round_id ?>");
    }
</script>

</body>
</html>