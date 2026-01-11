<?php
require_once __DIR__ . '/../app/core/guard.php';
requireLogin();
requireRole('Judge Coordinator');
require_once __DIR__ . '/../app/config/database.php';

$coordinator_id = $_SESSION['user_id'];
$message = "";
$error = "";

// --- 1. FETCH ASSIGNED EVENT (Must happen first to get Event ID) ---
$event_stmt = $conn->prepare("
    SELECT e.id, e.name, e.venue, e.event_date, e.status 
    FROM events e 
    JOIN event_organizers eo ON e.id = eo.event_id 
    WHERE eo.user_id = ? AND eo.status = 'Active' AND e.status = 'Active' 
    LIMIT 1
");
$event_stmt->bind_param("i", $coordinator_id);
$event_stmt->execute();
$event_result = $event_stmt->get_result();
$active_event = $event_result->fetch_assoc();

$event_id = $active_event['id'] ?? null;
$event_name = $active_event['name'] ?? "No Active Event Assigned";

// --- 2. HANDLE ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // A. Unlock Scorecard
    if (isset($_POST['action']) && $_POST['action'] === 'unlock_scorecard') {
        $judge_id_to_unlock = intval($_POST['judge_id']);
        $round_id_unlock = intval($_POST['round_id']);
        
        $stmt = $conn->prepare("UPDATE judge_round_status SET status = 'Pending' WHERE judge_id = ? AND round_id = ?");
        $stmt->bind_param("ii", $judge_id_to_unlock, $round_id_unlock);
        if ($stmt->execute()) {
            $message = "Scorecard unlocked successfully.";
        } else {
            $error = "Failed to unlock scorecard.";
        }
    }

    // B. Quick Add Judge (New Functionality)
    if (isset($_POST['action']) && $_POST['action'] === 'add_judge' && $event_id) {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        $is_chairman = isset($_POST['is_chairman']) ? 1 : 0;

        // 1. Check if user exists
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $res = $check->get_result();
        
        if ($res->num_rows > 0) {
            // User exists, just get ID
            $user_row = $res->fetch_assoc();
            $judge_user_id = $user_row['id'];
        } else {
            // Create new user
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $role = 'Judge';
            $ins = $conn->prepare("INSERT INTO users (name, email, password, role, created_by) VALUES (?, ?, ?, ?, ?)");
            $ins->bind_param("ssssi", $name, $email, $hashed, $role, $coordinator_id);
            if ($ins->execute()) {
                $judge_user_id = $ins->insert_id;
            } else {
                $error = "Failed to create user account.";
            }
        }

        if (isset($judge_user_id)) {
            // 2. Link to Event (Check duplicates first)
            $link_check = $conn->prepare("SELECT id FROM event_judges WHERE event_id = ? AND judge_id = ?");
            $link_check->bind_param("ii", $event_id, $judge_user_id);
            $link_check->execute();
            
            if ($link_check->get_result()->num_rows == 0) {
                $link = $conn->prepare("INSERT INTO event_judges (event_id, judge_id, is_chairman, status) VALUES (?, ?, ?, 'Active')");
                $link->bind_param("iii", $event_id, $judge_user_id, $is_chairman);
                if ($link->execute()) {
                    $message = "Judge added successfully!";
                } else {
                    $error = "Failed to assign judge to event.";
                }
            } else {
                $error = "This judge is already assigned to this event.";
            }
        }
    }
}

// --- 3. FETCH ACTIVE ROUND ---
$active_round_id = null;
$active_round_title = "No Active Round";

if ($event_id) {
    $round_stmt = $conn->prepare("SELECT id, title FROM rounds WHERE event_id = ? AND status = 'Active' LIMIT 1");
    $round_stmt->bind_param("i", $event_id);
    $round_stmt->execute();
    $round_res = $round_stmt->get_result();
    if ($r = $round_res->fetch_assoc()) {
        $active_round_id = $r['id'];
        $active_round_title = $r['title'];
    }
}

