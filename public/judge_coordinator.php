<?php
require_once __DIR__ . '/../app/core/guard.php';
requireLogin();
requireRole('Judge Coordinator');
require_once __DIR__ . '/../app/config/database.php';

$coordinator_id = $_SESSION['user_id'];

// --- 1. FETCH ASSIGNED EVENT ---
$event_stmt = $conn->prepare("
    SELECT e.id, e.title, e.venue, e.event_date, e.status 
    FROM events e 
    JOIN event_teams et ON e.id = et.event_id 
    WHERE et.user_id = ? AND et.status = 'Active' AND e.status = 'Active' AND et.is_deleted = 0
    LIMIT 1
");
$event_stmt->bind_param("i", $coordinator_id);
$event_stmt->execute();
$event_result = $event_stmt->get_result();
$active_event = $event_result->fetch_assoc();

$event_id = $active_event['id'] ?? null;
$event_name = $active_event['title'] ?? "No Active Event Assigned";

// --- 2. HANDLE ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // A. Unlock Scorecard
    if (isset($_POST['action']) && $_POST['action'] === 'unlock_scorecard') {
        $judge_id_to_unlock = intval($_POST['judge_id']);
        $round_id_unlock = intval($_POST['round_id']);
        
        $stmt = $conn->prepare("UPDATE judge_round_status SET status = 'Pending' WHERE judge_id = ? AND round_id = ?");
        $stmt->bind_param("ii", $judge_id_to_unlock, $round_id_unlock);
        
        if ($stmt->execute()) {
            header("Location: judge_coordinator.php?success=unlocked");
            exit();
        } else {
            header("Location: judge_coordinator.php?error=unlock_failed");
            exit();
        }
    }

    // B. Quick Add Judge
    if (isset($_POST['action']) && $_POST['action'] === 'add_judge' && $event_id) {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        $is_chairman = isset($_POST['is_chairman']) ? 1 : 0;

        // Check if user exists
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $res = $check->get_result();
        
        $judge_user_id = null;

        if ($res->num_rows > 0) {
            $user_row = $res->fetch_assoc();
            $judge_user_id = $user_row['id'];
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $role = 'Judge';
            $ins = $conn->prepare("INSERT INTO users (name, email, password, role, created_by) VALUES (?, ?, ?, ?, ?)");
            $ins->bind_param("ssssi", $name, $email, $hashed, $role, $coordinator_id);
            if ($ins->execute()) {
                $judge_user_id = $ins->insert_id;
            } else {
                header("Location: judge_coordinator.php?error=create_failed");
                exit();
            }
        }

        if ($judge_user_id) {
            $link_check = $conn->prepare("SELECT id FROM event_judges WHERE event_id = ? AND judge_id = ?");
            $link_check->bind_param("ii", $event_id, $judge_user_id);
            $link_check->execute();
            
            if ($link_check->get_result()->num_rows == 0) {
                $link = $conn->prepare("INSERT INTO event_judges (event_id, judge_id, is_chairman, status) VALUES (?, ?, ?, 'Active')");
                $link->bind_param("iii", $event_id, $judge_user_id, $is_chairman);
                if ($link->execute()) {
                    header("Location: judge_coordinator.php?success=added");
                    exit();
                }
            } else {
                header("Location: judge_coordinator.php?error=duplicate");
                exit();
            }
        }
    }
}

// --- 3. FETCH ACTIVE ROUND ---
$active_round_id = null;
$active_round_title = "No Active Round";

if ($event_id) {
    $round_stmt = $conn->prepare("SELECT id, title FROM rounds WHERE event_id = ? AND status = 'Active' AND is_deleted = 0 LIMIT 1");
    $round_stmt->bind_param("i", $event_id);
    $round_stmt->execute();
    $round_res = $round_stmt->get_result();
    if ($r = $round_res->fetch_assoc()) {
        $active_round_id = $r['id'];
        $active_round_title = $r['title'];
    }
}

// --- 4. FETCH JUDGES ---
$judges = [];
$total_judges = 0;
$submitted_count = 0;

