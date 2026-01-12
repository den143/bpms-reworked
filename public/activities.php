<?php
require_once __DIR__ . '/../app/core/guard.php';
requireLogin();
requireRole('Event Manager');
require_once __DIR__ . '/../app/config/database.php';

$manager_id = $_SESSION['user_id'];

// Get Active Event
$evt_stmt = $conn->prepare("SELECT id, title FROM events WHERE manager_id = ? AND status = 'Active' LIMIT 1");
$evt_stmt->bind_param("i", $manager_id);
$evt_stmt->execute();
$active_event = $evt_stmt->get_result()->fetch_assoc();

$activities = [];
$archived = [];
$view = isset($_GET['view']) ? $_GET['view'] : 'active';

if ($active_event) {
    $event_id = $active_event['id'];
    
    // FETCH ACTIVE
    $sql = "SELECT * FROM event_activities WHERE event_id = $event_id AND is_deleted = 0 ORDER BY activity_date ASC, start_time ASC";
    $activities = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

    // FETCH ARCHIVED
    $sql_arc = "SELECT * FROM event_activities WHERE event_id = $event_id AND is_deleted = 1 ORDER BY activity_date DESC";
    $archived = $conn->query($sql_arc)->fetch_all(MYSQLI_ASSOC);
}

// Determine Status Color
function getStatusColor($date, $start, $end) {
    $now = time();
    $start_ts = strtotime("$date $start");
    $end_ts = strtotime("$date $end");

    if ($now < $start_ts) return 'blue'; // Upcoming
    if ($now >= $start_ts && $now <= $end_ts) return 'green'; // Ongoing
    return 'gray'; // Completed
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Activities - BPMS</title>
    <link rel="stylesheet" href="./assets/css/style.css?v=8">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

        .activity-card { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); margin-bottom: 15px; display: flex; overflow: hidden; border: 1px solid #e5e7eb; }
        .date-badge { background: #f3f4f6; width: 80px; display: flex; flex-direction: column; justify-content: center; align-items: center; border-right: 1px solid #e5e7eb; padding: 10px; }
        .date-month { font-size: 12px; font-weight: bold; color: #ef4444; text-transform: uppercase; }
        .date-day { font-size: 24px; font-weight: bold; color: #1f2937; }
        
        .card-body { flex: 1; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        .act-title { font-size: 16px; font-weight: bold; color: #111827; margin-bottom: 4px; }
        .act-meta { font-size: 13px; color: #6b7280; display: flex; gap: 15px; align-items: center; }
        .act-meta i { width: 16px; text-align: center; }

        .status-dot { height: 8px; width: 8px; border-radius: 50%; display: inline-block; margin-right: 5px; }
        .bg-blue { background-color: #3b82f6; }
        .bg-green { background-color: #10b981; }
        .bg-gray { background-color: #9ca3af; }

        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background: white; padding: 25px; width: 450px; border-radius: 12px; }
        .form-row { display: flex; gap: 15px; }
        .form-group { margin-bottom: 15px; flex: 1; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; box-sizing: border-box; }
        .warning-box { background: #fffbeb; border-left: 4px solid #f59e0b; padding: 15px; margin-bottom: 15px; border-radius: 4px; color: #b45309; font-size: 14px; display: none; }
        .actions { display: flex; gap: 8px; }

        /* Mobile Adjustments */
        @media (max-width: 768px) {
            .card-body { flex-direction: column; align-items: flex-start; gap: 15px; }
            .act-meta { flex-direction: column; align-items: flex-start; gap: 5px; }
            .actions { width: 100%; justify-content: flex-start; }
        }
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
                    <div class="navbar-title">Manage Activities</div>
                </div>
            </div>
            
            <div class="container">
                <div id="toast-container" class="toast-container"></div>

                <?php if (!$active_event): ?>
                    <div style="text-align:center; padding: 40px; color: #6b7280;"><h2>No Active Event</h2></div>
                <?php else: ?>

                    <div class="page-header">
                        <h2 style="color:#111827;">Itinerary</h2>
                        <button class="btn-add" onclick="openModal('add')">+ Add Activity</button>
                    </div>

                    <div class="view-tabs">
                        <a href="?view=active" class="view-tab <?= $view === 'active' ? 'active' : '' ?>">Active Schedule</a>
                        <a href="?view=archive" class="view-tab <?= $view === 'archive' ? 'active' : '' ?>">Archived</a>
                    </div>

                    <?php 
                        $list = ($view === 'active') ? $activities : $archived;
                        if (empty($list)): 
                    ?>
                        <div style="text-align:center; padding:30px; background:white; border-radius:8px; color:#9ca3af;">
                            <?= ($view === 'active') ? 'No upcoming activities found.' : 'No archived activities.' ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($list as $act): 
                            $dateObj = new DateTime($act['activity_date']);
                            $startObj = new DateTime($act['start_time']);
                            $endObj = new DateTime($act['end_time']);
                            $color = getStatusColor($act['activity_date'], $act['start_time'], $act['end_time']);
                        ?>
                            <div class="activity-card">
                                <div class="date-badge">
                                    <span class="date-month"><?= $dateObj->format('M') ?></span>
                                    <span class="date-day"><?= $dateObj->format('d') ?></span>
                                </div>
                                <div class="card-body">
                                    <div>
                                        <div class="act-title">
                                            <span class="status-dot bg-<?= $color ?>"></span>
                                            <?= htmlspecialchars($act['title']) ?>
                                        </div>
                                        <div class="act-meta">
                                            <span><i class="far fa-clock"></i> <?= $startObj->format('g:i A') ?> - <?= $endObj->format('g:i A') ?></span>
                                            <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($act['venue']) ?></span>
                                        </div>
                                        <?php if($act['description']): ?>
                                            <div style="font-size:12px; color:#9ca3af; margin-top:4px;">
                                                <?= htmlspecialchars($act['description']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="actions">
                                        <?php if ($view === 'active'): ?>
                                            <button class="btn-icon" onclick='openEdit(<?= json_encode($act, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' title="Edit">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <a href="../api/activities.php?action=archive&id=<?= $act['id'] ?>" 
                                               class="btn-icon" style="color:#dc2626; border-color:#fee2e2;" 
                                               onclick="return confirm('Archive this activity?')" title="Archive">
                                                <i class="fas fa-archive"></i> Archive
                                            </a>
                                        <?php else: ?>
                                            <a href="../api/activities.php?action=restore&id=<?= $act['id'] ?>" 
                                               class="btn-icon" style="color:#059669; border-color:#d1fae5;" 
                                               title="Restore">
                                                <i class="fas fa-undo"></i> Restore
                                            </a>
                                            <a href="../api/activities.php?action=delete&id=<?= $act['id'] ?>" 
                                               class="btn-icon" style="color:#b91c1c; border-color:#fecaca;" 
                                               onclick="return confirm('Permanently delete this activity? This cannot be undone.')" title="Delete Permanently">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="activityModal" class="modal-overlay">
        <div class="modal-content">
            <h3 id="modalTitle">Add Activity</h3>
            
            <div id="warningBox" class="warning-box">
                <i class="fas fa-exclamation-triangle"></i> <span id="warningMsg"></span>
                <div style="margin-top:10px; text-align:right;">
                    <button type="button" onclick="confirmForceSave()" class="btn-add" style="font-size:12px; padding:5px 10px;">Proceed Anyway</button>
                </div>
            </div>

            <form action="../api/activities.php" method="POST" id="actForm">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="event_id" value="<?= $active_event['id'] ?? '' ?>">
                <input type="hidden" name="activity_id" id="act_id">
                <input type="hidden" name="force_save" id="force_save" value="0">

                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" id="a_title" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Venue</label>
                    <input type="text" name="venue" id="a_venue" class="form-control" placeholder="e.g. Main Hall" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="activity_date" id="a_date" class="form-control" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Start Time</label>
                        <input type="time" name="start_time" id="a_start" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>End Time</label>
                        <input type="time" name="end_time" id="a_end" class="form-control" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Description (Optional)</label>
                    <input type="text" name="description" id="a_desc" class="form-control">
                </div>

                <div style="text-align:right; margin-top:20px;">
                    <button type="button" onclick="closeModal('activityModal')" class="btn-icon" style="width:auto; padding:5px 15px; display:inline-block;">Cancel</button>
                    <button type="submit" class="btn-add">Save Schedule</button>
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
            document.getElementById('activityModal').style.display = 'flex';
            document.getElementById('warningBox').style.display = 'none';
            document.getElementById('force_save').value = "0";

            if(mode === 'add') {
                document.getElementById('modalTitle').innerText = 'Add Activity';
                document.getElementById('formAction').value = 'add';
                document.getElementById('actForm').reset();
            }
        }

        function openEdit(act) {
            openModal('edit');
            document.getElementById('modalTitle').innerText = 'Edit Activity';
            document.getElementById('formAction').value = 'update';
            document.getElementById('act_id').value = act.id;
            document.getElementById('a_title').value = act.title;
            document.getElementById('a_venue').value = act.venue;
            document.getElementById('a_date').value = act.activity_date;
            document.getElementById('a_start').value = act.start_time;
            document.getElementById('a_end').value = act.end_time;
            document.getElementById('a_desc').value = act.description;
        }

        function closeModal(id) { document.getElementById(id).style.display = 'none'; }

        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('warning')) {
            openModal('add');
            if (urlParams.has('title')) document.getElementById('a_title').value = urlParams.get('title');
            if (urlParams.has('venue')) document.getElementById('a_venue').value = urlParams.get('venue');
            if (urlParams.has('activity_date')) document.getElementById('a_date').value = urlParams.get('activity_date');
            if (urlParams.has('start_time')) document.getElementById('a_start').value = urlParams.get('start_time');
            if (urlParams.has('end_time')) document.getElementById('a_end').value = urlParams.get('end_time');
            if (urlParams.has('description')) document.getElementById('a_desc').value = urlParams.get('description');
            if (urlParams.has('action') && urlParams.get('action') === 'update') {
                 document.getElementById('formAction').value = 'update';
                 if (urlParams.has('activity_id')) document.getElementById('act_id').value = urlParams.get('activity_id');
            }
            document.getElementById('warningBox').style.display = 'block';
            document.getElementById('warningMsg').innerText = urlParams.get('warning');
        }

        function confirmForceSave() {
            document.getElementById('force_save').value = "1";
            document.getElementById('actForm').submit();
        }

        // Toast Notification Logic
        function showToast(message, type = 'success') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            const icon = type === 'success' ? '<i class="fas fa-check-circle"></i>' : '<i class="fas fa-exclamation-circle"></i>';
            toast.innerHTML = `${icon} <span>${message}</span>`;
            container.appendChild(toast);
            setTimeout(() => { toast.remove(); }, 3500);
        }

        if (urlParams.has('success') || urlParams.has('error')) {
            const msg = urlParams.get('success') || urlParams.get('error');
            const type = urlParams.has('success') ? 'success' : 'error';
            showToast(msg, type);
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    </script>

</body>
</html>