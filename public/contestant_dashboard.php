<?php
// public/contestant_dashboard.php

// 1. SECURITY & CONFIG
require_once __DIR__ . '/../app/core/guard.php';
requireLogin();
requireRole('Contestant');
require_once __DIR__ . '/../app/config/database.php';

$user_id = $_SESSION['user_id'];
$message = "";
$msg_type = ""; 

// 2. HANDLE FORM SUBMISSIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // A. Update Profile (Bio/Photo)
    if ($_POST['action'] === 'update_bio') {
        $new_motto = trim($_POST['motto']);
        $new_hometown = trim($_POST['hometown']);
        
        // Photo Upload
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $allowed_ext = ['jpg', 'jpeg', 'png'];
            $file_name = $_FILES['photo']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if (in_array($file_ext, $allowed_ext)) {
                $new_filename = "contestant_" . $user_id . "_" . time() . "." . $file_ext;
                $upload_dir = __DIR__ . '/assets/uploads/contestants/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

                if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $new_filename)) {
                    $photo_stmt = $conn->prepare("UPDATE event_contestants SET photo = ? WHERE user_id = ?");
                    $photo_stmt->bind_param("si", $new_filename, $user_id);
                    $photo_stmt->execute();
                }
            }
        }

        $upd = $conn->prepare("UPDATE event_contestants SET motto = ?, hometown = ? WHERE user_id = ?");
        $upd->bind_param("ssi", $new_motto, $new_hometown, $user_id);
        if ($upd->execute()) {
            $message = "Profile updated successfully.";
            $msg_type = "success";
        }
    }

    // B. Update Password
    if ($_POST['action'] === 'update_password') {
        $current_pass = $_POST['current_password'];
        $new_pass     = $_POST['new_password'];
        $confirm_pass = $_POST['confirm_password'];

        if ($new_pass !== $confirm_pass) {
            $message = "New passwords do not match.";
            $msg_type = "error";
        } elseif (strlen($new_pass) < 6) {
            $message = "Password must be at least 6 characters.";
            $msg_type = "error";
        } else {
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();

            if ($res && password_verify($current_pass, $res['password'])) {
                $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
                $upd_pass = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $upd_pass->bind_param("si", $new_hash, $user_id);
                if ($upd_pass->execute()) {
                    $message = "Password changed successfully.";
                    $msg_type = "success";
                }
            } else {
                $message = "Incorrect current password.";
                $msg_type = "error";
            }
        }
    }
}

// 3. FETCH DATA
$sql = "SELECT u.name, u.email, u.status, 
               ec.age, ec.height, 
               CONCAT(ec.bust, '-', ec.waist, '-', ec.hips) as vital_stats, 
               ec.hometown, ec.motto, ec.photo, ec.contestant_number,
               e.id as event_id, e.title as event_name, e.event_date, e.venue, e.status as event_status
        FROM users u 
        LEFT JOIN event_contestants ec ON u.id = ec.user_id 
        LEFT JOIN events e ON ec.event_id = e.id 
        WHERE u.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

// If the event is NOT Active (e.g. Inactive, Deleted, or Completed), log them out immediately.
if ($data['event_status'] !== 'Active') {
    // 1. Clear all session variables
    $_SESSION = [];

    // 2. Destroy the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // 3. Destroy the session storage
    session_destroy();

    // 4. Redirect to login with a clear explanation
    header("Location: index.php?error=" . urlencode("You were logged out because the event is no longer active."));
    exit();
}

// 4. FETCH SCHEDULE
$activities = [];
if ($data['event_id']) {
    $act_sql = "SELECT title, venue, activity_date, start_time FROM event_activities 
                WHERE event_id = ? AND is_deleted = 0 ORDER BY activity_date ASC, start_time ASC";
    $act_stmt = $conn->prepare($act_sql);
    $act_stmt->bind_param("i", $data['event_id']);
    $act_stmt->execute();
    $res = $act_stmt->get_result();
    while ($row = $res->fetch_assoc()) $activities[] = $row;
}

