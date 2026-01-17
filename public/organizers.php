<?php
require_once __DIR__ . '/../app/core/guard.php';
requireLogin();
requireRole('Event Manager');
require_once __DIR__ . '/../app/config/database.php';

$manager_id = $_SESSION['user_id'];
$view = $_GET['view'] ?? 'active';
$status_filter = ($view === 'archived') ? 'Inactive' : 'Active';

// 1. Get Active Event
$evt_stmt = $conn->prepare("SELECT id, title FROM events WHERE manager_id = ? AND status = 'Active' LIMIT 1");
$evt_stmt->bind_param("i", $manager_id);
$evt_stmt->execute();
$active_event = $evt_stmt->get_result()->fetch_assoc();

$organizers = [];

if ($active_event) {
    $event_id = $active_event['id'];

    $sql = "SELECT et.id as link_id, u.id as user_id, u.name, u.email, u.phone, et.role 
            FROM event_teams et 
            JOIN users u ON et.user_id = u.id 
            WHERE et.event_id = ? 
              AND et.status = ?
              AND et.is_deleted = 0
              AND u.role != 'Event Manager'
            ORDER BY u.created_at DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $event_id, $status_filter);
    $stmt->execute();
    $organizers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Manage Organizers - BPMS</title>
    <link rel="stylesheet" href="./assets/css/style.css?v=16">
    <link rel="stylesheet" href="./assets/fontawesome/css/all.min.css"> 
    <style>
        /* --- LAYOUT RESET --- */
        /* We rely on your global style.css for the Sidebar width and position. */
        /* We only add this to ensure content scrolls on mobile if body is locked */
        .content-area {
            height: 100vh;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* FULL WIDTH CONTAINER */
        .container { 
            max-width: 100%; 
            margin: 0; 
            padding: 20px; 
            padding-bottom: 100px; /* Extra padding for mobile scrolling */
        }
        
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }

        /* --- SIMPLE TABS --- */
        .tabs { display: flex; gap: 20px; border-bottom: 2px solid #e5e7eb; margin-bottom: 20px; }
        .tab-link { 
            padding: 10px 5px; text-decoration: none; color: #6b7280; 
            font-weight: 600; font-size: 14px; border-bottom: 3px solid transparent; 
            margin-bottom: -2px; transition: 0.2s;
        }
        .tab-link:hover { color: #111827; }
        .tab-link.active { color: #F59E0B; border-bottom-color: #F59E0B; }

        /* --- ADD BUTTON --- */
        .btn-add { 
            background: #F59E0B; color: white; border: none; padding: 10px 20px; 
            border-radius: 6px; font-weight: 700; cursor: pointer; 
            display: flex; align-items: center; gap: 8px; font-size: 14px;
        }
        .btn-add:hover { background: #d97706; }

        /* --- CARD GRID --- */
        .card-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 15px; }
        
        .org-card { 
            background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); 
            border: 1px solid #e5e7eb; display: flex; flex-direction: column; overflow: hidden;
        }

        .card-body { padding: 15px; flex: 1; }
        
        .o-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 5px; }
        .o-name { font-weight: 700; color: #1f2937; font-size: 16px; }
        
        /* Badges */
        .role-badge { font-size: 10px; padding: 2px 8px; border-radius: 10px; font-weight: 700; text-transform: uppercase; }
        .badge-jc { background: #f5f3ff; color: #7c3aed; }
        .badge-cm { background: #d1fae5; color: #059669; }
        .badge-tb { background: #eff6ff; color: #2563eb; }

        .o-details { font-size: 13px; color: #6b7280; display: flex; flex-direction: column; gap: 4px; margin-top: 5px; }
        .o-detail-item { display: flex; align-items: center; gap: 8px; }

        /* --- ACTION FOOTER --- */
        .card-actions { 
            background: #f9fafb; padding: 10px; border-top: 1px solid #f3f4f6; 
            display: flex; justify-content: flex-end; align-items: center; gap: 8px; flex-wrap: wrap;
        }

        /* FORCE REMOVE MARGINS */
        .card-actions > * { margin: 0 !important; display: inline-flex; }
        .card-actions form { margin: 0 !important; padding: 0 !important; }

        /* BUTTON STYLES */
        .btn-action {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 6px 12px; border-radius: 5px; font-size: 12px; 
            font-weight: 600; cursor: pointer; border: 1px solid transparent;
            transition: 0.2s; text-decoration: none;
        }

        .btn-edit { background: #eff6ff; color: #2563eb; border-color: #bfdbfe; }
        .btn-edit:hover { background: #dbeafe; }

        .btn-remind { background: #f0fdf4; color: #16a34a; border-color: #bbf7d0; }
        .btn-remind:hover { background: #dcfce7; }

        .btn-reset { background: #fffbeb; color: #d97706; border-color: #fde68a; }
        .btn-reset:hover { background: #fef3c7; }

        .btn-archive { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
        .btn-archive:hover { background: #fee2e2; }

        /* Modal */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background: white; padding: 25px; width: 400px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); }
        .form-group { margin-bottom: 15px; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 5px; }
        .btn-submit { background: #F59E0B; color: white; border: none; padding: 10px 20px; border-radius: 5px; font-weight: bold; cursor: pointer; width: 100%; }
    </style>
</head>
<body>

    <div id="sidebarOverlay" class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <div class="main-wrapper">
        <?php require_once __DIR__ . '/../app/views/partials/sidebar.php'; ?>

        <div class="content-area">
            <div class="navbar">
                <div style="display:flex; align-items:center; gap: 15px;">
                    <button class="menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
                    <div class="navbar-title">Manage Organizers</div>
                </div>
            </div>

            <div class="container">
                <div id="toast-container" class="toast-container"></div>

                <?php if (!$active_event): ?>
                    <div style="text-align:center; padding: 40px; background:white; border-radius:8px;">
                        <h2>No Active Event</h2>
                        <p>Go to settings to activate an event.</p>
                    </div>
                <?php else: ?>

                    <div class="page-header">
                        <h2 style="margin:0;">Organizer Team</h2>
                        <?php if ($view === 'active'): ?>
                            <button class="btn-add" onclick="openModal('addModal')"><i class="fas fa-plus"></i> Add Organizer</button>
                        <?php endif; ?>
                    </div>

                    <div class="tabs">
                        <a href="?view=active" class="tab-link <?= $view === 'active' ? 'active' : '' ?>">Active Team</a>
                        <a href="?view=archived" class="tab-link <?= $view === 'archived' ? 'active' : '' ?>">Archived</a>
                    </div>

                    <?php if (empty($organizers)): ?>
                        <div style="text-align:center; padding:40px; color:#9ca3af;">No organizers found.</div>
                    <?php else: ?>
                        <div class="card-grid">
                            <?php foreach ($organizers as $org): 
                                $badgeClass = '';
                                if ($org['role'] === 'Judge Coordinator') { $badgeClass='badge-jc'; }
                                elseif ($org['role'] === 'Contestant Manager') { $badgeClass='badge-cm'; }
                                elseif ($org['role'] === 'Tabulator') { $badgeClass='badge-tb'; }
                            ?>
                                <div class="org-card">
                                    <div class="card-body">
                                        <div class="o-header">
                                            <span class="o-name"><?= htmlspecialchars($org['name']) ?></span>
                                            <span class="role-badge <?= $badgeClass ?>"><?= htmlspecialchars($org['role']) ?></span>
                                        </div>
                                        
                                        <div class="o-details">
                                            <div class="o-detail-item">
                                                <i class="fas fa-envelope" style="width:15px; color:#9ca3af;"></i> 
                                                <?= htmlspecialchars($org['email']) ?>
                                            </div>
                                            <?php if($org['phone']): ?>
                                            <div class="o-detail-item">
                                                <i class="fas fa-phone" style="width:15px; color:#9ca3af;"></i> 
                                                <?= htmlspecialchars($org['phone']) ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="card-actions">
                                        <?php if ($view === 'active'): ?>
                                            <button class="btn-action btn-edit" onclick='openEditModal(<?= json_encode($org) ?>)'>
                                                <i class="fas fa-pen"></i> Edit
                                            </button>
                                            
                                            <form action="../api/resend_email.php" method="POST" onsubmit="return confirm('Send Reminder?');">
                                                <input type="hidden" name="user_id" value="<?= $org['user_id'] ?>">
                                                <input type="hidden" name="action_type" value="reminder">
                                                <button type="submit" class="btn-action btn-remind"><i class="fas fa-paper-plane"></i> Remind</button>
                                            </form>

                                            <form action="../api/resend_email.php" method="POST" onsubmit="return confirm('Reset Password?');">
                                                <input type="hidden" name="user_id" value="<?= $org['user_id'] ?>">
                                                <input type="hidden" name="action_type" value="reset">
                                                <button type="submit" class="btn-action btn-reset"><i class="fas fa-key"></i> Reset</button>
                                            </form>

                                            <a href="../api/organizer.php?action=remove&id=<?= $org['link_id'] ?>" class="btn-action btn-archive" onclick="return confirm('Archive?');">
                                                <i class="fas fa-archive"></i> Archive
                                            </a>

                                        <?php else: ?>
                                            <a href="../api/organizer.php?action=restore&id=<?= $org['link_id'] ?>" class="btn-action btn-remind" onclick="return confirm('Restore?');">
                                                <i class="fas fa-undo"></i> Restore
                                            </a>
                                            <a href="../api/organizer.php?action=delete&id=<?= $org['link_id'] ?>" class="btn-action btn-archive" onclick="return confirm('Delete Permanently?');">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="addModal" class="modal-overlay">
        <div class="modal-content">
            <h3 style="margin-top:0;">Add New Organizer</h3>
            <form action="../api/organizer.php" method="POST">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="event_id" value="<?= $active_event['id'] ?? '' ?>">
                
                <div class="form-group">
                    <label>Role</label>
                    <select name="role" class="form-control" required>
                        <option value="Judge Coordinator">Judge Coordinator</option>
                        <option value="Contestant Manager">Contestant Manager</option>
                        <option value="Tabulator">Tabulator</option>
                    </select>
                </div>
                <div class="form-group"><label>Full Name</label><input type="text" name="name" class="form-control" required></div>
                <div class="form-group"><label>Email Address</label><input type="email" name="email" class="form-control" required></div>
                <div class="form-group"><label>Phone (Optional)</label><input type="text" name="phone" class="form-control"></div>
                <div class="form-group"><label>Password</label><input type="password" name="password" class="form-control" required></div>
                
                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                    <button type="button" onclick="document.getElementById('addModal').style.display='none'" style="background:#e5e7eb; border:none; padding:10px 15px; border-radius:5px; cursor:pointer;">Cancel</button>
                    <button type="submit" class="btn-submit">Add Member</button>
                </div>
            </form>
        </div>
    </div>

    <div id="editModal" class="modal-overlay">
        <div class="modal-content">
            <h3 style="margin-top:0;">Edit Organizer</h3>
            <form action="../api/organizer.php" method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="org_id" id="edit_id"> 
                
                <div class="form-group">
                    <label>Role</label>
                    <select name="role" id="edit_role" class="form-control" required>
                        <option value="Judge Coordinator">Judge Coordinator</option>
                        <option value="Contestant Manager">Contestant Manager</option>
                        <option value="Tabulator">Tabulator</option>
                    </select>
                </div>
                <div class="form-group"><label>Name</label><input type="text" name="name" id="edit_name" class="form-control" required></div>
                <div class="form-group"><label>Email</label><input type="email" name="email" id="edit_email" class="form-control" required></div>
                <div class="form-group"><label>Phone</label><input type="text" name="phone" id="edit_phone" class="form-control"></div>
                <div class="form-group"><label>New Password (Optional)</label><input type="text" name="password" class="form-control" placeholder="Leave blank to keep"></div>
                
                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                    <button type="button" onclick="document.getElementById('editModal').style.display='none'" style="background:#e5e7eb; border:none; padding:10px 15px; border-radius:5px; cursor:pointer;">Cancel</button>
                    <button type="submit" class="btn-submit">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            if (sidebar.style.left === '0px') {
                sidebar.style.left = '-280px'; 
                overlay.style.display = 'none';
            } else {
                sidebar.style.left = '0px'; 
                overlay.style.display = 'block';
            }
        }

        function openModal(id) { document.getElementById(id).style.display = 'flex'; }
        
        function openEditModal(user) {
            document.getElementById('edit_id').value = user.link_id; 
            document.getElementById('edit_name').value = user.name;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_phone').value = user.phone;
            document.getElementById('edit_role').value = user.role;
            openModal('editModal');
        }

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
        if (urlParams.has('success')) showToast(urlParams.get('success'), 'success');
        if (urlParams.has('error')) showToast(urlParams.get('error'), 'error');
        if (urlParams.has('success') || urlParams.has('error')) {
            const newUrl = window.location.pathname + (urlParams.has('view') ? '?view=' + urlParams.get('view') : '');
            window.history.replaceState({}, document.title, newUrl);
        }
    </script>
</body>
</html>