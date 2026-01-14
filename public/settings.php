<?php
require_once __DIR__ . '/../app/core/guard.php';
requireLogin();

require_once __DIR__ . '/../app/config/database.php';

$u_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';

// --- ROLE CHECK ---
$is_event_manager = ($role === 'Event Manager');

// --- DETERMINE ACTIVE TAB ---
$default_tab = $is_event_manager ? 'event_details' : 'account';
$active_tab = $_GET['tab'] ?? $default_tab;

if (!$is_event_manager && $active_tab !== 'account') {
    $active_tab = 'account';
}

// --- 1. FETCH DATA ---
$active_event = null;
$tickets = [];
$total_unused = 0;
$total_used = 0;
$all_events = [];

if ($is_event_manager) {
    $active_evt_query = $conn->prepare("SELECT * FROM events WHERE manager_id = ? AND status = 'Active' AND is_deleted = 0 LIMIT 1");
    $active_evt_query->bind_param("i", $u_id);
    $active_evt_query->execute();
    $active_event = $active_evt_query->get_result()->fetch_assoc();

    if ($active_event) {
        $event_id = $active_event['id'];
        
        $res = $conn->query("SELECT status, COUNT(*) as count FROM tickets WHERE event_id = $event_id GROUP BY status");
        while($row = $res->fetch_assoc()) {
            if ($row['status'] == 'Unused') $total_unused = $row['count'];
            if ($row['status'] == 'Used') $total_used = $row['count'];
        }
        
        $tickets = $conn->query("SELECT * FROM tickets WHERE event_id = $event_id ORDER BY generated_at DESC LIMIT 500")->fetch_all(MYSQLI_ASSOC);
    }

    $hist_stmt = $conn->prepare("SELECT * FROM events WHERE manager_id = ? AND is_deleted = 0 ORDER BY created_at DESC");
    $hist_stmt->bind_param("i", $u_id);
    $hist_stmt->execute();
    $all_events = $hist_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// --- 2. HANDLE POST REQUESTS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$is_event_manager && (isset($_POST['update_event_details']) || isset($_POST['switch_event']) || isset($_POST['remove_event']))) {
        die("Unauthorized Action");
    }

    // A. Update Event
    if (isset($_POST['update_event_details'])) {
        $eid = (int)$_POST['event_id'];
        $title = trim($_POST['title']);
        $date = $_POST['event_date'];
        $venue = trim($_POST['venue']);
        $stmt = $conn->prepare("UPDATE events SET title=?, event_date=?, venue=? WHERE id=? AND manager_id=?");
        $stmt->bind_param("sssii", $title, $date, $venue, $eid, $u_id);
        if ($stmt->execute()) { $_SESSION['success'] = "Event details updated successfully."; } 
        else { $_SESSION['error'] = "Failed to update event."; }
        header("Location: settings.php?tab=event_details");
        exit();
    }

    // B. Update Profile
    if (isset($_POST['update_profile'])) {
        $name = trim($_POST['my_name']);
        $current_pass = $_POST['current_password'];
        $new_pass = trim($_POST['my_password']);
        $verifyStmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $verifyStmt->bind_param("i", $u_id);
        $verifyStmt->execute();
        $stored_hash = $verifyStmt->get_result()->fetch_assoc()['password'];
        if (!password_verify($current_pass, $stored_hash)) {
            $_SESSION['error'] = "Incorrect current password. Changes NOT saved.";
            header("Location: settings.php?tab=account");
            exit();
        }
        if (!empty($new_pass)) {
            $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET name=?, password=? WHERE id=?");
            $stmt->bind_param("ssi", $name, $hashed, $u_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET name=? WHERE id=?");
            $stmt->bind_param("si", $name, $u_id);
        }
        if ($stmt->execute()) { $_SESSION['success'] = "Account updated successfully."; $_SESSION['name'] = $name; } 
        else { $_SESSION['error'] = "Failed to update account."; }
        header("Location: settings.php?tab=account");
        exit();
    }

    // C. Switch Event
    if (isset($_POST['switch_event'])) {
        $target_id = (int)$_POST['event_id'];
        $password_check = $_POST['password_check'];
        $v_stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $v_stmt->bind_param("i", $u_id);
        $v_stmt->execute();
        $real_hash = $v_stmt->get_result()->fetch_assoc()['password'];
        if (!password_verify($password_check, $real_hash)) {
            $_SESSION['error'] = "Authentication Failed: Incorrect Password.";
            header("Location: settings.php?tab=history");
            exit();
        }
        $conn->begin_transaction();
        try {
            $conn->query("UPDATE events SET status = 'Inactive' WHERE manager_id = $u_id");
            $conn->query("UPDATE events SET status = 'Active' WHERE id = $target_id AND manager_id = $u_id");
            $conn->commit();
            $_SESSION['success'] = "Event switched successfully.";
            header("Location: dashboard.php");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "Database Error: " . $e->getMessage();
            header("Location: settings.php?tab=history");
            exit();
        }
    }

    // D. Remove Event
    if (isset($_POST['remove_event'])) {
        $target_id = (int)$_POST['event_id'];
        $password_check = $_POST['password_check'];
        if ($active_event && $target_id == $active_event['id']) {
            $_SESSION['error'] = "Cannot remove active event. Switch first.";
            header("Location: settings.php?tab=history");
            exit();
        }
        $v_stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $v_stmt->bind_param("i", $u_id);
        $v_stmt->execute();
        $real_hash = $v_stmt->get_result()->fetch_assoc()['password'];
        if (!password_verify($password_check, $real_hash)) {
            $_SESSION['error'] = "Authentication Failed: Incorrect Password.";
            header("Location: settings.php?tab=history");
            exit();
        }
        $stmt = $conn->prepare("UPDATE events SET is_deleted = 1, status = 'Inactive' WHERE id = ? AND manager_id = ?");
        $stmt->bind_param("ii", $target_id, $u_id);
        if ($stmt->execute()) { $_SESSION['success'] = "Event moved to archive."; } 
        else { $_SESSION['error'] = "Failed to remove event."; }
        header("Location: settings.php?tab=history");
        exit();
    }
}

