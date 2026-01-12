<?php
require_once __DIR__ . '/../app/core/guard.php';
requireLogin();
requireRole('Event Manager');
require_once __DIR__ . '/../app/config/database.php';

$manager_id = $_SESSION['user_id'];
$view = $_GET['view'] ?? 'active';
$status_filter = ($view === 'archived') ? 'Inactive' : 'Active';

$search = trim($_GET['search'] ?? '');
$role_filter = $_GET['role'] ?? '';

// 1. Get Active Event
$evt_sql = "SELECT id, title FROM events WHERE manager_id = ? AND status = 'Active' LIMIT 1";
$evt_stmt = $conn->prepare($evt_sql);
$evt_stmt->bind_param("i", $manager_id);
$evt_stmt->execute();
$active_event = $evt_stmt->get_result()->fetch_assoc();

$organizers = [];

if ($active_event) {
    $event_id = $active_event['id'];

    // 2. Build Query
    $sql = "SELECT et.id as link_id, u.id as user_id, u.name, u.email, u.phone, et.role 
            FROM event_teams et 
            JOIN users u ON et.user_id = u.id 
            WHERE et.event_id = ? 
              AND et.status = ?
              AND et.is_deleted = 0
              AND u.role != 'Event Manager'";

    $types = "is";
    $params = [$event_id, $status_filter];

    if (!empty($search)) {
        $sql .= " AND (u.name LIKE ? OR u.email LIKE ?)";
        $types .= "ss";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    if (!empty($role_filter)) {
        $sql .= " AND et.role = ?"; 
        $types .= "s";
        $params[] = $role_filter;
    }

    $sql .= " ORDER BY u.created_at DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $organizers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Organizers - BPMS</title>
    <link rel="stylesheet" href="./assets/css/style.css?v=3">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"> 
</head>
<body>

    <div id="sidebarOverlay" class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <div class="main-wrapper">
        <?php require_once __DIR__ . '/../app/views/partials/sidebar.php'; ?>

        <div class="content-area">
            <div class="navbar">
                <div style="display:flex; align-items:center;">
                    <button class="menu-toggle" onclick="toggleSidebar()">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="navbar-title">Manage Organizers</div>
                </div>
            </div>

            <div class="container">
                <div id="toast-container" class="toast-container"></div>

                <?php if (!$active_event): ?>
                    <div style="text-align:center; padding: 40px; color: #6b7280; background: white; border-radius: 8px;">
                        <i class="fas fa-calendar-plus" style="font-size: 40px; margin-bottom: 10px;"></i>
                        <h2>No Active Event</h2>
                        <p>You must set an event to 'Active' status in Settings to manage organizers.</p>
                    </div>
                <?php else: ?>

                    <div class="page-header">
                        <div>
                            <h2 style="color: #111827; margin:0;"><?= ($view === 'archived') ? 'Archived Organizers' : 'Organizer Team' ?></h2>
                            <p style="color: #6b7280; font-size: 13px; margin-top:5px;">Event: <strong><?= htmlspecialchars($active_event['title']) ?></strong></p>
                        </div>
                        <div class="header-actions">
                            <?php if ($view === 'active'): ?>
                                <a href="?view=archived" class="btn-secondary"><i class="fas fa-archive"></i> View Archive</a>
                                <button class="btn-add" onclick="openModal('addModal')"><i class="fas fa-plus"></i> Add Organizer</button>
                            <?php else: ?>
                                <a href="?view=active" class="btn-secondary">← Back to Active List</a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <form method="GET" action="organizers.php" class="search-container">
                        <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
                        <i class="fas fa-search" style="color:#9ca3af; padding-left:5px;"></i>
                        <input type="text" name="search" class="search-input" style="border:none;" placeholder="Search name or email..." value="<?= htmlspecialchars($search) ?>">
                        <select name="role" class="filter-select">
                            <option value="">All Roles</option>
                            <option value="Judge Coordinator" <?= $role_filter === 'Judge Coordinator' ? 'selected' : '' ?>>Judge Coordinator</option>
                            <option value="Contestant Manager" <?= $role_filter === 'Contestant Manager' ? 'selected' : '' ?>>Contestant Manager</option>
                            <option value="Tabulator" <?= $role_filter === 'Tabulator' ? 'selected' : '' ?>>Tabulator</option>
                        </select>
                        <button type="submit" class="btn-search">Filter</button>
                    </form>

                    <div class="table-card" style="overflow-x:auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Role</th>
                                    <th>Contact Info</th>
                                    <th style="text-align:right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($organizers)): ?>
                                    <tr><td colspan="4" style="text-align:center; padding: 30px; color:#9ca3af;">No organizers found matching your criteria.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($organizers as $org): ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight: 600; color:#1f2937;"><?= htmlspecialchars($org['name']) ?></div>
                                            </td>
                                            <td>
                                                <?php 
                                                    $cls = 'role-tab';
                                                    if ($org['role'] === 'Judge Coordinator') $cls = 'role-judge';
                                                    if ($org['role'] === 'Contestant Manager') $cls = 'role-cm';
                                                ?>
                                                <span class="role-badge <?= $cls ?>"><?= htmlspecialchars($org['role']) ?></span>
                                            </td>
                                            <td style="font-size:13px; color:#4b5563;">
                                                <div><i class="fas fa-envelope" style="color:#9ca3af; width:15px;"></i> <?= htmlspecialchars($org['email']) ?></div>
                                                <?php if($org['phone']): ?>
                                                <div style="margin-top:2px;"><i class="fas fa-phone" style="color:#9ca3af; width:15px;"></i> <?= htmlspecialchars($org['phone']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align:right;">
                                                <div style="display:inline-flex;">
                                                    <?php if ($view === 'active'): ?>
                                                        
                                                        <button class="icon-btn btn-edit" onclick='openEditModal(<?= json_encode($org) ?>)' title="Edit Details">
                                                            <i class="fas fa-pen"></i>
                                                        </button>
                                                        
                                                        <form action="../api/resend_email.php" method="POST" style="margin:0;" onsubmit="return confirm('Send a reminder email? (Password will NOT be changed)');">
                                                            <input type="hidden" name="user_id" value="<?= $org['user_id'] ?>">
                                                            <input type="hidden" name="action_type" value="reminder">
                                                            <button type="submit" class="icon-btn btn-reminder" title="Send Login Reminder">
                                                                <i class="fas fa-paper-plane"></i>
                                                            </button>
                                                        </form>

                                                        <form action="../api/resend_email.php" method="POST" style="margin:0;" onsubmit="return confirm('WARNING: This will RESET the password and email the new one. Proceed?');">
                                                            <input type="hidden" name="user_id" value="<?= $org['user_id'] ?>">
                                                            <input type="hidden" name="action_type" value="reset">
                                                            <button type="submit" class="icon-btn btn-reset" title="Reset Password & Email">
                                                                <i class="fas fa-key"></i>
                                                            </button>
                                                        </form>
                                                        
                                                        <a href="../api/organizer.php?action=remove&id=<?= $org['link_id'] ?>" class="icon-btn btn-archive" onclick="return confirm('Archive this organizer?');" title="Archive">
                                                            <i class="fas fa-archive"></i>
                                                        </a>

                                                    <?php else: ?>
                                                        <a href="../api/organizer.php?action=restore&id=<?= $org['link_id'] ?>" class="icon-btn btn-restore" onclick="return confirm('Restore this organizer?');" title="Restore">
                                                            <i class="fas fa-undo"></i>
                                                        </a>

                                                        <a href="../api/organizer.php?action=delete&id=<?= $org['link_id'] ?>" class="icon-btn btn-delete" onclick="return confirm('PERMANENTLY REMOVE?\n\nThey will disappear from this list, but their account credentials will remain in the database.');" title="Remove Completely">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="addModal" class="modal-overlay">
        <div class="modal-content">
            <h3 style="margin-bottom:20px; color:#111827;">Add New Organizer</h3>
            <form action="../api/organizer.php" method="POST">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="event_id" value="<?= $active_event['id'] ?? '' ?>">
                
                <div class="form-group">
                    <label style="font-size:13px; font-weight:600; color:#374151;">Role</label>
                    <select name="role" class="form-control" required>
                        <option value="Judge Coordinator">Judge Coordinator</option>
                        <option value="Contestant Manager">Contestant Manager</option>
                        <option value="Tabulator">Tabulator</option>
                    </select>
                </div>
                <div class="form-group">
                    <label style="font-size:13px; font-weight:600; color:#374151;">Full Name</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label style="font-size:13px; font-weight:600; color:#374151;">Email Address</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label style="font-size:13px; font-weight:600; color:#374151;">Phone (Optional)</label>
                    <input type="text" name="phone" class="form-control">
                </div>
                <div class="form-group">
                    <label style="font-size:13px; font-weight:600; color:#374151;">Password</label>
                    <input type="password" name="password" id="addPass" class="form-control" required>
                    <i class="fas fa-eye toggle-password" onclick="togglePassword('addPass', this)"></i>
                </div>
                <div style="text-align:right; margin-top:20px;">
                    <button type="button" onclick="document.getElementById('addModal').style.display='none'" style="background:#e5e7eb; padding:10px 20px; border:none; border-radius:6px; cursor:pointer; font-weight:600; color:#374151;">Cancel</button>
                    <button type="submit" 
                            onclick="this.innerHTML='<i class=\'fas fa-spinner fa-spin\'></i> Sending Email...'; this.style.opacity='0.7'; this.style.cursor='not-allowed';" 
                            style="background:#F59E0B; color:white; padding:10px 20px; border:none; border-radius:6px; cursor:pointer; font-weight:600; margin-left:10px;">
                        Add Member
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="editModal" class="modal-overlay">
        <div class="modal-content">
            <h3 style="margin-bottom:20px; color:#111827;">Edit Organizer</h3>
            <form action="../api/organizer.php" method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="org_id" id="edit_id"> 
                <div class="form-group">
                    <label style="font-size:13px; font-weight:600; color:#374151;">Role</label>
                    <select name="role" id="edit_role" class="form-control" required>
                        <option value="Judge Coordinator">Judge Coordinator</option>
                        <option value="Contestant Manager">Contestant Manager</option>
                        <option value="Tabulator">Tabulator</option>
                    </select>
                </div>
                <div class="form-group"><label style="font-size:13px; font-weight:600; color:#374151;">Name</label><input type="text" name="name" id="edit_name" class="form-control" required></div>
                <div class="form-group"><label style="font-size:13px; font-weight:600; color:#374151;">Email</label><input type="email" name="email" id="edit_email" class="form-control" required></div>
                <div class="form-group"><label style="font-size:13px; font-weight:600; color:#374151;">Phone</label><input type="text" name="phone" id="edit_phone" class="form-control"></div>
                <div class="form-group">
                    <label style="font-size:13px; font-weight:600; color:#374151;">New Password (Optional)</label>
                    <input type="password" name="password" id="editPass" class="form-control" placeholder="Leave blank to keep current">
                    <i class="fas fa-eye toggle-password" onclick="togglePassword('editPass', this)"></i>
                </div>
                <div style="text-align:right; margin-top:20px;">
                    <button type="button" onclick="document.getElementById('editModal').style.display='none'" style="background:#e5e7eb; padding:10px 20px; border:none; border-radius:6px; cursor:pointer; font-weight:600; color:#374151;">Cancel</button>
                    <button type="submit" 
                            onclick="this.innerHTML='<i class=\'fas fa-spinner fa-spin\'></i> Saving...'; this.style.opacity='0.7'; this.style.cursor='not-allowed';" 
                            style="background:#3b82f6; color:white; padding:10px 20px; border:none; border-radius:6px; cursor:pointer; font-weight:600; margin-left:10px;">
                        Save Changes
                    </button>
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
        function openEditModal(user) {
            document.getElementById('edit_id').value = user.user_id;
            document.getElementById('edit_name').value = user.name;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_phone').value = user.phone;
            document.getElementById('edit_role').value = user.role;
            openModal('editModal');
        }
        function togglePassword(inputId, icon) {
            const input = document.getElementById(inputId);
            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
            } else {
                input.type = "password";
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");
            }
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