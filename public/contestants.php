<?php
// public/contestants.php
require_once __DIR__ . '/../app/core/guard.php';
requireLogin();
requireRole(['Event Manager', 'Contestant Manager']);
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/models/Contestant.php';

$view = $_GET['view'] ?? 'active';
$search = trim($_GET['search'] ?? '');
$my_id = $_SESSION['user_id'];
$my_role = $_SESSION['role'];

// 1. Configure Tabs/View
if ($view === 'pending') {
    $status_filter = 'Pending';
    $page_title = "Pending Applications";
} elseif ($view === 'archived') {
    $status_filter = 'Inactive';
    $page_title = "Archived Contestants";
} else {
    $status_filter = 'Active';
    $page_title = "Official Candidates";
}

// 2. Fetch Contestants based on Role
// logic: Managers see their events; Staff see events they are assigned to.
if ($my_role === 'Event Manager') {
    $contestants = Contestant::getAllByManager($my_id, $status_filter, $search);
    
    // Fetch Events for the Add Modal (Manager owns them)
    $evt_stmt = $conn->prepare("SELECT id, title FROM events WHERE manager_id = ? AND status = 'Active'");
    $evt_stmt->bind_param("i", $my_id);
} else {
    // Contestant Manager / Staff
    $contestants = Contestant::getAllByOrganizer($my_id, $status_filter, $search);

    // Fetch Events for the Add Modal (Staff is assigned to them)
    $evt_stmt = $conn->prepare("
        SELECT e.id, e.title 
        FROM events e
        JOIN event_teams et ON e.id = et.event_id
        WHERE et.user_id = ? AND et.status = 'Active' AND et.is_deleted = 0 AND e.status = 'Active'
    ");
    $evt_stmt->bind_param("i", $my_id);
}

$evt_stmt->execute();
$my_events = $evt_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Contestants - BPMS</title>
    <link rel="stylesheet" href="./assets/css/style.css?v=4">
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
                    <div class="navbar-title">Register Contestant</div>
                </div>
            </div>

            <div class="container">
                <div id="toast-container" class="toast-container"></div>

                <div class="page-header">
                    <h2>Manage Contestants</h2>
                    <div class="header-actions">
                        <?php if ($view === 'archived'): ?>
                            <a href="?view=active" class="btn-secondary">← Back to List</a>
                        <?php else: ?>
                            <a href="?view=archived" class="btn-secondary"><i class="fas fa-archive"></i> View Archived</a>
                        <?php endif; ?>
                        
                        <button class="btn-add" onclick="openModal('addModal')"><i class="fas fa-plus"></i> Manually Add</button>
                    </div>
                </div>

                <?php if ($view !== 'archived'): ?>
                <div class="tabs">
                    <a href="?view=active" class="tab-link <?= $view === 'active' ? 'active' : '' ?>">Official Candidates</a>
                    <a href="?view=pending" class="tab-link <?= $view === 'pending' ? 'active' : '' ?>">Pending Applications</a>
                </div>
                <?php else: ?>
                    <div style="margin-bottom: 20px; font-size: 14px; color: #6b7280;">
                        Showing <strong>Archived</strong> contestants. Use restore to bring them back or delete to remove completely.
                    </div>
                <?php endif; ?>

                <form method="GET" action="contestants.php" class="search-container">
                    <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
                    <input type="text" name="search" class="search-input" placeholder="Search by name or hometown..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn-search"><i class="fas fa-search"></i> Search</button>
                    <?php if (!empty($search)): ?>
                        <a href="contestants.php?view=<?= $view ?>" class="btn-reset">Reset</a>
                    <?php endif; ?>
                </form>

                <?php if (empty($contestants)): ?>
                    <div style="text-align:center; padding:50px; color:#9ca3af; background:white; border-radius:8px; border: 1px dashed #d1d5db;">
                        <i class="fas fa-folder-open" style="font-size:30px; margin-bottom:10px;"></i>
                        <p>No contestants found matching "<?= htmlspecialchars($search) ?>" in <?= strtolower($page_title) ?>.</p>
                    </div>
                <?php else: ?>
                    <div class="contestant-grid">
                        <?php foreach ($contestants as $c): ?>
                            <div class="contestant-card">
                                <img src="./assets/uploads/contestants/<?= htmlspecialchars($c['photo']) ?>" 
                                    alt="Photo" 
                                    class="card-img" 
                                    onerror="this.onerror=null; this.src='./assets/images/default_user.png';">                                
                                <div class="card-body">
                                    <div class="card-title"><?= htmlspecialchars($c['name']) ?></div>
                                    <div class="card-subtitle"><?= htmlspecialchars($c['hometown']) ?></div>
                                    
                                    <div class="stats-row">
                                        <span>Age: <?= $c['age'] ?></span>
                                        <span>Ht: <?= htmlspecialchars($c['height']) ?></span>
                                    </div>
                                    <div class="stats-row">
                                        <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($c['event_name']) ?></span>
                                    </div>
                                </div>

                                <div class="card-actions">
                                    <?php if ($view === 'pending'): ?>
                                        <button class="icon-btn btn-view" onclick='openViewModal(<?= json_encode($c) ?>)' title="View Application Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <a href="../api/contestant.php?action=approve&id=<?= $c['contestant_id'] ?>" class="icon-btn btn-approve" onclick="return confirm('Approve this candidate?');" title="Approve">
                                            <i class="fas fa-check"></i>
                                        </a>
                                        <a href="../api/contestant.php?action=reject&id=<?= $c['contestant_id'] ?>" class="icon-btn btn-reject" onclick="return confirm('Reject this application?');" title="Reject">
                                            <i class="fas fa-times"></i>
                                        </a>
                                    
                                    <?php elseif ($view === 'archived'): ?>
                                        <a href="../api/contestant.php?action=restore&id=<?= $c['contestant_id'] ?>" class="icon-btn btn-restore" onclick="return confirm('Restore this contestant?');" title="Restore">
                                            <i class="fas fa-undo"></i>
                                        </a>
                                        <a href="../api/contestant.php?action=delete&id=<?= $c['contestant_id'] ?>" class="icon-btn btn-delete" onclick="return confirm('PERMANENTLY REMOVE?\n\nThey will disappear from the list completely.');" title="Delete Permanently">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    
                                    <?php else: ?>
                                        <button class="icon-btn btn-edit" onclick='openEditModal(<?= json_encode($c) ?>)' title="Edit Details">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                        
                                        <form action="../api/resend_email.php" method="POST" style="margin:0;" onsubmit="return confirm('Send reminder email?');">
                                            <input type="hidden" name="user_id" value="<?= $c['id'] ?>">
                                            <input type="hidden" name="action_type" value="reminder">
                                            <button type="submit" class="icon-btn btn-reminder" title="Send Login Reminder">
                                                <i class="fas fa-paper-plane"></i>
                                            </button>
                                        </form>

                                        <form action="../api/resend_email.php" method="POST" style="margin:0;" onsubmit="return confirm('RESET password and email it?');">
                                            <input type="hidden" name="user_id" value="<?= $c['id'] ?>">
                                            <input type="hidden" name="action_type" value="reset">
                                            <button type="submit" class="icon-btn btn-reset" title="Reset Password">
                                                <i class="fas fa-key"></i>
                                            </button>
                                        </form>

                                        <a href="../api/contestant.php?action=remove&id=<?= $c['contestant_id'] ?>" class="icon-btn btn-archive" onclick="return confirm('Archive this contestant?');" title="Archive">
                                            <i class="fas fa-archive"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <div id="addModal" class="modal-overlay">
        <div class="modal-content">
            <h3>Add New Contestant</h3>
            <form action="../api/contestant.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create">
                <div class="form-group">
                    <label>Event</label>
                    <select name="event_id" class="form-control" required>
                        <?php foreach ($my_events as $evt): ?>
                            <option value="<?= $evt['id'] ?>"><?= htmlspecialchars($evt['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Name</label><input type="text" name="name" class="form-control" required></div>
                    <div class="form-group"><label>Age</label><input type="number" name="age" class="form-control" required></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" required></div>
                    <div class="form-group"><label>Password</label><input type="password" name="password" class="form-control" required></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Height</label><input type="text" name="height" class="form-control"></div>
                    <div class="form-group"><label>Bust</label><input type="number" step="0.1" name="bust" class="form-control" placeholder="34"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Waist</label><input type="number" step="0.1" name="waist" class="form-control" placeholder="24"></div>
                    <div class="form-group"><label>Hips</label><input type="number" step="0.1" name="hips" class="form-control" placeholder="34"></div>
                </div>
                
                <div class="form-group"><label>Hometown</label><input type="text" name="hometown" class="form-control" required></div>
                <div class="form-group"><label>Motto</label><input type="text" name="motto" class="form-control"></div>
                <div class="form-group"><label>Photo</label><input type="file" name="photo" class="form-control" accept="image/*" required></div>
                
                <div style="text-align:right;">
                    <button type="button" onclick="closeModal('addModal')" style="padding:8px 15px; border:none; background:#e5e7eb; border-radius:4px; cursor:pointer;">Cancel</button> 
                    <button type="submit" style="padding:8px 15px; border:none; background:#F59E0B; color:white; border-radius:4px; font-weight:bold; cursor:pointer;">Save</button>
                </div>
            </form>
        </div>
    </div>

    <div id="editModal" class="modal-overlay">
        <div class="modal-content">
            <h3 id="modalTitle">Edit Contestant</h3>
            <form action="../api/contestant.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="contestant_id" id="edit_id">
                
                <div class="form-group">
                    <label>Event</label>
                    <select name="event_id" id="edit_event_id" class="form-control" required>
                        <?php foreach ($my_events as $evt): ?>
                            <option value="<?= $evt['id'] ?>"><?= htmlspecialchars($evt['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group"><label>Name</label><input type="text" name="name" id="edit_name" class="form-control" required></div>
                    <div class="form-group"><label>Age</label><input type="number" name="age" id="edit_age" class="form-control" required></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Email</label><input type="email" name="email" id="edit_email" class="form-control" required></div>
                    <div class="form-group"><label>Change Password</label><input type="password" name="password" id="editPass" class="form-control" placeholder="Optional"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Height</label><input type="text" name="height" id="edit_height" class="form-control"></div>
                    <div class="form-group"><label>Bust</label><input type="number" step="0.1" name="bust" id="edit_bust" class="form-control"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Waist</label><input type="number" step="0.1" name="waist" id="edit_waist" class="form-control"></div>
                    <div class="form-group"><label>Hips</label><input type="number" step="0.1" name="hips" id="edit_hips" class="form-control"></div>
                </div>
                
                <div class="form-group"><label>Hometown</label><input type="text" name="hometown" id="edit_hometown" class="form-control" required></div>
                <div class="form-group"><label>Motto</label><input type="text" name="motto" id="edit_motto" class="form-control"></div>
                <div class="form-group" id="photoGroup"><label>Update Photo</label><input type="file" name="photo" class="form-control" accept="image/*"></div>
                
                <div style="text-align:right; margin-top:15px;" id="modalActions">
                    <button type="button" onclick="closeModal('editModal')" style="padding:8px 15px; border:none; background:#e5e7eb; border-radius:4px; cursor:pointer;">Cancel</button>
                    <button type="submit" style="padding:8px 15px; border:none; background:#F59E0B; color:white; border-radius:4px; font-weight:bold; cursor:pointer;">Save Changes</button>
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

        function openModal(id) { document.getElementById(id).style.display = 'flex'; }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }
        
        function populateModal(c) {
            document.getElementById('edit_id').value = c.id;
            document.getElementById('edit_event_id').value = c.event_id;
            document.getElementById('edit_name').value = c.name;
            document.getElementById('edit_email').value = c.email;
            document.getElementById('edit_age').value = c.age;
            document.getElementById('edit_height').value = c.height;
            document.getElementById('edit_hometown').value = c.hometown;
            document.getElementById('edit_motto').value = c.motto;
            
            if (c.vital_stats) {
                const parts = c.vital_stats.split('-');
                if(parts.length === 3) {
                    document.getElementById('edit_bust').value = parts[0];
                    document.getElementById('edit_waist').value = parts[1];
                    document.getElementById('edit_hips').value = parts[2];
                }
            }
            openModal('editModal');
        }

        function openEditModal(c) {
            document.getElementById('modalTitle').innerText = "Edit Contestant";
            document.getElementById('photoGroup').style.display = 'block';
            document.getElementById('modalActions').style.display = 'block';
            const inputs = document.querySelectorAll('#editModal input, #editModal select');
            inputs.forEach(i => i.disabled = false);
            populateModal(c);
        }

        function openViewModal(c) {
            populateModal(c);
            document.getElementById('modalTitle').innerText = "Application Details";
            document.getElementById('photoGroup').style.display = 'none';
            const inputs = document.querySelectorAll('#editModal input, #editModal select');
            inputs.forEach(i => i.disabled = true);
            document.getElementById('modalActions').innerHTML = 
                `<button type="button" onclick="closeModal('editModal')" style="padding:8px 15px; border:none; background:#e5e7eb; border-radius:4px; cursor:pointer;">Close</button>`;
        }

        // --- TOAST NOTIFICATION LOGIC ---
        function showToast(message, type = 'success') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            const icon = type === 'success' ? '<i class="fas fa-check-circle"></i>' : '<i class="fas fa-exclamation-circle"></i>';
            toast.innerHTML = `${icon} <span>${message}</span>`;
            container.appendChild(toast);
            
            // Remove after 3.5s
            setTimeout(() => { toast.remove(); }, 3500);
        }

        // Check URL params for success/error from API redirect
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('success')) showToast(urlParams.get('success'), 'success');
        if (urlParams.has('error')) showToast(urlParams.get('error'), 'error');

        // Clean URL to prevent re-toast on refresh
        if (urlParams.has('success') || urlParams.has('error')) {
            const newUrl = window.location.pathname + (urlParams.has('view') ? '?view=' + urlParams.get('view') : '');
            window.history.replaceState({}, document.title, newUrl);
        }
    </script>
</body>
</html>