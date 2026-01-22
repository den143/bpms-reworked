<?php
require_once __DIR__ . '/../app/core/guard.php';
requireLogin();
requireRole('Event Manager');
require_once __DIR__ . '/../app/config/database.php';

$manager_id = $_SESSION['user_id'];

// 1. Get Active Event
$evt_stmt = $conn->prepare("SELECT id, title FROM events WHERE manager_id = ? AND status = 'Active' LIMIT 1");
$evt_stmt->bind_param("i", $manager_id);
$evt_stmt->execute();
$active_event = $evt_stmt->get_result()->fetch_assoc();

$awards = [];
$archived = [];
$segments = [];
$rounds = [];
$view = isset($_GET['view']) ? $_GET['view'] : 'active';

if ($active_event) {
    $event_id = $active_event['id'];

    // FIX: Fetch Active Awards (Status = Active AND Not Deleted)
    $sql = "SELECT * FROM awards 
            WHERE event_id = $event_id 
            AND status = 'Active' 
            AND is_deleted = 0 
            ORDER BY category_type ASC, title ASC";
    $awards = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

    // FIX: Fetch Archived Awards (Status = Inactive AND Not Deleted)
    $sql_arc = "SELECT * FROM awards 
                WHERE event_id = $event_id 
                AND status = 'Inactive' 
                AND is_deleted = 0 
                ORDER BY category_type ASC";
    $archived = $conn->query($sql_arc)->fetch_all(MYSQLI_ASSOC);

    // Fetch Segments (For Dropdown)
    // FIX APPLIED: Added check for 's.is_deleted = 0' AND 'r.is_deleted = 0'
    $seg_sql = "SELECT s.id, s.title, r.title as round_title 
                FROM segments s 
                JOIN rounds r ON s.round_id = r.id 
                WHERE r.event_id = $event_id 
                AND s.is_deleted = 0 
                AND r.is_deleted = 0 
                ORDER BY r.ordering, s.ordering";
    $segments = $conn->query($seg_sql)->fetch_all(MYSQLI_ASSOC);

    // Fetch Rounds (For Dropdown)
    // FIX APPLIED: Added 'AND is_deleted = 0' to prevent deleted rounds from showing up
    $rnd_sql = "SELECT id, title FROM rounds 
                WHERE event_id = $event_id 
                AND is_deleted = 0 
                ORDER BY ordering";
    $rounds = $conn->query($rnd_sql)->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Awards - BPMS</title>
    <link rel="stylesheet" href="./assets/css/style.css?v=9">
    <link rel="stylesheet" href="./assets/fontawesome/css/all.min.css">
    <style>
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .btn-add { background-color: #F59E0B; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; }
        
        .btn-icon { 
            background: white; border: 1px solid #d1d5db; border-radius: 6px; cursor: pointer; 
            color: #4b5563; display: inline-flex; justify-content: center; align-items: center; 
            text-decoration: none; padding: 6px 12px; width: auto; gap: 8px; 
            font-size: 13px; font-weight: 500; transition: all 0.2s; 
        }
        .btn-icon:hover { background: #f3f4f6; color: #111827; }

        .view-tabs { display: flex; gap: 10px; margin-bottom: 20px; }
        .view-tab { padding: 8px 16px; border-radius: 20px; font-size: 13px; text-decoration: none; color: #6b7280; background: #f3f4f6; }
        .view-tab.active { background: #1f2937; color: white; }

        /* Award Card Styles */
        .award-card { background: white; border-radius: 8px; padding: 15px; margin-bottom: 10px; border: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; }
        .award-info h3 { margin: 0 0 5px 0; font-size: 16px; color: #111827; display: flex; align-items: center; gap: 10px; }
        
        .badge { padding: 2px 8px; border-radius: 4px; font-size: 11px; text-transform: uppercase; font-weight: bold; }
        .badge-minor { background: #e0f2fe; color: #0284c7; }
        .badge-major { background: #fef3c7; color: #d97706; }

        .manual-tag, .audience-tag, .smart-tag { font-size: 12px; display: inline-flex; align-items: center; gap: 5px; padding: 4px 8px; border-radius: 4px; margin-top: 5px; }
        .manual-tag { background: #f3f4f6; color: #4b5563; }
        .audience-tag { background: #fae8ff; color: #86198f; }
        .smart-tag { background: #dcfce7; color: #15803d; }

        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background: white; padding: 25px; width: 450px; border-radius: 12px; }
        .form-group { margin-bottom: 15px; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; box-sizing: border-box; }
    </style>
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
                    <div class="navbar-title">Manage Awards</div>
                </div>
            </div>
            
            <div class="container">
                <div id="toast-container" class="toast-container"></div>

                <?php if (!$active_event): ?>
                    <div style="text-align:center; padding: 40px; color: #6b7280;"><h2>No Active Event</h2></div>
                <?php else: ?>

                    <div class="page-header">
                        <h2 style="color:#111827;">Awards List</h2>
                        <button class="btn-add" onclick="openModal('add')">+ Create Award</button>
                    </div>

                    <div class="view-tabs">
                        <a href="?view=active" class="view-tab <?= $view === 'active' ? 'active' : '' ?>">Active Awards</a>
                        <a href="?view=archive" class="view-tab <?= $view === 'archive' ? 'active' : '' ?>">Archived</a>
                    </div>

                    <?php 
                        $list = ($view === 'active') ? $awards : $archived;
                        if (empty($list)): 
                    ?>
                        <div style="text-align:center; padding:30px; background:white; border-radius:8px; color:#9ca3af;">
                            <?= ($view === 'active') ? 'No awards created yet.' : 'No archived awards.' ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($list as $aw): ?>
                            <div class="award-card">
                                <div class="award-info">
                                    <h3>
                                        <?= htmlspecialchars($aw['title']) ?>
                                        <span class="badge badge-<?= strtolower($aw['category_type']) ?>"><?= $aw['category_type'] ?></span>
                                    </h3>
                                    
                                    <?php if ($aw['selection_method'] === 'Manual'): ?>
                                        <div class="manual-tag"><i class="fas fa-hand-pointer"></i> Manual Selection</div>
                                    <?php elseif ($aw['selection_method'] === 'Audience_Vote'): ?>
                                        <div class="audience-tag"><i class="fas fa-users"></i> Audience Vote (People's Choice)</div>
                                    <?php else: ?>
                                        <div class="smart-tag">
                                            <i class="fas fa-robot"></i> 
                                            Auto-calculated from <?= str_replace('_', ' ', $aw['selection_method']) ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if($aw['description']): ?>
                                        <div style="font-size:13px; color:#9ca3af; margin-top:3px;"><?= htmlspecialchars($aw['description']) ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="actions" style="display:flex; gap:5px;">
                                    <?php if ($view === 'active'): ?>
                                        <button class="btn-icon" onclick='openEdit(<?= json_encode($aw, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' title="Edit">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <a href="../api/awards.php?action=archive&id=<?= $aw['id'] ?>" 
                                           class="btn-icon" style="color:#dc2626; border-color:#fee2e2;" 
                                           onclick="return confirm('Archive this award?')" title="Archive">
                                            <i class="fas fa-archive"></i> Archive
                                        </a>
                                    <?php else: ?>
                                        <a href="../api/awards.php?action=restore&id=<?= $aw['id'] ?>" 
                                           class="btn-icon" style="color:#059669; border-color:#d1fae5;" 
                                           title="Restore">
                                            <i class="fas fa-undo"></i> Restore
                                        </a>
                                        <a href="../api/awards.php?action=delete&id=<?= $aw['id'] ?>" 
                                           class="btn-icon" style="color:#b91c1c; border-color:#fecaca;" 
                                           onclick="return confirm('Permanently delete this award? This cannot be undone.')" title="Delete Permanently">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="awardModal" class="modal-overlay">
        <div class="modal-content">
            <h3 id="modalTitle">Create Award</h3>
            <form action="../api/awards.php" method="POST" id="awardForm">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="event_id" value="<?= $active_event['id'] ?? '' ?>">
                <input type="hidden" name="award_id" id="award_id">

                <div class="form-group">
                    <label>Award Title</label>
                    <input type="text" name="title" id="a_title" class="form-control" placeholder="e.g. Best in Swimsuit" required>
                </div>
                
                <div class="form-group">
                    <label>Category Type</label>
                    <select name="category_type" id="a_type" class="form-control">
                        <option value="Minor">Minor / Special Award</option>
                        <option value="Major">Major Title (Winner/Runner-up)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Winner Selection Method</label>
                    <select name="selection_method" id="a_source" class="form-control" onchange="toggleSourceDropdown()">
                        <option value="Manual">Manual Selection (Judge Vote/Deliberation)</option>
                        <option value="Audience_Vote">Audience Vote (People's Choice)</option> 
                        <option value="Highest_Segment">Highest Score in a Specific Segment</option>
                        <option value="Highest_Round">Highest Score in a Specific Round</option>
                    </select>
                </div>

                <div class="form-group" id="group_segment" style="display:none; background:#f9fafb; padding:10px; border-radius:6px;">
                    <label style="font-size:12px; font-weight:bold; color:#059669;">Select Segment Source:</label>
                    <select name="linked_segment_id" id="a_segment_id" class="form-control">
                        <?php foreach ($segments as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= $s['round_title'] ?> - <?= $s['title'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" id="group_round" style="display:none; background:#f9fafb; padding:10px; border-radius:6px;">
                    <label style="font-size:12px; font-weight:bold; color:#059669;">Select Round Source:</label>
                    <select name="linked_round_id" id="a_round_id" class="form-control">
                        <?php foreach ($rounds as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= $r['title'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <input type="text" name="description" id="a_desc" class="form-control">
                </div>

                <div style="text-align:right; margin-top:20px;">
                    <button type="button" onclick="closeModal('awardModal')" class="btn-icon" style="width:auto; padding:5px 15px; display:inline-block;">Cancel</button>
                    <button type="submit" class="btn-add">Save Award</button>
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

        function openModal(mode) {
            document.getElementById('awardModal').style.display = 'flex';
            if(mode === 'add') {
                document.getElementById('modalTitle').innerText = 'Create Award';
                document.getElementById('formAction').value = 'add';
                document.getElementById('awardForm').reset();
                toggleSourceDropdown();
            }
        }

        function openEdit(aw) {
            openModal('edit');
            document.getElementById('modalTitle').innerText = 'Edit Award';
            document.getElementById('formAction').value = 'update';
            document.getElementById('award_id').value = aw.id;
            document.getElementById('a_title').value = aw.title;
            document.getElementById('a_type').value = aw.category_type;
            document.getElementById('a_desc').value = aw.description;
            
            document.getElementById('a_source').value = aw.selection_method;
            toggleSourceDropdown(); 

            // Populate FKs
            if(aw.selection_method === 'Highest_Segment') document.getElementById('a_segment_id').value = aw.linked_segment_id;
            if(aw.selection_method === 'Highest_Round') document.getElementById('a_round_id').value = aw.linked_round_id;
        }

        function closeModal(id) { document.getElementById(id).style.display = 'none'; }

        function toggleSourceDropdown() {
            const val = document.getElementById('a_source').value;
            document.getElementById('group_segment').style.display = (val === 'Highest_Segment') ? 'block' : 'none';
            document.getElementById('group_round').style.display = (val === 'Highest_Round') ? 'block' : 'none';
        }

        // Toast Logic
        function showToast(message, type = 'success') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            const icon = type === 'success' ? '<i class="fas fa-check-circle"></i>' : '<i class="fas fa-exclamation-circle"></i>';
            toast.innerHTML = `${icon} <span>${message}</span>`;
            container.appendChild(toast);
            setTimeout(() => { toast.remove(); }, 3500);
        }

        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('success') || urlParams.has('error')) {
            const msg = urlParams.get('success') || urlParams.get('error');
            const type = urlParams.has('success') ? 'success' : 'error';
            showToast(msg, type);
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    </script>

</body>
</html>