<?php
require_once __DIR__ . '/../app/core/guard.php';
requireLogin();
requireRole('Event Manager');
require_once __DIR__ . '/../app/config/database.php';

$manager_id = $_SESSION['user_id'];
$view = $_GET['view'] ?? 'active'; 
$status_filter = ($view === 'archived') ? 'Inactive' : 'Active';

// 1. Get Active Event
$evt_sql = "SELECT id, name FROM events WHERE user_id = ? AND status = 'Active' LIMIT 1";
$evt_stmt = $conn->prepare($evt_sql);
$evt_stmt->bind_param("i", $manager_id);
$evt_stmt->execute();
$active_event = $evt_stmt->get_result()->fetch_assoc();

$judges = [];
if ($active_event) {
    $event_id = $active_event['id'];
    
    // 2. Fetch Judges (Filtered by is_deleted = 0)
    $sql = "SELECT ej.id as link_id, ej.is_chairman, ej.judge_id, 
                   u.name, u.email 
            FROM event_judges ej 
            JOIN users u ON ej.judge_id = u.id 
            WHERE ej.event_id = ? 
              AND ej.status = ?
              AND ej.is_deleted = 0";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $event_id, $status_filter);
    $stmt->execute();
    $judges = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Judges - BPMS</title>
    <link rel="stylesheet" href="./assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .header-actions { display: flex; gap: 10px; }

        .btn-add { background-color: #F59E0B; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 13px; }
        .btn-add:hover { background-color: #d97706; }
        .btn-secondary { background-color: white; border: 1px solid #d1d5db; color: #374151; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 5px; }
        .btn-secondary:hover { background-color: #f3f4f6; }

        .table-card { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); overflow: hidden; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 15px; text-align: left; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
        .data-table th { background-color: #f9fafb; font-weight: 600; color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
        
        .badge-chairman { background: #dbeafe; color: #1e40af; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: bold; text-transform: uppercase; display: inline-block; }
        .badge-judge { color: #6b7280; font-size: 12px; font-weight: 500; }

        /* Icon Buttons */
        .icon-btn { 
            width: 32px; height: 32px; border-radius: 6px; border: none; cursor: pointer; 
            display: inline-flex; align-items: center; justify-content: center; 
            transition: all 0.2s; color: white; font-size: 13px; margin-right: 4px;
        }
        .btn-edit { background: #3b82f6; color: white; }
        .btn-edit:hover { background: #2563eb; }
        
        .btn-reminder { background: #0ea5e9; color: white; } 
        .btn-reminder:hover { background: #0284c7; }
        
        .btn-reset { background: #f59e0b; color: white; }
        .btn-reset:hover { background: #d97706; }
        
        /* Archive Button (Orange) */
        .btn-archive { background: #f97316; color: white; } 
        .btn-archive:hover { background: #ea580c; }
        
        /* Restore Button (Green) */
        .btn-restore { background: #10b981; color: white; }
        .btn-restore:hover { background: #059669; }

        /* Delete Permanently (Red) */
        .btn-delete { background: #ef4444; color: white; }
        .btn-delete:hover { background: #dc2626; }

        /* Modal */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background: white; padding: 25px; width: 400px; border-radius: 12px; }
        .form-group { margin-bottom: 15px; position: relative; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; }
        .toggle-password { position: absolute; right: 10px; top: 35px; cursor: pointer; color: #9ca3af; }
    </style>
</head>
<body>

    <div class="main-wrapper">
        <?php require_once __DIR__ . '/../app/views/partials/sidebar.php'; ?>

        <div class="content-area">
            <div class="navbar">
                <div class="navbar-title">Manage Judges</div>
            </div>

            <div class="container">
                <div id="toast-container" class="toast-container"></div>

                <?php if (!$active_event): ?>
                    <div style="text-align:center; padding: 40px; color: #6b7280; background:white; border-radius:8px;">
                        <i class="fas fa-calendar-plus" style="font-size: 40px; margin-bottom: 10px;"></i>
                        <h2>No Active Event</h2>
                        <p>Please go to Settings to create an event.</p>
                    </div>
                <?php else: ?>

                    <div class="page-header">
                        <div>
                            <h2 style="color: #111827; margin:0;">
                                <?= ($view === 'archived') ? 'Archived Judges' : 'Judge Panel' ?>
                            </h2>
                            <p style="color: #6b7280; font-size: 13px; margin-top:5px;">Event: <strong><?= htmlspecialchars($active_event['name']) ?></strong></p>
                        </div>
                        
                        <div class="header-actions">
                            <?php if ($view === 'active'): ?>
                                <a href="?view=archived" class="btn-secondary"><i class="fas fa-archive"></i> View Archived</a>
                                <button class="btn-add" onclick="openModal('addModal')"><i class="fas fa-plus"></i> Add Judge</button>
                            <?php else: ?>
                                <a href="?view=active" class="btn-secondary">← Back to Active List</a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="table-card">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email (Username)</th>
                                    <th>Role</th>
                                    <th style="text-align:right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($judges)): ?>
                                    <tr><td colspan="4" style="text-align:center; padding:30px; color:#9ca3af;">No judges found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($judges as $j): ?>
                                        <tr>
                                            <td style="font-weight:600; color:#1f2937;"><?= htmlspecialchars($j['name']) ?></td>
                                            <td style="color:#4b5563; font-size:13px;"><i class="fas fa-envelope" style="color:#9ca3af; width:15px;"></i> <?= htmlspecialchars($j['email']) ?></td>
                                            <td>
                                                <?= $j['is_chairman'] ? '<span class="badge-chairman">Chairman</span>' : '<span class="badge-judge">Judge</span>' ?>
                                            </td>
                                            <td style="text-align:right;">
                                                <div style="display:inline-flex;">
                                                <?php if ($view === 'active'): ?>
                                                    
                                                    <button class="icon-btn btn-edit" onclick='openEditModal(<?= json_encode($j) ?>)' title="Edit Details">
                                                        <i class="fas fa-pen"></i>
                                                    </button>
                                                    
                                                    <form action="../api/resend_email.php" method="POST" style="margin:0;" onsubmit="return confirm('Send a reminder email? (Password will NOT be changed)');">
                                                        <input type="hidden" name="user_id" value="<?= $j['judge_id'] ?>">
                                                        <input type="hidden" name="action_type" value="reminder">
                                                        <button type="submit" class="icon-btn btn-reminder" title="Send Login Reminder">
                                                            <i class="fas fa-paper-plane"></i>
                                                        </button>
                                                    </form>

                                                    <form action="../api/resend_email.php" method="POST" style="margin:0;" onsubmit="return confirm('WARNING: This will RESET the password and email the new one. Proceed?');">
                                                        <input type="hidden" name="user_id" value="<?= $j['judge_id'] ?>">
                                                        <input type="hidden" name="action_type" value="reset">
                                                        <button type="submit" class="icon-btn btn-reset" title="Reset Password & Email">
                                                            <i class="fas fa-key"></i>
                                                        </button>
                                                    </form>

                                                    <button type="button" class="icon-btn btn-archive" onclick="triggerAction('remove', <?= $j['link_id'] ?>)" title="Archive">
                                                        <i class="fas fa-archive"></i>
                                                    </button>

                                                <?php else: ?>
                                                    <button type="button" class="icon-btn btn-restore" onclick="triggerAction('restore', <?= $j['link_id'] ?>)" title="Restore">
                                                        <i class="fas fa-undo"></i>
                                                    </button>

                                                    <button type="button" class="icon-btn btn-delete" onclick="triggerAction('delete', <?= $j['link_id'] ?>)" title="Remove Completely">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
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

    <form id="actionForm" action="../api/judge.php" method="POST" style="display:none;">
        <input type="hidden" name="action" id="formAction">
        <input type="hidden" name="id" id="formId">
    </form>

    <div id="addModal" class="modal-overlay">
        <div class="modal-content">
            <h3 style="margin-bottom:20px; color:#111827;">Add New Judge</h3>
            <form action="../api/judge.php" method="POST">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="event_id" value="<?= $active_event['id'] ?? '' ?>">
                <div class="form-group"><label style="font-size:13px; font-weight:600; color:#374151;">Full Name</label><input type="text" name="name" class="form-control" required></div>
                <div class="form-group"><label style="font-size:13px; font-weight:600; color:#374151;">Email (Login)</label><input type="email" name="email" class="form-control" required></div>
                <div class="form-group">
                    <label style="font-size:13px; font-weight:600; color:#374151;">Password</label>
                    <input type="password" name="password" id="addPass" class="form-control" required>
                    <i class="fas fa-eye toggle-password" onclick="togglePassword('addPass', this)"></i>
                </div>
                <div class="form-group" style="display:flex; align-items:center; gap:8px;">
                    <input type="checkbox" name="is_chairman" id="chkAdd" value="1" style="width:16px; height:16px;">
                    <label for="chkAdd" style="margin:0; font-size:13px; color:#374151; font-weight:600;">Set as Chairman of Judges?</label>
                </div>
                <div style="text-align:right; margin-top:20px;">
                    <button type="button" onclick="closeModal('addModal')" style="background:#e5e7eb; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; font-weight:600; color:#374151;">Cancel</button>
                    <button type="submit" style="background:#F59E0B; color:white; border:none; padding:10px 20px; border-radius:6px; font-weight:600; margin-left:10px; cursor:pointer;">Save Judge</button>
                </div>
            </form>
        </div>
    </div>

    <div id="editModal" class="modal-overlay">
        <div class="modal-content">
            <h3 style="margin-bottom:20px; color:#111827;">Edit Judge</h3>
            <form action="../api/judge.php" method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="link_id" id="edit_link_id">
                <input type="hidden" name="judge_id" id="edit_judge_id">
                <div class="form-group"><label style="font-size:13px; font-weight:600; color:#374151;">Full Name</label><input type="text" name="name" id="edit_name" class="form-control" required></div>
                <div class="form-group"><label style="font-size:13px; font-weight:600; color:#374151;">Email</label><input type="email" name="email" id="edit_email" class="form-control" required></div>
                <div class="form-group">
                    <label style="font-size:13px; font-weight:600; color:#374151;">Change Password</label>
                    <input type="password" name="password" id="editPass" class="form-control" placeholder="Leave empty to keep">
                    <i class="fas fa-eye toggle-password" onclick="togglePassword('editPass', this)"></i>
                </div>
                <div class="form-group" style="display:flex; align-items:center; gap:8px;">
                    <input type="checkbox" name="is_chairman" id="edit_chairman" value="1" style="width:16px; height:16px;">
                    <label for="edit_chairman" style="margin:0; font-size:13px; color:#374151; font-weight:600;">Set as Chairman?</label>
                </div>
                <div style="text-align:right; margin-top:20px;">
                    <button type="button" onclick="closeModal('editModal')" style="background:#e5e7eb; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; font-weight:600; color:#374151;">Cancel</button>
                    <button type="submit" style="background:#3b82f6; color:white; border:none; padding:10px 20px; border-radius:6px; font-weight:600; margin-left:10px; cursor:pointer;">Update</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(id) { document.getElementById(id).style.display = 'flex'; }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }
        
        function openEditModal(judge) {
            document.getElementById('edit_link_id').value = judge.link_id;
            document.getElementById('edit_judge_id').value = judge.judge_id;
            document.getElementById('edit_name').value = judge.name;
            document.getElementById('edit_email').value = judge.email;
            document.getElementById('edit_chairman').checked = (judge.is_chairman == 1);
            openModal('editModal');
        }

        // NEW SECURE ACTION HANDLER
        function triggerAction(action, id) {
            let msg = '';
            if(action === 'remove') msg = 'Archive this judge?';
            else if(action === 'restore') msg = 'Restore this judge?';
            else if(action === 'delete') msg = 'PERMANENTLY REMOVE?\n\nThey will disappear from the list, but their account credentials remain in the database.';

            if (confirm(msg)) {
                document.getElementById('formAction').value = action;
                document.getElementById('formId').value = id;
                document.getElementById('actionForm').submit();
            }
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