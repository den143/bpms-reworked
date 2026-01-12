<?php
require_once __DIR__ . '/../app/core/guard.php';
requireLogin();
requireRole('Event Manager');
require_once __DIR__ . '/../app/config/database.php';

$manager_id = $_SESSION['user_id'];
$view = $_GET['view'] ?? 'active'; 
$status_filter = ($view === 'archived') ? 'Inactive' : 'Active';

$evt_sql = "SELECT id, title FROM events WHERE manager_id = ? AND status = 'Active' LIMIT 1";
$evt_stmt = $conn->prepare($evt_sql);
$evt_stmt->bind_param("i", $manager_id);
$evt_stmt->execute();
$active_event = $evt_stmt->get_result()->fetch_assoc();

$judges = [];
if ($active_event) {
    $event_id = $active_event['id'];
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Judges - BPMS</title>
    <link rel="stylesheet" href="./assets/css/style.css?v=5">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <div id="globalSpinner" class="loading-overlay">
        <div style="text-align:center;">
            <div class="spinner"></div>
            <p style="margin-top:15px; font-weight:600; color:#374151;">Processing Request...</p>
        </div>
    </div>

    <div id="sidebarOverlay" class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <div class="main-wrapper">
        <?php require_once __DIR__ . '/../app/views/partials/sidebar.php'; ?>

        <div class="content-area">
            <div class="navbar">
                <div style="display:flex; align-items:center; gap: 15px;">
                    <button class="menu-toggle" onclick="toggleSidebar()">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="navbar-title">Manage Judges</div>
                </div>
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
                            <p style="color: #6b7280; font-size: 13px; margin-top:5px;">Event: <strong><?= htmlspecialchars($active_event['title']) ?></strong></p>
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

                    <div class="table-card" style="overflow-x:auto;">
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
                                            <td><?= $j['is_chairman'] ? '<span class="badge-chairman">Chairman</span>' : '<span class="badge-judge">Judge</span>' ?></td>
                                            <td style="text-align:right;">
                                                <div style="display:inline-flex;">
                                                <?php if ($view === 'active'): ?>
                                                    <button class="icon-btn btn-edit" onclick='openEditModal(<?= json_encode($j) ?>)' title="Edit Details"><i class="fas fa-pen"></i></button>
                                                    
                                                    <form action="../api/resend_email.php" method="POST" style="margin:0;" onsubmit="return confirm('Send a reminder email?');">
                                                        <input type="hidden" name="user_id" value="<?= $j['judge_id'] ?>">
                                                        <input type="hidden" name="action_type" value="reminder">
                                                        <button type="submit" class="icon-btn btn-reminder" title="Send Reminder"><i class="fas fa-paper-plane"></i></button>
                                                    </form>

                                                    <form action="../api/resend_email.php" method="POST" style="margin:0;" onsubmit="return confirm('WARNING: Reset password?');">
                                                        <input type="hidden" name="user_id" value="<?= $j['judge_id'] ?>">
                                                        <input type="hidden" name="action_type" value="reset">
                                                        <button type="submit" class="icon-btn btn-reset" title="Reset Password"><i class="fas fa-key"></i></button>
                                                    </form>

                                                    <button type="button" class="icon-btn btn-archive" onclick="triggerAction('remove', <?= $j['link_id'] ?>)" title="Archive"><i class="fas fa-archive"></i></button>
                                                <?php else: ?>
                                                    <button type="button" class="icon-btn btn-restore" onclick="triggerAction('restore', <?= $j['link_id'] ?>)" title="Restore"><i class="fas fa-undo"></i></button>
                                                    <button type="button" class="icon-btn btn-delete" onclick="triggerAction('delete', <?= $j['link_id'] ?>)" title="Remove Completely"><i class="fas fa-trash"></i></button>
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
            <form id="addJudgeForm" action="../api/judge.php" method="POST">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="event_id" value="<?= $active_event['id'] ?? '' ?>">
                
                <div class="form-group"><label>Full Name</label><input type="text" name="name" class="form-control" required></div>
                <div class="form-group"><label>Email (Login)</label><input type="email" name="email" class="form-control" required></div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" id="addPass" class="form-control" required>
                    <i class="fas fa-eye toggle-password" onclick="togglePassword('addPass', this)"></i>
                </div>
                <div class="form-group" style="display:flex; align-items:center; gap:8px;">
                    <input type="checkbox" name="is_chairman" id="chkAdd" value="1">
                    <label for="chkAdd" style="margin:0;">Set as Chairman of Judges?</label>
                </div>
                <div style="text-align:right; margin-top:20px;">
                    <button type="button" onclick="closeModal('addModal')" style="background:#e5e7eb; border:none; padding:10px 20px; border-radius:6px; font-weight:600; color:#374151; cursor: pointer;">Cancel</button>
                    <button type="submit" id="btnAddSubmit" style="background:#F59E0B; color:white; border:none; padding:10px 20px; border-radius:6px; font-weight:600; margin-left:10px; cursor:pointer;">Save Judge</button>
                </div>
            </form>
        </div>
    </div>

    <div id="editModal" class="modal-overlay">
        <div class="modal-content">
            <h3 style="margin-bottom:20px; color:#111827;">Edit Judge</h3>
            <form id="editJudgeForm" action="../api/judge.php" method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="link_id" id="edit_link_id">
                <input type="hidden" name="judge_id" id="edit_judge_id">
                
                <div class="form-group"><label>Full Name</label><input type="text" name="name" id="edit_name" class="form-control" required></div>
                <div class="form-group"><label>Email</label><input type="email" name="email" id="edit_email" class="form-control" required></div>
                <div class="form-group">
                    <label>Change Password</label>
                    <input type="password" name="password" id="editPass" class="form-control" placeholder="Leave empty to keep">
                    <i class="fas fa-eye toggle-password" onclick="togglePassword('editPass', this)"></i>
                </div>
                <div class="form-group" style="display:flex; align-items:center; gap:8px;">
                    <input type="checkbox" name="is_chairman" id="edit_chairman" value="1">
                    <label for="edit_chairman" style="margin:0;">Set as Chairman?</label>
                </div>
                <div style="text-align:right; margin-top:20px;">
                    <button type="button" onclick="closeModal('editModal')" style="background:#e5e7eb; border:none; padding:10px 20px; border-radius:6px; font-weight:600; color:#374151; cursor: pointer;">Cancel</button>
                    <button type="submit" id="btnEditSubmit" style="background:#3b82f6; color:white; border:none; padding:10px 20px; border-radius:6px; font-weight:600; margin-left:10px; cursor:pointer;">Update</button>
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
        
        function openEditModal(judge) {
            document.getElementById('edit_link_id').value = judge.link_id;
            document.getElementById('edit_judge_id').value = judge.judge_id;
            document.getElementById('edit_name').value = judge.name;
            document.getElementById('edit_email').value = judge.email;
            document.getElementById('edit_chairman').checked = (judge.is_chairman == 1);
            openModal('editModal');
        }

        function togglePassword(inputId, icon) {
            const input = document.getElementById(inputId);
            if (input.type === "password") {
                input.type = "text";
                icon.classList.replace("fa-eye", "fa-eye-slash");
            } else {
                input.type = "password";
                icon.classList.replace("fa-eye-slash", "fa-eye");
            }
        }

        // --- SPINNER LOGIC (Standard Submit) ---
        function setupFormLoader(formId, btnId) {
            const form = document.getElementById(formId);
            const btn = document.getElementById(btnId);
            
            if(form) {
                form.onsubmit = function() {
                    document.getElementById('globalSpinner').style.display = 'flex';
                    btn.disabled = true;
                    btn.innerText = "Processing...";
                };
            }
        }

        setupFormLoader('addJudgeForm', 'btnAddSubmit');
        setupFormLoader('editJudgeForm', 'btnEditSubmit');

        // Action Logic
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

        // Toast Notification
        function showToast(message, type = 'success') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            let bgClass = type;
            let icon = '<i class="fas fa-check-circle"></i>';
            
            if(type === 'error') icon = '<i class="fas fa-exclamation-circle"></i>';
            if(type === 'warning') {
                icon = '<i class="fas fa-exclamation-triangle"></i>';
                toast.style.borderLeft = "4px solid #F59E0B"; 
            }

            toast.className = `toast ${type}`;
            toast.innerHTML = `${icon} <span>${message}</span>`;
            container.appendChild(toast);
            setTimeout(() => { toast.remove(); }, 5000);
        }
        
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('success')) showToast(urlParams.get('success'), 'success');
        if (urlParams.has('error')) showToast(urlParams.get('error'), 'error');
        if (urlParams.has('warning')) showToast(urlParams.get('warning'), 'warning'); 

        if (urlParams.has('success') || urlParams.has('error') || urlParams.has('warning')) {
            const newUrl = window.location.pathname + (urlParams.has('view') ? '?view=' + urlParams.get('view') : '');
            window.history.replaceState({}, document.title, newUrl);
        }
    </script>
</body>
</html>