$me_stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
$me_stmt->bind_param("i", $u_id);
$me_stmt->execute();
$me = $me_stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <title>Settings - BPMS</title>
    <link rel="stylesheet" href="./assets/css/style.css">
    <link rel="stylesheet" href="./assets/fontawesome/css/all.min.css">
    <style>
        /* --- GLOBAL RESET --- */
        body, html { margin: 0; padding: 0; width: 100%; box-sizing: border-box; }

        /* --- NAVBAR STYLES --- */
        .navbar {
            display: flex; align-items: center; justify-content: flex-start;
            background: white; padding: 15px 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05); width: 100%;
        }
        .navbar-title { font-size: 20px; font-weight: 600; color: #111827; }
        .toggle-btn { display: none; font-size: 20px; cursor: pointer; margin-right: 15px; color: #1f2937; }

        /* --- DESKTOP LAYOUT --- */
        .settings-container { display: flex; gap: 30px; align-items: flex-start; margin-top: 30px; }
        
        .settings-sidebar {
            width: 250px; background: white; border-radius: 12px; overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05); flex-shrink: 0;
        }
        .tab-btn {
            display: block; width: 100%; padding: 15px 20px; text-align: left;
            background: none; border: none; border-bottom: 1px solid #f3f4f6;
            cursor: pointer; font-size: 14px; color: #6b7280; font-weight: 600;
            transition: all 0.2s; text-decoration: none; display: flex; align-items: center; gap: 10px;
        }
        .tab-btn:hover { background-color: #f9fafb; color: #374151; }
        .tab-btn.active { background-color: #fffbeb; color: #F59E0B; border-left: 4px solid #F59E0B; }
        .tab-btn i { width: 20px; text-align: center; }

        .settings-content { flex-grow: 1; min-width: 0; }
        .card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); display: none; margin-bottom: 30px; }
        .card.active { display: block; animation: fadeIn 0.3s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

        .card-header { margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #f3f4f6; display:flex; justify-content:space-between; align-items:center; }
        .card-header h3 { font-size: 18px; color: #111827; margin:0; }
        .card-header p { font-size: 13px; color: #6b7280; margin-top: 5px; margin-bottom:0; }
        .form-group { margin-bottom: 20px; position: relative; }
        .form-label { display: block; margin-bottom: 8px; font-weight: 600; color: #374151; font-size: 13px; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; }
        .btn-save { background-color: #F59E0B; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: bold; }
        
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 15px; text-align: left; border-bottom: 1px solid #f3f4f6; }
        .badge { padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; }
        .badge-active { background: #d1fae5; color: #065f46; } .badge-inactive { background: #f3f4f6; color: #6b7280; }
        
        .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px; }
        .stat-box { background: #f9fafb; padding: 15px; border-radius: 6px; text-align: center; border: 1px solid #e5e7eb; }
        .stat-num { font-size: 24px; font-weight: bold; color: #1f2937; } .stat-label { font-size: 12px; color: #6b7280; text-transform: uppercase; }
        
        .btn-generate { background: #3b82f6; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: bold; font-size:13px; }
        .btn-print { background: #111827; color: white; text-decoration: none; padding: 8px 15px; border-radius: 6px; font-size: 13px; display: inline-flex; align-items: center; gap: 5px; cursor: pointer; border: none; font-weight: bold; }
        .btn-clear { background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; padding: 8px 15px; border-radius: 6px; cursor: pointer; font-size: 13px; }
        
        .ticket-scroll-area { max-height: 400px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 6px; }
        .ticket-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .ticket-table th, .ticket-table td { padding: 10px; border-bottom: 1px solid #f3f4f6; }
        .status-used { color: #dc2626; background: #fee2e2; padding: 2px 6px; border-radius: 4px; font-size: 11px; }
        .status-unused { color: #059669; background: #ecfdf5; padding: 2px 6px; border-radius: 4px; font-size: 11px; }
        
        .report-item { display: flex; align-items: center; gap: 20px; padding: 20px; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 15px; }
        .report-icon { width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0; }
        .icon-pdf { background: #eff6ff; color: #3b82f6; }
        .btn-report { background: #0f172a; color: white; text-decoration: none; padding: 10px 20px; border-radius: 6px; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; }

        /* Modals */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); display: none; justify-content: center; align-items: center; z-index: 2000; }
        .modal-content { background: white; padding: 25px; width: 400px; border-radius: 12px; position: relative; max-width: 90%; }
        .modal-actions { text-align: right; margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px; }
        .btn-cancel, .btn-confirm, .btn-danger { border: none; padding: 10px 15px; border-radius: 6px; cursor: pointer; font-weight: 600; }
        .btn-cancel { background: #e5e7eb; } .btn-confirm { background: #F59E0B; color:white; } .btn-danger { background: #dc2626; color:white; }
        .loading-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); display: none; justify-content: center; align-items: center; z-index: 9999; flex-direction: column; color: white; }
        .spinner { border: 4px solid #f3f3f3; border-top: 4px solid #F59E0B; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin-bottom: 15px; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .toggle-password { position: absolute; right: 10px; top: 35px; cursor: pointer; color: #9ca3af; }

        /* --- PRINT ONLY STYLES --- */
        @media print {
            .sidebar, .navbar, .settings-sidebar, .card-header button, .no-print, .toast-container { display: none !important; }
            .settings-content { margin: 0; padding: 0; }
            .card { box-shadow: none; border: none; padding: 0; margin: 0; }
            body, html { background: white; height: auto; overflow: visible; }
            .main-wrapper, .content-area, .container, .settings-container { display: block; width: 100%; height: auto; margin: 0; padding: 0; }
            .card:not(.active), .stats-grid, .ticket-scroll-area, form { display: none !important; }

            #printable-tickets {
                display: grid !important;
                grid-template-columns: repeat(3, 1fr);
                gap: 15px; padding: 10px; width: 100%;
            }

            .ticket-card {
                border: 2px dashed #000; padding: 10px; text-align: center;
                height: 160px; page-break-inside: avoid;
                display: flex; flex-direction: column; justify-content: center;
                position: relative; background: white;
            }

            .t-header { font-size: 12pt; font-weight: 900; text-transform: uppercase; margin-bottom: 5px; line-height: 1.1; }
            .t-venue { font-size: 9pt; margin-bottom: 10px; }
            .t-code-box { 
                border: 2px solid #000; display: inline-block; padding: 5px 15px; 
                font-size: 16pt; font-weight: bold; letter-spacing: 2px;
                margin: 0 auto; background: #f3f4f6; -webkit-print-color-adjust: exact; print-color-adjust: exact;
            }
            .t-admit { font-size: 7pt; text-transform: uppercase; margin-top: 8px; font-weight: bold; letter-spacing: 1px; }
            .t-watermark {
                position: absolute; top: 50%; left: 50%;
                transform: translate(-50%, -50%) rotate(-20deg);
                font-size: 30pt; color: rgba(0,0,0,0.05);
                z-index: 0; font-weight: bold; pointer-events: none;
            }
        }
        #printable-tickets { display: none; }

        /* Mobile Fixes */
        .sidebar-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(2px); z-index: 999; display: none; }
        .sidebar-overlay.active { display: block; }

        @media screen and (max-width: 768px) {
            html, body { height: auto !important; overflow-y: auto !important; -webkit-overflow-scrolling: touch; }
            .content-area { overflow: visible !important; height: auto !important; padding: 0 !important; }
            .main-wrapper { height: auto !important; overflow: visible !important; display: block !important; margin-left: 0 !important; width: 100% !important; }
            .toggle-btn { display: flex; }
            .sidebar { position: fixed !important; top: 0; left: 0; width: 260px; height: 100vh; z-index: 1000; transform: translateX(-100%); transition: transform 0.3s ease-in-out; }
            .sidebar.active { transform: translateX(0); }
            .settings-container { flex-direction: column; gap: 20px; padding: 15px; }
            .settings-sidebar { width: 100%; margin-bottom: 0; }
            .navbar { padding: 15px 20px; }
            .data-table { display: block; overflow-x: auto; white-space: nowrap; }
            .ticket-scroll-area { max-height: none !important; overflow-y: visible !important; border: none; }
        }
    </style>
</head>
<body>

    <div id="loadingOverlay" class="loading-overlay"><div class="spinner"></div><div style="font-weight:600;">Processing...</div></div>
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <div class="main-wrapper">
        <?php require_once __DIR__ . '/../app/views/partials/sidebar.php'; ?>

        <div class="content-area">
            <div class="navbar no-print">
                <div class="toggle-btn" onclick="toggleSidebar()"><i class="fas fa-bars"></i></div>
                <div class="navbar-title">Settings</div>
            </div>

            <div class="container">
                <div id="toast-container" class="toast-container"></div>

                <div class="settings-container">
                    
                    <div class="settings-sidebar no-print">
                        <?php if ($is_event_manager): ?>
                            <a href="?tab=event_details" class="tab-btn <?= $active_tab == 'event_details' ? 'active' : '' ?>"><i class="fas fa-sliders-h"></i> Event Config</a>
                            <a href="?tab=tickets" class="tab-btn <?= $active_tab == 'tickets' ? 'active' : '' ?>"><i class="fas fa-ticket-alt"></i> Tickets</a>
                            <a href="?tab=reports" class="tab-btn <?= $active_tab == 'reports' ? 'active' : '' ?>"><i class="fas fa-file-invoice"></i> Reports</a>
                            <a href="?tab=history" class="tab-btn <?= $active_tab == 'history' ? 'active' : '' ?>"><i class="fas fa-history"></i> Switch Event</a>
                        <?php endif; ?>
                        <a href="?tab=account" class="tab-btn <?= $active_tab == 'account' ? 'active' : '' ?>"><i class="fas fa-user-cog"></i> Account</a>
                    </div>

                    <div class="settings-content">
                        <?php if ($is_event_manager): ?>
                            <div class="card <?= $active_tab == 'event_details' ? 'active' : '' ?>">
                                <div class="card-header"><div><h3>Event Config</h3><p>Edit details.</p></div></div>
                                <?php if (!$active_event): ?>
                                    <div style="text-align:center; padding:20px;">No active event.</div>
                                <?php else: ?>
                                    <form method="POST" onsubmit="showLoading()">
                                        <input type="hidden" name="event_id" value="<?= $active_event['id'] ?>">
                                        <div class="form-group"><label class="form-label">Title</label><input type="text" name="title" class="form-control" value="<?= htmlspecialchars($active_event['title']) ?>" required></div>
                                        <div class="form-group"><label class="form-label">Date</label><input type="date" name="event_date" class="form-control" value="<?= htmlspecialchars($active_event['event_date']) ?>" required></div>
                                        <div class="form-group"><label class="form-label">Venue</label><input type="text" name="venue" class="form-control" value="<?= htmlspecialchars($active_event['venue']) ?>" required></div>
                                        <div style="text-align:right;"><button type="submit" name="update_event_details" class="btn-save">Save</button></div>
                                    </form>
                                <?php endif; ?>
                            </div>

                            <div class="card <?= $active_tab == 'tickets' ? 'active' : '' ?>">
                                <div class="card-header">
                                    <div><h3>Tickets</h3></div>
                                    <?php if ($active_event): ?>
                                        <button class="btn-print no-print" onclick="window.print()"><i class="fas fa-print"></i> Print Cards</button>
                                    <?php endif; ?>
                                </div>
                                <?php if (!$active_event): ?><div style="text-align:center; padding:20px;">No event.</div><?php else: ?>
                                    <div class="stats-grid no-print">
                                        <div class="stat-box"><div class="stat-num" style="color:#059669;"><?= $total_unused ?></div><div class="stat-label">Unused</div></div>
                                        <div class="stat-box"><div class="stat-num" style="color:#dc2626;"><?= $total_used ?></div><div class="stat-label">Used</div></div>
                                    </div>
                                    <div style="margin-bottom:20px; background:#f9fafb; padding:15px; border-radius:8px;" class="no-print">
                                        <form action="../api/tickets.php" method="POST" style="display:flex; gap:10px; margin-bottom:10px;" onsubmit="showLoading()">
                                            <input type="hidden" name="action" value="generate"><input type="hidden" name="event_id" value="<?= $active_event['id'] ?>">
                                            <input type="number" name="quantity" class="form-control" placeholder="25" min="1" max="500" style="width:80px;">
                                            <button type="submit" class="btn-generate">Generate</button>
                                        </form>
                                        <form action="../api/tickets.php" method="POST" onsubmit="if(confirm('Delete unused tickets?')){ showLoading(); return true; } else { return false; }">
                                            <input type="hidden" name="action" value="clear_unused"><input type="hidden" name="event_id" value="<?= $active_event['id'] ?>">
                                            <button type="submit" class="btn-clear" style="width:100%;">Clear Unused</button>
                                        </form>
                                    </div>
                                    <div class="ticket-scroll-area no-print">
                                        <table class="ticket-table" id="printableTable"><thead><tr><th>#</th><th>Code</th><th>Status</th></tr></thead><tbody><?php foreach ($tickets as $i => $t): ?><tr><td><?= $i+1 ?></td><td><?= htmlspecialchars($t['ticket_code']) ?></td><td><span class="<?= ($t['status'] == 'Used') ? 'status-used' : 'status-unused' ?>"><?= $t['status'] ?></span></td></tr><?php endforeach; ?></tbody></table>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="card <?= $active_tab == 'reports' ? 'active' : '' ?>">
                                <div class="card-header"><div><h3>Reports</h3></div></div>
                                <?php if ($active_event): ?>
                                    <div class="report-item">
                                        <div class="report-icon icon-pdf"><i class="fas fa-file-pdf"></i></div>
                                        <div style="flex:1;"><h4 style="margin:0;">Tabulation Report</h4></div>
                                        <a href="print_report.php?event_id=<?= $active_event['id'] ?>" target="_blank" class="btn-report"><i class="fas fa-download"></i></a>
                                    </div>
                                <?php else: ?> No Event <?php endif; ?>
                            </div>

                            <div class="card <?= $active_tab == 'history' ? 'active' : '' ?>">
                                <div class="card-header">
                                    <div><h3>Switch Event</h3></div>
                                    <button onclick="openCreateModal()" style="background:#1f2937; color:white; border:none; padding:5px 10px; border-radius:6px;">+ New</button>
                                </div>
                                <div style="overflow-x:auto;">
                                    <table class="data-table">
                                        <thead><tr><th>Title</th><th>Status</th><th>Action</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($all_events as $evt): $is_curr = ($evt['status'] === 'Active'); ?>
                                            <tr>
                                                <td><?= htmlspecialchars($evt['title']) ?></td>
                                                <td><span class="badge <?= $is_curr ? 'badge-active' : 'badge-inactive' ?>"><?= $evt['status'] ?></span></td>
                                                <td>
                                                    <?php if (!$is_curr): ?>
                                                        <button onclick="openSwitchModal(<?= $evt['id'] ?>)" style="color:#2563eb; border:none; background:none; font-weight:bold;">Switch</button>
                                                        <button onclick="openRemoveModal(<?= $evt['id'] ?>)" style="color:#dc2626; border:none; background:none; font-weight:bold; margin-left:10px;">Remove</button>
                                                    <?php else: ?>
                                                        Active
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="card <?= $active_tab == 'account' ? 'active' : '' ?>">
                            <div class="card-header"><div><h3>Account</h3></div></div>
                            <form method="POST" onsubmit="showLoading()">
                                <div class="form-group"><label class="form-label">Name</label><input type="text" name="my_name" class="form-control" value="<?= htmlspecialchars($me['name']) ?>" required></div>
                                <div class="form-group"><label class="form-label">Email</label><input type="email" class="form-control" value="<?= htmlspecialchars($me['email']) ?>" disabled style="background:#f3f4f6;"></div>
                                <div class="form-group"><label class="form-label">Current Pass</label><input type="password" name="current_password" id="currPass" class="form-control" required><i class="fas fa-eye toggle-password" onclick="togglePassword('currPass', this)"></i></div>
                                <div class="form-group"><label class="form-label">New Pass</label><input type="password" name="my_password" id="newPass" class="form-control"><i class="fas fa-eye toggle-password" onclick="togglePassword('newPass', this)"></i></div>
                                <button type="submit" name="update_profile" class="btn-save" style="width:100%">Update</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($active_event && !empty($tickets)): ?>
    <div id="printable-tickets">
        <?php foreach($tickets as $t): ?>
            <div class="ticket-card">
                <div class="t-watermark">ADMIT ONE</div>
                
                <div class="t-header"><?= htmlspecialchars($active_event['title']) ?></div>
                <div class="t-venue"><?= htmlspecialchars($active_event['venue']) ?></div>
                <div style="font-size: 8pt; margin-bottom: 5px;"><?= date('F j, Y', strtotime($active_event['event_date'])) ?></div>
                
                <div>
                    <div class="t-code-box"><?= htmlspecialchars($t['ticket_code']) ?></div>
                </div>

                <div class="t-admit">
                    <i class="fas fa-star" style="font-size:8px;"></i> OFFICIAL TICKET <i class="fas fa-star" style="font-size:8px;"></i>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($is_event_manager): ?>
        <div id="createModal" class="modal-overlay">
            <div class="modal-content">
                <h3 style="margin-top:0;">New Event</h3>
                <form action="../api/event.php" method="POST" onsubmit="showLoading()">
                    <input type="hidden" name="action" value="create"> 
                    <div class="form-group"><label>Title</label><input type="text" name="title" class="form-control" required></div>
                    <div class="form-group"><label>Date</label><input type="date" name="event_date" class="form-control" required></div>
                    <div class="form-group"><label>Venue</label><input type="text" name="venue" class="form-control" required></div>
                    <div class="modal-actions"><button type="button" class="btn-cancel" onclick="document.getElementById('createModal').style.display='none'">Cancel</button><button type="submit" class="btn-confirm">Create</button></div>
                </form>
            </div>
        </div>
        <div id="switchModal" class="modal-overlay">
            <div class="modal-content">
                <h3 style="margin-top:0;">Confirm Switch</h3>
                <form method="POST" onsubmit="showLoading()">
                    <input type="hidden" name="switch_event" value="1"><input type="hidden" name="event_id" id="switchTargetId">
                    <div class="form-group"><input type="password" name="password_check" class="form-control" placeholder="Password" required></div>
                    <div class="modal-actions"><button type="button" class="btn-cancel" onclick="document.getElementById('switchModal').style.display='none'">Cancel</button><button type="submit" class="btn-confirm">Switch</button></div>
                </form>
            </div>
        </div>
        <div id="removeModal" class="modal-overlay">
            <div class="modal-content" style="border-top:5px solid #dc2626;">
                <h3 class="modal-title" style="color:#dc2626;">Remove Event?</h3>
                <div style="background:#fef2f2; padding:15px; margin-bottom:20px; color:#991b1b;">Archiving this event will hide its data.</div>
                <form method="POST" onsubmit="showLoading()">
                    <input type="hidden" name="remove_event" value="1"><input type="hidden" name="event_id" id="removeTargetId">
                    <div class="form-group"><input type="password" name="password_check" class="form-control" placeholder="Password" required></div>
                    <div class="modal-actions"><button type="button" class="btn-cancel" onclick="document.getElementById('removeModal').style.display='none'">Cancel</button><button type="submit" class="btn-danger">Confirm</button></div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <script>
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            if(sidebar) sidebar.classList.toggle('active');
            if(overlay) overlay.classList.toggle('active');
        }

        function showLoading() { document.getElementById('loadingOverlay').style.display = 'flex'; }
        
        function togglePassword(id, icon) {
            const x = document.getElementById(id);
            if (x.type === "password") { x.type = "text"; icon.classList.remove("fa-eye"); icon.classList.add("fa-eye-slash"); } 
            else { x.type = "password"; icon.classList.remove("fa-eye-slash"); icon.classList.add("fa-eye"); }
        }
        <?php if ($is_event_manager): ?>
        function openCreateModal() { document.getElementById('createModal').style.display = 'flex'; }
        function openSwitchModal(id) { document.getElementById('switchTargetId').value = id; document.getElementById('switchModal').style.display = 'flex'; }
        function openRemoveModal(id) { document.getElementById('removeTargetId').value = id; document.getElementById('removeModal').style.display = 'flex'; }
        <?php endif; ?>
        
        function showToast(message, type = 'success') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerText = message;
            container.appendChild(toast);
            setTimeout(() => { toast.remove(); }, 3500);
        }
        <?php if (isset($_SESSION['success'])): ?> showToast("<?= $_SESSION['success'] ?>", "success"); <?php unset($_SESSION['success']); ?> <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?> showToast("<?= $_SESSION['error'] ?>", "error"); <?php unset($_SESSION['error']); ?> <?php endif; ?>
    </script>
</body>
</html>