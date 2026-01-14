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
$evt_sql = "SELECT id, title FROM events WHERE manager_id = ? AND status = 'Active' LIMIT 1";
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
    
    // UPDATED: Added is_deleted check
    $r_query = $conn->query("SELECT * FROM rounds WHERE event_id = $event_id AND is_deleted = 0 ORDER BY ordering ASC");
    if ($r_query) $rounds = $r_query->fetch_all(MYSQLI_ASSOC);

    if ($active_round_id === 0 && !empty($rounds)) {
        $active_round_id = $rounds[0]['id'];
    }

    if ($active_round_id > 0) {
        $s_query = $conn->query("SELECT * FROM segments WHERE round_id = $active_round_id AND is_deleted = 0 ORDER BY ordering ASC");
        if ($s_query) {
            $active_segments = $s_query->fetch_all(MYSQLI_ASSOC);
            
            // Calculate Next Segment Order
            if (!empty($active_segments)) {
                $last_seg = end($active_segments);
                $next_seg_order = $last_seg['ordering'] + 1;
            }

            foreach ($active_segments as &$seg) {
                $sid = $seg['id'];
                // UPDATED: Added is_deleted check
                $c_query = $conn->query("SELECT * FROM criteria WHERE segment_id = $sid AND is_deleted = 0 ORDER BY ordering ASC");
                $seg['criteria'] = $c_query ? $c_query->fetch_all(MYSQLI_ASSOC) : [];
                
                // FIX: Use correct DB column 'weight_percent'
                $total_round_percentage += $seg['weight_percent'];
                
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Segments & Criteria - BPMS</title>
    <link rel="stylesheet" href="./assets/css/style.css?v=7">
    <link rel="stylesheet" href="./assets/fontawesome/css/all.min.css">
</head>
<body>

    <div id="sidebarOverlay" class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <div class="main-wrapper">
        <?php require_once __DIR__ . '/../app/views/partials/sidebar.php'; ?>
        
        <div class="content-area">
            <div class="navbar">
                <div style="display:flex; align-items:center; gap: 15px;">
                    <button class="menu-toggle" onclick="toggleSidebar()">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="navbar-title">Segments & Criteria</div>
                </div>
            </div>
            
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
                            <?php foreach ($active_segments as $seg_row): ?>
                                <div class="segment-card">
                                    <div class="segment-header">
                                        <div class="segment-title">
                                            <h3>
                                                <?= htmlspecialchars($seg_row['title']) ?>
                                                <span class="weight-badge"><?= $seg_row['weight_percent'] ?>%</span>
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
                                                                <button class="btn-icon" onclick='openEditCriteriaModal(<?= json_encode($crit, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' title="Edit Criteria">
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
                
                <div class="form-group"><label>Title</label><input type="text" name="title" placeholder="e.g. Evening Gown" id="seg_title" class="form-control" required></div>
                <div class="form-group"><label>Brief description</label><input type="text" name="description" placeholder="e.g. Judges elegance, poise, and overall presentation" id="seg_desc" class="form-control"></div>
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
                
                <div class="form-group"><label>Title</label><input type="text" name="title" placeholder="e.g. Coordination" id="crit_title" class="form-control" required></div>
                <div class="form-group"><label>Description</label><input type="text" name="description" placeholder="e.g. Precision and harmony of movements" id="crit_desc" class="form-control"></div>
                <div class="form-group"><label>Max Score</label><input type="number" placeholder="e.g. 50" step="0.01" min="0.01" name="max_score" id="crit_max" class="form-control"></div>
                <div class="form-group"><label>Order</label><input type="number" name="ordering" id="crit_order" class="form-control" value="1"></div>
                
                <div style="text-align:right;">
                    <button type="button" onclick="closeModal('criteriaModal')" class="btn-icon" style="width:auto; padding:5px 15px; display:inline-block;">Cancel</button>
                    <button type="submit" class="btn-add" style="padding:8px 15px; font-size:14px;">Save</button>
                </div>
            </form>
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
            
            // FIX: Map correct DB column 'weight_percent' to input
            document.getElementById('seg_weight').value = seg.weight_percent;
            
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
        function showToast(message, type = 'success') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `<i class="fas fa-${type==='success'?'check':'exclamation'}-circle"></i> <span>${message}</span>`;
            container.appendChild(toast);
            setTimeout(() => { toast.remove(); }, 3500);
        }

        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('success') || urlParams.has('error')) {
            const msg = urlParams.get('success') || urlParams.get('error');
            const type = urlParams.has('success') ? 'success' : 'error';
            showToast(msg, type);
            // Clean URL but keep round_id
            window.history.replaceState({}, document.title, window.location.pathname + "?round_id=<?= $active_round_id ?>");
        }
    </script>

</body>
</html>