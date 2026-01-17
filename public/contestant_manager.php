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
    $status_filter = 'Inactive';
    $page_title = "Archived Contestants";
} else {
    $status_filter = 'Active';
    $page_title = "Official Candidates";
}

// 1. FETCH CONTESTANTS
$contestants = Contestant::getAllByOrganizer($my_id, $status_filter, $search);

// 2. FETCH ASSIGNED EVENT
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Contestant Manager</title>
    <link rel="stylesheet" href="./assets/css/style.css">
    <link rel="stylesheet" href="./assets/fontawesome/css/all.min.css">
    <style>
        :root { --primary: #F59E0B; --dark: #111827; --bg: #f3f4f6; }
        
        body { background-color: var(--bg); font-family: 'Segoe UI', sans-serif; margin: 0; padding-bottom: 60px; overflow-y: auto; -webkit-overflow-scrolling: touch; }

        /* NAVBAR */
        .navbar { background: var(--dark); color: white; height: 60px; display: flex; align-items: center; justify-content: space-between; padding: 0 15px; position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .navbar-title { font-size: 16px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .btn-logout { background: rgba(255,255,255,0.1); color: #fca5a5; text-decoration: none; padding: 8px 12px; border-radius: 6px; font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 6px; }
        
        /* LAYOUT */
        .container { max-width: 1400px; margin: 0 auto; padding: 15px; }
        
        /* TABS */
        .tabs { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 10px; margin-bottom: 15px; scrollbar-width: none; }
        .tab-link { padding: 10px 16px; background: white; border: 1px solid #ddd; border-radius: 20px; text-decoration: none; color: #4b5563; font-weight: 600; font-size: 13px; white-space: nowrap; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .tab-link.active { background: var(--dark); color: white; border-color: var(--dark); }

        /* CONTROLS */
        .controls { display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px; }
        @media(min-width: 768px) { .controls { flex-direction: row; justify-content: space-between; } }
        .search-form { display: flex; gap: 8px; flex: 1; }
        .search-input { flex: 1; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; outline: none; }
        .btn-search { background: var(--dark); color: white; border: none; padding: 0 20px; border-radius: 8px; cursor: pointer; font-size: 14px; }
        .btn-add { background: var(--primary); color: white; border: none; padding: 12px 20px; border-radius: 8px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; font-size: 14px; box-shadow: 0 2px 4px rgba(245, 158, 11, 0.3); }

        /* GRID */
        .contestant-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 15px; }
        .card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid #e5e7eb; display: flex; flex-direction: column; }
        .card-header-img { height: 200px; width: 100%; position: relative; background: #f9fafb; }
        .card-img { width: 100%; height: 100%; object-fit: cover; object-position: top; }
        .card-id-badge { position: absolute; top: 10px; right: 10px; background: rgba(0,0,0,0.7); color: white; font-size: 11px; padding: 4px 10px; border-radius: 20px; font-weight: bold; }
        .card-body { padding: 15px; flex-grow: 1; }
        .c-name { font-size: 18px; font-weight: 700; color: #1f2937; margin-bottom: 2px; }
        .c-hometown { font-size: 13px; color: #F59E0B; font-weight: 600; text-transform: uppercase; margin-bottom: 12px; }
        .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; background: #f9fafb; padding: 10px; border-radius: 8px; margin-bottom: 12px; font-size: 12px; color: #4b5563; }
        .motto-box { font-size: 13px; color: #6b7280; font-style: italic; line-height: 1.4; max-height: 40px; overflow: hidden; position: relative; }
        
        /* ACTIONS */
        .card-actions { padding: 10px; background: white; border-top: 1px solid #f3f4f6; display: flex; gap: 8px; flex-wrap: wrap; }
        .btn-action { flex: 1; padding: 10px; border-radius: 6px; text-align: center; font-size: 12px; font-weight: 600; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 6px; border: 1px solid transparent; cursor: pointer; transition: 0.2s; white-space: nowrap; }
        .btn-edit { background: #f3f4f6; border-color: #d1d5db; color: #374151; }
        .btn-resend { background: #e0f2fe; color: #0369a1; border-color: #bae6fd; }
        .btn-archive { background: #fff7ed; color: #c2410c; border-color: #ffedd5; }
        .btn-approve { background: #dcfce7; color: #15803d; border-color: #bbf7d0; }
        .btn-reject { background: #fee2e2; color: #b91c1c; border-color: #fecaca; }

        /* MODAL */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); display: none; justify-content: center; align-items: center; z-index: 2000; backdrop-filter: blur(2px); }
        .modal-content { background: white; width: 95%; max-width: 500px; border-radius: 12px; padding: 20px; max-height: 90vh; overflow-y: auto; margin: 10px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        .form-label { display: block; margin-bottom: 6px; font-size: 13px; font-weight: 600; color: #374151; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; box-sizing: border-box; }
        .form-row { display: flex; gap: 10px; }
        .form-row .form-group { flex: 1; }
        .btn-submit { width: 100%; padding: 14px; background: var(--primary); color: white; border: none; border-radius: 8px; font-weight: bold; font-size: 16px; cursor: pointer; margin-top: 10px; }
        .empty-state { text-align: center; padding: 50px 20px; color: #9ca3af; }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="logout.php" class="btn-logout" onclick="return confirm('Logout?')">
        <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
    </a>
    <div class="navbar-title">Roster</div>
    <div style="width: 70px;"></div>
</nav>

<div class="container">
    <div id="toast-container" class="toast-container"></div>

    <div class="tabs">
        <a href="?view=active" class="tab-link <?= $view === 'active' ? 'active' : '' ?>">Official List</a>
        <a href="?view=pending" class="tab-link <?= $view === 'pending' ? 'active' : '' ?>">Pending</a>
        <a href="?view=archived" class="tab-link <?= $view === 'archived' ? 'active' : '' ?>">Archived</a>
    </div>

    <div class="controls">
        <form method="GET" action="contestant_manager.php" class="search-form">
            <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
            <input type="text" name="search" class="search-input" placeholder="Search by name..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn-search"><i class="fas fa-search"></i></button>
        </form>
        <?php if ($view !== 'archived'): ?>
            <button class="btn-add" onclick="openModal('addModal')"><i class="fas fa-plus-circle"></i> Add New</button>
        <?php endif; ?>
    </div>

    <?php if (empty($contestants)): ?>
        <div class="empty-state">
            <i class="fas fa-users" style="font-size: 40px; margin-bottom: 15px; opacity: 0.5;"></i>
            <p>No contestants found here.</p>
        </div>
    <?php else: ?>
        <div class="contestant-grid">
            <?php foreach ($contestants as $c): ?>
                <div class="card">
                    <div class="card-header-img">
                        <img src="./assets/uploads/contestants/<?= htmlspecialchars($c['photo']) ?>" class="card-img" onerror="this.src='./assets/images/default_user.png'">
                        <div class="card-id-badge">#<?= $c['contestant_number'] ?? '?' ?></div>
                    </div>
                    <div class="card-body">
                        <div class="c-name"><?= htmlspecialchars($c['name']) ?></div>
                        <div class="c-hometown"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($c['hometown']) ?></div>
                        <div class="stats-grid">
                            <div class="stat-item"><strong>Age:</strong> <?= $c['age'] ?></div>
                            <div class="stat-item"><strong>Height:</strong> <?= htmlspecialchars($c['height']) ?></div>
                            <div class="stat-item" style="grid-column: span 2;"><strong>Stats:</strong> <?= htmlspecialchars($c['vital_stats'] ?? '--') ?></div>
                        </div>
                        <?php if (!empty($c['motto'])): ?><div class="motto-box">"<?= htmlspecialchars($c['motto']) ?>"</div><?php endif; ?>
                    </div>
                    <div class="card-actions">
                        <?php if ($view === 'pending'): ?>
                            <a href="../api/contestant.php?action=approve&id=<?= $c['contestant_id'] ?>" class="btn-action btn-approve" onclick="return confirm('Approve?')"><i class="fas fa-check"></i> Accept</a>
                            <a href="../api/contestant.php?action=reject&id=<?= $c['contestant_id'] ?>" class="btn-action btn-reject" onclick="return confirm('Reject?')"><i class="fas fa-times"></i> Reject</a>
                        <?php elseif ($view === 'archived'): ?>
                            <a href="../api/contestant.php?action=restore&id=<?= $c['contestant_id'] ?>" class="btn-action btn-approve"><i class="fas fa-undo"></i> Restore</a>
                            <a href="../api/contestant.php?action=delete&id=<?= $c['contestant_id'] ?>" class="btn-action btn-reject" onclick="return confirm('Permanently delete?')"><i class="fas fa-trash"></i> Delete</a>
                        <?php else: ?>
                            <button class="btn-action btn-edit" onclick='openEditModal(<?= json_encode($c, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="fas fa-pen"></i> Edit</button>
                            <form action="../api/resend_email.php" method="POST" style="flex:1; display:flex;" onsubmit="return confirm('Reset password and email it?');">
                                <input type="hidden" name="user_id" value="<?= $c['id'] ?>"> <input type="hidden" name="role_type" value="Contestant">
                                <button type="submit" class="btn-action btn-resend" title="Reset Password & Resend Invite"><i class="fas fa-key"></i> Resend</button>
                            </form>
                            <a href="../api/contestant.php?action=remove&id=<?= $c['contestant_id'] ?>" class="btn-action btn-archive" onclick="return confirm('Move to archive?')"><i class="fas fa-box-archive"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div id="addModal" class="modal-overlay">
    <div class="modal-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
            <h3 style="margin:0; font-size:18px;">Add New Contestant</h3>
            <button onclick="closeModal('addModal')" style="border:none; background:none; font-size:24px; color:#666;">&times;</button>
        </div>
        <form action="../api/contestant.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create">
            <div class="form-group">
                <label class="form-label">Event</label>
                <select name="event_id" class="form-control" required>
                    <?php foreach ($my_events as $evt): ?>
                        <option value="<?= $evt['id'] ?>"><?= htmlspecialchars($evt['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Name</label><input type="text" name="name" class="form-control" required></div>
                <div class="form-group"><label class="form-label">Age</label><input type="number" name="age" class="form-control" required></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
                <div class="form-group"><label class="form-label">Password</label><input type="text" name="password" class="form-control" required></div>
            </div>
            <div class="form-group"><label class="form-label">Hometown</label><input type="text" name="hometown" class="form-control" required></div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Height</label><input type="text" name="height" class="form-control"></div>
                <div class="form-group"><label class="form-label">Bust</label><input type="number" step="0.1" name="bust" class="form-control"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Waist</label><input type="number" step="0.1" name="waist" class="form-control"></div>
                <div class="form-group"><label class="form-label">Hips</label><input type="number" step="0.1" name="hips" class="form-control"></div>
            </div>
            <div class="form-group"><label class="form-label">Motto</label><textarea name="motto" class="form-control" rows="2"></textarea></div>
            <div class="form-group"><label class="form-label">Photo</label><input type="file" name="photo" class="form-control" accept="image/*" required></div>
            <button type="submit" class="btn-submit">Save Contestant</button>
        </form>
    </div>
</div>

<div id="editModal" class="modal-overlay">
    <div class="modal-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
            <h3 style="margin:0; font-size:18px;">Edit Contestant</h3>
            <button onclick="closeModal('editModal')" style="border:none; background:none; font-size:24px; color:#666;">&times;</button>
        </div>
        <form action="../api/contestant.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="contestant_id" id="edit_id">
            <div class="form-group">
                <label class="form-label">Event</label>
                <select name="event_id" id="edit_event_id" class="form-control" required>
                    <?php foreach ($my_events as $evt): ?>
                        <option value="<?= $evt['id'] ?>"><?= htmlspecialchars($evt['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Name</label><input type="text" name="name" id="edit_name" class="form-control" required></div>
                <div class="form-group"><label class="form-label">Age</label><input type="number" name="age" id="edit_age" class="form-control" required></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" id="edit_email" class="form-control" required></div>
                <div class="form-group"><label class="form-label">New Password</label><input type="text" name="password" class="form-control" placeholder="(Optional - Leave blank to keep)"></div>
            </div>
            <div class="form-group"><label class="form-label">Hometown</label><input type="text" name="hometown" id="edit_hometown" class="form-control" required></div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Height</label><input type="text" name="height" id="edit_height" class="form-control"></div>
                <div class="form-group"><label class="form-label">Bust</label><input type="number" step="0.1" name="bust" id="edit_bust" class="form-control"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Waist</label><input type="number" step="0.1" name="waist" id="edit_waist" class="form-control"></div>
                <div class="form-group"><label class="form-label">Hips</label><input type="number" step="0.1" name="hips" id="edit_hips" class="form-control"></div>
            </div>
            <div class="form-group"><label class="form-label">Motto</label><textarea name="motto" id="edit_motto" class="form-control" rows="2"></textarea></div>
            <div class="form-group"><label class="form-label">New Photo</label><input type="file" name="photo" class="form-control" accept="image/*"></div>
            <button type="submit" class="btn-submit">Save Changes</button>
        </form>
    </div>
</div>

<script>
    function openModal(id) { document.getElementById(id).style.display = 'flex'; }
    function closeModal(id) { document.getElementById(id).style.display = 'none'; }
    function openEditModal(c) {
        document.getElementById('edit_id').value = c.contestant_id; 
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
    function showToast(message, type = 'success') {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `<span>${message}</span>`;
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