if ($event_id) {
    $sql = "
        SELECT u.id, u.name, u.email, ej.is_chairman, ej.status as judge_role_status,
               COALESCE(MAX(jrs.status), 'Pending') as round_status,
               MAX(jrs.submitted_at) as submitted_at
        FROM users u
        JOIN event_judges ej ON u.id = ej.judge_id
        LEFT JOIN judge_round_status jrs ON u.id = jrs.judge_id AND jrs.round_id = ?
        WHERE ej.event_id = ? AND ej.status = 'Active' AND ej.is_deleted = 0
        GROUP BY u.id
        ORDER BY ej.is_chairman DESC, u.name ASC
    ";
    
    $bind_round = $active_round_id ?? 0;
    $j_stmt = $conn->prepare($sql);
    $j_stmt->bind_param("ii", $bind_round, $event_id);
    $j_stmt->execute();
    $j_result = $j_stmt->get_result();
    
    while ($row = $j_result->fetch_assoc()) {
        $judges[] = $row;
        $total_judges++;
        if ($row['round_status'] === 'Submitted') {
            $submitted_count++;
        }
    }
}

// Toast Logic
$message = ""; $error = "";
if (isset($_GET['success'])) {
    if ($_GET['success'] == 'unlocked') $message = "Scorecard unlocked successfully.";
    if ($_GET['success'] == 'added') $message = "Judge added successfully!";
}
if (isset($_GET['error'])) {
    if ($_GET['error'] == 'unlock_failed') $error = "Failed to unlock scorecard.";
    if ($_GET['error'] == 'create_failed') $error = "Failed to create user account.";
    if ($_GET['error'] == 'duplicate') $error = "This judge is already assigned.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Judge Coordinator</title>
    <link rel="stylesheet" href="./assets/css/style.css">
    <link rel="stylesheet" href="./assets/fontawesome/css/all.min.css">
    <style>
        :root { --primary: #F59E0B; --dark: #111827; --bg: #f3f4f6; }
        
        body { 
            background-color: var(--bg); font-family: 'Segoe UI', sans-serif; 
            margin: 0; padding-bottom: 60px; overflow-y: auto; -webkit-overflow-scrolling: touch; 
        }

        /* NAVBAR */
        .navbar {
            background: var(--dark); color: white; height: 60px;
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 15px; position: sticky; top: 0; z-index: 100;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .navbar-title { font-size: 16px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 200px; }
        .btn-logout {
            background: rgba(255,255,255,0.1); color: #fca5a5; text-decoration: none;
            padding: 8px 12px; border-radius: 6px; font-size: 13px; font-weight: 600;
            display: flex; align-items: center; gap: 6px;
        }

        /* CONTAINER */
        .container { max-width: 1200px; margin: 0 auto; padding: 15px; }

        /* STATS GRID */
        .stats-grid { 
            display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); 
            gap: 10px; margin-bottom: 20px; 
        }
        .stat-card { 
            background: white; padding: 15px; border-radius: 12px; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; 
            align-items: center; gap: 15px; border-left: 4px solid var(--primary);
        }
        .stat-icon { 
            width: 40px; height: 40px; border-radius: 50%; display: flex; 
            align-items: center; justify-content: center; font-size: 18px; 
        }
        .stat-info h3 { margin: 0; font-size: 20px; color: #111827; }
        .stat-info p { margin: 0; font-size: 12px; color: #6b7280; font-weight: 600; }

        /* HEADER & ACTIONS */
        .page-header { 
            display: flex; justify-content: space-between; align-items: center; 
            margin-bottom: 15px; background: white; padding: 15px; 
            border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .header-title { font-size: 16px; font-weight: 700; color: #374151; }
        .btn-add { 
            background: var(--primary); color: white; border: none; 
            padding: 10px 16px; border-radius: 8px; font-weight: 700; 
            font-size: 13px; cursor: pointer; display: flex; align-items: center; gap: 6px;
        }

        /* RESPONSIVE TABLE / CARDS */
        .monitor-container { background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden; }
        
        /* Desktop Table View */
        .monitor-table { width: 100%; border-collapse: collapse; display: none; } /* Hidden on mobile */
        .monitor-table th, .monitor-table td { padding: 15px; text-align: left; border-bottom: 1px solid #f3f4f6; }
        .monitor-table th { background: #f9fafb; font-size: 12px; text-transform: uppercase; color: #6b7280; }
        
        /* Mobile Card View */
        .mobile-list { display: flex; flex-direction: column; gap: 10px; }
        .judge-card { 
            background: white; padding: 15px; border-radius: 12px; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid #e5e7eb;
            display: flex; flex-direction: column; gap: 10px;
        }
        .jc-header { display: flex; justify-content: space-between; align-items: center; }
        .jc-name { font-weight: 700; color: #111827; font-size: 15px; }
        .jc-email { color: #6b7280; font-size: 12px; }
        .jc-status { display: flex; justify-content: space-between; align-items: center; background: #f9fafb; padding: 8px; border-radius: 6px; font-size: 12px; }
        
        /* Badges & Buttons */
        .badge { padding: 4px 8px; border-radius: 10px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .badge-submitted { background: #d1fae5; color: #065f46; }
        .badge-pending { background: #fef3c7; color: #b45309; }
        .chairman-tag { background: #4f46e5; color: white; padding: 2px 6px; border-radius: 4px; font-size: 9px; margin-left: 5px; vertical-align: middle; }

        .btn-unlock { 
            background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; 
            padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; 
            cursor: pointer; display: inline-flex; align-items: center; gap: 5px; text-decoration: none;
        }

        /* MEDIA QUERY */
        @media(min-width: 768px) {
            .monitor-table { display: table; }
            .mobile-list { display: none; }
        }

        /* MODAL */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); display: none; justify-content: center; align-items: center; z-index: 2000; backdrop-filter: blur(2px); }
        .modal-content { background: white; width: 90%; max-width: 400px; padding: 20px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        .form-label { display: block; margin-bottom: 5px; font-size: 13px; font-weight: 600; color: #374151; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; box-sizing: border-box; }
        .btn-submit { width: 100%; padding: 12px; background: var(--primary); color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="logout.php" class="btn-logout" onclick="return confirm('Logout?')">
        <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
    </a>
    <div class="navbar-title">Coordinator Panel</div>
    <div style="width: 70px;"></div>
</nav>

<div class="container">
    <div id="toast-container"></div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: #eff6ff; color: #2563eb;"><i class="fas fa-users"></i></div>
            <div class="stat-info">
                <h3><?= $total_judges ?></h3>
                <p>Total Judges</p>
            </div>
        </div>
        <div class="stat-card" style="border-left-color: #10b981;">
            <div class="stat-icon" style="background: #d1fae5; color: #059669;"><i class="fas fa-check-double"></i></div>
            <div class="stat-info">
                <h3><?= $submitted_count ?> / <?= $total_judges ?></h3>
                <p>Submitted</p>
            </div>
        </div>
        <div class="stat-card" style="border-left-color: #6366f1;">
            <div class="stat-icon" style="background: #e0e7ff; color: #4338ca;"><i class="fas fa-layer-group"></i></div>
            <div class="stat-info">
                <h3 style="font-size:16px;"><?= htmlspecialchars($active_round_title) ?></h3>
                <p>Active Round</p>
            </div>
        </div>
    </div>

    <div class="page-header">
        <div class="header-title">
            Judge Monitoring
            <div style="font-size:12px; color:#6b7280; font-weight:400;"><?= htmlspecialchars($event_name) ?></div>
        </div>
        <button class="btn-add" onclick="document.getElementById('addJudgeModal').style.display='flex'">
            <i class="fas fa-plus"></i> Add Judge
        </button>
    </div>

    <?php if (empty($judges)): ?>
        <div style="text-align:center; padding:40px; color:#9ca3af; background:white; border-radius:12px;">
            <i class="fas fa-user-slash" style="font-size:32px; margin-bottom:10px;"></i>
            <p>No judges assigned to this event.</p>
        </div>
    <?php else: ?>
        
        <div class="monitor-container">
            <table class="monitor-table">
                <thead>
                    <tr>
                        <th>Judge Name</th>
                        <th>Email / ID</th>
                        <th>Status (<?= htmlspecialchars($active_round_title) ?>)</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($judges as $j): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($j['name']) ?></strong>
                            <?php if($j['is_chairman']): ?><span class="chairman-tag">CHAIRMAN</span><?php endif; ?>
                        </td>
                        <td style="color:#6b7280; font-size:13px;"><?= htmlspecialchars($j['email']) ?></td>
                        <td>
                            <?php if ($j['round_status'] === 'Submitted'): ?>
                                <span class="badge badge-submitted"><i class="fas fa-check"></i> Submitted</span>
                                <div style="font-size:11px; color:#6b7280; margin-top:4px;">
                                    <?= date('h:i A', strtotime($j['submitted_at'])) ?>
                                </div>
                            <?php else: ?>
                                <span class="badge badge-pending"><i class="fas fa-clock"></i> Pending</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($active_round_id && $j['round_status'] === 'Submitted'): ?>
                                <form method="POST" onsubmit="return confirm('Unlock scorecard for <?= $j['name'] ?>?');">
                                    <input type="hidden" name="action" value="unlock_scorecard">
                                    <input type="hidden" name="judge_id" value="<?= $j['id'] ?>">
                                    <input type="hidden" name="round_id" value="<?= $active_round_id ?>">
                                    <button type="submit" class="btn-unlock"><i class="fas fa-lock-open"></i> Unlock</button>
                                </form>
                            <?php else: ?>
                                <span style="color:#d1d5db;">--</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mobile-list">
            <?php foreach ($judges as $j): ?>
            <div class="judge-card">
                <div class="jc-header">
                    <div class="jc-name">
                        <?= htmlspecialchars($j['name']) ?>
                        <?php if($j['is_chairman']): ?><span class="chairman-tag">CHAIRMAN</span><?php endif; ?>
                    </div>
                </div>
                <div class="jc-email"><?= htmlspecialchars($j['email']) ?></div>
                
                <div class="jc-status">
                    <span>Round Status:</span>
                    <?php if ($j['round_status'] === 'Submitted'): ?>
                        <span style="color:#059669; font-weight:700;"><i class="fas fa-check"></i> Submitted</span>
                    <?php else: ?>
                        <span style="color:#d97706; font-weight:700;"><i class="fas fa-clock"></i> Pending</span>
                    <?php endif; ?>
                </div>

                <?php if ($active_round_id && $j['round_status'] === 'Submitted'): ?>
                    <form method="POST" style="width:100%;" onsubmit="return confirm('Unlock scorecard?');">
                        <input type="hidden" name="action" value="unlock_scorecard">
                        <input type="hidden" name="judge_id" value="<?= $j['id'] ?>">
                        <input type="hidden" name="round_id" value="<?= $active_round_id ?>">
                        <button type="submit" class="btn-unlock" style="width:100%; justify-content:center;">
                            <i class="fas fa-lock-open"></i> Unlock Scorecard
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>
</div>

<div id="addJudgeModal" class="modal-overlay">
    <div class="modal-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
            <h3 style="margin:0;">Add New Judge</h3>
            <button onclick="document.getElementById('addJudgeModal').style.display='none'" style="background:none; border:none; font-size:24px;">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_judge">
            
            <div class="form-group">
                <label class="form-label">Name</label>
                <input type="text" name="name" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="text" name="password" class="form-control" required>
            </div>
            <div class="form-group" style="display:flex; align-items:center; gap:10px;">
                <input type="checkbox" id="is_chairman" name="is_chairman" style="width:18px; height:18px;">
                <label for="is_chairman" style="margin:0;">Assign as Chairman</label>
            </div>
            <button type="submit" class="btn-submit">Register Judge</button>
        </form>
    </div>
</div>

<script>
    // Simple Toast Logic
    function showToast(msg, type='success') {
        const div = document.createElement('div');
        div.style.position = 'fixed'; div.style.top = '20px'; div.style.right = '20px';
        div.style.padding = '12px 20px'; div.style.borderRadius = '8px'; div.style.color = 'white';
        div.style.background = type === 'success' ? '#10B981' : '#EF4444';
        div.style.zIndex = '3000'; div.style.boxShadow = '0 4px 6px rgba(0,0,0,0.1)';
        div.innerHTML = msg;
        document.body.appendChild(div);
        setTimeout(() => div.remove(), 3000);
    }

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('success')) {
        if (urlParams.get('success') === 'unlocked') showToast('Scorecard Unlocked');
        if (urlParams.get('success') === 'added') showToast('Judge Added Successfully');
        window.history.replaceState({}, document.title, window.location.pathname);
    }
    if (urlParams.has('error')) {
        showToast(urlParams.get('error'), 'error');
        window.history.replaceState({}, document.title, window.location.pathname);
    }
</script>

</body>
</html>