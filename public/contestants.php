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
if ($my_role === 'Event Manager') {
    $contestants = Contestant::getAllByManager($my_id, $status_filter, $search);
    $evt_stmt = $conn->prepare("SELECT id, title FROM events WHERE manager_id = ? AND status = 'Active'");
    $evt_stmt->bind_param("i", $my_id);
} else {
    $contestants = Contestant::getAllByOrganizer($my_id, $status_filter, $search);
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Manage Contestants - BPMS</title>
    <link rel="stylesheet" href="./assets/css/style.css?v=20">
    <link rel="stylesheet" href="./assets/fontawesome/css/all.min.css">
    <style>
        /* --- GLOBAL LAYOUT FIXES --- */
        body { 
            background-color: #f3f4f6; 
            font-family: 'Segoe UI', sans-serif; 
            overflow-x: hidden; 
        }
        
        /* 1. SIDEBAR SCROLLING */
        .sidebar {
            height: 100vh;
            overflow-y: auto; 
            z-index: 2000;
            -webkit-overflow-scrolling: touch;
        }

        /* 2. DESKTOP LAYOUT (Fixed Sidebar) */
        @media (min-width: 769px) {
            .sidebar { position: fixed; top: 0; left: 0; bottom: 0; width: 260px; padding-bottom: 0; }
            .content-area { margin-left: 260px; width: calc(100% - 260px); height: 100vh; overflow-y: auto; }
        }

        /* 3. MOBILE LAYOUT (Scrollable Body) */
        @media (max-width: 768px) {
            /* Force body to scroll on mobile */
            body { overflow-y: auto !important; }
            
            .sidebar { padding-bottom: 80px; }
            
            /* Allow content to grow naturally */
            .content-area { 
                width: 100%; 
                margin-left: 0; 
                height: auto !important; 
                overflow-y: visible !important; 
            }
        }

        /* CONTAINER */
        .container { max-width: 100%; margin: 0; padding: 20px; padding-bottom: 80px; }
        
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }

        /* --- TABS --- */
        .tabs { display: flex; gap: 20px; border-bottom: 2px solid #e5e7eb; margin-bottom: 20px; }
        .tab-link { 
            padding: 10px 5px; text-decoration: none; color: #6b7280; 
            font-weight: 600; font-size: 14px; border-bottom: 3px solid transparent; 
            margin-bottom: -2px; transition: 0.2s;
        }
        .tab-link:hover { color: #111827; }
        .tab-link.active { color: #F59E0B; border-bottom-color: #F59E0B; }

        /* --- BUTTONS --- */
        .btn-add { 
            background: #F59E0B; color: white; border: none; padding: 10px 20px; 
            border-radius: 6px; font-weight: 700; cursor: pointer; 
            display: flex; align-items: center; gap: 8px; font-size: 14px;
        }
        .btn-add:hover { background: #d97706; }

        /* --- SEARCH BAR --- */
        .search-container { display: flex; gap: 10px; margin-bottom: 20px; background: white; padding: 10px; border-radius: 8px; border: 1px solid #e5e7eb; max-width: 600px; }
        .search-input { border: none; flex: 1; outline: none; font-size: 14px; }
        .btn-search { background: #1f2937; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600; }
        .btn-reset-search { color: #6b7280; text-decoration: none; font-size: 13px; display: flex; align-items: center; padding: 0 10px; }

        /* --- CONTESTANT CARD --- */
        .card-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px; }
        
        .contestant-card { 
            background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); 
            border: 1px solid #e5e7eb; display: flex; flex-direction: column; overflow: hidden;
        }

        .c-img-wrapper { height: 200px; width: 100%; position: relative; background: #f3f4f6; }
        .c-img { width: 100%; height: 100%; object-fit: cover; object-position: top; }
        .c-number { position: absolute; top: 10px; right: 10px; background: rgba(0,0,0,0.7); color: white; font-size: 12px; font-weight: bold; padding: 4px 8px; border-radius: 4px; }

        .card-body { padding: 15px; flex: 1; }
        .c-name { font-weight: 700; color: #1f2937; font-size: 16px; margin-bottom: 4px; }
        .c-location { font-size: 13px; color: #F59E0B; font-weight: 600; text-transform: uppercase; margin-bottom: 10px; display: flex; align-items: center; gap: 5px; }
        
        .c-stats { 
            background: #f9fafb; border-radius: 6px; padding: 8px; 
            display: grid; grid-template-columns: 1fr 1fr; gap: 5px; 
            font-size: 12px; color: #4b5563; 
        }

        /* --- ACTION FOOTER --- */
        .card-actions { 
            background: #f9fafb; padding: 10px; border-top: 1px solid #f3f4f6; 
            display: flex; justify-content: flex-end; align-items: center; gap: 8px; flex-wrap: wrap;
        }
        
        /* FIX: FORCE ZERO MARGINS ON FORMS TO PREVENT SPACING ISSUES */
        .card-actions form { margin: 0 !important; padding: 0 !important; display: inline-flex; }
        .card-actions a { display: inline-flex; }

        /* BUTTON STYLES */
        .btn-action {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 6px 12px; border-radius: 5px; font-size: 12px; 
            font-weight: 600; cursor: pointer; border: 1px solid transparent;
            transition: 0.2s; text-decoration: none; margin: 0; /* Safety margin reset */
        }

        .btn-edit { background: #eff6ff; color: #2563eb; border-color: #bfdbfe; }
        .btn-remind { background: #f0fdf4; color: #16a34a; border-color: #bbf7d0; }
        .btn-reset { background: #fffbeb; color: #d97706; border-color: #fde68a; }
        .btn-archive { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
        
        .btn-view { background: #f3f4f6; color: #374151; border-color: #d1d5db; }
        .btn-approve { background: #dcfce7; color: #166534; border-color: #bbf7d0; }
        .btn-reject { background: #fee2e2; color: #b91c1c; border-color: #fecaca; }

        /* Modal */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background: white; padding: 25px; width: 400px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); max-height: 90vh; overflow-y: auto; }
        .form-group { margin-bottom: 15px; }
        .form-row { display: flex; gap: 10px; }
        .form-row .form-group { flex: 1; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 5px; box-sizing: border-box; }
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
                    <div class="navbar-title">Register Contestant</div>
                </div>
            </div>

            <div class="container">
                <div id="toast-container" class="toast-container"></div>

                <div class="page-header">
                    <h2 style="margin:0;">Manage Contestants</h2>
                    <button class="btn-add" onclick="openModal('addModal')"><i class="fas fa-plus"></i> Manually Add</button>
                </div>

                <div class="tabs">
                    <a href="?view=active" class="tab-link <?= $view === 'active' ? 'active' : '' ?>">Official Candidates</a>
                    <a href="?view=pending" class="tab-link <?= $view === 'pending' ? 'active' : '' ?>">Pending Applications</a>
                    <a href="?view=archived" class="tab-link <?= $view === 'archived' ? 'active' : '' ?>">Archived</a>
                </div>

                <form method="GET" action="contestants.php" class="search-container">
                    <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
                    <i class="fas fa-search" style="color:#9ca3af; align-self:center;"></i>
                    <input type="text" name="search" class="search-input" placeholder="Search by name or hometown..." value="<?= htmlspecialchars($search) ?>">
                    <?php if (!empty($search)): ?>
                        <a href="contestants.php?view=<?= $view ?>" class="btn-reset-search"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                    <button type="submit" class="btn-search">Search</button>
                </form>

                <?php if (empty($contestants)): ?>
                    <div style="text-align:center; padding:50px; color:#9ca3af;">
                        <i class="fas fa-folder-open" style="font-size:30px; margin-bottom:10px;"></i>
                        <p>No contestants found matching "<?= htmlspecialchars($search) ?>" in <?= strtolower($page_title) ?>.</p>
                    </div>
                <?php else: ?>
                    
                    <div class="card-grid">
                        <?php foreach ($contestants as $c): ?>
                            <div class="contestant-card">
                                <div class="c-img-wrapper">
                                    <img src="./assets/uploads/contestants/<?= htmlspecialchars($c['photo']) ?>" 
                                         class="c-img" 
                                         onerror="this.onerror=null; this.src='./assets/images/default_user.png';">
                                    <?php if(!empty($c['contestant_number'])): ?>
                                        <div class="c-number">#<?= htmlspecialchars($c['contestant_number']) ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="card-body">
                                    <div class="c-name"><?= htmlspecialchars($c['name']) ?></div>
                                    <div class="c-location">
                                        <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($c['hometown']) ?>
                                    </div>
                                    
                                    <div class="c-stats">
                                        <div><strong>Age:</strong> <?= $c['age'] ?></div>
                                        <div><strong>Height:</strong> <?= htmlspecialchars($c['height']) ?></div>
                                        <div style="grid-column: span 2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            <strong>Event:</strong> <?= htmlspecialchars($c['event_name']) ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="card-actions">
                                    <?php if ($view === 'pending'): ?>
                                        <button class="btn-action btn-view" onclick='openViewModal(<?= json_encode($c) ?>)'>
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <a href="../api/contestant.php?action=approve&id=<?= $c['contestant_id'] ?>" class="btn-action btn-approve" onclick="return confirm('Approve this candidate?');">
                                            <i class="fas fa-check"></i> Accept
                                        </a>
                                        <a href="../api/contestant.php?action=reject&id=<?= $c['contestant_id'] ?>" class="btn-action btn-reject" onclick="return confirm('Reject this application?');">
                                            <i class="fas fa-times"></i> Reject
                                        </a>

                                    <?php elseif ($view === 'archived'): ?>
                                        <a href="../api/contestant.php?action=restore&id=<?= $c['contestant_id'] ?>" class="btn-action btn-remind" onclick="return confirm('Restore this contestant?');">
                                            <i class="fas fa-undo"></i> Restore
                                        </a>
                                        <a href="../api/contestant.php?action=delete&id=<?= $c['contestant_id'] ?>" class="btn-action btn-archive" onclick="return confirm('PERMANENTLY REMOVE?\n\nThey will disappear from the list completely.');">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>

                                    <?php else: ?>
                                        <button class="btn-action btn-edit" onclick='openEditModal(<?= json_encode($c) ?>)'>
                                            <i class="fas fa-pen"></i> Edit
                                        </button>
                                        
                                        <form action="../api/resend_email.php" method="POST" onsubmit="return confirm('Send reminder email?');">
                                            <input type="hidden" name="user_id" value="<?= $c['id'] ?>">
                                            <input type="hidden" name="action_type" value="reminder">
                                            <button type="submit" class="btn-action btn-remind"><i class="fas fa-paper-plane"></i> Remind</button>
                                        </form>

                                        <form action="../api/resend_email.php" method="POST" onsubmit="return confirm('RESET password and email it?');">
                                            <input type="hidden" name="user_id" value="<?= $c['id'] ?>">
                                            <input type="hidden" name="action_type" value="reset">
                                            <button type="submit" class="btn-action btn-reset"><i class="fas fa-key"></i> Reset</button>
                                        </form>

                                        <a href="../api/contestant.php?action=remove&id=<?= $c['contestant_id'] ?>" class="btn-action btn-archive" onclick="return confirm('Archive this contestant?');">
                                            <i class="fas fa-archive"></i> Archive
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
            <h3 style="margin-top:0;">Add New Contestant</h3>
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
                    
                    <div class="form-group" style="position:relative;">
                        <label>Password</label>
                        <input type="password" name="password" id="addPass" class="form-control" required>
                        <i class="fas fa-eye" id="toggleAddPass" 
                           onclick="togglePassword('addPass', 'toggleAddPass')" 
                           style="position:absolute; right:10px; top:32px; cursor:pointer; color:#6b7280;"></i>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group"><label>Height</label><input type="text" name="height" class="form-control" placeholder="170 cm"></div>
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
                    <button type="button" onclick="closeModal('addModal')" style="padding:10px 15px; border:none; background:#e5e7eb; border-radius:4px; cursor:pointer; font-weight:600;">Cancel</button> 
                    <button type="submit" class="btn-submit" style="width:auto; margin-left:10px;">Save</button>
                </div>
            </form>
        </div>
    </div>

    <div id="editModal" class="modal-overlay">
        <div class="modal-content">
            <h3 id="modalTitle" style="margin-top:0;">Edit Contestant</h3>
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
                    
                    <div class="form-group" style="position:relative;">
                        <label>Change Password</label>
                        <input type="password" name="password" id="editPass" class="form-control" placeholder="Optional">
                        <i class="fas fa-eye" id="toggleEditPass" 
                           onclick="togglePassword('editPass', 'toggleEditPass')" 
                           style="position:absolute; right:10px; top:32px; cursor:pointer; color:#6b7280;"></i>
                    </div>
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
                    <button type="button" onclick="closeModal('editModal')" style="padding:10px 15px; border:none; background:#e5e7eb; border-radius:4px; cursor:pointer; font-weight:600;">Cancel</button>
                    <button type="submit" class="btn-submit" style="width:auto; margin-left:10px;">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            if (sidebar.style.left === '0px') {
                sidebar.style.left = '-280px'; overlay.style.display = 'none';
            } else {
                sidebar.style.left = '0px'; overlay.style.display = 'block';
            }
        }

        function openModal(id) { document.getElementById(id).style.display = 'flex'; }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }
        
        function togglePassword(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            if (input.type === "password") {
                input.type = "text"; icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash');
            } else {
                input.type = "password"; icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye');
            }
        }

        function populateModal(c) {
            document.getElementById('edit_id').value = c.contestant_id || c.id; 
            document.getElementById('edit_event_id').value = c.event_id;
            document.getElementById('edit_name').value = c.name;
            document.getElementById('edit_email').value = c.email;
            document.getElementById('edit_age').value = c.age;
            document.getElementById('edit_height').value = c.height;
            document.getElementById('edit_hometown').value = c.hometown;
            document.getElementById('edit_motto').value = c.motto;  
            document.getElementById('edit_bust').value = c.bust || '';
            document.getElementById('edit_waist').value = c.waist || '';
            document.getElementById('edit_hips').value = c.hips || '';
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
                `<button type="button" onclick="closeModal('editModal')" style="padding:10px 15px; border:none; background:#e5e7eb; border-radius:4px; cursor:pointer; font-weight:600;">Close</button>`;
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