// Countdown
$days_left = 0;
if ($data['event_date']) {
    $event_date = new DateTime($data['event_date']);
    $today = new DateTime();
    if ($event_date > $today) $days_left = $today->diff($event_date)->days;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Candidate Portal - <?= htmlspecialchars($data['event_name']) ?></title>
    <link rel="stylesheet" href="./assets/css/style.css">
    <link rel="stylesheet" href="./assets/fontawesome/css/all.min.css">
    
    <style>
        /* --- RESET & BASICS --- */
        :root { --slate-900: #0f172a; --slate-800: #1e293b; --gold: #F59E0B; --gold-dark: #b45309; }
        * { box-sizing: border-box; }
        body { 
            background-color: #f1f5f9; 
            font-family: 'Segoe UI', sans-serif; 
            margin: 0; padding: 0; 
            min-height: 100vh;
            overflow-x: hidden; /* Prevent horizontal scroll */
            overflow-y: auto; 
            -webkit-overflow-scrolling: touch; 
        }

        /* --- HEADER --- */
        .page-header { 
            background: white; 
            padding: 10px 20px; 
            border-bottom: 1px solid #e2e8f0; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            position: sticky; 
            top: 0; 
            z-index: 100;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            width: 100%;
        }
        
        .header-brand { display: flex; align-items: center; gap: 12px; }
        .logo-img { height: 42px; width: auto; }
        .brand-text { display: flex; flex-direction: column; line-height: 1.2; }
        .brand-title { font-weight: 800; font-size: 1.2rem; color: var(--slate-900); letter-spacing: -0.5px; }
        .brand-sub { font-size: 0.75rem; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }

        .btn-logout {
            background: #ef4444; color: white; text-decoration: none;
            padding: 8px 16px; border-radius: 6px; font-weight: 600; font-size: 0.9rem;
            display: flex; align-items: center; gap: 8px; transition: background 0.2s;
            white-space: nowrap;
        }
        .btn-logout:hover { background: #dc2626; }

        /* --- LAYOUT --- */
        .container { 
            max-width: 1100px; 
            margin: 25px auto; 
            padding: 0 20px; /* Safe padding for sides */
            padding-bottom: 50px; 
            width: 100%;
        }
        
        .dashboard-grid { 
            display: grid; 
            grid-template-columns: 320px 1fr; 
            gap: 25px; 
            align-items: start; 
            width: 100%;
        }

        /* --- LEFT COLUMN: COMP CARD --- */
        .comp-card { 
            background: white; border-radius: 16px; overflow: hidden; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.05); text-align: center; 
            border: 1px solid #e2e8f0; 
            position: sticky; top: 90px;
            width: 100%;
        }
        .comp-header { background: var(--slate-900); height: 90px; position: relative; }
        .comp-header::after { content: ''; position: absolute; bottom: -1px; left: 0; width: 100%; height: 20px; background: white; border-radius: 20px 20px 0 0; }
        
        .profile-img { width: 130px; height: 130px; border-radius: 50%; object-fit: cover; border: 5px solid white; position: relative; margin-top: -65px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); background: #f1f5f9; }
        
        .comp-body { padding: 10px 20px 25px; }
        .candidate-name { font-size: 1.3rem; font-weight: 800; color: var(--slate-900); margin: 10px 0 5px; }
        .candidate-loc { color: var(--gold-dark); font-weight: 700; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 1px; margin-bottom: 15px; }
        .badge-number { background: var(--slate-900); color: var(--gold); display: inline-block; padding: 5px 15px; border-radius: 20px; font-weight: 800; font-size: 0.85rem; margin-bottom: 15px; }

        .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 15px; text-align: left; }
        .stat-box { background: #f8fafc; padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0; }
        .stat-label { font-size: 0.7rem; color: #64748b; text-transform: uppercase; font-weight: 700; }
        .stat-val { font-size: 0.9rem; font-weight: 600; color: var(--slate-900); }

        /* --- RIGHT COLUMN: MAIN PANEL --- */
        .action-center { width: 100%; }

        .welcome-banner { 
            background: linear-gradient(135deg, #F59E0B 0%, #d97706 100%); color: white; 
            padding: 20px 25px; border-radius: 12px; margin-bottom: 20px; 
            display: flex; justify-content: space-between; align-items: center; 
            box-shadow: 0 4px 8px rgba(245, 158, 11, 0.2); 
            width: 100%;
        }
        .wb-title { font-size: 1.4rem; font-weight: 800; line-height: 1.1; margin-bottom: 4px; }
        .wb-sub { font-size: 0.9rem; opacity: 0.95; display:flex; align-items:center; gap:6px; }
        .countdown { text-align: center; background: rgba(255,255,255,0.25); padding: 8px 16px; border-radius: 8px; backdrop-filter: blur(4px); white-space: nowrap; }
        .count-num { font-size: 1.6rem; font-weight: 800; line-height: 1; }
        .count-lbl { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 700; }

        .card-panel { 
            background: white; border-radius: 12px; padding: 25px; 
            border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.05); 
            min-height: 450px; 
            width: 100%;
        }
        
        /* TABS */
        .tabs-nav { display: flex; border-bottom: 1px solid #e2e8f0; margin-bottom: 25px; gap: 5px; width: 100%; }
        .tab-btn { 
            background: none; border: none; padding: 12px 20px; 
            font-size: 0.95rem; font-weight: 600; color: #64748b; 
            cursor: pointer; position: relative; bottom: -1px; transition: all 0.2s; 
        }
        .tab-btn:hover { color: var(--slate-900); background: #f8fafc; border-radius: 6px 6px 0 0; }
        .tab-btn.active { color: var(--slate-900); border-bottom: 3px solid var(--slate-900); background: white; }

        .tab-content { display: none; animation: fadeIn 0.3s ease; }
        .tab-content.active { display: block; }

        /* TIMELINE */
        .timeline { list-style: none; padding: 0; margin: 0; width: 100%; }
        .timeline li { position: relative; padding-left: 30px; margin-bottom: 25px; }
        .timeline li::before { content: ''; position: absolute; left: 0; top: 6px; width: 12px; height: 12px; border-radius: 50%; background: var(--gold); border: 2px solid white; box-shadow: 0 0 0 1px var(--gold); }
        .timeline li::after { content: ''; position: absolute; left: 5px; top: 22px; bottom: -15px; width: 2px; background: #e2e8f0; }
        .timeline li:last-child::after { display: none; }
        .tl-date { font-size: 0.8rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 4px; }
        .tl-title { font-weight: 700; color: var(--slate-900); font-size: 1.05rem; margin-bottom: 2px; }
        .tl-venue { font-size: 0.9rem; color: #64748b; }

        /* FORM ELEMENTS */
        .form-group { margin-bottom: 20px; width: 100%; }
        .form-group label { display: block; margin-bottom: 8px; font-size: 0.9rem; font-weight: 700; color: #334155; }
        .form-control { width: 100%; padding: 12px 15px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 1rem; color: #0f172a; max-width: 100%; }
        .form-control:focus { border-color: var(--gold); outline: none; box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.15); }
        .btn-save { background: var(--slate-900); color: white; border: none; padding: 14px 20px; border-radius: 8px; font-weight: 700; font-size: 1rem; cursor: pointer; width: 100%; transition: background 0.2s; }
        .btn-save:hover { background: #334155; }

        /* TOAST */
        .toast { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; display: flex; align-items: center; gap: 10px; font-size: 0.95rem; width: 100%; }
        .toast.success { background: #ecfdf5; color: #059669; border: 1px solid #d1fae5; }
        .toast.error { background: #fef2f2; color: #dc2626; border: 1px solid #fee2e2; }

        /* --- MOBILE RESPONSIVE FIX --- */
        @media (max-width: 900px) {
            
            /* Switch to Flexbox Column for better stacking */
            .dashboard-grid { 
                display: flex; 
                flex-direction: column; 
                gap: 25px; 
            }
            
            .container { 
                padding: 0 15px; /* Ensure 15px gap on edges */
                margin-top: 20px;
            }

            /* Header Adjustments */
            .page-header { padding: 12px 15px; }
            .brand-title { font-size: 1.1rem; }
            .brand-sub { display: none; } 
            .logo-img { height: 32px; }

            /* Comp Card Adjustments */
            .comp-card { 
                position: relative; 
                top: 0; 
                z-index: 1; 
                margin-bottom: 0;
            }
            .profile-img { width: 110px; height: 110px; margin-top: -55px; }
            .comp-header { height: 75px; }
            
            /* Banner Adjustments */
            .welcome-banner { flex-direction: column; text-align: center; gap: 15px; padding: 20px; }
            .wb-sub { justify-content: center; }

            /* Tabs Scrollable on Mobile */
            .tabs-nav { overflow-x: auto; white-space: nowrap; -webkit-overflow-scrolling: touch; }
            .tab-btn { flex: 0 0 auto; padding: 12px 20px; }
            
            /* Ensure forms don't overflow */
            .card-panel { padding: 20px; }
        }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>

    <header class="page-header">
        <div class="header-brand">
            <img src="./assets/images/BPMS_logo.png" alt="Logo" class="logo-img" onerror="this.style.display='none'"> 
            <div class="brand-text">
                <span class="brand-title">BPMS</span>
                <span class="brand-sub">Beauty Pageant Management System</span>
            </div>
        </div>
        
        <a href="logout.php" class="btn-logout" onclick="return confirm('Are you sure you want to log out?');">
            <i class="fas fa-sign-out-alt"></i> 
            <span>Logout</span>
        </a>
    </header>

    <div class="container">
        
        <?php if ($message): ?>
            <div class="toast <?= $msg_type ?>">
                <i class="fas <?= $msg_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="dashboard-grid">
            
            <div class="comp-card">
                <div class="comp-header"></div>
                <img src="./assets/uploads/contestants/<?= htmlspecialchars($data['photo']) ?>" onerror="this.src='./assets/images/default_user.png'" class="profile-img">
                
                <div class="comp-body">
                    <h2 class="candidate-name"><?= htmlspecialchars($data['name']) ?></h2>
                    <div class="candidate-loc"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($data['hometown']) ?></div>
                    <div class="badge-number">CANDIDATE #<?= str_pad($data['contestant_number'], 2, '0', STR_PAD_LEFT) ?></div>

                    <div class="stats-grid">
                        <div class="stat-box">
                            <div class="stat-label">Age</div>
                            <div class="stat-val"><?= $data['age'] ?></div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-label">Height</div>
                            <div class="stat-val"><?= htmlspecialchars($data['height']) ?></div>
                        </div>
                        <div class="stat-box" style="grid-column: span 2;">
                            <div class="stat-label">Vital Stats</div>
                            <div class="stat-val"><?= htmlspecialchars($data['vital_stats']) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="action-center">
                
                <div class="welcome-banner">
                    <div>
                        <div class="wb-title"><?= htmlspecialchars($data['event_name']) ?></div>
                        <div class="wb-sub"><i class="fas fa-map-pin"></i> <?= htmlspecialchars($data['venue']) ?></div>
                    </div>
                    <div class="countdown">
                        <div class="count-num"><?= $days_left ?></div>
                        <div class="count-lbl">Days Left</div>
                    </div>
                </div>

                <div class="card-panel">
                    <div class="tabs-nav">
                        <button class="tab-btn active" onclick="switchTab('timeline', event)">Schedule</button>
                        <button class="tab-btn" onclick="switchTab('profile', event)">My Profile</button>
                        <button class="tab-btn" onclick="switchTab('security', event)">Security</button>
                    </div>

                    <div id="tab-timeline" class="tab-content active">
                        <?php if (empty($activities)): ?>
                            <div style="text-align:center; padding:40px; color:#94a3b8;">
                                <i class="far fa-calendar-times" style="font-size:30px; margin-bottom:10px;"></i>
                                <p>No activities scheduled yet.</p>
                            </div>
                        <?php else: ?>
                            <ul class="timeline">
                                <?php foreach ($activities as $act): 
                                    $d = new DateTime($act['activity_date']);
                                    $t = new DateTime($act['start_time']);
                                ?>
                                <li>
                                    <div class="tl-date"><?= $d->format('M d') ?> • <?= $t->format('g:i A') ?></div>
                                    <div class="tl-title"><?= htmlspecialchars($act['title']) ?></div>
                                    <div class="tl-venue"><i class="fas fa-map-marker-alt" style="color:#ef4444"></i> <?= htmlspecialchars($act['venue']) ?></div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <div id="tab-profile" class="tab-content">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="update_bio">
                            
                            <div class="form-group">
                                <label>Official Hometown</label>
                                <input type="text" name="hometown" class="form-control" value="<?= htmlspecialchars($data['hometown']) ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Motto / Advocacy</label>
                                <textarea name="motto" rows="4" class="form-control" placeholder="Share your motto..."><?= htmlspecialchars($data['motto']) ?></textarea>
                            </div>

                            <div class="form-group">
                                <label>Update Profile Photo</label>
                                <input type="file" name="photo" accept="image/*" class="form-control">
                                <small style="color:#64748b; font-size:0.8rem; margin-top:5px; display:block;">Supported: JPG, PNG. Recommended: Square ratio.</small>
                            </div>

                            <button type="submit" class="btn-save">Save Profile Changes</button>
                        </form>
                    </div>

                    <div id="tab-security" class="tab-content">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_password">
                            
                            <div class="form-group">
                                <label>Current Password</label>
                                <input type="password" name="current_password" class="form-control" placeholder="Enter your current password" required>
                            </div>

                            <div class="form-group">
                                <label>New Password</label>
                                <input type="password" name="new_password" class="form-control" placeholder="Minimum 6 characters" required minlength="6">
                            </div>
                            
                            <div class="form-group">
                                <label>Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-control" placeholder="Repeat new password" required>
                            </div>

                            <button type="submit" class="btn-save" style="background:#dc2626;">Update Password</button>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabName, event) {
            if(event) event.preventDefault();
            
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            // Reset buttons
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            
            // Activate selected
            document.getElementById('tab-' + tabName).classList.add('active');
            // Highlight button
            if(event) event.target.classList.add('active');
        }
    </script>
</body>
</html>