// --- 4. FETCH JUDGES & STATUSES ---
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
        WHERE ej.event_id = ? AND ej.status = 'Active'
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
        // Check strictly for 'Submitted' to update the counter accurately
        if ($row['round_status'] === 'Submitted') {
            $submitted_count++;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Judge Coordinator - BPMS</title>
    <link rel="stylesheet" href="./assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .status-badge { padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; }
        .status-badge.submitted { background-color: #D1FAE5; color: #059669; }
        .status-badge.pending { background-color: #FEF3C7; color: #D97706; }
        
        .btn-unlock { background: none; border: none; color: #DC2626; cursor: pointer; text-decoration: underline; font-size: 13px; }
        .btn-unlock:hover { color: #B91C1C; }
        
        .monitor-card { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); overflow: hidden; }
        .monitor-table { width: 100%; border-collapse: collapse; }
        .monitor-table th, .monitor-table td { padding: 15px 20px; text-align: left; border-bottom: 1px solid #f3f4f6; }
        .monitor-table th { background-color: #f9fafb; font-weight: 600; color: #374151; font-size: 13px; text-transform: uppercase; }
        .monitor-table tr:last-child td { border-bottom: none; }
        
        .sidebar { background-color: #111827; } 
        
        /* Modal Styles */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background: white; padding: 25px; width: 400px; border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .modal-header h3 { margin: 0; font-size: 18px; color: #111827; }
        .close-modal { background: none; border: none; font-size: 20px; cursor: pointer; color: #6b7280; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-size: 14px; font-weight: 600; color: #374151; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; }
        .btn-primary { width: 100%; padding: 10px; background-color: #F59E0B; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; }
        .btn-primary:hover { background-color: #d97706; }
        .chairman-tag { background-color: #4F46E5; color: white; padding: 2px 6px; border-radius: 4px; font-size: 10px; margin-left: 5px; vertical-align: middle; }
    </style>
</head>
<body>

    <div class="main-wrapper">
        
        <div class="sidebar">
            <div class="sidebar-header">
                <img src="assets/images/BPMS_logo.png" alt="BPMS Logo" class="sidebar-logo">
                <div class="brand-text">
                    <div class="brand-name">BPMS</div>
                    <div class="brand-subtitle">Coordinator Panel</div>
                </div>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="judge_coordinator.php" class="active"><i class="fas fa-gavel"></i> <span>Monitor Judges</span></a></li>
            </ul>
            
            <div class="sidebar-footer">
                <a href="settings.php">
                    <i class="fas fa-cog"></i> <span>Settings</span>
                </a>
                <a href="logout.php" onclick="return confirm('Logout?');">
                    <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
                </a>
            </div>
        </div>

        <div class="content-area">
            
            <div class="navbar">
                <div class="navbar-title">Judge Coordinator</div>
                <div style="font-size: 14px; color: #6b7280;">
                    Active Event: <strong><?= htmlspecialchars($event_name) ?></strong>
                </div>
            </div>

            <div class="container">
                
                <?php if ($message): ?>
                    <div class="toast success" style="margin-bottom: 20px; background: #10B981; color: white; padding: 10px; border-radius: 6px;">
                        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="toast error" style="margin-bottom: 20px; background: #EF4444; color: white; padding: 10px; border-radius: 6px;">
                        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="background-color: rgba(59, 130, 246, 0.1); color: #2563EB;">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?= $total_judges ?></h3>
                            <p>Total Judges</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon" style="background-color: rgba(16, 185, 129, 0.1); color: #10B981;">
                            <i class="fas fa-check-double"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?= $submitted_count ?> / <?= $total_judges ?></h3>
                            <p>Submitted (<?= htmlspecialchars($active_round_title) ?>)</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background-color: rgba(245, 158, 11, 0.1); color: #F59E0B;">
                            <i class="fas fa-layer-group"></i>
                        </div>
                        <div class="stat-info">
                            <h3 style="font-size: 18px;"><?= htmlspecialchars($active_round_title) ?></h3>
                            <p>Current Active Round</p>
                        </div>
                    </div>
                </div>

                <div class="card-section">
                    <div class="card-title" style="display:flex; justify-content:space-between; align-items:center;">
                        <span>Judge Monitoring Panel</span>
                        <div style="display:flex; gap:10px;">
                            <button onclick="document.getElementById('addJudgeModal').style.display='flex'" style="background:#F59E0B; border:none; padding:8px 12px; border-radius:4px; color:white; cursor:pointer; font-size:13px;">
                                <i class="fas fa-plus"></i> Add Judge
                            </button>
                            <button onclick="location.reload()" style="background:none; border:none; cursor:pointer; color:#6b7280;">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>
                    </div>
                    
                    <?php if (!$event_id): ?>
                        <p style="padding: 20px; color: #6b7280; text-align: center;">You are not assigned to an active event.</p>
                    <?php else: ?>
                    
                    <div class="monitor-card">
                        <table class="monitor-table">
                            <thead>
                                <tr>
                                    <th>Judge Name</th>
                                    <th>Email / Login ID</th>
                                    <th>Scoring Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($judges) > 0): ?>
                                    <?php foreach ($judges as $judge): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 600; color: #111827;">
                                                <?= htmlspecialchars($judge['name']) ?>
                                                <?php if($judge['is_chairman']): ?>
                                                    <span class="chairman-tag">CHAIRMAN</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($judge['email']) ?></td>
                                        <td>
                                            <?php if (!$active_round_id): ?>
                                                <span style="color:#9ca3af;">Round not started</span>
                                            <?php else: ?>
                                                <?php if ($judge['round_status'] === 'Submitted'): ?>
                                                    <span class="status-badge submitted"><i class="fas fa-check"></i> Submitted</span>
                                                    <div style="font-size: 11px; color: #6b7280; margin-top: 2px;">
                                                        at <?= date('h:i A', strtotime($judge['submitted_at'])) ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="status-badge pending"><i class="fas fa-hourglass-half"></i> Pending</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($active_round_id && $judge['round_status'] === 'Submitted'): ?>
                                                <form method="POST" onsubmit="return confirm('Are you sure you want to unlock this scorecard?');">
                                                    <input type="hidden" name="action" value="unlock_scorecard">
                                                    <input type="hidden" name="judge_id" value="<?= $judge['id'] ?>">
                                                    <input type="hidden" name="round_id" value="<?= $active_round_id ?>">
                                                    <button type="submit" class="btn-unlock"><i class="fas fa-lock-open"></i> Unlock / Re-open</button>
                                                </form>
                                            <?php elseif ($active_round_id): ?>
                                                <span style="color: #9ca3af; font-size: 13px;">Waiting for submission...</span>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" style="text-align:center; color:#6b7280;">No judges assigned to this event.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

    <div id="addJudgeModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Judge</h3>
                <button class="close-modal" onclick="document.getElementById('addJudgeModal').style.display='none'">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_judge">
                
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g. Juan Dela Cruz">
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" class="form-control" required placeholder="e.g. juan@bpms.com">
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" required placeholder="••••••••">
                </div>

                <div class="form-group" style="display:flex; align-items:center; gap:10px;">
                    <input type="checkbox" id="is_chairman" name="is_chairman" style="width:16px; height:16px;">
                    <label for="is_chairman" style="margin:0; cursor:pointer;">Assign as Chairman of Judges</label>
                </div>

                <button type="submit" class="btn-primary">Register Judge</button>
            </form>
        </div>
    </div>

    <script>
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target == document.getElementById('addJudgeModal')) {
                document.getElementById('addJudgeModal').style.display = "none";
            }
        }
    </script>

</body>
</html>