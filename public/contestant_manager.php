<?php
require_once __DIR__ . '/../app/core/guard.php';
requireLogin();
requireRole('Contestant Manager');
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/models/Contestant.php';

// --- VIEW LOGIC ---
$view = $_GET['view'] ?? 'active';
$search = trim($_GET['search'] ?? '');
$my_id = $_SESSION['user_id'];

// Determine Status Filter
if ($view === 'pending') {
    $status_filter = 'Pending';
    $page_title = "Pending Applications";
} elseif ($view === 'archived') {
    $status_filter = 'Inactive'; // OR 'Eliminated'/'Removed' depending on flow
    $page_title = "Archived Contestants";
} else {
    $status_filter = 'Active';
    $page_title = "Official Candidates";
}

// 1. FETCH CONTESTANTS
$contestants = Contestant::getAllByOrganizer($my_id, $status_filter, $search);

// 2. FETCH ASSIGNED EVENT (For the Add Modal dropdown)
// UPDATED: Table 'event_teams', Column 'title'
$evt_stmt = $conn->prepare("
    SELECT e.id, e.title 
    FROM events e 
    JOIN event_teams et ON e.id = et.event_id 
    WHERE et.user_id = ? AND et.status = 'Active' AND e.status = 'Active' AND et.is_deleted = 0
");
$evt_stmt->bind_param("i", $my_id);
$evt_stmt->execute();
$my_events = $evt_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Contestant Manager - BPMS</title>
    <link rel="stylesheet" href="./assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .header-actions { display: flex; gap: 10px; }
        .btn-add { background-color: #F59E0B; color: white; padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 13px; text-decoration: none; display: flex; align-items: center; gap: 5px; }
        .btn-add:hover { background-color: #d97706; }
        .btn-secondary { background-color: white; border: 1px solid #d1d5db; color: #374151; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 600; transition: background 0.2s; }
        .btn-secondary:hover { background-color: #f3f4f6; }
        .tabs { display: flex; gap: 15px; margin-bottom: 20px; border-bottom: 2px solid #e5e7eb; padding-bottom: 0; }
        .tab-link { padding: 10px 15px; text-decoration: none; color: #6b7280; font-weight: 600; font-size: 14px; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: color 0.2s; }
        .tab-link:hover { color: #1f2937; }
        .tab-link.active { border-bottom-color: #F59E0B; color: #F59E0B; }
        .search-container { background: white; padding: 15px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px; display: flex; gap: 10px; align-items: center; }
        .search-input { padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; flex-grow: 1; font-size: 14px; }
        .btn-search { background-color: #1f2937; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 14px; }
        .btn-reset { color: #6b7280; text-decoration: none; font-size: 14px; padding: 0 10px; }
        
        /* Grid & Cards */
        .contestant-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; }
        .contestant-card { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; flex-direction: column; transition: transform 0.2s, box-shadow 0.2s; }
        .contestant-card:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .card-img { width: 100%; height: 160px; object-fit: cover; background: #f3f4f6; }
        .card-body { padding: 12px; flex-grow: 1; }
        .card-title { font-size: 15px; font-weight: bold; color: #1f2937; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .card-subtitle { font-size: 11px; color: #F59E0B; margin-bottom: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .stats-row { display: flex; justify-content: space-between; font-size: 12px; color: #6b7280; margin-bottom: 4px; border-bottom: 1px solid #f3f4f6; padding-bottom: 4px; }
        .motto-text { font-style: italic; color: #9ca3af; font-size: 11px; margin-top: 6px; line-height: 1.3; height: 28px; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; }
        
        /* UPDATED: Action Buttons with Text */
        .card-actions { padding: 8px 12px; background: #f9fafb; border-top: 1px solid #f3f4f6; display: flex; flex-direction: column; gap: 6px; }
        .btn-row { display: flex; gap: 5px; }
        .btn-sm { 
            padding: 6px 10px; border-radius: 4px; font-size: 11px; font-weight: bold; 
            cursor: pointer; text-decoration: none; border: 1px solid transparent; 
            flex: 1; text-align: center; display: inline-flex; justify-content: center; align-items: center; gap: 5px;
            transition: opacity 0.2s; 
        }
        .btn-sm:hover { opacity: 0.9; }
        .btn-approve { background: #d1fae5; color: #059669; border-color: #a7f3d0; }
        .btn-reject { background: #fee2e2; color: #dc2626; border-color: #fecaca; }
        .btn-edit { background: #e0f2fe; color: #0284c7; border-color: #bae6fd; }
        .btn-remove { background: #fee2e2; color: #dc2626; border-color: #fecaca; }
        .btn-restore { background: #d1fae5; color: #059669; border-color: #a7f3d0; }
        .btn-delete { background: #b91c1c; color: white; border-color: #991b1b; } /* Hard delete style */
        .btn-email { background: #eff6ff; color: #2563eb; border-color: #bfdbfe; }

        /* Modal Styles */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background: white; padding: 25px; width: 450px; border-radius: 12px; max-height: 90vh; overflow-y: auto; }
        .form-group { margin-bottom: 12px; position: relative; }
        .form-group label { display: block; margin-bottom: 4px; font-weight: 500; font-size: 13px; }
        .form-control { width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 14px; }
        .form-row { display: flex; gap: 10px; }
        .form-row .form-group { flex: 1; }
        .toggle-password { position: absolute; right: 10px; top: 30px; cursor: pointer; color: #9ca3af; }
    </style>
</head>
<body>

    <div class="main-wrapper">
        <?php require_once __DIR__ . '/../app/views/partials/sidebar.php'; ?>

        <div class="content-area">
            <div class="navbar">
                <div class="navbar-title">Roster Management</div>
            </div>

            <div class="container">
                <div id="toast-container" class="toast-container"></div>

                <div class="page-header">
                    <h2>Manage Roster</h2>
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
                        Showing <strong>Archived (Removed)</strong> contestants.
                    </div>
                <?php endif; ?>

                <form method="GET" action="contestant_manager.php" class="search-container">
                    <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
                    <input type="text" name="search" class="search-input" placeholder="Search by name or hometown..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn-search"><i class="fas fa-search"></i> Search</button>
                    <?php if (!empty($search)): ?>
                        <a href="contestant_manager.php?view=<?= $view ?>" class="btn-reset">Reset</a>
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
                                <img src="./assets/uploads/contestants/<?= htmlspecialchars($c['photo']) ?>" alt="Photo" class="card-img" onerror="this.src='./assets/images/default_user.png'">
                                
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

                                    <?php if (!empty($c['motto'])): ?>
                                        <div class="motto-text">"<?= htmlspecialchars($c['motto']) ?>"</div>
                                    <?php endif; ?>
                                </div>

                                <div class="card-actions">
                                    <?php if ($view === 'pending'): ?>
                                        <div class="btn-row">
                                            <a href="../api/contestant.php?action=approve&id=<?= $c['id'] ?>" class="btn-sm btn-approve" onclick="return confirm('Approve this candidate?');"><i class="fas fa-check"></i> Approve</a>
                                            <a href="../api/contestant.php?action=reject&id=<?= $c['id'] ?>" class="btn-sm btn-reject" onclick="return confirm('Reject this application?');"><i class="fas fa-times"></i> Reject</a>
                                        </div>
                                    
                                    <?php elseif ($view === 'archived'): ?>
                                        <div class="btn-row">
                                            <a href="../api/contestant.php?action=restore&id=<?= $c['id'] ?>" class="btn-sm btn-restore" onclick="return confirm('Restore this contestant?');"><i class="fas fa-undo"></i> Restore</a>
                                            <a href="../api/contestant.php?action=delete&id=<?= $c['id'] ?>" class="btn-sm btn-delete" onclick="return confirm('Permanently delete this contestant? This CANNOT be undone.');"><i class="fas fa-trash"></i> Delete</a>
                                        </div>
                                    
                                    <?php else: ?>
                                        <div class="btn-row">
                                            <button class="btn-sm btn-edit" onclick='openEditModal(<?= json_encode($c, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="fas fa-edit"></i> Edit</button>
                                            <a href="../api/contestant.php?action=remove&id=<?= $c['id'] ?>" class="btn-sm btn-remove" onclick="return confirm('Archive this contestant?');"><i class="fas fa-archive"></i> Archive</a>
                                        </div>
                                        <form action="../api/resend_email.php" method="POST" onsubmit="return confirm('Reset password and email it?');">
                                            <input type="hidden" name="user_id" value="<?= $c['id'] ?>">
                                            <input type="hidden" name="role_type" value="Contestant">
                                            <button type="submit" class="btn-sm btn-email" style="width:100%; margin-top:5px;">
                                                <i class="fas fa-envelope"></i> Resend Invite
                                            </button>
                                        </form>
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
                    <label>Assigned Event</label>
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
                    <div class="form-group"><label>Password</label>
                        <input type="password" name="password" id="addPass" class="form-control" required>
                        <i class="fas fa-eye toggle-password" onclick="togglePassword('addPass', this)"></i>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Height (cm/ft)</label><input type="text" name="height" class="form-control"></div>
                    <div class="form-group"><label>Bust</label><input type="number" step="0.1" name="bust" class="form-control" placeholder="34"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Waist</label><input type="number" step="0.1" name="waist" class="form-control" placeholder="24"></div>
                    <div class="form-group"><label>Hips</label><input type="number" step="0.1" name="hips" class="form-control" placeholder="34"></div>
                </div>

                <div class="form-group"><label>Hometown</label><input type="text" name="hometown" class="form-control" required></div>
                <div class="form-group"><label>Motto</label><input type="text" name="motto" class="form-control"></div>
                <div class="form-group"><label>Photo</label><input type="file" name="photo" class="form-control" accept="image/*" required></div>
                
                <div style="text-align:right; margin-top:15px;">
                    <button type="button" onclick="closeModal('addModal')" style="padding:8px 15px; border:none; background:#e5e7eb; border-radius:4px; cursor:pointer;">Cancel</button>
                    <button type="submit" style="padding:8px 15px; border:none; background:#F59E0B; color:white; border-radius:4px; font-weight:bold; cursor:pointer;">Save</button>
                </div>
            </form>
        </div>
    </div>

    <div id="editModal" class="modal-overlay">
        <div class="modal-content">
            <h3>Edit Contestant</h3>
            <form action="../api/contestant.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="contestant_id" id="edit_id">
                <div class="form-group">
                    <label>Assigned Event</label>
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
                    <div class="form-group"><label>Change Password</label>
                        <input type="password" name="password" id="editPass" class="form-control" placeholder="Leave blank to keep">
                        <i class="fas fa-eye toggle-password" onclick="togglePassword('editPass', this)"></i>
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
                <div class="form-group"><label>Update Photo (Optional)</label><input type="file" name="photo" class="form-control" accept="image/*"></div>
                <div style="text-align:right; margin-top:15px;">
                    <button type="button" onclick="closeModal('editModal')" style="padding:8px 15px; border:none; background:#e5e7eb; border-radius:4px; cursor:pointer;">Cancel</button>
                    <button type="submit" style="padding:8px 15px; border:none; background:#F59E0B; color:white; border-radius:4px; font-weight:bold; cursor:pointer;">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(id) { document.getElementById(id).style.display = 'flex'; }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }
        
        function openEditModal(c) {
            document.getElementById('edit_id').value = c.id;
            document.getElementById('edit_event_id').value = c.event_id;
            document.getElementById('edit_name').value = c.name;
            document.getElementById('edit_email').value = c.email;
            document.getElementById('edit_age').value = c.age;
            document.getElementById('edit_height').value = c.height;
            document.getElementById('edit_hometown').value = c.hometown;
            document.getElementById('edit_motto').value = c.motto;
            
            // PARSE VITAL STATS (Format: "34.0-24.0-34.0")
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
        if (urlParams.has('success')) showToast(urlParams.get('success'), 'success');
        if (urlParams.has('error')) showToast(urlParams.get('error'), 'error');
        if (urlParams.has('success') || urlParams.has('error')) {
            const newUrl = window.location.pathname + (urlParams.has('view') ? '?view=' + urlParams.get('view') : '');
            window.history.replaceState({}, document.title, newUrl);
        }
    </script>
</body>
</html>