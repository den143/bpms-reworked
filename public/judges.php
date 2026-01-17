<?php
require_once __DIR__ . '/../app/core/guard.php';
requireLogin();
requireRole('Event Manager');
require_once __DIR__ . '/../app/config/database.php';

$manager_id = $_SESSION['user_id'];
$view = $_GET['view'] ?? 'active'; 
$search = trim($_GET['search'] ?? '');

// 1. GET ACTIVE EVENT
$evt_stmt = $conn->prepare("SELECT id, title FROM events WHERE manager_id = ? AND status = 'Active' LIMIT 1");
$evt_stmt->bind_param("i", $manager_id);
$evt_stmt->execute();
$active_event = $evt_stmt->get_result()->fetch_assoc();
$event_id = $active_event['id'] ?? null;

// 2. GET ACTIVE ROUND (For Status Checking)
$active_round_id = null;
if ($event_id) {
    $r_stmt = $conn->query("SELECT id FROM rounds WHERE event_id = $event_id AND status = 'Active' LIMIT 1");
    if ($r = $r_stmt->fetch_assoc()) $active_round_id = $r['id'];
}

// 3. FETCH JUDGES
$judges = [];
if ($event_id) {
    $status_filter = ($view === 'archived') ? "ej.status = 'Inactive'" : "ej.status = 'Active'";
    $search_term = "%$search%";

    $sql = "SELECT u.id, u.name, u.email, ej.id as link_id, ej.is_chairman,
                   COALESCE(jrs.status, 'Pending') as round_status
            FROM event_judges ej
            JOIN users u ON ej.judge_id = u.id
            LEFT JOIN judge_round_status jrs ON u.id = jrs.judge_id AND jrs.round_id = ?
            WHERE ej.event_id = ? 
              AND $status_filter 
              AND ej.is_deleted = 0
              AND (u.name LIKE ? OR u.email LIKE ?)
            ORDER BY ej.is_chairman DESC, u.name ASC";

    $stmt = $conn->prepare($sql);
    $round_bind = $active_round_id ?? 0;
    $stmt->bind_param("iiss", $round_bind, $event_id, $search_term, $search_term);
    $stmt->execute();
    $judges = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Manage Judges</title>
    <link rel="stylesheet" href="./assets/css/style.css?v=8">
    <link rel="stylesheet" href="./assets/fontawesome/css/all.min.css">
    <style>
        /* --- LAYOUT FIXES --- */
        body { background-color: #f3f4f6; font-family: 'Segoe UI', sans-serif; padding-bottom: 60px; }
        
        /* Forces container to be full width like your organizers.php */
        .container { max-width: 100%; margin: 0; padding: 20px; }
        
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        
        /* --- SIMPLE TABS --- */
        .tabs { 
            display: flex; gap: 20px; border-bottom: 2px solid #e5e7eb; margin-bottom: 20px; 
        }
        .tab-link { 
            padding: 10px 5px; text-decoration: none; color: #6b7280; 
            font-weight: 600; font-size: 14px; border-bottom: 3px solid transparent; 
            margin-bottom: -2px; transition: 0.2s;
        }
        .tab-link:hover { color: #111827; }
        
        /* Active State: Gold Text + Gold Line */
        .tab-link.active { 
            color: #F59E0B; 
            border-bottom-color: #F59E0B; 
        }

        /* --- ADD BUTTON --- */
        .btn-add { 
            background: #F59E0B; color: white; border: none; padding: 10px 20px; 
            border-radius: 6px; font-weight: 700; cursor: pointer; 
            display: flex; align-items: center; gap: 8px; font-size: 14px;
        }
        .btn-add:hover { background: #d97706; }

        /* --- CARD GRID --- */
        .card-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 15px; }
        
        .judge-card { background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #e5e7eb; display: flex; flex-direction: column; }
        .judge-card.chairman { border-top: 4px solid #4F46E5; } /* Chairman Indicator */

        .card-body { padding: 15px; flex: 1; }
        
        .j-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 5px; }
        .j-name { font-weight: 700; color: #1f2937; font-size: 16px; }
        
        /* Status Badges */
        .badge-status { font-size: 11px; padding: 2px 8px; border-radius: 12px; font-weight: 700; text-transform: uppercase; }
        .st-pending { background: #fff7ed; color: #c2410c; border: 1px solid #ffedd5; }
        .st-completed { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }

        .j-role-tag { font-size: 10px; background: #e0e7ff; color: #4338ca; padding: 2px 6px; border-radius: 4px; font-weight: 700; text-transform: uppercase; margin-left: 5px; vertical-align: middle; }
        .j-email { font-size: 13px; color: #6b7280; display: flex; align-items: center; gap: 5px; }

        /* --- ACTION FOOTER --- */
        .card-actions { 
            background: #f9fafb; padding: 10px; border-top: 1px solid #f3f4f6; 
            display: flex; justify-content: space-between; align-items: center; gap: 10px;
        }

        /* BUTTON STYLES (With Text) */
        .btn-action {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 6px 12px; border-radius: 5px; font-size: 12px; 
            font-weight: 600; cursor: pointer; border: 1px solid transparent;
            transition: 0.2s; text-decoration: none;
        }

        /* 1. Edit (Blue) */
        .btn-edit { background: #eff6ff; color: #2563eb; border-color: #bfdbfe; }
        .btn-edit:hover { background: #dbeafe; }

        /* 2. Remind (Green) */
        .btn-remind { background: #f0fdf4; color: #16a34a; border-color: #bbf7d0; }
        .btn-remind:hover { background: #dcfce7; }

        /* 3. Reset (Orange/Yellow) */
        .btn-reset { background: #fffbeb; color: #d97706; border-color: #fde68a; }
        .btn-reset:hover { background: #fef3c7; }

        /* 4. Archive (Red) */
        .btn-archive { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
        .btn-archive:hover { background: #fee2e2; }

        /* LEFT SIDE: Unlock Button */
        .btn-unlock { 
            background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; 
            padding: 8px 12px; border-radius: 6px; font-size: 12px; font-weight: 700; 
            cursor: pointer; display: flex; align-items: center; gap: 6px; 
        }
        .btn-unlock:hover { background: #fecaca; }
        
        .btn-unlock.disabled { 
            background: #f3f4f6; color: #9ca3af; border-color: #e5e7eb; 
            cursor: not-allowed; opacity: 0.7; 
        }

        .action-group { display: flex; gap: 5px; flex-wrap: wrap; justify-content: flex-end; }

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
                    <div class="navbar-title">Manage Judges</div>
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
                        <h2 style="margin:0;">Judge Panel</h2>
                        <?php if ($view === 'active'): ?>
                            <button class="btn-add" onclick="openModal('addModal')"><i class="fas fa-plus"></i> Add Judge</button>
                        <?php endif; ?>
                    </div>

                    <div class="tabs">
                        <a href="?view=active" class="tab-link <?= $view === 'active' ? 'active' : '' ?>">Active Judges</a>
                        <a href="?view=archived" class="tab-link <?= $view === 'archived' ? 'active' : '' ?>">Archived</a>
                    </div>

                    <?php if (!empty($judges)): ?>
                        <div class="card-grid">
                            <?php foreach ($judges as $j): ?>
                                <div class="judge-card <?= $j['is_chairman'] ? 'chairman' : '' ?>">
                                    <div class="card-body">
                                        <div class="j-header">
                                            <div>
                                                <span class="j-name"><?= htmlspecialchars($j['name']) ?></span>
                                                <?php if($j['is_chairman']): ?>
                                                    <span class="j-role-tag">Chairman</span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <?php if ($view === 'active'): ?>
                                                <?php if ($j['round_status'] === 'Submitted'): ?>
                                                    <span class="badge-status st-completed">Completed</span>
                                                <?php else: ?>
                                                    <span class="badge-status st-pending">Pending</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge-status st-pending" style="color:#666; background:#eee; border-color:#ddd;">Archived</span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="j-email">
                                            <i class="fas fa-envelope"></i> <?= htmlspecialchars($j['email']) ?>
                                        </div>
                                    </div>

                                    <div class="card-actions">
                                        
                                        <?php if ($view === 'active'): ?>
                                            <?php if ($active_round_id && $j['round_status'] === 'Submitted'): ?>
                                                <form action="../api/judge.php" method="POST" onsubmit="return confirm('Unlock Scorecard?');" style="margin:0;">
                                                    <input type="hidden" name="action" value="unlock_scorecard">
                                                    <input type="hidden" name="judge_id" value="<?= $j['id'] ?>">
                                                    <input type="hidden" name="round_id" value="<?= $active_round_id ?>">
                                                    <button type="submit" class="btn-unlock">
                                                        <i class="fas fa-lock-open"></i> Unlock
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <button type="button" class="btn-unlock disabled" disabled title="Not submitted yet">
                                                    <i class="fas fa-lock"></i> Unlock
                                                </button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div></div> 
                                        <?php endif; ?>

                                        <div class="action-group">
                                            <?php if ($view === 'active'): ?>
                                                <button class="btn-action btn-edit" onclick='openEditModal(<?= json_encode($j) ?>)'>
                                                    <i class="fas fa-pen"></i> Edit
                                                </button>
                                                
                                                <form action="../api/resend_email.php" method="POST" style="margin:0;" onsubmit="return confirm('Resend Invite?');">
                                                    <input type="hidden" name="user_id" value="<?= $j['id'] ?>">
                                                    <input type="hidden" name="action_type" value="reminder">
                                                    <button type="submit" class="btn-action btn-remind" title="Send Email"><i class="fas fa-paper-plane"></i> Remind</button>
                                                </form>

                                                <form action="../api/resend_email.php" method="POST" style="margin:0;" onsubmit="return confirm('Reset Password?');">
                                                    <input type="hidden" name="user_id" value="<?= $j['id'] ?>">
                                                    <input type="hidden" name="action_type" value="reset">
                                                    <button type="submit" class="btn-action btn-reset" title="Reset Password"><i class="fas fa-key"></i> Reset</button>
                                                </form>

                                                <form action="../api/judge.php" method="POST" style="margin:0;" onsubmit="return confirm('Archive?');">
                                                    <input type="hidden" name="action" value="remove">
                                                    <input type="hidden" name="id" value="<?= $j['link_id'] ?>">
                                                    <button type="submit" class="btn-action btn-archive"><i class="fas fa-archive"></i> Archive</button>
                                                </form>

                                            <?php else: ?>
                                                <form action="../api/judge.php" method="POST" style="margin:0;">
                                                    <input type="hidden" name="action" value="restore">
                                                    <input type="hidden" name="id" value="<?= $j['link_id'] ?>">
                                                    <button type="submit" class="btn-action btn-remind"><i class="fas fa-undo"></i> Restore</button>
                                                </form>
                                                <form action="../api/judge.php" method="POST" style="margin:0;" onsubmit="return confirm('Delete Permanently?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= $j['link_id'] ?>">
                                                    <button type="submit" class="btn-action btn-archive"><i class="fas fa-trash"></i> Delete</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align:center; padding:40px; color:#9ca3af;">No judges found.</div>
                    <?php endif; ?>

                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="addModal" class="modal-overlay">
        <div class="modal-content">
            <h3 style="margin-top:0;">Add Judge</h3>
            <form action="../api/judge.php" method="POST">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="event_id" value="<?= $active_event['id'] ?? '' ?>">
                
                <div class="form-group"><label>Name</label><input type="text" name="name" class="form-control" required></div>
                <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" required></div>
                <div class="form-group"><label>Password</label><input type="text" name="password" class="form-control" required></div>
                <div class="form-group" style="display:flex; gap:10px; align-items:center;">
                    <input type="checkbox" name="is_chairman" value="1" id="chkChair">
                    <label for="chkChair" style="margin:0;">Set as Chairman</label>
                </div>
                
                <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:20px;">
                    <button type="button" onclick="closeModal('addModal')" style="background:#e5e7eb; border:none; padding:10px 15px; border-radius:5px; cursor:pointer;">Cancel</button>
                    <button type="submit" class="btn-submit">Save Judge</button>
                </div>
            </form>
        </div>
    </div>

    <div id="editModal" class="modal-overlay">
        <div class="modal-content">
            <h3 style="margin-top:0;">Edit Judge</h3>
            <form action="../api/judge.php" method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="link_id" id="edit_link_id">
                
                <div class="form-group"><label>Name</label><input type="text" name="name" id="edit_name" class="form-control" required></div>
                <div class="form-group"><label>Email</label><input type="email" name="email" id="edit_email" class="form-control" required></div>
                <div class="form-group"><label>New Password (Optional)</label><input type="text" name="password" class="form-control" placeholder="Leave blank to keep"></div>
                <div class="form-group" style="display:flex; gap:10px; align-items:center;">
                    <input type="checkbox" name="is_chairman" id="edit_chairman" value="1">
                    <label for="edit_chairman" style="margin:0;">Set as Chairman</label>
                </div>
                
                <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:20px;">
                    <button type="button" onclick="closeModal('editModal')" style="background:#e5e7eb; border:none; padding:10px 15px; border-radius:5px; cursor:pointer;">Cancel</button>
                    <button type="submit" class="btn-submit">Update</button>
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
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }

        function openEditModal(j) {
            document.getElementById('edit_link_id').value = j.link_id;
            document.getElementById('edit_name').value = j.name;
            document.getElementById('edit_email').value = j.email;
            document.getElementById('edit_chairman').checked = (j.is_chairman == 1);
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